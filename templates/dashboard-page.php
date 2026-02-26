<?php
/**
 * Dashboard Page Template
 *
 * @package Quarry
 * @since   2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Run the scan (cached).
$scan       = QRY_Site_Scanner::scan();
$post_types = QRY_Site_Scanner::get_useful_post_types( $scan );
$taxonomies = QRY_Site_Scanner::get_useful_taxonomies( $scan );
$stats      = $scan['statistics'];
$plugins    = $scan['structure']['plugins'];
$scanned_at = $scan['scanned_at'];
?>

<div class="qry-dashboard">

	<!-- Header row -->
	<div class="qry-dash-header">
		<div class="qry-dash-meta">
			<?php echo qry_icon( 'clock', 14 ); ?>
			<?php
			printf(
				__( 'Last scan: %s', 'quarry' ),
				'<strong>' . esc_html( $scanned_at ) . '</strong>'
			);
			?>
			<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'tools.php?page=quarry&tab=dashboard&qry_rescan=1' ), 'qry_rescan' ) ); ?>"
			   class="qry-btn qry-btn-outline qry-btn-sm">
				<?php echo qry_icon( 'refresh-cw', 14 ); ?>
				<?php _e( 'Rescan', 'quarry' ); ?>
			</a>
		</div>
	</div>

	<!-- Stats grid -->
	<div class="qry-stats-grid">
		<?php foreach ( $post_types as $slug => $type ) :
			$type_stats = isset( $stats['post_types'][ $slug ] ) ? $stats['post_types'][ $slug ] : null;
			$total      = $type_stats ? $type_stats['total'] : 0;
			$published  = $type_stats && isset( $type_stats['breakdown']['publish'] ) ? $type_stats['breakdown']['publish'] : 0;
			$draft      = $type_stats && isset( $type_stats['breakdown']['draft'] ) ? $type_stats['breakdown']['draft'] : 0;
			$icon_name  = qry_post_type_icon( $slug );
			?>
			<div class="qry-stat-card">
				<div class="qry-stat-icon">
					<?php echo qry_icon( $icon_name, 24 ); ?>
				</div>
				<div class="qry-stat-body">
					<div class="qry-stat-num"><?php echo number_format_i18n( $total ); ?></div>
					<div class="qry-stat-label"><?php echo esc_html( $type['label'] ); ?></div>
					<?php if ( $total > 0 ) : ?>
						<div class="qry-stat-detail">
							<?php
							$parts = array();
							if ( $published > 0 ) {
								$parts[] = $published . ' ' . __( 'published', 'quarry' );
							}
							if ( $draft > 0 ) {
								$parts[] = $draft . ' ' . __( 'draft', 'quarry' );
							}
							echo esc_html( implode( ', ', $parts ) );
							?>
						</div>
					<?php endif; ?>
				</div>
			</div>
		<?php endforeach; ?>

		<!-- Media library -->
		<div class="qry-stat-card">
			<div class="qry-stat-icon">
				<?php echo qry_icon( 'image', 24 ); ?>
			</div>
			<div class="qry-stat-body">
				<div class="qry-stat-num"><?php echo number_format_i18n( $stats['media_count'] ); ?></div>
				<div class="qry-stat-label"><?php _e( 'Media Files', 'quarry' ); ?></div>
			</div>
		</div>
	</div>

	<!-- Two-column layout -->
	<div class="qry-dash-columns">

		<!-- Taxonomies -->
		<div class="qry-dash-col">
			<div class="qry-card">
				<h3><?php echo qry_icon( 'tag', 18 ); ?> <?php _e( 'Taxonomies', 'quarry' ); ?></h3>
				<?php if ( ! empty( $taxonomies ) ) : ?>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php _e( 'Taxonomy', 'quarry' ); ?></th>
								<th><?php _e( 'Type', 'quarry' ); ?></th>
								<th class="qry-num-col"><?php _e( 'Terms', 'quarry' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $taxonomies as $slug => $tax ) :
								$term_count = isset( $stats['taxonomies'][ $slug ] ) ? $stats['taxonomies'][ $slug ] : 0;
								?>
								<tr>
									<td><strong><?php echo esc_html( $tax['label'] ); ?></strong> <code><?php echo esc_html( $slug ); ?></code></td>
									<td>
										<?php if ( $tax['hierarchical'] ) : ?>
											<span class="qry-badge"><?php _e( 'Hierarchical', 'quarry' ); ?></span>
										<?php else : ?>
											<span class="qry-badge"><?php _e( 'Flat', 'quarry' ); ?></span>
										<?php endif; ?>
									</td>
									<td class="qry-num-col"><?php echo number_format_i18n( $term_count ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php else : ?>
					<p><?php _e( 'No custom taxonomies found.', 'quarry' ); ?></p>
				<?php endif; ?>
			</div>
		</div>

		<!-- Detected Integrations -->
		<div class="qry-dash-col">
			<div class="qry-card">
				<h3><?php echo qry_icon( 'plug', 18 ); ?> <?php _e( 'Detected Integrations', 'quarry' ); ?></h3>
				<?php if ( ! empty( $plugins ) ) : ?>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php _e( 'Plugin', 'quarry' ); ?></th>
								<th><?php _e( 'Version', 'quarry' ); ?></th>
								<th><?php _e( 'Status', 'quarry' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $plugins as $key => $plugin ) : ?>
								<tr>
									<td><strong><?php echo esc_html( $plugin['name'] ); ?></strong></td>
									<td><code><?php echo esc_html( $plugin['version'] ); ?></code></td>
									<td><span class="qry-badge qry-badge-active"><?php _e( 'Active', 'quarry' ); ?></span></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php else : ?>
					<p><?php _e( 'No supported integrations detected.', 'quarry' ); ?></p>
				<?php endif; ?>
			</div>

			<!-- ACF Field Groups (if ACF active) -->
			<?php if ( isset( $plugins['acf'] ) && function_exists( 'acf_get_field_groups' ) ) :
				$all_groups = acf_get_field_groups();
				?>
				<div class="qry-card">
					<h3><?php echo qry_icon( 'layers', 18 ); ?> <?php _e( 'ACF Field Groups', 'quarry' ); ?></h3>
					<?php if ( ! empty( $all_groups ) ) : ?>
						<table class="widefat striped">
							<thead>
								<tr>
									<th><?php _e( 'Group', 'quarry' ); ?></th>
									<th class="qry-num-col"><?php _e( 'Fields', 'quarry' ); ?></th>
									<th><?php _e( 'Status', 'quarry' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $all_groups as $group ) :
									$fields = acf_get_fields( $group['ID'] );
									$field_count = $fields ? count( $fields ) : 0;
									?>
									<tr>
										<td><strong><?php echo esc_html( $group['title'] ); ?></strong></td>
										<td class="qry-num-col"><?php echo (int) $field_count; ?></td>
										<td>
											<?php if ( $group['active'] ) : ?>
												<span class="qry-badge qry-badge-active"><?php _e( 'Active', 'quarry' ); ?></span>
											<?php else : ?>
												<span class="qry-badge qry-badge-inactive"><?php _e( 'Inactive', 'quarry' ); ?></span>
											<?php endif; ?>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php else : ?>
						<p><?php _e( 'No field groups found.', 'quarry' ); ?></p>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
	</div>
</div>
