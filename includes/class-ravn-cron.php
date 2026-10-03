<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Ravn_Cron {

    const HOOK_RUN      = 'ravn_cron_fetch';     // Terugkerend gepland event.
    const HOOK_CONTINUE = 'ravn_cron_continue';  // Eenmalig: volgende deel van een lopende run.
    const OPT_STATE     = 'ravn_cron_state';     // Voortgang van de huidige run.
    const OPT_LAST_TS   = 'ravn_last_cron_ts';   // Unix-tijd van de laatste voltooide run.
    const LOCK          = 'ravn_cron_lock';      // Voorkomt dat twee batches tegelijk draaien.
    const BATCH_SIZE    = 200;                   // Max. producten per query.

    /** Gecachete Daisycon-feed binnen één batch (anders per aanbieding opnieuw gedownload). */
    private $daisycon_feed = null;

    public function __construct() {
        add_action( self::HOOK_RUN,      array( $this, 'run_fetch' ) );
        add_action( self::HOOK_CONTINUE, array( $this, 'run_batch' ) );
        add_filter( 'cron_schedules',    array( $this, 'add_schedules' ) );
        add_action( 'init',              array( $this, 'maybe_schedule' ) );
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
            wp_clear_scheduled_hook( self::HOOK_RUN );
            wp_clear_scheduled_hook( self::HOOK_CONTINUE );
            return;
        }

        if ( ! wp_next_scheduled( self::HOOK_RUN ) ) {
            $this->schedule_recurring( $opts );
        }

        // Vangnet: WP-Cron draait niet (uitgeschakeld, loopback geblokkeerd)
        // of loopt achter. Dan voeren we een deel zelf uit na het versturen
        // van de pagina, zodat het ook zonder serverinstellingen werkt.
        if ( $this->needs_fallback() ) {
            add_action( 'shutdown', array( $this, 'fallback_run' ), 100 );
        }
    }

    private function schedule_recurring( $opts ) {
        $interval = $this->map_interval( $opts['cron_interval'] );
        $next     = $this->next_run_time( $opts['cron_start_time'], $this->interval_seconds( $interval ) );
        wp_schedule_event( $next, $interval, self::HOOK_RUN );
    }

    /**
     * Eerstvolgende moment (UTC-timestamp) op starttijd + n × interval.
     * De starttijd wordt in de tijdzone van de site gelezen.
     */
    private function next_run_time( $time_str, $interval_secs ) {
        $parts = explode( ':', (string) $time_str );
        $hour  = isset( $parts[0] ) ? intval( $parts[0] ) : 1;
        $min   = isset( $parts[1] ) ? intval( $parts[1] ) : 0;

        $dt = new DateTime( 'now', wp_timezone() );
        $dt->setTime( max( 0, min( 23, $hour ) ), max( 0, min( 59, $min ) ), 0 );
        $anchor = $dt->getTimestamp();
        $now    = time();

        if ( $anchor > $now ) {
            return $anchor;
        }
        $interval_secs = max( HOUR_IN_SECONDS, $interval_secs );
        return $anchor + (int) ceil( ( $now - $anchor ) / $interval_secs ) * $interval_secs;
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

    private function interval_seconds( $schedule ) {
        $schedules = wp_get_schedules();
        return isset( $schedules[ $schedule ] ) ? (int) $schedules[ $schedule ]['interval'] : DAY_IN_SECONDS;
    }

    // ─── Status ──────────────────────────────────────────────────────────────

    private function get_state() {
        $state = get_option( self::OPT_STATE );
        return is_array( $state ) ? $state : null;
    }

    private function save_state( $state ) {
        update_option( self::OPT_STATE, $state, false );
    }

    /**
     * Gegevens voor de statusweergave op de instellingen-tab.
     */
    public static function status() {
        global $wpdb;
        $state = get_option( self::OPT_STATE );
        $due   = wp_next_scheduled( self::HOOK_RUN );
        $last  = (int) get_option( self::OPT_LAST_TS, 0 );

        $status = array(
            'wpcron_disabled' => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
            'overdue'         => $due && $due < time() - 10 * MINUTE_IN_SECONDS && $last < $due,
            'running'         => false,
            'done'            => 0,
            'total'           => 0,
        );

        if ( is_array( $state ) ) {
            $status['running'] = empty( $state['paused'] );
            $status['total']   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}ravn_products" );
            $status['done']    = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}ravn_products WHERE id <= %d",
                intval( $state['cursor'] )
            ) );
        }
        return $status;
    }

    /**
     * Moet deze request een deel van het werk zelf uitvoeren omdat WP-Cron
     * het laat liggen?
     */
    private function needs_fallback() {
        if ( get_transient( self::LOCK ) ) {
            return false;
        }

        // Alleen zonder fastcgi_finish_request() beperken we dit tot de
        // beheeromgeving, zodat bezoekers de pagina nooit zien hangen.
        if ( ! $this->can_finish_request() && ! is_admin() ) {
            return false;
        }

        $state = $this->get_state();
        if ( $state && empty( $state['paused'] ) ) {
            // Lopende run die al 5 minuten geen voortgang heeft gemaakt.
            return ( time() - intval( $state['updated'] ) ) > 5 * MINUTE_IN_SECONDS;
        }

        $due  = wp_next_scheduled( self::HOOK_RUN );
        $last = (int) get_option( self::OPT_LAST_TS, 0 );
        return $due && $due < time() - 10 * MINUTE_IN_SECONDS && $last < $due;
    }

    private function can_finish_request() {
        return function_exists( 'fastcgi_finish_request' ) || function_exists( 'litespeed_finish_request' );
    }

    public function fallback_run() {
        if ( function_exists( 'fastcgi_finish_request' ) ) {
            fastcgi_finish_request();
        } elseif ( function_exists( 'litespeed_finish_request' ) ) {
            litespeed_finish_request();
        }
        ignore_user_abort( true );

        $this->run_fetch();

        // WP-Cron liet het terugkerende event liggen; zet het opnieuw uit,
        // zodat de volgende ronde op de juiste tijd staat.
        $due = wp_next_scheduled( self::HOOK_RUN );
        if ( $due && $due < time() ) {
            wp_clear_scheduled_hook( self::HOOK_RUN );
            $this->schedule_recurring( Ravn_Options::get_all() );
        }
    }

    // ─── Uitvoeren ───────────────────────────────────────────────────────────

    /**
     * Start (of hervat) een run. Wordt aangeroepen door het geplande event.
     */
    public function run_fetch() {
        $state = $this->get_state();

        if ( ! $state ) {
            // Net al klaar (bijv. door het vangnet)? Dan niet nog een keer.
            $last = (int) get_option( self::OPT_LAST_TS, 0 );
            if ( $last && ( time() - $last ) < 15 * MINUTE_IN_SECONDS ) {
                return;
            }
            $state = array( 'cursor' => 0, 'started' => time(), 'updated' => time(), 'timeouts' => 0 );
        } elseif ( ! empty( $state['paused'] ) ) {
            // Gepauzeerd na te veel time-outs: ga verder waar we bleven.
            $state['paused']  = false;
            $state['started'] = time();
        }

        $this->save_state( $state );
        $this->run_batch();
    }

    /**
     * Verwerk producten tot de tijdsbudget op is. Is de lijst nog niet klaar,
     * dan wordt een vervolg ingepland (en hervat de run bij het volgende
     * product, niet weer bij het eerste).
     */
    public function run_batch() {
        global $wpdb;

        $state = $this->get_state();
        if ( ! $state || ! empty( $state['paused'] ) || get_transient( self::LOCK ) ) {
            return;
        }

        $opts     = Ravn_Options::get_all();
        $networks = is_array( $opts['cron_networks'] ) ? $opts['cron_networks'] : array();
        $run_cap  = intval( $opts['cron_timeout'] ) * MINUTE_IN_SECONDS;
        $max_to   = intval( $opts['cron_max_timeouts'] );

        // Totale looptijd van deze run overschreden?
        if ( $run_cap > 0 && ( time() - intval( $state['started'] ) ) > $run_cap ) {
            if ( $max_to > 0 && intval( $state['timeouts'] ) < $max_to ) {
                $state['timeouts']++;
                $state['started'] = time();
            } else {
                // Pauzeer; de volgende geplande run gaat verder bij het
                // huidige product, zodat de rest van de lijst niet verhongert.
                $state['paused']   = true;
                $state['timeouts'] = 0;
                $this->save_state( $state );
                return;
            }
        }

        $limit  = (int) ini_get( 'max_execution_time' );
        $budget = $limit > 0 ? min( 20, max( 5, (int) ( $limit * 0.5 ) ) ) : 20;
        $begin  = time();

        set_transient( self::LOCK, 1, $budget + MINUTE_IN_SECONDS );
        $this->daisycon_feed = null;

        $finished = false;
        while ( true ) {
            $products = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ravn_products WHERE id > %d ORDER BY id ASC LIMIT %d",
                intval( $state['cursor'] ),
                self::BATCH_SIZE
            ) );

            if ( empty( $products ) ) {
                $finished = true;
                break;
            }

            $out_of_time = false;
            foreach ( $products as $product ) {
                if ( ( time() - $begin ) >= $budget ) {
                    $out_of_time = true;
                    break;
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

                Ravn_Database::update_product( $product->id, array( 'last_updated' => current_time( 'mysql' ) ) );

                $state['cursor']  = intval( $product->id );
                $state['updated'] = time();
            }

            $this->save_state( $state );
            if ( $out_of_time ) break;
        }

        delete_transient( self::LOCK );
        $this->daisycon_feed = null;

        if ( $finished ) {
            delete_option( self::OPT_STATE );
            delete_option( 'ravn_cron_timeout_count' );
            update_option( 'ravn_last_cron_run', current_time( 'mysql' ) );
            update_option( self::OPT_LAST_TS, time() );
            return;
        }

        if ( ! wp_next_scheduled( self::HOOK_CONTINUE ) ) {
            wp_schedule_single_event( time() + 10, self::HOOK_CONTINUE );
        }
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
                if ( null === $this->daisycon_feed ) {
                    $this->daisycon_feed = Ravn_API::daisycon_get_feed();
                }
                $feed_data = $this->daisycon_feed;
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
     * Handmatig ophalen (admin knop): start altijd een verse run en verwerkt
     * het eerste deel meteen; de rest loopt op de achtergrond door.
     */
    public static function run_now() {
        delete_transient( self::LOCK );
        update_option( self::OPT_STATE, array(
            'cursor'   => 0,
            'started'  => time(),
            'updated'  => time(),
            'timeouts' => 0,
        ), false );

        $instance = new self();
        $instance->run_batch();
    }
}
