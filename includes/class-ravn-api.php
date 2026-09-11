<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Ravn_API {

    // ───────────────────────────────────────────────────────────────────────
    //  BOL.COM  (OAuth2 + Marketing Catalog API — voor affiliates)
    //
    //  Let op: bol.com heeft twee gescheiden API's die beide via hetzelfde
    //  OAuth2-tokenendpoint (login.bol.com/token) authenticeren, maar naar
    //  andere data-endpoints wijzen:
    //   - Retailer API (api.bol.com/retailer/*)  → voor verkopers/sellers.
    //   - Marketing Catalog API (api.bol.com/marketing/catalog/*) → voor
    //     affiliates; hiermee haalt deze plugin productdata op.
    //  Client ID/Secret voor de Marketing API vraag je aan vanuit je eigen
    //  bol.com Affiliate-account (niet vanuit een verkoopaccount).
    // ───────────────────────────────────────────────────────────────────────

    private static function bol_get_token() {
        $result = self::bol_get_token_verbose();
        return $result['success'] ? $result['token'] : false;
    }

    /**
     * Haalt een bol.com access token op en geeft gedetailleerde informatie
     * terug over succes/falen, inclusief de exacte foutmelding van bol.com.
     * Gebruikt voor de "Verbinding testen"-knop in de instellingen.
     *
     * @return array {
     *     @type bool   $success
     *     @type string $token    Alleen aanwezig bij succes.
     *     @type string $message  Mensleesbare uitleg (NL).
     *     @type int    $http_code
     * }
     */
    public static function bol_get_token_verbose( $force = false ) {
        if ( ! $force ) {
            $cached = get_transient( 'ravn_bol_token' );
            if ( $cached ) {
                return array( 'success' => true, 'token' => $cached, 'message' => 'Token uit cache (nog geldig).', 'http_code' => 200 );
            }
        }

        $client_id     = trim( (string) Ravn_Options::get( 'bol_client_id' ) );
        $client_secret = trim( (string) Ravn_Options::get( 'bol_client_secret' ) );

        if ( ! $client_id || ! $client_secret ) {
            return array( 'success' => false, 'message' => 'Client ID en/of Client Secret zijn niet ingevuld.', 'http_code' => 0 );
        }

        $response = wp_remote_post( 'https://login.bol.com/token', array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode( $client_id . ':' . $client_secret ),
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ),
            'body'    => 'grant_type=client_credentials',
            'timeout' => 15,
        ) );

        if ( is_wp_error( $response ) ) {
            return array(
                'success'   => false,
                'message'   => 'Geen verbinding met bol.com kunnen maken: ' . $response->get_error_message(),
                'http_code' => 0,
            );
        }

        $http_code = (int) wp_remote_retrieve_response_code( $response );
        $body      = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( 200 !== $http_code || empty( $body['access_token'] ) ) {
            $reason = '';
            if ( is_array( $body ) ) {
                // bol.com OAuth-fouten komen meestal als error/error_description.
                $reason = $body['error_description'] ?? $body['error'] ?? '';
            }
            $friendly = self::bol_friendly_auth_error( $http_code, $reason );
            return array(
                'success'   => false,
                'message'   => $friendly,
                'http_code' => $http_code,
            );
        }

        $token = $body['access_token'];
        if ( ! $force ) {
            set_transient( 'ravn_bol_token', $token, max( 1, intval( $body['expires_in'] ?? 300 ) - 60 ) );
        }

        return array(
            'success'   => true,
            'token'     => $token,
            'message'   => 'Verbinding gelukt — token opgehaald.',
            'http_code' => 200,
        );
    }

    /**
     * Vertaalt een HTTP-statuscode + bol.com-foutmelding naar een begrijpelijke
     * Nederlandse uitleg, inclusief het onderscheid Retailer API vs. Affiliate-programma.
     */
    private static function bol_friendly_auth_error( $http_code, $reason ) {
        switch ( $http_code ) {
            case 401:
                return 'Bol.com wijst deze Client ID/Secret af (401 Unauthorized). Controleer of je ze exact hebt gekopieerd zonder spaties, en of ze zijn aangemaakt in je Affiliate-account voor de Marketing API (niet voor een verkoopaccount).';
            case 400:
                return 'Bol.com geeft een ongeldige aanvraag terug (400). Melding: ' . ( $reason ?: 'onbekend' ) . '.';
            case 403:
                return 'Toegang geweigerd (403). Dit account heeft geen rechten voor de Marketing Catalog API. Vraag Client Credentials aan vanuit je Affiliate-account (Account → API toegang → Marketing API), niet vanuit een verkoopaccount voor de Retailer API — dat is een andere koppeling.';
            case 429:
                return 'Te veel aanvragen bij bol.com (429). Probeer het over een paar minuten opnieuw.';
            case 0:
                return $reason ?: 'Kon geen verbinding maken met bol.com.';
            default:
                return 'Bol.com gaf een onverwachte reactie (HTTP ' . $http_code . '). Melding: ' . ( $reason ?: 'geen details' ) . '.';
        }
    }

    /**
     * Zoekt producten in de bol.com catalogus via de Marketing Catalog API.
     *
     * LET OP — te verifiëren: het exacte pad en de parameternaam van het
     * zoek-endpoint zijn bevestigd te bestaan in de officiële documentatie
     * ("search and lists endpoint"), maar zijn hier nog niet één-op-één
     * overgenomen uit de OpenAPI-specificatie. Controleer dit tegen je eigen
     * Developer Center-toegang (api.bol.com/marketing/docs/catalog-api/) en
     * pas $url/$query_params hieronder aan indien nodig, vóórdat je hierop
     * vertrouwt in productie.
     */
    /**
     * Zoekt producten via het bevestigde endpoint:
     * GET /marketing/catalog/v1/products/search
     * (officiële OpenAPI-spec: search-term en country-code zijn verplicht)
     */
    public static function bol_search( $query, $limit = 10 ) {
        $token = self::bol_get_token();
        if ( ! $token ) return array();

        $country = self::bol_country_code();

        $url = add_query_arg(
            array(
                'search-term'    => rawurlencode( $query ),
                'country-code'   => $country,
                'page-size'      => min( 50, max( 1, intval( $limit ) ) ),
                'include-image'  => 'true',
                'include-offer'  => 'true',
                'include-rating' => 'true',
            ),
            'https://api.bol.com/marketing/catalog/v1/products/search'
        );
        $response = self::bol_rate_limited_get( $url, array(
            'Authorization'   => 'Bearer ' . $token,
            'Accept'          => 'application/json',
            'Accept-Language' => self::bol_accept_language( $country ),
        ) );

        if ( is_wp_error( $response ) ) return array();
        if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) return array();

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        // De officiële response geeft resultaten terug onder "results", niet "products".
        $results = isset( $body['results'] ) ? $body['results'] : array();

        // Tijdelijke diagnose: schrijf het eerste rauwe resultaat naar het
        // debug-log, zodat we exact kunnen zien welke velden bol.com
        // teruggeeft (bijv. of "offer" ontbreekt of anders heet dan verwacht).
        // Actief te maken door in wp-config.php WP_DEBUG en WP_DEBUG_LOG op
        // true te zetten. Kan later weer verwijderd worden.
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG && ! empty( $results[0] ) ) {
            error_log( '[Ravn bol_search] Eerste resultaat: ' . wp_json_encode( $results[0] ) );
        }

        return $results;
    }

    /**
     * Landcode voor bol.com-aanvragen (verplicht bij bijna elk endpoint).
     * Alleen NL of BE toegestaan volgens de API-specificatie.
     */
    private static function bol_country_code() {
        $code = strtoupper( trim( (string) Ravn_Options::get( 'bol_country_code', 'NL' ) ) );
        return in_array( $code, array( 'NL', 'BE' ), true ) ? $code : 'NL';
    }

    /**
     * Bepaalt een geldige Accept-Language-waarde op basis van de landcode.
     * Toegestane waarden volgens de spec: nl, fr, nl-NL, nl-BE, fr-BE.
     */
    private static function bol_accept_language( $country_code ) {
        return 'BE' === $country_code ? 'nl-BE' : 'nl-NL';
    }

    /**
     * Rate-limit-bewuste GET-wrapper voor de Marketing Catalog API.
     *
     * Volgens de officiële documentatie geldt een limiet van 10 requests per
     * seconde per endpoint, met headers x-ratelimit-remaining/-reset en een
     * 429-status bij overschrijding. Deze wrapper:
     *  - leest de rate-limit headers uit en wacht kort als het budget bijna op is,
     *  - vangt een 429-respons op en probeert het na een korte pauze opnieuw
     *    (maximaal 2 keer), in plaats van de fout meteen door te geven.
     *
     * @param string $url     Volledige request-URL (inclusief query string).
     * @param array  $headers Extra headers (Authorization, Accept, etc.).
     * @return array|WP_Error Het wp_remote_get()-resultaat, of WP_Error.
     */
    private static function bol_rate_limited_get( $url, $headers ) {
        $max_attempts = 3;

        for ( $attempt = 1; $attempt <= $max_attempts; $attempt++ ) {
            $response = wp_remote_get( $url, array(
                'headers' => $headers,
                'timeout' => 15,
            ) );

            if ( is_wp_error( $response ) ) {
                return $response;
            }

            $http_code = (int) wp_remote_retrieve_response_code( $response );

            if ( 429 !== $http_code ) {
                // Budget bijna op? Wacht kort zodat de volgende aanroep
                // (bijv. in een cron-lus) niet meteen tegen een 429 aanloopt.
                $remaining = wp_remote_retrieve_header( $response, 'x-ratelimit-remaining' );
                if ( '' !== $remaining && is_numeric( $remaining ) && intval( $remaining ) <= 1 ) {
                    $reset = wp_remote_retrieve_header( $response, 'x-ratelimit-reset' );
                    $wait  = ( '' !== $reset && is_numeric( $reset ) ) ? min( 2, max( 0, floatval( $reset ) ) ) : 0.2;
                    usleep( (int) ( $wait * 1000000 ) );
                }
                return $response;
            }

            // 429: gebruik x-ratelimit-reset als die er is, anders een oplopende pauze.
            if ( $attempt < $max_attempts ) {
                $reset = wp_remote_retrieve_header( $response, 'x-ratelimit-reset' );
                $wait  = ( '' !== $reset && is_numeric( $reset ) ) ? min( 5, max( 0.2, floatval( $reset ) ) ) : ( 0.5 * $attempt );
                usleep( (int) ( $wait * 1000000 ) );
            } else {
                return $response; // Laat de aanroeper de uiteindelijke 429 afhandelen.
            }
        }

        return $response;
    }

    /**
     * Volledige verbindingstest voor de "Verbinding testen"-knop:
     * 1) haalt een token op (verifieert Client ID/Secret),
     * 2) doet een lichte testaanvraag op de Retailer API (verifieert rechten/scope).
     * Geeft een array met success/message terug voor directe weergave aan de gebruiker.
     */
    public static function bol_test_connection() {
        $token_result = self::bol_get_token_verbose( true ); // forceer verse token, geen cache

        if ( ! $token_result['success'] ) {
            return array(
                'success' => false,
                'step'    => 'authenticatie',
                'message' => $token_result['message'],
            );
        }

        // Stap 2: test of het token daadwerkelijk toegang geeft tot de Marketing
        // Catalog API. We gebruiken een evident niet-bestaand EAN: een 404 is dan
        // een gezond teken (het endpoint werkt, dit EAN bestaat simpelweg niet),
        // terwijl 401/403 wijst op een verkeerd type credentials of ontbrekende
        // rechten voor de Marketing API. country-code is een verplichte parameter
        // volgens de officiële OpenAPI-spec — zonder deze geeft de API een 400
        // "missing required fields" terug, wat geen echte verbindingsfout is.
        $test_ean = '0000000000000';
        $country  = self::bol_country_code();
        $url      = add_query_arg( array( 'country-code' => $country ), 'https://api.bol.com/marketing/catalog/v1/products/' . $test_ean );
        $response = wp_remote_get(
            $url,
            array(
                'headers' => array(
                    'Authorization'   => 'Bearer ' . $token_result['token'],
                    'Accept'          => 'application/json',
                    'Accept-Language' => self::bol_accept_language( $country ),
                ),
                'timeout' => 15,
            )
        );

        if ( is_wp_error( $response ) ) {
            return array(
                'success' => false,
                'step'    => 'data-opvraging',
                'message' => 'Token opgehaald, maar geen verbinding met de Marketing Catalog API: ' . $response->get_error_message(),
            );
        }

        $http_code = (int) wp_remote_retrieve_response_code( $response );

        // 200 (onwaarschijnlijk voor dit test-EAN) of 404 (EAN niet gevonden)
        // betekenen allebei dat de API zelf bereikbaar is en het token geldig is.
        if ( 200 === $http_code || 404 === $http_code ) {
            return array(
                'success' => true,
                'step'    => 'voltooid',
                'message' => 'Verbinding werkt: authenticatie gelukt en de Marketing Catalog API reageert correct.',
            );
        }

        if ( 401 === $http_code || 403 === $http_code ) {
            return array(
                'success' => false,
                'step'    => 'data-opvraging',
                'message' => 'Authenticatie is gelukt, maar dit account heeft geen toegang tot de Marketing Catalog API (HTTP ' . $http_code . '). Vraag Client Credentials specifiek voor de Marketing API aan vanuit je bol.com Affiliate-account — credentials van een verkoopaccount (Retailer API) werken hier niet.',
            );
        }

        $body   = json_decode( wp_remote_retrieve_body( $response ), true );
        $detail = is_array( $body ) ? ( $body['detail'] ?? $body['title'] ?? '' ) : '';

        if ( 400 === $http_code ) {
            return array(
                'success' => false,
                'step'    => 'data-opvraging',
                'message' => 'Authenticatie is gelukt, maar de aanvraag zelf werd afgewezen (HTTP 400). Melding: ' . ( $detail ?: 'geen details' ) . '. Dit wijst op een ontbrekende of onjuiste parameter in de plugin zelf — controleer of er een pluginupdate beschikbaar is.',
            );
        }

        return array(
            'success' => false,
            'step'    => 'data-opvraging',
            'message' => 'Authenticatie gelukt, maar de testaanvraag gaf HTTP ' . $http_code . ' terug. ' . ( $detail ? 'Melding: ' . $detail : '' ),
        );
    }

    /**
     * Haalt productdetails op via EAN — het primaire input-patroon van de
     * Marketing Catalog API (bevestigd: alle endpoints gebruiken EAN als
     * identifier, patroon /marketing/catalog/v1/products/{ean}/...).
     */
    /**
     * Haalt productdetails op via het bevestigde endpoint:
     * GET /marketing/catalog/v1/products/{ean}
     * (officiële OpenAPI-spec: country-code is verplicht; include-* params
     * gebruiken koppeltekens, geen camelCase).
     */
    public static function bol_get_product_by_ean( $ean ) {
        $token = self::bol_get_token();
        if ( ! $token ) return false;

        $country = self::bol_country_code();

        $url = add_query_arg(
            array(
                'country-code'           => $country,
                'include-specifications' => 'true',
                'include-image'          => 'true',
                'include-offer'          => 'true',
                'include-rating'         => 'true',
            ),
            'https://api.bol.com/marketing/catalog/v1/products/' . rawurlencode( $ean )
        );

        $response = self::bol_rate_limited_get( $url, array(
            'Authorization'   => 'Bearer ' . $token,
            'Accept'          => 'application/json',
            'Accept-Language' => self::bol_accept_language( $country ),
        ) );

        if ( is_wp_error( $response ) ) return false;
        $http_code = (int) wp_remote_retrieve_response_code( $response );
        if ( 200 !== $http_code ) return false;

        return json_decode( wp_remote_retrieve_body( $response ), true );
    }

    /**
     * Haalt de beste aanbieding voor een EAN op via het endpoint dat
     * letterlijk bevestigd is in de bol.com-documentatie:
     * GET /marketing/catalog/v1/products/{ean}/offers/best
     */
    /**
     * Haalt de beste aanbieding voor een EAN op via het endpoint dat
     * bevestigd is in de officiële OpenAPI-spec:
     * GET /marketing/catalog/v1/products/{ean}/offers/best
     * country-code is verplicht; het antwoord bevat o.a. price,
     * strikethroughPrice, deliveryDescription en optioneel seller.name
     * (geen aparte stock/voorraadstatus-veld — bol geeft alleen levertekst).
     */
    public static function bol_get_offers_for_ean( $ean ) {
        $token = self::bol_get_token();
        if ( ! $token ) return array();

        $country = self::bol_country_code();

        $url = add_query_arg(
            array(
                'country-code'   => $country,
                'include-seller' => 'true',
            ),
            'https://api.bol.com/marketing/catalog/v1/products/' . rawurlencode( $ean ) . '/offers/best'
        );

        $response = self::bol_rate_limited_get( $url, array(
            'Authorization'   => 'Bearer ' . $token,
            'Accept'          => 'application/json',
            'Accept-Language' => self::bol_accept_language( $country ),
        ) );

        if ( is_wp_error( $response ) ) return array();
        $http_code = (int) wp_remote_retrieve_response_code( $response );
        // 404 betekent hier: product onbeschikbaar om te bestellen, geen fout.
        if ( 200 !== $http_code ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( '[Ravn bol_get_offers_for_ean] EAN ' . $ean . ' gaf HTTP ' . $http_code . ' terug.' );
            }
            return array();
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[Ravn bol_get_offers_for_ean] EAN ' . $ean . ' respons: ' . wp_json_encode( $body ) );
        }

        if ( ! is_array( $body ) || ! isset( $body['price'] ) ) return array();

        // Eén aanbieding; normaliseer naar array zodat aanroepende code
        // (die een lijst met offers verwacht) blijft werken.
        return array( $body );
    }

    /**
     * Diagnostische variant van bol_get_offers_for_ean(): geeft naast de
     * offer-data ook expliciet de HTTP-status en foutdetails terug, voor
     * gebruik in de EAN-zoeker waar we de gebruiker willen laten zien
     * *waarom* een prijs niet gevonden kon worden. Wordt niet gebruikt door
     * de cron-updater, om die logica puur te houden.
     */
    public static function bol_get_offers_for_ean_diag( $ean ) {
        $token = self::bol_get_token();
        if ( ! $token ) return array( 'price' => null, 'reason' => 'geen geldig token' );

        $country = self::bol_country_code();

        $url = add_query_arg(
            array(
                'country-code'   => $country,
                'include-seller' => 'true',
            ),
            'https://api.bol.com/marketing/catalog/v1/products/' . rawurlencode( $ean ) . '/offers/best'
        );

        $response = self::bol_rate_limited_get( $url, array(
            'Authorization'   => 'Bearer ' . $token,
            'Accept'          => 'application/json',
            'Accept-Language' => self::bol_accept_language( $country ),
        ) );

        if ( is_wp_error( $response ) ) {
            return array( 'price' => null, 'reason' => 'netwerkfout: ' . $response->get_error_message() );
        }

        $http_code = (int) wp_remote_retrieve_response_code( $response );
        if ( 404 === $http_code ) {
            return array( 'price' => null, 'reason' => 'bol.com geeft aan dat dit product momenteel niet besteld kan worden (404 op offers/best)' );
        }
        if ( 200 !== $http_code ) {
            return array( 'price' => null, 'reason' => 'onverwachte HTTP-status ' . $http_code . ' van offers/best-endpoint' );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $body ) || ! isset( $body['price'] ) ) {
            return array( 'price' => null, 'reason' => 'endpoint gaf 200 terug maar zonder prijsveld in de respons' );
        }

        return array( 'price' => $body['price'], 'reason' => '' );
    }

    // ───────────────────────────────────────────────────────────────────────
    //  AMAZON  (PA-API 5.0 + AWS Signature V4)
    // ───────────────────────────────────────────────────────────────────────

    public static function amazon_search( $query, $limit = 10 ) {
        $access_key   = Ravn_Options::get( 'amazon_access_key' );
        $secret_key   = Ravn_Options::get( 'amazon_secret_key' );
        $partner_tag  = Ravn_Options::get( 'amazon_partner_tag' );
        $marketplace  = Ravn_Options::get( 'amazon_marketplace', 'nl' );
        if ( ! $access_key || ! $secret_key || ! $partner_tag ) return array();

        $marketplace_map = array(
            'nl' => array( 'host' => 'webservices.amazon.nl', 'region' => 'eu-west-1' ),
            'de' => array( 'host' => 'webservices.amazon.de', 'region' => 'eu-west-1' ),
            'fr' => array( 'host' => 'webservices.amazon.fr', 'region' => 'eu-west-1' ),
            'uk' => array( 'host' => 'webservices.amazon.co.uk', 'region' => 'eu-west-1' ),
            'us' => array( 'host' => 'webservices.amazon.com', 'region' => 'us-east-1' ),
        );
        $config = isset( $marketplace_map[ $marketplace ] ) ? $marketplace_map[ $marketplace ] : $marketplace_map['nl'];

        $payload = wp_json_encode( array(
            'Keywords'     => $query,
            'Resources'    => array( 'Images.Primary.Large', 'ItemInfo.Title', 'Offers.Listings.Price', 'Offers.Listings.Availability.Type' ),
            'SearchIndex'  => 'All',
            'ItemCount'    => min( $limit, 10 ),
            'PartnerTag'   => $partner_tag,
            'PartnerType'  => 'Associates',
            'Marketplace'  => 'www.amazon.' . ( 'uk' === $marketplace ? 'co.uk' : $marketplace ),
        ) );

        $headers = self::aws_sign( 'POST', 'https://' . $config['host'] . '/paapi5/searchitems', $payload, $config['host'], $config['region'], $access_key, $secret_key, 'SearchItems' );
        $response = wp_remote_post( 'https://' . $config['host'] . '/paapi5/searchitems', array(
            'headers' => $headers,
            'body'    => $payload,
            'timeout' => 20,
        ) );

        if ( is_wp_error( $response ) ) return array();
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        return isset( $body['SearchResult']['Items'] ) ? $body['SearchResult']['Items'] : array();
    }

    private static function aws_sign( $method, $url, $payload, $host, $region, $access_key, $secret_key, $target ) {
        $service   = 'ProductAdvertisingAPI';
        $date      = gmdate( 'Ymd' );
        $datetime  = gmdate( 'Ymd\THis\Z' );
        $hash      = hash( 'sha256', $payload );

        $signed_headers = 'content-encoding;content-type;host;x-amz-date;x-amz-target';
        $canonical = implode( "\n", array(
            $method,
            '/paapi5/' . strtolower( $target ),
            '',
            'content-encoding:amz-1.0',
            'content-type:application/json; charset=utf-8',
            'host:' . $host,
            'x-amz-date:' . $datetime,
            'x-amz-target:com.amazon.paapi5.v1.ProductAdvertisingAPIv1.' . $target,
            '',
            $signed_headers,
            $hash,
        ) );

        $string_to_sign = implode( "\n", array(
            'AWS4-HMAC-SHA256',
            $datetime,
            $date . '/' . $region . '/' . $service . '/aws4_request',
            hash( 'sha256', $canonical ),
        ) );

        $k_date    = hash_hmac( 'sha256', $date,                  'AWS4' . $secret_key, true );
        $k_region  = hash_hmac( 'sha256', $region,                $k_date,              true );
        $k_service = hash_hmac( 'sha256', $service,               $k_region,            true );
        $k_signing = hash_hmac( 'sha256', 'aws4_request',         $k_service,           true );
        $signature = hash_hmac( 'sha256', $string_to_sign,        $k_signing );

        return array(
            'content-encoding' => 'amz-1.0',
            'content-type'     => 'application/json; charset=utf-8',
            'host'             => $host,
            'x-amz-date'       => $datetime,
            'x-amz-target'     => 'com.amazon.paapi5.v1.ProductAdvertisingAPIv1.' . $target,
            'Authorization'    => 'AWS4-HMAC-SHA256 Credential=' . $access_key . '/' . $date . '/' . $region . '/' . $service . '/aws4_request, SignedHeaders=' . $signed_headers . ', Signature=' . $signature,
        );
    }

    // ───────────────────────────────────────────────────────────────────────
    //  TRADETRACKER  (SOAP API)
    // ───────────────────────────────────────────────────────────────────────

    /**
     * Haalt productdata op bij TradeTracker via de SOAP-API.
     *
     * LET OP — gedeeltelijk geverifieerd: het endpoint en de authenticatie-
     * parameters (customerID, passphrase, affiliateSiteID) zijn bevestigd via
     * meerdere onafhankelijke, actuele client-libraries. De exacte methode-
     * naam voor het ophalen van productfeed-data kon echter niet met
     * zekerheid worden vastgesteld — TradeTracker's eigen documentatie
     * noemt "product feed data" als functie, maar niet de letterlijke
     * SOAP-methodenaam. Controleer dit tegen je eigen WSDL-toegang
     * (via SoapClient::__getFunctions() na inloggen) vóórdat je hierop
     * vertrouwt in productie — pas $method en $options hieronder aan indien
     * de daadwerkelijke methode een andere naam blijkt te hebben.
     */
    public static function tradetracker_get_products( $ean = '', $limit = 10 ) {
        $customer_id = Ravn_Options::get( 'tradetracker_customer_id' );
        $passphrase  = Ravn_Options::get( 'tradetracker_api_key' );
        $site_id     = Ravn_Options::get( 'tradetracker_site_id' );
        if ( ! $customer_id || ! $passphrase || ! $site_id ) return array();

        if ( ! class_exists( 'SoapClient' ) ) return array();

        try {
            $client = new SoapClient( 'https://ws.tradetracker.com/soap/affiliate?wsdl', array( 'trace' => 0, 'exceptions' => true ) );
            // Bevestigde signature uit officiële client-SDK's: customerID,
            // passphrase, sandbox (bool), affiliateSiteID, demo (bool).
            $client->authenticate( $customer_id, $passphrase, false, $site_id, false );

            if ( ! method_exists( $client, 'getProductFeeds' ) ) {
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( '[Ravn tradetracker] Methode getProductFeeds bestaat niet op deze WSDL. Beschikbare methodes: ' . implode( ', ', $client->__getFunctions() ) );
                }
                return array();
            }

            $options = array( 'EAN' => $ean, 'limit' => $limit );
            $result  = $client->getProductFeeds( null, null, $options );
            return ! empty( $result->items ) ? (array) $result->items : array();
        } catch ( Exception $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( '[Ravn tradetracker] SOAP-fout: ' . $e->getMessage() );
            }
            return array();
        }
    }

    /**
     * Test of de TradeTracker-koppeling technisch werkt: authenticatie via
     * de bevestigde getAffiliateSites()-methode (uit meerdere onafhankelijke
     * SDK's bekend te bestaan), onafhankelijk van de onzekere product-methode
     * hierboven.
     */
    public static function tradetracker_test_connection() {
        $customer_id = Ravn_Options::get( 'tradetracker_customer_id' );
        $passphrase  = Ravn_Options::get( 'tradetracker_api_key' );
        $site_id     = Ravn_Options::get( 'tradetracker_site_id' );

        if ( ! $customer_id || ! $passphrase || ! $site_id ) {
            return array( 'success' => false, 'message' => 'Customer ID, Passphrase en/of Affiliate Site ID zijn niet ingevuld.' );
        }
        if ( ! class_exists( 'SoapClient' ) ) {
            return array( 'success' => false, 'message' => 'De PHP SOAP-extensie is niet beschikbaar op deze server. Vraag je hoster deze te activeren.' );
        }

        try {
            $client = new SoapClient( 'https://ws.tradetracker.com/soap/affiliate?wsdl', array( 'trace' => 0, 'exceptions' => true, 'connection_timeout' => 15 ) );
            $client->authenticate( $customer_id, $passphrase, false, $site_id, false );

            if ( ! method_exists( $client, 'getAffiliateSites' ) ) {
                return array( 'success' => false, 'message' => 'Authenticatie leek te lukken, maar de verwachte testmethode is niet beschikbaar op deze WSDL. De API is mogelijk gewijzigd.' );
            }

            $sites = $client->getAffiliateSites();
            return array( 'success' => true, 'message' => 'Verbinding werkt: authenticatie gelukt en sites opgehaald.' );
        } catch ( SoapFault $e ) {
            $msg = $e->getMessage();
            if ( false !== stripos( $msg, 'auth' ) || false !== stripos( $msg, 'credential' ) || false !== stripos( $msg, 'login' ) ) {
                return array( 'success' => false, 'message' => 'Authenticatie afgewezen door TradeTracker. Controleer Customer ID, Passphrase en Affiliate Site ID. Melding: ' . $msg );
            }
            return array( 'success' => false, 'message' => 'SOAP-fout van TradeTracker: ' . $msg );
        } catch ( Exception $e ) {
            return array( 'success' => false, 'message' => 'Kon geen verbinding maken met TradeTracker: ' . $e->getMessage() );
        }
    }

    // ───────────────────────────────────────────────────────────────────────
    //  DAISYCON  (Feed URL / CSV / XML)
    // ───────────────────────────────────────────────────────────────────────

    public static function daisycon_get_feed( $url = '' ) {
        if ( ! $url ) {
            $url = Ravn_Options::get( 'daisycon_feed_url' );
        }
        if ( ! $url ) return array();

        $response = wp_remote_get( $url, array(
            'timeout'     => 30,
            'redirection' => 3,
            'limit_response_size' => 20 * 1024 * 1024, // 20 MB max
        ) );
        if ( is_wp_error( $response ) ) return array();
        if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) return array();

        $body = wp_remote_retrieve_body( $response );
        if ( '' === trim( (string) $body ) ) return array();
        // Auto-detect CSV of XML
        if ( substr( ltrim( $body ), 0, 1 ) === '<' ) {
            return self::parse_xml_feed( $body );
        }
        return self::parse_csv_feed( $body );
    }

    // ───────────────────────────────────────────────────────────────────────
    //  AWIN  (Productfeed API)
    // ───────────────────────────────────────────────────────────────────────

    /**
     * Haalt de productfeed op voor één specifieke adverteerder via het
     * bevestigde Awin-endpoint:
     * GET /publishers/{publisherId}/awinfeeds/download/{advertiserId}-{vertical}-{locale}
     *
     * Belangrijk verschil met bol.com: Awin heeft geen los zoek-op-trefwoord
     * endpoint. Je haalt de volledige productfeed van één adverteerder op
     * (waarmee je een goedgekeurde affiliate-relatie hebt) en filtert daarna
     * zelf op naam/EAN. De respons is JSON Lines (elke regel een los
     * JSON-object), geen standaard JSON-array.
     *
     * @param string $advertiser_id Awin adverteerder-ID (verplicht).
     * @param string $vertical      Productcategorie-slug, bijv. "retail".
     * @param string $locale        Taal/regio, bijv. "nl_NL".
     * @return array Lijst van producten (elk element = 1 gedecodeerde regel).
     */
    public static function awin_get_feed( $advertiser_id = '', $vertical = 'retail', $locale = 'nl_NL' ) {
        $publisher_id = Ravn_Options::get( 'awin_publisher_id' );
        $api_token    = Ravn_Options::get( 'awin_api_token' );
        $advertiser_id = $advertiser_id ?: Ravn_Options::get( 'awin_advertiser_id' );

        if ( ! $publisher_id || ! $api_token || ! $advertiser_id ) return array();

        $url = sprintf(
            'https://api.awin.com/publishers/%s/awinfeeds/download/%s-%s-%s',
            rawurlencode( $publisher_id ),
            rawurlencode( $advertiser_id ),
            rawurlencode( $vertical ),
            rawurlencode( $locale )
        );

        $response = wp_remote_get( $url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_token,
                'Accept'        => 'application/json',
            ),
            'timeout'             => 60, // feeds kunnen groot zijn
            'limit_response_size' => 50 * 1024 * 1024, // 50 MB max
        ) );

        if ( is_wp_error( $response ) ) return array();
        if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) return array();

        $body = wp_remote_retrieve_body( $response );
        if ( '' === trim( (string) $body ) ) return array();

        return self::parse_jsonl( $body );
    }

    /**
     * Test of de Awin-koppeling technisch werkt: authenticatie + toegang
     * tot de feed van de ingestelde adverteerder. Geeft een duidelijke
     * Nederlandse foutmelding terug voor de "Verbinding testen"-knop.
     */
    public static function awin_test_connection() {
        $publisher_id  = Ravn_Options::get( 'awin_publisher_id' );
        $api_token     = Ravn_Options::get( 'awin_api_token' );
        $advertiser_id = Ravn_Options::get( 'awin_advertiser_id' );

        if ( ! $publisher_id || ! $api_token ) {
            return array( 'success' => false, 'message' => 'Publisher ID en/of API-token zijn niet ingevuld.' );
        }
        if ( ! $advertiser_id ) {
            return array( 'success' => false, 'message' => 'Vul een Advertiser ID in — Awin heeft geen algemene zoekfunctie, je haalt de feed van één specifieke adverteerder op waarmee je een goedgekeurde relatie hebt.' );
        }

        $url = sprintf(
            'https://api.awin.com/publishers/%s/awinfeeds/download/%s-retail-nl_NL',
            rawurlencode( $publisher_id ),
            rawurlencode( $advertiser_id )
        );

        $response = wp_remote_get( $url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_token,
                'Accept'        => 'application/json',
            ),
            'timeout' => 30,
        ) );

        if ( is_wp_error( $response ) ) {
            return array( 'success' => false, 'message' => 'Geen verbinding met Awin: ' . $response->get_error_message() );
        }

        $http_code = (int) wp_remote_retrieve_response_code( $response );

        switch ( $http_code ) {
            case 200:
                return array( 'success' => true, 'message' => 'Verbinding werkt: de feed van deze adverteerder is opgehaald.' );
            case 401:
                return array( 'success' => false, 'message' => 'Authenticatie mislukt (401). Controleer je API-token in het Awin publisher-dashboard.' );
            case 403:
                return array( 'success' => false, 'message' => 'Toegang geweigerd (403). Je bent waarschijnlijk nog niet geaccepteerd door deze specifieke adverteerder, of het Publisher ID klopt niet.' );
            case 404:
                return array( 'success' => false, 'message' => 'Feed niet gevonden (404). Controleer of het Advertiser ID, de vertical ("retail") en locale ("nl_NL") kloppen — deze verschillen per adverteerder.' );
            default:
                return array( 'success' => false, 'message' => 'Onverwachte reactie van Awin (HTTP ' . $http_code . ').' );
        }
    }

    /**
     * Parseert JSON Lines (JSONL) — elke regel is een los, geldig JSON-object.
     * Gebruikt door Awin (en eventueel andere netwerken met dit formaat).
     */
    private static function parse_jsonl( $content ) {
        $lines  = preg_split( '/\r\n|\r|\n/', trim( $content ) );
        $result = array();
        foreach ( $lines as $line ) {
            $line = trim( $line );
            if ( '' === $line ) continue;
            $decoded = json_decode( $line, true );
            if ( is_array( $decoded ) ) $result[] = $decoded;
        }
        return $result;
    }

    // ───────────────────────────────────────────────────────────────────────
    //  TRADEDOUBLER
    // ───────────────────────────────────────────────────────────────────────

    public static function tradedoubler_search( $query, $limit = 10 ) {
        $org_id = Ravn_Options::get( 'tradedoubler_org_id' );
        $token  = Ravn_Options::get( 'tradedoubler_token' );
        if ( ! $org_id || ! $token ) return array();

        $url      = add_query_arg( array(
            'organizationId' => $org_id,
            'q'              => urlencode( $query ),
            'rows'           => $limit,
        ), 'https://api.tradedoubler.com/1.0/products.json' );

        $response = wp_remote_get( $url, array(
            'headers' => array( 'Authorization' => 'Bearer ' . $token ),
            'timeout' => 15,
        ) );
        if ( is_wp_error( $response ) ) return array();
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        return isset( $body['productElements'] ) ? $body['productElements'] : array();
    }

    // ───────────────────────────────────────────────────────────────────────
    //  ADTRACTION
    // ───────────────────────────────────────────────────────────────────────

    public static function adtraction_search( $query, $limit = 10 ) {
        $api_key    = Ravn_Options::get( 'adtraction_api_key' );
        $channel_id = Ravn_Options::get( 'adtraction_channel_id' );
        if ( ! $api_key || ! $channel_id ) return array();

        $url      = add_query_arg( array(
            'apiKey'    => $api_key,
            'channelId' => $channel_id,
            'search'    => urlencode( $query ),
            'limit'     => $limit,
        ), 'https://api.adtraction.com/v2/partner/products/' );

        $response = wp_remote_get( $url, array( 'timeout' => 15 ) );
        if ( is_wp_error( $response ) ) return array();
        return json_decode( wp_remote_retrieve_body( $response ), true ) ?: array();
    }

    // ───────────────────────────────────────────────────────────────────────
    //  PARTNERIZE
    // ───────────────────────────────────────────────────────────────────────

    public static function partnerize_search( $query, $limit = 10 ) {
        $user_key = Ravn_Options::get( 'partnerize_user_api_key' );
        $app_key  = Ravn_Options::get( 'partnerize_app_api_key' );
        if ( ! $user_key || ! $app_key ) return array();

        $url      = add_query_arg( array( 'q' => urlencode( $query ), 'limit' => $limit ), 'https://api.partnerize.com/v2/products' );
        $response = wp_remote_get( $url, array(
            'headers' => array(
                'X-User-Api-Key'        => $user_key,
                'X-Application-Api-Key' => $app_key,
            ),
            'timeout' => 15,
        ) );
        if ( is_wp_error( $response ) ) return array();
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        return isset( $body['products'] ) ? $body['products'] : array();
    }

    // ───────────────────────────────────────────────────────────────────────
    //  GENERIEKE CSV PARSER
    // ───────────────────────────────────────────────────────────────────────

    public static function parse_csv_feed( $content, $delimiter = null ) {
        if ( ! $delimiter ) {
            // Auto-detecteer scheidingsteken
            $delimiters = array( ';' => 0, ',' => 0, "\t" => 0 );
            $first_line = strtok( $content, "\n" );
            foreach ( $delimiters as $d => $count ) {
                $delimiters[ $d ] = substr_count( $first_line, $d );
            }
            arsort( $delimiters );
            $delimiter = key( $delimiters );
        }

        $lines   = array();
        $rows    = str_getcsv( $content, "\n" );
        $headers = array();

        foreach ( $rows as $i => $row ) {
            $cols = str_getcsv( $row, $delimiter );
            if ( 0 === $i ) {
                $headers = array_map( 'trim', $cols );
                continue;
            }
            if ( count( $cols ) !== count( $headers ) ) continue;
            $lines[] = array_combine( $headers, $cols );
        }
        return $lines;
    }

    // ───────────────────────────────────────────────────────────────────────
    //  GENERIEKE XML PARSER
    // ───────────────────────────────────────────────────────────────────────

    public static function parse_xml_feed( $content ) {
        libxml_use_internal_errors( true );
        // XXE-bescherming: sta geen externe entiteiten/netwerktoegang toe.
        if ( function_exists( 'libxml_set_external_entity_loader' ) ) {
            libxml_set_external_entity_loader( '__return_null' );
        }
        $options = defined( 'LIBXML_NONET' ) ? LIBXML_NONET : 0;
        if ( defined( 'LIBXML_NOENT' ) ) {
            // LIBXML_NOENT bewust NIET gebruiken; externe entiteiten blijven zo uit.
            $options |= 0;
        }
        $xml = simplexml_load_string( $content, 'SimpleXMLElement', $options );
        if ( false === $xml ) return array();

        // Zoek naar de eerste herhalende child (product items)
        $items = array();
        foreach ( $xml->children() as $child ) {
            if ( count( $xml->$child->getName() ) > 1 || is_array( $child ) ) {
                // waarschijnlijk de item container
                foreach ( $child->children() as $item ) {
                    $items[] = json_decode( wp_json_encode( $item ), true );
                }
                break;
            }
        }

        // Als geen geneste structuur, probeer direct
        if ( empty( $items ) ) {
            foreach ( $xml->children() as $item ) {
                $items[] = json_decode( wp_json_encode( $item ), true );
            }
        }

        return $items;
    }

    // ───────────────────────────────────────────────────────────────────────
    //  GECOMBINEERDE ZOEKFUNCTIE (EAN zoeker in admin)
    // ───────────────────────────────────────────────────────────────────────

    public static function search_all_networks( $query, $limit = 10 ) {
        $results = array();

        // Bol.com
        $bol = self::bol_search( $query, $limit );
        $price_fallback_used = 0;
        $price_fallback_max  = 5; // begrens extra API-calls (rate limit: 10/sec)

        foreach ( $bol as $item ) {
            $ean = isset( $item['ean'] ) ? $item['ean'] : '';
            // De officiële respons geeft "image" als los object (niet "images" als lijst)
            // en het veld heet "description", niet "shortDescription".
            $image = isset( $item['image']['url'] ) ? $item['image']['url'] : '';
            $price = isset( $item['offer']['price'] ) ? $item['offer']['price'] : null;
            $debug = '';

            // Fallback: als het zoekresultaat geen prijs bevat (bijv. omdat
            // bol voor dit specifieke product geen offer in de zoek-respons
            // meegeeft) maar er wel een EAN bekend is, haal de prijs dan
            // alsnog op via het toegewijde offers/best-endpoint. Begrensd
            // aantal calls om binnen de rate limit van bol te blijven.
            if ( null === $price ) {
                if ( ! $ean ) {
                    $debug = 'geen prijs in zoekresultaat en geen EAN bekend om op te zoeken';
                } elseif ( $price_fallback_used >= $price_fallback_max ) {
                    $debug = 'geen prijs in zoekresultaat; fallback-limiet (5) al bereikt in deze zoekopdracht';
                } else {
                    $diag = self::bol_get_offers_for_ean_diag( $ean );
                    $price_fallback_used++;
                    if ( null !== $diag['price'] ) {
                        $price = $diag['price'];
                        $debug = 'prijs alsnog gevonden via offers/best-endpoint';
                    } else {
                        $debug = 'geen prijs in zoekresultaat; offers/best-endpoint: ' . $diag['reason'];
                    }
                }
            }

            $results[] = array(
                'network'     => 'bol',
                'title'       => isset( $item['title'] ) ? $item['title'] : '',
                'ean'         => $ean,
                'image'       => $image,
                'description' => isset( $item['description'] ) ? $item['description'] : '',
                'price'       => $price,
                'debug'       => $debug,
                // "url" komt uit de zoekresultaten als bol dat meegeeft, anders
                // valt build_url() bij weergave alsnog terug op een op-EAN-
                // gebaseerde link; wordt bij weergave omgezet naar een
                // tracking-link met het ingestelde Site ID. De spec toont geen
                // concreet voorbeeld van dit veld (mogelijk relatief pad), dus
                // alleen gebruiken als het een volledige URL lijkt te zijn.
                'url'         => ( ! empty( $item['url'] ) && 0 === strpos( $item['url'], 'http' ) )
                    ? $item['url']
                    : ( $ean ? self::bol_url_from_ean( $ean ) : '' ),
            );
        }

        // Amazon
        $amazon = self::amazon_search( $query, $limit );
        foreach ( $amazon as $item ) {
            $results[] = array(
                'network'     => 'amazon',
                'title'       => isset( $item['ItemInfo']['Title']['DisplayValue'] ) ? $item['ItemInfo']['Title']['DisplayValue'] : '',
                'ean'         => isset( $item['ASIN'] ) ? $item['ASIN'] : '',
                'image'       => isset( $item['Images']['Primary']['Large']['URL'] ) ? $item['Images']['Primary']['Large']['URL'] : '',
                'description' => '',
            );
        }

        return array_slice( $results, 0, $limit );
    }

    /**
     * Bouwt een reguliere (niet-affiliate) bol.com-URL op basis van een EAN.
     * Bol's Retailer API geeft geen directe productpagina-URL terug, dus deze
     * link dient als basis die vervolgens door bol_wrap_affiliate_url() wordt
     * omgezet naar een geldige tracking-URL.
     */
    public static function bol_url_from_ean( $ean ) {
        $ean = preg_replace( '/[^0-9]/', '', (string) $ean );
        if ( '' === $ean ) return '';
        return 'https://www.bol.com/nl/nl/s/?searchtext=' . urlencode( $ean );
    }

    // ───────────────────────────────────────────────────────────────────────
    //  AFFILIATE TRACKING-WRAPPER (per netwerk)
    // ───────────────────────────────────────────────────────────────────────

    /**
     * Zet een reguliere bol.com productlink om naar een geldige affiliate
     * tracking-URL met het Site ID van de partner.
     *
     * Reguliere product-/API-links leveren GEEN commissie op; alleen links
     * die via partner.bol.com/click/click lopen met een geldig Site ID (s=)
     * worden aan het partneraccount toegeschreven.
     *
     * @param string $url    De originele bol.com URL (uit API, feed of handmatige invoer).
     * @param string $sub_id Optionele sub-id voor eigen tracking.
     * @param string $name   Optionele linknaam (vrij tekstveld, bijv. paginatitel).
     * @return string De omgezette tracking-URL, of de originele URL als er geen Site ID is ingesteld.
     */
    public static function bol_wrap_affiliate_url( $url, $sub_id = '', $name = '' ) {
        $site_id = trim( (string) Ravn_Options::get( 'bol_site_id', '' ) );

        // Zonder Site ID kan de plugin geen geldige tracking-link bouwen;
        // geef de originele URL terug zodat de link tenminste blijft werken.
        if ( '' === $site_id || '' === trim( (string) $url ) ) {
            return $url;
        }

        // Al een tracking-link? Niet dubbel wrappen.
        if ( false !== strpos( $url, 'partner.bol.com/click/click' ) ) {
            return $url;
        }

        $params = array(
            't'   => 'url',
            's'   => $site_id,
            'url' => $url,
            'f'   => 'txl',
        );
        if ( $sub_id !== '' ) {
            $params['subid'] = $sub_id;
        }
        if ( $name !== '' ) {
            $params['name'] = $name;
        }

        return add_query_arg( $params, 'https://partner.bol.com/click/click' );
    }

    /**
     * Bepaalt of een URL een bol.com-URL is (voor automatische detectie
     * wanneer het netwerk van een aanbieder niet expliciet is ingesteld).
     */
    public static function is_bol_url( $url ) {
        $host = wp_parse_url( $url, PHP_URL_HOST );
        if ( ! $host ) return false;
        $host = strtolower( $host );
        return ( 'bol.com' === $host || substr( $host, -8 ) === '.bol.com' );
    }
}
