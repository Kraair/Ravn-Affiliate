<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Ravn_Schema {

    public function __construct() {
        add_action( 'wp_head', array( $this, 'output_schema' ) );
    }

    public function output_schema() {
        if ( ! is_singular() ) return;

        $post    = get_post();
        if ( ! $post ) return;
        $content = $post->post_content;

        $product_ids = $this->collect_product_ids( $content );

        foreach ( $product_ids as $product_id ) {
            $product = Ravn_Database::get_product( intval( $product_id ) );
            if ( ! $product ) continue;
            echo $this->product_schema( $product );
        }

        // FAQ schema
        $faq_items = Ravn_Database::get_faq_by_post( $post->ID );
        if ( ! empty( $faq_items ) ) {
            echo $this->faq_schema( $faq_items );
        }
    }

    /**
     * Verzamelt alle product-ID's op de pagina, uit alle plekken waar ze
     * kunnen staan: de losse shortcode, de lijst-/carrousel-shortcodes
     * (die "ids" meervoud gebruiken) en de Gutenberg-blokken (die als
     * HTML-commentaar met JSON-attributen worden opgeslagen).
     */
    private function collect_product_ids( $content ) {
        $ids = array();

        // [ravn_product id="12"]
        if ( preg_match_all( '/\[ravn_product[^\]]*\bid=["\']?(\d+)["\']?/i', $content, $m ) ) {
            $ids = array_merge( $ids, $m[1] );
        }

        // [ravn_list ids="12,34"] en [ravn_carousel ids="12,34"]
        if ( preg_match_all( '/\[ravn_(?:list|carousel)[^\]]*\bids=["\']([\d,\s]+)["\']/i', $content, $m ) ) {
            foreach ( $m[1] as $list ) {
                $ids = array_merge( $ids, preg_split( '/[\s,]+/', trim( $list ) ) );
            }
        }

        // Gutenberg-blok: <!-- wp:ravn-affiliate/product-list {"productIds":[12,34]} /-->
        if ( preg_match_all( '/<!--\s*wp:ravn-affiliate\/product-list\s+({.*?})\s*\/?-->/s', $content, $m ) ) {
            foreach ( $m[1] as $json ) {
                $attrs = json_decode( $json, true );
                if ( empty( $attrs['productIds'] ) || ! is_array( $attrs['productIds'] ) ) continue;

                // Bij "single" toont het blok alleen het eerste product; schema
                // mag niets beschrijven dat niet zichtbaar op de pagina staat.
                $mode = isset( $attrs['displayMode'] ) ? $attrs['displayMode'] : 'single';
                if ( 'single' === $mode ) {
                    $ids[] = reset( $attrs['productIds'] );
                } else {
                    $ids = array_merge( $ids, $attrs['productIds'] );
                }
            }
        }

        // Verouderd blok: <!-- wp:ravn-affiliate/product-picker {"productId":12} /-->
        if ( preg_match_all( '/<!--\s*wp:ravn-affiliate\/product-picker\s+({.*?})\s*\/?-->/s', $content, $m ) ) {
            foreach ( $m[1] as $json ) {
                $attrs = json_decode( $json, true );
                if ( ! empty( $attrs['productId'] ) ) {
                    $ids[] = $attrs['productId'];
                }
            }
        }

        $ids = array_filter( array_map( 'intval', $ids ) );
        return array_values( array_unique( $ids ) );
    }

    private function product_schema( $product ) {
        $offers_data = array();
        $offers      = Ravn_Database::get_offers( $product->id );

        foreach ( $offers as $offer ) {
            $offer_entry = array(
                '@type'         => 'Offer',
                'priceCurrency' => $offer->currency ?: 'EUR',
                'url'           => Ravn_Product::build_url( $offer->affiliate_url, $offer->sub_id, '', $offer->network ),
                'seller'        => array(
                    '@type' => 'Organization',
                    'name'  => $offer->seller_name,
                ),
            );
            if ( $offer->price !== null ) {
                $offer_entry['price'] = number_format( (float) $offer->price, 2, '.', '' );
            }
            switch ( $offer->stock_status ) {
                case 'in_stock':
                    $offer_entry['availability'] = 'https://schema.org/InStock'; break;
                case 'out_of_stock':
                    $offer_entry['availability'] = 'https://schema.org/OutOfStock'; break;
                default:
                    // Onbekende voorraad is niet hetzelfde als "vooruit te
                    // bestellen"; InStoreOnly/PreOrder zouden hier onjuiste
                    // informatie aan Google doorgeven.
                    $offer_entry['availability'] = 'https://schema.org/LimitedAvailability';
            }
            $offers_data[] = $offer_entry;
        }

        $schema = array(
            '@context' => 'https://schema.org',
            '@type'    => 'Product',
            'name'     => $product->title,
        );

        if ( ! empty( $product->image_url ) ) {
            $schema['image'] = $product->image_url;
        }
        if ( ! empty( $product->description ) ) {
            $schema['description'] = wp_strip_all_tags( $product->description );
        }
        if ( ! empty( $product->ean ) ) {
            $schema['gtin13'] = $product->ean;
        }
        if ( $product->rating > 0 ) {
            $schema['aggregateRating'] = array(
                '@type'       => 'AggregateRating',
                'ratingValue' => number_format( (float) $product->rating, 1, '.', '' ),
                'ratingCount' => intval( $product->review_count ) ?: 1,
                'bestRating'  => '5',
                'worstRating' => '1',
            );
        }
        if ( ! empty( $offers_data ) ) {
            if ( count( $offers_data ) === 1 ) {
                $schema['offers'] = $offers_data[0];
            } else {
                // Meerdere aanbieders: AggregateOffer met prijsrange is het
                // juiste patroon voor vergelijkingspagina's; Google toont dan
                // "vanaf"-prijzen in plaats van één willekeurige aanbieding.
                $prices = array();
                foreach ( $offers_data as $o ) {
                    if ( isset( $o['price'] ) ) $prices[] = (float) $o['price'];
                }

                $aggregate = array(
                    '@type'         => 'AggregateOffer',
                    'offerCount'    => count( $offers_data ),
                    'priceCurrency' => $offers_data[0]['priceCurrency'],
                    'offers'        => $offers_data,
                );
                if ( ! empty( $prices ) ) {
                    $aggregate['lowPrice']  = number_format( min( $prices ), 2, '.', '' );
                    $aggregate['highPrice'] = number_format( max( $prices ), 2, '.', '' );
                }
                $schema['offers'] = $aggregate;
            }
        }

        return '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
    }

    private function faq_schema( $items ) {
        $entities = array();
        foreach ( $items as $item ) {
            $entities[] = array(
                '@type'          => 'Question',
                'name'           => $item->question,
                'acceptedAnswer' => array(
                    '@type' => 'Answer',
                    'text'  => wp_strip_all_tags( $item->answer ),
                ),
            );
        }
        $schema = array(
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => $entities,
        );
        return '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
    }
}
