<?php
/**
 * HELPERS
 *
 * Shared utility functions used across the plugin.
 */

namespace PluginRx\SitePerspectives;

if ( ! defined( 'ABSPATH' ) ) exit;


class Helpers {

    /**
     * Check whether the current admin screen belongs to this plugin.
     *
     * @return bool
     */
    public static function is_plugin_screen() : bool {
        if ( ! function_exists( 'get_current_screen' ) ) {
            return false;
        }

        $screen = get_current_screen();

        if ( ! $screen ) {
            return false;
        }

        return false !== strpos( $screen->id, Menu::MENU_SLUG );
    } // End is_plugin_screen()


    /**
     * Generate a unique, prefixed node ID.
     *
     * @param string $prefix
     * @return string
     */
    public static function generate_id( string $prefix = 'n' ) : string {
        return $prefix . '_' . substr( md5( uniqid( '', true ) ), 0, 10 );
    } // End generate_id()


    /**
     * Format a date string using WordPress's configured date and time format.
     *
     * @param string $date_string A date parseable by strtotime().
     * @return string
     */
    public static function format_datetime( string $date_string ) : string {
        $timestamp = strtotime( $date_string );

        if ( ! $timestamp ) {
            return '';
        }

        return date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
    } // End format_datetime()


    /**
     * Get the path portion of a URL relative to the site's home path.
     *
     * @param string $url
     * @return string
     */
    public static function url_path( string $url ) : string {
        $path = wp_parse_url( $url, PHP_URL_PATH );
        $query = wp_parse_url( $url, PHP_URL_QUERY );
        $home_path = untrailingslashit( (string) wp_parse_url( home_url(), PHP_URL_PATH ) );

        if ( ! is_string( $path ) || '' === $path ) {
            $path = '/';
        }

        if ( '' !== $home_path && 0 === strpos( $path, $home_path ) ) {
            $path = substr( $path, strlen( $home_path ) );
        }

        if ( '' === $path ) {
            $path = '/';
        }

        return $query ? $path . '?' . $query : $path;
    } // End url_path()


    /**
     * Sanitize a hex color, returning a fallback when invalid.
     *
     * @param mixed  $color
     * @param string $fallback
     * @return string
     */
    public static function hex( $color, string $fallback = '' ) : string {
        $clean = sanitize_hex_color( is_string( $color ) ? $color : '' );

        return $clean ? $clean : $fallback;
    } // End hex()


    /**
     * Sanitize a Dashicon slug (without the dashicons- prefix).
     *
     * @param mixed  $icon
     * @param string $fallback
     * @return string
     */
    public static function icon( $icon, string $fallback = '' ) : string {
        $icon = sanitize_key( is_string( $icon ) ? preg_replace( '/^dashicons-/', '', $icon ) : '' );

        return in_array( $icon, self::get_dashicons(), true ) ? $icon : $fallback;
    } // End icon()


    /**
     * Get all public post types that can appear in the tree or sitemap.
     *
     * @return array Associative array of slug => WP_Post_Type.
     */
    public static function public_post_types() : array {
        $types = get_post_types( [ 'public' => true ], 'objects' );
        unset( $types[ 'attachment' ] );

        return $types;
    } // End public_post_types()


    /**
     * Relative luminance of a hex color (WCAG 2.x).
     *
     * @param string $hex
     * @return float
     */
    private static function luminance( string $hex ) : float {
        $hex = ltrim( self::hex( $hex, '#000000' ), '#' );
        $hex = 3 === strlen( $hex ) ? $hex[ 0 ] . $hex[ 0 ] . $hex[ 1 ] . $hex[ 1 ] . $hex[ 2 ] . $hex[ 2 ] : $hex;
        $channels = [];

        foreach ( [ 0, 2, 4 ] as $offset ) {
            $value = hexdec( substr( $hex, $offset, 2 ) ) / 255;
            $channels[] = $value <= 0.03928 ? $value / 12.92 : pow( ( $value + 0.055 ) / 1.055, 2.4 );
        }

        return 0.2126 * $channels[ 0 ] + 0.7152 * $channels[ 1 ] + 0.0722 * $channels[ 2 ];
    } // End luminance()


    /**
     * Get white for dark and mid-tone backgrounds (relative luminance up to 0.22), black otherwise.
     *
     * @param string $background Hex color.
     * @return string '#000000' or '#ffffff'.
     */
    public static function text_color( string $background ) : string {
        $luminance = self::luminance( $background );

        return $luminance <= 0.22 ? '#ffffff' : '#000000';
    } // End text_color()


    /**
     * Get the CSS class for a node color. The matching rule is served by DynamicCss.
     *
     * @param string $hex
     * @return string Empty when the color is invalid.
     */
    public static function color_class( string $hex ) : string {
        $hex = self::hex( $hex );

        return '' === $hex ? '' : 'tl-c-' . substr( md5( strtolower( $hex ) ), 0, 8 );
    } // End color_class()


    /**
     * Get the post types that can be added to the Frontend tree. Defaults to every public post type.
     *
     * @return array Associative array of slug => WP_Post_Type.
     */
    public static function tree_post_types() : array {
        $all = self::public_post_types();
        $saved = get_option( 'siteperspectives_tree_post_types', null );

        if ( ! is_array( $saved ) || empty( $saved ) ) {
            return $all;
        }

        return array_intersect_key( $all, array_flip( $saved ) );
    } // End tree_post_types()


    /**
     * Get a curated list of Dashicons for the icon pickers.
     *
     * @return array List of dashicon slugs without the dashicons- prefix.
     */
    public static function get_dashicons() : array {
        static $cache = null;

        if ( null !== $cache ) {
            return $cache;
        }

        $dashicons = [
            'admin-appearance', 'admin-collapse', 'admin-comments', 'admin-customizer', 'admin-generic', 'admin-home', 'admin-links', 'admin-media',
            'admin-multisite', 'admin-network', 'admin-page', 'admin-plugins', 'admin-post', 'admin-settings', 'admin-site', 'admin-tools',
            'admin-users', 'airplane', 'album', 'align-center', 'align-left', 'align-none', 'align-pull-left', 'align-pull-right',
            'align-right', 'amazon', 'analytics', 'archive', 'arrow-down', 'arrow-down-alt', 'arrow-down-alt2', 'arrow-left',
            'arrow-left-alt', 'arrow-left-alt2', 'arrow-right', 'arrow-right-alt', 'arrow-right-alt2', 'arrow-up', 'arrow-up-alt', 'arrow-up-alt2',
            'art', 'awards', 'backup', 'bank', 'beer', 'bell', 'block-default', 'book',
            'book-alt', 'building', 'businessman', 'calculator', 'calendar', 'calendar-alt', 'camera', 'car',
            'carrot', 'cart', 'category', 'chart-area', 'chart-bar', 'chart-line', 'chart-pie', 'clipboard',
            'clock', 'cloud', 'cloud-saved', 'cloud-upload', 'coffee', 'columns', 'controls-back', 'controls-forward',
            'controls-pause', 'controls-play', 'controls-repeat', 'controls-skipback', 'controls-skipforward', 'controls-volumeoff', 'controls-volumeon', 'cover-image',
            'dashboard', 'database', 'database-add', 'database-export', 'database-import', 'database-remove', 'database-view', 'desktop',
            'dismiss', 'download', 'drumstick', 'edit', 'edit-page', 'editor-aligncenter', 'editor-alignleft', 'editor-alignright',
            'editor-bold', 'editor-break', 'editor-code', 'editor-contract', 'editor-customchar', 'editor-expand', 'editor-help', 'editor-indent',
            'editor-insertmore', 'editor-italic', 'editor-justify', 'editor-kitchensink', 'editor-ol', 'editor-outdent', 'editor-paragraph', 'editor-paste-text',
            'editor-paste-word', 'editor-quote', 'editor-removeformatting', 'editor-rtl', 'editor-spellcheck', 'editor-strikethrough', 'editor-table', 'editor-textcolor',
            'editor-ul', 'editor-underline', 'editor-unlink', 'editor-video', 'email', 'email-alt', 'embed-audio', 'embed-generic',
            'embed-photo', 'embed-post', 'embed-video', 'excerpt-view', 'exit', 'external', 'facebook', 'facebook-alt',
            'feedback', 'filter', 'flag', 'food', 'format-aside', 'format-audio', 'format-chat', 'format-gallery',
            'format-image', 'format-links', 'format-quote', 'format-standard', 'format-status', 'format-video', 'forms', 'fullscreen-alt',
            'fullscreen-exit-alt', 'google', 'googleplus', 'grid-view', 'groups', 'hammer', 'heart', 'hidden',
            'hourglass', 'html', 'id', 'id-alt', 'image-crop', 'image-filter', 'image-flip-horizontal', 'image-flip-vertical',
            'image-rotate', 'image-rotate-left', 'image-rotate-right', 'images-alt', 'images-alt2', 'index-card', 'info', 'info-outline',
            'insert', 'insert-after', 'insert-before', 'layout', 'leftright', 'lightbulb', 'linkedin', 'list-view',
            'location', 'location-alt', 'lock', 'marker', 'media-archive', 'media-audio', 'media-code', 'media-default',
            'media-document', 'media-interactive', 'media-spreadsheet', 'media-text', 'media-video', 'megaphone', 'menu', 'microphone',
            'migrate', 'minus', 'money', 'money-alt', 'nametag', 'networking', 'no', 'no-alt',
            'open-folder', 'palmtree', 'pdf', 'performance', 'pets', 'phone', 'pinterest', 'playlist-audio',
            'playlist-video', 'plus', 'plus-alt', 'plus-alt2', 'podio', 'portfolio', 'post-status', 'post-trash',
            'pressthis', 'printer', 'privacy', 'products', 'randomize', 'reddit', 'redo', 'remove',
            'rss', 'saved', 'schedule', 'screenoptions', 'search', 'share', 'share-alt', 'share-alt2',
            'share1', 'shield', 'shield-alt', 'shortcode', 'slides', 'smartphone', 'smiley', 'sort',
            'sos', 'spotify', 'star-empty', 'star-filled', 'star-half', 'sticky', 'store', 'superhero',
            'superhero-alt', 'table-col-after', 'table-col-before', 'table-col-delete', 'table-row-after', 'table-row-before', 'table-row-delete', 'tablet',
            'tag', 'tagcloud', 'testimonial', 'text', 'thumbs-down', 'thumbs-up', 'tickets', 'tickets-alt',
            'translation', 'trash', 'twitch', 'twitter', 'undo', 'universal-access', 'universal-access-alt', 'unlock',
            'update', 'upload', 'vault', 'video-alt', 'video-alt2', 'video-alt3', 'visibility', 'warning',
            'welcome-add-page', 'welcome-comments', 'welcome-edit-page', 'welcome-learn-more', 'welcome-view-site', 'welcome-widgets-menus', 'welcome-write-blog', 'whatsapp',
            'wordpress', 'wordpress-alt', 'xing', 'yes', 'youtube',
        ];

        $dashicons = apply_filters( 'siteperspectives_dashicons', $dashicons );
        sort( $dashicons );
        $cache = array_values( array_unique( $dashicons ) );

        return $cache;
    } // End get_dashicons()


    /**
     * Render a tooltip icon with hover text, matching Admin Help Docs' pattern.
     *
     * @param string $text
     * @return void
     */
    public static function tooltip( string $text ) : void {
        if ( '' === $text ) {
            return;
        }
        ?>
        <span class="siteperspectives-tooltip" tabindex="0">
            <span class="dashicons dashicons-editor-help" aria-hidden="true"></span>
            <span class="siteperspectives-tooltip-text" role="tooltip"><?php echo wp_kses_post( $text ); ?></span>
        </span>
        <?php
    } // End tooltip()

} // End class Helpers
