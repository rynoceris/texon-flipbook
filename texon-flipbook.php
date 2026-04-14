<?php
/**
 * Plugin Name: Texon Flipbook
 * Description: Embed interactive page-turn catalogs (PDF flipbooks) with clickable hotspots. Use shortcode [texon_flipbook id="..."] for inline, or [texon_flipbook id="..." trigger="button" label="View Catalog"] for a modal.
 * Version: 1.5.2
 * Author: Texon Towel
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'TEXON_FLIPBOOK_VERSION', '1.5.2' );
define( 'TEXON_FLIPBOOK_DIR', plugin_dir_path( __FILE__ ) );
define( 'TEXON_FLIPBOOK_URL', plugin_dir_url( __FILE__ ) );

require_once TEXON_FLIPBOOK_DIR . 'includes/class-renderer.php';
require_once TEXON_FLIPBOOK_DIR . 'includes/class-post-type.php';
require_once TEXON_FLIPBOOK_DIR . 'includes/class-events.php';
require_once TEXON_FLIPBOOK_DIR . 'includes/class-settings.php';
require_once TEXON_FLIPBOOK_DIR . 'includes/class-admin.php';
require_once TEXON_FLIPBOOK_DIR . 'includes/class-shortcode.php';

add_action( 'init', [ 'Texon_Flipbook_Post_Type', 'register' ] );
add_action( 'init', [ 'Texon_Flipbook_Shortcode', 'register' ] );
add_action( 'admin_menu', [ 'Texon_Flipbook_Admin', 'menu' ] );
add_action( 'admin_enqueue_scripts', [ 'Texon_Flipbook_Admin', 'enqueue' ] );
add_action( 'admin_post_texon_flipbook_save', [ 'Texon_Flipbook_Admin', 'handle_save' ] );
add_action( 'admin_post_texon_flipbook_delete', [ 'Texon_Flipbook_Admin', 'handle_delete' ] );
add_action( 'wp_ajax_texon_flipbook_save_hotspots', [ 'Texon_Flipbook_Admin', 'ajax_save_hotspots' ] );
add_action( 'wp_ajax_texon_flipbook_render_page', [ 'Texon_Flipbook_Admin', 'ajax_render_page' ] );

// Analytics tracking endpoints (public + logged-in)
add_action( 'wp_ajax_texon_flipbook_track',        [ 'Texon_Flipbook_Events', 'ajax_track' ] );
add_action( 'wp_ajax_nopriv_texon_flipbook_track', [ 'Texon_Flipbook_Events', 'ajax_track' ] );

// Daily cron: prune old events
add_action( 'texon_flipbook_prune', [ 'Texon_Flipbook_Events', 'prune' ] );

// Auto-install the events table and cron when the plugin is updated without
// a full deactivate/reactivate cycle
add_action( 'admin_init', function() {
    $installed = get_option( 'texon_flipbook_db_version' );
    if ( $installed !== TEXON_FLIPBOOK_VERSION ) {
        Texon_Flipbook_Events::install();
        if ( ! wp_next_scheduled( 'texon_flipbook_prune' ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'texon_flipbook_prune' );
        }
        update_option( 'texon_flipbook_db_version', TEXON_FLIPBOOK_VERSION );
    }
} );

register_activation_hook( __FILE__, function() {
    Texon_Flipbook_Post_Type::register();
    Texon_Flipbook_Events::install();
    if ( ! wp_next_scheduled( 'texon_flipbook_prune' ) ) {
        wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'texon_flipbook_prune' );
    }
    flush_rewrite_rules();
} );

register_deactivation_hook( __FILE__, function() {
    $ts = wp_next_scheduled( 'texon_flipbook_prune' );
    if ( $ts ) wp_unschedule_event( $ts, 'texon_flipbook_prune' );
} );
