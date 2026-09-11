<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Ravn_Cron {

    public function __construct() {
        add_action( 'ravn_cron_fetch',           array( $this, 'run_fetch' ) );
        add_filter( 'cron_schedules',          array( $this, 'add_schedules' ) );
        add_action( 'init',                    array( $this, 'maybe_schedule' ) );
    }

    public function add_schedules( $schedules ) {
        $schedules['ravn_twicedaily'] = array(
            'interval' => 12 * HOUR_IN_SECONDS,
            'display'  => 'Tweemaal per dag',
        );
        $schedules['ravn_6hours'] = array(
            'interval' => 6 * HOUR_IN_SECONDS,
            'display'  => 'Iedere 6 uur',
        );
        return $schedules;
    }

    public function maybe_schedule() {
        $opts = Ravn_Options::get_all();

        if ( empty( $opts['cron_active'] ) ) {
            wp_clear_scheduled_hook( 'ravn_cron_fetch' );
            return;
        }

        if ( ! wp_next_scheduled( 'ravn_cron_fetch' ) ) {
            $start_time = $this->get_next_start_time( $opts['cron_start_time'] );
            wp_schedule_event( $start_time, $this->map_interval( $opts['cron_interval'] ), 'ravn_cron_fetch' );
        }
    }

    private function get_next_start_time( $time_str ) {
        $parts = explode( ':', $time_str );
        $hour  = isset( $parts[0] ) ? intval( $parts[0] ) : 1;
        $min   = isset( $parts[1] ) ? intval( $parts[1] ) : 0;

        $now       = current_time( 'timestamp' );
        $today_run = mktime( $hour, $min, 0, date( 'n', $now ), date( 'j', $now ), date( 'Y', $now ) );

        return $today_run > $now ? $today_run : $today_run + DAY_IN_SECONDS;
    }

    private function map_interval( $interval ) {
        $map = array(
            'hourly'     => 'hourly',
            '6hours'     => 'ravn_6hours',
            'twicedaily' => 'ravn_twicedaily',
            'daily'      => 'daily',
        );
        return isset( $map[ $interval ] ) ? $map[ $interval ] : 'daily';
    }

    public function run_fetch() {
        $opts     = Ravn_Options::get_all();
        $timeout  = intval( $opts['cron_timeout'] ) * MINUTE_IN_SECONDS;
        $max_to   = intval( $opts['cron_max_timeouts'] );
        $networks = is_array( $opts['cron_networks'] ) ? $opts['cron_networks'] : array();

        // Timeout bewaker
        $to_count = intval( get_option( 'ravn_cron_timeout_count', 0 ) );
        if ( $max_to > 0 && $to_count >= $max_to ) {
            // Reset teller
            delete_option( 'ravn_cron_timeout_count' );
            return;
        }

        $start     = time();
        $products  = Ravn_Database::get_products( array( 'per_page' => 9999 ) );

        foreach ( $products as $product ) {
            // Timeout check
            if ( $timeout > 0 && ( time() - $start ) > $timeout ) {
                update_option( 'ravn_cron_timeout_count', $to_count + 1 );
                return;
            }

            $offers = Ravn_Database::get_offers( $product->id );
            foreach ( $offers as $offer ) {
                if ( ! in_array( $offer->network, $networks, true ) ) continue;
                $this->update_offer( $offer, $product, $opts );

                // Bol.com's Marketing Catalog API hanteert een limiet van
                // 10 requests per seconde per endpoint (zie officiële
                // rate-limits-documentatie). Een korte pauze na elke
                // bol-aanroep houdt de cron ruim binnen dat budget, ook al
                // vangt Ravn_API::bol_rate_limited_get() incidentele 429's
                // zelf al op.
                if ( 'bol' === $offer->network ) {
                    usleep( 120000 ); // 0,12s ≈ ruim onder 10/sec
                }
            }

            // Update last_updated
            Ravn_Database::update_product( $product->id, array( 'last_updated' => current_time( 'mysql' ) ) );
        }

        delete_option( 'ravn_cron_timeout_count' );
        update_option( 'ravn_last_cron_run', current_time( 'mysql' ) );
    }

    private function update_offer( $offer, $product, $opts ) {
        $updated      = array();
        $old_stock    = $offer->stock_status;
        $new_stock    = $old_stock;

        switch ( $offer->network ) {
            case 'bol':
                if ( ! empty( $product->ean ) ) {
                    // bol_get_offers_for_ean() geeft nu altijd 0 of 1 aanbieding
                    // terug (het "beste" aanbod), met de daadwerkelijke velden
                    // uit de officiële Marketing Catalog API-respons.
                    $bol_offers = Ravn_API::bol_get_offers_for_ean( $product->ean );
                    if ( ! empty( $bol_offers[0] ) ) {
                        $bo = $bol_offers[0];

                        if ( isset( $bo['price'] ) ) {
                            $updated['price'] = floatval( $bo['price'] );
                        }

                        // De API geeft geen expliciete voorraadstatus terug,
                        // alleen een vrije leverbeschrijving. We leiden een
                        // grove status af: aanwezigheid van een prijs +
                        // leverbeschrijving duidt op beschikbaarheid.
                        $delivery = isset( $bo['deliveryDescription'] ) ? strtolower( $bo['deliveryDescription'] ) : '';
                        if ( isset( $bo['price'] ) && $delivery && false === strpos( $delivery, 'niet leverbaar' ) ) {
                            $new_stock = 'in_stock';
                        } elseif ( isset( $bo['price'] ) ) {
                            $new_stock = 'unknown';
                        } else {
                            $new_stock = 'out_of_stock';
                        }
                        $updated['stock_status'] = $new_stock;

                        // Vul een basis-URL in als de aanbieder er nog geen heeft;
                        // build_url() zet deze bij weergave om naar een tracking-link.
                        if ( empty( $offer->affiliate_url ) ) {
                            $updated['affiliate_url'] = Ravn_API::bol_url_from_ean( $product->ean );
                        }
                    } else {
                        // Geen aanbieding gevonden (404 of leeg antwoord) = niet leverbaar.
                        $updated['stock_status'] = 'out_of_stock';
                        $new_stock = 'out_of_stock';
                    }
                }
                break;

            case 'amazon':
                // Amazon update via ASIN (stored in ean veld)
                break;

            case 'daisycon':
                $feed_data = Ravn_API::daisycon_get_feed();
                foreach ( $feed_data as $row ) {
                    if ( empty( $row['EAN'] ) || $row['EAN'] !== $product->ean ) continue;
                    if ( ! empty( $row['Price'] ) ) $updated['price'] = floatval( $row['Price'] );
                    if ( isset( $row['InStock'] ) ) {
                        $updated['stock_status'] = $row['InStock'] ? 'in_stock' : 'out_of_stock';
                        $new_stock = $updated['stock_status'];
                    }
                    break;
                }
                break;
        }

        if ( ! empty( $updated ) ) {
            $updated['updated_at'] = current_time( 'mysql' );
            Ravn_Database::update_offer( $offer->id, $updated );
        }

        // Voorraad tracking
        if ( ! empty( $opts['stats_stock_tracking'] ) && $new_stock !== $old_stock ) {
            Ravn_Database::log_stock_change( $offer->id, $product->id, $old_stock, $new_stock );
        }
    }

    /**
     * Handmatig ophalen (admin knop).
     */
    public static function run_now() {
        $instance = new self();
        $instance->run_fetch();
    }
}
