<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Ravn_Product {

    /**
     * Rendert HTML sterren (0-5, halve sterren ondersteund).
     */
    public static function stars_html( $rating, $review_count = 0 ) {
        $opts         = Ravn_Options::get_all();
        if ( ! empty( $opts['hide_rating'] ) ) return '';

        $rating       = floatval( $rating );
        $full         = floor( $rating );
        $half         = ( $rating - $full ) >= 0.5 ? 1 : 0;
        $empty        = 5 - $full - $half;
        $active_color = esc_attr( $opts['style_star_active_color'] );
        $inactive_color = esc_attr( $opts['style_star_inactive_color'] );

        $html = '<div class="ravn-stars" aria-label="' . esc_attr( number_format( $rating, 1 ) ) . ' van de 5 sterren">';
        for ( $i = 0; $i < $full; $i++ ) {
            $html .= '<span class="ravn-star ravn-star-full" style="color:' . $active_color . '">&#9733;</span>';
        }
        if ( $half ) {
            $html .= '<span class="ravn-star ravn-star-half" style="color:' . $active_color . '">&#9733;</span>';
        }
        for ( $i = 0; $i < $empty; $i++ ) {
            $html .= '<span class="ravn-star ravn-star-empty" style="color:' . $inactive_color . '">&#9733;</span>';
        }
        if ( $review_count > 0 ) {
            $html .= '<span class="ravn-review-count">(' . intval( $review_count ) . ')</span>';
        }
        $html .= '</div>';
        return $html;
    }

    /**
     * Bouwt de affiliate URL met sub_id en nofollow/sponsored.
     */
    public static function build_url( $base_url, $offer_sub_id = '', $shortcode_sub_id = '', $network = '' ) {
        $opts      = Ravn_Options::get_all();
        $sub_id    = '';

        if ( ! empty( $shortcode_sub_id ) ) {
            $sub_id = $shortcode_sub_id;
        } elseif ( ! empty( $offer_sub_id ) ) {
            $sub_id = $offer_sub_id;
        } elseif ( ! empty( $opts['auto_sub_id'] ) ) {
            // Automatisch: paginatitel
            $post = get_post();
            if ( $post ) {
                $sub_id = sanitize_title( $post->post_title );
            }
        }

        if ( empty( $sub_id ) && ! empty( $opts['sub_id_global'] ) ) {
            $sub_id = $opts['sub_id_global'];
        }

        $url = $base_url;

        // Bol.com-links moeten via de partner-trackinglink lopen om commissie
        // op te leveren; reguliere product-/API-links doen dat niet vanzelf.
        $is_bol = ( 'bol' === $network ) || ( '' === $network && Ravn_API::is_bol_url( $url ) );
        if ( $is_bol ) {
            $post = ! $sub_id && ! empty( $opts['auto_sub_id'] ) ? get_post() : null;
            $name = $post ? $post->post_title : '';
            $url  = Ravn_API::bol_wrap_affiliate_url( $url, $sub_id, $name );
            // De sub_id zit al in de tracking-URL (subid=), dus niet nogmaals toevoegen.
            return $url;
        }

        if ( $sub_id ) {
            $url = add_query_arg( 'sub_id', urlencode( $sub_id ), $url );
        }

        return $url;
    }

    /**
     * Genereert rel attribuut string.
     */
    public static function rel_attr() {
        $opts  = Ravn_Options::get_all();
        $parts = array();
        if ( ! empty( $opts['nofollow'] ) )   $parts[] = 'nofollow';
        if ( ! empty( $opts['sponsored'] ) )  $parts[] = 'sponsored';
        if ( ! empty( $opts['new_window'] ) ) $parts[] = 'noopener';
        return ! empty( $parts ) ? ' rel="' . esc_attr( implode( ' ', $parts ) ) . '"' : '';
    }

    /**
     * Target attribuut.
     */
    public static function target_attr() {
        $opts = Ravn_Options::get_all();
        return ! empty( $opts['new_window'] ) ? ' target="_blank"' : '';
    }

    /**
     * Formatteert prijs.
     */
    public static function format_price( $price, $currency = 'EUR' ) {
        if ( $price === null || $price === '' ) return '';
        $symbols = array( 'EUR' => '&euro;', 'USD' => '$', 'GBP' => '&pound;', 'GBP' => '&pound;' );
        $symbol  = isset( $symbols[ $currency ] ) ? $symbols[ $currency ] : esc_html( $currency ) . ' ';
        return $symbol . number_format( (float) $price, 2, ',', '.' );
    }

    /**
     * Voorraad icoon HTML.
     */
    public static function stock_icon_html( $status, $context = 'seller' ) {
        $opts     = Ravn_Options::get_all();
        $key_in   = 'seller' === $context ? 'style_seller_stock_in'   : 'style_cta_stock_in';
        $key_out  = 'seller' === $context ? 'style_seller_stock_out'  : 'style_cta_stock_out';
        $key_unk  = 'seller' === $context ? 'style_seller_stock_unknown' : 'style_cta_stock_unknown';

        switch ( $status ) {
            case 'in_stock':
                $color = esc_attr( $opts[ $key_in ] );
                $icon  = '<svg viewBox="0 0 24 24" fill="' . $color . '" width="14" height="14"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>';
                $label = 'Op voorraad';
                break;
            case 'out_of_stock':
                $color = esc_attr( $opts[ $key_out ] );
                $icon  = '<svg viewBox="0 0 24 24" fill="' . $color . '" width="14" height="14"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>';
                $label = 'Niet op voorraad';
                break;
            default:
                $color = esc_attr( $opts[ $key_unk ] );
                $icon  = '<svg viewBox="0 0 24 24" fill="' . $color . '" width="14" height="14"><path d="M7 18h2v-2H7v2zm1-16C4.69 2 2 4.69 2 8h2c0-2.21 1.79-4 4-4s4 1.79 4 4c0 2-3 2.75-3 5h2c0-1.25 3-2 3-5 0-3.31-2.69-6-6-6zm-1 12h2v-2H7v2z"/></svg>';
                $label = 'Voorraad onbekend';
        }
        return '<span class="ravn-stock-icon" title="' . esc_attr( $label ) . '">' . $icon . '</span>';
    }

    /**
     * Favicon URL voor een verkoper.
     */
    public static function get_logo_url( $seller_domain, $custom_logo = '' ) {
        if ( ! empty( $custom_logo ) ) return esc_url( $custom_logo );
        $custom_logos = Ravn_Options::get( 'custom_logos', array() );
        if ( is_array( $custom_logos ) && isset( $custom_logos[ $seller_domain ] ) ) {
            return esc_url( $custom_logos[ $seller_domain ] );
        }
        // Google favicon service
        return 'https://www.google.com/s2/favicons?domain=' . urlencode( $seller_domain ) . '&sz=32';
    }

    /**
     * Rendert één productbox.
     */
    public static function render_product_box( $product, $atts = array() ) {
        $opts = Ravn_Options::get_all();
        $id   = intval( $product->id );

        // Shortcode overrides
        $hide_price   = ! empty( $atts['hide_price'] )   || ! empty( $opts['hide_price'] );
        $hide_image   = ! empty( $atts['hide_image'] )   || ! empty( $opts['hide_image'] );
        $hide_title   = ! empty( $atts['hide_title'] )   || ! empty( $opts['hide_title'] );
        $hide_desc    = ! empty( $atts['hide_desc'] )    || ! empty( $opts['hide_desc'] );
        $hide_sellers = ! empty( $atts['hide_sellers'] ) || ! empty( $opts['hide_sellers'] );
        $hide_rating  = ! empty( $atts['hide_rating'] )  || ! empty( $opts['hide_rating'] );
        $sub_id       = isset( $atts['sub_id'] ) ? $atts['sub_id'] : '';
        $content      = isset( $atts['content'] ) ? $atts['content'] : '';

        // Verkopers ophalen. Via $atts['preview_offers'] kunnen aanbieders
        // rechtstreeks worden meegegeven (gebruikt door de stijl-preview in
        // de admin), zodat er geen echt product in de database nodig is.
        $sort = $opts['sort_sellers'];
        if ( isset( $atts['preview_offers'] ) && is_array( $atts['preview_offers'] ) ) {
            $offers = $atts['preview_offers'];
        } else {
            $offers = Ravn_Database::get_offers( $id, $sort );
        }

        // Uitgesloten verkopers filteren
        $excluded = array_filter( array_map( 'trim', explode( ',', $opts['excluded_sellers'] ) ) );
        if ( ! empty( $excluded ) ) {
            $offers = array_filter( $offers, function( $o ) use ( $excluded ) {
                return ! in_array( strtolower( $o->seller_domain ), $excluded, true );
            } );
            $offers = array_values( $offers );
        }

        // Cloaking slug voor popup klik
        $cloak_slug  = ! empty( $product->cloak_slug ) ? $product->cloak_slug : '';
        $click_action = $opts['click_action'];
        $first_offer  = ! empty( $offers ) ? $offers[0] : null;

        if ( $first_offer ) {
            $click_url = self::build_url( $first_offer->affiliate_url, $first_offer->sub_id, $sub_id, $first_offer->network );
            if ( ! empty( $opts['module_cloaking'] ) && $cloak_slug ) {
                $click_url = home_url( '/go/' . $cloak_slug . '/' );
            }
        } else {
            $click_url = '#';
        }

        $rel    = self::rel_attr();
        $target = self::target_attr();

        ob_start();
        ?>
        <div class="ravn-product-box" data-product-id="<?php echo $id; ?>" data-click-action="<?php echo esc_attr( $click_action ); ?>">

            <?php // === AFBEELDING ===
            if ( ! $hide_image && ! empty( $product->image_url ) ) :
                $img_class = ! empty( $opts['style_img_blend'] ) ? ' ravn-img-blend' : '';
                if ( 'popup' === $click_action && count( $offers ) > 1 ) : ?>
                    <div class="ravn-product-image<?php echo $img_class; ?> ravn-popup-trigger" data-product-id="<?php echo $id; ?>" role="button" tabindex="0">
                        <img src="<?php echo esc_url( $product->image_url ); ?>" alt="<?php echo esc_attr( $product->title ); ?>" loading="lazy">
                    </div>
                <?php elseif ( $first_offer ) : ?>
                    <a href="<?php echo esc_url( $click_url ); ?>"<?php echo $target . $rel; ?> class="ravn-product-image-link">
                        <div class="ravn-product-image<?php echo $img_class; ?>">
                            <img src="<?php echo esc_url( $product->image_url ); ?>" alt="<?php echo esc_attr( $product->title ); ?>" loading="lazy">
                        </div>
                    </a>
                <?php else : ?>
                    <div class="ravn-product-image<?php echo $img_class; ?>">
                        <img src="<?php echo esc_url( $product->image_url ); ?>" alt="<?php echo esc_attr( $product->title ); ?>" loading="lazy">
                    </div>
                <?php endif;
            endif; ?>

            <div class="ravn-product-content">

                <?php // === SHORTCODE CONTENT ===
                if ( $content ) : ?>
                    <div class="ravn-content"><?php echo wp_kses_post( $content ); ?></div>
                <?php endif; ?>

                <?php // === TITEL ===
                if ( ! $hide_title ) :
                    if ( 'popup' === $click_action && count( $offers ) > 1 ) : ?>
                        <div class="ravn-product-title ravn-popup-trigger" data-product-id="<?php echo $id; ?>" role="button" tabindex="0">
                            <?php echo esc_html( $product->title ); ?>
                        </div>
                    <?php elseif ( $first_offer ) : ?>
                        <a href="<?php echo esc_url( $click_url ); ?>"<?php echo $target . $rel; ?> class="ravn-product-title-link">
                            <div class="ravn-product-title"><?php echo esc_html( $product->title ); ?></div>
                        </a>
                    <?php else : ?>
                        <div class="ravn-product-title"><?php echo esc_html( $product->title ); ?></div>
                    <?php endif;
                endif; ?>

                <?php // === STERREN ===
                if ( ! $hide_rating && $product->rating > 0 ) :
                    echo self::stars_html( $product->rating, $product->review_count );
                endif; ?>

                <?php // === BESCHRIJVING ===
                if ( ! $hide_desc && ! empty( $product->description ) ) : ?>
                    <div class="ravn-product-desc"><?php echo wp_kses_post( $product->description ); ?></div>
                <?php endif; ?>

                <?php // === PRODUCTLABEL ===
                if ( ! empty( $product->label_text ) ) :
                    $label_color = ! empty( $product->label_color ) ? $product->label_color : '#e74c3c';
                    $label_icon  = ! empty( $product->label_icon )  ? $product->label_icon  : '';
                    $icon_align  = $opts['style_label_icon_align'];
                    ?>
                    <div class="ravn-product-label" style="background-color:<?php echo esc_attr( $label_color ); ?>">
                        <?php if ( $label_icon && 'left' === $icon_align ) : ?>
                            <span class="ravn-label-icon"><?php echo esc_html( $label_icon ); ?></span>
                        <?php endif; ?>
                        <span class="ravn-label-text"><?php echo esc_html( $product->label_text ); ?></span>
                        <?php if ( $label_icon && 'right' === $icon_align ) : ?>
                            <span class="ravn-label-icon"><?php echo esc_html( $label_icon ); ?></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php // === VERKOPERS ===
                if ( ! $hide_sellers && ! empty( $offers ) ) :
                    $visible_count = intval( $opts['sellers_visible'] );
                    $total_offers  = count( $offers );
                    $has_more      = $total_offers > $visible_count;
                    $enable_cta    = ! empty( $opts['enable_cta'] ) && $total_offers <= 1;
                    ?>
                    <div class="ravn-sellers-section">
                        <?php if ( ! empty( $opts['sellers_description'] ) ) : ?>
                            <div class="ravn-sellers-desc"><?php echo esc_html( $opts['sellers_description'] ); ?></div>
                        <?php endif; ?>

                        <?php if ( $enable_cta && $first_offer ) :
                            // CTA knop modus
                            $cta_url = self::build_url( $first_offer->affiliate_url, $first_offer->sub_id, $sub_id, $first_offer->network );
                            ?>
                            <?php if ( ! empty( $opts['enable_cta_info'] ) ) : ?>
                                <div class="ravn-cta-info">
                                    <?php echo self::get_logo_html( $first_offer ); ?>
                                    <span class="ravn-cta-info-seller"><?php echo esc_html( $first_offer->seller_domain ?: $first_offer->seller_name ); ?></span>
                                    <?php if ( ! $hide_price && $first_offer->price !== null ) : ?>
                                        <span class="ravn-cta-info-price"><?php echo self::format_price( $first_offer->price, $first_offer->currency ); ?></span>
                                    <?php endif; ?>
                                    <?php echo self::stock_icon_html( $first_offer->stock_status, 'cta' ); ?>
                                </div>
                            <?php endif; ?>
                            <a href="<?php echo esc_url( $cta_url ); ?>"<?php echo $target . $rel; ?>
                               class="ravn-cta-btn"
                               data-offer-id="<?php echo intval( $first_offer->id ); ?>"
                               data-product-id="<?php echo $id; ?>">
                                <?php
                                $icon_name  = $opts['style_cta_icon_name'];
                                $icon_align = $opts['style_cta_icon_align'];
                                $cta_text   = esc_html( $opts['cta_text'] );
                                if ( ! empty( $opts['style_cta_icon'] ) && 'left' === $icon_align ) {
                                    echo '<span class="ravn-cta-icon ravn-icon-' . esc_attr( $icon_name ) . '"></span>';
                                }
                                echo $cta_text;
                                if ( ! empty( $opts['style_cta_icon'] ) && 'right' === $icon_align ) {
                                    echo '<span class="ravn-cta-icon ravn-icon-' . esc_attr( $icon_name ) . '"></span>';
                                }
                                ?>
                            </a>
                        <?php else :
                            // Normale verkopers lijst
                            $show_sellers = $visible_count > 0 ? array_slice( $offers, 0, $visible_count ) : $offers;
                            $hidden_sellers = $has_more && $visible_count > 0 ? array_slice( $offers, $visible_count ) : array();
                            ?>
                            <div class="ravn-sellers-list">
                                <?php foreach ( $show_sellers as $offer ) :
                                    $offer_url = self::build_url( $offer->affiliate_url, $offer->sub_id, $sub_id, $offer->network );
                                    ?>
                                    <div class="ravn-seller-row">
                                        <a href="<?php echo esc_url( $offer_url ); ?>"<?php echo $target . $rel; ?>
                                           class="ravn-seller-link"
                                           data-offer-id="<?php echo intval( $offer->id ); ?>"
                                           data-product-id="<?php echo $id; ?>">
                                            <?php echo self::get_logo_html( $offer ); ?>
                                            <?php echo self::stock_icon_html( $offer->stock_status, 'seller' ); ?>
                                            <span class="ravn-seller-name"><?php echo esc_html( $offer->seller_domain ?: $offer->seller_name ); ?></span>
                                            <?php if ( ! $hide_price && $offer->price !== null ) : ?>
                                                <span class="ravn-seller-price"><?php echo self::format_price( $offer->price, $offer->currency ); ?></span>
                                            <?php endif; ?>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <?php if ( $has_more && $visible_count > 0 ) :
                                $hidden_data  = array();
                                foreach ( $hidden_sellers as $o ) {
                                    $ou = self::build_url( $o->affiliate_url, $o->sub_id, $sub_id, $o->network );
                                    $hidden_data[] = array(
                                        'id'          => $o->id,
                                        'product_id'  => $id,
                                        'seller_name' => $o->seller_name,
                                        'seller_domain'=> $o->seller_domain,
                                        'price'       => $o->price,
                                        'currency'    => $o->currency,
                                        'stock_status'=> $o->stock_status,
                                        'url'         => $ou,
                                        'logo'        => self::get_logo_url( $o->seller_domain, $o->logo_url ),
                                        'hide_price'  => $hide_price,
                                    );
                                }
                                $show_all_text = esc_html( $opts['show_all_text'] );
                                if ( ! empty( $opts['show_seller_count'] ) ) {
                                    $show_all_text .= ' (' . $total_offers . ')';
                                }
                                ?>
                                <button type="button"
                                        class="ravn-show-all-btn ravn-popup-trigger"
                                        data-product-id="<?php echo $id; ?>"
                                        data-sellers="<?php echo esc_attr( wp_json_encode( $hidden_data ) ); ?>"
                                        data-all-sellers="<?php echo esc_attr( wp_json_encode( array_map( function( $o ) use ( $sub_id, $target, $rel, $hide_price, $id ) {
                                            $ou = Ravn_Product::build_url( $o->affiliate_url, $o->sub_id, $sub_id, $o->network );
                                            return array(
                                                'id'          => $o->id,
                                                'product_id'  => $id,
                                                'seller_name' => $o->seller_name,
                                                'seller_domain'=> $o->seller_domain,
                                                'price'       => $o->price,
                                                'currency'    => $o->currency,
                                                'stock_status'=> $o->stock_status,
                                                'url'         => $ou,
                                                'logo'        => Ravn_Product::get_logo_url( $o->seller_domain, $o->logo_url ),
                                                'hide_price'  => $hide_price,
                                            );
                                        }, $offers ) ) ); ?>">
                                    <?php echo $show_all_text; ?>
                                </button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php // === LAST UPDATED ===
                if ( ! empty( $opts['show_last_updated'] ) && ! empty( $product->last_updated ) ) :
                    $date_str = mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $product->last_updated );
                    ?>
                    <div class="ravn-last-updated">
                        <?php echo esc_html( $opts['last_updated_text'] ) . ' '; ?>
                        <time datetime="<?php echo esc_attr( $product->last_updated ); ?>"><?php echo esc_html( $date_str ); ?></time>
                    </div>
                <?php endif; ?>

            </div><!-- .ravn-product-content -->
        </div><!-- .ravn-product-box -->
        <?php

        // Popup HTML (verborgen, alleen renderen als popup klik actie)
        if ( 'popup' === $click_action || count( $offers ) > $opts['sellers_visible'] ) {
            self::render_popup( $product, $offers, $atts );
        }

        return ob_get_clean();
    }

    /**
     * Rendert popup overlay met alle verkopers.
     */
    public static function render_popup( $product, $offers, $atts = array() ) {
        $opts     = Ravn_Options::get_all();
        $id       = intval( $product->id );
        $sub_id   = isset( $atts['sub_id'] ) ? $atts['sub_id'] : '';
        $hide_price = ! empty( $atts['hide_price'] ) || ! empty( $opts['hide_price'] );
        $target   = self::target_attr();
        $rel      = self::rel_attr();
        ?>
        <div class="ravn-popup-overlay" id="ravn-popup-<?php echo $id; ?>" hidden aria-hidden="true" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr( $product->title ); ?>">
            <div class="ravn-popup-inner">
                <button type="button" class="ravn-popup-close" aria-label="Sluiten">&times;</button>
                <?php if ( ! empty( $product->image_url ) ) : ?>
                    <img class="ravn-popup-image" src="<?php echo esc_url( $product->image_url ); ?>" alt="<?php echo esc_attr( $product->title ); ?>">
                <?php endif; ?>
                <h3 class="ravn-popup-title"><?php echo esc_html( $product->title ); ?></h3>
                <div class="ravn-popup-sellers">
                    <?php foreach ( $offers as $offer ) :
                        $offer_url = self::build_url( $offer->affiliate_url, $offer->sub_id, $sub_id, $offer->network );
                        ?>
                        <div class="ravn-seller-row">
                            <a href="<?php echo esc_url( $offer_url ); ?>"<?php echo $target . $rel; ?>
                               class="ravn-seller-link"
                               data-offer-id="<?php echo intval( $offer->id ); ?>"
                               data-product-id="<?php echo $id; ?>">
                                <?php echo self::get_logo_html( $offer ); ?>
                                <?php echo self::stock_icon_html( $offer->stock_status, 'seller' ); ?>
                                <span class="ravn-seller-name"><?php echo esc_html( $offer->seller_domain ?: $offer->seller_name ); ?></span>
                                <?php if ( ! $hide_price && $offer->price !== null ) : ?>
                                    <span class="ravn-seller-price"><?php echo self::format_price( $offer->price, $offer->currency ); ?></span>
                                <?php endif; ?>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Logo HTML voor een verkoper.
     */
    private static function get_logo_html( $offer ) {
        $logo = self::get_logo_url( $offer->seller_domain, $offer->logo_url );
        return '<span class="ravn-seller-logo"><img src="' . esc_url( $logo ) . '" alt="' . esc_attr( $offer->seller_name ) . '" width="16" height="16" loading="lazy"></span>';
    }
}
