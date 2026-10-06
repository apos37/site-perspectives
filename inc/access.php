<?php
/**
 * ACCESS CONTROL
 *
 * Two settings control which roles (beyond administrators) can view, and which can
 * edit, the Frontend and Backend tabs. Shortcode and Settings are always
 * limited to users who can manage options.
 */

namespace PluginRx\SitePerspectives;

if ( ! defined( 'ABSPATH' ) ) exit;


class Access {

    /**
     * Capability required for the Shortcode and Settings tabs.
     * Access to the tree tabs is broader (see can_view() and can_edit()).
     */
    public const MANAGE_CAP = 'manage_options';


    /**
     * @var Access|null Singleton instance
     */
    private static ?Access $instance = null;


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
        // No hooks needed.
    } // End __construct()


    /**
     * Check if the current user can manage plugin settings.
     *
     * @return bool
     */
    public static function can_manage() : bool {
        return current_user_can( self::MANAGE_CAP );
    } // End can_manage()


    /**
     * Check whether a user holds one of the roles saved under an option.
     *
     * @param string   $option  Option name holding an array of role slugs.
     * @param int|null $user_id Optional user ID, defaults to current user.
     * @return bool
     */
    private static function has_role_in( string $option, ?int $user_id ) : bool {
        $user = $user_id ? get_userdata( $user_id ) : wp_get_current_user();

        if ( ! $user || ! $user->exists() ) {
            return false;
        }

        if ( user_can( $user, self::MANAGE_CAP ) ) {
            return true;
        }

        return count( array_intersect( (array) get_option( $option, [] ), (array) $user->roles ) ) > 0;
    } // End has_role_in()


    /**
     * Check if the current user can edit the Frontend tree and the Backend layout.
     * Administrators always can; other roles are granted in Settings.
     *
     * @param int|null $user_id Optional user ID, defaults to current user.
     * @return bool
     */
    public static function can_edit( ?int $user_id = null ) : bool {
        return self::has_role_in( 'siteperspectives_edit_roles', $user_id );
    } // End can_edit()


    /**
     * Check if the current user can view the Frontend and Backend tabs. Anyone who can edit can also view.
     *
     * @param int|null $user_id Optional user ID, defaults to current user.
     * @return bool
     */
    public static function can_view( ?int $user_id = null ) : bool {
        return self::has_role_in( 'siteperspectives_view_roles', $user_id ) || self::can_edit( $user_id );
    } // End can_view()


    /**
     * Get all available roles for the access control setting UI.
     *
     * @return array Associative array of role_slug => role_label.
     */
    public static function get_assignable_roles() : array {
        $wp_roles = wp_roles();
        $roles = [];

        foreach ( $wp_roles->roles as $slug => $role ) {
            if ( 'administrator' === $slug ) {
                continue;
            }

            $roles[ $slug ] = translate_user_role( $role[ 'name' ] );
        }

        return $roles;
    } // End get_assignable_roles()

} // End class Access

Access::instance();