<?php
/**
 * SETTINGS
 *
 * Interface, Access and Advanced panels, saved over AJAX.
 */

namespace PluginRx\SitePerspectives;

if ( ! defined( 'ABSPATH' ) ) exit;


class Settings {

    /**
     * @var Settings|null Singleton instance
     */
    private static ?Settings $instance = null;


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
        add_action( 'wp_ajax_siteperspectives_save_settings', [ $this, 'ajax_save_settings' ] );
        add_action( 'siteperspectives_subheader_left', [ $this, 'render_subheader_buttons' ] );
        add_action( 'siteperspectives_subheader_right', [ $this, 'render_version' ], 5 );
        add_action( 'siteperspectives_subheader_right', [ $this, 'render_subheader_links' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
    } // End __construct()


    /**
     * Enqueue assets on this tab.
     *
     * @return void
     */
    public function enqueue() : void {
        if ( ! Helpers::is_plugin_screen() || Menu::MENU_SLUG . '-settings' !== Menu::get_current_page_slug() || ! Access::can_manage() ) {
            return;
        }

        wp_enqueue_script( 'siteperspectives-settings', Bootstrap::url() . 'inc/js/settings.js', [ 'jquery' ], Bootstrap::script_version(), true );

        wp_localize_script( 'siteperspectives-settings', 'sitePerspectivesSettings', [
            'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
            'nonce'       => wp_create_nonce( 'siteperspectives_settings_nonce' ),
            'savingText'  => __( 'Saving', 'site-perspectives' ),
            'savedText'   => __( 'Settings saved successfully.', 'site-perspectives' ),
            'errorText'   => __( 'Error saving settings. Please try again.', 'site-perspectives' ),
            'importOk'    => __( 'Settings imported. Reloading...', 'site-perspectives' ),
            'importError' => __( 'That file is not a valid Site Perspectives export.', 'site-perspectives' ),
            'resetOk'     => __( 'Settings reset. Reloading...', 'site-perspectives' ),
            'confirmReset' => __( 'Reset all Site Perspectives settings, the saved tree, the backend layout, shortcode settings to their defaults?', 'site-perspectives' ),
            'confirmImport' => __( 'Importing will overwrite your current Site Perspectives settings. Continue?', 'site-perspectives' ),
        ] );
    } // End enqueue()


    /**
     * Render the Save button and dirty-state reminder in the subheader.
     *
     * @param string $active_page
     * @return void
     */
    public function render_subheader_buttons( string $active_page ) : void {
        if ( Menu::MENU_SLUG . '-settings' !== $active_page || ! Access::can_manage() ) {
            return;
        }
        ?>
        <button type="button" class="siteperspectives-button" id="siteperspectives-save"><?php esc_html_e( 'Save', 'site-perspectives' ); ?></button>
        <span id="siteperspectives-save-reminder"><?php esc_html_e( 'Remember to click "Save" after making changes to your settings.', 'site-perspectives' ); ?></span>
        <?php
    } // End render_subheader_buttons()


    /**
     * Render the plugin version in the right subheader.
     *
     * @param string $active_page
     * @return void
     */
    public function render_version( string $active_page ) : void {
        if ( Menu::MENU_SLUG . '-settings' !== $active_page ) {
            return;
        }
        ?>
        <span id="siteperspectives-version"><?php esc_html_e( 'Version', 'site-perspectives' ); ?> <?php echo esc_html( Bootstrap::version() ); ?></span>
        <?php
    } // End render_version()


    /**
     * Render the guide, docs and support buttons in the right subheader.
     *
     * @param string $active_page
     * @return void
     */
    public function render_subheader_links( string $active_page ) : void {
        if ( Menu::MENU_SLUG . '-settings' !== $active_page ) {
            return;
        }

        $links = [
            'guide'   => [ __( 'How-To Guide', 'site-perspectives' ), 'https://pluginrx.com/guide/plugin/site-perspectives/' ],
            'docs'    => [ __( 'Developer Docs', 'site-perspectives' ), 'https://pluginrx.com/docs/plugin/site-perspectives/' ],
            'support' => [ __( 'Support Forum', 'site-perspectives' ), 'https://pluginrx.com/support/plugin/site-perspectives/' ],
        ];

        foreach ( $links as $link ) {
            ?>
            <a href="<?php echo esc_url( $link[ 1 ] ); ?>" target="_blank" rel="noopener noreferrer" class="siteperspectives-button siteperspectives-external-link"><?php echo esc_html( $link[ 0 ] ); ?> <span class="dashicons dashicons-external"></span></a>
            <?php
        }
    } // End render_subheader_links()


    /**
     * Render the Settings admin page.
     *
     * @return void
     */
    public static function render_page() : void {
        if ( ! Access::can_manage() ) {
            wp_die( esc_html__( 'You do not have permission to view this page.', 'site-perspectives' ) );
        }

        $menu_title = get_option( 'siteperspectives_menu_title', __( 'Perspectives', 'site-perspectives' ) );
        $page_title = get_option( 'siteperspectives_page_title', __( 'Site Perspectives', 'site-perspectives' ) );
        $menu_icon = Helpers::icon( get_option( 'siteperspectives_menu_icon', 'networking' ), 'networking' );
        $logo = get_option( 'siteperspectives_logo', '' );
        $tree_post_types = Helpers::tree_post_types();
        $auto_post_types = (array) get_option( 'siteperspectives_auto_post_types', [] );
        $roles_by_option = [
            'siteperspectives_view_roles' => (array) get_option( 'siteperspectives_view_roles', [] ),
            'siteperspectives_edit_roles' => (array) get_option( 'siteperspectives_edit_roles', [] ),
        ];
        ?>
        <div class="wrap siteperspectives-wrap siteperspectives-content-wrap siteperspectives-settings">
            <div class="siteperspectives-settings-grid" id="siteperspectives-settings-form">

                <div class="siteperspectives-box">
                    <div class="siteperspectives-box-header"><h2><?php esc_html_e( 'Interface', 'site-perspectives' ); ?></h2></div>
                    <div class="siteperspectives-box-body">
                        <div class="siteperspectives-field">
                            <label for="siteperspectives_menu_title"><?php esc_html_e( 'Menu Title', 'site-perspectives' ); ?></label>
                            <input type="text" id="siteperspectives_menu_title" name="siteperspectives_menu_title" value="<?php echo esc_attr( $menu_title ); ?>">
                        </div>
                        <div class="siteperspectives-field">
                            <label for="siteperspectives_menu_icon">
                                <?php esc_html_e( 'Menu Icon', 'site-perspectives' ); ?> -
                                <a href="https://developer.wordpress.org/resource/dashicons/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View Dashicons', 'site-perspectives' ); ?> <span class="dashicons dashicons-external"></span></a>
                            </label>
                            <select id="siteperspectives_menu_icon" name="siteperspectives_menu_icon">
                                <?php foreach ( Helpers::get_dashicons() as $icon ) : ?>
                                    <option value="<?php echo esc_attr( $icon ); ?>" <?php selected( $menu_icon, $icon ); ?>><?php echo esc_html( $icon ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="siteperspectives-field">
                            <label for="siteperspectives_page_title"><?php esc_html_e( 'Page Title', 'site-perspectives' ); ?></label>
                            <input type="text" id="siteperspectives_page_title" name="siteperspectives_page_title" value="<?php echo esc_attr( $page_title ); ?>">
                        </div>
                        <div class="siteperspectives-field">
                            <label for="siteperspectives_logo">
                                <?php esc_html_e( 'Page Logo', 'site-perspectives' ); ?>
                                <?php Helpers::tooltip( __( 'Preferred size: 100x100 pixels. Accepted formats: jpg | jpeg | png | webp', 'site-perspectives' ) ); ?>
                            </label>
                            <input type="text" id="siteperspectives_logo" name="siteperspectives_logo" value="<?php echo esc_attr( $logo ); ?>" placeholder="https://example.com/logo.png">
                        </div>
                    </div>
                </div>

                <div class="siteperspectives-box">
                    <div class="siteperspectives-box-header"><h2><?php esc_html_e( 'Frontend Tree', 'site-perspectives' ); ?></h2></div>
                    <div class="siteperspectives-box-body">
                        <div class="siteperspectives-field">
                            <label>
                                <?php esc_html_e( 'Post Types to Include', 'site-perspectives' ); ?>
                                <?php Helpers::tooltip( __( 'Choose which content types can be added to the Frontend tree. Items already in the tree stay, even if their type is unchecked here.', 'site-perspectives' ) ); ?>
                            </label>
                            <div class="siteperspectives-checkboxes">
                                <?php foreach ( Helpers::public_post_types() as $slug => $object ) : ?>
                                    <label>
                                        <input type="checkbox" name="siteperspectives_tree_post_types[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( array_key_exists( $slug, $tree_post_types ) ); ?>>
                                        <?php echo esc_html( $object->labels->name ); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="siteperspectives-box">
                    <div class="siteperspectives-box-header"><h2><?php esc_html_e( 'Automatic Updates', 'site-perspectives' ); ?></h2></div>
                    <div class="siteperspectives-box-body">
                        <div class="siteperspectives-field">
                            <label>
                                <?php esc_html_e( 'Add Newly Published Content', 'site-perspectives' ); ?>
                                <?php Helpers::tooltip( __( 'New items are added once, when first published. Pages go under their parent if it is in the tree, otherwise to the end of the main list. Pages flagged as utility go to the Utility list. Trashed or deleted items are removed from the tree.', 'site-perspectives' ) ); ?>
                            </label>
                            <div class="siteperspectives-checkboxes">
                                <?php foreach ( $tree_post_types as $slug => $object ) : ?>
                                    <label>
                                        <input type="checkbox" name="siteperspectives_auto_post_types[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $auto_post_types, true ) ); ?>>
                                        <?php echo esc_html( $object->labels->name ); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="siteperspectives-field">
                            <label class="siteperspectives-inline-label">
                                <input type="checkbox" name="siteperspectives_auto_hide_new" value="1" <?php checked( (bool) get_option( 'siteperspectives_auto_hide_new', 0 ) ); ?>>
                                <?php esc_html_e( 'New items start hidden from the shortcode', 'site-perspectives' ); ?>
                                <?php Helpers::tooltip( __( 'Automatically added items show on the Frontend tab but stay off the shortcode until you uncheck "Hide from Shortcode".', 'site-perspectives' ) ); ?>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="siteperspectives-box">
                    <div class="siteperspectives-box-header"><h2><?php esc_html_e( 'Utility Pages', 'site-perspectives' ); ?></h2></div>
                    <div class="siteperspectives-box-body">
                        <div class="siteperspectives-field">
                            <label class="siteperspectives-inline-label">
                                <input type="checkbox" name="siteperspectives_utility_flags" value="1" <?php checked( (bool) get_option( 'siteperspectives_utility_flags', 0 ) ); ?>>
                                <?php esc_html_e( 'Enable Utility Page Flags', 'site-perspectives' ); ?>
                                <?php Helpers::tooltip( __( 'Adds a Utility column, a Quick Edit checkbox and bulk actions to the Pages list, plus Utility and Non-Utility views that remember the last view you used.', 'site-perspectives' ) ); ?>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="siteperspectives-box">
                    <div class="siteperspectives-box-header"><h2><?php esc_html_e( 'Access', 'site-perspectives' ); ?></h2></div>
                    <div class="siteperspectives-box-body">
                        <?php
                        $access_fields = [
                            'siteperspectives_view_roles' => [ __( 'Roles That Can View', 'site-perspectives' ), __( 'Can open the Frontend and Backend tabs but not change anything. Administrators always have full access. Anyone who can edit can also view.', 'site-perspectives' ) ],
                            'siteperspectives_edit_roles' => [ __( 'Roles That Can Edit', 'site-perspectives' ), __( 'Can edit the Frontend tree and the Backend layout. The Shortcode and Settings tabs are always administrators only.', 'site-perspectives' ) ],
                        ];
                        ?>
                        <?php foreach ( $access_fields as $option => $field ) : ?>
                            <div class="siteperspectives-field">
                                <label>
                                    <?php echo esc_html( $field[ 0 ] ); ?>
                                    <?php Helpers::tooltip( $field[ 1 ] ); ?>
                                </label>
                                <div class="siteperspectives-checkboxes">
                                    <?php foreach ( Access::get_assignable_roles() as $slug => $label ) : ?>
                                        <label>
                                            <input type="checkbox" name="<?php echo esc_attr( $option ); ?>[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $roles_by_option[ $option ], true ) ); ?>>
                                            <?php echo esc_html( $label ); ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="siteperspectives-box">
                    <div class="siteperspectives-box-header"><h2><?php esc_html_e( 'Advanced', 'site-perspectives' ); ?></h2></div>
                    <div class="siteperspectives-box-body">

                        <div class="siteperspectives-field">
                            <label class="siteperspectives-inline-label">
                                <input type="checkbox" name="siteperspectives_clear_data_on_uninstall" value="1" <?php checked( (bool) get_option( 'siteperspectives_clear_data_on_uninstall' ) ); ?>>
                                <?php esc_html_e( 'Remove All Plugin Data on Uninstall', 'site-perspectives' ); ?>
                                <?php Helpers::tooltip( __( 'Deletes all Site Perspectives settings, the saved tree, backend layout and cached data permanently when the plugin is deleted.', 'site-perspectives' ) ); ?>
                            </label>
                        </div>

                        <div class="siteperspectives-field" id="siteperspectives_field_upload_download_settings">
                            <label>
                                <?php esc_html_e( 'Upload/Download Settings', 'site-perspectives' ); ?>
                                <?php Helpers::tooltip( __( 'Export everything Site Perspectives stores as a JSON file for backup or to restore on this site later. Items in the tree are matched by post ID, so imports are intended for the same site. Importing overwrites your current settings.', 'site-perspectives' ) ); ?>
                            </label>
                            <div class="siteperspectives-inline-actions">
                                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                                    <?php wp_nonce_field( 'siteperspectives_advanced_action' ); ?>
                                    <input type="hidden" name="action" value="siteperspectives_export">
                                    <button type="submit" class="siteperspectives-button"><?php esc_html_e( 'Download Settings', 'site-perspectives' ); ?></button>
                                </form>
                                <label for="siteperspectives-upload-settings" class="siteperspectives-button" tabindex="0"><?php esc_html_e( 'Upload Settings', 'site-perspectives' ); ?></label>
                                <input type="file" id="siteperspectives-upload-settings" accept="application/json" hidden>
                                <span id="siteperspectives-upload-filename"></span>
                            </div>
                        </div>

                        <div class="siteperspectives-field" id="siteperspectives_field_reset_settings">
                            <label>
                                <?php esc_html_e( 'Reset Settings', 'site-perspectives' ); ?>
                                <?php Helpers::tooltip( __( 'Resets every Site Perspectives option to its default. Your posts and pages are never touched.', 'site-perspectives' ) ); ?>
                            </label>
                            <div><button type="button" class="siteperspectives-button siteperspectives-button-danger" id="siteperspectives-reset-settings"><?php esc_html_e( 'Reset Settings', 'site-perspectives' ); ?></button></div>
                        </div>

                    </div>
                </div>

            </div>
        </div>
        <?php
    } // End render_page()


    /**
     * AJAX handler to save every settings field at once.
     *
     * @return void
     */
    public function ajax_save_settings() : void {
        check_ajax_referer( 'siteperspectives_settings_nonce', 'nonce' );

        if ( ! Access::can_manage() ) {
            wp_send_json_error( __( 'Insufficient permissions.', 'site-perspectives' ), 403 );
        }

        $settings = isset( $_POST[ 'settings' ] ) ? wp_unslash( $_POST[ 'settings' ] ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- every value is sanitized individually in self::apply().

        if ( ! is_array( $settings ) ) {
            wp_send_json_error( __( 'Invalid data.', 'site-perspectives' ) );
        }

        self::apply( $settings );

        wp_send_json_success();
    } // End ajax_save_settings()


    /**
     * Sanitize and store the settings handled by this tab.
     *
     * @param array $settings Raw values keyed by option name.
     * @return void
     */
    public static function apply( array $settings ) : void {
        $text_fields = [
            'siteperspectives_menu_title' => 'sanitize_text_field',
            'siteperspectives_page_title' => 'sanitize_text_field',
            'siteperspectives_logo'       => 'esc_url_raw',
        ];

        foreach ( $text_fields as $key => $sanitizer ) {
            if ( isset( $settings[ $key ] ) && is_string( $settings[ $key ] ) ) {
                update_option( $key, call_user_func( $sanitizer, $settings[ $key ] ) );
            }
        }

        if ( isset( $settings[ 'siteperspectives_menu_icon' ] ) ) {
            update_option( 'siteperspectives_menu_icon', Helpers::icon( $settings[ 'siteperspectives_menu_icon' ], 'networking' ) );
        }

        $types = isset( $settings[ 'siteperspectives_tree_post_types' ] ) ? array_map( 'sanitize_key', (array) $settings[ 'siteperspectives_tree_post_types' ] ) : [];
        $types = array_values( array_intersect( $types, array_keys( Helpers::public_post_types() ) ) );
        update_option( 'siteperspectives_tree_post_types', ! empty( $types ) ? $types : [ 'page' ] );

        foreach ( [ 'siteperspectives_view_roles', 'siteperspectives_edit_roles' ] as $option ) {
            $roles = isset( $settings[ $option ] ) ? array_map( 'sanitize_key', (array) $settings[ $option ] ) : [];
            update_option( $option, array_values( array_intersect( $roles, array_keys( Access::get_assignable_roles() ) ) ) );
        }

        $auto = isset( $settings[ 'siteperspectives_auto_post_types' ] ) ? array_map( 'sanitize_key', (array) $settings[ 'siteperspectives_auto_post_types' ] ) : [];
        update_option( 'siteperspectives_auto_post_types', array_values( array_intersect( $auto, array_keys( Helpers::tree_post_types() ) ) ) );
        update_option( 'siteperspectives_auto_hide_new', ! empty( $settings[ 'siteperspectives_auto_hide_new' ] ) ? 1 : 0 );
        update_option( 'siteperspectives_utility_flags', ! empty( $settings[ 'siteperspectives_utility_flags' ] ) ? 1 : 0 );
        update_option( 'siteperspectives_clear_data_on_uninstall', ! empty( $settings[ 'siteperspectives_clear_data_on_uninstall' ] ) ? 1 : 0 );
    } // End apply()

} // End class Settings

Settings::instance();
