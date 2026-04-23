<?php
/**
 * Plugin Name:       WP Numeric Slug Fixer
 * Plugin URI:        https://github.com/mypacecreator/wp-numeric-slug-fixer
 * Description:       Automatically prefixes numeric-only post slugs to prevent misrouting to date archives when the %postname% permalink structure is active.
 * Version:           1.0.0
 * Author:            mypacecreator
 * Author URI:        https://github.com/mypacecreator
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-numeric-slug-fixer
 * Requires at least: 6.0
 * Tested up to:      6.9
 * Requires PHP:      7.4
 *
 * @package WPNumericSlugFixer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Prepend a prefix to numeric-only post slugs before they are saved.
 *
 * When the permalink structure includes %postname%, WordPress's rewrite rules
 * treat a purely numeric path segment (e.g. /2023/) as a year-archive query
 * and issue a redirect, so the post is never served at its intended URL.
 * This filter intercepts the data before the database write and rewrites the
 * slug, e.g. "2023" → "post-2023", eliminating the routing conflict.
 *
 * @since 1.0.0
 *
 * @param array $data    Slashed, sanitized, and processed post data.
 * @param array $postarr Raw data passed to wp_insert_post().
 * @return array Modified post data.
 */
function wpnsf_fix_numeric_slug( array $data, array $postarr ): array {
	// Revisions inherit slug handling from their parent; skip them.
	if ( 'revision' === $data['post_type'] ) {
		return $data;
	}

	// The routing conflict only occurs with the %postname% permalink structure.
	if ( false === strpos( (string) get_option( 'permalink_structure' ), '%postname%' ) ) {
		return $data;
	}

	$slug = $data['post_name'];

	// An empty slug will be generated from the title later; nothing to fix yet.
	if ( '' === $slug ) {
		return $data;
	}

	if ( ctype_digit( $slug ) ) {
		/**
		 * Filters the prefix prepended to numeric-only post slugs.
		 *
		 * @since 1.0.0
		 *
		 * @param string $prefix Prefix string. Default 'post-'.
		 */
		$prefix            = (string) apply_filters( 'wpnsf_prefix', 'post-' );
		$data['post_name'] = $prefix . $slug;
	}

	return $data;
}
add_filter( 'wp_insert_post_data', 'wpnsf_fix_numeric_slug', 10, 2 );
