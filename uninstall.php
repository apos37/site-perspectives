<?php
/**
 * UNINSTALL
 *
 * Fires only on full plugin deletion via the WordPress Plugins screen.
 * Clears all plugin data only if the user opted in via the Advanced settings.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;

if ( ! get_option( 'siteperspectives_clear_data_on_uninstall' ) ) {
    return;
}

$siteperspectives_options = [
    'siteperspectives_menu_title',
    'siteperspectives_page_title',
    'siteperspectives_menu_icon',
    'siteperspectives_logo',
    'siteperspectives_tree_post_types',
    'siteperspectives_home_title',
    'siteperspectives_view_roles',
    'siteperspectives_edit_roles',
    'siteperspectives_utility_flags',
    'siteperspectives_auto_post_types',
    'siteperspectives_auto_hide_new',
    'siteperspectives_clear_data_on_uninstall',
    'siteperspectives_frontend_tree',
    'siteperspectives_backend_layout',
    'siteperspectives_view_options',
    'siteperspectives_shortcode',
    'siteperspectives_tree_version',
    'siteperspectives_activated_time',
];

foreach ( $siteperspectives_options as $siteperspectives_option ) {
    delete_option( $siteperspectives_option );
}

delete_metadata( 'user', 0, 'siteperspectives_orientation_frontend', '', true );
delete_metadata( 'user', 0, 'siteperspectives_orientation_backend', '', true );

global $wpdb;
$siteperspectives_like_transient = $wpdb->esc_like( '_transient_siteperspectives_' ) . '%';
$siteperspectives_like_timeout = $wpdb->esc_like( '_transient_timeout_siteperspectives_' ) . '%';
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $siteperspectives_like_transient, $siteperspectives_like_timeout ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time cleanup of this plugin's transients on uninstall.

delete_post_meta_by_key( '_siteperspectives_utility' );
delete_metadata( 'user', 0, 'siteperspectives_utility_view', '', true );
delete_post_meta_by_key( '_siteperspectives_auto_added' );
