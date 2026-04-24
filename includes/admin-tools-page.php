<?php
/**
 * Admin Tools page: bulk-fix existing numeric post slugs.
 *
 * Provides a page under Tools > Fix Numeric Slugs that lets site admins
 * retroactively fix posts whose slugs were saved as purely numeric values
 * before the plugin was activated.
 *
 * @package WPNumericSlugFixer
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Maximum number of rows shown in the preview table. Fix All still processes every post. */
define( 'WPNSF_TABLE_DISPLAY_LIMIT', 200 );

add_action( 'admin_menu', 'wpnsf_register_tools_page' );

/**
 * Register the Tools > Fix Numeric Slugs page.
 *
 * @since 1.1.0
 */
function wpnsf_register_tools_page(): void {
	add_management_page(
		__( 'Fix Numeric Slugs', 'wp-numeric-slug-fixer' ),
		__( 'Fix Numeric Slugs', 'wp-numeric-slug-fixer' ),
		'manage_options',
		'wpnsf-fix-numeric-slugs',
		'wpnsf_render_tools_page'
	);
}

/**
 * Return all posts that currently have a purely numeric slug.
 *
 * Excludes post types listed in the wpnsf_excluded_post_types filter,
 * plus auto-drafts and trashed posts.
 *
 * @since  1.1.0
 * @global wpdb $wpdb
 * @return object[] Array of row objects with properties: ID, post_name, post_type, post_status.
 */
function wpnsf_get_numeric_slug_posts(): array {
	global $wpdb;

	/** This filter is documented in wp-numeric-slug-fixer.php */
	$excluded = (array) apply_filters( 'wpnsf_excluded_post_types', array( 'revision', 'nav_menu_item' ) );

	$type_clause = '';
	if ( ! empty( $excluded ) ) {
		$escaped     = array_map( 'esc_sql', $excluded );
		$type_clause = "AND post_type NOT IN ('" . implode( "','", $escaped ) . "')";
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
	$results = $wpdb->get_results(
		"SELECT ID, post_name, post_type, post_status
		   FROM {$wpdb->posts}
		  WHERE post_name REGEXP '^[0-9]+$'
		    {$type_clause}
		    AND post_status NOT IN ('auto-draft', 'trash')
		  ORDER BY ID ASC"
	);

	return is_array( $results ) ? $results : array();
}

/**
 * Process the "Fix All" form submission.
 *
 * Iterates over all numeric-slug posts and calls wp_update_post() with the
 * same post_name, allowing the existing wp_insert_post_data filter to apply
 * the configured prefix. Assumes the nonce has already been verified by the caller.
 *
 * @since  1.1.0
 * @return array{ fixed: int, errors: int[], skipped: int }
 */
function wpnsf_process_fix_all(): array {
	$result = array(
		'fixed'   => 0,
		'errors'  => array(),
		'skipped' => 0,
	);

	$posts = wpnsf_get_numeric_slug_posts();

	foreach ( $posts as $post ) {
		$update_result = wp_update_post(
			array(
				'ID'        => (int) $post->ID,
				'post_name' => $post->post_name,
			),
			true
		);

		if ( is_wp_error( $update_result ) ) {
			$result['errors'][] = (int) $post->ID;
		} elseif ( 0 === $update_result ) {
			++$result['skipped'];
		} else {
			++$result['fixed'];
		}
	}

	return $result;
}

/**
 * Render the Tools > Fix Numeric Slugs admin page.
 *
 * @since 1.1.0
 */
function wpnsf_render_tools_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'wp-numeric-slug-fixer' ), 403 );
	}

	$permalink_ok = false !== strpos( (string) get_option( 'permalink_structure' ), '%postname%' );
	$result       = null;

	if ( isset( $_POST['wpnsf_fix_all_nonce'] ) ) {
		check_admin_referer( 'wpnsf_fix_all', 'wpnsf_fix_all_nonce' );
		if ( $permalink_ok ) {
			$result = wpnsf_process_fix_all();
		}
	}

	$posts      = wpnsf_get_numeric_slug_posts();
	$total      = count( $posts );
	$display    = array_slice( $posts, 0, WPNSF_TABLE_DISPLAY_LIMIT );
	$overflowed = $total > WPNSF_TABLE_DISPLAY_LIMIT;
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Fix Numeric Slugs', 'wp-numeric-slug-fixer' ); ?></h1>

		<?php if ( ! $permalink_ok ) : ?>
		<div class="notice notice-warning">
			<p>
				<?php
				printf(
					/* translators: 1: settings link open tag, 2: link close tag */
					esc_html__( 'The Fix All operation requires the %1$sPost name%2$s permalink structure to be active. Numeric slugs do not conflict with archives under other structures, so no action is needed.', 'wp-numeric-slug-fixer' ),
					'<a href="' . esc_url( admin_url( 'options-permalink.php' ) ) . '">',
					'</a>'
				);
				?>
			</p>
		</div>
		<?php endif; ?>

		<?php if ( null !== $result ) : ?>
			<?php if ( empty( $result['errors'] ) ) : ?>
			<div class="notice notice-success is-dismissible">
				<p>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: number of posts fixed */
							_n(
								'Fixed %d post slug successfully.',
								'Fixed %d post slugs successfully.',
								$result['fixed'],
								'wp-numeric-slug-fixer'
							),
							$result['fixed']
						)
					);
					if ( $result['skipped'] > 0 ) {
						echo ' ' . esc_html(
							sprintf(
								/* translators: %d: number of posts skipped */
								_n(
									'%d post was skipped (already updated or not found).',
									'%d posts were skipped (already updated or not found).',
									$result['skipped'],
									'wp-numeric-slug-fixer'
								),
								$result['skipped']
							)
						);
					}
					?>
				</p>
			</div>
			<?php else : ?>
			<div class="notice notice-error is-dismissible">
				<p>
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: fixed count, 2: error count, 3: comma-separated post IDs */
							__( '%1$d slug(s) fixed. %2$d could not be updated (Post IDs: %3$s).', 'wp-numeric-slug-fixer' ),
							$result['fixed'],
							count( $result['errors'] ),
							implode( ', ', array_map( 'intval', $result['errors'] ) )
						)
					);
					?>
				</p>
			</div>
			<?php endif; ?>
		<?php endif; ?>

		<p>
			<?php
			if ( 0 === $total ) {
				esc_html_e( 'No posts with numeric-only slugs found. Nothing to fix.', 'wp-numeric-slug-fixer' );
			} else {
				echo esc_html(
					sprintf(
						/* translators: %d: number of posts with numeric slugs */
						_n(
							'%d post with a numeric-only slug found.',
							'%d posts with numeric-only slugs found.',
							$total,
							'wp-numeric-slug-fixer'
						),
						$total
					)
				);
			}
			?>
		</p>

		<?php if ( $total > 0 ) : ?>
		<table class="widefat striped" style="max-width:800px;">
			<thead>
				<tr>
					<th><?php esc_html_e( 'ID', 'wp-numeric-slug-fixer' ); ?></th>
					<th><?php esc_html_e( 'Current Slug', 'wp-numeric-slug-fixer' ); ?></th>
					<th><?php esc_html_e( 'Post Type', 'wp-numeric-slug-fixer' ); ?></th>
					<th><?php esc_html_e( 'Status', 'wp-numeric-slug-fixer' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $display as $post ) : ?>
				<tr>
					<td><?php echo intval( $post->ID ); ?></td>
					<td><code><?php echo esc_html( $post->post_name ); ?></code></td>
					<td><?php echo esc_html( $post->post_type ); ?></td>
					<td><?php echo esc_html( $post->post_status ); ?></td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( $overflowed ) : ?>
		<p class="description">
			<?php
			echo esc_html(
				sprintf(
					/* translators: 1: display limit, 2: total count */
					__( 'Showing first %1$d of %2$d affected posts. "Fix All" will process all %2$d posts.', 'wp-numeric-slug-fixer' ),
					WPNSF_TABLE_DISPLAY_LIMIT,
					$total
				)
			);
			?>
		</p>
		<?php endif; ?>

		<?php if ( $permalink_ok ) : ?>
		<form method="post" style="margin-top:1em;">
			<?php wp_nonce_field( 'wpnsf_fix_all', 'wpnsf_fix_all_nonce' ); ?>
			<input
				type="submit"
				class="button button-primary"
				value="<?php esc_attr_e( 'Fix All', 'wp-numeric-slug-fixer' ); ?>"
			>
		</form>
		<?php endif; ?>
		<?php endif; ?>
	</div>
	<?php
}
