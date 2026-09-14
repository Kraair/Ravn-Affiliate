<?php if ( ! defined( 'ABSPATH' ) ) exit;
$o    = Ravn_Options::get_all();
$tab  = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'general';
$tabs = array(
    'general'   => 'Algemeen',
    'products'  => 'Producten',
    'sellers'   => 'Verkopers',
    'links'     => 'Affiliate links',
    'carousel'  => 'Carrousel',
    'cron'      => 'Auto bijwerken',
    'stats'     => 'Statistieken',
    'api'       => 'API koppelingen',
    'modules'   => 'Modules',
    'search'    => 'Zoeken',
);
?>
<div class="wrap ravn-wrap">
<h1>Ravn Affiliate — Instellingen</h1>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
<input type="hidden" name="action" value="ravn_affiliate_save_settings">
<input type="hidden" name="settings_tab" value="<?php echo esc_attr( $tab ); ?>">
<?php wp_nonce_field( 'ravn_affiliate_save_settings' ); ?>

<div class="ravn-tabs-container">
<ul class="ravn-tabs-nav">
<?php foreach ( $tabs as $key => $label ) : ?>
    <li>
        <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'ravn-affiliate-settings', 'tab' => $key ), admin_url( 'admin.php' ) ) ); ?>"
           class="<?php echo $tab === $key ? 'active' : ''; ?>"><?php echo esc_html( $label ); ?></a>
    </li>
<?php endforeach; ?>
</ul>

<div class="ravn-tab-content">
<?php

// ── TAB: ALGEMEEN ──────────────────────────────────────────────────────
if ( 'general' === $tab ) : ?>
<h2>Elementen verbergen (sitebreed)</h2>
<p class="description">Verberg elementen op alle productboxen. Je kunt dit ook per shortcode instellen.</p>
<table class="form-table">
    <tr><th>Verberg prijs</th><td><label><input type="checkbox" name="hide_price" <?php checked( $o['hide_price'] ); ?>> Prijs verbergen</label></td></tr>
    <tr><th>Verberg afbeelding</th><td><label><input type="checkbox" name="hide_image" <?php checked( $o['hide_image'] ); ?>> Afbeelding verbergen</label></td></tr>
    <tr><th>Verberg titel</th><td><label><input type="checkbox" name="hide_title" <?php checked( $o['hide_title'] ); ?>> Titel verbergen</label></td></tr>
    <tr><th>Verberg beschrijving</th><td><label><input type="checkbox" name="hide_desc" <?php checked( $o['hide_desc'] ); ?>> Beschrijving verbergen</label></td></tr>
    <tr><th>Verberg verkopers</th><td><label><input type="checkbox" name="hide_sellers" <?php checked( $o['hide_sellers'] ); ?>> Verkopers verbergen</label></td></tr>
    <tr><th>Verberg beoordeling</th><td><label><input type="checkbox" name="hide_rating" <?php checked( $o['hide_rating'] ); ?>> Sterren verbergen</label></td></tr>
</table>

<?php // ── TAB: PRODUCTEN ───────────────────────────────────────────────────
elseif ( 'products' === $tab ) : ?>
<h2>Producten</h2>
<table class="form-table">
    <tr>
        <th><label for="ean_max_results">Max. resultaten EAN zoeker</label></th>
        <td>
            <select id="ean_max_results" name="ean_max_results">
                <?php foreach ( array( 5, 10, 25, 50, 100 ) as $n ) : ?>
                    <option value="<?php echo $n; ?>" <?php selected( $o['ean_max_results'], $n ); ?>><?php echo $n; ?></option>
                <?php endforeach; ?>
            </select>
            <p class="description">Hoe meer resultaten, hoe langer het zoeken duurt.</p>
        </td>
    </tr>
    <tr>
        <th>Toon "Laatst bijgewerkt"</th>
        <td><label><input type="checkbox" name="show_last_updated" <?php checked( $o['show_last_updated'] ); ?>> Activeer</label></td>
    </tr>
    <tr>
        <th><label for="last_updated_text">Tekst "Laatst bijgewerkt"</label></th>
        <td><input type="text" id="last_updated_text" name="last_updated_text" value="<?php echo esc_attr( $o['last_updated_text'] ); ?>" class="regular-text">
        <p class="description">Bijv. "Pijsinformatie bijgewerkt op"</p></td>
    </tr>
    <tr>
        <th><label for="sort_sellers">Sorteer verkopers op</label></th>
        <td>
            <select id="sort_sellers" name="sort_sellers">
                <option value="default" <?php selected( $o['sort_sellers'], 'default' ); ?>>Standaard (toevoegvolgorde)</option>
                <option value="price_asc" <?php selected( $o['sort_sellers'], 'price_asc' ); ?>>Prijs (laag → hoog)</option>
                <option value="price_desc" <?php selected( $o['sort_sellers'], 'price_desc' ); ?>>Prijs (hoog → laag)</option>
                <option value="name_asc" <?php selected( $o['sort_sellers'], 'name_asc' ); ?>>Naam (A → Z)</option>
            </select>
        </td>
    </tr>
</table>

<?php // ── TAB: VERKOPERS ───────────────────────────────────────────────────
elseif ( 'sellers' === $tab ) : ?>
<h2>Verkopers</h2>
<table class="form-table">
    <tr>
        <th><label for="sellers_visible">Verkopers direct zichtbaar</label></th>
        <td>
            <select id="sellers_visible" name="sellers_visible">
                <option value="0" <?php selected( $o['sellers_visible'], 0 ); ?>>Geen (alleen "toon alle" knop)</option>
                <?php for ( $i = 1; $i <= 10; $i++ ) : ?>
                    <option value="<?php echo $i; ?>" <?php selected( $o['sellers_visible'], $i ); ?>><?php echo $i; ?></option>
                <?php endfor; ?>
            </select>
        </td>
    </tr>
    <tr>
        <th>Als verkoper niet meer gevonden</th>
        <td>
            <select name="sellers_not_found">
                <option value="out_of_stock" <?php selected( $o['sellers_not_found'], 'out_of_stock' ); ?>>Zet op "niet op voorraad"</option>
                <option value="remove" <?php selected( $o['sellers_not_found'], 'remove' ); ?>>Verwijder verkoper</option>
            </select>
        </td>
    </tr>
    <tr>
        <th><label for="sellers_description">Verkopers beschrijving</label></th>
        <td><input type="text" id="sellers_description" name="sellers_description" value="<?php echo esc_attr( $o['sellers_description'] ); ?>" class="regular-text">
        <p class="description">Bijv. "Verkrijgbaar bij" of "Waar te koop?"</p></td>
    </tr>
    <tr>
        <th>Verkoper CTA knop</th>
        <td>
            <label><input type="checkbox" name="enable_cta" <?php checked( $o['enable_cta'] ); ?>> Activeer CTA knop (bij 1 aanbieder)</label><br>
            <label><input type="checkbox" name="enable_cta_info" <?php checked( $o['enable_cta_info'] ); ?>> Toon verkopersinformatie boven CTA</label>
        </td>
    </tr>
    <tr>
        <th><label for="cta_text">CTA knoptekst</label></th>
        <td><input type="text" id="cta_text" name="cta_text" value="<?php echo esc_attr( $o['cta_text'] ); ?>" class="regular-text"></td>
    </tr>
    <tr>
        <th><label for="show_all_text">Toon alle verkopers tekst</label></th>
        <td><input type="text" id="show_all_text" name="show_all_text" value="<?php echo esc_attr( $o['show_all_text'] ); ?>" class="regular-text"></td>
    </tr>
    <tr>
        <th>Toon verkopersaantal</th>
        <td><label><input type="checkbox" name="show_seller_count" <?php checked( $o['show_seller_count'] ); ?>> Toon aantal achter "toon alle" tekst</label>
        <p class="description">Bijv. "Toon alle verkopers (5)"</p></td>
    </tr>
    <tr>
        <th><label for="excluded_sellers">Uitgesloten verkopers</label></th>
        <td>
            <input type="text" id="excluded_sellers" name="excluded_sellers" value="<?php echo esc_attr( $o['excluded_sellers'] ); ?>" class="large-text">
            <p class="description">Kommagescheiden lijst van domeinen, bijv. <code>plein.be,marktplaats.nl</code>. Gebruik kleine letters zonder http://.</p>
        </td>
    </tr>
    <tr>
        <th>Aangepaste logo's</th>
        <td>
            <div id="ravn-custom-logos">
                <?php
                $logos = is_array( $o['custom_logos'] ) ? $o['custom_logos'] : array();
                foreach ( $logos as $domain => $logo_url ) : ?>
                    <div class="ravn-logo-row" style="display:flex;gap:8px;margin-bottom:6px;">
                        <input type="text" name="custom_logos_domain[]" value="<?php echo esc_attr( $domain ); ?>" placeholder="bol.com" style="width:180px;">
                        <input type="url" name="custom_logos_url[]" value="<?php echo esc_attr( $logo_url ); ?>" placeholder="https://..." style="flex:1;">
                        <button type="button" class="button ravn-remove-logo-row">✕</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="button" id="ravn-add-logo-btn">+ Logo toevoegen</button>
            <script type="text/html" id="ravn-logo-row-template">
                <div class="ravn-logo-row" style="display:flex;gap:8px;margin-bottom:6px;">
                    <input type="text" name="custom_logos_domain[]" value="" placeholder="bol.com" style="width:180px;">
                    <input type="url" name="custom_logos_url[]" value="" placeholder="https://..." style="flex:1;">
                    <button type="button" class="button ravn-remove-logo-row">✕</button>
                </div>
            </script>
            <p class="description">Overschrijf het favicon van een verkoper met een eigen logo.</p>
        </td>
    </tr>
</table>

<?php // ── TAB: AFFILIATE LINKS ─────────────────────────────────────────────
elseif ( 'links' === $tab ) : ?>
<h2>Affiliate links</h2>
<table class="form-table">
    <tr>
        <th>Klik op titel of afbeelding</th>
        <td>
            <select name="click_action">
                <option value="redirect" <?php selected( $o['click_action'], 'redirect' ); ?>>Doorsturen naar eerste verkoper</option>
                <option value="popup" <?php selected( $o['click_action'], 'popup' ); ?>>Popup openen met alle verkopers</option>
            </select>
        </td>
    </tr>
    <tr>
        <th>Link cloaking</th>
        <td>
            <label><input type="checkbox" name="module_cloaking" <?php checked( $o['module_cloaking'] ); ?>> Gebruik cloaked links (<code><?php echo esc_html( home_url( '/go/...' ) ); ?></code>) in plaats van de directe affiliate-URL bij klikken op titel/afbeelding</label>
            <p class="description">Werkt alleen bij "Doorsturen naar eerste verkoper" hierboven, en alleen als het product een cloak-slug heeft.</p>
        </td>
    </tr>
    <tr>
        <th>Nieuw venster</th>
        <td><label><input type="checkbox" name="new_window" <?php checked( $o['new_window'] ); ?>> Open affiliate links in een nieuw venster</label></td>
    </tr>
    <tr>
        <th>rel="nofollow"</th>
        <td><label><input type="checkbox" name="nofollow" <?php checked( $o['nofollow'] ); ?>> Voeg rel="nofollow" toe aan affiliate links</label></td>
    </tr>
    <tr>
        <th>rel="sponsored"</th>
        <td><label><input type="checkbox" name="sponsored" <?php checked( $o['sponsored'] ); ?>> Voeg rel="sponsored" toe aan affiliate links</label></td>
    </tr>
    <tr>
        <th>Automatische Sub ID</th>
        <td>
            <label><input type="checkbox" name="auto_sub_id" <?php checked( $o['auto_sub_id'] ); ?>> Voeg automatisch Sub ID toe op basis van paginatitel</label>
            <p class="description">Elke affiliate link krijgt automatisch de paginatitel als sub ID.</p>
        </td>
    </tr>
    <tr>
        <th><label for="sub_id_global">Globale Sub ID (fallback)</label></th>
        <td>
            <input type="text" id="sub_id_global" name="sub_id_global" value="<?php echo esc_attr( $o['sub_id_global'] ); ?>" class="regular-text">
            <p class="description">Wordt gebruikt als er geen specifieke Sub ID is ingesteld.</p>
        </td>
    </tr>
</table>

<?php // ── TAB: CARROUSEL ───────────────────────────────────────────────────
elseif ( 'carousel' === $tab ) : ?>
<h2>Carrousel instellingen</h2>
<table class="form-table">
    <tr>
        <th>Pijlen</th>
        <td><label><input type="checkbox" name="carousel_arrows" <?php checked( $o['carousel_arrows'] ); ?>> Activeer navigatiepijlen</label></td>
    </tr>
    <tr>
        <th>Navigatiepunten</th>
        <td><label><input type="checkbox" name="carousel_dots" <?php checked( $o['carousel_dots'] ); ?>> Activeer navigatiepunten</label></td>
    </tr>
    <tr>
        <th>Oneindige herhaling</th>
        <td><label><input type="checkbox" name="carousel_infinite" <?php checked( $o['carousel_infinite'] ); ?>> Activeer oneindige herhaling</label></td>
    </tr>
    <tr>
        <th>Automatisch afspelen</th>
        <td><label><input type="checkbox" name="carousel_autoplay" <?php checked( $o['carousel_autoplay'] ); ?>> Activeer automatisch afspelen</label></td>
    </tr>
    <tr>
        <th><label for="carousel_speed">Snelheid slide</label></th>
        <td>
            <select id="carousel_speed" name="carousel_speed">
                <?php foreach ( array( 250 => '250ms (snel)', 500 => '500ms', 1000 => '1 seconde', 2000 => '2 seconden', 3000 => '3 seconden' ) as $val => $label ) : ?>
                    <option value="<?php echo $val; ?>" <?php selected( intval( $o['carousel_speed'] ), $val ); ?>><?php echo $label; ?></option>
                <?php endforeach; ?>
            </select>
        </td>
    </tr>
    <tr>
        <th><label for="carousel_autoplay_speed">Snelheid autoplay</label></th>
        <td>
            <select id="carousel_autoplay_speed" name="carousel_autoplay_speed">
                <?php foreach ( array( 1000 => '1 seconde', 2000 => '2 seconden', 3000 => '3 seconden', 5000 => '5 seconden', 7500 => '7,5 seconden', 10000 => '10 seconden' ) as $val => $label ) : ?>
                    <option value="<?php echo $val; ?>" <?php selected( intval( $o['carousel_autoplay_speed'] ), $val ); ?>><?php echo $label; ?></option>
                <?php endforeach; ?>
            </select>
        </td>
    </tr>
</table>

<?php // ── TAB: AUTO BIJWERKEN ──────────────────────────────────────────────
elseif ( 'cron' === $tab ) :
    $last_run = get_option( 'ravn_last_cron_run' );
    $next_run = wp_next_scheduled( 'ravn_cron_fetch' );
    ?>
<h2>Automatisch gegevens ophalen</h2>

<?php if ( $last_run ) echo '<p>Laatste keer bijgewerkt: <strong>' . esc_html( mysql2date( 'd-m-Y H:i', $last_run ) ) . '</strong></p>'; ?>
<?php if ( $next_run ) echo '<p>Volgende keer: <strong>' . esc_html( date_i18n( 'd-m-Y H:i', $next_run ) ) . '</strong></p>'; ?>

<table class="form-table">
    <tr>
        <th>Automatisch ophalen</th>
        <td><label><input type="checkbox" name="cron_active" <?php checked( $o['cron_active'] ); ?>> Activeer automatisch ophalen</label></td>
    </tr>
    <tr>
        <th><label for="cron_start_time">Starttijd</label></th>
        <td><input type="time" id="cron_start_time" name="cron_start_time" value="<?php echo esc_attr( $o['cron_start_time'] ); ?>">
        <p class="description">Tijdstip waarop het ophalen start. Afhankelijk van bezoekers op je site.</p></td>
    </tr>
    <tr>
        <th><label for="cron_interval">Interval</label></th>
        <td>
            <select id="cron_interval" name="cron_interval">
                <option value="hourly" <?php selected( $o['cron_interval'], 'hourly' ); ?>>Elk uur</option>
                <option value="6hours" <?php selected( $o['cron_interval'], '6hours' ); ?>>Elke 6 uur</option>
                <option value="twicedaily" <?php selected( $o['cron_interval'], 'twicedaily' ); ?>>Tweemaal per dag</option>
                <option value="daily" <?php selected( $o['cron_interval'], 'daily' ); ?>>Dagelijks</option>
            </select>
        </td>
    </tr>
    <tr>
        <th><label for="cron_timeout">Time-out tijd</label></th>
        <td>
            <select id="cron_timeout" name="cron_timeout">
                <option value="0" <?php selected( intval( $o['cron_timeout'] ), 0 ); ?>>Geen time-out</option>
                <?php foreach ( array( 2, 5, 10, 15, 30 ) as $m ) : ?>
                    <option value="<?php echo $m; ?>" <?php selected( intval( $o['cron_timeout'] ), $m ); ?>><?php echo $m; ?> minuten</option>
                <?php endforeach; ?>
            </select>
        </td>
    </tr>
    <tr>
        <th><label for="cron_max_timeouts">Max. time-outs</label></th>
        <td>
            <select id="cron_max_timeouts" name="cron_max_timeouts">
                <option value="0" <?php selected( intval( $o['cron_max_timeouts'] ), 0 ); ?>>Niet herstarten</option>
                <?php foreach ( array( 1, 2, 3, 5, 10 ) as $n ) : ?>
                    <option value="<?php echo $n; ?>" <?php selected( intval( $o['cron_max_timeouts'] ), $n ); ?>><?php echo $n; ?></option>
                <?php endforeach; ?>
            </select>
        </td>
    </tr>
    <tr>
        <th>Netwerken</th>
        <td>
            <?php
            $all_nets = array( 'bol' => 'Bol.com', 'amazon' => 'Amazon', 'tradetracker' => 'TradeTracker', 'daisycon' => 'Daisycon', 'awin' => 'Awin', 'tradedoubler' => 'Tradedoubler', 'adtraction' => 'Adtraction', 'partnerize' => 'Partnerize' );
            $sel_nets = is_array( $o['cron_networks'] ) ? $o['cron_networks'] : array();
            foreach ( $all_nets as $val => $label ) : ?>
                <label style="display:block;margin-bottom:4px;">
                    <input type="checkbox" name="cron_networks[]" value="<?php echo $val; ?>" <?php checked( in_array( $val, $sel_nets, true ) ); ?>>
                    <?php echo esc_html( $label ); ?>
                </label>
            <?php endforeach; ?>
        </td>
    </tr>
</table>

<h3>Nu ophalen</h3>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
    <input type="hidden" name="action" value="ravn_affiliate_run_cron">
    <?php wp_nonce_field( 'ravn_affiliate_run_cron' ); ?>
    <input type="submit" value="Nieuwe gegevens nu ophalen" class="button button-secondary">
</form>

<?php // ── TAB: STATISTIEKEN ────────────────────────────────────────────────
elseif ( 'stats' === $tab ) : ?>
<h2>Statistieken</h2>
<table class="form-table">
    <tr>
        <th>Link tracking</th>
        <td><label><input type="checkbox" name="stats_link_tracking" <?php checked( $o['stats_link_tracking'] ); ?>> Activeer link tracking (klikken bijhouden)</label></td>
    </tr>
    <tr>
        <th>Voorraad tracking</th>
        <td><label><input type="checkbox" name="stats_stock_tracking" <?php checked( $o['stats_stock_tracking'] ); ?>> Activeer voorraad tracking</label></td>
    </tr>
</table>

<?php // ── TAB: API KOPPELINGEN ─────────────────────────────────────────────
elseif ( 'api' === $tab ) : ?>
<h2>API koppelingen</h2>

<h3>Bol.com (OAuth2 — Marketing Catalog API, voor productdata)</h3>
<div class="notice notice-info inline" style="margin:10px 0;padding:10px 14px;">
    <p style="margin:0.5em 0;"><strong>Let op:</strong> deze plugin gebruikt de <em>Marketing Catalog API</em>, speciaal voor affiliates. Vraag Client Credentials hiervoor aan <u>vanuit je bol.com Affiliate-account</u> (onder "Account" → API-toegang → Marketing API) — <u>niet</u> vanuit een verkoopaccount, want die geeft toegang tot de Retailer API en dat is een andere koppeling die hier niet werkt. Voor commissie op klikken vul je hieronder ook je <strong>Site ID</strong> in, eveneens te vinden in je Affiliate-account onder "Account".</p>
</div>
<table class="form-table">
    <tr><th><label for="bol_client_id">Client ID</label></th>
        <td><input type="text" id="bol_client_id" name="bol_client_id" value="<?php echo esc_attr( $o['bol_client_id'] ); ?>" class="large-text">
        <p class="description">Uit je bol.com Affiliate-account, aangemaakt voor de Marketing API (niet de Retailer API).</p></td></tr>
    <tr><th><label for="bol_client_secret">Client Secret</label></th>
        <td><input type="password" id="bol_client_secret" name="bol_client_secret" value="<?php echo esc_attr( $o['bol_client_secret'] ); ?>" class="large-text"></td></tr>
    <tr><th><label for="bol_country_code">Land</label></th>
        <td>
            <select id="bol_country_code" name="bol_country_code">
                <option value="NL" <?php selected( $o['bol_country_code'], 'NL' ); ?>>Nederland</option>
                <option value="BE" <?php selected( $o['bol_country_code'], 'BE' ); ?>>België</option>
            </select>
            <p class="description">Bol.com vereist een landcode bij elke productaanvraag; dit bepaalt welke aanbiedingen en prijzen worden opgehaald.</p>
        </td>
    </tr>
    <tr><th>Verbinding testen</th>
        <td>
            <button type="button" class="button" id="ravn-test-bol-connection">Verbinding testen</button>
            <span id="ravn-bol-test-result" style="display:none; margin-left:8px; padding:6px 10px; border-radius:3px; font-size:13px;"></span>
            <p class="description">Test met de waarden die nu in de velden hierboven staan — je hoeft niet eerst op te slaan.</p>
        </td>
    </tr>
</table>

<h3>Bol.com Affiliate (voor commissie)</h3>
<table class="form-table">
    <tr><th><label for="bol_site_id">Site ID</label></th>
        <td>
            <input type="text" id="bol_site_id" name="bol_site_id" value="<?php echo esc_attr( $o['bol_site_id'] ); ?>" class="regular-text" placeholder="Bijv. 1234567">
            <p class="description">Verplicht om commissie te ontvangen. Elke bol.com-link die de plugin toont wordt hiermee automatisch omgezet naar een geldige tracking-link (<code>partner.bol.com/click/click</code>).</p>
        </td>
    </tr>
</table>

<h3>Amazon (PA-API 5.0)</h3>
<table class="form-table">
    <tr><th><label for="amazon_access_key">Access Key</label></th>
        <td><input type="text" id="amazon_access_key" name="amazon_access_key" value="<?php echo esc_attr( $o['amazon_access_key'] ); ?>" class="large-text"></td></tr>
    <tr><th><label for="amazon_secret_key">Secret Key</label></th>
        <td><input type="password" id="amazon_secret_key" name="amazon_secret_key" value="<?php echo esc_attr( $o['amazon_secret_key'] ); ?>" class="large-text"></td></tr>
    <tr><th><label for="amazon_partner_tag">Partner Tag</label></th>
        <td><input type="text" id="amazon_partner_tag" name="amazon_partner_tag" value="<?php echo esc_attr( $o['amazon_partner_tag'] ); ?>" class="regular-text"></td></tr>
    <tr><th><label for="amazon_marketplace">Marketplace</label></th>
        <td>
            <select id="amazon_marketplace" name="amazon_marketplace">
                <?php foreach ( array( 'nl' => 'Nederland (amazon.nl)', 'de' => 'Duitsland (amazon.de)', 'fr' => 'Frankrijk (amazon.fr)', 'uk' => 'UK (amazon.co.uk)', 'us' => 'VS (amazon.com)' ) as $val => $label ) : ?>
                    <option value="<?php echo $val; ?>" <?php selected( $o['amazon_marketplace'], $val ); ?>><?php echo esc_html( $label ); ?></option>
                <?php endforeach; ?>
            </select>
        </td>
    </tr>
</table>

<h3>TradeTracker (SOAP API)</h3>
<div class="notice notice-info inline" style="margin:10px 0;padding:10px 14px;">
    <p style="margin:0.5em 0;">Naast Customer ID en Passphrase (API Key) is een <strong>Affiliate Site ID</strong> verplicht — te vinden in je TradeTracker-dashboard bij je aangemelde website.</p>
</div>
<table class="form-table">
    <tr><th><label for="tradetracker_customer_id">Customer ID</label></th>
        <td><input type="text" id="tradetracker_customer_id" name="tradetracker_customer_id" value="<?php echo esc_attr( $o['tradetracker_customer_id'] ); ?>" class="regular-text"></td></tr>
    <tr><th><label for="tradetracker_api_key">Passphrase (API Key)</label></th>
        <td><input type="password" id="tradetracker_api_key" name="tradetracker_api_key" value="<?php echo esc_attr( $o['tradetracker_api_key'] ); ?>" class="large-text"></td></tr>
    <tr><th><label for="tradetracker_site_id">Affiliate Site ID</label></th>
        <td><input type="text" id="tradetracker_site_id" name="tradetracker_site_id" value="<?php echo esc_attr( $o['tradetracker_site_id'] ); ?>" class="regular-text" placeholder="Bijv. 12345"></td></tr>
    <tr><th>Verbinding testen</th>
        <td>
            <button type="button" class="button" id="ravn-test-tradetracker-connection">Verbinding testen</button>
            <span id="ravn-tradetracker-test-result" style="display:none; margin-left:8px; padding:6px 10px; border-radius:3px; font-size:13px;"></span>
        </td>
    </tr>
</table>

<h3>Daisycon (Feed URL)</h3>
<table class="form-table">
    <tr><th><label for="daisycon_publisher_id">Publisher ID</label></th>
        <td><input type="text" id="daisycon_publisher_id" name="daisycon_publisher_id" value="<?php echo esc_attr( $o['daisycon_publisher_id'] ); ?>" class="regular-text"></td></tr>
    <tr><th><label for="daisycon_feed_url">Feed URL</label></th>
        <td><input type="url" id="daisycon_feed_url" name="daisycon_feed_url" value="<?php echo esc_attr( $o['daisycon_feed_url'] ); ?>" class="large-text"></td></tr>
</table>

<h3>Awin</h3>
<div class="notice notice-info inline" style="margin:10px 0;padding:10px 14px;">
    <p style="margin:0.5em 0;">Awin heeft geen algemene zoekfunctie. Je haalt de volledige productfeed op van één specifieke adverteerder waarmee je een goedgekeurde relatie hebt — vul daarom ook het <strong>Advertiser ID</strong> in (te vinden in je Awin-dashboard bij het betreffende programma).</p>
</div>
<table class="form-table">
    <tr><th><label for="awin_publisher_id">Publisher ID</label></th>
        <td><input type="text" id="awin_publisher_id" name="awin_publisher_id" value="<?php echo esc_attr( $o['awin_publisher_id'] ); ?>" class="regular-text"></td></tr>
    <tr><th><label for="awin_api_token">API-token</label></th>
        <td><input type="password" id="awin_api_token" name="awin_api_token" value="<?php echo esc_attr( $o['awin_api_token'] ); ?>" class="large-text">
        <p class="description">Uit je Awin-account onder API-instellingen.</p></td></tr>
    <tr><th><label for="awin_advertiser_id">Advertiser ID</label></th>
        <td><input type="text" id="awin_advertiser_id" name="awin_advertiser_id" value="<?php echo esc_attr( $o['awin_advertiser_id'] ); ?>" class="regular-text" placeholder="Bijv. 6789"></td></tr>
    <tr><th>Verbinding testen</th>
        <td>
            <button type="button" class="button" id="ravn-test-awin-connection">Verbinding testen</button>
            <span id="ravn-awin-test-result" style="display:none; margin-left:8px; padding:6px 10px; border-radius:3px; font-size:13px;"></span>
        </td>
    </tr>
</table>

<h3>Tradedoubler</h3>
<table class="form-table">
    <tr><th><label for="tradedoubler_org_id">Organization ID</label></th>
        <td><input type="text" id="tradedoubler_org_id" name="tradedoubler_org_id" value="<?php echo esc_attr( $o['tradedoubler_org_id'] ); ?>" class="regular-text"></td></tr>
    <tr><th><label for="tradedoubler_token">API Token</label></th>
        <td><input type="password" id="tradedoubler_token" name="tradedoubler_token" value="<?php echo esc_attr( $o['tradedoubler_token'] ); ?>" class="large-text"></td></tr>
</table>

<h3>Adtraction</h3>
<table class="form-table">
    <tr><th><label for="adtraction_api_key">API Key</label></th>
        <td><input type="text" id="adtraction_api_key" name="adtraction_api_key" value="<?php echo esc_attr( $o['adtraction_api_key'] ); ?>" class="large-text"></td></tr>
    <tr><th><label for="adtraction_channel_id">Channel ID</label></th>
        <td><input type="text" id="adtraction_channel_id" name="adtraction_channel_id" value="<?php echo esc_attr( $o['adtraction_channel_id'] ); ?>" class="regular-text"></td></tr>
</table>

<h3>Partnerize</h3>
<table class="form-table">
    <tr><th><label for="partnerize_user_api_key">User API Key</label></th>
        <td><input type="password" id="partnerize_user_api_key" name="partnerize_user_api_key" value="<?php echo esc_attr( $o['partnerize_user_api_key'] ); ?>" class="large-text"></td></tr>
    <tr><th><label for="partnerize_app_api_key">Application API Key</label></th>
        <td><input type="password" id="partnerize_app_api_key" name="partnerize_app_api_key" value="<?php echo esc_attr( $o['partnerize_app_api_key'] ); ?>" class="large-text"></td></tr>
</table>

<?php // ── TAB: MODULES ─────────────────────────────────────────────────────
elseif ( 'modules' === $tab ) : ?>
<h2>Modules in- en uitschakelen</h2>
<table class="form-table">
    <tr><th>FAQ module</th><td><label><input type="checkbox" name="module_faq" <?php checked( $o['module_faq'] ); ?>> Activeer veelgestelde vragen module</label></td></tr>
    <tr><th>TOC module</th><td><label><input type="checkbox" name="module_toc" <?php checked( $o['module_toc'] ); ?>> Activeer automatische inhoudsopgave</label></td></tr>
    <tr><th>Schema module</th><td><label><input type="checkbox" name="module_schema" <?php checked( $o['module_schema'] ); ?>> Activeer schema markup (JSON-LD)</label></td></tr>
    <tr><th>Statistieken module</th><td><label><input type="checkbox" name="module_stats" <?php checked( $o['module_stats'] ); ?>> Activeer statistieken en dashboard widget</label></td></tr>
    <tr><th>Import module</th><td><label><input type="checkbox" name="module_import" <?php checked( $o['module_import'] ); ?>> Activeer bulkimport module</label></td></tr>
</table>

<?php // ── TAB: ZOEKEN ───────────────────────────────────────────────────────
elseif ( 'search' === $tab ) : ?>
<h2>Zoeken</h2>
<table class="form-table">
    <tr>
        <th>Site zoekresultaten</th>
        <td>
            <label><input type="checkbox" name="exclude_from_search" <?php checked( $o['exclude_from_search'] ); ?>> Sluit affiliate producten uit van zoekresultaten</label>
            <p class="description">Dit gaat over de WordPress zoekfunctie, niet over zoekmachines zoals Google.</p>
        </td>
    </tr>
</table>
<?php endif; ?>

<p class="submit">
    <input type="submit" value="Instellingen opslaan" class="button button-primary button-large">
</p>
</div><!-- .ravn-tab-content -->
</div><!-- .ravn-tabs-container -->
</form>

<hr style="margin:24px 0;">
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
      onsubmit="return confirm('Weet je zeker dat je de instellingen van dit tabblad terugzet naar de standaardwaarden?');">
    <input type="hidden" name="action" value="ravn_affiliate_reset_tab">
    <input type="hidden" name="settings_tab" value="<?php echo esc_attr( $tab ); ?>">
    <?php wp_nonce_field( 'ravn_affiliate_reset_tab' ); ?>
    <button type="submit" class="button button-secondary">Dit tabblad terugzetten naar standaardwaarden</button>
    <p class="description" style="margin-top:6px;">
        Handig als instellingen ongewild zijn uitgezet. Raakt alleen dit tabblad.
    </p>
</form>
</div>
