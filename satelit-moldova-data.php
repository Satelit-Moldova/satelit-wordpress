<?php
/**
 * Plugin Name:       Satelit — Moldova Data
 * Plugin URI:        https://developer.satelit.md/en/docs/
 * Description:       BNM exchange rates, a MDL converter, fuel prices, shop price comparison, news, cinema schedules, Monitorul Oficial digests and the joke of the day from the Satelit API, as shortcodes, blocks and widgets.
 * Version:           1.0.0
 * Requires at least: 5.0
 * Requires PHP:      7.2
 * Author:            Satelit
 * Author URI:        https://satelit.md
 * License:           MIT
 * License URI:       https://opensource.org/licenses/MIT
 * Text Domain:       satelit-moldova-data
 * Domain Path:       /languages
 *
 * @package SatelitMoldovaData
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SATELIT_MD_VERSION', '1.0.0' );
define( 'SATELIT_MD_FILE', __FILE__ );
define( 'SATELIT_MD_DIR', plugin_dir_path( __FILE__ ) );
define( 'SATELIT_MD_URL', plugin_dir_url( __FILE__ ) );

require_once SATELIT_MD_DIR . 'includes/class-satelit-api.php';
require_once SATELIT_MD_DIR . 'includes/class-satelit-features.php';
require_once SATELIT_MD_DIR . 'includes/class-satelit-render.php';
require_once SATELIT_MD_DIR . 'includes/class-satelit-shortcodes.php';
require_once SATELIT_MD_DIR . 'includes/class-satelit-blocks.php';
require_once SATELIT_MD_DIR . 'includes/class-satelit-widget.php';
require_once SATELIT_MD_DIR . 'includes/class-satelit-settings.php';
require_once SATELIT_MD_DIR . 'includes/class-satelit-woocommerce.php';

/**
 * Loads the bundled translations (ro_RO, ru_RU).
 */
function satelit_md_load_textdomain() {
	load_plugin_textdomain( 'satelit-moldova-data', false, dirname( plugin_basename( SATELIT_MD_FILE ) ) . '/languages' );
}
add_action( 'init', 'satelit_md_load_textdomain', 1 );

/**
 * Registers the front-end assets; they are enqueued only when something renders.
 */
function satelit_md_register_assets() {
	wp_register_style( 'satelit-md', SATELIT_MD_URL . 'assets/css/satelit.css', array(), SATELIT_MD_VERSION );
	wp_register_script( 'satelit-md-converter', SATELIT_MD_URL . 'assets/js/converter.js', array(), SATELIT_MD_VERSION, true );
}
add_action( 'init', 'satelit_md_register_assets', 5 );

Satelit_Shortcodes::init();
Satelit_Blocks::init();
Satelit_Settings::init();
Satelit_WooCommerce::init();
add_action( 'widgets_init', array( 'Satelit_Widget', 'register' ) );

/**
 * Adds a "Settings" link on the Plugins screen.
 *
 * @param array $links Existing links.
 * @return array
 */
function satelit_md_action_links( $links ) {
	$url = admin_url( 'options-general.php?page=satelit' );
	array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'satelit-moldova-data' ) . '</a>' );
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'satelit_md_action_links' );
