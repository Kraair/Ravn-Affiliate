<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Ravn_Deactivator {
    public static function deactivate() {
        wp_clear_scheduled_hook( 'ravn_cron_fetch' );
        wp_clear_scheduled_hook( 'ravn_cron_continue' );
        flush_rewrite_rules();
    }
}
