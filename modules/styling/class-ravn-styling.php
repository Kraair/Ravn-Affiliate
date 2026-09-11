<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Ravn_Styling {

    public function __construct() {
        // Styling klasse hoeft niets te doen bij init
    }

    public static function generate_css() {
        $o = Ravn_Options::get_all();

        // Helper: kleur of lege string
        $c = function( $val ) { return ! empty( $val ) ? $val : ''; };

        // Font grootte classes
        $size_map = array( 'small' => '0.8em', 'medium' => '1em', 'large' => '1.2em' );
        $s = function( $key ) use ( $o, $size_map ) {
            $v = isset( $o[ $key ] ) ? $o[ $key ] : 'medium';
            return isset( $size_map[ $v ] ) ? $size_map[ $v ] : '1em';
        };

        $px = function( $key, $default = '0' ) use ( $o ) {
            return ( isset( $o[ $key ] ) && $o[ $key ] !== '' ) ? intval( $o[ $key ] ) . 'px' : $default . 'px';
        };

        // Border positie
        $border_side = array(
            'all'    => 'border',
            'top'    => 'border-top',
            'right'  => 'border-right',
            'bottom' => 'border-bottom',
            'left'   => 'border-left',
        );
        $border_prop = isset( $border_side[ $o['style_border_position'] ] ) ? $border_side[ $o['style_border_position'] ] : 'border';

        $shadow      = ! empty( $o['style_shadow'] )      ? 'box-shadow: 0 2px 8px rgba(0,0,0,.1);' : '';
        $label_shadow = ! empty( $o['style_label_shadow'] ) ? 'box-shadow: 0 2px 4px rgba(0,0,0,.2);' : '';
        $seller_shadow = ! empty( $o['style_seller_shadow'] ) ? 'box-shadow: 0 1px 3px rgba(0,0,0,.15);' : '';
        $cta_shadow   = ! empty( $o['style_cta_shadow'] )  ? 'box-shadow: 0 2px 6px rgba(0,0,0,.2);' : '';
        $show_all_shadow = ! empty( $o['style_show_all_shadow'] ) ? 'box-shadow: 0 1px 3px rgba(0,0,0,.15);' : '';

        // Primaire kleur cascade
        $primary = ''; // via CSS custom property

        $css = "
/* ─── Ravn Affiliate Plugin CSS ─────────────────────────────────── */
:root {
    --ravn-primary: var(--wp--preset--color--primary, var(--gp-color-1, var(--contrast, #e74c3c)));
    --ravn-star-active: {$o['style_star_active_color']};
    --ravn-star-inactive: {$o['style_star_inactive_color']};
}

/* ─── Productbox ── */
.ravn-product-box {
    padding: {$px('style_padding')};
    margin-top: {$px('style_margin_top')};
    background-color: " . ( $c($o['style_bg_color']) ?: '#fff' ) . ";
    {$border_prop}: {$px('style_border_width')} solid " . ( $c($o['style_border_color']) ?: '#e0e0e0' ) . ";
    border-radius: {$px('style_border_radius')};
    {$shadow}
    overflow: hidden;
}

.ravn-list .ravn-product-box + .ravn-product-box {
    margin-top: {$px('style_gap')};
    border-top: {$px('style_divider_height')} solid " . ( $c($o['style_border_color']) ?: '#e0e0e0' ) . ";
    padding-top: {$px('style_divider_margin')};
}

/* ─── Kolommen ── */
.ravn-columns { display: grid; gap: {$px('style_col_gap')}; }
.ravn-columns-2 { grid-template-columns: repeat(2, 1fr); }
.ravn-columns-3 { grid-template-columns: repeat(3, 1fr); }
.ravn-columns-4 { grid-template-columns: repeat(4, 1fr); }
@media (max-width: 768px) {
    .ravn-columns-2,.ravn-columns-3,.ravn-columns-4 { grid-template-columns: 1fr; }
}
.ravn-columns .ravn-product-box { text-align: {$o['style_col_align']}; }
.ravn-columns .ravn-product-box + .ravn-product-box { margin-top: 0; border-top: none; padding-top: 0; }

/* ─── Afbeelding ── */
.ravn-product-image img { max-width: 100%; height: auto; display: block; }
" . ( ! empty( $o['style_img_blend'] ) ? '.ravn-img-blend img { mix-blend-mode: multiply; }' : '' ) . "
.ravn-product-image-link { display: block; cursor: pointer; }
.ravn-popup-trigger.ravn-product-image { cursor: pointer; }

/* ─── Inhoud ── */
.ravn-content { margin-bottom: {$px('style_content_margin_bottom')}; }

/* ─── Titel ── */
.ravn-product-title {
    margin-bottom: {$px('style_title_margin_bottom')};
    font-size: {$s('style_title_size')};
    font-weight: {$o['style_title_weight']};
    " . ( $c($o['style_title_color']) ? 'color:' . $c($o['style_title_color']) . ';' : '' ) . "
    " . ( ! empty( $o['style_title_underline'] ) ? 'text-decoration: underline;' : 'text-decoration: none;' ) . "
}
.ravn-product-title-link, .ravn-popup-trigger.ravn-product-title { display: block; text-decoration: none; color: inherit; cursor: pointer; }

/* ─── Sterren ── */
.ravn-stars {
    display: flex; align-items: center; gap: 2px;
    margin-top: {$px('style_stars_margin_top')};
    margin-bottom: 4px;
}
.ravn-star { font-size: 1.1em; line-height: 1; }
.ravn-review-count { font-size: 0.85em; color: #666; margin-left: 4px; }

/* ─── Beschrijving ── */
.ravn-product-desc { margin-top: {$px('style_desc_margin_top')}; font-size: 0.9em; }

/* ─── Productlabel ── */
.ravn-product-label {
    display: inline-flex; align-items: center; gap: 4px;
    padding: {$px('style_label_padding')};
    margin-top: {$px('style_label_margin_top')};
    border-radius: {$px('style_label_radius')};
    font-size: {$s('style_label_size')};
    font-weight: {$o['style_label_weight']};
    color: #fff;
    {$label_shadow}
}

/* ─── Verkopers beschrijving ── */
.ravn-sellers-desc {
    margin-bottom: {$px('style_sellers_desc_margin')};
    font-size: {$s('style_sellers_desc_size')};
}
.ravn-sellers-section { margin-top: 12px; }

/* ─── Verkopers ── */
.ravn-sellers-list { display: flex; flex-direction: column; gap: {$px('style_seller_gap')}; }
.ravn-seller-row { display: flex; }
.ravn-seller-link {
    display: inline-flex; align-items: center; gap: 6px;
    padding: {$px('style_seller_padding')};
    border-radius: {$px('style_seller_radius')};
    font-size: {$s('style_seller_size')};
    font-weight: {$o['style_seller_weight']};
    text-decoration: " . ( ! empty( $o['style_seller_underline'] ) ? 'underline' : 'none' ) . ";
    " . ( $c($o['style_seller_bg']) ? 'background-color:' . $c($o['style_seller_bg']) . ';' : '' ) . "
    " . ( $c($o['style_seller_color']) ? 'color:' . $c($o['style_seller_color']) . ';' : 'color: inherit;' ) . "
    {$seller_shadow}
    flex-wrap: wrap;
}
.ravn-seller-logo { display: flex; align-items: center; }
.ravn-seller-logo img { width: 16px; height: 16px; object-fit: contain; }
.ravn-seller-name { flex: 1; }
.ravn-seller-price { font-weight: 700; white-space: nowrap; }
.ravn-stock-icon { display: flex; align-items: center; }

/* ─── CTA knop ── */
.ravn-cta-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: {$px('style_cta_padding')};
    margin-bottom: {$px('style_cta_margin_bottom')};
    border-radius: {$px('style_cta_radius')};
    font-size: {$s('style_cta_size')};
    font-weight: {$o['style_cta_weight']};
    text-decoration: " . ( ! empty( $o['style_cta_underline'] ) ? 'underline' : 'none' ) . ";
    background-color: " . ( $c($o['style_cta_bg']) ?: 'var(--ravn-primary)' ) . ";
    color: " . ( $c($o['style_cta_color']) ?: '#ffffff' ) . ";
    {$cta_shadow}
    cursor: pointer; border: none;
}
.ravn-cta-btn:hover { opacity: .9; }
.ravn-cta-info {
    display: flex; align-items: center; gap: 6px;
    font-size: {$s('style_cta_info_size')};
    font-weight: {$o['style_cta_info_weight']};
    " . ( $c($o['style_cta_info_color']) ? 'color:' . $c($o['style_cta_info_color']) . ';' : '' ) . "
    " . ( ! empty( $o['style_cta_info_underline'] ) ? 'text-decoration: underline;' : '' ) . "
    margin-bottom: 6px;
}

/* ─── Toon alle verkopers knop ── */
.ravn-show-all-btn {
    display: inline-flex; align-items: center; gap: 4px;
    padding: {$px('style_show_all_padding')};
    margin-top: {$px('style_show_all_margin_top')};
    border-radius: {$px('style_show_all_radius')};
    font-size: {$s('style_show_all_size')};
    font-weight: {$o['style_show_all_weight']};
    text-decoration: " . ( ! empty( $o['style_show_all_underline'] ) ? 'underline' : 'none' ) . ";
    " . ( $c($o['style_show_all_bg']) ? 'background-color:' . $c($o['style_show_all_bg']) . ';' : 'background: none;' ) . "
    " . ( $c($o['style_show_all_color']) ? 'color:' . $c($o['style_show_all_color']) . ';' : 'color: var(--ravn-primary);' ) . "
    {$show_all_shadow}
    border: none; cursor: pointer;
}
.ravn-show-all-btn:hover { opacity: .85; }

/* ─── Laatste bijgewerkt ── */
.ravn-last-updated {
    margin-top: {$px('style_updated_margin_top')};
    font-size: {$s('style_updated_size')};
    font-weight: {$o['style_updated_weight']};
    " . ( $c($o['style_updated_color']) ? 'color:' . $c($o['style_updated_color']) . ';' : 'color: #999;' ) . "
    " . ( ! empty( $o['style_updated_underline'] ) ? 'text-decoration: underline;' : '' ) . "
}

/* ─── Popup overlay ── */
.ravn-popup-overlay {
    position: fixed; inset: 0; z-index: 99999;
    background: rgba(0,0,0,.6);
    display: flex; align-items: center; justify-content: center;
}
.ravn-popup-overlay[hidden] { display: none; }
.ravn-popup-inner {
    background: #fff; border-radius: 8px; padding: 24px;
    max-width: 520px; width: 90%; max-height: 90vh; overflow-y: auto;
    position: relative;
}
.ravn-popup-close {
    position: absolute; top: 12px; right: 16px;
    background: none; border: none;
    font-size: 1.6em; cursor: pointer; line-height: 1;
    color: " . ( $c($o['style_popup_close_color']) ?: '#333' ) . ";
}
.ravn-popup-image { max-width: 120px; height: auto; display: block; margin: 0 auto 12px; }
.ravn-popup-title { font-size: 1.1em; font-weight: 700; margin: 0 0 16px; text-align: center; }
.ravn-popup-sellers { display: flex; flex-direction: column; gap: 8px; }

/* ─── Carrousel ── */
.ravn-carousel { position: relative; overflow: hidden; }
.ravn-carousel-track { display: flex; transition: transform {$o['carousel_speed']}ms ease; }
.ravn-carousel-item { flex: 0 0 100%; }
.ravn-carousel-btn {
    position: absolute; top: 50%; transform: translateY(-50%);
    background: none; border: none; cursor: pointer; z-index: 2; padding: 8px;
    color: " . ( $c($o['style_carousel_arrow_color']) ?: 'var(--ravn-primary)' ) . ";
    font-size: 1.5em;
}
.ravn-carousel-prev { left: 0; }
.ravn-carousel-next { right: 0; }
.ravn-carousel-dots { display: flex; justify-content: center; gap: 6px; margin-top: 10px; }
.ravn-carousel-dot {
    width: 10px; height: 10px; border-radius: 50%;
    background: " . ( $c($o['style_carousel_dot_color']) ?: '#ccc' ) . ";
    border: none; cursor: pointer; padding: 0;
}
.ravn-carousel-dot.is-active {
    background: " . ( $c($o['style_carousel_dot_active_color']) ?: 'var(--ravn-primary)' ) . ";
}

/* ─── FAQ ── */
.ravn-faq { margin: 1em 0; }
.ravn-faq-item { border-bottom: 1px solid #e0e0e0; }
.ravn-faq-question {
    width: 100%; text-align: left; background: none; border: none;
    padding: 14px 0; font-weight: 700; cursor: pointer;
    display: flex; justify-content: space-between; align-items: center;
    font-size: 1em;
}
.ravn-faq-icon { transition: transform .25s; display: inline-block; }
.ravn-faq-question[aria-expanded='true'] .ravn-faq-icon { transform: rotate(180deg); }
.ravn-faq-answer { padding: 0 0 14px; font-size: .95em; }

/* ─── TOC ── */
.ravn-toc { background: #f8f8f8; border: 1px solid #e0e0e0; border-radius: 4px; padding: 16px; margin: 1.5em 0; }
.ravn-toc-title { font-weight: 700; margin: 0 0 8px; display: flex; justify-content: space-between; align-items: center; }
.ravn-toc-toggle { background: none; border: none; cursor: pointer; font-size: .85em; color: var(--ravn-primary); }
.ravn-toc ol, .ravn-toc ul { margin: 0; padding-left: 1.5em; }
.ravn-toc li { margin: 4px 0; }
.ravn-toc a { color: var(--ravn-primary); text-decoration: none; }
.ravn-toc a:hover { text-decoration: underline; }

/* ─── CTA icoon ── */
.ravn-cta-icon { display: inline-block; width: 1em; height: 1em; fill: currentColor; }
.ravn-icon-arrow-right::after { content: '→'; }
.ravn-icon-cart::after { content: '🛒'; }
.ravn-icon-check::after { content: '✓'; }
.ravn-icon-star::after { content: '★'; }

/* ─── Algemeen ── */
.ravn-wrap { margin: 1em 0; }
";

        return $css;
    }

    /**
     * Rendert een voorbeeld-productbox met verzonnen data, zodat de
     * stijlinstellingen direct te beoordelen zijn zonder dat er een echt
     * product in de database hoeft te staan. Gebruikt exact dezelfde
     * renderer en CSS als de frontend, dus wat je hier ziet is wat je
     * op de site krijgt.
     */
    public static function render_preview() {
        $product = (object) array(
            'id'           => 0,
            'title'        => 'Voorbeeldproduct — draadloze koptelefoon',
            'description'  => 'Dit is een voorbeeldbeschrijving om te zien hoe je stijlinstellingen uitpakken. De tekst loopt over meerdere regels zodat je regelafstand en marges goed kunt beoordelen.',
            'image_url'    => 'data:image/svg+xml;base64,' . base64_encode(
                '<svg xmlns="http://www.w3.org/2000/svg" width="300" height="300" viewBox="0 0 300 300">'
                . '<rect width="300" height="300" fill="#f0f0f1"/>'
                . '<circle cx="150" cy="130" r="55" fill="none" stroke="#c3c4c7" stroke-width="10"/>'
                . '<rect x="88" y="130" width="30" height="70" rx="14" fill="#c3c4c7"/>'
                . '<rect x="182" y="130" width="30" height="70" rx="14" fill="#c3c4c7"/>'
                . '<text x="150" y="250" font-family="sans-serif" font-size="16" fill="#8c8f94" text-anchor="middle">Voorbeeld</text>'
                . '</svg>'
            ),
            'ean'          => '0000000000000',
            'rating'       => 4.5,
            'review_count' => 128,
            'label_text'   => 'Beste koop',
            'label_color'  => '#e74c3c',
            'label_icon'   => '🏆',
            'cloak_slug'   => '',
            'last_updated' => current_time( 'mysql' ),
        );

        $offers = array(
            (object) array(
                'id'            => 0,
                'product_id'    => 0,
                'seller_name'   => 'Bol.com',
                'seller_domain' => 'bol.com',
                'price'         => 89.99,
                'currency'      => 'EUR',
                'stock_status'  => 'in_stock',
                'affiliate_url' => '#',
                'sub_id'        => '',
                'network'       => 'bol',
                'logo_url'      => '',
                'sort_order'    => 0,
            ),
            (object) array(
                'id'            => 0,
                'product_id'    => 0,
                'seller_name'   => 'Coolblue',
                'seller_domain' => 'coolblue.nl',
                'price'         => 94.50,
                'currency'      => 'EUR',
                'stock_status'  => 'in_stock',
                'affiliate_url' => '#',
                'sub_id'        => '',
                'network'       => 'manual',
                'logo_url'      => '',
                'sort_order'    => 1,
            ),
            (object) array(
                'id'            => 0,
                'product_id'    => 0,
                'seller_name'   => 'Amazon',
                'seller_domain' => 'amazon.nl',
                'price'         => 99.00,
                'currency'      => 'EUR',
                'stock_status'  => 'out_of_stock',
                'affiliate_url' => '#',
                'sub_id'        => '',
                'network'       => 'amazon',
                'logo_url'      => '',
                'sort_order'    => 2,
            ),
        );

        return Ravn_Product::render_product_box( $product, array(
            'preview_offers' => $offers,
        ) );
    }
}
