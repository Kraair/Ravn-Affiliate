<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Ravn_Database {

    public static function create_tables() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Producten
        dbDelta( "CREATE TABLE {$wpdb->prefix}ravn_products (
            id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            title       VARCHAR(500) NOT NULL DEFAULT '',
            description TEXT,
            image_url   VARCHAR(1000) DEFAULT '',
            ean         VARCHAR(50)  DEFAULT '',
            rating      DECIMAL(3,1) DEFAULT 0,
            review_count INT(11) DEFAULT 0,
            label_text  VARCHAR(100) DEFAULT '',
            label_color VARCHAR(20)  DEFAULT '#e74c3c',
            label_icon  VARCHAR(50)  DEFAULT '',
            cloak_slug  VARCHAR(200) DEFAULT '',
            last_updated DATETIME    DEFAULT NULL,
            created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY ean (ean),
            KEY cloak_slug (cloak_slug(100))
        ) $charset;" );

        // Aanbieders/verkopers per product
        dbDelta( "CREATE TABLE {$wpdb->prefix}ravn_offers (
            id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id  BIGINT(20) UNSIGNED NOT NULL,
            seller_name VARCHAR(200) NOT NULL DEFAULT '',
            seller_domain VARCHAR(200) DEFAULT '',
            price       DECIMAL(10,2) DEFAULT NULL,
            currency    VARCHAR(10) DEFAULT 'EUR',
            stock_status VARCHAR(20) DEFAULT 'unknown',
            affiliate_url TEXT,
            sub_id      VARCHAR(200) DEFAULT '',
            network     VARCHAR(50)  DEFAULT '',
            logo_url    VARCHAR(1000) DEFAULT '',
            sort_order  INT(11) DEFAULT 0,
            created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at  DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY product_id (product_id),
            KEY network (network)
        ) $charset;" );

        // Klik statistieken
        dbDelta( "CREATE TABLE {$wpdb->prefix}ravn_clicks (
            id         BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            offer_id   BIGINT(20) UNSIGNED NOT NULL,
            product_id BIGINT(20) UNSIGNED NOT NULL,
            page_url   VARCHAR(1000) DEFAULT '',
            sub_id     VARCHAR(200) DEFAULT '',
            ip_hash    VARCHAR(64) DEFAULT '',
            clicked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY offer_id (offer_id),
            KEY product_id (product_id),
            KEY clicked_at (clicked_at)
        ) $charset;" );

        // Voorraad log
        dbDelta( "CREATE TABLE {$wpdb->prefix}ravn_stock_log (
            id         BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            offer_id   BIGINT(20) UNSIGNED NOT NULL,
            product_id BIGINT(20) UNSIGNED NOT NULL,
            old_status VARCHAR(20) DEFAULT '',
            new_status VARCHAR(20) DEFAULT '',
            logged_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY offer_id (offer_id),
            KEY product_id (product_id),
            KEY logged_at (logged_at)
        ) $charset;" );

        // FAQ items
        dbDelta( "CREATE TABLE {$wpdb->prefix}ravn_faq (
            id         BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id    BIGINT(20) UNSIGNED DEFAULT 0,
            question   TEXT NOT NULL,
            answer     TEXT NOT NULL,
            sort_order INT(11) DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_id (post_id)
        ) $charset;" );
    }

    public static function drop_tables() {
        global $wpdb;
        $tables = array( 'ravn_products', 'ravn_offers', 'ravn_clicks', 'ravn_stock_log', 'ravn_faq' );
        foreach ( $tables as $table ) {
            $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$table}" );
        }
    }

    // ── Products ────────────────────────────────────────────────────────────

    /**
     * Bouwt de WHERE-clausule voor zoeken + voorraadfilter (gedeeld door
     * get_products() en count_products(), zodat aantallen kloppen).
     *
     * Voorraadfilter (op basis van de aanbieders van een product):
     *  in_stock     - minstens één aanbieder op voorraad
     *  out_of_stock - geen enkele aanbieder op voorraad, minstens één uitverkocht
     *  unknown      - wel aanbieders, maar alleen met onbekende status
     *  no_offers    - geen aanbieders
     */
    private static function build_product_where( $search = '', $stock = '' ) {
        global $wpdb;
        $offers = $wpdb->prefix . 'ravn_offers';
        $where  = ' WHERE 1=1';
        $params = array();

        if ( '' !== $search ) {
            $where   .= ' AND (title LIKE %s OR ean LIKE %s)';
            $like     = '%' . $wpdb->esc_like( $search ) . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $has = function ( $status ) use ( $offers, $wpdb ) {
            return "EXISTS (SELECT 1 FROM {$offers} o WHERE o.product_id = {$wpdb->prefix}ravn_products.id AND o.stock_status = '{$status}')";
        };
        $any = "EXISTS (SELECT 1 FROM {$offers} o WHERE o.product_id = {$wpdb->prefix}ravn_products.id)";

        switch ( $stock ) {
            case 'in_stock':
                $where .= ' AND ' . $has( 'in_stock' );
                break;
            case 'out_of_stock':
                $where .= ' AND NOT ' . $has( 'in_stock' ) . ' AND ' . $has( 'out_of_stock' );
                break;
            case 'unknown':
                $where .= ' AND ' . $any . ' AND NOT ' . $has( 'in_stock' ) . ' AND NOT ' . $has( 'out_of_stock' );
                break;
            case 'no_offers':
                $where .= ' AND NOT ' . $any;
                break;
        }

        return array( $where, $params );
    }

    public static function get_products( $args = array() ) {
        global $wpdb;
        $defaults = array(
            'per_page' => 20,
            'page'     => 1,
            'search'   => '',
            'stock'    => '',
            'orderby'  => 'id',
            'order'    => 'DESC',
        );
        $args = wp_parse_args( $args, $defaults );

        list( $where, $params ) = self::build_product_where( (string) $args['search'], (string) $args['stock'] );

        $allowed_orderby = array( 'id', 'title', 'created_at', 'last_updated', 'rating' );
        $orderby = in_array( $args['orderby'], $allowed_orderby ) ? $args['orderby'] : 'id';
        $order   = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

        $offset = ( absint( $args['page'] ) - 1 ) * absint( $args['per_page'] );

        $sql = $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ravn_products{$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
            array_merge( $params, array( absint( $args['per_page'] ), $offset ) )
        );

        return $wpdb->get_results( $sql );
    }

    public static function count_products( $search = '', $stock = '' ) {
        global $wpdb;
        list( $where, $params ) = self::build_product_where( (string) $search, (string) $stock );
        $sql = "SELECT COUNT(*) FROM {$wpdb->prefix}ravn_products{$where}";
        return (int) $wpdb->get_var( $params ? $wpdb->prepare( $sql, $params ) : $sql );
    }

    public static function get_product( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ravn_products WHERE id = %d", $id ) );
    }

    public static function insert_product( $data ) {
        global $wpdb;
        $wpdb->insert( $wpdb->prefix . 'ravn_products', $data );
        return $wpdb->insert_id;
    }

    public static function update_product( $id, $data ) {
        global $wpdb;
        return $wpdb->update( $wpdb->prefix . 'ravn_products', $data, array( 'id' => $id ) );
    }

    public static function delete_product( $id ) {
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'ravn_offers',    array( 'product_id' => $id ) );
        $wpdb->delete( $wpdb->prefix . 'ravn_clicks',    array( 'product_id' => $id ) );
        $wpdb->delete( $wpdb->prefix . 'ravn_stock_log', array( 'product_id' => $id ) );
        return $wpdb->delete( $wpdb->prefix . 'ravn_products', array( 'id' => $id ) );
    }

    // ── Offers ──────────────────────────────────────────────────────────────

    public static function get_offers( $product_id, $sort = 'default' ) {
        global $wpdb;
        $allowed_sorts = array(
            'price_asc'  => 'price ASC, sort_order ASC',
            'price_desc' => 'price DESC, sort_order ASC',
            'name_asc'   => 'seller_name ASC',
            'default'    => 'sort_order ASC, id ASC',
        );
        $order_sql = isset( $allowed_sorts[ $sort ] ) ? $allowed_sorts[ $sort ] : $allowed_sorts['default'];
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ravn_offers WHERE product_id = %d ORDER BY {$order_sql}",
            $product_id
        ) );
    }

    public static function get_offer( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ravn_offers WHERE id = %d", $id ) );
    }

    public static function insert_offer( $data ) {
        global $wpdb;
        $wpdb->insert( $wpdb->prefix . 'ravn_offers', $data );
        return $wpdb->insert_id;
    }

    public static function update_offer( $id, $data ) {
        global $wpdb;
        return $wpdb->update( $wpdb->prefix . 'ravn_offers', $data, array( 'id' => $id ) );
    }

    public static function delete_offer( $id ) {
        global $wpdb;
        return $wpdb->delete( $wpdb->prefix . 'ravn_offers', array( 'id' => $id ) );
    }

    public static function delete_offers_by_product( $product_id ) {
        global $wpdb;
        return $wpdb->delete( $wpdb->prefix . 'ravn_offers', array( 'product_id' => $product_id ) );
    }

    // ── Clicks ──────────────────────────────────────────────────────────────

    public static function log_click( $offer_id, $product_id, $page_url = '', $sub_id = '' ) {
        global $wpdb;
        $ip_hash = hash( 'sha256', isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '' );
        $wpdb->insert( $wpdb->prefix . 'ravn_clicks', array(
            'offer_id'   => $offer_id,
            'product_id' => $product_id,
            'page_url'   => esc_url_raw( $page_url ),
            'sub_id'     => sanitize_text_field( $sub_id ),
            'ip_hash'    => $ip_hash,
            'clicked_at' => current_time( 'mysql' ),
        ) );
    }

    public static function get_top_clicks( $limit = 8 ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT c.offer_id, c.product_id, COUNT(*) as click_count,
                    o.seller_name, p.title as product_title
             FROM {$wpdb->prefix}ravn_clicks c
             LEFT JOIN {$wpdb->prefix}ravn_offers o ON o.id = c.offer_id
             LEFT JOIN {$wpdb->prefix}ravn_products p ON p.id = c.product_id
             GROUP BY c.offer_id
             ORDER BY click_count DESC
             LIMIT %d",
            $limit
        ) );
    }

    public static function get_clicks_paged( $per_page = 20, $page = 1 ) {
        global $wpdb;
        $offset = ( $page - 1 ) * $per_page;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT c.*, o.seller_name, o.seller_domain, p.title as product_title
             FROM {$wpdb->prefix}ravn_clicks c
             LEFT JOIN {$wpdb->prefix}ravn_offers o ON o.id = c.offer_id
             LEFT JOIN {$wpdb->prefix}ravn_products p ON p.id = c.product_id
             ORDER BY c.clicked_at DESC
             LIMIT %d OFFSET %d",
            $per_page, $offset
        ) );
    }

    public static function get_stock_log_paged( $per_page = 20, $page = 1 ) {
        global $wpdb;
        $offset = ( $page - 1 ) * $per_page;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT sl.*, o.seller_name, o.seller_domain, p.title as product_title
             FROM {$wpdb->prefix}ravn_stock_log sl
             LEFT JOIN {$wpdb->prefix}ravn_offers o ON o.id = sl.offer_id
             LEFT JOIN {$wpdb->prefix}ravn_products p ON p.id = sl.product_id
             ORDER BY sl.logged_at DESC
             LIMIT %d OFFSET %d",
            $per_page, $offset
        ) );
    }

    public static function log_stock_change( $offer_id, $product_id, $old_status, $new_status ) {
        global $wpdb;
        $wpdb->insert( $wpdb->prefix . 'ravn_stock_log', array(
            'offer_id'   => $offer_id,
            'product_id' => $product_id,
            'old_status' => $old_status,
            'new_status' => $new_status,
            'logged_at'  => current_time( 'mysql' ),
        ) );
    }

    public static function clear_stats() {
        global $wpdb;
        $wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}ravn_clicks" );
        $wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}ravn_stock_log" );
    }

    // ── FAQ ─────────────────────────────────────────────────────────────────

    public static function get_faq_by_post( $post_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ravn_faq WHERE post_id = %d ORDER BY sort_order ASC, id ASC",
            $post_id
        ) );
    }

    public static function save_faq_for_post( $post_id, $items ) {
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'ravn_faq', array( 'post_id' => $post_id ) );
        $order = 0;
        foreach ( $items as $item ) {
            if ( empty( $item['question'] ) ) continue;
            $wpdb->insert( $wpdb->prefix . 'ravn_faq', array(
                'post_id'    => $post_id,
                'question'   => sanitize_text_field( $item['question'] ),
                'answer'     => wp_kses_post( $item['answer'] ),
                'sort_order' => $order++,
            ) );
        }
    }
}
