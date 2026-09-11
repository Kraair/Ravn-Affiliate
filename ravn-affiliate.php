<?php
/**
 * Plugin Name: Ravn Affiliate
 * Description: An affiliate plugin for the platforms Bol.com, Amazon, Awin, Daisycon and TradeTracker.
 * Version:     2.7.1
 * Author:      KraaiR
 * Author URI:  https://tammohaan.nl
 * Text Domain: ravn-affiliate
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * Copyright (c) 2026 KraaiR
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'RAVN_VERSION',     '2.7.1' );
define( 'RAVN_PLUGIN_FILE', __FILE__ );
define( 'RAVN_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'RAVN_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'RAVN_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Autoloader
spl_autoload_register( function( $class ) {
    $map = array(
        'Ravn_Options'    => 'includes/class-ravn-options.php',
        'Ravn_Database'   => 'includes/class-ravn-database.php',
        'Ravn_Activator'  => 'includes/class-ravn-activator.php',
        'Ravn_Deactivator'=> 'includes/class-ravn-deactivator.php',
        'Ravn_Product'    => 'includes/class-ravn-product.php',
        'Ravn_API'        => 'includes/class-ravn-api.php',
        'Ravn_Shortcodes' => 'includes/class-ravn-shortcodes.php',
        'Ravn_Blocks'     => 'includes/class-ravn-blocks.php',
        'Ravn_Cron'       => 'includes/class-ravn-cron.php',
        'Ravn_Admin'      => 'admin/class-ravn-admin.php',
        'Ravn_FAQ'        => 'modules/faq/class-ravn-faq.php',
        'Ravn_TOC'        => 'modules/toc/class-ravn-toc.php',
        'Ravn_Schema'     => 'modules/schema/class-ravn-schema.php',
        'Ravn_Stats'      => 'modules/stats/class-ravn-stats.php',
        'Ravn_Cloaking'   => 'modules/cloaking/class-ravn-cloaking.php',
        'Ravn_Import'     => 'modules/import/class-ravn-import.php',
        'Ravn_Styling'    => 'modules/styling/class-ravn-styling.php',
    );
    if ( isset( $map[ $class ] ) ) {
        $file = RAVN_PLUGIN_DIR . $map[ $class ];
        if ( file_exists( $file ) ) {
            require_once $file;
        }
    }
} );

register_activation_hook(   __FILE__, array( 'Ravn_Activator',   'activate' ) );
register_deactivation_hook( __FILE__, array( 'Ravn_Deactivator', 'deactivate' ) );

function ravn_affiliate_run() {
    // Database-upgrade check: zorgt dat toekomstige schemawijzigingen
    // ook verschijnen na een update via bestandsvervanging, niet alleen na
    // een expliciete de-/reactivatie van de plugin. dbDelta() is veilig
    // herhaalbaar en past alleen aan wat nog niet klopt.
    if ( get_option( 'ravn_affiliate_version' ) !== RAVN_VERSION ) {
        Ravn_Database::create_tables();
        update_option( 'ravn_affiliate_version', RAVN_VERSION );
    }

    // Core modules
    new Ravn_Shortcodes();
    new Ravn_Blocks();
    new Ravn_Cron();
    new Ravn_Cloaking();
    new Ravn_Styling();

    // Content modules
    $opts = Ravn_Options::get_all();
    if ( ! empty( $opts['module_faq'] ) )    new Ravn_FAQ();
    if ( ! empty( $opts['module_toc'] )  )   new Ravn_TOC();
    if ( ! empty( $opts['module_schema'] ) ) new Ravn_Schema();
    if ( ! empty( $opts['module_stats'] )  ) new Ravn_Stats();

    // Admin
    if ( is_admin() ) {
        new Ravn_Admin();
        if ( ! empty( $opts['module_import'] ) ) new Ravn_Import();
    }
}
add_action( 'plugins_loaded', 'ravn_affiliate_run' );
