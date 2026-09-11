<?php if ( ! defined( 'ABSPATH' ) ) exit;
global $wpdb;
$active_tab = isset( $_GET['stab'] ) ? sanitize_key( $_GET['stab'] ) : 'clicks';
$table_clicks = $wpdb->prefix . 'ravn_clicks';
$table_stock  = $wpdb->prefix . 'ravn_stock_log';
$table_prod   = $wpdb->prefix . 'ravn_products';
$table_offers = $wpdb->prefix . 'ravn_offers';

// Pagination
$per_page     = 25;
$current_page = max( 1, absint( $_GET['paged'] ?? 1 ) );
$offset       = ( $current_page - 1 ) * $per_page;

// Date filter
$date_from = sanitize_text_field( $_GET['from'] ?? date( 'Y-m-d', strtotime( '-30 days' ) ) );
$date_to   = sanitize_text_field( $_GET['to']   ?? date( 'Y-m-d' ) );
?>
<div class="wrap ravn-wrap">
    <h1><?php _e( 'Statistieken', 'ravn-affiliate' ); ?></h1>

    <div class="ravn-tabs-container">
    <ul class="ravn-tabs-nav">
        <li><a href="?page=ravn-affiliate-stats&stab=clicks" class="<?php echo $active_tab === 'clicks' ? 'active' : ''; ?>"><?php _e( 'Klikken', 'ravn-affiliate' ); ?></a></li>
        <li><a href="?page=ravn-affiliate-stats&stab=stock" class="<?php echo $active_tab === 'stock' ? 'active' : ''; ?>"><?php _e( 'Voorraad', 'ravn-affiliate' ); ?></a></li>
    </ul>

    <div class="ravn-tab-content">
    <?php if ( $active_tab === 'clicks' ) : ?>

    <form method="get" style="margin:16px 0; display:flex; gap:12px; align-items:center;">
        <input type="hidden" name="page" value="ravn-affiliate-stats">
        <input type="hidden" name="stab" value="clicks">
        <label><?php _e( 'Van:', 'ravn-affiliate' ); ?> <input type="date" name="from" value="<?php echo esc_attr( $date_from ); ?>"></label>
        <label><?php _e( 'Tot:', 'ravn-affiliate' ); ?> <input type="date" name="to" value="<?php echo esc_attr( $date_to ); ?>"></label>
        <?php submit_button( __( 'Filter', 'ravn-affiliate' ), 'secondary', '', false ); ?>
    </form>

    <?php
    // Summary cards
    $total_clicks = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$table_clicks} WHERE clicked_at BETWEEN %s AND %s",
        $date_from . ' 00:00:00', $date_to . ' 23:59:59'
    ) );
    $unique_products = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(DISTINCT product_id) FROM {$table_clicks} WHERE clicked_at BETWEEN %s AND %s",
        $date_from . ' 00:00:00', $date_to . ' 23:59:59'
    ) );
    ?>

    <div class="ravn-stat-cards" style="display:flex; gap:16px; margin-bottom:20px;">
        <div class="ravn-stat-card" style="background:#fff; border:1px solid #ddd; border-radius:6px; padding:16px 24px; flex:1; text-align:center;">
            <div style="font-size:2em; font-weight:bold; color:var(--ravn-admin-primary, #2271b1);"><?php echo number_format( $total_clicks ); ?></div>
            <div><?php _e( 'Totaal klikken', 'ravn-affiliate' ); ?></div>
        </div>
        <div class="ravn-stat-card" style="background:#fff; border:1px solid #ddd; border-radius:6px; padding:16px 24px; flex:1; text-align:center;">
            <div style="font-size:2em; font-weight:bold; color:var(--ravn-admin-primary, #2271b1);"><?php echo number_format( $unique_products ); ?></div>
            <div><?php _e( 'Unieke producten', 'ravn-affiliate' ); ?></div>
        </div>
    </div>

    <?php
    // Top products
    $top = $wpdb->get_results( $wpdb->prepare(
        "SELECT c.product_id, c.offer_id, p.title as product_name, o.seller_name,
                COUNT(*) as clicks
         FROM {$table_clicks} c
         LEFT JOIN {$table_prod}  p ON c.product_id = p.id
         LEFT JOIN {$table_offers} o ON c.offer_id = o.id
         WHERE c.clicked_at BETWEEN %s AND %s
         GROUP BY c.product_id, c.offer_id
         ORDER BY clicks DESC
         LIMIT %d OFFSET %d",
        $date_from . ' 00:00:00', $date_to . ' 23:59:59', $per_page, $offset
    ) );

    $total_rows = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(DISTINCT CONCAT(product_id,'-',offer_id)) FROM {$table_clicks} WHERE clicked_at BETWEEN %s AND %s",
        $date_from . ' 00:00:00', $date_to . ' 23:59:59'
    ) );
    $total_pages = ceil( $total_rows / $per_page );
    ?>

    <h2><?php _e( 'Klikken per aanbieder', 'ravn-affiliate' ); ?></h2>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php _e( 'Product', 'ravn-affiliate' ); ?></th>
                <th><?php _e( 'Aanbieder', 'ravn-affiliate' ); ?></th>
                <th style="width:100px; text-align:center;"><?php _e( 'Klikken', 'ravn-affiliate' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( $top ) : foreach ( $top as $row ) : ?>
            <tr>
                <td><?php echo esc_html( $row->product_name ?: __( '(verwijderd)', 'ravn-affiliate' ) ); ?></td>
                <td><?php echo esc_html( $row->seller_name ?: __( '(onbekend)', 'ravn-affiliate' ) ); ?></td>
                <td style="text-align:center;"><strong><?php echo number_format( $row->clicks ); ?></strong></td>
            </tr>
            <?php endforeach; else : ?>
            <tr><td colspan="3"><?php _e( 'Geen klikken gevonden in deze periode.', 'ravn-affiliate' ); ?></td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ( $total_pages > 1 ) :
        echo '<div class="tablenav bottom"><div class="tablenav-pages">';
        echo paginate_links( [
            'base'    => add_query_arg( 'paged', '%#%' ),
            'format'  => '',
            'current' => $current_page,
            'total'   => $total_pages,
        ] );
        echo '</div></div>';
    endif;
    ?>

    <hr style="margin:30px 0;">
    <h2 style="color:#b32d2e;"><?php _e( 'Statistieken wissen', 'ravn-affiliate' ); ?></h2>
    <p><?php _e( 'Dit verwijdert alle klikstatistieken permanent. Deze actie kan niet ongedaan worden gemaakt.', 'ravn-affiliate' ); ?></p>
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php esc_attr_e( 'Weet je het zeker? Alle statistieken worden permanent verwijderd.', 'ravn-affiliate' ); ?>');">
        <input type="hidden" name="action" value="ravn_clear_stats">
        <?php wp_nonce_field( 'ravn_clear_stats' ); ?>
        <button type="submit" class="button button-secondary" style="color:#b32d2e; border-color:#b32d2e;">
            <?php _e( 'Alle statistieken wissen', 'ravn-affiliate' ); ?>
        </button>
    </form>

    <?php elseif ( $active_tab === 'stock' ) : ?>

    <?php
    $stock_log = $wpdb->get_results( $wpdb->prepare(
        "SELECT sl.*, p.title as product_name, o.seller_name
         FROM {$table_stock} sl
         LEFT JOIN {$table_prod}  p ON sl.product_id = p.id
         LEFT JOIN {$table_offers} o ON sl.offer_id = o.id
         ORDER BY sl.logged_at DESC
         LIMIT %d OFFSET %d",
        $per_page, $offset
    ) );
    $total_stock_rows = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_stock}" );
    $total_pages = ceil( $total_stock_rows / $per_page );
    ?>

    <h2><?php _e( 'Voorraadwijzigingen', 'ravn-affiliate' ); ?></h2>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php _e( 'Datum', 'ravn-affiliate' ); ?></th>
                <th><?php _e( 'Product', 'ravn-affiliate' ); ?></th>
                <th><?php _e( 'Aanbieder', 'ravn-affiliate' ); ?></th>
                <th><?php _e( 'Oud', 'ravn-affiliate' ); ?></th>
                <th><?php _e( 'Nieuw', 'ravn-affiliate' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( $stock_log ) : foreach ( $stock_log as $row ) :
                $labels = [ 'in_stock' => __('Op voorraad','ravn-affiliate'), 'out_of_stock' => __('Niet op voorraad','ravn-affiliate'), 'unknown' => __('Onbekend','ravn-affiliate') ];
            ?>
            <tr>
                <td><?php echo esc_html( wp_date( get_option('date_format') . ' H:i', strtotime( $row->logged_at ) ) ); ?></td>
                <td><?php echo esc_html( $row->product_name ?: __( '(verwijderd)', 'ravn-affiliate' ) ); ?></td>
                <td><?php echo esc_html( $row->seller_name ?: __( '(onbekend)', 'ravn-affiliate' ) ); ?></td>
                <td><?php echo esc_html( $labels[ $row->old_status ] ?? $row->old_status ); ?></td>
                <td><strong><?php echo esc_html( $labels[ $row->new_status ] ?? $row->new_status ); ?></strong></td>
            </tr>
            <?php endforeach; else : ?>
            <tr><td colspan="5"><?php _e( 'Geen voorraadwijzigingen gevonden.', 'ravn-affiliate' ); ?></td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ( $total_pages > 1 ) :
        echo '<div class="tablenav bottom"><div class="tablenav-pages">';
        echo paginate_links( [
            'base'    => add_query_arg( 'paged', '%#%' ),
            'format'  => '',
            'current' => $current_page,
            'total'   => $total_pages,
        ] );
        echo '</div></div>';
    endif;
    ?>

    <hr style="margin:30px 0;">
    <h2 style="color:#b32d2e;"><?php _e( 'Voorraadlog wissen', 'ravn-affiliate' ); ?></h2>
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php esc_attr_e( 'Weet je het zeker? Alle statistieken (klikken én voorraadlog) worden permanent verwijderd.', 'ravn-affiliate' ); ?>');">
        <input type="hidden" name="action" value="ravn_clear_stats">
        <?php wp_nonce_field( 'ravn_clear_stats' ); ?>
        <button type="submit" class="button button-secondary" style="color:#b32d2e; border-color:#b32d2e;">
            <?php _e( 'Voorraadlog + klikken wissen', 'ravn-affiliate' ); ?>
        </button>
    </form>

    <?php endif; ?>
    </div><!-- .ravn-tab-content -->
    </div><!-- .ravn-tabs-container -->
</div>
