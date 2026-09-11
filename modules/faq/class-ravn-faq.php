<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Ravn_FAQ {

    public function __construct() {
        add_action( 'add_meta_boxes',   array( $this, 'add_meta_box' ) );
        add_action( 'save_post',        array( $this, 'save_meta_box' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
    }

    public function add_meta_box() {
        add_meta_box(
            'ravn-faq-meta-box',
            'Veelgestelde vragen (FAQ)',
            array( $this, 'render_meta_box' ),
            array( 'post', 'page' ),
            'normal',
            'default'
        );
    }

    public function render_meta_box( $post ) {
        wp_nonce_field( 'ravn_faq_save', 'ravn_faq_nonce' );
        $items = Ravn_Database::get_faq_by_post( $post->ID );
        ?>
        <div id="ravn-faq-container">
            <?php foreach ( $items as $i => $item ) : ?>
                <div class="ravn-faq-meta-item" style="border:1px solid #ddd; padding:12px; margin-bottom:8px; border-radius:4px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                        <strong>Vraag <?php echo $i + 1; ?></strong>
                        <button type="button" class="button ravn-faq-remove">Verwijder</button>
                    </div>
                    <p>
                        <label>Vraag:</label><br>
                        <input type="text" name="ravn_faq[<?php echo $i; ?>][question]" value="<?php echo esc_attr( $item->question ); ?>" style="width:100%;" class="widefat">
                    </p>
                    <p>
                        <label>Antwoord:</label><br>
                        <textarea name="ravn_faq[<?php echo $i; ?>][answer]" rows="4" style="width:100%;" class="widefat"><?php echo esc_textarea( $item->answer ); ?></textarea>
                    </p>
                </div>
            <?php endforeach; ?>
        </div>

        <button type="button" class="button" id="ravn-faq-add-btn">+ Vraag toevoegen</button>

        <script id="ravn-faq-template" type="text/html">
            <div class="ravn-faq-meta-item" style="border:1px solid #ddd; padding:12px; margin-bottom:8px; border-radius:4px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                    <strong>Vraag #IDX#</strong>
                    <button type="button" class="button ravn-faq-remove">Verwijder</button>
                </div>
                <p>
                    <label>Vraag:</label><br>
                    <input type="text" name="ravn_faq[#IDX#][question]" value="" style="width:100%;" class="widefat">
                </p>
                <p>
                    <label>Antwoord:</label><br>
                    <textarea name="ravn_faq[#IDX#][answer]" rows="4" style="width:100%;" class="widefat"></textarea>
                </p>
            </div>
        </script>
        <?php
    }

    public function save_meta_box( $post_id ) {
        if ( ! isset( $_POST['ravn_faq_nonce'] ) ) return;
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ravn_faq_nonce'] ) ), 'ravn_faq_save' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        $items = isset( $_POST['ravn_faq'] ) ? (array) wp_unslash( $_POST['ravn_faq'] ) : array();
        Ravn_Database::save_faq_for_post( $post_id, $items );
    }

    public function enqueue_admin_scripts( $hook ) {
        if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) return;
        wp_enqueue_script(
            'ravn-faq-meta',
            RAVN_PLUGIN_URL . 'admin/js/ravn-faq-meta.js',
            array( 'jquery' ),
            RAVN_VERSION,
            true
        );
    }
}
