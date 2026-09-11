<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Ravn_Activator {
    public static function activate() {
        Ravn_Database::create_tables();
        // Sla versie op
        update_option( 'ravn_affiliate_version', RAVN_VERSION );
        // Flush rewrite rules voor cloaking
        flush_rewrite_rules();
    }
}
