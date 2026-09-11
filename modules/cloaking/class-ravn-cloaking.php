<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Ravn_Cloaking {

    public function __construct() {
        add_action( 'init',                  array( $this, 'add_rewrite_rules' ) );
        add_filter( 'query_vars',            array( $this, 'add_query_vars' ) );
        add_action( 'template_redirect',     array( $this, 'handle_redirect' ) );
    }

    public function add_rewrite_rules() {
        add_rewrite_rule( '^go/([^/]+)/?$', 'index.php?ravn_cloak=$matches[1]', 'top' );
        $this->maybe_flush_rules();
    }

    /**
     * Ververst de rewrite rules eenmalig per plugin-versie. Nodig omdat
     * WordPress rewrite rules alleen cachet bij (de)activeren van de plugin,
     * niet bij een bestandsvervanging (zip-upload over een bestaande install)
     * — zonder dit blijft /go/... onherkend en valt de request terug op
     * normale routing.
     */
    private function maybe_flush_rules() {
        if ( get_option( 'ravn_cloak_rules_version' ) !== RAVN_VERSION ) {
            flush_rewrite_rules();
            update_option( 'ravn_cloak_rules_version', RAVN_VERSION );
        }
    }

    public function add_query_vars( $vars ) {
        $vars[] = 'ravn_cloak';
        return $vars;
    }

    public function handle_redirect() {
        $slug = get_query_var( 'ravn_cloak' );
        if ( ! $slug ) return;

        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT p.id, o.affiliate_url, o.id as offer_id, o.sub_id, o.network
             FROM {$wpdb->prefix}ravn_products p
             LEFT JOIN {$wpdb->prefix}ravn_offers o ON o.product_id = p.id
             WHERE p.cloak_slug = %s
             ORDER BY o.sort_order ASC, o.id ASC
             LIMIT 1",
            sanitize_title( $slug )
        ) );

        if ( ! $row || empty( $row->affiliate_url ) ) {
            wp_redirect( home_url(), 301 );
            exit;
        }

        // Click tracking
        $opts = Ravn_Options::get_all();
        if ( ! empty( $opts['stats_link_tracking'] ) ) {
            Ravn_Database::log_click(
                $row->offer_id,
                $row->id,
                isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '',
                $row->sub_id
            );
        }

        $url = Ravn_Product::build_url( $row->affiliate_url, $row->sub_id, '', $row->network );
        wp_redirect( $url, 302 );
        exit;
    }

    public static function generate_slug( $title ) {
        return sanitize_title( $title );
    }
}
