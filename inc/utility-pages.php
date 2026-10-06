<?php
/**
 * UTILITY PAGES
 *
 * Lets editors mark pages as utility pages from the Pages list (Quick Edit and Bulk Actions),
 * adds Utility / Non-Utility views with counts next to the core views, and remembers the
 * last view each user was on. Enabled from the Settings tab.
 */

namespace PluginRx\SitePerspectives;

if ( ! defined( 'ABSPATH' ) ) exit;


class UtilityPages {

    /**
     * Post meta key that flags a utility page.
     */
    public const META_KEY = '_siteperspectives_utility';


    /**
     * User meta key that stores the last view.
     */
    public const VIEW_META_KEY = 'siteperspectives_utility_view';


    /**
     * @var UtilityPages|null Singleton instance
     */
    private static ?UtilityPages $instance = null;


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
        add_action( 'admin_init', [ $this, 'register_hooks' ] );
    } // End __construct()


    /**
     * Is the feature turned on in Settings?
     *
     * @return bool
     */
    public static function enabled() : bool {
        return (bool) get_option( 'siteperspectives_utility_flags', 0 );
    } // End enabled()


    /**
     * Post types that get the feature.
     *
     * @return array
     */
    public static function post_types() : array {
        return array_values( array_filter( (array) apply_filters( 'siteperspectives_utility_post_types', [ 'page' ] ), 'post_type_exists' ) );
    } // End post_types()


    /**
     * Register the list screen hooks for each supported post type.
     *
     * @return void
     */
    public function register_hooks() : void {
        if ( ! self::enabled() ) {
            return;
        }

        foreach ( self::post_types() as $post_type ) {
            add_filter( 'views_edit-' . $post_type, [ $this, 'filter_views' ] );
            add_filter( 'bulk_actions-edit-' . $post_type, [ $this, 'add_bulk_actions' ] );
            add_filter( 'handle_bulk_actions-edit-' . $post_type, [ $this, 'handle_bulk_actions' ], 10, 3 );
            add_filter( 'manage_' . $post_type . '_posts_columns', [ $this, 'add_column' ] );
            add_action( 'manage_' . $post_type . '_posts_custom_column', [ $this, 'render_column' ], 10, 2 );
            add_action( 'save_post_' . $post_type, [ $this, 'save_quick_edit' ] );
        }

        add_action( 'load-edit.php', [ $this, 'maybe_restore_view' ] );
        add_action( 'pre_get_posts', [ $this, 'filter_query' ] );
        add_action( 'quick_edit_custom_box', [ $this, 'render_quick_edit' ], 10, 2 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
        add_filter( 'admin_body_class', [ $this, 'add_body_class' ] );
        add_action( 'admin_notices', [ $this, 'render_notice' ] );
    } // End register_hooks()


    /**
     * Get the post type on the current list screen, or an empty string when it is not supported.
     *
     * @return string
     */
    private static function screen_post_type() : string {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

        if ( ! $screen || 'edit' !== $screen->base || ! in_array( $screen->post_type, self::post_types(), true ) ) {
            return '';
        }

        return $screen->post_type;
    } // End screen_post_type()


    /**
     * Get the list screen URL for a post type with extra query args.
     *
     * @param string $post_type
     * @param array  $args
     * @return string
     */
    private static function list_url( string $post_type, array $args = [] ) : string {
        return add_query_arg( array_merge( [ 'post_type' => $post_type ], $args ), admin_url( 'edit.php' ) );
    } // End list_url()


    /**
     * Work out which view the current request is showing, or an empty string when there is no
     * view filter. Reads query args only to choose what to display.
     *
     * @return string all|utility|non_utility|mine|status:{slug}|''
     */
    private static function request_view() : string {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        if ( isset( $_GET[ 'siteperspectives_utility' ] ) ) {
            return '1' === sanitize_key( wp_unslash( $_GET[ 'siteperspectives_utility' ] ) ) ? 'utility' : 'non_utility';
        }

        if ( isset( $_GET[ 'post_status' ] ) ) {
            $status = sanitize_key( wp_unslash( $_GET[ 'post_status' ] ) );

            return 'all' === $status ? 'all' : ( in_array( $status, get_post_stati(), true ) ? 'status:' . $status : '' );
        }

        if ( isset( $_GET[ 'author' ] ) && absint( $_GET[ 'author' ] ) === get_current_user_id() ) {
            return 'mine';
        }

        return isset( $_GET[ 'all_posts' ] ) ? 'all' : '';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
    } // End request_view()


    /**
     * Convert a saved view into list screen query args.
     *
     * @param string $view
     * @return array|null Null when the view is not valid.
     */
    private static function view_args( string $view ) : ?array {
        if ( 'all' === $view ) {
            return [ 'all_posts' => 1 ];
        }

        if ( 'utility' === $view ) {
            return [ 'siteperspectives_utility' => 1 ];
        }

        if ( 'non_utility' === $view ) {
            return [ 'siteperspectives_utility' => 0 ];
        }

        if ( 'mine' === $view ) {
            return [ 'author' => get_current_user_id() ];
        }

        if ( 0 === strpos( $view, 'status:' ) ) {
            $status = substr( $view, 7 );

            return in_array( $status, get_post_stati(), true ) ? [ 'post_status' => $status ] : null;
        }

        return null;
    } // End view_args()


    /**
     * Remember the view being shown, or send the user to their last view when they open the
     * list with no filters.
     *
     * @return void
     */
    public function maybe_restore_view() : void {
        $post_type = self::screen_post_type();

        if ( '' === $post_type ) {
            return;
        }

        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $keys = array_keys( $_GET );
        $is_plain = [ 'post_type' ] === $keys || [] === $keys;
        $has_search = isset( $_GET[ 's' ] ) && '' !== $_GET[ 's' ];
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        $current = self::request_view();

        if ( $is_plain ) {
            $saved = (string) get_user_meta( get_current_user_id(), self::VIEW_META_KEY, true );
            $args = '' !== $saved && 'all' !== $saved ? self::view_args( $saved ) : null;

            if ( $args ) {
                wp_safe_redirect( self::list_url( $post_type, $args ) );
                exit;
            }

            return;
        }

        if ( '' !== $current && ! $has_search && get_user_meta( get_current_user_id(), self::VIEW_META_KEY, true ) !== $current ) {
            update_user_meta( get_current_user_id(), self::VIEW_META_KEY, $current );
        }
    } // End maybe_restore_view()


    /**
     * Count the posts of a type that are shown in the All view.
     *
     * @param string $post_type
     * @return array [ total, utility ]
     */
    private static function counts( string $post_type ) : array {
        $statuses = get_post_stati( [ 'show_in_admin_all_list' => true ] );
        $total = 0;

        foreach ( wp_count_posts( $post_type ) as $status => $count ) {
            if ( in_array( $status, $statuses, true ) ) {
                $total += (int) $count;
            }
        }

        $query = new \WP_Query( [
            'post_type'              => $post_type,
            'post_status'            => array_values( $statuses ),
            'meta_key'               => self::META_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- one small count query on the admin list only.
            'meta_value'             => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- one small count query on the admin list only.
            'posts_per_page'         => 1,
            'fields'                 => 'ids',
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ] );

        return [ $total, (int) $query->found_posts ];
    } // End counts()


    /**
     * Build one view link.
     *
     * @param string $label
     * @param int    $count
     * @param string $url
     * @param bool   $current
     * @return string
     */
    private static function view_link( string $label, int $count, string $url, bool $current ) : string {
        return '<a href="' . esc_url( $url ) . '"' . ( $current ? ' class="current" aria-current="page"' : '' ) . '>' . esc_html( $label ) . ' <span class="count">(' . esc_html( number_format_i18n( $count ) ) . ')</span></a>';
    } // End view_link()


    /**
     * Add the Utility and Non-Utility views after All, and make All a real link so it can be chosen
     * over a remembered view.
     *
     * @param array $views
     * @return array
     */
    public function filter_views( array $views ) : array {
        $post_type = self::screen_post_type();

        if ( '' === $post_type ) {
            return $views;
        }

        list( $total, $utility ) = self::counts( $post_type );
        $current = self::request_view();
        $is_all = 'all' === $current || ( '' === $current && ! isset( $_GET[ 's' ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $ordered = [
            'all'         => self::view_link( __( 'All', 'site-perspectives' ), $total, self::list_url( $post_type, [ 'all_posts' => 1 ] ), $is_all ),
            'utility'     => self::view_link( __( 'Utility', 'site-perspectives' ), $utility, self::list_url( $post_type, [ 'siteperspectives_utility' => 1 ] ), 'utility' === $current ),
            'non_utility' => self::view_link( __( 'Non-Utility', 'site-perspectives' ), max( 0, $total - $utility ), self::list_url( $post_type, [ 'siteperspectives_utility' => 0 ] ), 'non_utility' === $current ),
        ];

        unset( $views[ 'all' ] );

        return $ordered + $views;
    } // End filter_views()


    /**
     * Limit the main list query to utility or non-utility posts.
     *
     * @param \WP_Query $query
     * @return void
     */
    public function filter_query( \WP_Query $query ) : void {
        global $pagenow;

        if ( ! is_admin() || 'edit.php' !== $pagenow || ! $query->is_main_query() || ! isset( $_GET[ 'siteperspectives_utility' ] ) || ! in_array( $query->get( 'post_type' ), self::post_types(), true ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return;
        }

        $meta_query = (array) $query->get( 'meta_query' );
        $is_utility = 'utility' === self::request_view();

        $meta_query[] = $is_utility
            ? [ 'key' => self::META_KEY, 'value' => '1' ]
            : [ 'key' => self::META_KEY, 'compare' => 'NOT EXISTS' ];

        $query->set( 'meta_query', $meta_query );
    } // End filter_query()


    /**
     * Add the bulk actions.
     *
     * @param array $actions
     * @return array
     */
    public function add_bulk_actions( array $actions ) : array {
        $actions[ 'siteperspectives_mark_utility' ] = __( 'Mark as Utility', 'site-perspectives' );
        $actions[ 'siteperspectives_unmark_utility' ] = __( 'Remove Utility Mark', 'site-perspectives' );

        return $actions;
    } // End add_bulk_actions()


    /**
     * Handle the bulk actions. WordPress has already verified the bulk nonce.
     *
     * @param string $redirect_url
     * @param string $action
     * @param array  $post_ids
     * @return string
     */
    public function handle_bulk_actions( string $redirect_url, string $action, array $post_ids ) : string {
        if ( ! in_array( $action, [ 'siteperspectives_mark_utility', 'siteperspectives_unmark_utility' ], true ) ) {
            return $redirect_url;
        }

        $mark = 'siteperspectives_mark_utility' === $action;
        $changed = 0;

        foreach ( array_map( 'absint', $post_ids ) as $post_id ) {
            if ( ! current_user_can( 'edit_post', $post_id ) ) {
                continue;
            }

            self::set_flag( $post_id, $mark );
            $changed++;
        }

        $redirect_url = remove_query_arg( [ 'siteperspectives_marked', 'siteperspectives_unmarked' ], $redirect_url );

        return add_query_arg( $mark ? 'siteperspectives_marked' : 'siteperspectives_unmarked', $changed, $redirect_url );
    } // End handle_bulk_actions()


    /**
     * Set or clear the utility flag.
     *
     * @param int  $post_id
     * @param bool $mark
     * @return void
     */
    private static function set_flag( int $post_id, bool $mark ) : void {
        if ( $mark ) {
            update_post_meta( $post_id, self::META_KEY, '1' );
        } else {
            delete_post_meta( $post_id, self::META_KEY );
        }
    } // End set_flag()


    /**
     * Show the result of a bulk action.
     *
     * @return void
     */
    public function render_notice() : void {
        if ( '' === self::screen_post_type() ) {
            return;
        }

        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $marked = isset( $_GET[ 'siteperspectives_marked' ] ) ? absint( $_GET[ 'siteperspectives_marked' ] ) : 0;
        $unmarked = isset( $_GET[ 'siteperspectives_unmarked' ] ) ? absint( $_GET[ 'siteperspectives_unmarked' ] ) : 0;
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        if ( $marked ) {
            /* translators: %s: number of pages */
            $message = _n( '%s page marked as utility.', '%s pages marked as utility.', $marked, 'site-perspectives' );
            $count = $marked;
        } elseif ( $unmarked ) {
            /* translators: %s: number of pages */
            $message = _n( 'Utility mark removed from %s page.', 'Utility mark removed from %s pages.', $unmarked, 'site-perspectives' );
            $count = $unmarked;
        } else {
            return;
        }

        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sprintf( $message, number_format_i18n( $count ) ) ) . '</p></div>';
    } // End render_notice()


    /**
     * Add the Utility column.
     *
     * @param array $columns
     * @return array
     */
    public function add_column( array $columns ) : array {
        $columns[ 'siteperspectives_utility' ] = __( 'Utility', 'site-perspectives' );

        return $columns;
    } // End add_column()


    /**
     * Render the Utility column, including the flag Quick Edit reads.
     *
     * @param string $column
     * @param int    $post_id
     * @return void
     */
    public function render_column( string $column, int $post_id ) : void {
        if ( 'siteperspectives_utility' !== $column ) {
            return;
        }

        $is_utility = '1' === get_post_meta( $post_id, self::META_KEY, true );
        ?>
        <span class="siteperspectives-utility-flag" data-utility="<?php echo esc_attr( $is_utility ? '1' : '0' ); ?>">
            <?php if ( $is_utility ) : ?>
                <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
                <span class="screen-reader-text"><?php esc_html_e( 'Utility page', 'site-perspectives' ); ?></span>
            <?php else : ?>
                <span aria-hidden="true">&mdash;</span>
                <span class="screen-reader-text"><?php esc_html_e( 'Not a utility page', 'site-perspectives' ); ?></span>
            <?php endif; ?>
        </span>
        <?php
    } // End render_column()


    /**
     * Render the Quick Edit checkbox.
     *
     * @param string $column
     * @param string $post_type
     * @return void
     */
    public function render_quick_edit( string $column, string $post_type ) : void {
        if ( 'siteperspectives_utility' !== $column || ! in_array( $post_type, self::post_types(), true ) ) {
            return;
        }
        ?>
        <fieldset class="inline-edit-col-right">
            <div class="inline-edit-col">
                <?php wp_nonce_field( 'siteperspectives_utility_quick', 'siteperspectives_utility_nonce', false ); ?>
                <input type="hidden" name="siteperspectives_utility_present" value="1">
                <label class="alignleft">
                    <input type="checkbox" name="siteperspectives_utility" value="1">
                    <span class="checkbox-title"><?php esc_html_e( 'Utility page', 'site-perspectives' ); ?></span>
                </label>
            </div>
        </fieldset>
        <?php
    } // End render_quick_edit()


    /**
     * Save the Quick Edit checkbox.
     *
     * @param int $post_id
     * @return void
     */
    public function save_quick_edit( int $post_id ) : void {
        if ( ! isset( $_POST[ 'siteperspectives_utility_present' ], $_POST[ 'siteperspectives_utility_nonce' ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ 'siteperspectives_utility_nonce' ] ) ), 'siteperspectives_utility_quick' ) ) {
            return;
        }

        if ( wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        self::set_flag( $post_id, ! empty( $_POST[ 'siteperspectives_utility' ] ) );
    } // End save_quick_edit()


    /**
     * Enqueue the Quick Edit script and column style on the list screen.
     *
     * @return void
     */
    public function enqueue() : void {
        if ( '' === self::screen_post_type() ) {
            return;
        }

        wp_enqueue_style( 'siteperspectives-utility-pages', Bootstrap::url() . 'inc/css/utility-pages.css', [], Bootstrap::script_version() );
        wp_enqueue_script( 'siteperspectives-utility-pages', Bootstrap::url() . 'inc/js/utility-pages.js', [ 'jquery', 'inline-edit-post' ], Bootstrap::script_version(), true );
    } // End enqueue()


    /**
     * Add a body class on the Utility and Non-Utility views, which hides the Utility column.
     *
     * @param string $classes
     * @return string
     */
    public function add_body_class( string $classes ) : string {
        if ( '' !== self::screen_post_type() && in_array( self::request_view(), [ 'utility', 'non_utility' ], true ) ) {
            $classes .= ' siteperspectives-utility-view ';
        }

        return $classes;
    } // End add_body_class()

} // End class UtilityPages

UtilityPages::instance();
