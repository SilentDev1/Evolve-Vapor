<?php
/**
 * Plugin Name:       Evolve AI Vape Match
 * Plugin URI:        https://evolvevapornh.com
 * Description:       AI-powered "Find My Perfect Vape" + "Flavor Match" guided shopping wizard for WooCommerce vape stores. Only ever recommends real, in-stock, published products.
 * Version:           1.3.1
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * WC requires at least: 7.0
 * Author:            Evolve Vapor / Cao-Tech
 * Author URI:        https://evolvevapornh.com
 * License:           GPL-2.0-or-later
 * Text Domain:       evolve-ai-vape-match
 *
 * @package EvolveAIVapeMatch
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'EVOLVE_AIVM_VERSION', '1.3.1' );
define( 'EVOLVE_AIVM_FILE',    __FILE__ );
define( 'EVOLVE_AIVM_DIR',     plugin_dir_path( __FILE__ ) );
define( 'EVOLVE_AIVM_URL',     plugin_dir_url( __FILE__ ) );
define( 'EVOLVE_AIVM_BASENAME',plugin_basename( __FILE__ ) );

require_once EVOLVE_AIVM_DIR . 'includes/class-plugin.php';
require_once EVOLVE_AIVM_DIR . 'includes/class-admin.php';
require_once EVOLVE_AIVM_DIR . 'includes/class-product-meta.php';
require_once EVOLVE_AIVM_DIR . 'includes/class-recommender.php';
require_once EVOLVE_AIVM_DIR . 'includes/class-openai.php';
require_once EVOLVE_AIVM_DIR . 'includes/class-rest-api.php';

add_action( 'plugins_loaded', static function () {
	\EvolveAIVapeMatch\Plugin::instance()->boot();
}, 20 );

register_activation_hook( __FILE__, static function () {
	// Seed default options on activation.
	$defaults = [
		'api_key'             => '',
		'model'               => 'gpt-4o-mini',
		'ai_explanations'     => 1,
		'max_products_to_ai'  => 12,
		'fallback_enabled'    => 1,
		'accent_color'        => '#B7FF00',
		'button_color'        => '#B7FF00',
		'floating_button'     => 0,
		'age_text'            => 'I confirm I am of legal age to purchase nicotine products in my location.',
	];
	if ( ! get_option( 'evolve_aivm_settings' ) ) {
		add_option( 'evolve_aivm_settings', $defaults );
	}
} );

// WooCommerce active check — show admin notice instead of fatal-ing if missing.
add_action( 'admin_notices', static function () {
	if ( class_exists( 'WooCommerce' ) ) { return; }
	if ( ! current_user_can( 'activate_plugins' ) ) { return; }
	echo '<div class="notice notice-error"><p><strong>Evolve AI Vape Match</strong> requires WooCommerce to be active. Install &amp; activate WooCommerce to use this plugin.</p></div>';
} );
