<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$o    = Ravn_Options::get_all();
$stab = isset( $_GET['stab'] ) ? sanitize_key( $_GET['stab'] ) : 'general';

$tabs = array(
    'general'     => 'Algemeen',
    'stars'       => 'Sterren',
    'divider'     => 'Scheidingslijn',
    'border'      => 'Rand',
    'columns'     => 'Kolommen',
    'carousel'    => 'Carrousel',
    'image'       => 'Afbeeldingen',
    'title'       => 'Titel',
    'description' => 'Beschrijving',
    'label'       => 'Productlabel',
    'seller_desc' => 'Verkopersbeschrijving',
    'sellers'     => 'Verkopers',
    'seller_cta'  => 'Verkoper CTA',
    'show_all'    => 'Toon alle verkopers',
    'updated'     => 'Laatste bijgewerkt',
    'popup'       => 'Popup',
);

/* ── Render helpers ─────────────────────────────────────────────── */

if ( ! function_exists( 'ravn_field_row' ) ) {
    function ravn_field_row( $label, $html ) {
        echo '<tr><th>' . esc_html( $label ) . '</th><td>' . $html . '</td></tr>';
    }
    function ravn_num( $o, $key, $label, $suffix = 'px', $min = 0, $max = 200 ) {
        $val = isset( $o[ $key ] ) ? esc_attr( $o[ $key ] ) : '';
        $h   = '<input type="number" name="' . esc_attr( $key ) . '" value="' . $val . '" min="' . intval( $min ) . '" max="' . intval( $max ) . '" style="width:90px"> ' . esc_html( $suffix );
        ravn_field_row( $label, $h );
    }
    function ravn_color( $o, $key, $label ) {
        $val = isset( $o[ $key ] ) ? esc_attr( $o[ $key ] ) : '';
        $h   = '<input type="text" name="' . esc_attr( $key ) . '" value="' . $val . '" class="ravn-color-picker">';
        ravn_field_row( $label, $h );
    }
    function ravn_check( $o, $key, $label, $desc = '' ) {
        $checked = ! empty( $o[ $key ] ) ? 'checked' : '';
        $h = '<label><input type="checkbox" name="' . esc_attr( $key ) . '" value="1" ' . $checked . '> ' . esc_html( $desc ?: 'Inschakelen' ) . '</label>';
        ravn_field_row( $label, $h );
    }
    function ravn_size( $o, $key, $label ) {
        $val  = isset( $o[ $key ] ) ? $o[ $key ] : 'medium';
        $opts = array( 'small' => 'Klein', 'medium' => 'Normaal', 'large' => 'Groot' );
        $h    = '<select name="' . esc_attr( $key ) . '">';
        foreach ( $opts as $v => $l ) {
            $h .= '<option value="' . esc_attr( $v ) . '" ' . selected( $val, $v, false ) . '>' . esc_html( $l ) . '</option>';
        }
        $h .= '</select>';
        ravn_field_row( $label, $h );
    }
    function ravn_select( $o, $key, $label, $choices ) {
        $val = isset( $o[ $key ] ) ? $o[ $key ] : '';
        $h   = '<select name="' . esc_attr( $key ) . '">';
        foreach ( $choices as $v => $l ) {
            $h .= '<option value="' . esc_attr( $v ) . '" ' . selected( $val, $v, false ) . '>' . esc_html( $l ) . '</option>';
        }
        $h .= '</select>';
        ravn_field_row( $label, $h );
    }
    function ravn_weight( $o, $key, $label ) {
        ravn_select( $o, $key, $label, array(
            '400' => 'Normaal (400)',
            '600' => 'Halfvet (600)',
            '700' => 'Vet (700)',
        ) );
    }
}
?>
<div class="wrap ravn-wrap">
    <h1>Ravn Affiliate — Styling</h1>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <input type="hidden" name="action" value="ravn_affiliate_save_styling">
        <input type="hidden" name="stab" value="<?php echo esc_attr( $stab ); ?>">
        <?php wp_nonce_field( 'ravn_affiliate_save_styling' ); ?>

        <div class="ravn-tabs-container">
            <ul class="ravn-tabs-nav">
                <?php foreach ( $tabs as $key => $label ) : ?>
                    <li>
                        <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'ravn-affiliate-styling', 'stab' => $key ), admin_url( 'admin.php' ) ) ); ?>"
                           class="<?php echo $stab === $key ? 'active' : ''; ?>">
                            <?php echo esc_html( $label ); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>

            <div class="ravn-tab-content">
                <table class="form-table ravn-form-table">
                <?php
                switch ( $stab ) {

                    case 'general':
                        ravn_num(   $o, 'style_padding',              'Padding productbox' );
                        ravn_num(   $o, 'style_margin_top',           'Marge boven' );
                        ravn_num(   $o, 'style_gap',                  'Ruimte tussen producten' );
                        ravn_num(   $o, 'style_content_margin_bottom','Marge onder inhoud' );
                        ravn_color( $o, 'style_bg_color',             'Achtergrondkleur' );
                        break;

                    case 'stars':
                        ravn_num(   $o, 'style_stars_margin_top',      'Marge boven' );
                        ravn_color( $o, 'style_star_active_color',     'Kleur gevulde ster' );
                        ravn_color( $o, 'style_star_inactive_color',   'Kleur lege ster' );
                        break;

                    case 'divider':
                        ravn_num(   $o, 'style_divider_height', 'Dikte', 'px', 0, 20 );
                        ravn_num(   $o, 'style_divider_margin', 'Marge boven/onder' );
                        break;

                    case 'border':
                        ravn_num(    $o, 'style_border_width',  'Randdikte', 'px', 0, 20 );
                        ravn_num(    $o, 'style_border_radius', 'Randradius', 'px', 0, 50 );
                        ravn_color(  $o, 'style_border_color',  'Randkleur' );
                        ravn_select( $o, 'style_border_position', 'Randpositie', array(
                            'all'    => 'Alle zijden',
                            'top'    => 'Boven',
                            'right'  => 'Rechts',
                            'bottom' => 'Onder',
                            'left'   => 'Links',
                        ) );
                        ravn_check( $o, 'style_shadow',        'Slagschaduw', 'Schaduw om productbox tonen' );
                        ravn_num(   $o, 'style_shadow_x',      'Afstand X', 'px', -50, 50 );
                        ravn_num(   $o, 'style_shadow_y',      'Afstand Y', 'px', -50, 50 );
                        ravn_num(   $o, 'style_shadow_blur',   'Vervaging (blur)', 'px', 0, 100 );
                        ravn_num(   $o, 'style_shadow_spread', 'Spreiding', 'px', -50, 50 );
                        ravn_color( $o, 'style_shadow_color',  'Schaduwkleur' );
                        ravn_num(   $o, 'style_shadow_opacity','Dekking', '%', 0, 100 );
                        break;

                    case 'columns':
                        ravn_num(    $o, 'style_col_gap', 'Kolomafstand', 'px', 0, 80 );
                        ravn_select( $o, 'style_col_align', 'Uitlijning', array(
                            'left'   => 'Links',
                            'center' => 'Midden',
                            'right'  => 'Rechts',
                        ) );
                        break;

                    case 'carousel':
                        ravn_color( $o, 'style_carousel_arrow_color',      'Pijlkleur' );
                        ravn_color( $o, 'style_carousel_dot_color',        'Dotkleur' );
                        ravn_color( $o, 'style_carousel_dot_active_color', 'Actieve dotkleur' );
                        break;

                    case 'image':
                        ravn_check( $o, 'style_img_blend', 'Blend-modus', 'Afbeelding mengen met achtergrond (multiply)' );
                        break;

                    case 'title':
                        ravn_num(    $o, 'style_title_margin_bottom', 'Marge onder' );
                        ravn_size(   $o, 'style_title_size',          'Lettergrootte' );
                        ravn_weight( $o, 'style_title_weight',        'Gewicht' );
                        ravn_color(  $o, 'style_title_color',         'Kleur (leeg = thema)' );
                        ravn_check(  $o, 'style_title_underline',     'Onderstrepen', 'Titel onderstrepen' );
                        break;

                    case 'description':
                        ravn_num( $o, 'style_desc_margin_top', 'Marge boven' );
                        break;

                    case 'label':
                        ravn_num(    $o, 'style_label_padding',    'Padding', 'px', 0, 30 );
                        ravn_num(    $o, 'style_label_margin_top', 'Marge boven' );
                        ravn_num(    $o, 'style_label_radius',     'Randradius', 'px', 0, 30 );
                        ravn_size(   $o, 'style_label_size',       'Lettergrootte' );
                        ravn_weight( $o, 'style_label_weight',     'Gewicht' );
                        ravn_select( $o, 'style_label_icon_align', 'Icoon uitlijning', array(
                            'left'  => 'Links van tekst',
                            'right' => 'Rechts van tekst',
                        ) );
                        ravn_check(  $o, 'style_label_shadow',     'Slagschaduw', 'Schaduw op label' );
                        break;

                    case 'seller_desc':
                        ravn_num(  $o, 'style_sellers_desc_margin', 'Marge onder' );
                        ravn_size( $o, 'style_sellers_desc_size',   'Lettergrootte' );
                        break;

                    case 'sellers':
                        ravn_num(    $o, 'style_seller_padding', 'Padding rij', 'px', 0, 30 );
                        ravn_num(    $o, 'style_seller_gap',     'Ruimte tussen rijen' );
                        ravn_num(    $o, 'style_seller_radius',  'Randradius', 'px', 0, 30 );
                        ravn_size(   $o, 'style_seller_size',    'Lettergrootte' );
                        ravn_weight( $o, 'style_seller_weight',  'Gewicht' );
                        ravn_color(  $o, 'style_seller_bg',      'Achtergrondkleur rij (leeg = geen)' );
                        ravn_color(  $o, 'style_seller_color',   'Tekstkleur (leeg = thema)' );
                        ravn_check(  $o, 'style_seller_shadow',    'Slagschaduw', 'Schaduw op verkopersrij' );
                        ravn_check(  $o, 'style_seller_underline', 'Onderstrepen', 'Verkopernaam onderstrepen' );
                        echo '<tr><th colspan="2"><h3 style="margin:16px 0 4px">Voorraadkleuren</h3></th></tr>';
                        ravn_color(  $o, 'style_seller_stock_in',      'Op voorraad' );
                        ravn_color(  $o, 'style_seller_stock_out',     'Niet op voorraad' );
                        ravn_color(  $o, 'style_seller_stock_unknown', 'Onbekend' );
                        break;

                    case 'seller_cta':
                        ravn_num(    $o, 'style_cta_padding',       'Padding', 'px', 0, 30 );
                        ravn_num(    $o, 'style_cta_margin_bottom', 'Marge onder' );
                        ravn_num(    $o, 'style_cta_radius',        'Randradius', 'px', 0, 30 );
                        ravn_size(   $o, 'style_cta_size',          'Lettergrootte' );
                        ravn_weight( $o, 'style_cta_weight',        'Gewicht' );
                        ravn_color(  $o, 'style_cta_bg',            'Achtergrondkleur (leeg = primair)' );
                        ravn_color(  $o, 'style_cta_color',         'Tekstkleur' );
                        ravn_check(  $o, 'style_cta_shadow',        'Slagschaduw', 'Schaduw op knop' );
                        ravn_check(  $o, 'style_cta_underline',     'Onderstrepen', 'Knoptekst onderstrepen' );
                        ravn_check(  $o, 'style_cta_icon',          'Icoon tonen', 'Icoon in knop tonen' );
                        ravn_select( $o, 'style_cta_icon_name',     'Icoon', array(
                            'arrow-right' => 'Pijl',
                            'cart'        => 'Winkelwagen',
                            'check'       => 'Vinkje',
                            'star'        => 'Ster',
                        ) );
                        ravn_select( $o, 'style_cta_icon_align',    'Icoon uitlijning', array(
                            'left'  => 'Links',
                            'right' => 'Rechts',
                        ) );
                        echo '<tr><th colspan="2"><h3 style="margin:16px 0 4px">CTA info-regel</h3></th></tr>';
                        ravn_size(   $o, 'style_cta_info_size',      'Lettergrootte info' );
                        ravn_weight( $o, 'style_cta_info_weight',    'Gewicht info' );
                        ravn_color(  $o, 'style_cta_info_color',     'Kleur info (leeg = thema)' );
                        ravn_check(  $o, 'style_cta_info_underline', 'Onderstrepen info', 'Info-regel onderstrepen' );
                        echo '<tr><th colspan="2"><h3 style="margin:16px 0 4px">Voorraadkleuren CTA</h3></th></tr>';
                        ravn_color(  $o, 'style_cta_stock_in',      'Op voorraad' );
                        ravn_color(  $o, 'style_cta_stock_out',     'Niet op voorraad' );
                        ravn_color(  $o, 'style_cta_stock_unknown', 'Onbekend' );
                        break;

                    case 'show_all':
                        ravn_num(    $o, 'style_show_all_padding',    'Padding', 'px', 0, 30 );
                        ravn_num(    $o, 'style_show_all_margin_top', 'Marge boven' );
                        ravn_num(    $o, 'style_show_all_radius',     'Randradius', 'px', 0, 30 );
                        ravn_size(   $o, 'style_show_all_size',       'Lettergrootte' );
                        ravn_weight( $o, 'style_show_all_weight',     'Gewicht' );
                        ravn_color(  $o, 'style_show_all_bg',         'Achtergrondkleur (leeg = geen)' );
                        ravn_color(  $o, 'style_show_all_color',      'Tekstkleur (leeg = primair)' );
                        ravn_check(  $o, 'style_show_all_shadow',     'Slagschaduw', 'Schaduw op knop' );
                        ravn_check(  $o, 'style_show_all_underline',  'Onderstrepen', 'Knoptekst onderstrepen' );
                        break;

                    case 'updated':
                        ravn_num(    $o, 'style_updated_margin_top', 'Marge boven' );
                        ravn_size(   $o, 'style_updated_size',       'Lettergrootte' );
                        ravn_weight( $o, 'style_updated_weight',     'Gewicht' );
                        ravn_color(  $o, 'style_updated_color',      'Kleur' );
                        ravn_check(  $o, 'style_updated_underline',  'Onderstrepen', 'Datum onderstrepen' );
                        break;

                    case 'popup':
                        ravn_color( $o, 'style_popup_close_color', 'Sluitknopkleur' );
                        break;
                }
                ?>
                </table>

                <?php submit_button( 'Styling opslaan' ); ?>
            </div><!-- .ravn-tab-content -->
        </div><!-- .ravn-tabs-container -->
    </form>

    <div class="ravn-style-preview">
        <h2>Voorbeeld</h2>
        <p class="description">
            Zo ziet een productbox eruit met de <strong>opgeslagen</strong> instellingen.
            Sla je wijzigingen op om het voorbeeld bij te werken.
        </p>
        <div class="ravn-style-preview-frame">
            <?php
            // Dezelfde CSS als op de frontend, zodat het voorbeeld exact
            // overeenkomt met wat bezoekers te zien krijgen.
            echo '<style>' . Ravn_Styling::generate_css() . '</style>';
            echo '<div class="ravn-wrap">' . Ravn_Styling::render_preview() . '</div>';
            ?>
        </div>
    </div>
</div>
