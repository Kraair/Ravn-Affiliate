<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Registreert alle Gutenberg-blokken van de plugin bij WordPress.
 *
 * Vóór deze klasse bestonden de block.json-bestanden en editor-scripts al
 * in de blocks/-map, maar werden ze nergens met register_block_type()
 * aangemeld — daardoor waren ze nooit zichtbaar in de blokkeneditor en
 * deed ServerSideRender niets (geen render_callback gekoppeld).
 */
class Ravn_Blocks {

    public function __construct() {
        add_action( 'init',                 array( $this, 'register_blocks' ) );
        add_action( 'block_categories_all',  array( $this, 'register_category' ), 10, 2 );
        add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_globals' ) );
    }

    /**
     * Voegt de eigen blokcategorie toe zodat de blokken niet tussen
     * "Widgets" of "Tekst" belanden, maar in hun eigen groep staan.
     */
    public function register_category( $categories, $editor_context ) {
        return array_merge(
            array(
                array(
                    'slug'  => 'ravn-affiliate',
                    'title' => __( 'Ravn Affiliate', 'ravn-affiliate' ),
                    'icon'  => 'cart',
                ),
            ),
            $categories
        );
    }

    /**
     * Maakt de REST-URL en nonce beschikbaar aan de blok-editor-scripts
     * (gebruikt door de zoekfunctie in de product-picker en product-list
     * blokken via window.ravnBlock).
     */
    public function enqueue_editor_globals() {
        wp_add_inline_script(
            'wp-blocks',
            'window.ravnBlock = ' . wp_json_encode( array(
                'rest_url' => esc_url_raw( rest_url() ),
                'nonce'    => wp_create_nonce( 'wp_rest' ),
            ) ) . ';',
            'before'
        );
    }

    public function register_blocks() {
        $opts = Ravn_Options::get_all();

        // Losse producttegel: zoeken + één product tonen.
        register_block_type( RAVN_PLUGIN_DIR . 'blocks/ravn-product-picker', array(
            'render_callback' => array( $this, 'render_product_picker' ),
        ) );

        // Meerdere producten: lijst of carrousel, met keuzepaneel.
        register_block_type( RAVN_PLUGIN_DIR . 'blocks/ravn-product-list', array(
            'render_callback' => array( $this, 'render_product_list' ),
        ) );

        // FAQ-accordion van het huidige bericht. Alleen registreren als de
        // module aanstaat, zodat het blok ook uit de blokkenkiezer verdwijnt
        // wanneer je de module uitzet (consistent met hoe Ravn_FAQ zelf wordt
        // geladen in het hoofdbestand).
        if ( ! empty( $opts['module_faq'] ) ) {
            register_block_type( RAVN_PLUGIN_DIR . 'blocks/ravn-faq-block', array(
                'render_callback' => array( $this, 'render_faq' ),
            ) );
        }

        // Automatische inhoudsopgave — idem, gekoppeld aan module_toc.
        if ( ! empty( $opts['module_toc'] ) ) {
            register_block_type( RAVN_PLUGIN_DIR . 'blocks/ravn-toc-block', array(
                'render_callback' => array( $this, 'render_toc' ),
            ) );
        }
    }

    /**
     * Render callback voor ravn-affiliate/product-picker.
     * Hergebruikt de bestaande [ravn_product]-shortcode-logica zodat er geen
     * dubbele rendering-code hoeft te bestaan.
     */
    public function render_product_picker( $attributes ) {
        $product_id = isset( $attributes['productId'] ) ? intval( $attributes['productId'] ) : 0;
        if ( ! $product_id ) return '';

        $atts = array(
            'id'           => $product_id,
            'hide_image'   => ! empty( $attributes['hideImage'] )       ? 1 : 0,
            'hide_price'   => ! empty( $attributes['hidePrice'] )       ? 1 : 0,
            'hide_desc'    => ! empty( $attributes['hideDescription'] ) ? 1 : 0,
            'hide_sellers' => ! empty( $attributes['hideSellers'] )     ? 1 : 0,
            'hide_rating'  => ! empty( $attributes['hideRating'] )      ? 1 : 0,
            'sub_id'       => isset( $attributes['subId'] ) ? sanitize_text_field( $attributes['subId'] ) : '',
        );

        return do_shortcode( $this->build_shortcode( 'ravn_product', $atts ) );
    }

    /**
     * Render callback voor ravn-affiliate/product-list.
     * Bouwt op basis van displayMode de [ravn_list]- of [ravn_carousel]-
     * shortcode op en laat die de daadwerkelijke HTML genereren, zodat
     * lijst- en carrouselweergave altijd hetzelfde gedrag hebben als de
     * shortcodes zelf (inclusief alle styling-instellingen).
     */
    public function render_product_list( $attributes ) {
        $ids = isset( $attributes['productIds'] ) && is_array( $attributes['productIds'] )
            ? array_filter( array_map( 'intval', $attributes['productIds'] ) )
            : array();

        if ( empty( $ids ) ) return '';

        $common = array(
            'ids'          => implode( ',', $ids ),
            'hide_image'   => ! empty( $attributes['hideImage'] )       ? 1 : 0,
            'hide_price'   => ! empty( $attributes['hidePrice'] )       ? 1 : 0,
            'hide_desc'    => ! empty( $attributes['hideDescription'] ) ? 1 : 0,
            'hide_sellers' => ! empty( $attributes['hideSellers'] )     ? 1 : 0,
            'hide_rating'  => ! empty( $attributes['hideRating'] )      ? 1 : 0,
            'sub_id'       => isset( $attributes['subId'] ) ? sanitize_text_field( $attributes['subId'] ) : '',
        );

        $mode = isset( $attributes['displayMode'] ) ? $attributes['displayMode'] : 'single';

        // Eén product: gebruik [ravn_product], die een enkelvoudig "id"-attribuut
        // verwacht in plaats van de "ids"-lijst van de andere twee modi.
        if ( 'single' === $mode ) {
            $single = $common;
            unset( $single['ids'] );
            $single['id'] = reset( $ids );
            return do_shortcode( $this->build_shortcode( 'ravn_product', $single ) );
        }

        if ( 'carousel' === $mode ) {
            $atts = array_merge( $common, array(
                'columns'        => isset( $attributes['columns'] ) ? intval( $attributes['columns'] ) : 3,
                'arrows'         => ! empty( $attributes['arrows'] )   ? 1 : 0,
                'dots'           => ! empty( $attributes['dots'] )     ? 1 : 0,
                'infinite'       => ! empty( $attributes['infinite'] ) ? 1 : 0,
                'autoplay'       => ! empty( $attributes['autoplay'] ) ? 1 : 0,
            ) );
            return do_shortcode( $this->build_shortcode( 'ravn_carousel', $atts ) );
        }

        $atts = array_merge( $common, array(
            'columns' => isset( $attributes['columns'] ) ? intval( $attributes['columns'] ) : 3,
        ) );
        return do_shortcode( $this->build_shortcode( 'ravn_list', $atts ) );
    }

    public function render_faq( $attributes ) {
        $atts = array(
            'multi_open' => ! empty( $attributes['multiOpen'] ) ? 1 : 0,
        );
        return do_shortcode( $this->build_shortcode( 'ravn_faq', $atts ) );
    }

    public function render_toc( $attributes ) {
        $atts = array(
            'min_headings' => 3,
            'max_depth'    => isset( $attributes['endLevel'] ) ? intval( $attributes['endLevel'] ) - 1 : 3,
            'collapsed'    => ! empty( $attributes['collapsed'] ) ? 1 : 0,
        );
        return do_shortcode( $this->build_shortcode( 'ravn_toc', $atts ) );
    }

    /**
     * Bouwt een shortcode-string op uit een attributen-array, met correcte
     * escaping van waarden (voorkomt problemen bij titels/sub-ID's met
     * aanhalingstekens).
     */
    private function build_shortcode( $tag, $atts ) {
        $pairs = array();
        foreach ( $atts as $key => $value ) {
            // Lege strings en null overslaan; een letterlijke 0 blijft wel
            // behouden (bijv. hide_price="0"), want dat is betekenisvol.
            if ( '' === $value || null === $value ) continue;
            $pairs[] = $key . '="' . esc_attr( str_replace( '"', '&quot;', (string) $value ) ) . '"';
        }

        if ( empty( $pairs ) ) {
            return '[' . $tag . ']';
        }

        return '[' . $tag . ' ' . implode( ' ', $pairs ) . ']';
    }
}
