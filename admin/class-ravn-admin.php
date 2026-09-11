<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Ravn_Admin {

    public function __construct() {
        add_action( 'admin_menu',            array( $this, 'add_menus' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'admin_post_ravn_save_settings', array( $this, 'save_settings' ) );
        add_action( 'admin_post_ravn_reset_tab', array( $this, 'reset_tab_settings' ) );
        add_action( 'admin_post_ravn_save_styling',  array( $this, 'save_styling' ) );
        add_action( 'admin_post_ravn_save_product',  array( $this, 'save_product' ) );
        add_action( 'admin_post_ravn_delete_product',array( $this, 'delete_product' ) );
        add_action( 'admin_post_ravn_run_cron',      array( $this, 'run_cron_now' ) );
        add_action( 'admin_post_ravn_clear_stats',   array( $this, 'clear_stats' ) );
        add_action( 'wp_ajax_ravn_search_ean',       array( $this, 'ajax_search_ean' ) );
        add_action( 'wp_ajax_ravn_bol_diag_ean',     array( $this, 'ajax_bol_diag_ean' ) );
        add_action( 'wp_ajax_ravn_delete_offer',     array( $this, 'ajax_delete_offer' ) );
        add_action( 'wp_ajax_ravn_test_bol_connection', array( $this, 'ajax_test_bol_connection' ) );
        add_action( 'wp_ajax_ravn_test_awin_connection', array( $this, 'ajax_test_awin_connection' ) );
        add_action( 'wp_ajax_ravn_test_tradetracker_connection', array( $this, 'ajax_test_tradetracker_connection' ) );
        add_action( 'admin_notices',               array( $this, 'admin_notices' ) );
    }

    public function add_menus() {
        add_menu_page(
            'Ravn Affiliate',
            'Ravn Affiliate',
            'manage_options',
            'ravn-affiliate',
            array( $this, 'page_products' ),
            'dashicons-cart',
            30
        );
        add_submenu_page( 'ravn-affiliate', 'Alle Producten', 'Alle Producten', 'manage_options', 'ravn-affiliate', array( $this, 'page_products' ) );
        add_submenu_page( 'ravn-affiliate', 'Product Toevoegen', 'Product Toevoegen', 'manage_options', 'ravn-affiliate-add', array( $this, 'page_product_edit' ) );
        add_submenu_page( 'ravn-affiliate', 'Statistieken', 'Statistieken', 'manage_options', 'ravn-affiliate-stats', array( $this, 'page_stats' ) );
        add_submenu_page( 'ravn-affiliate', 'Importeren', 'Importeren', 'manage_options', 'ravn-affiliate-import', array( $this, 'page_import' ) );
        add_submenu_page( 'ravn-affiliate', 'Instellingen', 'Instellingen', 'manage_options', 'ravn-affiliate-settings', array( $this, 'page_settings' ) );
        add_submenu_page( 'ravn-affiliate', 'Styling', 'Styling', 'manage_options', 'ravn-affiliate-styling', array( $this, 'page_styling' ) );

        // Verberg product edit pagina uit menu
        add_submenu_page( 'ravn-affiliate', 'Product Bewerken', 'Product Bewerken', 'manage_options', 'ravn-affiliate-edit', array( $this, 'page_product_edit' ) );
    }

    public function enqueue_scripts( $hook ) {
        if ( strpos( $hook, 'ravn-affiliate' ) === false ) return;

        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_style( 'ravn-admin', RAVN_PLUGIN_URL . 'admin/css/ravn-admin.css', array(), RAVN_VERSION );

        wp_enqueue_media();
        wp_enqueue_script( 'wp-color-picker' );
        wp_enqueue_script( 'ravn-admin', RAVN_PLUGIN_URL . 'admin/js/ravn-admin.js', array( 'jquery', 'wp-color-picker' ), RAVN_VERSION, true );
        wp_localize_script( 'ravn-admin', 'ravnAdmin', array(
            'ajax_url'             => admin_url( 'admin-ajax.php' ),
            'nonce'                => wp_create_nonce( 'ravn_admin_nonce' ),
            'searching'            => 'Zoeken...',
            'search_btn'           => 'Zoeken',
            'found'                => 'Gevonden',
            'offer_prefilled'      => 'aanbieder automatisch toegevoegd, controleer de gegevens hieronder',
            'not_found'            => 'Geen resultaten gevonden.',
            'error'                => 'Er is een fout opgetreden.',
            'remove_offer_confirm' => 'Weet je zeker dat je deze aanbieder wilt verwijderen?',
            'media_title'          => 'Afbeelding kiezen',
            'media_use'            => 'Gebruik deze afbeelding',
            'running'              => 'Bezig...',
            'run_now'              => 'Nieuwe gegevens nu ophalen',
            'cron_done'            => 'Gegevens opgehaald.',
            'testing'              => 'Testen...',
            'api_ok'               => 'Verbinding werkt',
            'api_error'            => 'Verbinding mislukt',
        ) );
    }

    // ─── Pagina's ────────────────────────────────────────────────────────────

    public function page_products() {
        require_once RAVN_PLUGIN_DIR . 'admin/views/product-list.php';
    }

    public function page_product_edit() {
        require_once RAVN_PLUGIN_DIR . 'admin/views/product-edit.php';
    }

    public function page_stats() {
        require_once RAVN_PLUGIN_DIR . 'admin/views/stats.php';
    }

    public function page_import() {
        require_once RAVN_PLUGIN_DIR . 'admin/views/import.php';
    }

    public function page_settings() {
        require_once RAVN_PLUGIN_DIR . 'admin/views/settings.php';
    }

    public function page_styling() {
        require_once RAVN_PLUGIN_DIR . 'admin/views/styling.php';
    }

    // ─── Save handlers ───────────────────────────────────────────────────────

    /**
     * Welke instelvelden bij welke tab horen. Nodig omdat de instellingen-
     * pagina maar één tab tegelijk toont: zonder deze afbakening zou het
     * opslaan van de ene tab alle checkboxes van de andere tabs op 0 zetten
     * (die zitten immers niet in $_POST) en de aangepaste logo's wissen.
     */
    private function get_settings_tab_fields() {
        return array(
            'general'  => array( 'hide_price', 'hide_image', 'hide_title', 'hide_desc', 'hide_sellers', 'hide_rating' ),
            'products' => array( 'ean_max_results', 'show_last_updated', 'last_updated_text', 'sort_sellers' ),
            'sellers'  => array( 'sellers_visible', 'sellers_not_found', 'sellers_description', 'enable_cta', 'enable_cta_info', 'cta_text', 'show_all_text', 'show_seller_count', 'excluded_sellers', 'custom_logos' ),
            'links'    => array( 'click_action', 'module_cloaking', 'new_window', 'nofollow', 'sponsored', 'auto_sub_id', 'sub_id_global' ),
            'carousel' => array( 'carousel_arrows', 'carousel_dots', 'carousel_infinite', 'carousel_autoplay', 'carousel_speed', 'carousel_autoplay_speed' ),
            'cron'     => array( 'cron_active', 'cron_start_time', 'cron_interval', 'cron_timeout', 'cron_max_timeouts', 'cron_networks' ),
            'stats'    => array( 'stats_link_tracking', 'stats_stock_tracking' ),
            'api'      => array(
                'bol_client_id', 'bol_client_secret', 'bol_site_id', 'bol_country_code',
                'amazon_access_key', 'amazon_secret_key', 'amazon_partner_tag', 'amazon_marketplace',
                'tradetracker_customer_id', 'tradetracker_api_key', 'tradetracker_site_id',
                'daisycon_publisher_id', 'daisycon_feed_url',
                'awin_publisher_id', 'awin_api_token', 'awin_advertiser_id',
                'tradedoubler_org_id', 'tradedoubler_token',
                'adtraction_api_key', 'adtraction_channel_id',
                'partnerize_user_api_key', 'partnerize_app_api_key',
            ),
            'modules'  => array( 'module_faq', 'module_toc', 'module_schema', 'module_stats', 'module_import' ),
            'search'   => array( 'exclude_from_search' ),
        );
    }

    /**
     * Zet de instellingen van één tab terug naar de standaardwaarden.
     * Handig na de opslag-bug in versies vóór 2.6.2, waarbij vinkjes van
     * andere tabs ongewild op 0 werden gezet.
     */
    public function reset_tab_settings() {
        check_admin_referer( 'ravn_reset_tab' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Geen toegang' );

        $post = wp_unslash( $_POST );
        $tab  = isset( $post['settings_tab'] ) ? sanitize_key( $post['settings_tab'] ) : '';

        $tab_fields = $this->get_settings_tab_fields();
        if ( ! isset( $tab_fields[ $tab ] ) ) {
            wp_redirect( add_query_arg( array( 'page' => 'ravn-affiliate-settings' ), admin_url( 'admin.php' ) ) );
            exit;
        }

        $defaults = Ravn_Options::defaults();
        $data     = array();
        foreach ( $tab_fields[ $tab ] as $key ) {
            if ( array_key_exists( $key, $defaults ) ) {
                $data[ $key ] = $defaults[ $key ];
            }
        }

        Ravn_Options::save( $data );

        wp_redirect( add_query_arg( array(
            'page'  => 'ravn-affiliate-settings',
            'tab'   => $tab,
            'reset' => 1,
        ), admin_url( 'admin.php' ) ) );
        exit;
    }

    public function save_settings() {
        check_admin_referer( 'ravn_save_settings' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Geen toegang' );

        $post = wp_unslash( $_POST );

        // Bepaal welke tab is verzonden en dus welke velden we mogen aanraken.
        $tab_fields = $this->get_settings_tab_fields();
        $tab        = isset( $post['settings_tab'] ) ? sanitize_key( $post['settings_tab'] ) : '';
        $allowed_keys = isset( $tab_fields[ $tab ] ) ? $tab_fields[ $tab ] : array();

        // Onbekende/ontbrekende tab: niets opslaan, om te voorkomen dat er
        // per ongeluk instellingen worden overschreven of gewist.
        if ( empty( $allowed_keys ) ) {
            wp_redirect( add_query_arg( array( 'page' => 'ravn-affiliate-settings', 'tab' => $tab ), admin_url( 'admin.php' ) ) );
            exit;
        }

        // Checkboxes zitten niet in $_POST als ze niet aangevinkt zijn; die
        // moeten dus expliciet op 0 gezet worden — maar alleen binnen deze tab.
        $checkbox_keys = array(
            'module_faq','module_toc','module_schema','module_stats','module_import','module_cloaking',
            'hide_price','hide_image','hide_title','hide_desc','hide_sellers','hide_rating',
            'show_last_updated','enable_cta','enable_cta_info','show_seller_count',
            'new_window','nofollow','sponsored','auto_sub_id',
            'carousel_arrows','carousel_dots','carousel_infinite','carousel_autoplay',
            'cron_active','stats_link_tracking','stats_stock_tracking','exclude_from_search',
        );

        $data = array();
        foreach ( $allowed_keys as $key ) {
            if ( in_array( $key, $checkbox_keys, true ) ) {
                $data[ $key ] = isset( $post[ $key ] ) ? 1 : 0;
            } elseif ( 'cron_networks' === $key ) {
                $nets = isset( $post[ $key ] ) ? (array) $post[ $key ] : array();
                $data[ $key ] = array_map( 'sanitize_text_field', $nets );
            } elseif ( 'custom_logos' === $key ) {
                // Parallelle arrays domain[]/url[] koppelen tot domain => url.
                $logos   = array();
                $domains = isset( $post['custom_logos_domain'] ) ? (array) $post['custom_logos_domain'] : array();
                $urls    = isset( $post['custom_logos_url'] )    ? (array) $post['custom_logos_url']    : array();
                foreach ( $domains as $i => $domain ) {
                    $domain = strtolower( trim( sanitize_text_field( $domain ) ) );
                    $url    = isset( $urls[ $i ] ) ? esc_url_raw( trim( $urls[ $i ] ) ) : '';
                    if ( $domain && $url ) {
                        $logos[ $domain ] = $url;
                    }
                }
                $data['custom_logos'] = $logos;
            } elseif ( isset( $post[ $key ] ) ) {
                $data[ $key ] = sanitize_text_field( $post[ $key ] );
            }
        }

        Ravn_Options::save( $data );

        // Cron opnieuw laten inplannen als de cron-instellingen zijn gewijzigd.
        if ( 'cron' === $tab ) {
            wp_clear_scheduled_hook( 'ravn_cron_fetch' );
        }

        wp_redirect( add_query_arg( array(
            'page'  => 'ravn-affiliate-settings',
            'tab'   => $tab,
            'saved' => 1,
        ), admin_url( 'admin.php' ) ) );
        exit;
    }

    public function save_styling() {
        check_admin_referer( 'ravn_save_styling' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Geen toegang' );

        $post    = wp_unslash( $_POST );
        $defaults = Ravn_Options::defaults();
        $data    = array();

        $checkbox_style_keys = array(
            'style_shadow','style_img_blend',
            'style_title_underline','style_label_shadow',
            'style_seller_shadow','style_seller_underline',
            'style_cta_shadow','style_cta_underline','style_cta_icon','style_cta_info_underline',
            'style_show_all_shadow','style_show_all_underline',
            'style_updated_underline',
        );

        foreach ( $defaults as $key => $default ) {
            if ( strpos( $key, 'style_' ) !== 0 ) continue;
            if ( in_array( $key, $checkbox_style_keys, true ) ) {
                $data[ $key ] = isset( $post[ $key ] ) ? 1 : 0;
            } elseif ( isset( $post[ $key ] ) ) {
                $data[ $key ] = sanitize_text_field( $post[ $key ] );
            }
        }

        Ravn_Options::save( $data );

        wp_redirect( add_query_arg( array( 'page' => 'ravn-affiliate-styling', 'saved' => 1 ), admin_url( 'admin.php' ) ) );
        exit;
    }

    /**
     * Zorgt dat de cloak-slug uniek is over alle producten heen, naar het
     * patroon van WordPress' eigen post-slugs (voegt -2, -3, enz. toe bij
     * een botsing). Zonder dit zouden twee producten met dezelfde of
     * gelijkluidende titel dezelfde /go/{slug}/-URL kunnen krijgen, waarbij
     * de link dan naar een willekeurig van de twee producten zou wijzen.
     *
     * @param string $slug             De gewenste (al gesanitized) slug.
     * @param int    $exclude_product_id Product-ID om te negeren bij het
     *                                    checken (bij het opslaan van een
     *                                    bestaand product mag het zijn eigen
     *                                    slug behouden).
     * @return string Een gegarandeerd unieke slug.
     */
    private function get_unique_cloak_slug( $slug, $exclude_product_id = 0 ) {
        if ( '' === $slug ) return $slug;

        global $wpdb;
        $base  = $slug;
        $count = 2;

        while ( true ) {
            $existing_id = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ravn_products WHERE cloak_slug = %s LIMIT 1",
                $slug
            ) );

            // Geen botsing, of de botsing is met het product dat we nu juist opslaan.
            if ( ! $existing_id || intval( $existing_id ) === intval( $exclude_product_id ) ) {
                return $slug;
            }

            $slug = $base . '-' . $count;
            $count++;

            // Veiligheidsklep tegen een oneindige lus in een onwaarschijnlijk edge-case.
            if ( $count > 100 ) {
                return $base . '-' . uniqid();
            }
        }
    }

    public function save_product() {
        check_admin_referer( 'ravn_save_product' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Geen toegang' );

        $post       = wp_unslash( $_POST );
        $product_id = intval( $post['product_id'] ?? 0 );

        $requested_slug = sanitize_title( ! empty( $post['cloak_slug'] ) ? $post['cloak_slug'] : ( $post['title'] ?? '' ) );
        $unique_slug    = $this->get_unique_cloak_slug( $requested_slug, $product_id );

        $product_data = array(
            'title'        => sanitize_text_field( $post['title'] ?? '' ),
            'description'  => wp_kses_post( $post['description'] ?? '' ),
            'image_url'    => esc_url_raw( $post['image_url'] ?? '' ),
            'ean'          => sanitize_text_field( $post['ean'] ?? '' ),
            'rating'       => floatval( $post['rating'] ?? 0 ),
            'review_count' => intval( $post['review_count'] ?? 0 ),
            'label_text'   => sanitize_text_field( $post['label_text'] ?? '' ),
            'label_color'  => sanitize_hex_color( $post['label_color'] ?? '#e74c3c' ) ?: '#e74c3c',
            'label_icon'   => sanitize_text_field( $post['label_icon'] ?? '' ),
            'cloak_slug'   => $unique_slug,
        );

        if ( $product_id ) {
            Ravn_Database::update_product( $product_id, $product_data );
        } else {
            $product_id = Ravn_Database::insert_product( array_merge( $product_data, array(
                'created_at' => current_time( 'mysql' ),
            ) ) );
        }

        // Sla aanbieders op
        $offers    = isset( $post['offers'] ) ? (array) $post['offers'] : array();
        $kept_ids  = array();
        foreach ( $offers as $idx => $offer_data ) {
            $offer_id = intval( $offer_data['id'] ?? 0 );
            $domain   = '';
            if ( ! empty( $offer_data['affiliate_url'] ) ) {
                $parsed = wp_parse_url( $offer_data['affiliate_url'] );
                $domain = isset( $parsed['host'] ) ? $parsed['host'] : '';
            }
            $clean = array(
                'product_id'    => $product_id,
                'seller_name'   => sanitize_text_field( $offer_data['seller_name'] ?? '' ),
                'seller_domain' => sanitize_text_field( $offer_data['seller_domain'] ?? $domain ),
                'price'         => is_numeric( $offer_data['price'] ?? '' ) ? floatval( $offer_data['price'] ) : null,
                'currency'      => sanitize_text_field( $offer_data['currency'] ?? 'EUR' ),
                'stock_status'  => sanitize_text_field( $offer_data['stock_status'] ?? 'unknown' ),
                'affiliate_url' => esc_url_raw( $offer_data['affiliate_url'] ?? '' ),
                'sub_id'        => sanitize_text_field( $offer_data['sub_id'] ?? '' ),
                'network'       => sanitize_text_field( $offer_data['network'] ?? '' ),
                'logo_url'      => esc_url_raw( $offer_data['logo_url'] ?? '' ),
                'sort_order'    => intval( $idx ),
            );
            if ( $offer_id ) {
                Ravn_Database::update_offer( $offer_id, $clean );
                $kept_ids[] = $offer_id;
            } else {
                $new_id = Ravn_Database::insert_offer( $clean );
                if ( $new_id ) $kept_ids[] = $new_id;
            }
        }

        // Verwijder aanbieders die in de interface zijn weggehaald.
        $existing = Ravn_Database::get_offers( $product_id );
        foreach ( $existing as $ex ) {
            if ( ! in_array( intval( $ex->id ), $kept_ids, true ) ) {
                Ravn_Database::delete_offer( $ex->id );
            }
        }

        $url = add_query_arg( array(
            'page'       => 'ravn-affiliate-edit',
            'product_id' => $product_id,
            'saved'      => 1,
        ), admin_url( 'admin.php' ) );
        wp_redirect( $url );
        exit;
    }

    public function delete_product() {
        check_admin_referer( 'ravn_delete_product' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Geen toegang' );

        $product_id = intval( $_POST['product_id'] ?? $_GET['product_id'] ?? 0 );
        if ( $product_id ) {
            Ravn_Database::delete_product( $product_id );
        }
        wp_redirect( add_query_arg( 'page', 'ravn-affiliate', admin_url( 'admin.php' ) ) );
        exit;
    }

    public function run_cron_now() {
        check_admin_referer( 'ravn_run_cron' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Geen toegang' );

        Ravn_Cron::run_now();

        wp_redirect( add_query_arg( array( 'page' => 'ravn-affiliate-settings', 'cron_ran' => 1 ), admin_url( 'admin.php' ) ) );
        exit;
    }

    public function clear_stats() {
        check_admin_referer( 'ravn_clear_stats' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Geen toegang' );

        Ravn_Database::clear_stats();
        wp_redirect( add_query_arg( array( 'page' => 'ravn-affiliate-stats', 'cleared' => 1 ), admin_url( 'admin.php' ) ) );
        exit;
    }

    // ─── AJAX ────────────────────────────────────────────────────────────────

    public function ajax_search_ean() {
        check_ajax_referer( 'ravn_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Geen toegang' );

        $query   = sanitize_text_field( wp_unslash( $_POST['query'] ?? '' ) );
        $limit   = intval( Ravn_Options::get( 'ean_max_results', 10 ) );
        $results = Ravn_API::search_all_networks( $query, $limit );

        wp_send_json_success( $results );
    }

    /**
     * Diagnostische AJAX-actie: toont het rauwe resultaat van bol.com voor
     * één specifiek EAN, zowel het product-endpoint als het offers/best-
     * endpoint, zodat direct te zien is waarom een prijs wel/niet gevonden
     * wordt zonder in logbestanden te hoeven zoeken.
     */
    public function ajax_bol_diag_ean() {
        check_ajax_referer( 'ravn_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array( 'message' => 'Geen toegang' ) );

        $ean = sanitize_text_field( wp_unslash( $_POST['ean'] ?? '' ) );
        $ean = preg_replace( '/[^0-9]/', '', $ean );

        if ( 13 !== strlen( $ean ) ) {
            wp_send_json_error( array( 'message' => 'Ongeldig EAN: verwacht 13 cijfers, kreeg "' . $ean . '" (' . strlen( $ean ) . ' tekens).' ) );
        }

        $product_data = Ravn_API::bol_get_product_by_ean( $ean );
        $offer_diag   = Ravn_API::bol_get_offers_for_ean_diag( $ean );

        wp_send_json_success( array(
            'ean'          => $ean,
            'product_data' => $product_data,
            'offer_price'  => $offer_diag['price'],
            'offer_reason' => $offer_diag['reason'],
        ) );
    }

    public function ajax_delete_offer() {
        check_ajax_referer( 'ravn_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Geen toegang' );

        $offer_id = intval( wp_unslash( $_POST['offer_id'] ?? 0 ) );
        if ( $offer_id ) {
            Ravn_Database::delete_offer( $offer_id );
            wp_send_json_success();
        }
        wp_send_json_error( 'Ongeldig ID' );
    }

    public function ajax_test_bol_connection() {
        check_ajax_referer( 'ravn_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array( 'message' => 'Geen toegang' ) );

        // Gebruik eventueel de nog niet opgeslagen velden uit het formulier zelf,
        // zodat de gebruiker kan testen vóór het klikken op "Instellingen opslaan".
        $temp_id     = sanitize_text_field( wp_unslash( $_POST['client_id'] ?? '' ) );
        $temp_secret = sanitize_text_field( wp_unslash( $_POST['client_secret'] ?? '' ) );

        $restore = null;
        if ( '' !== $temp_id || '' !== $temp_secret ) {
            $current = Ravn_Options::get_all();
            $restore = array(
                'bol_client_id'     => $current['bol_client_id'],
                'bol_client_secret' => $current['bol_client_secret'],
            );
            // Tijdelijk in-memory overschrijven voor de test (niet opgeslagen in de database).
            Ravn_Options::save( array(
                'bol_client_id'     => $temp_id ?: $current['bol_client_id'],
                'bol_client_secret' => $temp_secret ?: $current['bol_client_secret'],
            ) );
        }

        $result = Ravn_API::bol_test_connection();

        // Zet meteen terug zodat de test geen ongeoorloofde wijziging achterlaat
        // als de gebruiker de instellingen niet daadwerkelijk had opgeslagen.
        if ( null !== $restore ) {
            Ravn_Options::save( $restore );
        }

        if ( $result['success'] ) {
            wp_send_json_success( array( 'message' => $result['message'] ) );
        }
        wp_send_json_error( array( 'message' => $result['message'], 'step' => $result['step'] ?? '' ) );
    }

    public function ajax_test_awin_connection() {
        check_ajax_referer( 'ravn_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array( 'message' => 'Geen toegang' ) );

        $temp = array(
            'awin_publisher_id'  => sanitize_text_field( wp_unslash( $_POST['publisher_id'] ?? '' ) ),
            'awin_api_token'     => sanitize_text_field( wp_unslash( $_POST['api_token'] ?? '' ) ),
            'awin_advertiser_id' => sanitize_text_field( wp_unslash( $_POST['advertiser_id'] ?? '' ) ),
        );

        $current = Ravn_Options::get_all();
        $restore = array();
        $override = array();
        foreach ( $temp as $key => $val ) {
            $restore[ $key ]  = $current[ $key ];
            $override[ $key ] = $val ?: $current[ $key ];
        }
        Ravn_Options::save( $override );

        $result = Ravn_API::awin_test_connection();

        Ravn_Options::save( $restore );

        if ( $result['success'] ) {
            wp_send_json_success( array( 'message' => $result['message'] ) );
        }
        wp_send_json_error( array( 'message' => $result['message'] ) );
    }

    public function ajax_test_tradetracker_connection() {
        check_ajax_referer( 'ravn_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array( 'message' => 'Geen toegang' ) );

        $temp = array(
            'tradetracker_customer_id' => sanitize_text_field( wp_unslash( $_POST['customer_id'] ?? '' ) ),
            'tradetracker_api_key'     => sanitize_text_field( wp_unslash( $_POST['passphrase'] ?? '' ) ),
            'tradetracker_site_id'     => sanitize_text_field( wp_unslash( $_POST['site_id'] ?? '' ) ),
        );

        $current = Ravn_Options::get_all();
        $restore = array();
        $override = array();
        foreach ( $temp as $key => $val ) {
            $restore[ $key ]  = $current[ $key ];
            $override[ $key ] = $val ?: $current[ $key ];
        }
        Ravn_Options::save( $override );

        $result = Ravn_API::tradetracker_test_connection();

        Ravn_Options::save( $restore );

        if ( $result['success'] ) {
            wp_send_json_success( array( 'message' => $result['message'] ) );
        }
        wp_send_json_error( array( 'message' => $result['message'] ) );
    }

    // ─── Admin notices ────────────────────────────────────────────────────────

    public function admin_notices() {
        if ( isset( $_GET['saved'] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>Instellingen opgeslagen.</p></div>';
        }
        if ( isset( $_GET['reset'] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>Dit tabblad is teruggezet naar de standaardwaarden.</p></div>';
        }
        if ( isset( $_GET['cron_ran'] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>Gegevens worden opgehaald. Dit kan even duren.</p></div>';
        }
        if ( isset( $_GET['cleared'] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>Statistieken gewist.</p></div>';
        }
        if ( isset( $_GET['ravn_status'] ) ) {
            $type = sanitize_text_field( $_GET['ravn_status'] ) === 'success' ? 'success' : 'error';
            $msg  = isset( $_GET['ravn_message'] ) ? sanitize_text_field( urldecode( $_GET['ravn_message'] ) ) : '';
            echo '<div class="notice notice-' . $type . ' is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
        }
    }
}
