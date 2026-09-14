<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Ravn_Shortcodes {

    private $assets_enqueued = false;

    public function __construct() {
        add_shortcode( 'ravn_product',  array( $this, 'shortcode_product' ) );
        add_shortcode( 'ravn_list',     array( $this, 'shortcode_list' ) );
        add_shortcode( 'ravn_carousel', array( $this, 'shortcode_carousel' ) );
        add_shortcode( 'ravn_link',     array( $this, 'shortcode_link' ) );
        add_shortcode( 'ravn_faq',      array( $this, 'shortcode_faq' ) );
        add_shortcode( 'ravn_toc',      array( $this, 'shortcode_toc' ) );

        // AJAX voor click tracking (public)
        add_action( 'wp_ajax_ravn_affiliate_track_click',        array( $this, 'ajax_track_click' ) );
        add_action( 'wp_ajax_nopriv_ravn_track_click', array( $this, 'ajax_track_click' ) );

        // REST endpoint voor Gutenberg blok zoeken
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
    }

    // ─── Assets ─────────────────────────────────────────────────────────────

    private function enqueue_assets() {
        if ( $this->assets_enqueued ) return;
        $this->assets_enqueued = true;

        wp_enqueue_style(
            'ravn-public',
            RAVN_PLUGIN_URL . 'public/css/ravn-public.css',
            array(),
            RAVN_VERSION
        );

        // Dynamische CSS (styling opties)
        wp_add_inline_style( 'ravn-public', Ravn_Styling::generate_css() );

        $opts = Ravn_Options::get_all();
        wp_enqueue_script(
            'ravn-public',
            RAVN_PLUGIN_URL . 'public/js/ravn-public.js',
            array( 'jquery' ),
            RAVN_VERSION,
            true
        );

        wp_localize_script( 'ravn-public', 'ravnVars', array(
            'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
            'nonce'            => wp_create_nonce( 'ravn_affiliate_public_nonce' ),
            'trackingEnabled'  => ! empty( $opts['stats_link_tracking'] ) ? 1 : 0,
            'newWindow'        => ! empty( $opts['new_window'] ) ? 1 : 0,
            'carouselArrows'   => ! empty( $opts['carousel_arrows'] ) ? 1 : 0,
            'carouselDots'     => ! empty( $opts['carousel_dots'] ) ? 1 : 0,
            'carouselInfinite' => ! empty( $opts['carousel_infinite'] ) ? 1 : 0,
            'carouselAutoplay' => ! empty( $opts['carousel_autoplay'] ) ? 1 : 0,
            'carouselSpeed'    => intval( $opts['carousel_speed'] ),
            'carouselAutoplaySpeed' => intval( $opts['carousel_autoplay_speed'] ),
            'popupCloseColor'  => esc_js( $opts['style_popup_close_color'] ),
        ) );
    }

    // ─── [ravn_product] ───────────────────────────────────────────────────────

    public function shortcode_product( $atts, $content = '' ) {
        $atts = shortcode_atts( array(
            'id'           => 0,
            'hide_price'   => 0,
            'hide_image'   => 0,
            'hide_title'   => 0,
            'hide_desc'    => 0,
            'hide_sellers' => 0,
            'hide_rating'  => 0,
            'sub_id'       => '',
        ), $atts, 'ravn_product' );

        $id = intval( $atts['id'] );
        if ( ! $id ) return '';

        $product = Ravn_Database::get_product( $id );
        if ( ! $product ) return '';

        $this->enqueue_assets();
        $atts['content'] = $content;

        return '<div class="ravn-wrap">' . Ravn_Product::render_product_box( $product, $atts ) . '</div>';
    }

    // ─── [ravn_list] ──────────────────────────────────────────────────────────

    public function shortcode_list( $atts, $content = '' ) {
        $atts = shortcode_atts( array(
            'ids'          => '',
            'columns'      => 1,
            'hide_price'   => 0,
            'hide_image'   => 0,
            'hide_title'   => 0,
            'hide_desc'    => 0,
            'hide_sellers' => 0,
            'hide_rating'  => 0,
            'sub_id'       => '',
        ), $atts, 'ravn_list' );

        $ids = array_filter( array_map( 'intval', explode( ',', $atts['ids'] ) ) );
        if ( empty( $ids ) ) return '';

        $this->enqueue_assets();
        $cols    = max( 1, intval( $atts['columns'] ) );
        $class   = $cols > 1 ? ' ravn-columns ravn-columns-' . $cols : '';
        $html    = '<div class="ravn-list' . $class . '">';

        foreach ( $ids as $id ) {
            $product = Ravn_Database::get_product( $id );
            if ( ! $product ) continue;
            $html .= Ravn_Product::render_product_box( $product, $atts );
        }
        $html .= '</div>';
        return $html;
    }

    // ─── [ravn_carousel] ──────────────────────────────────────────────────────

    public function shortcode_carousel( $atts ) {
        $opts = Ravn_Options::get_all();
        $atts = shortcode_atts( array(
            'ids'          => '',
            'hide_price'   => 0,
            'hide_image'   => 0,
            'hide_title'   => 0,
            'hide_desc'    => 0,
            'hide_sellers' => 0,
            'hide_rating'  => 0,
            'sub_id'       => '',
            'arrows'       => $opts['carousel_arrows']   ? '1' : '0',
            'dots'         => $opts['carousel_dots']     ? '1' : '0',
            'infinite'     => $opts['carousel_infinite'] ? '1' : '0',
            'autoplay'     => $opts['carousel_autoplay'] ? '1' : '0',
            'speed'        => $opts['carousel_speed'],
            'autoplay_speed' => $opts['carousel_autoplay_speed'],
            'columns'      => 3,
        ), $atts, 'ravn_carousel' );

        $ids = array_filter( array_map( 'intval', explode( ',', $atts['ids'] ) ) );
        if ( empty( $ids ) ) return '';

        $this->enqueue_assets();

        $show_arrows = ! empty( $atts['arrows'] );
        $show_dots   = ! empty( $atts['dots'] );

        // De frontend-JS (initCarousels in ravn-public.js) verwacht deze exacte
        // structuur: .ravn-carousel-wrap als container met data-* attributen,
        // .ravn-carousel-track met de slides erin, en losse knoppen/dots-divs
        // ernaast. De eerdere versie gaf .ravn-carousel/.ravn-carousel-item terug,
        // wat door geen enkele JS/CSS-selector werd herkend.
        $html = '<div class="ravn-carousel-wrap"'
            . ' data-infinite="'       . ( ! empty( $atts['infinite'] ) ? '1' : '0' ) . '"'
            . ' data-autoplay="'      . ( ! empty( $atts['autoplay'] ) ? '1' : '0' ) . '"'
            . ' data-autoplay-speed="' . intval( $atts['autoplay_speed'] ) . '"'
            . ' data-slide-speed="'    . intval( $atts['speed'] ) . '"'
            . ' data-per-view="'       . max( 1, intval( $atts['columns'] ) ) . '"'
            . '>';

        $html .= '<div class="ravn-carousel-track">';
        foreach ( $ids as $id ) {
            $product = Ravn_Database::get_product( $id );
            if ( ! $product ) continue;
            $html .= Ravn_Product::render_product_box( $product, $atts );
        }
        $html .= '</div>'; // .ravn-carousel-track

        if ( $show_arrows ) {
            $html .= '<button type="button" class="ravn-carousel-arrow prev" aria-label="Vorige">&#8249;</button>';
            $html .= '<button type="button" class="ravn-carousel-arrow next" aria-label="Volgende">&#8250;</button>';
        }
        if ( $show_dots ) {
            $html .= '<div class="ravn-carousel-dots"></div>'; // wordt door de JS gevuld
        }

        $html .= '</div>'; // .ravn-carousel-wrap
        return $html;
    }

    // ─── [ravn_link] ──────────────────────────────────────────────────────────

    public function shortcode_link( $atts, $content = '' ) {
        $atts = shortcode_atts( array(
            'offer_id' => 0,
            'sub_id'   => '',
            'text'     => '',
        ), $atts, 'ravn_link' );

        $offer_id = intval( $atts['offer_id'] );
        if ( ! $offer_id ) return $content;

        $offer = Ravn_Database::get_offer( $offer_id );
        if ( ! $offer ) return $content;

        $this->enqueue_assets();
        $url    = Ravn_Product::build_url( $offer->affiliate_url, $offer->sub_id, $atts['sub_id'], $offer->network );
        $text   = $atts['text'] ?: ( $content ?: $offer->seller_name );
        $target = Ravn_Product::target_attr();
        $rel    = Ravn_Product::rel_attr();

        return '<a href="' . esc_url( $url ) . '"' . $target . $rel . ' class="ravn-link" data-offer-id="' . $offer_id . '" data-product-id="' . intval( $offer->product_id ) . '">' . esc_html( $text ) . '</a>';
    }

    // ─── [ravn_faq] ───────────────────────────────────────────────────────────

    public function shortcode_faq( $atts ) {
        // Respecteer de module-instelling: staat de FAQ-module uit, dan hoort
        // ook de shortcode niets te renderen (consistent met het blok, dat
        // dan niet eens geregistreerd wordt).
        if ( empty( Ravn_Options::get( 'module_faq' ) ) ) return '';

        $atts = shortcode_atts( array( 'post_id' => 0, 'multi_open' => 0 ), $atts, 'ravn_faq' );
        $post_id = $atts['post_id'] ? intval( $atts['post_id'] ) : get_the_ID();
        if ( ! $post_id ) return '';

        $items = Ravn_Database::get_faq_by_post( $post_id );
        if ( empty( $items ) ) return '';

        $this->enqueue_assets();

        $multi = ! empty( $atts['multi_open'] ) ? '1' : '0';
        $html  = '<div class="ravn-faq" data-multi-open="' . esc_attr( $multi ) . '">';
        foreach ( $items as $item ) {
            $html .= '<div class="ravn-faq-item">';
            $html .= '<button class="ravn-faq-question" aria-expanded="false">' . esc_html( $item->question ) . '<span class="ravn-faq-icon">&#x25BC;</span></button>';
            $html .= '<div class="ravn-faq-answer" hidden>' . wp_kses_post( $item->answer ) . '</div>';
            $html .= '</div>';
        }
        $html .= '</div>';
        return $html;
    }

    // ─── [ravn_toc] ───────────────────────────────────────────────────────────

    public function shortcode_toc( $atts ) {
        // Respecteer de module-instelling (zie toelichting bij shortcode_faq).
        // De class_exists()-check alleen volstaat niet, omdat de autoloader
        // Ravn_TOC ook laadt wanneer de module is uitgeschakeld.
        if ( empty( Ravn_Options::get( 'module_toc' ) ) ) return '';

        if ( class_exists( 'Ravn_TOC' ) ) {
            return Ravn_TOC::render_shortcode( $atts );
        }
        return '';
    }

    // ─── AJAX click tracking ────────────────────────────────────────────────

    public function ajax_track_click() {
        check_ajax_referer( 'ravn_affiliate_public_nonce', 'nonce' );
        $opts = Ravn_Options::get_all();
        if ( empty( $opts['stats_link_tracking'] ) ) {
            wp_send_json_success();
        }
        $offer_id   = intval( $_POST['offer_id'] ?? 0 );
        $product_id = intval( $_POST['product_id'] ?? 0 );
        $page_url   = sanitize_text_field( wp_unslash( $_POST['page_url'] ?? '' ) );
        $sub_id     = sanitize_text_field( wp_unslash( $_POST['sub_id'] ?? '' ) );

        if ( $offer_id && $product_id ) {
            Ravn_Database::log_click( $offer_id, $product_id, $page_url, $sub_id );
        }
        wp_send_json_success();
    }

    // ─── REST API voor Gutenberg ─────────────────────────────────────────────

    public function register_rest_routes() {
        register_rest_route( 'ravn-affiliate/v1', '/search', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'rest_search_products' ),
            'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
            'args'                => array(
                'query' => array( 'sanitize_callback' => 'sanitize_text_field' ),
            ),
        ) );

        register_rest_route( 'ravn-affiliate/v1', '/product/(?P<id>\d+)', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'rest_get_product' ),
            'permission_callback' => '__return_true',
            'args'                => array(
                'id' => array( 'validate_callback' => function( $v ) { return is_numeric( $v ); } ),
            ),
        ) );
    }

    public function rest_search_products( $request ) {
        $query    = $request->get_param( 'query' );
        $products = Ravn_Database::get_products( array( 'search' => $query, 'per_page' => 20 ) );
        $results  = array();
        foreach ( $products as $p ) {
            $results[] = array(
                'id'    => $p->id,
                'title' => $p->title,
                'image' => $p->image_url,
            );
        }
        return rest_ensure_response( $results );
    }

    public function rest_get_product( $request ) {
        $product = Ravn_Database::get_product( intval( $request['id'] ) );
        if ( ! $product ) return new WP_Error( 'not_found', 'Product niet gevonden', array( 'status' => 404 ) );
        return rest_ensure_response( $product );
    }
}
