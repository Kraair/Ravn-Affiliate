<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Ravn_Import {

    public function __construct() {
        add_action( 'admin_post_ravn_import_file',        array( $this, 'handle_file_import' ) );
        add_action( 'admin_post_ravn_import_feed',        array( $this, 'handle_feed_import' ) );
        add_action( 'admin_post_ravn_import_tradetracker',array( $this, 'handle_tradetracker_import' ) );
        add_action( 'admin_post_ravn_import_daisycon',    array( $this, 'handle_daisycon_import' ) );
    }

    private function redirect_back( $status, $message ) {
        $url = add_query_arg( array(
            'page'       => 'ravn-affiliate-import',
            'ravn_status'  => $status,
            'ravn_message' => urlencode( $message ),
        ), admin_url( 'admin.php' ) );
        wp_redirect( $url );
        exit;
    }

    public function handle_file_import() {
        check_admin_referer( 'ravn_import_file' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Geen toegang' );

        if ( empty( $_FILES['ravn_import_file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['ravn_import_file']['tmp_name'] ) ) {
            $this->redirect_back( 'error', 'Geen geldig bestand geüpload.' );
        }

        $file = $_FILES['ravn_import_file'];

        // 1. Upload-fout van PHP zelf afvangen.
        if ( ! empty( $file['error'] ) ) {
            $this->redirect_back( 'error', 'Upload mislukt (foutcode ' . intval( $file['error'] ) . ').' );
        }

        // 2. Groottelimiet (5 MB).
        $max_bytes = 5 * 1024 * 1024;
        if ( $file['size'] > $max_bytes ) {
            $this->redirect_back( 'error', 'Bestand is te groot (max. 5 MB).' );
        }

        // 3. Extensie- en MIME-whitelist via WordPress' eigen controle.
        $allowed = array(
            'csv' => 'text/csv',
            'xml' => 'text/xml',
            'txt' => 'text/plain',
        );
        $check = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], $allowed );
        $ext   = $check['ext'] ? strtolower( $check['ext'] ) : strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );

        if ( ! isset( $allowed[ $ext ] ) ) {
            $this->redirect_back( 'error', 'Alleen CSV- of XML-bestanden zijn toegestaan.' );
        }

        // 4. Extra MIME-verificatie op de daadwerkelijke bestandsinhoud.
        if ( function_exists( 'finfo_open' ) ) {
            $finfo = finfo_open( FILEINFO_MIME_TYPE );
            $mime  = finfo_file( $finfo, $file['tmp_name'] );
            finfo_close( $finfo );
            $mime_ok = array( 'text/csv', 'text/plain', 'text/xml', 'application/xml', 'application/csv', 'application/octet-stream' );
            if ( $mime && ! in_array( $mime, $mime_ok, true ) ) {
                $this->redirect_back( 'error', 'Bestandstype wordt niet vertrouwd (' . esc_html( $mime ) . ').' );
            }
        }

        $content  = file_get_contents( $file['tmp_name'] );
        if ( false === $content || '' === trim( $content ) ) {
            $this->redirect_back( 'error', 'Het bestand is leeg of onleesbaar.' );
        }

        $sep      = sanitize_text_field( wp_unslash( $_POST['ravn_delimiter'] ?? ';' ) );
        $network  = sanitize_text_field( wp_unslash( $_POST['ravn_network'] ?? '' ) );

        // Alleen bekende scheidingstekens toestaan.
        if ( ! in_array( $sep, array( ';', ',', "\t" ), true ) ) {
            $sep = ';';
        }

        if ( 'xml' === $ext ) {
            $rows = Ravn_API::parse_xml_feed( $content );
        } else {
            $rows = Ravn_API::parse_csv_feed( $content, $sep );
        }

        $imported = $this->import_rows( $rows, $network );
        $this->redirect_back( 'success', $imported . ' producten geïmporteerd.' );
    }

    public function handle_feed_import() {
        check_admin_referer( 'ravn_import_feed' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Geen toegang' );

        $url     = esc_url_raw( wp_unslash( $_POST['ravn_feed_url'] ?? '' ) );
        $network = sanitize_text_field( wp_unslash( $_POST['ravn_network'] ?? '' ) );
        if ( ! $url ) $this->redirect_back( 'error', 'Geen URL opgegeven.' );

        // Alleen http(s) toestaan.
        $scheme = wp_parse_url( $url, PHP_URL_SCHEME );
        if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
            $this->redirect_back( 'error', 'Alleen http- of https-URLs zijn toegestaan.' );
        }

        // SSRF-bescherming: blokkeer interne/private IP-adressen.
        $host = wp_parse_url( $url, PHP_URL_HOST );
        if ( $host ) {
            $ip = gethostbyname( $host );
            if ( $ip && filter_var( $ip, FILTER_VALIDATE_IP ) &&
                 ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
                $this->redirect_back( 'error', 'Deze URL verwijst naar een intern adres en is geblokkeerd.' );
            }
        }

        $rows     = Ravn_API::daisycon_get_feed( $url );
        $imported = $this->import_rows( $rows, $network );
        $this->redirect_back( 'success', $imported . ' producten geïmporteerd.' );
    }

    public function handle_tradetracker_import() {
        check_admin_referer( 'ravn_import_tradetracker' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Geen toegang' );

        $ean   = sanitize_text_field( wp_unslash( $_POST['ravn_ean'] ?? '' ) );
        $rows  = Ravn_API::tradetracker_get_products( $ean );
        $count = 0;

        foreach ( $rows as $row ) {
            $row   = (array) $row;
            $title = $row['name'] ?? $row['title'] ?? '';
            if ( ! $title ) continue;
            $this->upsert_product_from_row( $row, 'tradetracker' );
            $count++;
        }
        $this->redirect_back( 'success', $count . ' producten geïmporteerd via TradeTracker.' );
    }

    public function handle_daisycon_import() {
        check_admin_referer( 'ravn_import_daisycon' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Geen toegang' );

        $feed_url = Ravn_Options::get( 'daisycon_feed_url' );
        $rows     = Ravn_API::daisycon_get_feed( $feed_url );
        $imported = $this->import_rows( $rows, 'daisycon' );
        $this->redirect_back( 'success', $imported . ' producten geïmporteerd via Daisycon.' );
    }

    private function import_rows( $rows, $network = '' ) {
        $count = 0;
        foreach ( $rows as $row ) {
            if ( $this->upsert_product_from_row( (array) $row, $network ) ) {
                $count++;
            }
        }
        return $count;
    }

    private function upsert_product_from_row( $row, $network = '' ) {
        // Mapping van veelvoorkomende kolomnamen
        $title   = $row['name'] ?? $row['title'] ?? $row['Name'] ?? $row['Title'] ?? $row['product_name'] ?? '';
        $ean     = $row['ean'] ?? $row['EAN'] ?? $row['gtin'] ?? $row['barcode'] ?? '';
        $image   = $row['image'] ?? $row['imageUrl'] ?? $row['Image'] ?? $row['image_url'] ?? '';
        $desc    = $row['description'] ?? $row['Description'] ?? $row['shortDescription'] ?? '';
        $price   = floatval( $row['price'] ?? $row['Price'] ?? $row['priceInclVat'] ?? 0 );
        $url     = $row['url'] ?? $row['Url'] ?? $row['deepLink'] ?? $row['affiliate_url'] ?? '';
        $seller  = $row['shopName'] ?? $row['shop'] ?? $row['Seller'] ?? $row['merchant'] ?? '';
        $stock   = isset( $row['inStock'] ) ? ( $row['inStock'] ? 'in_stock' : 'out_of_stock' ) : 'unknown';

        if ( ! $title ) return false;

        // Zoek bestaand product op EAN
        global $wpdb;
        $existing = null;
        if ( $ean ) {
            $existing = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ravn_products WHERE ean = %s LIMIT 1", $ean
            ) );
        }

        if ( $existing ) {
            $product_id = $existing->id;
            Ravn_Database::update_product( $product_id, array(
                'title'        => sanitize_text_field( $title ),
                'image_url'    => esc_url_raw( $image ),
                'description'  => wp_kses_post( $desc ),
                'last_updated' => current_time( 'mysql' ),
            ) );
        } else {
            $product_id = Ravn_Database::insert_product( array(
                'title'       => sanitize_text_field( $title ),
                'ean'         => sanitize_text_field( $ean ),
                'image_url'   => esc_url_raw( $image ),
                'description' => wp_kses_post( $desc ),
                'cloak_slug'  => sanitize_title( $title ),
                'created_at'  => current_time( 'mysql' ),
            ) );
        }

        if ( ! $product_id ) return false;

        // Upsert aanbieder
        if ( $url ) {
            $domain = $seller ? strtolower( sanitize_text_field( $seller ) ) : '';
            if ( ! $domain && $url ) {
                $parsed = wp_parse_url( $url );
                $domain = isset( $parsed['host'] ) ? $parsed['host'] : '';
            }

            $existing_offer = $wpdb->get_row( $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ravn_offers WHERE product_id = %d AND seller_domain = %s LIMIT 1",
                $product_id, $domain
            ) );

            $offer_data = array(
                'price'         => $price ?: null,
                'affiliate_url' => esc_url_raw( $url ),
                'stock_status'  => $stock,
                'network'       => $network,
                'updated_at'    => current_time( 'mysql' ),
            );

            if ( $existing_offer ) {
                Ravn_Database::update_offer( $existing_offer->id, $offer_data );
            } else {
                Ravn_Database::insert_offer( array_merge( $offer_data, array(
                    'product_id'    => $product_id,
                    'seller_name'   => sanitize_text_field( $seller ),
                    'seller_domain' => $domain,
                ) ) );
            }
        }

        return true;
    }
}
