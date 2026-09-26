<?php
/**
 * Storefront fixes found in the 2026-09 QA / SEO pass.
 *
 * 1. Product structured data. The Elementor single-product template never runs
 *    woocommerce_single_product_summary, where WooCommerce builds its Product/Offer
 *    JSON-LD, so product pages had no price, availability or brand for search engines.
 *    When nothing built it, it's built here before WooCommerce prints it in the footer.
 * 2. One H1 per product page: the Elementor Product Title widget renders an <h4>.
 *    Its tag becomes <h1>; classes (and so the styling) stay.
 * 3. Phones: the chat bubble and accessibility button sat on top of Add to Cart,
 *    the coupon field and Place order. They fade out while those are on screen.
 *
 * @package Evolve_Core
 */

namespace Evolve_Core;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Storefront {

	public function __construct() {
		add_action( 'wp_footer', [ $this, 'product_schema' ], 5 );
		add_filter( 'elementor/widget/render_content', [ $this, 'product_title_h1' ], 10, 2 );
		add_action( 'wp_footer', [ $this, 'buy_area_widgets' ], 50 );
	}

	public function product_schema() {
		if ( ! function_exists( 'is_product' ) || ! is_product() || ! function_exists( 'WC' ) || ! isset( WC()->structured_data ) ) {
			return;
		}
		$sd = WC()->structured_data;
		foreach ( (array) $sd->get_data() as $node ) {
			if ( isset( $node['@type'] ) && in_array( 'Product', (array) $node['@type'], true ) ) {
				return; // Already built by the template.
			}
		}
		$product = wc_get_product( get_the_ID() );
		if ( $product ) {
			$sd->generate_product_data( $product );
		}
	}

	/**
	 * @param string                 $content Rendered widget HTML.
	 * @param \Elementor\Widget_Base $widget  Widget.
	 */
	public function product_title_h1( $content, $widget ) {
		if ( ! is_object( $widget ) || $widget->get_name() !== 'woocommerce-product-title' || ! is_product() ) {
			return $content;
		}
		return preg_replace(
			'#<h([2-6])(\s[^>]*class="[^"]*product_title[^"]*"[^>]*)>(.*?)</h\1>#s',
			'<h1$2>$3</h1>',
			$content,
			1
		);
	}

	public function buy_area_widgets() {
		if ( ! function_exists( 'is_woocommerce' ) || ! ( is_product() || is_cart() || is_checkout() ) ) {
			return;
		}
		?>
		<style>
			@media (max-width: 767px) {
				#omnisuggest-bubble, #ea11y-root { transition: opacity .2s ease, visibility .2s; }
				body.evolve-buy-in-view #omnisuggest-bubble,
				body.evolve-buy-in-view #ea11y-root { opacity: 0; visibility: hidden; pointer-events: none; }
			}
		</style>
		<script>
		(function () {
			if (!('IntersectionObserver' in window) || !window.matchMedia('(max-width: 767px)').matches) return;
			var targets = document.querySelectorAll('form.cart .single_add_to_cart_button, form.cart .quantity, .woocommerce-cart-form .coupon, .wc-proceed-to-checkout, #place_order');
			if (!targets.length) return;
			var shown = new Set();
			var io = new IntersectionObserver(function (entries) {
				entries.forEach(function (e) { if (e.isIntersecting) shown.add(e.target); else shown.delete(e.target); });
				document.body.classList.toggle('evolve-buy-in-view', shown.size > 0);
			}, { rootMargin: '0px' });
			targets.forEach(function (t) { io.observe(t); });
		})();
		</script>
		<?php
	}
}
