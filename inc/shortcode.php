<?php
/**
 * SHORTCODE TAB
 *
 * The [site_perspectives] shortcode shows the tree defined on the Frontend tab to visitors,
 * styled by the settings on this tab, with a live preview.
 */

namespace PluginRx\SitePerspectives;

if ( ! defined( 'ABSPATH' ) ) exit;


class Shortcode {

    public const OPTION = 'siteperspectives_shortcode';
    public const TAG = 'site_perspectives';


    /**
     * @var Shortcode|null Singleton instance
     */
    private static ?Shortcode $instance = null;


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
        add_shortcode( self::TAG, [ $this, 'render' ] );
        add_action( 'wp_ajax_siteperspectives_save_shortcode', [ $this, 'ajax_save' ] );
        add_action( 'wp_ajax_siteperspectives_shortcode_preview', [ $this, 'ajax_preview' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin' ] );
        add_action( 'siteperspectives_subheader_left', [ $this, 'render_subheader_buttons' ] );
        add_action( 'edited_term', [ Tree::class, 'bump_version' ] );
        add_action( 'delete_term', [ Tree::class, 'bump_version' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'register_public_assets' ] );
    } // End __construct()


    /**
     * Get the layouts available to the shortcode.
     *
     * @return array
     */
    public static function layouts() : array {
        return [
            'tree'       => __( 'Vertical tree', 'site-perspectives' ),
            'horizontal' => __( 'Horizontal tree', 'site-perspectives' ),
            'cards'      => __( 'Card grid', 'site-perspectives' ),
        ];
    } // End layouts()


    /**
     * Get the data fields that may be shown to visitors.
     *
     * @return array
     */
    public static function public_fields() : array {
        $all = Tree::get_fields( 'frontend' );

        return array_intersect_key( $all, array_flip( [ 'path', 'post_type', 'categories', 'tags', 'terms', 'author', 'published', 'modified' ] ) );
    } // End public_fields()


    /**
     * Default settings.
     *
     * @return array
     */
    public static function defaults() : array {
        return [
            'theme'           => 'classic',
            'colors'          => [],
            'layout'          => 'tree',
            'density'         => 'comfortable',
            'radius'          => 8,
            'show_icons'      => true,
            'new_tab'         => false,
            'show_utility'    => true,
            'utility_heading' => __( 'Utility Pages', 'site-perspectives' ),
            'fields'          => [],
            'meta'            => [],
        ];
    } // End defaults()


    /**
     * Get the saved settings merged with defaults.
     *
     * @return array
     */
    public static function get_settings() : array {
        $saved = get_option( self::OPTION, [] );

        return self::sanitize( array_merge( self::defaults(), is_array( $saved ) ? $saved : [] ) );
    } // End get_settings()


    /**
     * Sanitize settings.
     *
     * @param mixed $input
     * @return array
     */
    public static function sanitize( $input ) : array {
        $input = is_array( $input ) ? $input : [];
        $defaults = self::defaults();
        $themes = Colors::themes();
        $theme = isset( $input[ 'theme' ] ) ? sanitize_key( $input[ 'theme' ] ) : 'classic';
        $colors = [];

        foreach ( Colors::defaults() as $key => $data ) {
            $colors[ $key ] = Helpers::hex( $input[ 'colors' ][ $key ] ?? '', $data[ 'color' ] );
        }

        $meta = [];

        foreach ( array_slice( (array) ( $input[ 'meta' ] ?? [] ), 0, 20 ) as $row ) {
            $key = isset( $row[ 'key' ] ) ? mb_substr( sanitize_text_field( $row[ 'key' ] ), 0, 191 ) : '';

            if ( '' === $key ) {
                continue;
            }

            $label = isset( $row[ 'label' ] ) ? mb_substr( sanitize_text_field( $row[ 'label' ] ), 0, 60 ) : '';
            $meta[] = [ 'key' => $key, 'label' => $label !== $key ? $label : '' ];
        }

        $layout = isset( $input[ 'layout' ] ) ? sanitize_key( $input[ 'layout' ] ) : 'tree';
        $density = isset( $input[ 'density' ] ) ? sanitize_key( $input[ 'density' ] ) : 'comfortable';

        return [
            'theme'           => ( isset( $themes[ $theme ] ) || 'custom' === $theme ) ? $theme : 'classic',
            'colors'          => $colors,
            'layout'          => isset( self::layouts()[ $layout ] ) ? $layout : 'tree',
            'density'         => in_array( $density, [ 'comfortable', 'compact' ], true ) ? $density : 'comfortable',
            'radius'          => min( 32, max( 0, absint( $input[ 'radius' ] ?? $defaults[ 'radius' ] ) ) ),
            'show_icons'      => ! empty( $input[ 'show_icons' ] ),
            'new_tab'         => ! empty( $input[ 'new_tab' ] ),
            'show_utility'    => ! empty( $input[ 'show_utility' ] ),
            'utility_heading' => mb_substr( sanitize_text_field( $input[ 'utility_heading' ] ?? '' ), 0, 80 ),
            'fields'          => array_values( array_intersect( array_map( 'sanitize_key', (array) ( $input[ 'fields' ] ?? [] ) ), array_keys( self::public_fields() ) ) ),
            'meta'            => $meta,
        ];
    } // End sanitize()


    /**
     * Build the inline CSS variable declarations for a wrapper.
     *
     * @param array $settings
     * @return string
     */
    public static function style_vars( array $settings ) : string {
        $colors = Colors::resolve( $settings[ 'theme' ], $settings[ 'colors' ] );
        $vars = [];

        foreach ( $colors as $key => $value ) {
            $vars[] = '--siteperspectives-' . str_replace( '_', '-', $key ) . ':' . $value;
        }

        $vars[] = '--siteperspectives-radius:' . (int) $settings[ 'radius' ] . 'px';
        $vars[] = '--tl-trunk-text:' . Helpers::text_color( $colors[ 'title' ] );
        $vars[] = '--tl-accent-text:' . Helpers::text_color( $colors[ 'accent' ] );

        return implode( ';', $vars ) . ';';
    } // End style_vars()


    /**
     * Register the visitor stylesheet.
     *
     * @return void
     */
    public function register_public_assets() : void {
        wp_register_style( 'siteperspectives-orgchart', Bootstrap::url() . 'inc/css/orgchart.css', [], Bootstrap::script_version() );
        wp_register_style( 'siteperspectives-shortcode', Bootstrap::url() . 'inc/css/shortcode.css', [], Bootstrap::script_version() );
    } // End register_public_assets()


    /**
     * Build the shortcode markup.
     *
     * @param array $settings
     * @param bool  $preview  Render every available field so the preview can toggle them live.
     * @return string
     */
    public static function build_html( array $settings, bool $preview = false ) : string {
        $tree = Tree::get_tree();
        $ids = Tree::collect_post_ids( $tree[ 'main' ] );
        $ids = Tree::collect_post_ids( $tree[ 'utility' ], $ids );
        $meta_view = [ 'collapsed' => [], 'expanded' => [], 'meta' => $settings[ 'meta' ] ];
        $fields = $preview ? array_keys( self::public_fields() ) : $settings[ 'fields' ];

        foreach ( $settings[ 'meta' ] as $row ) {
            $fields[] = 'meta:' . $row[ 'key' ];
        }

        $ctx = Tree::context( [
            'public'  => true,
            'view'    => $meta_view,
            'posts'   => Tree::load_posts( array_keys( $ids ), true ),
            'icons'   => $preview ? true : $settings[ 'show_icons' ],
            'new_tab' => $settings[ 'new_tab' ],
            'fields'  => $fields,
        ] );

        $show_utility = $settings[ 'show_utility' ] || $preview;

        if ( 'cards' === $settings[ 'layout' ] || $preview ) {
            $flat = Tree::flatten_public( $tree[ 'main' ], $ctx[ 'posts' ] );

            if ( $show_utility ) {
                $flat = array_merge( $flat, Tree::flatten_public( $tree[ 'utility' ], $ctx[ 'posts' ], true ) );
            }

            usort( $flat, function ( $a, $b ) {
                return strnatcasecmp( $a[ 'sort' ], $b[ 'sort' ] );
            } );

            $cards = Tree::render_items( array_column( $flat, 'node' ), $ctx );
        }

        $main = Tree::render_items( $tree[ 'main' ], $ctx );
        $utility = $show_utility ? Tree::render_items( $tree[ 'utility' ], $ctx ) : '';

        ob_start();

        if ( 'cards' === $settings[ 'layout' ] && ! $preview ) {
            ?>
            <ul class="siteperspectives-list siteperspectives-cards-list"><?php echo $cards; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside Tree::render_items(). ?></ul>
            <?php

            return (string) ob_get_clean();
        }
        ?>
        <?php if ( $preview ) : ?>
            <ul class="siteperspectives-list siteperspectives-cards-list"><?php echo $cards; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside Tree::render_items(). ?></ul>
        <?php endif; ?>
        <?php if ( $preview || 'horizontal' === $settings[ 'layout' ] ) : ?>
            <?php echo Tree::render_home( ! $preview, false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside Tree::render_home(). ?>
        <?php endif; ?>
        <ul class="siteperspectives-list siteperspectives-root"><?php echo $main; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside Tree::render_items(). ?></ul>
        <?php if ( '' !== $utility ) : ?>
            <div class="siteperspectives-sc-utility">
                <?php if ( '' !== $settings[ 'utility_heading' ] || $preview ) : ?>
                    <h2 class="siteperspectives-sc-heading"><?php echo esc_html( $settings[ 'utility_heading' ] ); ?></h2>
                <?php endif; ?>
                <ul class="siteperspectives-list siteperspectives-root"><?php echo $utility; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside Tree::render_items(). ?></ul>
            </div>
        <?php endif; ?>
        <?php

        return (string) ob_get_clean();
    } // End build_html()


    /**
     * Render the [site_perspectives] shortcode.
     *
     * @param array|string $atts Optional "layout" attribute to override the saved layout.
     * @return string
     */
    public function render( $atts = [] ) : string {
        $settings = self::get_settings();
        $atts = shortcode_atts( [ 'layout' => '' ], $atts, self::TAG );
        $layout = sanitize_key( $atts[ 'layout' ] );

        if ( isset( self::layouts()[ $layout ] ) ) {
            $settings[ 'layout' ] = $layout;
        }

        $key = 'siteperspectives_sc_' . md5( Tree::version() . wp_json_encode( $settings ) . self::style_vars( $settings ) . get_locale() );
        $inner = get_transient( $key );

        if ( ! is_string( $inner ) ) {
            $inner = self::build_html( $settings );
            set_transient( $key, $inner, 12 * HOUR_IN_SECONDS );
        }

        wp_enqueue_style( 'siteperspectives-shortcode' );

        if ( 'horizontal' === $settings[ 'layout' ] ) {
            wp_enqueue_style( 'siteperspectives-orgchart' );
        }

        if ( $settings[ 'show_icons' ] || 'horizontal' === $settings[ 'layout' ] ) {
            wp_enqueue_style( 'dashicons' );
        }

        DynamicCss::enqueue( 'siteperspectives-shortcode-dynamic', 'shortcode', [ 'siteperspectives-shortcode' ] );

        $classes = 'siteperspectives-sc siteperspectives-layout-' . $settings[ 'layout' ] . ' siteperspectives-density-' . $settings[ 'density' ];

        if ( 'frontend' === $settings[ 'theme' ] ) {
            $classes .= ' siteperspectives-use-node-colors';
        }

        return '<nav class="' . esc_attr( $classes ) . '" aria-label="' . esc_attr__( 'Site map', 'site-perspectives' ) . '">' . $inner . '</nav>';
    } // End render()


    /**
     * Enqueue admin assets on this tab.
     *
     * @return void
     */
    public function enqueue_admin() : void {
        if ( ! Helpers::is_plugin_screen() || Menu::MENU_SLUG . '-shortcode' !== Menu::get_current_page_slug() || ! Access::can_manage() ) {
            return;
        }

        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_style( 'siteperspectives-shortcode-admin', Bootstrap::url() . 'inc/css/shortcode-admin.css', [ 'siteperspectives-theme' ], Bootstrap::script_version() );
        wp_enqueue_script( 'siteperspectives-shortcode-admin', Bootstrap::url() . 'inc/js/shortcode.js', [ 'jquery', 'wp-color-picker' ], Bootstrap::script_version(), true );

        $themes = [];

        foreach ( Colors::themes() as $key => $theme ) {
            $themes[ $key ] = Colors::resolve( $key );
        }

        wp_localize_script( 'siteperspectives-shortcode-admin', 'sitePerspectivesShortcode', [
            'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
            'nonce'     => wp_create_nonce( 'siteperspectives_shortcode_nonce' ),
            'themes'    => $themes,
            'cssUrl'    => add_query_arg( 'ver', Bootstrap::script_version(), Bootstrap::url() . 'inc/css/shortcode.css' ),
            'previewUrl' => add_query_arg( 'ver', Bootstrap::script_version(), Bootstrap::url() . 'inc/css/shortcode-preview.css' ),
            'nodesUrl'  => DynamicCss::versioned_url( 'nodes' ),
            'orgUrl'    => add_query_arg( 'ver', Bootstrap::script_version(), Bootstrap::url() . 'inc/css/orgchart.css' ),
            'iconsUrl'  => includes_url( 'css/dashicons.min.css' ),
            'i18n'      => [
                'saving'  => __( 'Saving', 'site-perspectives' ),
                'saved'   => __( 'Saved.', 'site-perspectives' ),
                'error'   => __( 'Error saving. Please try again.', 'site-perspectives' ),
                'copied'  => __( 'Copied!', 'site-perspectives' ),
                'noRows'  => __( 'Nothing to preview yet. Add items on the Frontend tab.', 'site-perspectives' ),
            ],
        ] );
    } // End enqueue_admin()


    /**
     * Render the Save button in the left of the subheader.
     *
     * @param string $active_page
     * @return void
     */
    public function render_subheader_buttons( string $active_page ) : void {
        if ( Menu::MENU_SLUG . '-shortcode' !== $active_page || ! Access::can_manage() ) {
            return;
        }
        ?>
        <button type="button" class="siteperspectives-button" id="siteperspectives-save"><?php esc_html_e( 'Save', 'site-perspectives' ); ?></button>
        <span id="siteperspectives-save-reminder"><?php esc_html_e( 'Remember to click "Save" after making changes.', 'site-perspectives' ); ?></span>
        <?php
    } // End render_subheader_buttons()


    /**
     * AJAX: save the shortcode settings.
     *
     * @return void
     */
    public function ajax_save() : void {
        check_ajax_referer( 'siteperspectives_shortcode_nonce', 'nonce' );

        if ( ! Access::can_manage() ) {
            wp_send_json_error( __( 'Insufficient permissions.', 'site-perspectives' ), 403 );
        }

        $raw = isset( $_POST[ 'settings' ] ) ? wp_unslash( $_POST[ 'settings' ] ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- every value is sanitized in self::sanitize().

        if ( ! is_array( $raw ) ) {
            wp_send_json_error( __( 'Invalid data.', 'site-perspectives' ) );
        }

        update_option( self::OPTION, self::sanitize( $raw ), false );
        Tree::bump_version();

        wp_send_json_success();
    } // End ajax_save()


    /**
     * AJAX: rebuild the preview markup from unsaved settings (used when custom meta rows change).
     *
     * @return void
     */
    public function ajax_preview() : void {
        check_ajax_referer( 'siteperspectives_shortcode_nonce', 'nonce' );

        if ( ! Access::can_manage() ) {
            wp_send_json_error( __( 'Insufficient permissions.', 'site-perspectives' ), 403 );
        }

        $raw = isset( $_POST[ 'settings' ] ) ? wp_unslash( $_POST[ 'settings' ] ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- every value is sanitized in self::sanitize().

        if ( ! is_array( $raw ) ) {
            wp_send_json_error( __( 'Invalid data.', 'site-perspectives' ) );
        }

        wp_send_json_success( [ 'html' => self::build_html( self::sanitize( $raw ), true ) ] );
    } // End ajax_preview()


    /**
     * Render the Shortcode tab.
     *
     * @return void
     */
    public static function render_page() : void {
        if ( ! Access::can_manage() ) {
            wp_die( esc_html__( 'You do not have permission to view this page.', 'site-perspectives' ) );
        }

        $s = self::get_settings();
        $themes = Colors::themes();
        $preview = self::build_html( $s, true );
        $tree = Tree::get_tree();
        ?>
        <div class="wrap siteperspectives-wrap siteperspectives-content-wrap siteperspectives-shortcode-page" id="siteperspectives-app" data-tab="shortcode">
            <div class="siteperspectives-split">

                <div class="siteperspectives-split-form" id="siteperspectives-sc-form">

                    <div class="siteperspectives-box">
                        <div class="siteperspectives-box-header"><h2><?php esc_html_e( 'Usage', 'site-perspectives' ); ?></h2></div>
                        <div class="siteperspectives-box-body">
                            <p class="siteperspectives-section-desc"><?php esc_html_e( 'Paste this shortcode into any page, post or widget. It shows the tree from the Frontend tab, so changes there appear automatically. To use a different layout for one instance, add the layout attribute: tree, horizontal or cards.', 'site-perspectives' ); ?></p>
                            <div class="siteperspectives-copy-row">
                                <code id="siteperspectives-sc-code">[<?php echo esc_html( self::TAG ); ?>]</code>
                                <button type="button" class="siteperspectives-button siteperspectives-button-secondary" id="siteperspectives-copy"><?php esc_html_e( 'Copy', 'site-perspectives' ); ?></button>
                            </div>
                            <?php if ( empty( $tree[ 'main' ] ) && empty( $tree[ 'utility' ] ) ) : ?>
                                <p class="siteperspectives-section-desc"><?php esc_html_e( 'The tree is empty. Add items on the Frontend tab first.', 'site-perspectives' ); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="siteperspectives-box">
                        <div class="siteperspectives-box-header"><h2><?php esc_html_e( 'Theme and Colors', 'site-perspectives' ); ?></h2></div>
                        <div class="siteperspectives-box-body">
                            <div class="siteperspectives-field">
                                <label for="siteperspectives-theme"><?php esc_html_e( 'Theme', 'site-perspectives' ); ?></label>
                                <select id="siteperspectives-theme" name="theme">
                                    <?php foreach ( $themes as $key => $theme ) : ?>
                                        <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $s[ 'theme' ], $key ); ?>><?php echo esc_html( $theme[ 'label' ] ); ?></option>
                                    <?php endforeach; ?>
                                    <option value="custom" <?php selected( $s[ 'theme' ], 'custom' ); ?>><?php esc_html_e( 'Custom (use the color pickers below)', 'site-perspectives' ); ?></option>
                                </select>
                            </div>
                            <?php $resolved = Colors::resolve( $s[ 'theme' ], $s[ 'colors' ] ); ?>
                            <div class="siteperspectives-color-grid">
                                <?php foreach ( Colors::defaults() as $key => $data ) : ?>
                                    <div class="siteperspectives-field">
                                        <label for="siteperspectives-color-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $data[ 'label' ] ); ?></label>
                                        <input type="text" class="siteperspectives-color" id="siteperspectives-color-<?php echo esc_attr( $key ); ?>" data-key="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $resolved[ $key ] ); ?>" data-default-color="<?php echo esc_attr( $data[ 'color' ] ); ?>">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="siteperspectives-box">
                        <div class="siteperspectives-box-header"><h2><?php esc_html_e( 'Layout', 'site-perspectives' ); ?></h2></div>
                        <div class="siteperspectives-box-body">
                            <div class="siteperspectives-field">
                                <label for="siteperspectives-layout"><?php esc_html_e( 'Layout', 'site-perspectives' ); ?></label>
                                <select id="siteperspectives-layout" name="layout">
                                    <?php foreach ( self::layouts() as $key => $label ) : ?>
                                        <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $s[ 'layout' ], $key ); ?>><?php echo esc_html( $label ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="siteperspectives-field">
                                <label for="siteperspectives-density"><?php esc_html_e( 'Spacing', 'site-perspectives' ); ?></label>
                                <select id="siteperspectives-density" name="density">
                                    <option value="comfortable" <?php selected( $s[ 'density' ], 'comfortable' ); ?>><?php esc_html_e( 'Comfortable', 'site-perspectives' ); ?></option>
                                    <option value="compact" <?php selected( $s[ 'density' ], 'compact' ); ?>><?php esc_html_e( 'Compact', 'site-perspectives' ); ?></option>
                                </select>
                            </div>
                            <div class="siteperspectives-field">
                                <label for="siteperspectives-radius"><?php esc_html_e( 'Corner Radius', 'site-perspectives' ); ?> <output id="siteperspectives-radius-out"><?php echo esc_html( (string) $s[ 'radius' ] ); ?>px</output></label>
                                <input type="range" id="siteperspectives-radius" name="radius" min="0" max="32" value="<?php echo esc_attr( (string) $s[ 'radius' ] ); ?>">
                            </div>
                            <div class="siteperspectives-checkboxes siteperspectives-checkboxes-single">
                                <label><input type="checkbox" name="show_icons" <?php checked( $s[ 'show_icons' ] ); ?>> <?php esc_html_e( 'Show icons', 'site-perspectives' ); ?></label>
                                <label><input type="checkbox" name="new_tab" <?php checked( $s[ 'new_tab' ] ); ?>> <?php esc_html_e( 'Open links in a new tab', 'site-perspectives' ); ?></label>
                                <label><input type="checkbox" name="show_utility" <?php checked( $s[ 'show_utility' ] ); ?>> <?php esc_html_e( 'Include utility pages', 'site-perspectives' ); ?></label>
                            </div>
                            <div class="siteperspectives-field">
                                <label for="siteperspectives-utility-heading"><?php esc_html_e( 'Utility Pages Heading', 'site-perspectives' ); ?></label>
                                <input type="text" id="siteperspectives-utility-heading" name="utility_heading" value="<?php echo esc_attr( $s[ 'utility_heading' ] ); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="siteperspectives-box">
                        <div class="siteperspectives-box-header"><h2><?php esc_html_e( 'Data to Show', 'site-perspectives' ); ?></h2></div>
                        <div class="siteperspectives-box-body">
                            <div class="siteperspectives-checkboxes">
                                <?php foreach ( self::public_fields() as $key => $label ) : ?>
                                    <label><input type="checkbox" name="fields[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $s[ 'fields' ], true ) ); ?>> <?php echo esc_html( $label ); ?></label>
                                <?php endforeach; ?>
                            </div>
                            <div class="siteperspectives-field">
                                <label><?php esc_html_e( 'Custom Meta', 'site-perspectives' ); ?> <?php Helpers::tooltip( __( 'Show custom field values under each item. Protected fields (those starting with an underscore) are never shown to visitors.', 'site-perspectives' ) ); ?></label>
                                <div class="siteperspectives-meta-rows" id="siteperspectives-sc-meta">
                                    <?php foreach ( $s[ 'meta' ] as $row ) : ?>
                                        <div class="siteperspectives-meta-row">
                                            <input type="text" class="siteperspectives-meta-key" value="<?php echo esc_attr( $row[ 'key' ] ); ?>" placeholder="<?php esc_attr_e( 'meta_key', 'site-perspectives' ); ?>">
                                            <input type="text" class="siteperspectives-meta-label" value="<?php echo esc_attr( $row[ 'label' ] ); ?>" placeholder="<?php esc_attr_e( 'Label (blank for none)', 'site-perspectives' ); ?>">
                                            <button type="button" class="siteperspectives-meta-remove" aria-label="<?php esc_attr_e( 'Remove', 'site-perspectives' ); ?>"><span class="dashicons dashicons-no-alt"></span></button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="siteperspectives-note siteperspectives-note-warning" id="siteperspectives-sc-meta-note" hidden>
                                    <span class="dashicons dashicons-warning"></span>
                                    <p><?php esc_html_e( 'Meta keys that start with an underscore are protected and are never shown to visitors.', 'site-perspectives' ); ?></p>
                                </div>
                                <div><button type="button" class="siteperspectives-button siteperspectives-button-secondary" id="siteperspectives-sc-add-meta"><span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( 'Add Custom Meta', 'site-perspectives' ); ?></button></div>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="siteperspectives-split-preview">
                    <div class="siteperspectives-box siteperspectives-sticky">
                        <div class="siteperspectives-box-header siteperspectives-preview-header">
                            <h2><?php esc_html_e( 'Live Preview', 'site-perspectives' ); ?></h2>
                            <div class="siteperspectives-orientation" role="group" aria-label="<?php esc_attr_e( 'Preview width', 'site-perspectives' ); ?>">
                                <button type="button" data-width="100%" class="is-active" title="<?php esc_attr_e( 'Desktop', 'site-perspectives' ); ?>"><span class="dashicons dashicons-desktop"></span></button>
                                <button type="button" data-width="768px" title="<?php esc_attr_e( 'Tablet', 'site-perspectives' ); ?>"><span class="dashicons dashicons-tablet"></span></button>
                                <button type="button" data-width="375px" title="<?php esc_attr_e( 'Mobile', 'site-perspectives' ); ?>"><span class="dashicons dashicons-smartphone"></span></button>
                            </div>
                        </div>
                        <div class="siteperspectives-preview-stage">
                            <iframe id="siteperspectives-preview" title="<?php esc_attr_e( 'Shortcode preview', 'site-perspectives' ); ?>" sandbox="allow-same-origin"></iframe>
                        </div>
                        <template id="siteperspectives-preview-template"><?php echo $preview; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside Tree::render_items(). ?></template>
                    </div>
                </div>

            </div>
        </div>
        <?php
    } // End render_page()

} // End class Shortcode

Shortcode::instance();
