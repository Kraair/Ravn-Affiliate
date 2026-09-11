<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Ravn_Stats {

    public function __construct() {
        add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widget' ) );
    }

    public function add_dashboard_widget() {
        wp_add_dashboard_widget(
            'ravn_stats_widget',
            'Ravn Affiliate - Top klikken',
            array( $this, 'render_dashboard_widget' )
        );
    }

    public function render_dashboard_widget() {
        $top = Ravn_Database::get_top_clicks( 8 );
        if ( empty( $top ) ) {
            echo '<p>Geen klikdata beschikbaar. Activeer link tracking in de instellingen.</p>';
            return;
        }
        echo '<table class="widefat striped" style="margin-top:8px;">';
        echo '<thead><tr><th>Product</th><th>Verkoper</th><th>Klikken</th></tr></thead><tbody>';
        foreach ( $top as $row ) {
            echo '<tr>';
            echo '<td>' . esc_html( $row->product_title ) . '</td>';
            echo '<td>' . esc_html( $row->seller_name ) . '</td>';
            echo '<td><strong>' . intval( $row->click_count ) . '</strong></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        $url = admin_url( 'admin.php?page=ravn-affiliate-stats' );
        echo '<p style="margin-top:8px;"><a href="' . esc_url( $url ) . '">Alle statistieken bekijken →</a></p>';
    }
}
