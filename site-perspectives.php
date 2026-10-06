<?php
/**
 * Plugin Name:         Site Perspectives – Your Site Structure, Mapped for Everyone
 * Plugin URI:          https://pluginrx.com/plugin/site-perspectives/
 * Description:         Build your site structure once, then use it everywhere: a drag and drop site tree, a visitor sitemap shortcode, and a folder-style admin menu.
 * Version:             1.0.3
 * Requires at least:   6.0
 * Tested up to:        7.1
 * Requires PHP:        8.0
 * Author:              PluginRx
 * Author URI:          https://pluginrx.com/
 * Discord URI:         https://discord.gg/3HnzNEJVnR
 * Text Domain:         site-perspectives
 * License:             GPLv2 or later
 * License URI:         https://www.gnu.org/licenses/gpl-2.0.html
 * Created on:          October 2, 2026
 */

namespace PluginRx\SitePerspectives;

if ( ! defined( 'ABSPATH' ) ) exit;


/**
 * BOOTSTRAP
 *
 * Loads plugin metadata, performs environment checks, and initializes the plugin.
 */
final class Bootstrap {

    /**
     * Plugin files to load.
     *
     * This array contains the paths to all plugin files that need to be included.
     */
    public const FILES = [
        'helpers.php',
        'access.php',
        'menu.php',
        'plugin-page.php',
        'colors.php',
        'tree.php',
        'tree-ajax.php',
        'dynamic-css.php',
        'frontend.php',
        'backend.php',
        'shortcode.php',
        'utility-pages.php',
        'auto-update.php',
        'settings.php',
        'advanced.php',
        'integrations.php',
    ];


    /**
     * Plugin header keys for get_file_data()
     */
    public const HEADER_KEYS = [
        'name'         => 'Plugin Name',
        'description'  => 'Description',
        'version'      => 'Version',
        'plugin_uri'   => 'Plugin URI',
        'requires_php' => 'Requires PHP',
        'textdomain'   => 'Text Domain',
        'author'       => 'Author',
        'author_uri'   => 'Author URI',
        'discord_uri'  => 'Discord URI',
    ];


    /**
     * @var array Plugin metadata from file header
     */
    private array $meta;


    /**
     * @var Bootstrap|null Singleton instance
     */
    private static ?Bootstrap $instance = null;


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
        $this->meta = $this->load_meta();
        $this->check_environment();
        add_action( 'plugins_loaded', [ $this, 'load_files' ] );
        add_action( 'plugins_loaded', [ $this, 'load_integrations' ], 20 );
        register_activation_hook( __FILE__, [ $this, 'activate' ] );
    } // End __construct()


    /**
     * Load plugin meta from file header
     *
     * @return array
     */
    private function load_meta() : array {
        return get_file_data( __FILE__, self::HEADER_KEYS );
    } // End load_meta()


    /**
     * Get a meta value
     *
     * @param string $key
     * @return string
     */
    public function meta( string $key ) : string {
        return $this->meta[ $key ] ?? '';
    } // End meta()


    /**
     * Get plugin version
     *
     * @return string
     */
    public static function version() : string {
        return self::instance()->meta( 'version' );
    } // End version()


    /**
     * Check if the site is in test mode via Developer Debug Tools.
     *
     * @return bool
     */
    public static function test_mode() : bool {
        return (bool) get_option( 'ddtt_test_mode' );
    } // End test_mode()


    /**
     * Get the version string used for enqueued scripts/styles.
     *
     * @return string
     */
    public static function script_version() : string {
        return self::test_mode() ? (string) time() : self::version();
    } // End script_version()


    /**
     * Get the plugin's base directory path, with trailing slash.
     *
     * @return string
     */
    public static function dir() : string {
        return plugin_dir_path( __FILE__ );
    } // End dir()


    /**
     * Get the plugin's base URL, with trailing slash.
     *
     * @return string
     */
    public static function url() : string {
        return plugin_dir_url( __FILE__ );
    } // End url()


    /**
     * Get the plugin's main file path.
     *
     * @return string
     */
    public static function file() : string {
        return __FILE__;
    } // End file()


    /**
     * Check environment requirements, deactivate if not met
     *
     * @return void
     */
    private function check_environment() : void {
        $requires_php = $this->meta( 'requires_php' );

        if ( $requires_php && version_compare( PHP_VERSION, $requires_php, '<' ) ) {
            add_action( 'admin_notices', [ $this, 'notice_php_version' ] );
        }
    } // End check_environment()


    /**
     * Admin notice for unsupported PHP version
     *
     * @return void
     */
    public function notice_php_version() : void {
        echo '<div class="notice notice-error"><p>' . esc_html__( 'Site Perspectives requires a newer version of PHP. Please contact your host to upgrade.', 'site-perspectives' ) . '</p></div>';
    } // End notice_php_version()


    /**
     * Load plugin files
     *
     * @return void
     */
    public function load_files() : void {
        if ( version_compare( PHP_VERSION, $this->meta( 'requires_php' ), '<' ) ) {
            return;
        }

        foreach ( self::FILES as $file ) {
            $path = plugin_dir_path( __FILE__ ) . 'inc/' . $file;
            if ( file_exists( $path ) ) {
                require_once $path;
            }
        }
    } // End load_files()


    /**
     * Load each active integration's integration.php file from inc/integrations/{slug}/.
     * Each integration.php gates itself on the companion plugin being active.
     *
     * @return void
     */
    public function load_integrations() : void {
        if ( ! class_exists( __NAMESPACE__ . '\\Integrations' ) ) {
            return;
        }

        $integrations_dir = self::dir() . 'inc/integrations/';

        if ( ! is_dir( $integrations_dir ) ) {
            return;
        }

        foreach ( glob( $integrations_dir . '*', GLOB_ONLYDIR ) as $folder ) {
            $file = $folder . '/integration.php';

            if ( file_exists( $file ) ) {
                require_once $file;
            }
        }
    } // End load_integrations()


    /**
     * Activation hook.
     *
     * @return void
     */
    public function activate() : void {
        if ( ! get_option( 'siteperspectives_activated_time' ) ) {
            update_option( 'siteperspectives_activated_time', time(), false );
        }
    } // End activate()


} // End class Bootstrap

Bootstrap::instance();
