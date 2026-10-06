<?php
/**
 * TREE
 *
 * Data layer and renderer shared by the Frontend tab, Backend tab and the visitor shortcode.
 *
 * A tree is a nested array of nodes:
 * [ 'id' => 'n_abc', 'type' => 'post|custom|parent|admin', 'post_id' => 0, 'title' => '', 'url' => '',
 *   'icon' => '', 'color' => '', 'hidden' => false, 'children' => [] ]
 */

namespace PluginRx\SitePerspectives;

if ( ! defined( 'ABSPATH' ) ) exit;


class Tree {

    public const OPTION_TREE = 'siteperspectives_frontend_tree';
    public const OPTION_VIEW = 'siteperspectives_view_options';
    public const OPTION_VERSION = 'siteperspectives_tree_version';
    public const MAX_NODES = 2000;
    public const MAX_DEPTH = 10;


    /**
     * @var Tree|null Singleton instance
     */
    private static ?Tree $instance = null;


    /**
     * @var array|null In-request cache of the resolved tree
     */
    private static ?array $tree_cache = null;


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
        add_action( 'transition_post_status', [ $this, 'maybe_bump_version' ], 10, 3 );
        add_action( 'deleted_post', [ __CLASS__, 'bump_version' ] );
    } // End __construct()


    /**
     * Bump the cache version when a post's visibility may have changed.
     *
     * @param string   $new_status
     * @param string   $old_status
     * @param \WP_Post $post
     * @return void
     */
    public function maybe_bump_version( $new_status, $old_status, $post ) : void {
        if ( $new_status === $old_status && 'publish' !== $new_status ) {
            return;
        }

        if ( 'publish' === $new_status || 'publish' === $old_status ) {
            self::bump_version();
        }
    } // End maybe_bump_version()


    /**
     * Bump the cache version used by the shortcode output cache.
     *
     * @return void
     */
    public static function bump_version() : void {
        update_option( self::OPTION_VERSION, microtime( true ), false );
    } // End bump_version()


    /**
     * Get the current cache version.
     *
     * @return string
     */
    public static function version() : string {
        return (string) get_option( self::OPTION_VERSION, '0' );
    } // End version()


    /**
     * Get the data fields that can be displayed on a card.
     *
     * @param string $tab frontend|backend
     * @return array Associative array of key => label.
     */
    public static function get_fields( string $tab = 'frontend' ) : array {
        if ( 'backend' === $tab ) {
            return [
                'path'       => __( 'Path', 'site-perspectives' ),
                'slug'       => __( 'Menu Slug', 'site-perspectives' ),
                'capability' => __( 'Capability', 'site-perspectives' ),
            ];
        }

        return [
            'path'       => __( 'Path', 'site-perspectives' ),
            'url'        => __( 'Full URL', 'site-perspectives' ),
            'view'       => __( 'View Page', 'site-perspectives' ),
            'slug'       => __( 'Slug', 'site-perspectives' ),
            'post_type'  => __( 'Post Type', 'site-perspectives' ),
            'status'     => __( 'Status', 'site-perspectives' ),
            'id'         => __( 'ID', 'site-perspectives' ),
            'author'     => __( 'Author', 'site-perspectives' ),
            'published'  => __( 'Published', 'site-perspectives' ),
            'modified'   => __( 'Last Modified', 'site-perspectives' ),
            'template'   => __( 'Template', 'site-perspectives' ),
            'categories' => __( 'Categories', 'site-perspectives' ),
            'tags'       => __( 'Tags', 'site-perspectives' ),
            'terms'      => __( 'Other Taxonomies', 'site-perspectives' ),
            'parent'     => __( 'Parent', 'site-perspectives' ),
            'menu_order' => __( 'Menu Order', 'site-perspectives' ),
        ];
    } // End get_fields()


    /**
     * Get the default view options for a tab.
     *
     * @param string $tab
     * @return array
     */
    public static function default_view( string $tab ) : array {
        if ( 'backend' === $tab ) {
            return [ 'collapsed' => [ 'path' ], 'expanded' => [ 'capability' ], 'meta' => [] ];
        }

        return [
            'collapsed' => [ 'path' ],
            'expanded'  => [ 'post_type', 'status', 'id', 'categories', 'tags', 'modified' ],
            'meta'      => [],
        ];
    } // End default_view()


    /**
     * Get the saved view options for a tab, merged with defaults.
     *
     * @param string $tab
     * @return array
     */
    public static function get_view( string $tab ) : array {
        $saved = get_option( self::OPTION_VIEW, [] );
        $saved = is_array( $saved ) && isset( $saved[ $tab ] ) && is_array( $saved[ $tab ] ) ? $saved[ $tab ] : [];

        return self::sanitize_view( array_merge( self::default_view( $tab ), $saved ), $tab );
    } // End get_view()


    /**
     * Sanitize view options.
     *
     * @param mixed  $input
     * @param string $tab
     * @return array
     */
    public static function sanitize_view( $input, string $tab ) : array {
        $input = is_array( $input ) ? $input : [];
        $valid = array_keys( self::get_fields( $tab ) );
        $meta = [];

        if ( 'frontend' === $tab && ! empty( $input[ 'meta' ] ) && is_array( $input[ 'meta' ] ) ) {
            foreach ( array_slice( $input[ 'meta' ], 0, 20 ) as $row ) {
                $key = isset( $row[ 'key' ] ) ? mb_substr( sanitize_text_field( $row[ 'key' ] ), 0, 191 ) : '';

                if ( '' === $key ) {
                    continue;
                }

                $label = isset( $row[ 'label' ] ) ? mb_substr( sanitize_text_field( $row[ 'label' ] ), 0, 60 ) : '';

                $meta[] = [
                    'key'       => $key,
                    'label'     => '' !== $label ? $label : $key,
                    'collapsed' => ! empty( $row[ 'collapsed' ] ),
                    'expanded'  => ! empty( $row[ 'expanded' ] ),
                ];
            }
        }

        $collapsed = array_values( array_intersect( array_map( 'sanitize_key', (array) ( $input[ 'collapsed' ] ?? [] ) ), $valid ) );
        $expanded = array_values( array_intersect( array_map( 'sanitize_key', (array) ( $input[ 'expanded' ] ?? [] ) ), $valid ) );

        return [
            'collapsed' => $collapsed,
            'expanded'  => $expanded,
            'meta'      => $meta,
        ];
    } // End sanitize_view()


    /**
     * Save view options for a tab.
     *
     * @param string $tab
     * @param array  $view Sanitized view options.
     * @return void
     */
    public static function save_view( string $tab, array $view ) : void {
        $saved = get_option( self::OPTION_VIEW, [] );
        $saved = is_array( $saved ) ? $saved : [];
        $saved[ $tab ] = $view;

        update_option( self::OPTION_VIEW, $saved, false );
    } // End save_view()


    /**
     * Build the list of field keys to display for a group, including custom meta rows.
     *
     * @param array  $view
     * @param string $group collapsed|expanded
     * @return array
     */
    public static function active_fields( array $view, string $group ) : array {
        $fields = (array) ( $view[ $group ] ?? [] );

        foreach ( (array) ( $view[ 'meta' ] ?? [] ) as $row ) {
            if ( ! empty( $row[ $group ] ) ) {
                $fields[] = 'meta:' . $row[ 'key' ];
            }
        }

        return $fields;
    } // End active_fields()


    /**
     * Get a map of field key => label, including custom meta rows.
     *
     * @param array  $view
     * @param string $tab
     * @return array
     */
    public static function field_labels( array $view, string $tab ) : array {
        $labels = self::get_fields( $tab );

        foreach ( (array) ( $view[ 'meta' ] ?? [] ) as $row ) {
            $labels[ 'meta:' . $row[ 'key' ] ] = $row[ 'label' ];
        }

        return $labels;
    } // End field_labels()


    /**
     * Get the saved tree, or a default one built from the primary menu.
     *
     * @return array [ 'main' => nodes, 'utility' => nodes ]
     */
    public static function get_tree() : array {
        if ( null !== self::$tree_cache ) {
            return self::$tree_cache;
        }

        $saved = get_option( self::OPTION_TREE, null );

        if ( is_array( $saved ) && isset( $saved[ 'main' ] ) ) {
            self::$tree_cache = [
                'main'    => (array) $saved[ 'main' ],
                'utility' => (array) ( $saved[ 'utility' ] ?? [] ),
            ];
        } else {
            self::$tree_cache = self::default_tree();
        }

        return self::$tree_cache;
    } // End get_tree()


    /**
     * Whether a tree has been saved yet.
     *
     * @return bool
     */
    public static function has_saved_tree() : bool {
        return is_array( get_option( self::OPTION_TREE, null ) );
    } // End has_saved_tree()


    /**
     * Save a tree.
     *
     * @param array $tree [ 'main' => nodes, 'utility' => nodes ]
     * @return void
     */
    public static function save_tree( array $tree ) : void {
        update_option( self::OPTION_TREE, $tree, false );
        self::$tree_cache = null;
        self::bump_version();
    } // End save_tree()


    /**
     * Build the default tree from the primary menu (or the page hierarchy as a fallback).
     *
     * @return array
     */
    public static function default_tree() : array {
        $main = self::menu_nodes();

        if ( empty( $main ) ) {
            $main = self::page_nodes();
        }

        return [
            'main'    => $main,
            'utility' => self::utility_defaults(),
        ];
    } // End default_tree()


    /**
     * Find the primary menu ID.
     *
     * @return int
     */
    public static function find_primary_menu_id() : int {
        $locations = get_nav_menu_locations();
        $menu_id = 0;

        foreach ( $locations as $location => $id ) {
            if ( $id && preg_match( '/primary|main|header|menu-1/i', (string) $location ) ) {
                $menu_id = (int) $id;
                break;
            }
        }

        if ( ! $menu_id ) {
            foreach ( $locations as $id ) {
                if ( $id ) {
                    $menu_id = (int) $id;
                    break;
                }
            }
        }

        return (int) apply_filters( 'siteperspectives_default_menu_id', $menu_id );
    } // End find_primary_menu_id()


    /**
     * Convert a classic navigation menu into nodes.
     *
     * @param int $menu_id Menu ID, or 0 to use the primary menu.
     * @return array
     */
    public static function menu_nodes( int $menu_id = 0 ) : array {
        $menu_id = $menu_id ? $menu_id : self::find_primary_menu_id();

        if ( ! $menu_id ) {
            return [];
        }

        $items = wp_get_nav_menu_items( $menu_id, [ 'update_post_term_cache' => false ] );

        if ( empty( $items ) || ! is_array( $items ) ) {
            return [];
        }

        $has_children = [];

        foreach ( $items as $item ) {
            if ( $item->menu_item_parent ) {
                $has_children[ (int) $item->menu_item_parent ] = true;
            }
        }

        $public_types = array_keys( Helpers::public_post_types() );
        $by_parent = [];

        foreach ( $items as $item ) {
            $title = wp_strip_all_tags( $item->title );
            $node = self::blank_node( 'custom' );
            $node[ 'title' ] = $title;
            $node[ 'url' ] = $item->url;

            if ( 'post_type' === $item->type && in_array( $item->object, $public_types, true ) ) {
                $node[ 'type' ] = 'post';
                $node[ 'post_id' ] = (int) $item->object_id;
                $node[ 'url' ] = '';
                $node[ 'title' ] = ( get_the_title( $item->object_id ) === $title ) ? '' : $title;
            } elseif ( ! empty( $has_children[ (int) $item->ID ] ) && in_array( $item->url, [ '', '#' ], true ) ) {
                $node[ 'type' ] = 'parent';
                $node[ 'url' ] = '';
            }

            $node[ '_menu_id' ] = (int) $item->ID;
            $by_parent[ (int) $item->menu_item_parent ][] = $node;
        }

        return self::attach_menu_children( $by_parent, 0 );
    } // End menu_nodes()


    /**
     * Recursively attach menu children to their parents.
     *
     * @param array $by_parent
     * @param int   $parent_id
     * @return array
     */
    private static function attach_menu_children( array $by_parent, int $parent_id ) : array {
        $nodes = [];

        foreach ( $by_parent[ $parent_id ] ?? [] as $node ) {
            $node[ 'children' ] = self::attach_menu_children( $by_parent, $node[ '_menu_id' ] );
            unset( $node[ '_menu_id' ] );
            $nodes[] = $node;
        }

        return $nodes;
    } // End attach_menu_children()


    /**
     * Convert the published page hierarchy into nodes.
     *
     * @return array
     */
    public static function page_nodes() : array {
        $pages = get_pages( [
            'post_status' => 'publish',
            'sort_column' => 'menu_order,post_title',
            'number'      => 500,
        ] );

        $by_parent = [];

        foreach ( $pages as $page ) {
            $node = self::blank_node( 'post' );
            $node[ 'post_id' ] = (int) $page->ID;
            $by_parent[ (int) $page->post_parent ][] = $node;
        }

        return self::attach_page_children( $by_parent, 0 );
    } // End page_nodes()


    /**
     * Recursively attach page children.
     *
     * @param array $by_parent
     * @param int   $parent_id
     * @return array
     */
    private static function attach_page_children( array $by_parent, int $parent_id ) : array {
        $nodes = [];

        foreach ( $by_parent[ $parent_id ] ?? [] as $node ) {
            $node[ 'children' ] = self::attach_page_children( $by_parent, $node[ 'post_id' ] );
            $nodes[] = $node;
        }

        if ( 0 !== $parent_id ) {
            return $nodes;
        }

        $known = [];

        foreach ( $by_parent as $siblings ) {
            foreach ( $siblings as $sibling ) {
                $known[ $sibling[ 'post_id' ] ] = true;
            }
        }

        foreach ( $by_parent as $pid => $siblings ) {
            if ( 0 !== $pid && ! isset( $known[ $pid ] ) ) {
                foreach ( $siblings as $orphan ) {
                    $orphan[ 'children' ] = self::attach_page_children( $by_parent, $orphan[ 'post_id' ] );
                    $nodes[] = $orphan;
                }
            }
        }

        return $nodes;
    } // End attach_page_children()


    /**
     * Get the suggested utility pages that exist on this site.
     *
     * @return array List of nodes.
     */
    public static function utility_suggestions() : array {
        $suggestions = [];

        $login = self::blank_node( 'custom' );
        $login[ 'title' ] = __( 'Log In', 'site-perspectives' );
        $login[ 'url' ] = wp_login_url();
        $suggestions[ 'login' ] = $login;

        if ( get_option( 'users_can_register' ) ) {
            $register = self::blank_node( 'custom' );
            $register[ 'title' ] = __( 'Register', 'site-perspectives' );
            $register[ 'url' ] = wp_registration_url();
            $suggestions[ 'register' ] = $register;
        }

        $lost = self::blank_node( 'custom' );
        $lost[ 'title' ] = __( 'Lost Password', 'site-perspectives' );
        $lost[ 'url' ] = wp_lostpassword_url();
        $suggestions[ 'lost_password' ] = $lost;

        $privacy_id = (int) get_option( 'wp_page_for_privacy_policy' );

        if ( $privacy_id && 'publish' === get_post_status( $privacy_id ) ) {
            $privacy = self::blank_node( 'post' );
            $privacy[ 'post_id' ] = $privacy_id;
            $suggestions[ 'privacy' ] = $privacy;
        }

        $posts_page_id = (int) get_option( 'page_for_posts' );

        if ( $posts_page_id && 'publish' === get_post_status( $posts_page_id ) ) {
            $blog = self::blank_node( 'post' );
            $blog[ 'post_id' ] = $posts_page_id;
            $suggestions[ 'posts_page' ] = $blog;
        }

        $search = self::blank_node( 'custom' );
        $search[ 'title' ] = __( 'Search Results', 'site-perspectives' );
        $search[ 'url' ] = home_url( '/?s=' );
        $suggestions[ 'search' ] = $search;

        return apply_filters( 'siteperspectives_utility_suggestions', $suggestions );
    } // End utility_suggestions()


    /**
     * Get the utility nodes included by default.
     *
     * @return array
     */
    public static function utility_defaults() : array {
        $suggestions = self::utility_suggestions();
        $defaults = [];

        foreach ( [ 'login', 'privacy' ] as $key ) {
            if ( isset( $suggestions[ $key ] ) ) {
                $defaults[] = $suggestions[ $key ];
            }
        }

        return $defaults;
    } // End utility_defaults()


    /**
     * Create a blank node.
     *
     * @param string $type
     * @return array
     */
    public static function blank_node( string $type ) : array {
        return [
            'id'       => Helpers::generate_id(),
            'type'     => $type,
            'post_id'  => 0,
            'title'    => '',
            'url'      => '',
            'icon'     => '',
            'color'    => '',
            'hidden'   => false,
            'hide_public' => false,
            'children' => [],
        ];
    } // End blank_node()


    /**
     * Sanitize a list of nodes coming from the browser.
     *
     * @param mixed  $nodes
     * @param string $tab     frontend|backend
     * @param array  $valid   Valid post IDs (frontend) or null to skip check.
     * @param int    $count   Running node count.
     * @param int    $depth
     * @return array
     */
    public static function sanitize_nodes( $nodes, string $tab, ?array $valid, int &$count = 0, int $depth = 0 ) : array {
        $clean = [];

        if ( ! is_array( $nodes ) ) {
            return $clean;
        }

        $types = 'backend' === $tab ? [ 'admin', 'custom' ] : [ 'post', 'custom', 'parent' ];

        foreach ( $nodes as $raw ) {
            if ( $count >= self::MAX_NODES || ! is_array( $raw ) ) {
                break;
            }

            $type = isset( $raw[ 'type' ] ) ? sanitize_key( $raw[ 'type' ] ) : '';

            if ( ! in_array( $type, $types, true ) ) {
                continue;
            }

            $node = self::blank_node( $type );
            $id = isset( $raw[ 'id' ] ) ? preg_replace( '/[^a-zA-Z0-9_|.\-]/', '', (string) $raw[ 'id' ] ) : '';
            $node[ 'id' ] = '' !== $id ? mb_substr( $id, 0, 191 ) : $node[ 'id' ];
            $node[ 'title' ] = isset( $raw[ 'title' ] ) ? mb_substr( sanitize_text_field( $raw[ 'title' ] ), 0, 200 ) : '';
            $node[ 'icon' ] = Helpers::icon( $raw[ 'icon' ] ?? '' );
            $node[ 'color' ] = Helpers::hex( $raw[ 'color' ] ?? '' );
            $node[ 'hidden' ] = ! empty( $raw[ 'hidden' ] );
            $node[ 'hide_public' ] = 'admin' !== $type && ! empty( $raw[ 'hide_public' ] );

            if ( 'post' === $type ) {
                $node[ 'post_id' ] = absint( $raw[ 'post_id' ] ?? 0 );

                if ( ! $node[ 'post_id' ] || ( null !== $valid && ! isset( $valid[ $node[ 'post_id' ] ] ) ) ) {
                    continue;
                }
            }

            if ( 'custom' === $type ) {
                $node[ 'url' ] = esc_url_raw( $raw[ 'url' ] ?? '' );

                if ( '' === $node[ 'url' ] || '' === $node[ 'title' ] ) {
                    continue;
                }
            }

            if ( 'admin' === $type ) {
                $node[ 'url' ] = '';
            }

            if ( 'parent' === $type && '' === $node[ 'title' ] ) {
                $node[ 'title' ] = __( 'Untitled', 'site-perspectives' );
            }

            $count++;

            if ( $depth < self::MAX_DEPTH && ! empty( $raw[ 'children' ] ) ) {
                $node[ 'children' ] = self::sanitize_nodes( $raw[ 'children' ], $tab, $valid, $count, $depth + 1 );
            }

            $clean[] = $node;
        }

        return $clean;
    } // End sanitize_nodes()


    /**
     * Collect every distinct item color from a list of nodes.
     *
     * @param array $nodes
     * @param array $colors Passed by reference.
     * @return void
     */
    public static function collect_colors( array $nodes, array &$colors ) : void {
        foreach ( $nodes as $node ) {
            if ( ! is_array( $node ) ) {
                continue;
            }

            $hex = Helpers::hex( $node[ 'color' ] ?? '' );

            if ( '' !== $hex ) {
                $colors[] = strtolower( $hex );
            }

            if ( ! empty( $node[ 'children' ] ) ) {
                self::collect_colors( (array) $node[ 'children' ], $colors );
            }
        }
    } // End collect_colors()


    /**
     * Collect every post ID referenced by raw or clean nodes.
     *
     * @param array $nodes
     * @param array $ids
     * @return array
     */
    public static function collect_post_ids( array $nodes, array $ids = [] ) : array {
        foreach ( $nodes as $node ) {
            if ( ! is_array( $node ) ) {
                continue;
            }

            if ( isset( $node[ 'type' ] ) && 'post' === $node[ 'type' ] && ! empty( $node[ 'post_id' ] ) ) {
                $ids[ absint( $node[ 'post_id' ] ) ] = true;
            }

            if ( ! empty( $node[ 'children' ] ) && is_array( $node[ 'children' ] ) ) {
                $ids = self::collect_post_ids( $node[ 'children' ], $ids );
            }
        }

        return $ids;
    } // End collect_post_ids()


    /**
     * Load every post needed by a set of node lists in a single query.
     *
     * @param array $ids    Post IDs (values, or keys when $keys is true).
     * @param bool  $public Only return published posts.
     * @return array Associative array of ID => WP_Post.
     */
    public static function load_posts( array $ids, bool $public = false ) : array {
        $ids = array_values( array_filter( array_map( 'absint', $ids ) ) );

        if ( empty( $ids ) ) {
            return [];
        }

        $query = new \WP_Query( [
            'post__in'            => $ids,
            'post_type'           => array_keys( Helpers::public_post_types() ),
            'post_status'         => $public ? 'publish' : 'any',
            'posts_per_page'      => count( $ids ),
            'orderby'             => 'post__in',
            'no_found_rows'       => true,
            'ignore_sticky_posts' => true,
        ] );

        $posts = [];

        foreach ( $query->posts as $post ) {
            $posts[ (int) $post->ID ] = $post;
        }

        return $posts;
    } // End load_posts()


    /**
     * Get the Dashicon slug that represents a node by default.
     *
     * @param array         $node
     * @param \WP_Post|null $post
     * @return string
     */
    public static function default_icon( array $node, ?\WP_Post $post ) : string {
        if ( 'parent' === $node[ 'type' ] ) {
            return 'category';
        }

        if ( 'custom' === $node[ 'type' ] ) {
            return 'admin-links';
        }

        if ( 'admin' === $node[ 'type' ] ) {
            return '';
        }

        if ( $post ) {
            $object = get_post_type_object( $post->post_type );

            if ( $object && is_string( $object->menu_icon ) && 0 === strpos( $object->menu_icon, 'dashicons-' ) ) {
                return Helpers::icon( $object->menu_icon, 'admin-page' );
            }

            return 'page' === $post->post_type ? 'admin-page' : 'admin-post';
        }

        return 'warning';
    } // End default_icon()


    /**
     * Get the value of a data field for a node.
     *
     * @param string        $key
     * @param array         $node
     * @param \WP_Post|null $post
     * @param bool          $public Whether the value will be shown to visitors.
     * @return string Plain text, to be escaped by the caller.
     */
    public static function field_value( string $key, array $node, ?\WP_Post $post, bool $public = false ) : string {
        if ( ! empty( $node[ 'data' ] ) && is_array( $node[ 'data' ] ) ) {
            return isset( $node[ 'data' ][ $key ] ) ? (string) $node[ 'data' ][ $key ] : '';
        }

        if ( ! $post ) {
            return self::custom_field_value( $key, $node );
        }

        if ( 0 === strpos( $key, 'meta:' ) ) {
            return self::meta_value( substr( $key, 5 ), $post, $public );
        }

        switch ( $key ) {
            case 'path':
                return Helpers::url_path( (string) get_permalink( $post ) );
            case 'url':
            case 'view':
                return (string) get_permalink( $post );
            case 'slug':
                return $post->post_name;
            case 'post_type':
                $object = get_post_type_object( $post->post_type );
                return $object ? $object->labels->singular_name : $post->post_type;
            case 'status':
                $status = get_post_status_object( $post->post_status );
                return $status ? $status->label : $post->post_status;
            case 'id':
                return (string) $post->ID;
            case 'author':
                return (string) get_the_author_meta( 'display_name', (int) $post->post_author );
            case 'published':
                return Helpers::format_datetime( $post->post_date );
            case 'modified':
                return Helpers::format_datetime( $post->post_modified );
            case 'template':
                return self::template_label( $post );
            case 'categories':
                return self::term_names( $post, [ 'category' ] );
            case 'tags':
                return self::term_names( $post, [ 'post_tag' ] );
            case 'terms':
                $taxonomies = array_diff( get_object_taxonomies( $post->post_type ), [ 'category', 'post_tag', 'nav_menu', 'post_format' ] );
                return self::term_names( $post, $taxonomies );
            case 'parent':
                return $post->post_parent ? get_the_title( $post->post_parent ) : '';
            case 'menu_order':
                return $post->menu_order ? (string) $post->menu_order : '';
        }

        return '';
    } // End field_value()


    /**
     * Field values for custom links (no post).
     *
     * @param string $key
     * @param array  $node
     * @return string
     */
    private static function custom_field_value( string $key, array $node ) : string {
        if ( 'custom' !== $node[ 'type' ] ) {
            return '';
        }

        switch ( $key ) {
            case 'path':
                return Helpers::url_path( $node[ 'url' ] );
            case 'url':
            case 'view':
                return $node[ 'url' ];
            case 'post_type':
                return __( 'Custom Link', 'site-perspectives' );
        }

        return '';
    } // End custom_field_value()


    /**
     * Get a readable page template name.
     *
     * @param \WP_Post $post
     * @return string
     */
    private static function template_label( \WP_Post $post ) : string {
        static $names = null;

        if ( null === $names ) {
            $names = wp_get_theme()->get_page_templates();
        }

        $slug = get_page_template_slug( $post );

        if ( ! $slug ) {
            return __( 'Default', 'site-perspectives' );
        }

        return $names[ $slug ] ?? $slug;
    } // End template_label()


    /**
     * Get a comma-separated list of term names for a post.
     *
     * @param \WP_Post $post
     * @param array    $taxonomies
     * @return string
     */
    private static function term_names( \WP_Post $post, array $taxonomies ) : string {
        $names = [];

        foreach ( $taxonomies as $taxonomy ) {
            $terms = get_the_terms( $post, $taxonomy );

            if ( is_array( $terms ) ) {
                foreach ( $terms as $term ) {
                    $names[] = $term->name;
                }
            }
        }

        return implode( ', ', $names );
    } // End term_names()


    /**
     * Get a single meta value as a short string.
     *
     * @param string   $key
     * @param \WP_Post $post
     * @param bool     $public
     * @return string
     */
    private static function meta_value( string $key, \WP_Post $post, bool $public ) : string {
        if ( $public && is_protected_meta( $key, 'post' ) && ! in_array( $key, (array) apply_filters( 'siteperspectives_public_protected_meta', [] ), true ) ) {
            return '';
        }

        $value = apply_filters( 'siteperspectives_meta_value', get_post_meta( $post->ID, $key, true ), $key, $post->ID, $public );

        if ( is_array( $value ) || is_object( $value ) ) {
            $value = wp_json_encode( $value );
        }

        $value = wp_strip_all_tags( (string) $value );

        return mb_strlen( $value ) > 120 ? mb_substr( $value, 0, 117 ) . '...' : $value;
    } // End meta_value()


    /**
     * Build the rendering context shared by every node.
     *
     * @param array $args
     * @return array
     */
    public static function context( array $args ) : array {
        $defaults = [
            'tab'        => 'frontend',
            'public'     => false,
            'view'       => self::default_view( 'frontend' ),
            'posts'      => [],
            'icons'      => true,
            'new_tab'    => false,
            'fields'     => [],
            'collapsed'  => [],
            'expanded'   => [],
            'labels'     => [],
        ];

        $ctx = array_merge( $defaults, $args );
        $ctx[ 'labels' ] = self::field_labels( $ctx[ 'view' ], $ctx[ 'tab' ] );

        if ( ! $ctx[ 'public' ] ) {
            $ctx[ 'collapsed' ] = self::active_fields( $ctx[ 'view' ], 'collapsed' );
            $ctx[ 'expanded' ] = self::active_fields( $ctx[ 'view' ], 'expanded' );
        }

        return $ctx;
    } // End context()


    /**
     * Render a list of nodes to HTML.
     *
     * @param array $nodes
     * @param array $ctx   Context from self::context().
     * @param int   $depth
     * @return string
     */
    public static function render_items( array $nodes, array $ctx, int $depth = 0 ) : string {
        $html = '';

        foreach ( $nodes as $node ) {
            $html .= $ctx[ 'public' ] ? self::render_public_item( $node, $ctx, $depth ) : self::render_admin_item( $node, $ctx, $depth );
        }

        return $html;
    } // End render_items()


    /**
     * Render the visible data fields for a node.
     *
     * @param array         $node
     * @param \WP_Post|null $post
     * @param array         $keys
     * @param array         $ctx
     * @param string        $mode inline|rows
     * @return string
     */
    private static function render_fields( array $node, ?\WP_Post $post, array $keys, array $ctx, string $mode ) : string {
        ob_start();

        foreach ( $keys as $key ) {
            $value = self::field_value( $key, $node, $post, $ctx[ 'public' ] );

            if ( '' === $value ) {
                continue;
            }

            $label = $ctx[ 'labels' ][ $key ] ?? $key;
            $class = 'siteperspectives-datum siteperspectives-datum-' . sanitize_html_class( str_replace( ':', '-', $key ) );
            $show_label = $ctx[ 'public' ] && 0 === strpos( $key, 'meta:' );
            $is_link = ! $ctx[ 'public' ] && 'view' === $key;

            if ( 'rows' === $mode ) {
                ?>
                <div class="siteperspectives-datum-row <?php echo esc_attr( $class ); ?>">
                    <span class="siteperspectives-datum-label"><?php echo esc_html( $label ); ?></span>
                    <span class="siteperspectives-datum-value"><?php if ( $is_link ) : ?><a href="<?php echo esc_url( $value ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open', 'site-perspectives' ); ?></a><?php else : echo esc_html( $value ); endif; ?></span>
                </div>
                <?php
            } else {
                ?>
                <span class="<?php echo esc_attr( $class ); ?>"<?php echo ! $show_label ? ' title="' . esc_attr( $label ) . '"' : ''; ?>><?php if ( $show_label && '' !== $label ) : ?><span class="siteperspectives-datum-label"><?php echo esc_html( $label ); ?>:</span> <?php endif; ?><?php if ( $is_link ) : ?><a href="<?php echo esc_url( $value ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $label ); ?></a><?php else : echo esc_html( $value ); endif; ?></span>
                <?php
            }
        }

        return (string) ob_get_clean();
    } // End render_fields()


    /**
     * Render one node in the admin (card with edit controls).
     *
     * @param array $node
     * @param array $ctx
     * @param int   $depth
     * @return string
     */
    private static function render_admin_item( array $node, array $ctx, int $depth ) : string {
        $type = $node[ 'type' ];
        $post = ( 'post' === $type ) ? ( $ctx[ 'posts' ][ $node[ 'post_id' ] ] ?? null ) : null;
        $missing = ( 'post' === $type && ! $post );

        if ( $post ) {
            $orig = get_the_title( $post );
            $orig = '' !== $orig ? $orig : __( '(no title)', 'site-perspectives' );
        } elseif ( $missing ) {
            /* translators: %d: post ID */
            $orig = sprintf( __( 'Missing item #%d', 'site-perspectives' ), $node[ 'post_id' ] );
        } else {
            $orig = $node[ 'orig' ] ?? $node[ 'title' ];
        }

        $title = '' !== $node[ 'title' ] ? $node[ 'title' ] : $orig;
        $icon = $node[ 'icon' ] ? $node[ 'icon' ] : ( $node[ 'default_icon' ] ?? self::default_icon( $node, $post ) );
        $href = (string) ( $node[ 'href' ] ?? '' );

        if ( $post ) {
            $edit = get_edit_post_link( $post->ID, 'raw' );
            $href = $edit ? $edit : (string) get_permalink( $post );
        }

        $classes = [ 'siteperspectives-node', 'siteperspectives-type-' . $type ];

        if ( $missing ) {
            $classes[] = 'siteperspectives-missing';
        }

        if ( $node[ 'hidden' ] ) {
            $classes[] = 'siteperspectives-hidden';
        }

        if ( ! empty( $node[ 'hide_public' ] ) ) {
            $classes[] = 'siteperspectives-is-hide-public';
        }

        if ( $node[ 'color' ] ) {
            $classes[] = Helpers::color_class( $node[ 'color' ] );
        }

        if ( $depth > 0 ) {
            $classes[] = 'siteperspectives-depth-nested';
        }

        $has_url_field = ( 'custom' === $type );

        ob_start();
        ?>
        <li class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" data-id="<?php echo esc_attr( $node[ 'id' ] ); ?>" data-type="<?php echo esc_attr( $type ); ?>" data-post-id="<?php echo esc_attr( (string) $node[ 'post_id' ] ); ?>" data-orig="<?php echo esc_attr( $orig ); ?>">
            <div class="siteperspectives-card">
                <div class="siteperspectives-card-main">
                    <span class="siteperspectives-handle dashicons dashicons-menu" title="<?php esc_attr_e( 'Drag to move', 'site-perspectives' ); ?>"></span>
                    <?php if ( $ctx[ 'icons' ] && '' !== $icon ) : ?>
                        <span class="siteperspectives-node-icon dashicons dashicons-<?php echo esc_attr( $icon ); ?>"></span>
                    <?php endif; ?>
                    <div class="siteperspectives-card-text">
                        <?php if ( '' !== $href ) : ?>
                            <a class="siteperspectives-title" href="<?php echo esc_url( $href ); ?>"><?php echo esc_html( $title ); ?></a>
                        <?php elseif ( $has_url_field ) : ?>
                            <button type="button" class="siteperspectives-title siteperspectives-title-link" aria-expanded="false"><?php echo esc_html( $title ); ?></button>
                        <?php else : ?>
                            <span class="siteperspectives-title"><?php echo esc_html( $title ); ?></span>
                        <?php endif; ?>
                        <?php if ( $has_url_field && '' === $href ) : ?>
                            <div class="siteperspectives-inline-url" hidden>
                                <input type="text" readonly value="<?php echo esc_attr( $node[ 'url' ] ); ?>" class="siteperspectives-inline-url-input">
                                <a href="<?php echo esc_url( $node[ 'url' ] ); ?>" target="_blank" rel="noopener noreferrer" class="siteperspectives-button siteperspectives-button-small"><?php esc_html_e( 'Open', 'site-perspectives' ); ?></a>
                            </div>
                        <?php endif; ?>
                        <div class="siteperspectives-collapsed-data"><?php echo self::render_fields( $node, $post, $ctx[ 'collapsed' ], $ctx, 'inline' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside render_fields(). ?></div>
                    </div>
                    <?php if ( 'admin' !== $type ) : ?>
                        <span class="siteperspectives-badge-hidden dashicons dashicons-hidden" title="<?php esc_attr_e( 'Hidden from the shortcode', 'site-perspectives' ); ?>"></span>
                    <?php endif; ?>
                    <button type="button" class="siteperspectives-toggle" aria-expanded="false" aria-label="<?php esc_attr_e( 'Show details', 'site-perspectives' ); ?>"><span class="dashicons dashicons-arrow-down-alt2"></span></button>
                </div>
                <div class="siteperspectives-expanded" hidden>
                    <div class="siteperspectives-expanded-data"><?php echo self::render_fields( $node, $post, $ctx[ 'expanded' ], $ctx, 'rows' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside render_fields(). ?></div>
                    <div class="siteperspectives-edit-fields">
                        <label class="siteperspectives-edit-field">
                            <span><?php echo 'admin' === $type ? esc_html__( 'Label', 'site-perspectives' ) : esc_html__( 'Title', 'site-perspectives' ); ?></span>
                            <input type="text" class="siteperspectives-in-title" value="<?php echo esc_attr( $title ); ?>">
                        </label>
                        <?php if ( $has_url_field ) : ?>
                            <label class="siteperspectives-edit-field">
                                <span><?php esc_html_e( 'URL', 'site-perspectives' ); ?></span>
                                <input type="text" class="siteperspectives-in-url" value="<?php echo esc_attr( $node[ 'url' ] ); ?>">
                            </label>
                        <?php endif; ?>
                        <?php if ( 'admin' !== $type ) : ?>
                            <div class="siteperspectives-edit-field">
                                <span><?php esc_html_e( 'Icon', 'site-perspectives' ); ?></span>
                                <button type="button" class="siteperspectives-icon-pick siteperspectives-button siteperspectives-button-secondary"><span class="dashicons dashicons-<?php echo esc_attr( $icon ); ?>"></span> <?php esc_html_e( 'Choose', 'site-perspectives' ); ?></button>
                                <input type="hidden" class="siteperspectives-in-icon" value="<?php echo esc_attr( $node[ 'icon' ] ); ?>">
                            </div>
                            <label class="siteperspectives-edit-field">
                                <span><?php esc_html_e( 'Color', 'site-perspectives' ); ?></span>
                                <input type="color" class="siteperspectives-in-color" value="<?php echo esc_attr( strtolower( $node[ 'color' ] ? $node[ 'color' ] : '#306dc4' ) ); ?>" data-set="<?php echo $node[ 'color' ] ? '1' : '0'; ?>">
                            </label>
                        <?php endif; ?>
                        <?php if ( 'admin' === $type ) : ?>
                            <label class="siteperspectives-edit-field siteperspectives-edit-check">
                                <input type="checkbox" class="siteperspectives-in-hidden" <?php checked( $node[ 'hidden' ] ); ?>>
                                <span><?php esc_html_e( 'Hide from this view', 'site-perspectives' ); ?></span>
                            </label>
                        <?php else : ?>
                            <label class="siteperspectives-edit-field siteperspectives-edit-check">
                                <input type="checkbox" class="siteperspectives-in-hide-public" <?php checked( ! empty( $node[ 'hide_public' ] ) ); ?>>
                                <span><?php esc_html_e( 'Hide from Shortcode', 'site-perspectives' ); ?></span>
                            </label>
                        <?php endif; ?>
                        <?php if ( 'admin' !== $type || ! empty( $node[ 'removable' ] ) ) : ?>
                            <button type="button" class="siteperspectives-remove siteperspectives-button siteperspectives-button-danger"><span class="dashicons dashicons-trash"></span> <?php esc_html_e( 'Remove', 'site-perspectives' ); ?></button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <ul class="siteperspectives-list siteperspectives-children"><?php echo self::render_items( $node[ 'children' ], $ctx, $depth + 1 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the recursive render. ?></ul>
        </li>
        <?php

        return (string) ob_get_clean();
    } // End render_admin_item()


    /**
     * Render one node for visitors.
     *
     * @param array $node
     * @param array $ctx
     * @param int   $depth
     * @return string
     */
    private static function render_public_item( array $node, array $ctx, int $depth ) : string {
        $type = $node[ 'type' ];
        $post = null;

        if ( ! empty( $node[ 'hide_public' ] ) ) {
            return '';
        }

        if ( 'post' === $type ) {
            $post = $ctx[ 'posts' ][ $node[ 'post_id' ] ] ?? null;

            if ( ! $post || ! is_post_publicly_viewable( $post ) || '' !== $post->post_password ) {
                return self::render_items( $node[ 'children' ], $ctx, $depth );
            }
        }

        $title = $node[ 'title' ];

        if ( '' === $title && $post ) {
            $title = get_the_title( $post );
        }

        $url = '';

        if ( $post ) {
            $url = (string) get_permalink( $post );
        } elseif ( 'custom' === $type ) {
            $url = $node[ 'url' ];
        }

        $icon = $node[ 'icon' ] ? $node[ 'icon' ] : self::default_icon( $node, $post );
        $children = self::render_items( $node[ 'children' ], $ctx, $depth + 1 );
        $fields = self::render_fields( $node, $post, $ctx[ 'fields' ], $ctx, 'inline' );

        ob_start();
        ?>
        <li class="<?php echo esc_attr( trim( 'siteperspectives-node siteperspectives-type-' . $type . ' ' . Helpers::color_class( $node[ 'color' ] ) . ( ! empty( $node[ 'is_utility' ] ) ? ' siteperspectives-is-utility' : '' ) ) ); ?>">
            <div class="siteperspectives-card">
                <?php if ( $ctx[ 'icons' ] && '' !== $icon ) : ?>
                    <span class="siteperspectives-node-icon dashicons dashicons-<?php echo esc_attr( $icon ); ?>" aria-hidden="true"></span>
                <?php endif; ?>
                <div class="siteperspectives-card-text">
                    <?php if ( '' !== $url ) : ?>
                        <a class="siteperspectives-title" href="<?php echo esc_url( $url ); ?>"<?php echo $ctx[ 'new_tab' ] ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>><?php echo esc_html( $title ); ?></a>
                    <?php else : ?>
                        <span class="siteperspectives-title"><?php echo esc_html( $title ); ?></span>
                    <?php endif; ?>
                    <?php if ( '' !== trim( $fields ) ) : ?>
                        <div class="siteperspectives-data"><?php echo $fields; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside render_fields(). ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ( '' !== $children ) : ?>
                <ul class="siteperspectives-list siteperspectives-children"><?php echo $children; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the recursive render. ?></ul>
            <?php endif; ?>
        </li>
        <?php

        return (string) ob_get_clean();
    } // End render_public_item()


    /**
     * Flatten a tree into a single list for the card layout, skipping hidden nodes and their children.
     *
     * @param array $nodes
     * @param array $posts   Loaded posts keyed by ID.
     * @param bool  $utility Whether the nodes come from the utility list.
     * @return array List of [ 'sort' => title, 'node' => node without children ].
     */
    public static function flatten_public( array $nodes, array $posts, bool $utility = false ) : array {
        $flat = [];

        foreach ( $nodes as $node ) {
            if ( ! empty( $node[ 'hide_public' ] ) ) {
                continue;
            }

            $title = $node[ 'title' ];

            if ( '' === $title && 'post' === $node[ 'type' ] && isset( $posts[ $node[ 'post_id' ] ] ) ) {
                $title = get_the_title( $posts[ $node[ 'post_id' ] ] );
            }

            $children = $node[ 'children' ];
            $node[ 'children' ] = [];
            $node[ 'is_utility' ] = $utility;
            $flat[] = [ 'sort' => wp_strip_all_tags( $title ), 'node' => $node ];
            $flat = array_merge( $flat, self::flatten_public( $children, $posts, $utility ) );
        }

        return $flat;
    } // End flatten_public()


    /**
     * Render one or more node lists for the admin in a single pass, loading posts once.
     *
     * @param array  $lists Associative array of name => nodes.
     * @param string $tab
     * @param array  $view
     * @return array Associative array of name => HTML.
     */
    public static function render_admin_lists( array $lists, string $tab, array $view ) : array {
        $ids = [];

        foreach ( $lists as $nodes ) {
            $ids = self::collect_post_ids( $nodes, $ids );
        }

        $ctx = self::context( [
            'tab'   => $tab,
            'view'  => $view,
            'posts' => self::load_posts( array_keys( $ids ) ),
        ] );

        $html = [];

        foreach ( $lists as $name => $nodes ) {
            $html[ $name ] = self::render_items( $nodes, $ctx );
        }

        return $html;
    } // End render_admin_lists()


    /**
     * Get the title shown in the Home box: the saved title, else the front page's title, else "Home".
     *
     * @return string
     */
    public static function get_home_title() : string {
        $saved = (string) get_option( 'siteperspectives_home_title', '' );

        if ( '' !== $saved ) {
            return $saved;
        }

        $front = (int) get_option( 'page_on_front' );
        $title = $front ? get_the_title( $front ) : '';

        return '' !== $title ? $title : __( 'Home', 'site-perspectives' );
    } // End get_home_title()


    /**
     * Render the "Home" box that sits above the columns in the horizontal (org chart) layout.
     *
     * @param bool $link     Whether to link the box to the home page.
     * @param bool $editable Whether to include the title field used in edit mode.
     * @return string
     */
    public static function render_home( bool $link, bool $editable ) : string {
        $title = self::get_home_title();
        $input = $editable ? '<input type="text" class="siteperspectives-home-input" value="' . esc_attr( $title ) . '" aria-label="' . esc_attr__( 'Home title', 'site-perspectives' ) . '">' : '';
        $inner = '<span class="dashicons dashicons-admin-home" aria-hidden="true"></span><span><strong class="siteperspectives-home-title">' . esc_html( $title ) . '</strong>' . $input . '<small>/</small></span>';

        if ( $link ) {
            return '<a class="siteperspectives-home" href="' . esc_url( home_url( '/' ) ) . '">' . $inner . '</a>';
        }

        return '<div class="siteperspectives-home">' . $inner . '</div>';
    } // End render_home()


    /**
     * Render the expandable Display Options panel shared by the Frontend and Backend tabs.
     *
     * @param string $tab
     * @param array  $view
     * @return void
     */
    public static function render_options_panel( string $tab, array $view ) : void {
        $fields = self::get_fields( $tab );
        $groups = [
            'collapsed' => __( 'Data to Show When Collapsed', 'site-perspectives' ),
            'expanded'  => __( 'Data to Show When Expanded', 'site-perspectives' ),
        ];
        ?>
        <details class="siteperspectives-options" id="siteperspectives-options">
            <summary>
                <span class="dashicons dashicons-admin-settings"></span>
                <?php esc_html_e( 'Display Options', 'site-perspectives' ); ?>
                <span class="siteperspectives-options-chevron dashicons dashicons-arrow-down-alt2"></span>
            </summary>
            <div class="siteperspectives-options-body">
                <?php foreach ( $groups as $group => $heading ) : ?>
                    <div class="siteperspectives-options-col" data-group="<?php echo esc_attr( $group ); ?>">
                        <h3><?php echo esc_html( $heading ); ?></h3>
                        <div class="siteperspectives-checkboxes">
                            <?php foreach ( $fields as $key => $label ) : ?>
                                <label>
                                    <input type="checkbox" name="<?php echo esc_attr( $group ); ?>[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, (array) $view[ $group ], true ) ); ?>>
                                    <?php echo esc_html( $label ); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if ( 'frontend' === $tab ) : ?>
                    <div class="siteperspectives-options-col siteperspectives-options-meta">
                        <h3><?php esc_html_e( 'Custom Meta', 'site-perspectives' ); ?></h3>
                        <div class="siteperspectives-meta-rows">
                            <?php foreach ( $view[ 'meta' ] as $row ) : ?>
                                <?php self::render_meta_row( $row ); ?>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="siteperspectives-button siteperspectives-button-secondary" id="siteperspectives-add-meta"><span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( 'Add Custom Meta', 'site-perspectives' ); ?></button>
                        <datalist id="siteperspectives-meta-keys"></datalist>
                        <template id="siteperspectives-meta-template"><?php self::render_meta_row( [ 'key' => '', 'label' => '', 'collapsed' => false, 'expanded' => true ] ); ?></template>
                    </div>
                <?php endif; ?>
            </div>
        </details>
        <?php
    } // End render_options_panel()


    /**
     * Render one custom meta row in the options panel.
     *
     * @param array $row
     * @return void
     */
    private static function render_meta_row( array $row ) : void {
        ?>
        <div class="siteperspectives-meta-row">
            <input type="text" class="siteperspectives-meta-key" list="siteperspectives-meta-keys" value="<?php echo esc_attr( $row[ 'key' ] ); ?>" placeholder="<?php esc_attr_e( 'meta_key', 'site-perspectives' ); ?>">
            <input type="text" class="siteperspectives-meta-label" value="<?php echo esc_attr( $row[ 'key' ] === $row[ 'label' ] ? '' : $row[ 'label' ] ); ?>" placeholder="<?php esc_attr_e( 'Label (optional)', 'site-perspectives' ); ?>">
            <label><input type="checkbox" class="siteperspectives-meta-collapsed" <?php checked( ! empty( $row[ 'collapsed' ] ) ); ?>> <?php esc_html_e( 'Collapsed', 'site-perspectives' ); ?></label>
            <label><input type="checkbox" class="siteperspectives-meta-expanded" <?php checked( ! empty( $row[ 'expanded' ] ) ); ?>> <?php esc_html_e( 'Expanded', 'site-perspectives' ); ?></label>
            <button type="button" class="siteperspectives-meta-remove" aria-label="<?php esc_attr_e( 'Remove', 'site-perspectives' ); ?>"><span class="dashicons dashicons-no-alt"></span></button>
        </div>
        <?php
    } // End render_meta_row()


    /**
     * Render the toolbar (search, orientation switch, expand/collapse) above a tree.
     *
     * @param string $orientation vertical|horizontal
     * @return void
     */
    public static function render_toolbar( string $orientation ) : void {
        ?>
        <div class="siteperspectives-bar">
            <div class="siteperspectives-search">
                <span class="dashicons dashicons-search"></span>
                <input type="search" id="siteperspectives-search" placeholder="<?php esc_attr_e( 'Filter by title or path...', 'site-perspectives' ); ?>" autocomplete="off">
            </div>
            <div class="siteperspectives-bar-actions">
                <button type="button" class="siteperspectives-button siteperspectives-button-secondary" id="siteperspectives-expand-all"><?php esc_html_e( 'Expand All', 'site-perspectives' ); ?></button>
                <button type="button" class="siteperspectives-button siteperspectives-button-secondary" id="siteperspectives-collapse-all"><?php esc_html_e( 'Collapse All', 'site-perspectives' ); ?></button>
                <div class="siteperspectives-orientation" role="group" aria-label="<?php esc_attr_e( 'Layout', 'site-perspectives' ); ?>">
                    <button type="button" data-orientation="vertical" class="<?php echo 'vertical' === $orientation ? 'is-active' : ''; ?>" title="<?php esc_attr_e( 'Vertical', 'site-perspectives' ); ?>"><span class="dashicons dashicons-menu-alt3"></span></button>
                    <button type="button" data-orientation="horizontal" class="<?php echo 'horizontal' === $orientation ? 'is-active' : ''; ?>" title="<?php esc_attr_e( 'Horizontal', 'site-perspectives' ); ?>"><span class="dashicons dashicons-columns"></span></button>
                </div>
            </div>
        </div>
        <?php
    } // End render_toolbar()


    /**
     * Get a user's saved orientation for a tab.
     *
     * @param string $tab
     * @return string
     */
    public static function get_orientation( string $tab ) : string {
        $value = get_user_meta( get_current_user_id(), 'siteperspectives_orientation_' . $tab, true );

        return 'horizontal' === $value ? 'horizontal' : 'vertical';
    } // End get_orientation()


    /**
     * Enqueue the shared tree assets and localize their data.
     *
     * @param string $tab
     * @param array  $extra Extra data merged into the localized object.
     * @return void
     */
    public static function enqueue_assets( string $tab, array $extra = [] ) : void {
        wp_enqueue_style( 'siteperspectives-orgchart', Bootstrap::url() . 'inc/css/orgchart.css', [ 'siteperspectives-theme' ], Bootstrap::script_version() );
        wp_enqueue_style( 'siteperspectives-tree', Bootstrap::url() . 'inc/css/tree.css', [ 'siteperspectives-theme', 'siteperspectives-orgchart' ], Bootstrap::script_version() );
        wp_enqueue_script( 'siteperspectives-tree', Bootstrap::url() . 'inc/js/tree.js', [ 'jquery', 'jquery-ui-sortable' ], Bootstrap::script_version(), true );

        $data = array_merge( [
            'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
            'nonce'       => wp_create_nonce( 'siteperspectives_tree_nonce' ),
            'tab'         => $tab,
            'canEdit'     => Access::can_edit(),
            'icons'       => Helpers::get_dashicons(),
            'i18n'        => [
                'edit'        => __( 'Edit', 'site-perspectives' ),
                'save'        => __( 'Save', 'site-perspectives' ),
                'saving'      => __( 'Saving', 'site-perspectives' ),
                'saved'       => __( 'Saved.', 'site-perspectives' ),
                'error'       => __( 'Something went wrong. Please try again.', 'site-perspectives' ),
                'cancel'      => __( 'Cancel', 'site-perspectives' ),
                'unsaved'     => __( 'You have unsaved changes.', 'site-perspectives' ),
                'confirmLeave' => __( 'You have unsaved changes. Discard them?', 'site-perspectives' ),
                'searchIcons' => __( 'Search icons...', 'site-perspectives' ),
                'noResults'   => __( 'Nothing found.', 'site-perspectives' ),
                'loadMore'    => __( 'Load more', 'site-perspectives' ),
                'added'       => __( 'Added to the tree.', 'site-perspectives' ),
                'adding'      => __( 'Adding...', 'site-perspectives' ),
                'titleRequired' => __( 'A title is required.', 'site-perspectives' ),
                'urlRequired'   => __( 'A title and URL are required.', 'site-perspectives' ),
                'parentMain'    => __( 'Main tree', 'site-perspectives' ),
                'parentUtility' => __( 'Utility Pages', 'site-perspectives' ),
                'untitled'      => __( '(untitled)', 'site-perspectives' ),
                'resetIcon'   => __( 'Use default', 'site-perspectives' ),
                'resetConfirm' => __( 'Reset this layout to match the live admin menu? Your custom order, names and links will be removed.', 'site-perspectives' ),
            ],
        ], $extra );

        wp_localize_script( 'siteperspectives-tree', 'sitePerspectivesTree', $data );
    } // End enqueue_assets()

} // End class Tree

Tree::instance();
