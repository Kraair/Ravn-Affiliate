<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap">
<h1 class="wp-heading-inline">Ravn Affiliate</h1>
<a href="<?php echo esc_url( admin_url( 'admin.php?page=ravn-affiliate-add' ) ); ?>" class="page-title-action">Product toevoegen</a>
<hr class="wp-header-end">

<?php
$search   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$page_num = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
$per_page = 20;
$products = Ravn_Database::get_products( array( 'search' => $search, 'page' => $page_num, 'per_page' => $per_page ) );
$total    = Ravn_Database::count_products( $search );
$pages    = ceil( $total / $per_page );
?>

<form method="get">
    <input type="hidden" name="page" value="ravn-affiliate">
    <p class="search-box">
        <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Zoek op titel of EAN...">
        <input type="submit" value="Zoeken" class="button">
    </p>
</form>

<table class="wp-list-table widefat fixed striped posts">
    <thead>
        <tr>
            <th style="width:60px">Afb.</th>
            <th>Titel</th>
            <th>EAN</th>
            <th>Beoordeling</th>
            <th>Label</th>
            <th>Aanbieders</th>
            <th>Shortcode</th>
            <th>Bijgewerkt</th>
            <th>Acties</th>
        </tr>
    </thead>
    <tbody>
    <?php if ( empty( $products ) ) : ?>
        <tr><td colspan="9">Geen producten gevonden. <a href="<?php echo esc_url( admin_url( 'admin.php?page=ravn-affiliate-add' ) ); ?>">Voeg je eerste product toe</a>.</td></tr>
    <?php else : ?>
        <?php foreach ( $products as $p ) :
            $offers        = Ravn_Database::get_offers( $p->id );
            $offers_count  = count( $offers );
            $edit_url      = admin_url( 'admin.php?page=ravn-affiliate-edit&product_id=' . $p->id );
            ?>
        <tr>
            <td><?php if ( $p->image_url ) : ?>
                <img src="<?php echo esc_url( $p->image_url ); ?>" width="48" height="48" style="object-fit:contain;" alt="">
            <?php endif; ?></td>
            <td>
                <strong><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $p->title ); ?></a></strong>
            </td>
            <td><?php echo esc_html( $p->ean ); ?></td>
            <td>
                <?php if ( $p->rating > 0 ) : ?>
                    <span style="color:#f5a623">&#9733;</span>
                    <?php echo esc_html( number_format( (float) $p->rating, 1 ) ); ?>
                    <?php if ( $p->review_count ) echo '<small>(' . intval( $p->review_count ) . ')</small>'; ?>
                <?php else : echo '—'; endif; ?>
            </td>
            <td>
                <?php if ( $p->label_text ) : ?>
                    <span style="background:<?php echo esc_attr( $p->label_color ?: '#e74c3c' ); ?>;color:#fff;padding:2px 6px;border-radius:3px;font-size:.8em;">
                        <?php echo esc_html( $p->label_icon . ' ' . $p->label_text ); ?>
                    </span>
                <?php else : echo '—'; endif; ?>
            </td>
            <td><?php echo $offers_count; ?></td>
            <td>
                <code class="ravn-copy-shortcode" style="cursor:pointer" title="Klik om te kopiëren">[ravn_product id="<?php echo $p->id; ?>"]</code>
            </td>
            <td>
                <?php echo $p->last_updated ? esc_html( mysql2date( 'd-m-Y H:i', $p->last_updated ) ) : '—'; ?>
            </td>
            <td>
                <a href="<?php echo esc_url( $edit_url ); ?>" class="button button-small">Bewerk</a>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;" onsubmit="return confirm('Weet je zeker dat je dit product wilt verwijderen?')">
                    <input type="hidden" name="action" value="ravn_delete_product">
                    <input type="hidden" name="product_id" value="<?php echo $p->id; ?>">
                    <?php wp_nonce_field( 'ravn_delete_product' ); ?>
                    <button type="submit" class="button button-small" style="color:#a00">Verwijder</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
</table>

<?php if ( $pages > 1 ) : ?>
<div class="tablenav bottom">
    <div class="tablenav-pages">
        <?php
        echo paginate_links( array(
            'base'    => add_query_arg( 'paged', '%#%' ),
            'format'  => '',
            'current' => $page_num,
            'total'   => $pages,
            'type'    => 'list',
        ) );
        ?>
    </div>
</div>
<?php endif; ?>
</div>
