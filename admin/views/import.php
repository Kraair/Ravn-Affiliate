<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$stab = isset( $_GET['stab'] ) ? sanitize_key( $_GET['stab'] ) : 'file';
$o    = Ravn_Options::get_all();

$networks = array(
    ''             => 'Geen',
    'bol'          => 'Bol.com',
    'amazon'       => 'Amazon',
    'tradetracker' => 'TradeTracker',
    'daisycon'     => 'Daisycon',
    'awin'         => 'Awin',
    'tradedoubler' => 'Tradedoubler',
    'adtraction'   => 'Adtraction',
    'partnerize'   => 'Partnerize',
);

$post_url = esc_url( admin_url( 'admin-post.php' ) );
?>
<div class="wrap ravn-wrap">
    <h1>Producten importeren</h1>

    <ul class="ravn-sub-tabs nav-tab-wrapper">
        <li><a href="?page=ravn-affiliate-import&stab=file"         class="nav-tab <?php echo $stab === 'file'         ? 'nav-tab-active' : ''; ?>">Bestand uploaden</a></li>
        <li><a href="?page=ravn-affiliate-import&stab=url"          class="nav-tab <?php echo $stab === 'url'          ? 'nav-tab-active' : ''; ?>">Feed URL</a></li>
        <li><a href="?page=ravn-affiliate-import&stab=tradetracker" class="nav-tab <?php echo $stab === 'tradetracker' ? 'nav-tab-active' : ''; ?>">TradeTracker</a></li>
        <li><a href="?page=ravn-affiliate-import&stab=daisycon"     class="nav-tab <?php echo $stab === 'daisycon'     ? 'nav-tab-active' : ''; ?>">Daisycon</a></li>
    </ul>

    <div class="ravn-tab-content" style="margin-top:16px;">
    <?php if ( 'file' === $stab ) : ?>

        <h2>Bestand uploaden</h2>
        <p>Upload een CSV of XML bestand met productgegevens.</p>
        <form method="post" action="<?php echo $post_url; ?>" enctype="multipart/form-data">
            <input type="hidden" name="action" value="ravn_import_file">
            <input type="hidden" name="MAX_FILE_SIZE" value="5242880">
            <?php wp_nonce_field( 'ravn_import_file' ); ?>
            <table class="form-table ravn-form-table">
                <tr>
                    <th><label for="ravn_import_file">Bestand</label></th>
                    <td>
                        <input type="file" id="ravn_import_file" name="ravn_import_file" accept=".csv,.xml,.txt,text/csv,text/xml" required>
                        <p class="description">Ondersteunde formaten: CSV, XML. Maximaal 5 MB.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="ravn_delimiter">CSV scheidingsteken</label></th>
                    <td>
                        <select id="ravn_delimiter" name="ravn_delimiter">
                            <option value=";">Puntkomma (;)</option>
                            <option value=",">Komma (,)</option>
                            <option value="	">Tab</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="ravn_network_file">Standaard netwerk</label></th>
                    <td>
                        <select id="ravn_network_file" name="ravn_network">
                            <?php foreach ( $networks as $val => $label ) : ?>
                                <option value="<?php echo esc_attr( $val ); ?>"><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </table>
            <?php submit_button( 'Importeren' ); ?>
        </form>

    <?php elseif ( 'url' === $stab ) : ?>

        <h2>Feed URL importeren</h2>
        <p>Importeer producten vanuit een externe CSV of XML feed URL.</p>
        <form method="post" action="<?php echo $post_url; ?>">
            <input type="hidden" name="action" value="ravn_import_feed">
            <?php wp_nonce_field( 'ravn_import_feed' ); ?>
            <table class="form-table ravn-form-table">
                <tr>
                    <th><label for="ravn_feed_url">Feed URL</label></th>
                    <td><input type="url" id="ravn_feed_url" name="ravn_feed_url" class="large-text" required placeholder="https://example.com/feed.csv"></td>
                </tr>
                <tr>
                    <th><label for="ravn_network_url">Standaard netwerk</label></th>
                    <td>
                        <select id="ravn_network_url" name="ravn_network">
                            <?php foreach ( $networks as $val => $label ) : ?>
                                <option value="<?php echo esc_attr( $val ); ?>"><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </table>
            <?php submit_button( 'Feed importeren' ); ?>
        </form>

    <?php elseif ( 'tradetracker' === $stab ) : ?>

        <h2>TradeTracker importeren</h2>
        <?php if ( empty( $o['tradetracker_customer_id'] ) || empty( $o['tradetracker_api_key'] ) ) : ?>
            <div class="notice notice-warning inline"><p>
                Vul eerst je TradeTracker API-gegevens in op de
                <a href="?page=ravn-affiliate-settings&tab=api">API instellingen pagina</a>.
            </p></div>
        <?php else : ?>
            <p>Importeer producten direct vanuit het TradeTracker netwerk via de API.</p>
            <form method="post" action="<?php echo $post_url; ?>">
                <input type="hidden" name="action" value="ravn_import_tradetracker">
                <?php wp_nonce_field( 'ravn_import_tradetracker' ); ?>
                <table class="form-table ravn-form-table">
                    <tr>
                        <th><label for="ravn_ean">EAN (optioneel)</label></th>
                        <td>
                            <input type="text" id="ravn_ean" name="ravn_ean" class="regular-text" placeholder="Filter op EAN">
                            <p class="description">Laat leeg om de volledige productfeed op te halen.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button( 'TradeTracker importeren' ); ?>
            </form>
        <?php endif; ?>

    <?php elseif ( 'daisycon' === $stab ) : ?>

        <h2>Daisycon importeren</h2>
        <?php if ( empty( $o['daisycon_feed_url'] ) ) : ?>
            <div class="notice notice-warning inline"><p>
                Vul eerst je Daisycon feed-URL in op de
                <a href="?page=ravn-affiliate-settings&tab=api">API instellingen pagina</a>.
            </p></div>
        <?php else : ?>
            <p>Importeer producten vanuit de ingestelde Daisycon feed.</p>
            <form method="post" action="<?php echo $post_url; ?>">
                <input type="hidden" name="action" value="ravn_import_daisycon">
                <?php wp_nonce_field( 'ravn_import_daisycon' ); ?>
                <p><strong>Feed URL:</strong> <code><?php echo esc_html( $o['daisycon_feed_url'] ); ?></code></p>
                <?php submit_button( 'Daisycon importeren' ); ?>
            </form>
        <?php endif; ?>

    <?php endif; ?>
    </div>
</div>
