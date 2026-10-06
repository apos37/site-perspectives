<?php
/**
 * MENU
 *
 * Registers the admin menu and submenu pages, plus the shared header and subheader.
 */

namespace PluginRx\SitePerspectives;

if ( ! defined( 'ABSPATH' ) ) exit;


class Menu {

    /**
     * Top-level menu slug.
     */
    public const MENU_SLUG = 'site-perspectives';


    /**
     * Page slug => title, used by the subheader.
     *
     * @var array
     */
    private static array $page_titles = [];


    /**
     * @var Menu|null Singleton instance
     */
    private static ?Menu $instance = null;


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
        add_action( 'admin_menu', [ $this, 'register_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_shared_assets' ] );
        add_action( 'in_admin_header', [ $this, 'maybe_render_header' ] );
    } // End __construct()


    /**
     * Get the tabs available to the current user, in order.
     *
     * @return array Associative array of page slug => [ label, capability ].
     */
    public static function get_tabs() : array {
        $tabs = [
            self::MENU_SLUG                => [ __( 'Backend', 'site-perspectives' ), 'read' ],
            self::MENU_SLUG . '-frontend'  => [ __( 'Frontend', 'site-perspectives' ), 'read' ],
            self::MENU_SLUG . '-shortcode' => [ __( 'Shortcode', 'site-perspectives' ), Access::MANAGE_CAP ],
            self::MENU_SLUG . '-settings'  => [ __( 'Settings', 'site-perspectives' ), Access::MANAGE_CAP ],
        ];

        return $tabs;
    } // End get_tabs()


    /**
     * Register the top-level menu and submenus.
     *
     * @return void
     */
    public function register_menu() : void {
        if ( ! Access::can_view() ) {
            return;
        }

        $menu_title = get_option( 'siteperspectives_menu_title', __( 'Perspectives', 'site-perspectives' ) );
        $page_title = get_option( 'siteperspectives_page_title', __( 'Site Perspectives', 'site-perspectives' ) );
        $menu_icon = Helpers::icon( get_option( 'siteperspectives_menu_icon', 'networking' ), 'networking' );

        add_menu_page( $page_title, $menu_title, 'read', self::MENU_SLUG, [ Backend::class, 'render_page' ], 'dashicons-' . $menu_icon, 85 );

        $callbacks = [
            self::MENU_SLUG                => [ Backend::class, 'render_page' ],
            self::MENU_SLUG . '-frontend'  => [ Frontend::class, 'render_page' ],
            self::MENU_SLUG . '-shortcode' => [ Shortcode::class, 'render_page' ],
            self::MENU_SLUG . '-settings'  => [ Settings::class, 'render_page' ],
        ];

        foreach ( self::get_tabs() as $slug => $tab ) {
            add_submenu_page( self::MENU_SLUG, $tab[ 0 ], $tab[ 0 ], $tab[ 1 ], $slug, $callbacks[ $slug ] );
            self::register_page_title( $slug, $tab[ 0 ] );
        }
    } // End register_menu()


    /**
     * Register a page's subheader title.
     *
     * @param string $slug
     * @param string $title
     * @return void
     */
    public static function register_page_title( string $slug, string $title ) : void {
        self::$page_titles[ $slug ] = $title;
    } // End register_page_title()


    /**
     * Render the header on in_admin_header, before any admin notices, but only on our screens.
     *
     * @return void
     */
    public function maybe_render_header() : void {
        if ( ! Helpers::is_plugin_screen() ) {
            return;
        }

        $active_page = self::get_current_page_slug();

        self::render_header( $active_page );
        self::render_subheader( self::$page_titles[ $active_page ] ?? '', $active_page );
    } // End maybe_render_header()


    /**
     * Determine the current Site Perspectives admin page slug from $_GET['page'].
     *
     * @return string
     */
    public static function get_current_page_slug() : string {
        return isset( $_GET[ 'page' ] ) ? sanitize_key( wp_unslash( $_GET[ 'page' ] ) ) : self::MENU_SLUG; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only, no state change.
    } // End get_current_page_slug()


    /**
     * Render the shared top header: logo, title, and top-level tabs.
     *
     * @param string $active_page
     * @return void
     */
    public static function render_header( string $active_page ) : void {
        $page_title = get_option( 'siteperspectives_page_title', __( 'Site Perspectives', 'site-perspectives' ) );
        $logo = get_option( 'siteperspectives_logo', '' );

        if ( ! $logo ) {
            $logo = self::get_default_logo();
        }
        ?>
        <div id="siteperspectives-header">
            <img src="<?php echo esc_url( $logo ); ?>" alt="" class="logo">

            <div class="title-cont">
                <h1><?php echo esc_html( $page_title ); ?></h1>
            </div>

            <div class="tabs-wrapper">
                <?php foreach ( self::get_tabs() as $slug => $tab ) : ?>
                    <?php
                    if ( ! current_user_can( $tab[ 1 ] ) && ! ( 'read' === $tab[ 1 ] && Access::can_view() ) ) {
                        continue;
                    }

                    $class = ( $active_page === $slug ) ? 'siteperspectives-tab siteperspectives-tab-active' : 'siteperspectives-tab';
                    ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $slug ) ); ?>" class="<?php echo esc_attr( $class ); ?>"><?php echo esc_html( $tab[ 0 ] ); ?></a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    } // End render_header()


    /**
     * Get the default logo URL, used when no custom logo is set in Settings.
     *
     * @return string
     */
    public static function get_default_logo() : string {
        $default = add_query_arg( 'ver', Bootstrap::script_version(), Bootstrap::url() . 'inc/img/logo.png' );

        return apply_filters( 'siteperspectives_default_logo', $default );
    } // End get_default_logo()


    /**
     * Render the white subheader bar: page title on the left, action buttons on the right.
     * Fires 'siteperspectives_subheader_left' and 'siteperspectives_subheader_right' so tabs can add buttons.
     *
     * @param string $title
     * @param string $active_page
     * @return void
     */
    public static function render_subheader( string $title, string $active_page ) : void {
        ?>
        <div id="siteperspectives-subheader">
            <div class="subheader-left">
                <h2 class="tab-title"><?php echo esc_html( $title ); ?></h2>
                <?php do_action( 'siteperspectives_subheader_left', $active_page ); ?>
            </div>
            <div class="subheader-right">
                <?php do_action( 'siteperspectives_subheader_right', $active_page ); ?>
            </div>
        </div>
        <?php
    } // End render_subheader()


    /**
     * Enqueue the shared admin stylesheet on any Site Perspectives screen.
     *
     * @return void
     */
    public function enqueue_shared_assets() : void {
        if ( ! Helpers::is_plugin_screen() ) {
            return;
        }

        wp_enqueue_style( 'siteperspectives-theme', Bootstrap::url() . 'inc/css/theme.css', [], Bootstrap::script_version() );

        DynamicCss::enqueue( 'siteperspectives-dynamic', 'admin', [ 'siteperspectives-theme' ] );
    } // End enqueue_shared_assets()


    /**
     * Get the CSS variables for the admin theme colors.
     *
     * @return string
     */
    public static function theme_css() : string {
        $colors = self::get_theme_colors();
        $declarations = [];

        foreach ( $colors as $var => $value ) {
            $value = Helpers::hex( $value );

            if ( '' !== $value ) {
                $declarations[] = '--siteperspectives-color-' . sanitize_key( $var ) . ':' . $value . ';';
            }
        }

        $header_bg = Helpers::hex( $colors[ 'header-bg' ] ?? '', '#1D2327' );
        $declarations[] = '--tl-trunk-text:' . Helpers::text_color( $header_bg ) . ';';

        return ':root{' . implode( '', $declarations ) . '}';
    } // End theme_css()


    /**
     * Get theme color overrides. Empty by default; the Admin Help Docs integration
     * hooks 'siteperspectives_theme_colors' to provide matching colors.
     *
     * Expected keys: header-bg, header-font, header-tab, header-tab-link,
     * accent, button, button-font, button-hover.
     *
     * @return array
     */
    public static function get_theme_colors() : array {
        return (array) apply_filters( 'siteperspectives_theme_colors', [] );
    } // End get_theme_colors()

} // End class Menu

Menu::instance();
