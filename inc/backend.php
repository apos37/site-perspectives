<?php
/**
 * BACKEND TAB
 *
 * An expanded, folder-like view of the WordPress admin menu so any screen is one click away.
 * The live menu is the source of truth; an optional saved layout only stores order, labels,
 * hidden items and custom links, so new menu items from plugins appear automatically.
 */

namespace PluginRx\SitePerspectives;

if ( ! defined( 'ABSPATH' ) ) exit;


class Backend {

    public const OPTION_LAYOUT = 'siteperspectives_backend_layout';


    /**
     * @var Backend|null Singleton instance
     */
    private static ?Backend $instance = null;


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
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
    } // End __construct()


    /**
     * Enqueue assets on this tab only.
     *
     * @return void
     */
    public function enqueue() : void {
        if ( ! Helpers::is_plugin_screen() || Menu::MENU_SLUG !== Menu::get_current_page_slug() ) {
            return;
        }

        Tree::enqueue_assets( 'backend' );
    } // End enqueue()



    /**
     * Clean a menu title for display (strips update bubbles and tags).
     *
     * @param string $title
     * @return string
     */
    private static function clean_title( string $title ) : string {
        $title = preg_replace( '/<span\b[^>]*>.*?<\/span>/is', '', $title );

        return trim( wp_strip_all_tags( (string) $title ) );
    } // End clean_title()


    /**
     * Work out the admin URL for a menu entry, mirroring how WordPress builds menu links.
     *
     * @param string $slug
     * @param string $parent_slug Empty for top-level items.
     * @return string
     */
    private static function admin_link( string $slug, string $parent_slug ) : string {
        $parent_file = '' !== $parent_slug ? $parent_slug : 'admin.php';
        $hook = get_plugin_page_hook( $slug, $parent_file );
        $slug_file = strtok( $slug, '?' );
        $is_plugin_file = $slug_file && file_exists( WP_PLUGIN_DIR . '/' . $slug_file ) && ! file_exists( ABSPATH . 'wp-admin/' . $slug_file );

        if ( ! empty( $hook ) || ( 'index.php' !== $slug && $is_plugin_file ) ) {
            $base = ( '' !== $parent_slug && file_exists( ABSPATH . 'wp-admin/' . strtok( $parent_slug, '?' ) ) ) ? $parent_slug : 'admin.php';

            return admin_url( add_query_arg( 'page', $slug, $base ) );
        }

        if ( 0 === strpos( $slug, 'http' ) ) {
            return $slug;
        }

        return admin_url( $slug );
    } // End admin_link()


    /**
     * Build a flat description of the live admin menu.
     *
     * @return array [ 'top' => ordered top-level ids, 'items' => id => node, 'children' => id => ordered child ids ]
     */
    private static function live_menu() : array {
        global $menu, $submenu;

        $cache_key = 'siteperspectives_live_menu_' . get_current_user_id();

        if ( empty( $menu ) ) {
            $cached = get_transient( $cache_key );

            return is_array( $cached ) ? $cached : [ 'top' => [], 'items' => [], 'children' => [] ];
        }

        $out = [ 'top' => [], 'items' => [], 'children' => [] ];

        foreach ( (array) $menu as $item ) {
            if ( empty( $item[ 0 ] ) || ( isset( $item[ 4 ] ) && false !== strpos( (string) $item[ 4 ], 'wp-menu-separator' ) ) ) {
                continue;
            }

            $slug = (string) $item[ 2 ];
            $id = 'm_' . substr( md5( $slug ), 0, 12 );
            $icon = ( isset( $item[ 6 ] ) && 0 === strpos( (string) $item[ 6 ], 'dashicons-' ) ) ? Helpers::icon( $item[ 6 ] ) : '';

            $out[ 'items' ][ $id ] = self::live_node( $id, self::clean_title( (string) $item[ 0 ] ), self::admin_link( $slug, '' ), $slug, (string) $item[ 1 ], $icon, 'admin-generic' );
            $out[ 'top' ][] = $id;
            $out[ 'children' ][ $id ] = [];

            foreach ( (array) ( $submenu[ $slug ] ?? [] ) as $sub ) {
                if ( empty( $sub[ 0 ] ) || empty( $sub[ 2 ] ) ) {
                    continue;
                }

                $sub_slug = (string) $sub[ 2 ];
                $sub_id = 's_' . substr( md5( $slug . '|' . $sub_slug ), 0, 12 );

                if ( isset( $out[ 'items' ][ $sub_id ] ) ) {
                    continue;
                }

                $out[ 'items' ][ $sub_id ] = self::live_node( $sub_id, self::clean_title( (string) $sub[ 0 ] ), self::admin_link( $sub_slug, $slug ), $sub_slug, (string) $sub[ 1 ], '', '' );
                $out[ 'children' ][ $id ][] = $sub_id;
            }
        }

        set_transient( $cache_key, $out, DAY_IN_SECONDS );

        return $out;
    } // End live_menu()


    /**
     * Create a node from a live menu entry.
     *
     * @param string $id
     * @param string $title
     * @param string $href
     * @param string $slug
     * @param string $capability
     * @param string $icon
     * @param string $default_icon
     * @return array
     */
    private static function live_node( string $id, string $title, string $href, string $slug, string $capability, string $icon, string $default_icon ) : array {
        $node = Tree::blank_node( 'admin' );
        $node[ 'id' ] = $id;
        $node[ 'orig' ] = $title;
        $node[ 'href' ] = $href;
        $node[ 'icon' ] = $icon;
        $node[ 'default_icon' ] = $default_icon;
        $node[ 'data' ] = [
            'path'       => str_replace( admin_url(), '', $href ),
            'slug'       => $slug,
            'capability' => $capability,
        ];

        return $node;
    } // End live_node()


    /**
     * Get the saved layout (a list of nodes), or an empty list.
     *
     * @return array
     */
    public static function get_layout() : array {
        $layout = get_option( self::OPTION_LAYOUT, [] );

        return is_array( $layout ) ? $layout : [];
    } // End get_layout()


    /**
     * Save the layout.
     *
     * @param array $nodes Sanitized nodes.
     * @return void
     */
    public static function save_layout( array $nodes ) : void {
        update_option( self::OPTION_LAYOUT, $nodes, false );
    } // End save_layout()


    /**
     * Merge a saved layout with the live menu.
     *
     * @param array $saved
     * @return array
     */
    public static function merge( array $saved ) : array {
        $live = self::live_menu();
        $used = [];
        $nodes = self::merge_level( $saved, $live, $used );

        self::append_unused_children( $nodes, $live, $used );

        foreach ( $live[ 'top' ] as $id ) {
            if ( isset( $used[ $id ] ) ) {
                continue;
            }

            $node = $live[ 'items' ][ $id ];
            $used[ $id ] = true;

            foreach ( $live[ 'children' ][ $id ] as $child_id ) {
                if ( ! isset( $used[ $child_id ] ) ) {
                    $used[ $child_id ] = true;
                    $node[ 'children' ][] = $live[ 'items' ][ $child_id ];
                }
            }

            $nodes[] = $node;
        }

        return self::decorate( $nodes );
    } // End merge()


    /**
     * Merge one level of saved nodes against the live menu.
     *
     * @param array $saved
     * @param array $live
     * @param array $used
     * @return array
     */
    private static function merge_level( array $saved, array $live, array &$used ) : array {
        $result = [];

        foreach ( $saved as $node ) {
            $children = self::merge_level( (array) ( $node[ 'children' ] ?? [] ), $live, $used );

            if ( 'admin' === $node[ 'type' ] ) {
                if ( ! isset( $live[ 'items' ][ $node[ 'id' ] ] ) || isset( $used[ $node[ 'id' ] ] ) ) {
                    $result = array_merge( $result, $children );
                    continue;
                }

                $merged = $live[ 'items' ][ $node[ 'id' ] ];
                $merged[ 'title' ] = $node[ 'title' ];
                $merged[ 'hidden' ] = $node[ 'hidden' ];
                $used[ $node[ 'id' ] ] = true;
                $node = $merged;
            }

            $node[ 'children' ] = $children;
            $result[] = $node;
        }

        return $result;
    } // End merge_level()


    /**
     * Append live submenu items that are not in the saved layout to their parent's children.
     *
     * @param array $nodes
     * @param array $live
     * @param array $used
     * @return void
     */
    private static function append_unused_children( array &$nodes, array $live, array &$used ) : void {
        foreach ( $nodes as &$node ) {
            if ( ! empty( $node[ 'children' ] ) ) {
                self::append_unused_children( $node[ 'children' ], $live, $used );
            }

            if ( 'admin' === $node[ 'type' ] && isset( $live[ 'children' ][ $node[ 'id' ] ] ) ) {
                foreach ( $live[ 'children' ][ $node[ 'id' ] ] as $child_id ) {
                    if ( ! isset( $used[ $child_id ] ) ) {
                        $used[ $child_id ] = true;
                        $node[ 'children' ][] = $live[ 'items' ][ $child_id ];
                    }
                }
            }
        }
    } // End append_unused_children()


    /**
     * Prepare custom links for rendering (they link directly and can be removed).
     *
     * @param array $nodes
     * @return array
     */
    public static function decorate( array $nodes ) : array {
        foreach ( $nodes as &$node ) {
            if ( 'custom' === $node[ 'type' ] ) {
                $node[ 'href' ] = $node[ 'url' ];
                $node[ 'orig' ] = $node[ 'title' ];
                $node[ 'removable' ] = true;
                $node[ 'data' ] = [ 'path' => $node[ 'url' ] ];
            }

            if ( ! empty( $node[ 'children' ] ) ) {
                $node[ 'children' ] = self::decorate( $node[ 'children' ] );
            }
        }

        return $nodes;
    } // End decorate()


    /**
     * Get the merged nodes for the current user.
     *
     * @return array
     */
    public static function get_nodes() : array {
        return self::merge( self::get_layout() );
    } // End get_nodes()


    /**
     * Render the page.
     *
     * @return void
     */
    public static function render_page() : void {
        if ( ! Access::can_view() ) {
            wp_die( esc_html__( 'You do not have permission to view this page.', 'site-perspectives' ) );
        }

        $view = Tree::get_view( 'backend' );
        $html = Tree::render_admin_lists( [ 'main' => self::get_nodes() ], 'backend', $view );
        $orientation = Tree::get_orientation( 'backend' );
        ?>
        <div class="wrap siteperspectives-wrap siteperspectives-content-wrap" id="siteperspectives-app" data-tab="backend" data-orientation="<?php echo esc_attr( $orientation ); ?>">
            <?php Tree::render_options_panel( 'backend', $view ); ?>

            <div class="siteperspectives-layout">
                <?php if ( Access::can_edit() ) : ?>
                    <aside class="siteperspectives-add-panel" id="siteperspectives-add-panel" hidden>
                        <div class="siteperspectives-box">
                            <div class="siteperspectives-box-header"><h2><?php esc_html_e( 'Add Custom Link', 'site-perspectives' ); ?></h2></div>
                            <div class="siteperspectives-box-body" id="siteperspectives-acc-custom">
                                <label class="siteperspectives-edit-field"><span><?php esc_html_e( 'Link Text', 'site-perspectives' ); ?></span><input type="text" class="siteperspectives-new-title"></label>
                                <label class="siteperspectives-edit-field"><span><?php esc_html_e( 'URL', 'site-perspectives' ); ?></span><input type="text" class="siteperspectives-new-url" placeholder="<?php echo esc_attr( admin_url() ); ?>"></label>
                                <button type="button" class="siteperspectives-button siteperspectives-add-custom"><?php esc_html_e( 'Add to Tree', 'site-perspectives' ); ?></button>
                                <hr>
                                <p class="siteperspectives-section-desc"><?php esc_html_e( 'Reordering, renaming and hiding only change this view, never the real admin menu. Menu items added by plugins later appear automatically.', 'site-perspectives' ); ?></p>
                                <button type="button" class="siteperspectives-button siteperspectives-button-danger" id="siteperspectives-reset"><?php esc_html_e( 'Reset Layout', 'site-perspectives' ); ?></button>
                            </div>
                        </div>
                    </aside>
                <?php endif; ?>

                <div class="siteperspectives-main">
                    <ul class="siteperspectives-list siteperspectives-root" data-list="main"><?php echo $html[ 'main' ]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside Tree::render_items(). ?></ul>
                </div>
            </div>
        </div>
        <?php
    } // End render_page()

} // End class Backend

Backend::instance();
