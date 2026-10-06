<?php
/**
 * ADVANCED
 *
 * Export, import and reset handlers for the Advanced panel on the Settings tab.
 */

namespace PluginRx\SitePerspectives;

if ( ! defined( 'ABSPATH' ) ) exit;


class Advanced {

    /**
     * Current export format version. Bump when the export shape changes.
     */
    public const EXPORT_VERSION = 1;


    /**
     * @var Advanced|null Singleton instance
     */
    private static ?Advanced $instance = null;


    /**
     * Get instance
     *
     * @return self
     */
    public static function instance() : self {
        return self::$instance ??= new self();
    } // End instance()


    /**
     * Constructor
     */
    private function __construct() {
        add_action( 'admin_post_siteperspectives_export', [ $this, 'handle_export' ] );
        add_action( 'wp_ajax_siteperspectives_import', [ $this, 'ajax_import' ] );
        add_action( 'wp_ajax_siteperspectives_reset', [ $this, 'ajax_reset' ] );
    } // End __construct()


    /**
     * Options that make up the plugin's configuration.
     *
     * @return array
     */
    private static function setting_keys() : array {
        return [ 'siteperspectives_menu_title', 'siteperspectives_page_title', 'siteperspectives_menu_icon', 'siteperspectives_logo', 'siteperspectives_tree_post_types', 'siteperspectives_home_title', 'siteperspectives_view_roles', 'siteperspectives_edit_roles', 'siteperspectives_utility_flags', 'siteperspectives_auto_post_types', 'siteperspectives_auto_hide_new', 'siteperspectives_clear_data_on_uninstall' ];
    } // End setting_keys()


    /**
     * Build the export payload.
     *
     * @return array
     */
    private function build_export_payload() : array {
        $settings = [];

        foreach ( self::setting_keys() as $key ) {
            $settings[ $key ] = get_option( $key, '' );
        }

        return [
            'export_version'  => self::EXPORT_VERSION,
            'exported_at'     => gmdate( 'c' ),
            'plugin_version'  => Bootstrap::version(),
            'settings'        => $settings,
            'view'            => get_option( Tree::OPTION_VIEW, [] ),
            'tree'            => Tree::get_tree(),
            'backend_layout'  => Backend::get_layout(),
            'shortcode'       => Shortcode::get_settings(),
        ];
    } // End build_export_payload()


    /**
     * Handle export request: output JSON file for download.
     *
     * @return void
     */
    public function handle_export() : void {
        check_admin_referer( 'siteperspectives_advanced_action' );

        if ( ! Access::can_manage() ) {
            wp_die( esc_html__( 'You do not have permission to do this.', 'site-perspectives' ) );
        }

        nocache_headers();
        header( 'Content-Type: application/json' );
        header( 'Content-Disposition: attachment; filename="siteperspectives-settings.json"' );

        echo wp_json_encode( $this->build_export_payload(), JSON_PRETTY_PRINT ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON file download.
        exit;
    } // End handle_export()


    /**
     * AJAX: import an exported file.
     *
     * @return void
     */
    public function ajax_import() : void {
        check_ajax_referer( 'siteperspectives_settings_nonce', 'nonce' );

        if ( ! Access::can_manage() ) {
            wp_send_json_error( __( 'Insufficient permissions.', 'site-perspectives' ), 403 );
        }

        $raw = isset( $_POST[ 'payload' ] ) ? wp_unslash( $_POST[ 'payload' ] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON is decoded and every section is sanitized below.
        $data = ( is_string( $raw ) && strlen( $raw ) < 5 * MB_IN_BYTES ) ? json_decode( $raw, true ) : null;

        if ( ! is_array( $data ) || ! isset( $data[ 'export_version' ] ) ) {
            wp_send_json_error( __( 'The uploaded file is not a valid export.', 'site-perspectives' ) );
        }

        if ( (int) $data[ 'export_version' ] > self::EXPORT_VERSION ) {
            wp_send_json_error( __( 'This export was created by a newer version of the plugin and cannot be imported.', 'site-perspectives' ) );
        }

        Settings::apply( (array) ( $data[ 'settings' ] ?? [] ) );

        foreach ( [ 'frontend', 'backend' ] as $tab ) {
            if ( isset( $data[ 'view' ][ $tab ] ) ) {
                Tree::save_view( $tab, Tree::sanitize_view( $data[ 'view' ][ $tab ], $tab ) );
            }
        }

        if ( isset( $data[ 'tree' ] ) && is_array( $data[ 'tree' ] ) ) {
            $ids = Tree::collect_post_ids( (array) ( $data[ 'tree' ][ 'main' ] ?? [] ) );
            $ids = Tree::collect_post_ids( (array) ( $data[ 'tree' ][ 'utility' ] ?? [] ), $ids );
            $valid = Tree::load_posts( array_keys( $ids ) );
            $count = 0;

            Tree::save_tree( [
                'main'    => Tree::sanitize_nodes( $data[ 'tree' ][ 'main' ] ?? [], 'frontend', $valid, $count ),
                'utility' => Tree::sanitize_nodes( $data[ 'tree' ][ 'utility' ] ?? [], 'frontend', $valid, $count ),
            ] );
        }

        if ( isset( $data[ 'backend_layout' ] ) && is_array( $data[ 'backend_layout' ] ) ) {
            $count = 0;
            Backend::save_layout( Tree::sanitize_nodes( $data[ 'backend_layout' ], 'backend', null, $count ) );
        }

        if ( isset( $data[ 'shortcode' ] ) ) {
            update_option( Shortcode::OPTION, Shortcode::sanitize( $data[ 'shortcode' ] ), false );
            Tree::bump_version();
        }

        wp_send_json_success();
    } // End ajax_import()


    /**
     * AJAX: reset every option to its default.
     *
     * @return void
     */
    public function ajax_reset() : void {
        check_ajax_referer( 'siteperspectives_settings_nonce', 'nonce' );

        if ( ! Access::can_manage() ) {
            wp_send_json_error( __( 'Insufficient permissions.', 'site-perspectives' ), 403 );
        }

        foreach ( self::setting_keys() as $key ) {
            delete_option( $key );
        }

        foreach ( [ Tree::OPTION_TREE, Tree::OPTION_VIEW, Backend::OPTION_LAYOUT, Shortcode::OPTION ] as $key ) {
            delete_option( $key );
        }

        Tree::bump_version();

        wp_send_json_success();
    } // End ajax_reset()

} // End class Advanced

Advanced::instance();
