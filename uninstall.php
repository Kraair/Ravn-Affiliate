<?php
/**
 * Ravn Affiliate – Uninstall
 * Verwijdert alle tabellen en opties bij het verwijderen van de plugin.
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Verwijder databasetabellen
$tables = [
    $wpdb->prefix . 'ravn_products',
    $wpdb->prefix . 'ravn_offers',
    $wpdb->prefix . 'ravn_clicks',
    $wpdb->prefix . 'ravn_stock_log',
    $wpdb->prefix . 'ravn_faq',
];

foreach ( $tables as $table ) {
    $wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

// Verwijder alle plugin opties
$options = [
    'ravn_affiliate_options',
    'ravn_affiliate_version',
    'ravn_cron_timeout_count',
    'ravn_last_cron_run',
    'ravn_cloak_rules_version',
];

foreach ( $options as $option ) {
    delete_option( $option );
}

// Verwijder transients
delete_transient( 'ravn_bol_token' );

// Multisite: verwijder per blog
if ( is_multisite() ) {
    $blog_ids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->blogs}" );
    foreach ( $blog_ids as $blog_id ) {
        switch_to_blog( $blog_id );
        foreach ( $tables as $table ) {
            $wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore
        }
        foreach ( $options as $option ) {
            delete_option( $option );
        }
        delete_transient( 'ravn_bol_token' );
        restore_current_blog();
    }
}
