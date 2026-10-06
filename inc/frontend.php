<?php
/**
 * FRONTEND TAB
 *
 * An admin sitemap of the site's front-end pages as a tree of cards, seeded from the
 * primary menu, with an inline edit mode (drag and drop, add content, custom links, parents).
 */

namespace PluginRx\SitePerspectives;

if ( ! defined( 'ABSPATH' ) ) exit;


class Frontend {

    /**
     * @var Frontend|null Singleton instance
     */
    private static ?Frontend $instance = null;


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
        add_action( 'siteperspectives_subheader_left', [ $this, 'render_subheader_buttons' ] );
        add_action( 'siteperspectives_subheader_right', [ $this, 'render_subheader_toolbar' ] );
    } // End __construct()


    /**
     * Enqueue assets on this tab only.
     *
     * @return void
     */
    public function enqueue() : void {
        if ( ! Helpers::is_plugin_screen() || Menu::MENU_SLUG . '-frontend' !== Menu::get_current_page_slug() ) {
            return;
        }

        Tree::enqueue_assets( 'frontend' );
    } // End enqueue()


    /**
     * Get the tree tab shown on a page slug, or an empty string for other pages.
     *
     * @param string $active_page
     * @return string 'frontend', 'backend' or ''.
     */
    private static function tab_for_page( string $active_page ) : string {
        if ( Menu::MENU_SLUG === $active_page ) {
            return 'backend';
        }

        return Menu::MENU_SLUG . '-frontend' === $active_page ? 'frontend' : '';
    } // End tab_for_page()


    /**
     * Render the Edit / Save / Cancel buttons in the left of the subheader.
     *
     * @param string $active_page
     * @return void
     */
    public function render_subheader_buttons( string $active_page ) : void {
        if ( '' === self::tab_for_page( $active_page ) || ! Access::can_edit() ) {
            return;
        }

        self::render_edit_buttons();
    } // End render_subheader_buttons()


    /**
     * Render the search, expand/collapse and view buttons in the right of the subheader.
     *
     * @param string $active_page
     * @return void
     */
    public function render_subheader_toolbar( string $active_page ) : void {
        $tab = self::tab_for_page( $active_page );

        if ( '' === $tab || ! Access::can_view() ) {
            return;
        }

        Tree::render_toolbar( Tree::get_orientation( $tab ) );
    } // End render_subheader_toolbar()


    /**
     * Render the shared edit controls used by the Frontend and Backend tabs.
     *
     * @return void
     */
    public static function render_edit_buttons() : void {
        ?>
        <span class="siteperspectives-edit-actions" hidden>
            <button type="button" class="siteperspectives-button" id="siteperspectives-save"><?php esc_html_e( 'Save', 'site-perspectives' ); ?></button>
            <button type="button" class="siteperspectives-button siteperspectives-button-secondary" id="siteperspectives-cancel"><?php esc_html_e( 'Cancel', 'site-perspectives' ); ?></button>
        </span>
        <button type="button" class="siteperspectives-button" id="siteperspectives-edit"><span class="dashicons dashicons-edit"></span> <?php esc_html_e( 'Edit', 'site-perspectives' ); ?></button>
        <span id="siteperspectives-save-reminder"><?php esc_html_e( 'You have unsaved changes.', 'site-perspectives' ); ?></span>
        <span id="siteperspectives-save-status" role="status"></span>
        <?php
    } // End render_edit_buttons()


    /**
     * Render the page.
     *
     * @return void
     */
    public static function render_page() : void {
        if ( ! Access::can_view() ) {
            wp_die( esc_html__( 'You do not have permission to view this page.', 'site-perspectives' ) );
        }

        $view = Tree::get_view( 'frontend' );
        $tree = Tree::get_tree();
        $html = Tree::render_admin_lists( [ 'main' => $tree[ 'main' ], 'utility' => $tree[ 'utility' ] ], 'frontend', $view );
        $orientation = Tree::get_orientation( 'frontend' );
        ?>
        <div class="wrap siteperspectives-wrap siteperspectives-content-wrap" id="siteperspectives-app" data-tab="frontend" data-orientation="<?php echo esc_attr( $orientation ); ?>">

            <?php if ( ! Tree::has_saved_tree() ) : ?>
                <div class="siteperspectives-note">
                    <span class="dashicons dashicons-info-outline"></span>
                    <p><?php esc_html_e( 'This tree was built automatically from your primary menu. Click Edit, arrange it however you like, then Save to keep your layout.', 'site-perspectives' ); ?></p>
                </div>
            <?php endif; ?>

            <?php Tree::render_options_panel( 'frontend', $view ); ?>

            <div class="siteperspectives-layout">
                <?php self::render_add_panel(); ?>

                <div class="siteperspectives-main">
                    <?php echo Tree::render_home( false, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside Tree::render_home(). ?>
                    <ul class="siteperspectives-list siteperspectives-root" data-list="main"><?php echo $html[ 'main' ]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside Tree::render_items(). ?></ul>
                    <p class="siteperspectives-empty" hidden><?php esc_html_e( 'Nothing here yet. Click Edit to add pages, posts, custom links or parent items.', 'site-perspectives' ); ?></p>

                    <div class="siteperspectives-utility">
                        <h3 class="siteperspectives-section-title"><span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e( 'Utility Pages', 'site-perspectives' ); ?></h3>
                        <p class="siteperspectives-section-desc"><?php esc_html_e( 'Pages that are not part of the main navigation, such as login and privacy policy. You can drag items between this list and the tree above.', 'site-perspectives' ); ?></p>
                        <ul class="siteperspectives-list siteperspectives-root" data-list="utility"><?php echo $html[ 'utility' ]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside Tree::render_items(). ?></ul>
                    </div>
                </div>
            </div>
        </div>
        <?php
    } // End render_page()


    /**
     * Render the "Add Items" side panel shown in edit mode.
     *
     * @return void
     */
    private static function render_add_panel() : void {
        ?>
        <aside class="siteperspectives-add-panel" id="siteperspectives-add-panel" hidden>
            <div class="siteperspectives-box">
                <div class="siteperspectives-box-header"><h2><?php esc_html_e( 'Add Items', 'site-perspectives' ); ?></h2></div>
                <div class="siteperspectives-add-parent">
                    <label for="siteperspectives-add-parent"><?php esc_html_e( 'Add under', 'site-perspectives' ); ?></label>
                    <select id="siteperspectives-add-parent">
                        <option value=""><?php esc_html_e( 'No parent (top level)', 'site-perspectives' ); ?></option>
                    </select>
                </div>
                <div class="siteperspectives-accordion">

                    <?php foreach ( Helpers::tree_post_types() as $slug => $object ) : ?>
                        <?php
                        /* translators: %s: post type plural label */
                        $placeholder = sprintf( __( 'Search %s', 'site-perspectives' ), $object->labels->name );
                        ?>
                        <details class="siteperspectives-acc siteperspectives-acc-posts" data-post-type="<?php echo esc_attr( $slug ); ?>">
                            <summary><?php echo esc_html( $object->labels->name ); ?></summary>
                            <div class="siteperspectives-acc-body">
                                <input type="search" class="siteperspectives-acc-search" placeholder="<?php echo esc_attr( $placeholder ); ?>">
                                <ul class="siteperspectives-acc-list"></ul>
                                <div class="siteperspectives-acc-actions">
                                    <button type="button" class="siteperspectives-button siteperspectives-button-secondary siteperspectives-acc-more" hidden><?php esc_html_e( 'Load more', 'site-perspectives' ); ?></button>
                                    <button type="button" class="siteperspectives-button siteperspectives-acc-add"><?php esc_html_e( 'Add to Tree', 'site-perspectives' ); ?></button>
                                </div>
                            </div>
                        </details>
                    <?php endforeach; ?>

                    <details class="siteperspectives-acc" id="siteperspectives-acc-custom">
                        <summary><?php esc_html_e( 'Custom Link', 'site-perspectives' ); ?></summary>
                        <div class="siteperspectives-acc-body">
                            <label class="siteperspectives-edit-field"><span><?php esc_html_e( 'Link Text', 'site-perspectives' ); ?></span><input type="text" class="siteperspectives-new-title"></label>
                            <label class="siteperspectives-edit-field"><span><?php esc_html_e( 'URL', 'site-perspectives' ); ?></span><input type="text" class="siteperspectives-new-url" placeholder="https://"></label>
                            <div class="siteperspectives-acc-actions"><button type="button" class="siteperspectives-button siteperspectives-add-custom"><?php esc_html_e( 'Add to Tree', 'site-perspectives' ); ?></button></div>
                        </div>
                    </details>

                </div>
            </div>
        </aside>
        <?php
    } // End render_add_panel()

} // End class Frontend

Frontend::instance();
