<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Ravn_Deactivator {
    public static function deactivate() {
        wp_clear_scheduled_hook( 'ravn_cron_fetch' );
        flush_rewrite_rules();
    }
}
