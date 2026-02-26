<?php
/**
 * Site Scanner Class
 *
 * Discovers and caches the WordPress site structure:
 * post types, taxonomies, content statistics, ACF fields, and meta keys.
 *
 * @package Quarry
 * @since   2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class QRY_Site_Scanner {

	/**
	 * Transient key for cached scan results.
	 */
	const CACHE_KEY = 'qry_site_scan';

	/**
	 * Cache TTL in seconds (6 hours).
	 */
	const CACHE_TTL = 21600;

	/**
	 * Transient key for field discovery cache (per post type).
	 */
	const FIELD_CACHE_PREFIX = 'qry_fields_';

	/**
	 * Field cache TTL in seconds (1 hour).
	 */
	const FIELD_CACHE_TTL = 3600;

	/**
	 * Run a full site scan (Layers 1 + 2).
	 *
	 * @param bool $force_refresh Clear cache before scanning.
	 * @return array Scan results.
	 */
	public static function scan( $force_refresh = false ) {
		if ( $force_refresh ) {
			delete_transient( self::CACHE_KEY );
		}

		$cached = get_transient( self::CACHE_KEY );
		if ( false !== $cached ) {
			return $cached;
		}

		$results = array(
			'structure'  => self::scan_structure(),
			'statistics' => self::scan_statistics(),
			'scanned_at' => current_time( 'mysql' ),
		);

		set_transient( self::CACHE_KEY, $results, self::CACHE_TTL );

		return $results;
	}

	/**
	 * Clear all scan caches.
	 */
	public static function clear_cache() {
		delete_transient( self::CACHE_KEY );

		// Clear per-post-type field caches.
		$post_types = get_post_types( array(), 'names' );
		foreach ( $post_types as $pt ) {
			delete_transient( self::FIELD_CACHE_PREFIX . $pt );
		}
	}

	// =========================================================================
	// Layer 1: Structural Discovery
	// =========================================================================

	/**
	 * Discover site structure: post types, taxonomies, statuses, plugins.
	 *
	 * @return array Structure data.
	 */
	private static function scan_structure() {
		return array(
			'post_types'   => self::get_post_types(),
			'taxonomies'   => self::get_taxonomies(),
			'type_tax_map' => self::get_type_taxonomy_map(),
			'statuses'     => self::get_statuses(),
			'plugins'      => self::detect_plugins(),
		);
	}

	/**
	 * Get all registered post types.
	 *
	 * @return array Post types keyed by slug.
	 */
	private static function get_post_types() {
		$types  = get_post_types( array(), 'objects' );
		$result = array();

		foreach ( $types as $slug => $type ) {
			$result[ $slug ] = array(
				'label'        => $type->label,
				'public'       => $type->public,
				'hierarchical' => $type->hierarchical,
				'has_archive'  => $type->has_archive,
				'show_ui'      => $type->show_ui,
				'show_in_rest' => $type->show_in_rest,
				'menu_icon'    => $type->menu_icon,
				'supports'     => get_all_post_type_supports( $slug ),
				'builtin'      => $type->_builtin,
			);
		}

		return $result;
	}

	/**
	 * Get all registered taxonomies.
	 *
	 * @return array Taxonomies keyed by slug.
	 */
	private static function get_taxonomies() {
		$taxes  = get_taxonomies( array(), 'objects' );
		$result = array();

		foreach ( $taxes as $slug => $tax ) {
			$result[ $slug ] = array(
				'label'        => $tax->label,
				'public'       => $tax->public,
				'hierarchical' => $tax->hierarchical,
				'show_ui'      => $tax->show_ui,
				'show_in_rest' => $tax->show_in_rest,
				'object_types' => $tax->object_type,
				'builtin'      => $tax->_builtin,
			);
		}

		return $result;
	}

	/**
	 * Build post type to taxonomy mapping.
	 *
	 * @return array Map of post_type => array of taxonomy slugs.
	 */
	private static function get_type_taxonomy_map() {
		$map = array();

		foreach ( get_post_types( array(), 'names' ) as $pt ) {
			$taxes = get_object_taxonomies( $pt );
			if ( ! empty( $taxes ) ) {
				$map[ $pt ] = $taxes;
			}
		}

		return $map;
	}

	/**
	 * Get all registered post statuses.
	 *
	 * @return array Statuses keyed by slug.
	 */
	private static function get_statuses() {
		$statuses = get_post_stati( array(), 'objects' );
		$result   = array();

		foreach ( $statuses as $slug => $status ) {
			$result[ $slug ] = array(
				'label'    => $status->label,
				'public'   => $status->public,
				'internal' => $status->internal,
			);
		}

		return $result;
	}

	/**
	 * Detect active plugins relevant to data management.
	 *
	 * @return array Detected plugins with version info.
	 */
	private static function detect_plugins() {
		$detected = array();

		// ACF / ACF Pro.
		if ( class_exists( 'ACF' ) ) {
			$detected['acf'] = array(
				'name'    => defined( 'ACF_PRO' ) ? 'ACF Pro' : 'ACF',
				'version' => defined( 'ACF_VERSION' ) ? ACF_VERSION : 'unknown',
				'active'  => true,
			);
		}

		// Yoast SEO.
		if ( defined( 'WPSEO_VERSION' ) ) {
			$detected['yoast'] = array(
				'name'    => 'Yoast SEO',
				'version' => WPSEO_VERSION,
				'active'  => true,
			);
		}

		// WooCommerce.
		if ( defined( 'WC_VERSION' ) ) {
			$detected['woocommerce'] = array(
				'name'    => 'WooCommerce',
				'version' => WC_VERSION,
				'active'  => true,
			);
		}

		// Gravity Forms.
		if ( class_exists( 'GFForms' ) ) {
			$detected['gravity_forms'] = array(
				'name'    => 'Gravity Forms',
				'version' => class_exists( 'GFCommon' ) ? GFCommon::$version : 'unknown',
				'active'  => true,
			);
		}

		return $detected;
	}

	// =========================================================================
	// Layer 2: Content Statistics
	// =========================================================================

	/**
	 * Gather content statistics per post type and taxonomy.
	 *
	 * @return array Statistics.
	 */
	private static function scan_statistics() {
		$stats = array(
			'post_types'  => array(),
			'taxonomies'  => array(),
			'media_count' => self::get_media_count(),
		);

		// Count posts per type (only types with UI).
		$types = get_post_types( array( 'show_ui' => true ), 'names' );
		foreach ( $types as $pt ) {
			$counts = wp_count_posts( $pt );
			$total  = 0;
			$breakdown = array();

			foreach ( $counts as $status => $count ) {
				$count = (int) $count;
				if ( $count > 0 ) {
					$breakdown[ $status ] = $count;
					$total += $count;
				}
			}

			if ( $total > 0 ) {
				$stats['post_types'][ $pt ] = array(
					'total'     => $total,
					'breakdown' => $breakdown,
				);
			}
		}

		// Count terms per taxonomy (only those with UI).
		$taxes = get_taxonomies( array( 'show_ui' => true ), 'names' );
		foreach ( $taxes as $tax ) {
			$count = wp_count_terms( array( 'taxonomy' => $tax ) );
			if ( ! is_wp_error( $count ) && (int) $count > 0 ) {
				$stats['taxonomies'][ $tax ] = (int) $count;
			}
		}

		return $stats;
	}

	/**
	 * Get media library count.
	 *
	 * @return int Total attachments.
	 */
	private static function get_media_count() {
		$counts = wp_count_posts( 'attachment' );
		return isset( $counts->inherit ) ? (int) $counts->inherit : 0;
	}

	// =========================================================================
	// Layer 3: Field Discovery (on-demand, per post type)
	// =========================================================================

	/**
	 * Discover fields for a specific post type.
	 *
	 * Combines ACF field groups, raw meta keys, and Yoast fields.
	 * Results are cached per post type with a 1-hour TTL.
	 *
	 * @param string $post_type Post type slug.
	 * @param bool   $force_refresh Clear cache first.
	 * @return array Field data.
	 */
	public static function discover_fields( $post_type, $force_refresh = false ) {
		$cache_key = self::FIELD_CACHE_PREFIX . $post_type;

		if ( $force_refresh ) {
			delete_transient( $cache_key );
		}

		$cached = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$fields = array(
			'acf_groups' => array(),
			'meta_keys'  => array(),
			'yoast'      => false,
		);

		// ACF field groups and fields.
		if ( function_exists( 'acf_get_field_groups' ) ) {
			$groups = acf_get_field_groups( array( 'post_type' => $post_type ) );

			foreach ( $groups as $group ) {
				$group_fields = acf_get_fields( $group['ID'] );
				$field_list   = array();

				if ( $group_fields ) {
					foreach ( $group_fields as $field ) {
						$field_list[] = array(
							'name'     => $field['name'],
							'label'    => $field['label'],
							'type'     => $field['type'],
							'required' => ! empty( $field['required'] ),
						);
					}
				}

				$fields['acf_groups'][] = array(
					'title'       => $group['title'],
					'key'         => $group['key'],
					'field_count' => count( $field_list ),
					'fields'      => $field_list,
				);
			}
		}

		// Raw meta keys from postmeta table.
		$fields['meta_keys'] = self::get_meta_keys_for_type( $post_type );

		// Yoast SEO fields present?
		if ( defined( 'WPSEO_VERSION' ) ) {
			$fields['yoast'] = true;
		}

		set_transient( $cache_key, $fields, self::FIELD_CACHE_TTL );

		return $fields;
	}

	/**
	 * Get distinct meta keys used by a post type.
	 *
	 * @param string $post_type Post type slug.
	 * @return array Meta keys with usage counts.
	 */
	private static function get_meta_keys_for_type( $post_type ) {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm.meta_key, COUNT(*) as usage_count
				 FROM {$wpdb->postmeta} pm
				 INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
				 WHERE p.post_type = %s
				   AND p.post_status IN ('publish', 'draft', 'pending', 'private')
				   AND pm.meta_key NOT LIKE '\_%%'
				 GROUP BY pm.meta_key
				 ORDER BY usage_count DESC
				 LIMIT 200",
				$post_type
			)
		);

		$keys = array();
		if ( $results ) {
			foreach ( $results as $row ) {
				$keys[] = array(
					'key'   => $row->meta_key,
					'count' => (int) $row->usage_count,
				);
			}
		}

		return $keys;
	}

	// =========================================================================
	// Helpers
	// =========================================================================

	/**
	 * Get a filtered list of "useful" post types for UI display.
	 *
	 * Excludes internal WP types that users don't care about.
	 *
	 * @param array|null $scan Scan results (will run scan if null).
	 * @return array Filtered post types.
	 */
	public static function get_useful_post_types( $scan = null ) {
		if ( null === $scan ) {
			$scan = self::scan();
		}

		$exclude = array(
			'revision',
			'nav_menu_item',
			'custom_css',
			'customize_changeset',
			'oembed_cache',
			'user_request',
			'wp_block',
			'wp_template',
			'wp_template_part',
			'wp_global_styles',
			'wp_navigation',
			'wp_font_family',
			'wp_font_face',
			'acf-field-group',
			'acf-field',
			'acf-taxonomy',
			'acf-post-type',
			'acf-ui-options-page',
		);

		$types = $scan['structure']['post_types'];

		return array_filter( $types, function ( $type, $slug ) use ( $exclude ) {
			return ! in_array( $slug, $exclude, true ) && $type['show_ui'];
		}, ARRAY_FILTER_USE_BOTH );
	}

	/**
	 * Get a filtered list of "useful" taxonomies for UI display.
	 *
	 * @param array|null $scan Scan results.
	 * @return array Filtered taxonomies.
	 */
	public static function get_useful_taxonomies( $scan = null ) {
		if ( null === $scan ) {
			$scan = self::scan();
		}

		$exclude = array(
			'nav_menu',
			'link_category',
			'post_format',
			'wp_theme',
			'wp_template_part_area',
			'wp_pattern_category',
		);

		$taxes = $scan['structure']['taxonomies'];

		return array_filter( $taxes, function ( $tax, $slug ) use ( $exclude ) {
			return ! in_array( $slug, $exclude, true ) && $tax['show_ui'];
		}, ARRAY_FILTER_USE_BOTH );
	}
}
