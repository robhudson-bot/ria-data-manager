<?php
/**
 * GitHub Updater.
 *
 * Enables automatic updates from GitHub releases.
 * Checks for new releases and integrates with WordPress update system.
 *
 * @package RIA_Data_Manager
 * @since   1.3.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RIA_DM_GitHub_Updater
 *
 * Handles plugin updates from GitHub releases.
 *
 * @since 1.3.0
 */
class RIA_DM_GitHub_Updater {

	/**
	 * GitHub repository owner.
	 *
	 * @var string
	 */
	private $owner = 'robhudson-bot';

	/**
	 * GitHub repository name.
	 *
	 * @var string
	 */
	private $repo = 'ria-data-manager';

	/**
	 * Plugin slug.
	 *
	 * @var string
	 */
	private $slug;

	/**
	 * Plugin basename.
	 *
	 * @var string
	 */
	private $basename;

	/**
	 * Current plugin version.
	 *
	 * @var string
	 */
	private $version;

	/**
	 * GitHub Personal Access Token for accessing the release repo.
	 *
	 * Uses the RIA_DM_UPDATE_TOKEN constant if defined and non-empty.
	 *
	 * @var string
	 */
	private $token;

	/**
	 * Cached GitHub response.
	 *
	 * @var object|null
	 */
	private $github_response = null;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->slug     = 'ria-data-manager';
		$this->basename = RIA_DM_PLUGIN_BASENAME;
		$this->version  = RIA_DM_VERSION;

		if ( defined( 'RIA_DM_UPDATE_TOKEN' ) && RIA_DM_UPDATE_TOKEN ) {
			$this->token = RIA_DM_UPDATE_TOKEN;
		} else {
			$this->token = '';
		}
	}

	/**
	 * Initialize the updater.
	 */
	public function init() {
		// Check for updates.
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_update' ) );

		// Provide plugin info for the update details popup.
		add_filter( 'plugins_api', array( $this, 'plugin_info' ), 20, 3 );

		// After install, make sure plugin is in the right folder.
		add_filter( 'upgrader_post_install', array( $this, 'after_install' ), 10, 3 );

		// Add "Check for updates" link on plugins page.
		add_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 2 );

		// Handle manual update check.
		add_action( 'admin_init', array( $this, 'handle_manual_check' ) );

		// Set up download auth filters just before WordPress downloads the package.
		if ( ! empty( $this->token ) ) {
			add_filter( 'upgrader_pre_download', array( $this, 'setup_download_filters' ), 10, 1 );
		}
	}

	/**
	 * Set up HTTP filters for authenticated downloads.
	 *
	 * Called via upgrader_pre_download just before WordPress downloads
	 * the package. Registers the auth header injection and the redirect
	 * auth stripping filters.
	 *
	 * @param mixed $reply Download reply (pass through).
	 * @return mixed Unchanged reply.
	 */
	public function setup_download_filters( $reply ) {
		add_filter( 'http_request_args', array( $this, 'inject_auth_headers' ), 10, 2 );
		add_action( 'requests-requests.before_redirect', array( $this, 'strip_auth_on_redirect' ), 10, 4 );
		return $reply;
	}

	/**
	 * Add auth headers to GitHub API requests.
	 *
	 * Injects Authorization and Accept headers only for requests
	 * targeting this plugin's GitHub repository API.
	 *
	 * @param array  $args HTTP request arguments.
	 * @param string $url  Request URL.
	 * @return array Modified arguments.
	 */
	public function inject_auth_headers( $args, $url ) {
		$api_base = 'https://api.github.com/repos/' . $this->owner . '/' . $this->repo . '/';

		// Only add auth for requests to this repo's GitHub API.
		if ( false === strpos( $url, $api_base ) ) {
			return $args;
		}

		$args['headers']['Authorization'] = 'token ' . $this->token;

		// For release asset downloads, request binary content.
		if ( false !== strpos( $url, '/releases/assets/' ) ) {
			$args['headers']['Accept'] = 'application/octet-stream';
		}

		return $args;
	}

	/**
	 * Strip auth headers when redirected away from GitHub API.
	 *
	 * GitHub's API returns a 302 redirect to a pre-signed Azure/S3 URL
	 * for release asset downloads. The pre-signed URL has its own auth
	 * via query parameters. Sending our GitHub token to Azure causes
	 * a 400 error, so we strip it on cross-domain redirects.
	 *
	 * @param string $location Redirect URL.
	 * @param array  $headers  Request headers (passed by reference).
	 */
	public function strip_auth_on_redirect( &$location, &$headers ) {
		$api_base = 'https://api.github.com/repos/' . $this->owner . '/' . $this->repo . '/';

		// Still on GitHub API -- keep auth.
		if ( 0 === strpos( $location, $api_base ) ) {
			return;
		}

		// Redirect is going elsewhere (Azure Blob / S3). Strip auth.
		if ( isset( $headers['Authorization'] ) ) {
			unset( $headers['Authorization'] );
		}
	}

	/**
	 * Get GitHub release info.
	 *
	 * @return object|false Release data or false on failure.
	 */
	private function get_github_release() {
		if ( null !== $this->github_response ) {
			return $this->github_response;
		}

		// Check cache first.
		$cached = get_transient( 'ria_dm_github_release' );
		if ( false !== $cached ) {
			$this->github_response = $cached;
			return $cached;
		}

		// Fetch from GitHub API.
		$url = sprintf(
			'https://api.github.com/repos/%s/%s/releases/latest',
			$this->owner,
			$this->repo
		);

		$headers = array(
			'Accept'     => 'application/vnd.github.v3+json',
			'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
		);

		// Add auth token for private repos.
		if ( ! empty( $this->token ) ) {
			$headers['Authorization'] = 'token ' . $this->token;
		}

		$response = wp_remote_get(
			$url,
			array(
				'headers' => $headers,
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body );

		if ( empty( $data ) || ! isset( $data->tag_name ) ) {
			return false;
		}

		// Cache for 6 hours.
		set_transient( 'ria_dm_github_release', $data, 6 * HOUR_IN_SECONDS );

		$this->github_response = $data;
		return $data;
	}

	/**
	 * Check for plugin updates.
	 *
	 * @param object $transient Update transient.
	 * @return object Modified transient.
	 */
	public function check_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$release = $this->get_github_release();
		if ( ! $release ) {
			return $transient;
		}

		// Extract version from tag (remove 'v' prefix if present).
		$github_version = ltrim( $release->tag_name, 'v' );

		// Compare versions.
		if ( version_compare( $github_version, $this->version, '>' ) ) {
			// Find the ZIP asset.
			$download_url = $this->get_download_url( $release );

			if ( $download_url ) {
				$transient->response[ $this->basename ] = (object) array(
					'slug'        => $this->slug,
					'plugin'      => $this->basename,
					'new_version' => $github_version,
					'url'         => $release->html_url,
					'package'     => $download_url,
					'icons'       => array(),
					'banners'     => array(),
					'tested'      => get_bloginfo( 'version' ),
					'requires'    => RIA_DM_MIN_WP_VERSION,
				);
			}
		}

		return $transient;
	}

	/**
	 * Get download URL from release.
	 *
	 * @param object $release GitHub release data.
	 * @return string|false Download URL or false.
	 */
	private function get_download_url( $release ) {
		// First, check for uploaded ZIP asset.
		// Use the API URL (not browser_download_url) so auth headers
		// are sent on the initial request for private repos.
		if ( ! empty( $release->assets ) ) {
			foreach ( $release->assets as $asset ) {
				if ( 'application/zip' === $asset->content_type ||
					preg_match( '/\.zip$/i', $asset->name ) ) {
					return ! empty( $this->token ) && ! empty( $asset->url )
						? $asset->url
						: $asset->browser_download_url;
				}
			}
		}

		// Fall back to GitHub's auto-generated zipball.
		if ( ! empty( $release->zipball_url ) ) {
			return $release->zipball_url;
		}

		return false;
	}

	/**
	 * Provide plugin info for update details popup.
	 *
	 * @param false|object|array $result The result object or array.
	 * @param string             $action The type of information being requested.
	 * @param object             $args   Plugin API arguments.
	 * @return false|object Plugin info or false.
	 */
	public function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		if ( $this->slug !== $args->slug ) {
			return $result;
		}

		$release = $this->get_github_release();
		if ( ! $release ) {
			return $result;
		}

		$github_version = ltrim( $release->tag_name, 'v' );

		$plugin_info = (object) array(
			'name'          => 'RIA Data Manager',
			'slug'          => $this->slug,
			'version'       => $github_version,
			'author'        => '<a href="https://the-ria.ca">Rob Hudson</a>',
			'homepage'      => 'https://github.com/' . $this->owner . '/' . $this->repo,
			'requires'      => RIA_DM_MIN_WP_VERSION,
			'tested'        => get_bloginfo( 'version' ),
			'downloaded'    => 0,
			'last_updated'  => $release->published_at,
			'sections'      => array(
				'description' => 'Export and import WordPress metadata (posts, pages, custom post types) with ACF fields for collaborative editing in Google Sheets.',
				'changelog'   => $this->parse_changelog( $release->body ),
			),
			'download_link' => $this->get_download_url( $release ),
			'banners'       => array(),
			'icons'         => array(),
		);

		return $plugin_info;
	}

	/**
	 * Parse release body as changelog.
	 *
	 * @param string $body Release body (Markdown).
	 * @return string HTML changelog.
	 */
	private function parse_changelog( $body ) {
		if ( empty( $body ) ) {
			return '<p>No changelog available.</p>';
		}

		// Basic Markdown to HTML conversion.
		$html = esc_html( $body );
		$html = nl2br( $html );

		// Convert **bold** to <strong>.
		$html = preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html );

		// Convert - items to list.
		$html = preg_replace( '/^- (.+)$/m', '<li>$1</li>', $html );
		$html = preg_replace( '/(<li>.*<\/li>)/s', '<ul>$1</ul>', $html );

		return $html;
	}

	/**
	 * Rename folder after install.
	 *
	 * GitHub's zipball has a folder like "owner-repo-hash".
	 * We need to rename it to "ria-data-manager".
	 *
	 * @param bool  $response   Install response.
	 * @param array $hook_extra Extra args.
	 * @param array $result     Install result.
	 * @return array Modified result.
	 */
	public function after_install( $response, $hook_extra, $result ) {
		global $wp_filesystem;

		// Only for this plugin.
		if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->basename ) {
			return $result;
		}

		$install_dir = $result['destination'];
		$plugin_dir  = WP_PLUGIN_DIR . '/' . $this->slug;

		// If the directory name is wrong, rename it.
		if ( $install_dir !== $plugin_dir ) {
			$wp_filesystem->move( $install_dir, $plugin_dir );
			$result['destination'] = $plugin_dir;
		}

		// Reactivate the plugin.
		activate_plugin( $this->basename );

		return $result;
	}

	/**
	 * Add "Check for updates" link to plugin row.
	 *
	 * @param array  $links Plugin row links.
	 * @param string $file  Plugin file.
	 * @return array Modified links.
	 */
	public function plugin_row_meta( $links, $file ) {
		if ( $file !== $this->basename ) {
			return $links;
		}

		$check_url = wp_nonce_url(
			admin_url( 'plugins.php?ria_dm_check_update=1' ),
			'ria_dm_check_update'
		);

		$links[] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( $check_url ),
			esc_html__( 'Check for updates', 'ria-data-manager' )
		);

		return $links;
	}

	/**
	 * Handle manual update check.
	 */
	public function handle_manual_check() {
		if ( ! isset( $_GET['ria_dm_check_update'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'ria_dm_check_update' ) ) {
			return;
		}

		if ( ! current_user_can( 'update_plugins' ) ) {
			return;
		}

		// Clear cached data.
		delete_transient( 'ria_dm_github_release' );
		delete_site_transient( 'update_plugins' );

		// Force refresh.
		wp_update_plugins();

		// Redirect back with message.
		wp_safe_redirect(
			add_query_arg(
				array(
					'ria_dm_checked' => '1',
				),
				admin_url( 'plugins.php' )
			)
		);
		exit;
	}
}
