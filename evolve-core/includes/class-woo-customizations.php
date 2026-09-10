<?php
/**
 * WooCommerce customizations specific to Evolve.
 *
 * - Adds an in-store-pickup badge above the cart button on the loop card
 * - Replaces the default "Sale!" label with a custom badge
 * - Stops Woo from forcing a wrapper on archive grids so Elementor can own layout
 */
namespace Evolve_Core;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Woo_Customizations {

	public function __construct() {
		if ( ! class_exists( 'WooCommerce' ) ) { return; }

		add_filter( 'woocommerce_sale_flash', [ $this, 'sale_badge' ], 10, 3 );
		add_action( 'woocommerce_after_shop_loop_item_title', [ $this, 'pickup_chip' ], 7 );

		// Strip Woo's enforced sidebar on archives so Elementor templates work clean.
		add_action( 'init', function () {
			remove_action( 'woocommerce_sidebar', 'woocommerce_get_sidebar', 10 );
		} );

		// Allow the "Account" menu icon to swap login text dynamically.
		add_shortcode( 'evolve_account_link', [ $this, 'shortcode_account_link' ] );
		add_shortcode( 'evolve_cart_count',   [ $this, 'shortcode_cart_count' ] );

		// Replace the "No products in the cart." line on the mini-cart with a
		// proper empty-state card (icon + heading + lede + neon CTA).
		add_filter( 'woocommerce_empty_mini_cart_html', [ $this, 'empty_mini_cart' ], 10, 1 );

		// 1.3.3 — the filter above doesn't exist in older WC versions, so also
		// wrap the buffered fragment HTML on shipping_cart fragment refresh.
		add_filter( 'woocommerce_add_to_cart_fragments', [ $this, 'inject_empty_state_fragment' ], 20, 1 );
	}

	/**
	 * Returns the Evolve empty-state HTML for the mini-cart.
	 */
	private function empty_html() {
		$shop = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' );
		ob_start();
		?>
		<div class="evolve-cart-empty">
			<span class="evolve-cart-empty__icon" aria-hidden="true">
				<svg viewBox="0 0 64 64" width="48" height="48" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round">
					<path d="M8 12h6l4 30h32l5-22H18"/>
					<circle cx="24" cy="52" r="3.5"/>
					<circle cx="44" cy="52" r="3.5"/>
				</svg>
			</span>
			<h4 class="evolve-cart-empty__title">Your cart is empty.</h4>
			<p class="evolve-cart-empty__lede">Disposables, e-juice, kits, pods, coils — let's find something good.</p>
			<a href="<?php echo esc_url( $shop ); ?>" class="evolve-cart-empty__btn">Browse the store →</a>
		</div>
		<?php
		return ob_get_clean();
	}

	public function empty_mini_cart( $html ) {
		return $this->empty_html();
	}

	/**
	 * Catch WC's standard mini-cart fragment refresh and swap the empty
	 * paragraph for our styled card. Necessary because WC's JS fragment
	 * refresh re-renders the inner HTML via AJAX *after* page load, and
	 * the `woocommerce_empty_mini_cart_html` filter is only available
	 * in newer WC versions.
	 */
	public function inject_empty_state_fragment( $fragments ) {
		if ( ! function_exists( 'WC' ) || ! WC()->cart || ! WC()->cart->is_empty() ) {
			return $fragments;
		}
		// Re-render the widget_shopping_cart fragment that the menu cart reads.
		ob_start();
		?>
		<div class="widget_shopping_cart_content"><?php echo $this->empty_html(); ?></div>
		<?php
		$fragments['div.widget_shopping_cart_content'] = ob_get_clean();
		return $fragments;
	}

	public function sale_badge( $html, $post, $product ) {
		return '<span class="onsale evolve-sale">DEAL</span>';
	}

	public function pickup_chip() {
		echo '<span class="evolve-loop-chip">In-store pickup</span>';
	}

	public function shortcode_account_link() {
		$url   = wc_get_page_permalink( 'myaccount' );
		$label = is_user_logged_in() ? __( 'Account', 'evolve-core' ) : __( 'Sign In', 'evolve-core' );
		return sprintf( '<a href="%s" class="evolve-iconbtn evolve-account-link">%s</a>', esc_url( $url ), esc_html( $label ) );
	}

	public function shortcode_cart_count() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) { return ''; }
		$count = WC()->cart->get_cart_contents_count();
		return sprintf(
			'<a href="%s" class="evolve-iconbtn evolve-cart-link" aria-label="Cart"><span class="evolve-cart-icon">🛒</span><span class="evolve-badge evolve-cart-count">%d</span></a>',
			esc_url( wc_get_cart_url() ),
			(int) $count
		);
	}
}
