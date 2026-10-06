<?php
/**
 * AUTO UPDATE
 *
 * Optionally adds newly published content to the Frontend / shortcode tree, and removes
 * items from it when their post is trashed or deleted. Controlled per post type in Settings.
 */

namespace PluginRx\SitePerspectives;

if ( ! defined( 'ABSPATH' ) ) exit;


class AutoUpdate {

    /**
     * Post meta key set once a post has been added automatically, so it is never added twice.
     */
    public const META_KEY = '_siteperspectives_auto_added';


    /**
     * @var AutoUpdate|null Singleton instance
     */
    private static ?AutoUpdate $instance = null;


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
        add_action( 'wp_after_insert_post', [ $this, 'maybe_add' ], 20, 4 );
        add_action( 'wp_trash_post', [ $this, 'remove' ] );
        add_action( 'before_delete_post', [ $this, 'remove' ] );
    } // End __construct()


    /**
     * Get the post types with automatic updates turned on.
     *
     * @return array
     */
    private static function post_types() : array {
        $saved = (array) get_option( 'siteperspectives_auto_post_types', [] );

        if ( empty( $saved ) ) {
            return [];
        }

        return array_values( array_intersect( $saved, array_keys( Helpers::tree_post_types() ) ) );
    } // End post_types()


    /**
     * Add a post to the tree the first time it is published.
     *
     * @param int           $post_id
     * @param \WP_Post      $post
     * @param bool          $update
     * @param \WP_Post|null $post_before
     * @return void
     */
    public function maybe_add( $post_id, $post, $update, $post_before = null ) : void {
        if ( 'publish' !== $post->post_status || ( $post_before && 'publish' === $post_before->post_status ) ) {
            return;
        }

        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || '' !== $post->post_password ) {
            return;
        }

        if ( ! in_array( $post->post_type, self::post_types(), true ) || get_post_meta( $post_id, self::META_KEY, true ) ) {
            return;
        }

        $tree = Tree::get_tree();

        if ( self::count_nodes( $tree[ 'main' ] ) + self::count_nodes( $tree[ 'utility' ] ) >= Tree::MAX_NODES ) {
            return;
        }

        $node = Tree::blank_node( 'post' );
        $node[ 'post_id' ] = (int) $post_id;
        $node[ 'hide_public' ] = (bool) get_option( 'siteperspectives_auto_hide_new', 0 );

        if ( '1' === get_post_meta( $post_id, UtilityPages::META_KEY, true ) ) {
            $tree[ 'utility' ][] = $node;
        } elseif ( ! $post->post_parent || ! self::add_under( $tree[ 'main' ], (int) $post->post_parent, $node ) ) {
            $tree[ 'main' ][] = $node;
        }

        Tree::save_tree( $tree );
        update_post_meta( $post_id, self::META_KEY, 1 );
    } // End maybe_add()


    /**
     * Remove a post from the tree when it is trashed or deleted. Its children move up a level.
     *
     * @param int $post_id
     * @return void
     */
    public function remove( $post_id ) : void {
        $post = get_post( $post_id );

        if ( ! $post || ! in_array( $post->post_type, self::post_types(), true ) ) {
            return;
        }

        $tree = Tree::get_tree();
        $ids = Tree::collect_post_ids( $tree[ 'utility' ], Tree::collect_post_ids( $tree[ 'main' ] ) );

        if ( ! isset( $ids[ (int) $post_id ] ) ) {
            return;
        }

        Tree::save_tree( [
            'main'    => self::remove_post( $tree[ 'main' ], (int) $post_id ),
            'utility' => self::remove_post( $tree[ 'utility' ], (int) $post_id ),
        ] );

        delete_post_meta( $post_id, self::META_KEY );
    } // End remove()


    /**
     * Add a node as the last child of the node for a post.
     *
     * @param array $nodes
     * @param int   $parent_id
     * @param array $node
     * @return bool Whether the parent was found.
     */
    private static function add_under( array &$nodes, int $parent_id, array $node ) : bool {
        foreach ( $nodes as &$item ) {
            if ( 'post' === $item[ 'type' ] && (int) $item[ 'post_id' ] === $parent_id ) {
                $item[ 'children' ][] = $node;

                return true;
            }

            if ( ! empty( $item[ 'children' ] ) && self::add_under( $item[ 'children' ], $parent_id, $node ) ) {
                return true;
            }
        }

        return false;
    } // End add_under()


    /**
     * Remove every node for a post, keeping its children in its place.
     *
     * @param array $nodes
     * @param int   $post_id
     * @return array
     */
    private static function remove_post( array $nodes, int $post_id ) : array {
        $clean = [];

        foreach ( $nodes as $node ) {
            $children = ! empty( $node[ 'children' ] ) ? self::remove_post( (array) $node[ 'children' ], $post_id ) : [];

            if ( 'post' === $node[ 'type' ] && (int) $node[ 'post_id' ] === $post_id ) {
                $clean = array_merge( $clean, $children );
                continue;
            }

            $node[ 'children' ] = $children;
            $clean[] = $node;
        }

        return $clean;
    } // End remove_post()


    /**
     * Count the nodes in a list, including all descendants.
     *
     * @param array $nodes
     * @return int
     */
    private static function count_nodes( array $nodes ) : int {
        $count = 0;

        foreach ( $nodes as $node ) {
            $count += 1 + ( ! empty( $node[ 'children' ] ) ? self::count_nodes( (array) $node[ 'children' ] ) : 0 );
        }

        return $count;
    } // End count_nodes()

} // End class AutoUpdate

AutoUpdate::instance();
