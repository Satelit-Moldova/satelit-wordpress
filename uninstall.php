<?php
/**
 * Removes everything the plugin stored: options, cached answers, product meta.
 *
 * @package SatelitMoldovaData
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Cleans one site.
 */
function satelit_md_uninstall_site() {
	global $wpdb;
	foreach ( array( 'satelit_md_api_key', 'satelit_md_settings', 'satelit_md_last_error', 'satelit_md_credits', 'satelit_md_cache_ver', 'widget_satelit_md' ) as $opt ) {
		delete_option( $opt );
	}
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time cleanup of own rows.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_satelit_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_satelit_' ) . '%'
		)
	);
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE meta_key IN (%s, %s)", '_satelit_md_query', '_satelit_md_item' ) );
	// phpcs:enable
}

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids' ) ) as $satelit_md_site ) {
		switch_to_blog( $satelit_md_site );
		satelit_md_uninstall_site();
		restore_current_blog();
	}
} else {
	satelit_md_uninstall_site();
}
