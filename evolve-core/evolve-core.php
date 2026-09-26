<?php
/**
 * Plugin Name: Evolve Core
 * Plugin URI:  https://evolvevapornh.com
 * Description: Companion plugin for the Evolve Child theme — age gate, dynamic tags, WooCommerce helpers, and a one-click Elementor template importer.
 * Version:     1.9.9
 * Author:      Evolve Vapor
 * Author URI:  https://evolvevapornh.com
 * Text Domain: evolve-core
 * Requires Plugins: elementor
 * Requires at least: 6.4
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'EVOLVE_CORE_VERSION', '1.9.9' );
define( 'EVOLVE_CORE_FILE',    __FILE__ );
define( 'EVOLVE_CORE_DIR',     plugin_dir_path( __FILE__ ) );
define( 'EVOLVE_CORE_URL',     plugin_dir_url( __FILE__ ) );

require_once EVOLVE_CORE_DIR . 'includes/class-age-gate.php';
require_once EVOLVE_CORE_DIR . 'includes/class-woo-customizations.php';
require_once EVOLVE_CORE_DIR . 'includes/class-dynamic-tags.php';
require_once EVOLVE_CORE_DIR . 'includes/class-template-importer.php';
require_once EVOLVE_CORE_DIR . 'includes/class-widgets.php';
require_once EVOLVE_CORE_DIR . 'includes/class-filter-query.php';
require_once EVOLVE_CORE_DIR . 'includes/class-search.php';
require_once EVOLVE_CORE_DIR . 'includes/class-subscribe.php';
require_once EVOLVE_CORE_DIR . 'includes/class-pulse.php';
require_once EVOLVE_CORE_DIR . 'includes/class-shortcode-docs.php';
require_once EVOLVE_CORE_DIR . 'includes/class-popup-linker.php';
require_once EVOLVE_CORE_DIR . 'includes/class-bogo-promotion.php';
require_once EVOLVE_CORE_DIR . 'includes/class-weekly-promo.php';
require_once EVOLVE_CORE_DIR . 'includes/class-performance.php';
require_once EVOLVE_CORE_DIR . 'includes/class-variation-stock.php';
require_once EVOLVE_CORE_DIR . 'includes/class-storefront.php';

add_action( 'plugins_loaded', function () {
	new \Evolve_Core\Age_Gate();
	new \Evolve_Core\Woo_Customizations();
	new \Evolve_Core\Template_Importer();
	new \Evolve_Core\Widgets();
	new \Evolve_Core\Filter_Query();
	new \Evolve_Core\Search();
	new \Evolve_Core\Subscribe();
	new \Evolve_Core\Pulse();
	new \Evolve_Core\Shortcode_Docs();
	new \Evolve_Core\Popup_Linker();
	new \Evolve_Core\Bogo_Promotion();
	new \Evolve_Core\Weekly_Promo();
	new \Evolve_Core\Performance();
	new \Evolve_Core\Variation_Stock();
	new \Evolve_Core\Storefront();

	// Elementor dynamic tags register on its own hook.
	add_action( 'elementor/dynamic_tags/register', function ( $dynamic_tags_manager ) {
		\Evolve_Core\Dynamic_Tags::register( $dynamic_tags_manager );
	} );
} );

register_activation_hook( __FILE__, function () {
	// Flush rewrite rules in case any CPTs/endpoints get added later.
	flush_rewrite_rules();
} );
