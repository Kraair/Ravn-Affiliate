<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Ravn_TOC {

    public function __construct() {
        add_filter( 'the_content', array( $this, 'maybe_auto_insert' ), 12 );
    }

    public static function render_shortcode( $atts ) {
        $atts = shortcode_atts( array(
            'min_headings' => 3,
            'max_depth'    => 3,
            'collapsed'    => 0,
            'title'        => 'Inhoudsopgave',
        ), $atts, 'ravn_toc' );

        $post = get_post();
        if ( ! $post ) return '';

        return self::build_toc( $post->post_content, $atts );
    }

    public function maybe_auto_insert( $content ) {
        if ( ! is_singular() ) return $content;
        // Alleen als shortcode aanwezig is
        if ( has_shortcode( $content, 'ravn_toc' ) ) {
            return $content;
        }
        return $content;
    }

    public static function build_toc( $content, $atts = array() ) {
        $atts = wp_parse_args( $atts, array(
            'min_headings' => 3,
            'max_depth'    => 3,
            'collapsed'    => 0,
            'title'        => 'Inhoudsopgave',
        ) );

        $min_depth = 2;
        $max_depth = min( 6, max( 1, intval( $atts['max_depth'] ) ) + 1 );

        // Zoek alle headings
        preg_match_all( '/<h([' . $min_depth . '-' . $max_depth . '])([^>]*)>(.*?)<\/h\1>/is', $content, $matches, PREG_SET_ORDER );

        if ( count( $matches ) < intval( $atts['min_headings'] ) ) return '';

        $items      = array();
        $slug_count = array();

        foreach ( $matches as $m ) {
            $level   = intval( $m[1] );
            $text    = wp_strip_all_tags( $m[3] );
            $slug    = sanitize_title( $text );

            // Dubbele slugs voorkomen
            if ( isset( $slug_count[ $slug ] ) ) {
                $slug_count[ $slug ]++;
                $slug .= '-' . $slug_count[ $slug ];
            } else {
                $slug_count[ $slug ] = 0;
            }

            $items[] = array(
                'level' => $level,
                'text'  => $text,
                'slug'  => $slug,
            );
        }

        if ( empty( $items ) ) return '';

        $collapsed   = ! empty( $atts['collapsed'] );
        $toggle_text = $collapsed ? 'tonen' : 'verbergen';
        $hidden_attr = $collapsed ? ' hidden' : '';

        $html  = '<nav class="ravn-toc" aria-label="Inhoudsopgave">';
        $html .= '<div class="ravn-toc-title">';
        $html .= '<span>' . esc_html( $atts['title'] ) . '</span>';
        $html .= '<button type="button" class="ravn-toc-toggle" data-collapsed="' . ( $collapsed ? '1' : '0' ) . '">' . esc_html( $toggle_text ) . '</button>';
        $html .= '</div>';
        $html .= '<div class="ravn-toc-content"' . $hidden_attr . '>';
        $html .= self::build_nested_list( $items );
        $html .= '</div></nav>';

        return $html;
    }

    private static function build_nested_list( $items ) {
        if ( empty( $items ) ) return '';

        $html        = '';
        $min_level   = min( array_column( $items, 'level' ) );
        $stack       = array(); // stack van open niveaus
        $current     = $min_level;

        $html .= '<ol>';

        foreach ( $items as $item ) {
            $level = $item['level'];

            if ( $level > $current ) {
                $html .= '<ol>';
                $stack[] = $current;
                $current = $level;
            } elseif ( $level < $current ) {
                while ( ! empty( $stack ) && end( $stack ) >= $level ) {
                    $html .= '</ol></li>';
                    array_pop( $stack );
                }
                $current = $level;
            } else {
                if ( $html !== '<ol>' ) $html .= '</li>';
            }

            $html .= '<li><a href="#' . esc_attr( $item['slug'] ) . '">' . esc_html( $item['text'] ) . '</a>';
        }

        // Sluit openstaande tags
        while ( ! empty( $stack ) ) {
            $html .= '</ol></li>';
            array_pop( $stack );
        }
        $html .= '</li></ol>';

        return $html;
    }
}
