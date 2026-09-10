<?php
/**
 * Registers Evolve's custom Elementor widgets + their asset handles.
 */
namespace Evolve_Core;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Widgets {

	public function __construct() {
		add_action( 'elementor/elements/categories_registered', [ $this, 'category' ] );
		add_action( 'elementor/widgets/register',               [ $this, 'register' ] );
		add_action( 'wp_enqueue_scripts',                       [ $this, 'enqueue_assets' ] );
	}

	public function category( $manager ) {
		$manager->add_category( 'evolve', [
			'title' => esc_html__( 'Evolve', 'evolve-core' ),
			'icon'  => 'eicon-woo-products',
		] );
	}

	public function register( $manager ) {
		require_once EVOLVE_CORE_DIR . 'includes/widgets/class-widget-shop-filter.php';
		require_once EVOLVE_CORE_DIR . 'includes/widgets/class-widget-header-search.php';
		require_once EVOLVE_CORE_DIR . 'includes/widgets/class-widget-subscribe.php';
		require_once EVOLVE_CORE_DIR . 'includes/widgets/class-widget-vape-match-button.php';
		require_once EVOLVE_CORE_DIR . 'includes/widgets/class-widget-shop-menu.php';
		$manager->register( new Widgets\Widget_Shop_Filter() );
		$manager->register( new Widgets\Widget_Header_Search() );
		$manager->register( new Widgets\Widget_Subscribe() );
		$manager->register( new Widgets\Widget_Vape_Match_Button() );
		$manager->register( new Widgets\Widget_Shop_Menu() );
	}

	public function enqueue_assets() {
		wp_register_style(
			'evolve-shop-filter',
			EVOLVE_CORE_URL . 'assets/css/shop-filter.css',
			[],
			EVOLVE_CORE_VERSION
		);
		wp_register_script(
			'evolve-shop-filter',
			EVOLVE_CORE_URL . 'assets/js/shop-filter.js',
			[],
			EVOLVE_CORE_VERSION,
			true
		);
		wp_localize_script( 'evolve-shop-filter', 'evolveFilter', [
			'ajax'  => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'evolve_filter' ),
		] );

		wp_register_style(
			'evolve-header-search',
			EVOLVE_CORE_URL . 'assets/css/header-search.css',
			[],
			EVOLVE_CORE_VERSION
		);
		wp_register_script(
			'evolve-header-search',
			EVOLVE_CORE_URL . 'assets/js/header-search.js',
			[],
			EVOLVE_CORE_VERSION,
			true
		);
		wp_localize_script( 'evolve-header-search', 'evolveSearch', [
			'ajax'  => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'evolve_search' ),
		] );

		// Shop Menu widget assets
		wp_register_style(
			'evolve-shop-menu',
			EVOLVE_CORE_URL . 'assets/css/shop-menu.css',
			[],
			EVOLVE_CORE_VERSION
		);

		// Vape Match button widget assets
		wp_register_style(
			'evolve-vape-match-button',
			EVOLVE_CORE_URL . 'assets/css/vape-match-button.css',
			[],
			EVOLVE_CORE_VERSION
		);
		wp_register_script(
			'evolve-vape-match-button',
			EVOLVE_CORE_URL . 'assets/js/vape-match-button.js',
			[],
			EVOLVE_CORE_VERSION,
			true
		);
		wp_enqueue_style(  'evolve-vape-match-button' );
		wp_enqueue_script( 'evolve-vape-match-button' );
	}
}
