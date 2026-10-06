<?php
/**
 * COLORS
 *
 * Predefined themes and color fields for the visitor-facing shortcode,
 * following the theme/custom pattern used by Admin Help Docs.
 */

namespace PluginRx\SitePerspectives;

if ( ! defined( 'ABSPATH' ) ) exit;


class Colors {

    /**
     * Default color keys, labels and values (the Classic theme).
     *
     * @return array
     */
    public static function defaults() : array {
        return [
            'card_bg'    => [ 'color' => '#FFFFFF', 'label' => __( 'Card Background', 'site-perspectives' ) ],
            'border'     => [ 'color' => '#D0D5DD', 'label' => __( 'Card Border', 'site-perspectives' ) ],
            'title'      => [ 'color' => '#1D2327', 'label' => __( 'Heading Text', 'site-perspectives' ) ],
            'font'       => [ 'color' => '#475467', 'label' => __( 'Body Text', 'site-perspectives' ) ],
            'link'       => [ 'color' => '#2F76DB', 'label' => __( 'Link', 'site-perspectives' ) ],
            'link_hover' => [ 'color' => '#1B4F9F', 'label' => __( 'Link Hover', 'site-perspectives' ) ],
            'accent'     => [ 'color' => '#306DC4', 'label' => __( 'Accent and Connector Lines', 'site-perspectives' ) ],
            'icon'       => [ 'color' => '#306DC4', 'label' => __( 'Icon Color', 'site-perspectives' ) ],
        ];
    } // End defaults()


    /**
     * Predefined themes.
     *
     * @return array
     */
    public static function themes() : array {
        $classic = [];

        foreach ( self::defaults() as $key => $data ) {
            $classic[ $key ] = $data[ 'color' ];
        }

        $themes = [
            'classic'   => [
                'label'  => __( 'Classic', 'site-perspectives' ),
                'colors' => $classic,
            ],
            'corporate' => [
                'label'  => __( 'Corporate', 'site-perspectives' ),
                'colors' => [
                    'card_bg' => '#FFFFFF', 'border' => '#9CCBD2', 'title' => '#093B60',
                    'font' => '#023260', 'link' => '#2271B1', 'link_hover' => '#093B60', 'accent' => '#C99A2C',
                ],
            ],
            'sunrise'   => [
                'label'  => __( 'Sunrise', 'site-perspectives' ),
                'colors' => [
                    'card_bg' => '#FFFFFF', 'border' => '#FFCCBC', 'title' => '#4E342E',
                    'font' => '#5D4037', 'link' => '#E76F51', 'link_hover' => '#BF4A2E', 'accent' => '#FF7043',
                ],
            ],
            'ocean'     => [
                'label'  => __( 'Ocean', 'site-perspectives' ),
                'colors' => [
                    'card_bg' => '#FFFFFF', 'border' => '#B3E5FC', 'title' => '#01579B',
                    'font' => '#0277BD', 'link' => '#039BE5', 'link_hover' => '#01579B', 'accent' => '#0288D1',
                ],
            ],
            'forest'    => [
                'label'  => __( 'Forest', 'site-perspectives' ),
                'colors' => [
                    'card_bg' => '#FFFFFF', 'border' => '#A5D6A7', 'title' => '#1B5E20',
                    'font' => '#2E7D32', 'link' => '#43A047', 'link_hover' => '#1B5E20', 'accent' => '#2E7D32',
                ],
            ],
            'slate'     => [
                'label'  => __( 'Slate', 'site-perspectives' ),
                'colors' => [
                    'card_bg' => '#F1F5F9', 'border' => '#CBD5E1', 'title' => '#0F172A',
                    'font' => '#475569', 'link' => '#2563EB', 'link_hover' => '#0F172A', 'accent' => '#334155',
                ],
            ],
            'mono'      => [
                'label'  => __( 'Monochrome', 'site-perspectives' ),
                'colors' => [
                    'card_bg' => '#FFFFFF', 'border' => '#CFCFCF', 'title' => '#111111',
                    'font' => '#555555', 'link' => '#111111', 'link_hover' => '#555555', 'accent' => '#333333',
                ],
            ],
        ];

        $themes = [
            'frontend' => [
                'label'  => __( 'Same as Frontend Tab', 'site-perspectives' ),
                'colors' => self::frontend_colors(),
            ],
        ] + $themes;

        return apply_filters( 'siteperspectives_color_themes', $themes );
    } // End themes()


    /**
     * Colors matching the Frontend tab, which follow the admin colors (including those inherited from Admin Help Docs).
     *
     * @return array
     */
    public static function frontend_colors() : array {
        $admin = Menu::get_theme_colors();
        $header = Helpers::hex( $admin[ 'header-bg' ] ?? '', '#1D2327' );
        $accent = Helpers::hex( $admin[ 'accent' ] ?? '', '#0783BE' );
        $button = Helpers::hex( $admin[ 'button' ] ?? '', $accent );
        $hover = Helpers::hex( $admin[ 'button-hover' ] ?? '', '#066998' );

        return [
            'card_bg'    => '#FFFFFF',
            'border'     => '#EAECF0',
            'title'      => $header,
            'font'       => '#667085',
            'link'       => $button,
            'link_hover' => $hover,
            'accent'     => $accent,
            'icon'       => $accent,
        ];
    } // End frontend_colors()


    /**
     * Resolve the colors for a theme, with custom overrides applied when the theme is "custom".
     *
     * @param string $theme  Theme key, or 'custom'.
     * @param array  $custom Custom color values keyed by color key.
     * @return array Associative array of key => hex.
     */
    public static function resolve( string $theme, array $custom = [] ) : array {
        $themes = self::themes();
        $resolved = $themes[ 'classic' ][ 'colors' ];

        if ( isset( $themes[ $theme ] ) ) {
            $resolved = array_merge( $resolved, $themes[ $theme ][ 'colors' ] );

            if ( ! isset( $themes[ $theme ][ 'colors' ][ 'icon' ] ) ) {
                $resolved[ 'icon' ] = $resolved[ 'accent' ];
            }
        } elseif ( 'custom' === $theme ) {
            foreach ( $resolved as $key => $default ) {
                $resolved[ $key ] = Helpers::hex( $custom[ $key ] ?? '', $default );
            }
        }

        return $resolved;
    } // End resolve()

} // End class Colors
