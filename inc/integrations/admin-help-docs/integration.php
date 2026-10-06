<?php
/**
 * INTEGRATION: ADMIN HELP DOCS
 *
 * Inherits Admin Help Docs' header, button and accent colors so both plugins look alike.
 */

namespace PluginRx\SitePerspectives;

if ( ! defined( 'ABSPATH' ) ) exit;


if ( ! Integrations::is_active( 'admin-help-docs/admin-help-docs.php' ) ) {
    return;
}


/**
 * Pass Admin Help Docs' color theme to Site Perspectives' admin screens.
 *
 * @param array $colors
 * @return array
 */
function siteperspectives_ahd_theme_colors( array $colors ) : array {
    $ahd_colors = [];

    if ( class_exists( '\PluginRx\AdminHelpDocs\Colors' ) ) {
        $ahd_colors = \PluginRx\AdminHelpDocs\Colors::get();
    }

    if ( ! is_array( $ahd_colors ) || empty( $ahd_colors ) ) {
        $ahd_colors = get_option( 'helpdocs_colors', [] ); // phpcs:ignore
    }

    if ( ! is_array( $ahd_colors ) || empty( $ahd_colors ) ) {
        return $colors;
    }

    $map = [
        'header_bg'       => 'header-bg',
        'header_font'     => 'header-font',
        'header_tab'      => 'header-tab',
        'header_tab_link' => 'header-tab-link',
        'doc_accent'      => 'accent',
        'button'          => 'button',
        'button_font'     => 'button-font',
        'button_hover'    => 'button-hover',
    ];

    foreach ( $map as $ahd_key => $siteperspectives_key ) {
        if ( ! empty( $ahd_colors[ $ahd_key ] ) ) {
            $colors[ $siteperspectives_key ] = sanitize_hex_color( $ahd_colors[ $ahd_key ] );
        }
    }

    return $colors;
} // End siteperspectives_ahd_theme_colors()

add_filter( 'siteperspectives_theme_colors', __NAMESPACE__ . '\\siteperspectives_ahd_theme_colors' );
