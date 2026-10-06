<?php
/**
 * INTEGRATIONS
 *
 * Helpers used by the integration files in /inc/integrations/{slug}/integration.php.
 */

namespace PluginRx\SitePerspectives;

if ( ! defined( 'ABSPATH' ) ) exit;


class Integrations {

    /**
     * Check if a plugin is active.
     *
     * @param string $plugin_file Plugin basename, e.g. admin-help-docs/admin-help-docs.php.
     * @return bool
     */
    public static function is_active( string $plugin_file ) : bool {
        if ( ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return is_plugin_active( $plugin_file );
    } // End is_active()

} // End class Integrations
