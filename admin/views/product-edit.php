<?php if ( ! defined( 'ABSPATH' ) ) exit;

$product_id = isset( $_GET['product_id'] ) ? intval( $_GET['product_id'] ) : 0;
$product    = $product_id ? Ravn_Database::get_product( $product_id ) : null;
$offers     = $product_id ? Ravn_Database::get_offers( $product_id ) : array();
$is_new     = ! $product;
$title      = $is_new ? 'Product toevoegen' : 'Product bewerken: ' . esc_html( $product->title );

$p = array(
    'id'           => $product_id,
    'title'        => $product ? $product->title : '',
    'description'  => $product ? $product->description : '',
    'image_url'    => $product ? $product->image_url : '',
    'ean'          => $product ? $product->ean : '',
    'rating'       => $product ? $product->rating : '',
    'review_count' => $product ? $product->review_count : '',
    'label_text'   => $product ? $product->label_text : '',
    'label_color'  => $product ? ( $product->label_color ?: '#e74c3c' ) : '#e74c3c',
    'label_icon'   => $product ? $product->label_icon : '',
    'cloak_slug'   => $product ? $product->cloak_slug : '',
);

$networks = array( '' => '— geen —', 'bol' => 'Bol.com', 'amazon' => 'Amazon', 'awin' => 'Awin', 'tradetracker' => 'TradeTracker', 'daisycon' => 'Daisycon', 'tradedoubler' => 'Tradedoubler', 'adtraction' => 'Adtraction', 'partnerize' => 'Partnerize', 'manual' => 'Handmatig' );
$stock_options = array( 'in_stock' => 'Op voorraad', 'out_of_stock' => 'Niet op voorraad', 'unknown' => 'Onbekend' );
?>
<div class="wrap">
<h1><?php echo $title; ?></h1>

<?php if ( $product_id ) : ?>
<p><strong>Shortcode:</strong>
    <code class="ravn-copy-shortcode" title="Klik om te kopiëren">[ravn_product id="<?php echo $product_id; ?>"]</code>
    <?php if ( $p['cloak_slug'] ) : ?>
    &nbsp;&nbsp;<strong>Cloaked URL:</strong> <code><?php echo esc_html( home_url( '/go/' . $p['cloak_slug'] . '/' ) ); ?></code>
    <?php endif; ?>
</p>
<?php endif; ?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
    <input type="hidden" name="action" value="ravn_save_product">
    <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">
    <?php wp_nonce_field( 'ravn_save_product' ); ?>

    <div id="poststuff">
        <div id="post-body" class="metabox-holder columns-2">
            <div id="post-body-content">

                <!-- Basisinformatie -->
                <div class="postbox">
                    <h2 class="hndle">Productinformatie</h2>
                    <div class="inside">

                        <!-- EAN zoeker -->
                        <div class="ravn-ean-finder" style="margin-bottom:20px;padding:16px;background:#f0f0f1;border-radius:4px;">
                            <h3 style="margin-top:0">EAN code zoeker</h3>
                            <p style="margin-top:0;color:#666;font-size:.9em;">Zoek een product op naam of EAN code om het automatisch in te laden.</p>
                            <div style="display:flex;gap:8px;">
                                <input type="text" id="ravn-ean-search" placeholder="Zoek op naam of EAN..." style="flex:1;" class="regular-text">
                                <button type="button" id="ravn-ean-search-btn" class="button button-primary">Zoeken</button>
                            </div>
                            <div id="ravn-ean-results" style="margin-top:12px;"></div>
                        </div>

                        <!-- Bol.com prijsdiagnose per EAN -->
                        <div class="ravn-bol-diag" style="margin-bottom:20px;padding:16px;background:#fff8f0;border:1px solid #f0d9b5;border-radius:4px;">
                            <h3 style="margin-top:0">Bol.com prijs testen op EAN</h3>
                            <p style="margin-top:0;color:#666;font-size:.9em;">Test direct wat bol.com voor één specifiek EAN teruggeeft — handig als een zoekresultaat geen prijs toont.</p>
                            <div style="display:flex;gap:8px;">
                                <input type="text" id="ravn-bol-diag-ean" placeholder="Bijv. 6950000116475" style="flex:1;" class="regular-text">
                                <button type="button" id="ravn-bol-diag-btn" class="button">Testen</button>
                            </div>
                            <div id="ravn-bol-diag-result" style="margin-top:12px; font-family:monospace; font-size:12px; white-space:pre-wrap; background:#fff; border:1px solid #ddd; border-radius:4px; padding:10px; display:none;"></div>
                        </div>

                        <table class="form-table">
                            <tr>
                                <th><label for="ravn-title">Producttitel *</label></th>
                                <td><input type="text" id="ravn-title" name="title" value="<?php echo esc_attr( $p['title'] ); ?>" class="large-text" required></td>
                            </tr>
                            <tr>
                                <th><label for="ravn-desc">Beschrijving</label></th>
                                <td><textarea id="ravn-desc" name="description" rows="4" class="large-text"><?php echo esc_textarea( $p['description'] ); ?></textarea></td>
                            </tr>
                            <tr>
                                <th><label for="ravn-image">Afbeelding URL</label></th>
                                <td>
                                    <input type="url" id="ravn-image" name="image_url" value="<?php echo esc_attr( $p['image_url'] ); ?>" class="large-text">
                                    <?php if ( $p['image_url'] ) : ?>
                                        <br><img src="<?php echo esc_url( $p['image_url'] ); ?>" style="max-width:120px;max-height:120px;margin-top:8px;" alt="">
                                    <?php endif; ?>
                                    <button type="button" class="button" id="ravn-media-btn">Mediabibliotheek</button>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="ravn-ean">EAN code</label></th>
                                <td><input type="text" id="ravn-ean" name="ean" value="<?php echo esc_attr( $p['ean'] ); ?>" class="regular-text" placeholder="Bijv. 8710103551492"></td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Beoordeling & Label -->
                <div class="postbox">
                    <h2 class="hndle">Beoordeling &amp; Productlabel</h2>
                    <div class="inside">
                        <table class="form-table">
                            <tr>
                                <th><label for="ravn-rating">Beoordeling (0-5)</label></th>
                                <td>
                                    <input type="number" id="ravn-rating" name="rating" value="<?php echo esc_attr( $p['rating'] ); ?>" min="0" max="5" step="0.1" class="small-text">
                                    &nbsp;
                                    <label for="ravn-review-count">Aantal reviews:</label>
                                    <input type="number" id="ravn-review-count" name="review_count" value="<?php echo esc_attr( $p['review_count'] ); ?>" min="0" class="small-text">
                                </td>
                            </tr>
                            <tr>
                                <th><label for="ravn-label-text">Label tekst</label></th>
                                <td>
                                    <input type="text" id="ravn-label-text" name="label_text" value="<?php echo esc_attr( $p['label_text'] ); ?>" class="regular-text" placeholder="Bijv. Beste koop">
                                    <p class="description">Laat leeg om geen label te tonen.</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="ravn-label-color">Label kleur</label></th>
                                <td><input type="text" id="ravn-label-color" name="label_color" value="<?php echo esc_attr( $p['label_color'] ); ?>" class="ravn-color-picker"></td>
                            </tr>
                            <tr>
                                <th><label for="ravn-label-icon">Label icoon</label></th>
                                <td>
                                    <input type="text" id="ravn-label-icon" name="label_icon" value="<?php echo esc_attr( $p['label_icon'] ); ?>" class="regular-text" placeholder="Bijv. 🏆 of ✅">
                                    <p class="description">Emoji of tekst icoon naast de labeltekst.</p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Aanbieders -->
                <div class="postbox">
                    <h2 class="hndle">Aanbieders/Verkopers</h2>
                    <div class="inside">
                        <div id="ravn-offers-container">
                            <?php foreach ( $offers as $idx => $offer ) : ?>
                            <div class="ravn-offer-row" style="border:1px solid #ddd;padding:16px;margin-bottom:12px;border-radius:4px;position:relative;">
                                <button type="button" class="button ravn-remove-offer" style="position:absolute;top:8px;right:8px;" data-offer-id="<?php echo $offer->id; ?>">✕ Verwijder</button>
                                <input type="hidden" name="offers[<?php echo $idx; ?>][id]" value="<?php echo $offer->id; ?>">
                                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                                    <p>
                                        <label>Verkopernaam:</label><br>
                                        <input type="text" name="offers[<?php echo $idx; ?>][seller_name]" value="<?php echo esc_attr( $offer->seller_name ); ?>" class="widefat">
                                    </p>
                                    <p>
                                        <label>Verkoper domein (bijv. bol.com):</label><br>
                                        <input type="text" name="offers[<?php echo $idx; ?>][seller_domain]" value="<?php echo esc_attr( $offer->seller_domain ); ?>" class="widefat">
                                    </p>
                                    <p>
                                        <label>Prijs (bijv. 29.99):</label><br>
                                        <input type="number" name="offers[<?php echo $idx; ?>][price]" value="<?php echo esc_attr( $offer->price ); ?>" step="0.01" class="widefat">
                                    </p>
                                    <p>
                                        <label>Valuta:</label><br>
                                        <select name="offers[<?php echo $idx; ?>][currency]" class="widefat">
                                            <?php foreach ( array( 'EUR', 'USD', 'GBP' ) as $cur ) : ?>
                                                <option value="<?php echo $cur; ?>" <?php selected( $offer->currency, $cur ); ?>><?php echo $cur; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </p>
                                    <p>
                                        <label>Voorraadstatus:</label><br>
                                        <select name="offers[<?php echo $idx; ?>][stock_status]" class="widefat">
                                            <?php foreach ( $stock_options as $val => $label ) : ?>
                                                <option value="<?php echo $val; ?>" <?php selected( $offer->stock_status, $val ); ?>><?php echo $label; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </p>
                                    <p>
                                        <label>Netwerk:</label><br>
                                        <select name="offers[<?php echo $idx; ?>][network]" class="widefat">
                                            <?php foreach ( $networks as $val => $label ) : ?>
                                                <option value="<?php echo $val; ?>" <?php selected( $offer->network, $val ); ?>><?php echo $label; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </p>
                                    <p style="grid-column:span 2;">
                                        <label>Affiliate URL:</label><br>
                                        <input type="url" name="offers[<?php echo $idx; ?>][affiliate_url]" value="<?php echo esc_attr( $offer->affiliate_url ); ?>" class="widefat">
                                        <span class="description">Bij netwerk "Bol.com" volstaat een gewone productlink — de plugin zet deze automatisch om naar een tracking-link met je Site ID (in te stellen bij Instellingen → API koppelingen).</span>
                                    </p>
                                    <p>
                                        <label>Sub ID (optioneel):</label><br>
                                        <input type="text" name="offers[<?php echo $idx; ?>][sub_id]" value="<?php echo esc_attr( $offer->sub_id ); ?>" class="widefat">
                                    </p>
                                    <p>
                                        <label>Aangepast logo URL:</label><br>
                                        <input type="url" name="offers[<?php echo $idx; ?>][logo_url]" value="<?php echo esc_attr( $offer->logo_url ); ?>" class="widefat">
                                    </p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <button type="button" class="button" id="ravn-add-offer-btn">+ Aanbieder toevoegen</button>

                        <script type="text/html" id="ravn-offer-template">
                        <div class="ravn-offer-row" style="border:1px solid #ddd;padding:16px;margin-bottom:12px;border-radius:4px;position:relative;">
                            <button type="button" class="button ravn-remove-offer" style="position:absolute;top:8px;right:8px;" data-offer-id="0">✕ Verwijder</button>
                            <input type="hidden" name="offers[#IDX#][id]" value="0">
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                                <p><label>Verkopernaam:</label><br><input type="text" name="offers[#IDX#][seller_name]" value="" class="widefat"></p>
                                <p><label>Verkoper domein:</label><br><input type="text" name="offers[#IDX#][seller_domain]" value="" class="widefat"></p>
                                <p><label>Prijs:</label><br><input type="number" name="offers[#IDX#][price]" value="" step="0.01" class="widefat"></p>
                                <p><label>Valuta:</label><br>
                                    <select name="offers[#IDX#][currency]" class="widefat">
                                        <option value="EUR">EUR</option><option value="USD">USD</option><option value="GBP">GBP</option>
                                    </select>
                                </p>
                                <p><label>Voorraadstatus:</label><br>
                                    <select name="offers[#IDX#][stock_status]" class="widefat">
                                        <option value="unknown">Onbekend</option>
                                        <option value="in_stock">Op voorraad</option>
                                        <option value="out_of_stock">Niet op voorraad</option>
                                    </select>
                                </p>
                                <p><label>Netwerk:</label><br>
                                    <select name="offers[#IDX#][network]" class="widefat">
                                        <?php foreach ( $networks as $val => $label ) echo '<option value="' . esc_attr( $val ) . '">' . esc_html( $label ) . '</option>'; ?>
                                    </select>
                                </p>
                                <p style="grid-column:span 2;"><label>Affiliate URL:</label><br><input type="url" name="offers[#IDX#][affiliate_url]" value="" class="widefat"><span class="description">Bij "Bol.com" volstaat een gewone productlink — zie Instellingen → API koppelingen voor je Site ID.</span></p>
                                <p><label>Sub ID:</label><br><input type="text" name="offers[#IDX#][sub_id]" value="" class="widefat"></p>
                                <p><label>Aangepast logo URL:</label><br><input type="url" name="offers[#IDX#][logo_url]" value="" class="widefat"></p>
                            </div>
                        </div>
                        </script>
                    </div>
                </div>

            </div><!-- #post-body-content -->

            <div id="postbox-container-1" class="postbox-container">
                <!-- Opslaan -->
                <div class="postbox">
                    <h2 class="hndle">Opslaan</h2>
                    <div class="inside">
                        <input type="submit" value="<?php echo $is_new ? 'Product aanmaken' : 'Wijzigingen opslaan'; ?>" class="button button-primary button-large">
                        <?php if ( $product_id ) : ?>
                            <br><br>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=ravn-affiliate' ) ); ?>">← Terug naar overzicht</a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Cloaking -->
                <div class="postbox">
                    <h2 class="hndle">Link cloaking</h2>
                    <div class="inside">
                        <p><label for="ravn-cloak-slug">Cloaking slug:</label></p>
                        <input type="text" id="ravn-cloak-slug" name="cloak_slug" value="<?php echo esc_attr( $p['cloak_slug'] ); ?>" class="widefat">
                        <?php if ( $p['cloak_slug'] ) : ?>
                            <p class="description">URL: <code><?php echo esc_html( home_url( '/go/' . $p['cloak_slug'] . '/' ) ); ?></code></p>
                        <?php else : ?>
                            <p class="description">Wordt automatisch gevuld op basis van de producttitel.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div><!-- .postbox-container -->
        </div><!-- #post-body -->
    </div><!-- #poststuff -->
</form>
</div>
