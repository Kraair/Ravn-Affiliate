<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Ravn_Options {

    const OPTION_KEY = 'ravn_affiliate_options';

    public static function defaults() {
        return array(
            // Modules aan/uit
            'module_faq'           => 1,
            'module_toc'           => 1,
            'module_schema'        => 1,
            'module_stats'         => 1,
            'module_import'        => 1,

            // === 1. ALGEMEEN (verbergen per site) ===
            'hide_price'           => 0,
            'hide_image'           => 0,
            'hide_title'           => 0,
            'hide_desc'            => 0,
            'hide_sellers'         => 0,
            'hide_rating'          => 0,

            // === 2. PRODUCTEN ===
            'ean_max_results'      => 10,
            'show_last_updated'    => 0,
            'last_updated_text'    => 'Laatst bijgewerkt op',
            'sort_sellers'         => 'default', // default, price_asc, price_desc, name_asc

            // === 3. VERKOPERS ===
            'sellers_visible'      => 3,
            'sellers_not_found'    => 'out_of_stock', // out_of_stock, remove
            'sellers_description'  => 'Verkrijgbaar bij',
            'enable_cta'           => 0,
            'enable_cta_info'      => 0,
            'cta_text'             => 'Bekijk aanbieding',
            'show_all_text'        => 'Toon alle verkopers',
            'show_seller_count'    => 0,
            'excluded_sellers'     => '',
            'custom_logos'         => array(), // array( 'domain' => 'url' )

            // === 4. AFFILIATE LINKS ===
            'click_action'         => 'redirect', // redirect, popup
            'module_cloaking'      => 1,
            'new_window'           => 1,
            'nofollow'             => 1,
            'sponsored'            => 1,
            'auto_sub_id'          => 0,
            'sub_id_global'        => '',

            // === 5. CARROUSEL ===
            'carousel_arrows'      => 1,
            'carousel_dots'        => 1,
            'carousel_infinite'    => 1,
            'carousel_autoplay'    => 0,
            'carousel_speed'       => 500,
            'carousel_autoplay_speed' => 3000,

            // === 6. AUTO BIJWERKEN ===
            'cron_active'          => 0,
            'cron_start_time'      => '01:00',
            'cron_interval'        => 'daily',  // hourly, twicedaily, daily
            'cron_timeout'         => 5,
            'cron_max_timeouts'    => 3,
            'cron_networks'        => array( 'bol', 'amazon', 'tradetracker', 'daisycon', 'awin', 'tradedoubler', 'adtraction', 'partnerize' ),

            // === 7. STATISTIEKEN ===
            'stats_link_tracking'  => 0,
            'stats_stock_tracking' => 0,

            // === 8. ZOEKEN ===
            'exclude_from_search'  => 1,

            // === API KOPPELINGEN ===
            'bol_client_id'        => '',
            'bol_client_secret'    => '',
            'bol_site_id'          => '',
            'bol_country_code'     => 'NL',
            'amazon_access_key'    => '',
            'amazon_secret_key'    => '',
            'amazon_partner_tag'   => '',
            'amazon_marketplace'   => 'nl',
            'tradetracker_customer_id' => '',
            'tradetracker_api_key' => '',
            'tradetracker_site_id' => '',
            'daisycon_publisher_id'=> '',
            'daisycon_feed_url'    => '',
            'awin_publisher_id'    => '',
            'awin_api_token'       => '',
            'awin_advertiser_id'   => '',
            'tradedoubler_org_id'  => '',
            'tradedoubler_token'   => '',
            'adtraction_api_key'   => '',
            'adtraction_channel_id'=> '',
            'partnerize_user_api_key' => '',
            'partnerize_app_api_key'  => '',

            // === STYLING: ALGEMEEN ===
            'style_padding'        => '20',
            'style_margin_top'     => '0',
            'style_gap'            => '10',
            'style_shadow'         => 1,
            'style_bg_color'       => '#ffffff',

            // Scheidingslijn
            'style_divider_height' => '1',
            'style_divider_margin' => '20',

            // Rand
            'style_border_width'   => '0',
            'style_border_radius'  => '0',
            'style_border_position'=> 'all',
            'style_border_color'   => '#e0e0e0',

            // Kolommen
            'style_col_gap'        => '20',
            'style_col_align'      => 'left',

            // === STYLING: AFBEELDING ===
            'style_img_blend'      => 0,

            // === STYLING: STERREN ===
            'style_stars_margin_top'  => '5',
            'style_star_active_color' => '#f5a623',
            'style_star_inactive_color' => '#e0e0e0',

            // === STYLING: TITEL ===
            'style_title_margin_bottom' => '10',
            'style_title_size'     => 'medium',
            'style_title_weight'   => '700',
            'style_title_underline'=> 0,
            'style_title_color'    => '',

            // === STYLING: BESCHRIJVING ===
            'style_desc_margin_top'=> '10',

            // === STYLING: PRODUCTLABEL ===
            'style_label_padding'  => '4',
            'style_label_margin_top'=> '8',
            'style_label_radius'   => '4',
            'style_label_size'     => 'medium',
            'style_label_weight'   => '700',
            'style_label_icon_align'=> 'left',
            'style_label_shadow'   => 0,

            // === STYLING: VERKOPERS BESCHRIJVING ===
            'style_sellers_desc_margin' => '10',
            'style_sellers_desc_size'   => 'medium',

            // === STYLING: VERKOPERS ===
            'style_seller_padding' => '8',
            'style_seller_gap'     => '8',
            'style_seller_radius'  => '4',
            'style_seller_size'    => 'medium',
            'style_seller_weight'  => '400',
            'style_seller_shadow'  => 0,
            'style_seller_underline'=> 0,
            'style_seller_bg_logo' => '',
            'style_seller_bg_stock'=> '',
            'style_seller_bg'      => '',
            'style_seller_color'   => '',
            'style_seller_stock_in'   => '#27ae60',
            'style_seller_stock_out'  => '#e74c3c',
            'style_seller_stock_unknown' => '#95a5a6',

            // === STYLING: CTA ===
            'style_cta_padding'    => '12',
            'style_cta_margin_bottom'=> '10',
            'style_cta_radius'     => '4',
            'style_cta_size'       => 'medium',
            'style_cta_weight'     => '700',
            'style_cta_shadow'     => 1,
            'style_cta_underline'  => 0,
            'style_cta_icon'       => 0,
            'style_cta_icon_name'  => 'arrow-right',
            'style_cta_icon_align' => 'right',
            'style_cta_bg'         => '',
            'style_cta_color'      => '#ffffff',
            'style_cta_info_size'  => 'medium',
            'style_cta_info_weight'=> '400',
            'style_cta_info_underline' => 0,
            'style_cta_info_color' => '',
            'style_cta_stock_in'   => '#27ae60',
            'style_cta_stock_out'  => '#e74c3c',
            'style_cta_stock_unknown' => '#95a5a6',

            // === STYLING: TOON ALLE VERKOPERS ===
            'style_show_all_padding'  => '8',
            'style_show_all_margin_top'=> '8',
            'style_show_all_radius'   => '4',
            'style_show_all_size'     => 'medium',
            'style_show_all_weight'   => '400',
            'style_show_all_shadow'   => 0,
            'style_show_all_underline'=> 1,
            'style_show_all_bg'       => '',
            'style_show_all_color'    => '',

            // === STYLING: CARROUSEL ===
            'style_carousel_arrow_color'      => '',
            'style_carousel_dot_color'        => '#cccccc',
            'style_carousel_dot_active_color' => '',

            // === STYLING: LAATS BIJGEWERKT ===
            'style_updated_margin_top' => '10',
            'style_updated_size'    => 'small',
            'style_updated_weight'  => '400',
            'style_updated_underline'=> 0,
            'style_updated_color'   => '#999999',

            // === STYLING: POPUP ===
            'style_popup_close_color' => '#333333',

            // === STYLING: INHOUD (shortcode content) ===
            'style_content_margin_bottom' => '10',
        );
    }

    public static function get_all() {
        $saved    = get_option( self::OPTION_KEY, array() );
        $defaults = self::defaults();
        return wp_parse_args( $saved, $defaults );
    }

    public static function get( $key, $default = null ) {
        $all = self::get_all();
        if ( isset( $all[ $key ] ) ) return $all[ $key ];
        $defaults = self::defaults();
        return isset( $defaults[ $key ] ) ? $defaults[ $key ] : $default;
    }

    public static function set( $key, $value ) {
        $all         = self::get_all();
        $all[ $key ] = $value;
        update_option( self::OPTION_KEY, $all );
    }

    public static function save( $data ) {
        $all  = self::get_all();
        $merged = array_merge( $all, $data );
        update_option( self::OPTION_KEY, $merged );
    }
}
