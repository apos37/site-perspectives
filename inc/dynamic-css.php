<?php
/**
 * DYNAMIC CSS
 *
 * Serves the settings-driven CSS (theme colors, per-item colors, and shortcode colors)
 * as a real stylesheet so it can be enqueued instead of printed inline. The URL carries a hash of
 * the content, so browsers cache it until something changes.
 */

namespace PluginRx\SitePerspectives;

if ( ! defined( 'ABSPATH' ) ) exit;


class DynamicCss {

    /**
     * AJAX action name.
     */
    public const ACTION = 'siteperspectives_css';


    /**
     * @var DynamicCss|null Singleton instance
     */
    private static ?DynamicCss $instance = null;


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
        add_action( 'wp_ajax_' . self::ACTION, [ $this, 'serve' ] );
        add_action( 'wp_ajax_nopriv_' . self::ACTION, [ $this, 'serve' ] );
    } // End __construct()


    /**
     * Get the stylesheet URL for a type.
     *
     * @param string $type admin|shortcode|nodes
     * @return string
     */
    public static function url( string $type ) : string {
        return add_query_arg( [ 'action' => self::ACTION, 'type' => $type ], admin_url( 'admin-ajax.php' ) );
    } // End url()


    /**
     * Get the version string for a type, which changes whenever the CSS changes.
     *
     * @param string $type
     * @return string
     */
    public static function version( string $type ) : string {
        return substr( md5( self::build( $type ) ), 0, 12 );
    } // End version()


    /**
     * Enqueue a dynamic stylesheet.
     *
     * @param string $handle
     * @param string $type
     * @param array  $deps
     * @return void
     */
    public static function enqueue( string $handle, string $type, array $deps = [] ) : void {
        wp_enqueue_style( $handle, self::url( $type ), $deps, self::version( $type ) );
    } // End enqueue()


    /**
     * Get the URL including its version, for places that build their own link tag.
     *
     * @param string $type
     * @return string
     */
    public static function versioned_url( string $type ) : string {
        return add_query_arg( 'ver', self::version( $type ), self::url( $type ) );
    } // End versioned_url()


    /**
     * Build the CSS for a type.
     *
     * @param string $type admin|shortcode|nodes
     * @return string
     */
    public static function build( string $type ) : string {
        if ( 'admin' === $type ) {
            $colors = [];

            Tree::collect_colors( Tree::get_tree()[ 'main' ], $colors );
            Tree::collect_colors( Tree::get_tree()[ 'utility' ], $colors );
            Tree::collect_colors( Backend::get_layout(), $colors );

            return Menu::theme_css() . self::node_rules( $colors, '' );
        }

        $colors = [];
        $tree = Tree::get_tree();

        Tree::collect_colors( $tree[ 'main' ], $colors );
        Tree::collect_colors( $tree[ 'utility' ], $colors );

        $css = self::node_rules( $colors, '.siteperspectives-sc.siteperspectives-use-node-colors ' );

        if ( 'shortcode' === $type ) {
            $settings = Shortcode::get_settings();
            $css = '.siteperspectives-sc{' . Shortcode::style_vars( $settings ) . '}' . $css;
        }

        return $css;
    } // End build()


    /**
     * Build one rule per distinct item color.
     *
     * @param array  $colors Hex colors.
     * @param string $scope  Selector prefix, including a trailing space when needed.
     * @return string
     */
    private static function node_rules( array $colors, string $scope ) : string {
        $css = '';

        foreach ( array_unique( $colors ) as $hex ) {
            $css .= $scope . '.siteperspectives-node.' . Helpers::color_class( $hex ) . '{--siteperspectives-node-color:' . $hex . ' !important;--siteperspectives-node-text:' . Helpers::text_color( $hex ) . ' !important}';
        }

        return $css;
    } // End node_rules()


    /**
     * AJAX: output a stylesheet.
     *
     * @return void
     */
    public function serve() : void {
        $type = isset( $_GET[ 'type' ] ) ? sanitize_key( wp_unslash( $_GET[ 'type' ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public, read-only stylesheet.

        if ( ! in_array( $type, [ 'admin', 'shortcode', 'nodes' ], true ) ) {
            status_header( 404 );
            exit;
        }

        header( 'Content-Type: text/css; charset=UTF-8' );
        header( 'Cache-Control: public, max-age=31536000' );
        header_remove( 'Pragma' );
        header_remove( 'Expires' );

        echo wp_strip_all_tags( self::build( $type ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from validated hex colors and sanitized settings, with tags stripped.
        exit;
    } // End serve()

} // End class DynamicCss

DynamicCss::instance();
