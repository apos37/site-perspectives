<?php
/**
 * TREE AJAX
 *
 * AJAX endpoints shared by the Frontend and Backend tabs: saving, re-rendering,
 * searching content to add, adding items, remembering orientation and meta key suggestions.
 */

namespace PluginRx\SitePerspectives;

if ( ! defined( 'ABSPATH' ) ) exit;


class TreeAjax {

    /**
     * Maximum size in bytes of a posted tree payload.
     */
    public const MAX_PAYLOAD = 1048576;


    /**
     * @var TreeAjax|null Singleton instance
     */
    private static ?TreeAjax $instance = null;


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
        add_action( 'wp_ajax_siteperspectives_save_tree', [ $this, 'save_tree' ] );
        add_action( 'wp_ajax_siteperspectives_render_tree', [ $this, 'render_tree' ] );
        add_action( 'wp_ajax_siteperspectives_search_posts', [ $this, 'search_posts' ] );
        add_action( 'wp_ajax_siteperspectives_add_items', [ $this, 'add_items' ] );
        add_action( 'wp_ajax_siteperspectives_save_orientation', [ $this, 'save_orientation' ] );
        add_action( 'wp_ajax_siteperspectives_meta_keys', [ $this, 'meta_keys' ] );
    } // End __construct()


    /**
     * Verify the nonce and permissions for a request, sending an error and exiting on failure.
     *
     * @param string $tab frontend|backend
     * @param bool   $needs_edit Whether the action modifies stored data.
     * @return void
     */
    private function authorize( string $tab, bool $needs_edit = false ) : void {
        check_ajax_referer( 'siteperspectives_tree_nonce', 'nonce' );

        $allowed = $needs_edit ? Access::can_edit() : Access::can_view();

        if ( ! $allowed ) {
            wp_send_json_error( __( 'You do not have permission to do this.', 'site-perspectives' ), 403 );
        }
    } // End authorize()


    /**
     * Get the requested tab from the request, defaulting to frontend.
     *
     * @return string
     */
    private function get_tab() : string {
        $tab = isset( $_POST[ 'tab' ] ) ? sanitize_key( wp_unslash( $_POST[ 'tab' ] ) ) : 'frontend'; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in authorize().

        return 'backend' === $tab ? 'backend' : 'frontend';
    } // End get_tab()


    /**
     * Decode a JSON POST field into an array.
     *
     * @param string $field
     * @return array
     */
    private function get_json( string $field ) : array {
        $raw = isset( $_POST[ $field ] ) ? wp_unslash( $_POST[ $field ] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified in authorize(); decoded and sanitized per field by the caller.

        if ( ! is_string( $raw ) || '' === $raw || strlen( $raw ) > self::MAX_PAYLOAD ) {
            return [];
        }

        $decoded = json_decode( $raw, true );

        return is_array( $decoded ) ? $decoded : [];
    } // End get_json()


    /**
     * Sanitize the posted tree for a tab into clean node lists.
     *
     * @param string $tab
     * @param array  $payload
     * @return array Associative array of list name => nodes.
     */
    private function sanitize_payload( string $tab, array $payload ) : array {
        if ( 'backend' === $tab ) {
            $count = 0;

            return [ 'main' => Tree::sanitize_nodes( $payload[ 'main' ] ?? [], 'backend', null, $count ) ];
        }

        $raw_ids = Tree::collect_post_ids( (array) ( $payload[ 'main' ] ?? [] ) );
        $raw_ids = Tree::collect_post_ids( (array) ( $payload[ 'utility' ] ?? [] ), $raw_ids );
        $valid = Tree::load_posts( array_keys( $raw_ids ) );
        $count = 0;

        return [
            'main'    => Tree::sanitize_nodes( $payload[ 'main' ] ?? [], 'frontend', $valid, $count ),
            'utility' => Tree::sanitize_nodes( $payload[ 'utility' ] ?? [], 'frontend', $valid, $count ),
        ];
    } // End sanitize_payload()


    /**
     * AJAX: save the tree.
     *
     * @return void
     */
    public function save_tree() : void {
        $tab = $this->get_tab();
        $this->authorize( $tab, true );

        $payload = $this->get_json( 'tree' );
        $lists = $this->sanitize_payload( $tab, $payload );

        if ( 'backend' === $tab ) {
            Backend::save_layout( $lists[ 'main' ] );
        } else {
            if ( isset( $payload[ 'home' ] ) && is_string( $payload[ 'home' ] ) ) {
                update_option( 'siteperspectives_home_title', sanitize_text_field( $payload[ 'home' ] ), false );
            }

            Tree::save_tree( $lists );
        }

        wp_send_json_success( [ 'cssUrl' => DynamicCss::versioned_url( 'admin' ) ] );
    } // End save_tree()


    /**
     * AJAX: re-render the tree from the posted (possibly unsaved) state, optionally saving the view options.
     *
     * @return void
     */
    public function render_tree() : void {
        $tab = $this->get_tab();
        $this->authorize( $tab );

        $view = Tree::sanitize_view( $this->get_json( 'view' ), $tab );

        if ( ! empty( $_POST[ 'save_view' ] ) && Access::can_edit() ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in authorize().
            Tree::save_view( $tab, $view );
        }

        $lists = $this->sanitize_payload( $tab, $this->get_json( 'tree' ) );

        if ( 'backend' === $tab ) {
            $lists[ 'main' ] = Backend::merge( $lists[ 'main' ] );
        }

        wp_send_json_success( Tree::render_admin_lists( $lists, $tab, $view ) );
    } // End render_tree()


    /**
     * AJAX: search content to add to the tree.
     *
     * @return void
     */
    public function search_posts() : void {
        $this->authorize( 'frontend' );

        $post_type = isset( $_POST[ 'post_type' ] ) ? sanitize_key( wp_unslash( $_POST[ 'post_type' ] ) ) : 'page'; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in authorize().
        $search = isset( $_POST[ 'search' ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'search' ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in authorize().
        $paged = isset( $_POST[ 'paged' ] ) ? max( 1, absint( $_POST[ 'paged' ] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in authorize().

        if ( ! array_key_exists( $post_type, Helpers::tree_post_types() ) ) {
            wp_send_json_error( __( 'Invalid post type.', 'site-perspectives' ) );
        }

        $query = new \WP_Query( [
            'post_type'      => $post_type,
            'post_status'    => [ 'publish', 'draft', 'pending', 'private', 'future' ],
            's'              => $search,
            'paged'          => $paged,
            'posts_per_page' => 20,
            'orderby'        => '' !== $search ? 'relevance' : 'date',
            'order'          => 'DESC',
        ] );

        $items = [];

        foreach ( $query->posts as $post ) {
            if ( ! current_user_can( 'edit_post', $post->ID ) ) {
                continue;
            }

            $status = get_post_status_object( $post->post_status );
            $title = get_the_title( $post );

            $items[] = [
                'id'     => (int) $post->ID,
                'title'  => '' !== $title ? $title : __( '(no title)', 'site-perspectives' ),
                'status' => ( $status && 'publish' !== $post->post_status ) ? $status->label : '',
            ];
        }

        wp_send_json_success( [
            'items'   => $items,
            'hasMore' => $paged < (int) $query->max_num_pages,
        ] );
    } // End search_posts()


    /**
     * AJAX: build new nodes and return their rendered markup.
     *
     * @return void
     */
    public function add_items() : void {
        $tab = $this->get_tab();
        $this->authorize( $tab, true );

        $requested = $this->get_json( 'items' );
        $nodes = [];

        foreach ( array_slice( $requested, 0, 100 ) as $item ) {
            $kind = isset( $item[ 'kind' ] ) ? sanitize_key( $item[ 'kind' ] ) : '';

            if ( 'backend' === $tab && 'custom' !== $kind ) {
                continue;
            }

            if ( 'post' === $kind ) {
                $node = Tree::blank_node( 'post' );
                $node[ 'post_id' ] = absint( $item[ 'id' ] ?? 0 );
            } elseif ( 'custom' === $kind ) {
                $node = Tree::blank_node( 'custom' );
                $node[ 'title' ] = sanitize_text_field( $item[ 'title' ] ?? '' );
                $node[ 'url' ] = esc_url_raw( $item[ 'url' ] ?? '' );

                if ( '' === $node[ 'title' ] || '' === $node[ 'url' ] ) {
                    continue;
                }
            } elseif ( 'parent' === $kind ) {
                $node = Tree::blank_node( 'parent' );
                $node[ 'title' ] = sanitize_text_field( $item[ 'title' ] ?? '' );
                $node[ 'icon' ] = Helpers::icon( $item[ 'icon' ] ?? '' );
                $node[ 'color' ] = Helpers::hex( $item[ 'color' ] ?? '' );

                if ( '' === $node[ 'title' ] ) {
                    continue;
                }
            } else {
                continue;
            }

            $nodes[] = $node;
        }

        $valid = Tree::load_posts( array_keys( Tree::collect_post_ids( $nodes ) ) );
        $count = 0;
        $nodes = Tree::sanitize_nodes( $nodes, $tab, 'backend' === $tab ? null : $valid, $count );

        if ( 'backend' === $tab ) {
            $nodes = Backend::decorate( $nodes );
        }

        $html = Tree::render_admin_lists( [ 'new' => $nodes ], $tab, Tree::get_view( $tab ) );

        wp_send_json_success( [ 'html' => $html[ 'new' ], 'count' => count( $nodes ) ] );
    } // End add_items()


    /**
     * AJAX: remember a user's vertical/horizontal choice.
     *
     * @return void
     */
    public function save_orientation() : void {
        $tab = $this->get_tab();
        $this->authorize( $tab );

        $value = isset( $_POST[ 'orientation' ] ) ? sanitize_key( wp_unslash( $_POST[ 'orientation' ] ) ) : 'vertical'; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in authorize().

        update_user_meta( get_current_user_id(), 'siteperspectives_orientation_' . $tab, 'horizontal' === $value ? 'horizontal' : 'vertical' );

        wp_send_json_success();
    } // End save_orientation()


    /**
     * AJAX: suggest existing custom field keys for the Custom Meta option.
     *
     * @return void
     */
    public function meta_keys() : void {
        $this->authorize( 'frontend' );

        $keys = get_transient( 'siteperspectives_meta_keys' );

        if ( ! is_array( $keys ) ) {
            global $wpdb;

            $like = $wpdb->esc_like( '_' ) . '%';
            $keys = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT meta_key FROM {$wpdb->postmeta} WHERE meta_key NOT LIKE %s ORDER BY meta_key ASC LIMIT 300", $like ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- cached in a transient below; only runs when the options panel is used.
            $keys = is_array( $keys ) ? array_map( 'sanitize_text_field', $keys ) : [];

            set_transient( 'siteperspectives_meta_keys', $keys, HOUR_IN_SECONDS );
        }

        wp_send_json_success( $keys );
    } // End meta_keys()

} // End class TreeAjax

TreeAjax::instance();
