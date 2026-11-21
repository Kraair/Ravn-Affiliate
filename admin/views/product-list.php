<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap">
<h1 class="wp-heading-inline">Ravn Affiliate</h1>
<a href="<?php echo esc_url( admin_url( 'admin.php?page=ravn-affiliate-add' ) ); ?>" class="page-title-action">Product toevoegen</a>
<hr class="wp-header-end">

<?php
$search   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$page_num = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
$per_page = isset( $_GET['per_page'] ) ? intval( $_GET['per_page'] ) : 20;
if ( ! in_array( $per_page, array( 20, 50, 100, 200 ), true ) ) $per_page = 20;
$products = Ravn_Database::get_products( array( 'search' => $search, 'page' => $page_num, 'per_page' => $per_page ) );
$total    = Ravn_Database::count_products( $search );
$pages    = ceil( $total / $per_page );
?>

<form method="get">
    <input type="hidden" name="page" value="ravn-affiliate">
    <p class="search-box">
        <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Zoek op titel of EAN...">
        <input type="hidden" name="per_page" value="<?php echo (int) $per_page; ?>">
        <input type="submit" value="Zoeken" class="button">
    </p>
</form>

<form method="post" id="ravn-bulk-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
    <input type="hidden" name="action" value="ravn_affiliate_bulk_products">
    <?php wp_nonce_field( 'ravn_affiliate_bulk_products' ); ?>
</form>

<div class="tablenav top">
    <div class="alignleft actions bulkactions">
        <select name="bulk_action" form="ravn-bulk-form" id="ravn-bulk-action">
            <option value="">Bulkacties</option>
            <option value="delete">Verwijderen</option>
        </select>
        <button type="submit" form="ravn-bulk-form" class="button" id="ravn-bulk-apply">Toepassen</button>
        <span id="ravn-bulk-count" style="margin-left:8px;color:#666;"></span>
    </div>
    <div class="alignright">
        <form method="get" style="display:inline;">
            <input type="hidden" name="page" value="ravn-affiliate">
            <input type="hidden" name="s" value="<?php echo esc_attr( $search ); ?>">
            <label>Per pagina
                <select name="per_page" onchange="this.form.submit()">
                    <?php foreach ( array( 20, 50, 100, 200 ) as $n ) : ?>
                        <option value="<?php echo $n; ?>" <?php selected( $per_page, $n ); ?>><?php echo $n; ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <span class="displaying-num"><?php echo (int) $total; ?> producten</span>
        </form>
    </div>
</div>

<table class="wp-list-table widefat fixed striped posts">
    <thead>
        <tr>
            <td class="manage-column column-cb check-column" style="width:30px"><input type="checkbox" id="ravn-select-all" aria-label="Alles selecteren"></td>
            <th style="width:60px">Afb.</th>
            <th>Titel</th>
            <th>EAN</th>
            <th>Beoordeling</th>
            <th>Label</th>
            <th>Aanbieders</th>
            <th>Voorraad</th>
            <th>Shortcode</th>
            <th>Bijgewerkt</th>
            <th>Acties</th>
        </tr>
    </thead>
    <tbody>
    <?php if ( empty( $products ) ) : ?>
        <tr><td colspan="11">Geen producten gevonden. <a href="<?php echo esc_url( admin_url( 'admin.php?page=ravn-affiliate-add' ) ); ?>">Voeg je eerste product toe</a>.</td></tr>
    <?php else : ?>
        <?php foreach ( $products as $p ) :
            $offers        = Ravn_Database::get_offers( $p->id );
            $offers_count  = count( $offers );
            $stock_counts  = array( 'in_stock' => 0, 'out_of_stock' => 0, 'unknown' => 0 );
            foreach ( $offers as $o ) {
                $st = isset( $stock_counts[ $o->stock_status ] ) ? $o->stock_status : 'unknown';
                $stock_counts[ $st ]++;
            }
            $edit_url      = admin_url( 'admin.php?page=ravn-affiliate-edit&product_id=' . $p->id );
            ?>
        <tr>
            <th scope="row" class="check-column"><input type="checkbox" class="ravn-row-cb" name="product_ids[]" value="<?php echo (int) $p->id; ?>" form="ravn-bulk-form"></th>
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
                <?php if ( ! $offers_count ) : echo '—'; else : ?>
                    <?php if ( $stock_counts['in_stock'] ) : ?>
                        <span style="display:inline-block;background:#d7f0dd;color:#14532d;padding:1px 7px;border-radius:3px;font-size:.85em;white-space:nowrap;" title="Aanbieders op voorraad">&#10003; <?php echo (int) $stock_counts['in_stock']; ?> op voorraad</span>
                    <?php endif; ?>
                    <?php if ( $stock_counts['out_of_stock'] ) : ?>
                        <span style="display:inline-block;background:#fbdcdc;color:#7f1d1d;padding:1px 7px;border-radius:3px;font-size:.85em;white-space:nowrap;" title="Aanbieders niet op voorraad">&#10007; <?php echo (int) $stock_counts['out_of_stock']; ?> uitverkocht</span>
                    <?php endif; ?>
                    <?php if ( $stock_counts['unknown'] ) : ?>
                        <span style="display:inline-block;background:#ececec;color:#444;padding:1px 7px;border-radius:3px;font-size:.85em;white-space:nowrap;" title="Voorraadstatus onbekend">? <?php echo (int) $stock_counts['unknown']; ?> onbekend</span>
                    <?php endif; ?>
                <?php endif; ?>
            </td>
            <td>
                <code class="ravn-copy-shortcode" style="cursor:pointer" title="Klik om te kopiëren">[ravn_product id="<?php echo $p->id; ?>"]</code>
            </td>
            <td>
                <?php echo $p->last_updated ? esc_html( mysql2date( 'd-m-Y H:i', $p->last_updated ) ) : '—'; ?>
            </td>
            <td>
                <a href="<?php echo esc_url( $edit_url ); ?>" class="button button-small">Bewerk</a>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;" onsubmit="return confirm('Weet je zeker dat je dit product wilt verwijderen?')">
                    <input type="hidden" name="action" value="ravn_affiliate_delete_product">
                    <input type="hidden" name="product_id" value="<?php echo $p->id; ?>">
                    <?php wp_nonce_field( 'ravn_affiliate_delete_product' ); ?>
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
            'base'    => add_query_arg( array( 'paged' => '%#%', 'per_page' => $per_page ) ),
            'format'  => '',
            'current' => $page_num,
            'total'   => $pages,
            'type'    => 'list',
        ) );
        ?>
    </div>
</div>
<?php endif; ?>
<script>
(function () {
    var all = document.getElementById('ravn-select-all');
    var boxes = function () { return document.querySelectorAll('.ravn-row-cb'); };
    var count = document.getElementById('ravn-bulk-count');
    function update() {
        var n = document.querySelectorAll('.ravn-row-cb:checked').length;
        count.textContent = n ? n + ' geselecteerd' : '';
        all.checked = n > 0 && n === boxes().length;
    }
    all.addEventListener('change', function () {
        boxes().forEach(function (b) { b.checked = all.checked; });
        update();
    });
    boxes().forEach(function (b) { b.addEventListener('change', update); });
    document.getElementById('ravn-bulk-form').addEventListener('submit', function (e) {
        var n = document.querySelectorAll('.ravn-row-cb:checked').length;
        var action = document.getElementById('ravn-bulk-action').value;
        if (!action) { e.preventDefault(); alert('Kies eerst een bulkactie.'); return; }
        if (!n) { e.preventDefault(); alert('Selecteer eerst één of meer producten.'); return; }
        if (!confirm(n + ' product(en) definitief verwijderen, inclusief aanbieders en statistieken?')) e.preventDefault();
    });
})();
</script>
</div>
