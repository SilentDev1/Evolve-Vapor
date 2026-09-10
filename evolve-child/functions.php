<?php
/**
 * Evolve Child Theme — functions.php
 *
 * Hello Elementor child for evolvevapornh.com.
 * Loads the design-system stylesheets, registers fonts, and exposes a few small
 * helpers used by the Evolve Core companion plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EVOLVE_CHILD_VERSION', '1.0.34' );
define( 'EVOLVE_CHILD_DIR', trailingslashit( get_stylesheet_directory() ) );
define( 'EVOLVE_CHILD_URI', trailingslashit( get_stylesheet_directory_uri() ) );

/* -----------------------------------------------------------------------------
 * Enqueue
 * -------------------------------------------------------------------------- */
add_action( 'wp_enqueue_scripts', function () {

	wp_enqueue_style(
		'hello-elementor',
		trailingslashit( get_template_directory_uri() ) . 'style.css',
		[],
		'3.0'
	);

	wp_enqueue_style(
		'evolve-fonts',
		'https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&family=Inter:wght@300;400;500;600;700&display=swap',
		[],
		EVOLVE_CHILD_VERSION
	);

	wp_enqueue_style(
		'evolve-design-system',
		EVOLVE_CHILD_URI . 'assets/css/evolve.css',
		[ 'hello-elementor', 'evolve-fonts' ],
		EVOLVE_CHILD_VERSION
	);

	if ( class_exists( 'WooCommerce' ) ) {
		wp_enqueue_style(
			'evolve-woocommerce',
			EVOLVE_CHILD_URI . 'assets/css/woocommerce.css',
			[ 'evolve-design-system' ],
			EVOLVE_CHILD_VERSION
		);
	}

	wp_enqueue_script(
		'evolve-ui',
		EVOLVE_CHILD_URI . 'assets/js/evolve-ui.js',
		[],
		EVOLVE_CHILD_VERSION,
		true
	);
}, 20 );

/* -----------------------------------------------------------------------------
 * Theme support
 * -------------------------------------------------------------------------- */
add_action( 'after_setup_theme', function () {

	add_theme_support( 'woocommerce' );
	add_theme_support( 'wc-product-gallery-zoom' );
	add_theme_support( 'wc-product-gallery-lightbox' );
	add_theme_support( 'wc-product-gallery-slider' );

	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'custom-logo', [
		'height'      => 60,
		'width'       => 220,
		'flex-height' => true,
		'flex-width'  => true,
	] );

	register_nav_menus( [
		'primary'        => __( 'Primary Menu',        'evolve-child' ),
		'mobile'         => __( 'Mobile Menu',         'evolve-child' ),
		'footer-quick'   => __( 'Footer — Quick Links','evolve-child' ),
		'footer-support' => __( 'Footer — Customer Service', 'evolve-child' ),
	] );
} );

/* -----------------------------------------------------------------------------
 * WooCommerce tweaks
 * -------------------------------------------------------------------------- */
// Default to 5 columns on the shop archive (matches homepage Featured grid).
add_filter( 'loop_shop_columns',    fn() => 5, 99 );
add_filter( 'loop_shop_per_page',   fn() => 20, 99 );

// Strip the default WooCommerce wrapper — Elementor controls the page chrome.
remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper',       10 );
remove_action( 'woocommerce_after_main_content',  'woocommerce_output_content_wrapper_end',   10 );
add_action(    'woocommerce_before_main_content', function () { echo '<div class="evolve-woo-wrap">'; }, 10 );
add_action(    'woocommerce_after_main_content',  function () { echo '</div>'; }, 10 );

// Pickup notice — Elementor cart / checkout / single-product templates already
// render this via their own HTML widgets, so we DO NOT auto-inject here anymore.
// Kept as a helper for any place that still calls it directly.
function evolve_pickup_notice() {
	echo '<div class="evolve-pickup-notice"><span class="evolve-pickup-notice__dot"></span><span>In-store pickup at <strong>Pheasant Lane Mall</strong> · 21+ ID required</span></div>';
}

// Hide WC + Hello Elementor page titles on cart/checkout/my-account/order-received
// so the Elementor template's hero H1 is the only one visible. We avoid the
// global `the_title` filter — it also runs on every menu item, which would
// leave the header navigation labels empty on these pages.
add_filter( 'woocommerce_show_page_title', '__return_false' );
add_filter( 'hello_elementor_page_title',  '__return_false' );

/* -----------------------------------------------------------------------------
 * Body classes — give CSS hooks for hero / shop / single-product / etc.
 * -------------------------------------------------------------------------- */
add_filter( 'body_class', function ( $classes ) {
	$classes[] = 'evolve-theme';
	if ( is_front_page() )            { $classes[] = 'evolve-home'; }
	if ( function_exists( 'is_woocommerce' ) && is_woocommerce() ) {
		$classes[] = 'evolve-woo';
	}
	return $classes;
} );

/* -----------------------------------------------------------------------------
 * Helper available to the companion plugin and to template parts.
 * -------------------------------------------------------------------------- */
function evolve_logo_url() {
	$custom = get_theme_mod( 'custom_logo' );
	if ( $custom ) {
		$src = wp_get_attachment_image_src( $custom, 'full' );
		if ( $src ) { return $src[0]; }
	}
	return EVOLVE_CHILD_URI . 'assets/images/evolve-logo.png';
}
