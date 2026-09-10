<?php
/**
 * Buy 4 Get 5th Free — automatic BOGO promotion.
 *
 * Tracks qualifying items per category (e-juice, salt-nicotine, disposable)
 * independently. Every 5th qualifying item is free (cheapest in its group).
 * Stacks: 10 → 2 free, 15 → 3 free, etc.
 */
namespace Evolve_Core;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Bogo_Promotion {

	/**
	 * Product category slugs that qualify for the promotion.
	 */
	private const QUALIFYING_CATS = [ 'e-juice', 'salt-nicotine', 'disposable' ];

	/**
	 * Items needed per free item.
	 */
	private const GROUP_SIZE = 5;

	public function __construct() {
		if ( ! class_exists( 'WooCommerce' ) ) { return; }

		// Apply discount as a negative fee.
		add_action( 'woocommerce_cart_calculate_fees', [ $this, 'apply_discount' ] );

		// Add-to-cart progress notice.
		add_action( 'woocommerce_add_to_cart', [ $this, 'add_to_cart_notice' ], 10, 6 );

		// Cart / checkout promo banner.
		add_action( 'woocommerce_before_cart',          [ $this, 'cart_promo_banner' ] );
		add_action( 'woocommerce_before_checkout_form', [ $this, 'cart_promo_banner' ] );

		// Re-check after quantity update.
		add_action( 'woocommerce_after_cart_item_quantity_update', [ $this, 'quantity_update_notice' ], 10, 4 );

		// Mini-cart promo banner (before View Cart / Checkout buttons).
		add_action( 'woocommerce_widget_shopping_cart_before_buttons', [ $this, 'mini_cart_banner' ], 5 );

		// Keep mini-cart banner in sync via AJAX fragment refresh.
		add_filter( 'woocommerce_add_to_cart_fragments', [ $this, 'mini_cart_fragment' ] );

		// Enqueue banner styles.
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/* ------------------------------------------------------------------
	 * Asset loading
	 * ----------------------------------------------------------------*/

	public function enqueue_assets() {
		wp_enqueue_style(
			'evolve-bogo-promotion',
			EVOLVE_CORE_URL . 'assets/css/bogo-promotion.css',
			[],
			EVOLVE_CORE_VERSION
		);
	}

	/* ------------------------------------------------------------------
	 * Core logic — build per-category stats
	 * ----------------------------------------------------------------*/

	/**
	 * Returns an associative array keyed by qualifying category slug:
	 *
	 *   [
	 *     'e-juice' => [
	 *       'qty'        => 7,
	 *       'free_count' => 1,
	 *       'discount'   => 12.99,
	 *       'label'      => 'E-Juice',
	 *     ],
	 *     ...
	 *   ]
	 */
	public function get_qualifying_counts() {
		if ( ! WC()->cart ) { return []; }

		$groups = [];

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$product_id = $cart_item['variation_id'] ? $cart_item['variation_id'] : $cart_item['product_id'];
			$parent_id  = $cart_item['product_id'];

			foreach ( self::QUALIFYING_CATS as $cat_slug ) {
				if ( has_term( $cat_slug, 'product_cat', $parent_id ) ) {
					if ( ! isset( $groups[ $cat_slug ] ) ) {
						$term = get_term_by( 'slug', $cat_slug, 'product_cat' );
						$groups[ $cat_slug ] = [
							'qty'    => 0,
							'prices' => [],
							'label'  => $term ? $term->name : ucwords( str_replace( '-', ' ', $cat_slug ) ),
						];
					}

					$qty   = (int) $cart_item['quantity'];
					$price = (float) $cart_item['data']->get_price();

					$groups[ $cat_slug ]['qty'] += $qty;

					// Store each unit price individually for sorting.
					for ( $i = 0; $i < $qty; $i++ ) {
						$groups[ $cat_slug ]['prices'][] = $price;
					}

					break; // A product only counts toward one category.
				}
			}
		}

		// Calculate free count and discount for each category.
		$results = [];
		foreach ( $groups as $slug => $data ) {
			$free_count = (int) floor( $data['qty'] / self::GROUP_SIZE );
			$discount   = 0;

			if ( $free_count > 0 ) {
				sort( $data['prices'] ); // ascending — cheapest first
				for ( $i = 0; $i < $free_count; $i++ ) {
					$discount += $data['prices'][ $i ];
				}
			}

			$results[ $slug ] = [
				'qty'        => $data['qty'],
				'free_count' => $free_count,
				'discount'   => round( $discount, 2 ),
				'label'      => $data['label'],
			];
		}

		return $results;
	}

	/* ------------------------------------------------------------------
	 * Discount application — negative fee
	 * ----------------------------------------------------------------*/

	public function apply_discount( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) { return; }

		$counts = $this->get_qualifying_counts();

		foreach ( $counts as $slug => $data ) {
			if ( $data['discount'] <= 0 ) { continue; }

			$cart->add_fee(
				sprintf( 'Buy 4 Get 5th Free — %s', $data['label'] ),
				-$data['discount'],
				false // not taxable — discount on product price
			);
		}
	}

	/* ------------------------------------------------------------------
	 * Add-to-cart notice
	 * ----------------------------------------------------------------*/

	public function add_to_cart_notice( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
		$matched_cat = $this->get_product_qualifying_cat( $product_id );
		if ( ! $matched_cat ) { return; }

		$counts = $this->get_qualifying_counts();
		if ( ! isset( $counts[ $matched_cat ] ) ) { return; }

		$data      = $counts[ $matched_cat ];
		$remaining = self::GROUP_SIZE - ( $data['qty'] % self::GROUP_SIZE );

		if ( $remaining === self::GROUP_SIZE ) {
			// Just completed a group — user earned a free item.
			wc_add_notice(
				sprintf(
					'<span class="evolve-bogo-notice evolve-bogo-notice--success">🎉 You earned a <strong>FREE %s</strong> item! The cheapest qualifying item is on us.</span>',
					esc_html( $data['label'] )
				),
				'success'
			);
		} else {
			wc_add_notice(
				sprintf(
					'<span class="evolve-bogo-notice">🛒 Add <strong>%d</strong> more %s to get 1 <strong>FREE</strong>! <small>Buy 4, Get 5th Free</small></span>',
					$remaining,
					esc_html( $data['label'] )
				),
				'notice'
			);
		}
	}

	/* ------------------------------------------------------------------
	 * Quantity update notice
	 * ----------------------------------------------------------------*/

	public function quantity_update_notice( $cart_item_key, $new_quantity, $old_quantity, $cart ) {
		$cart_item = $cart->cart_contents[ $cart_item_key ] ?? null;
		if ( ! $cart_item ) { return; }

		$product_id  = $cart_item['product_id'];
		$matched_cat = $this->get_product_qualifying_cat( $product_id );
		if ( ! $matched_cat ) { return; }

		// Recalculate totals so counts reflect the new qty.
		$cart->calculate_totals();
		$counts = $this->get_qualifying_counts();
		if ( ! isset( $counts[ $matched_cat ] ) ) { return; }

		$data      = $counts[ $matched_cat ];
		$remaining = self::GROUP_SIZE - ( $data['qty'] % self::GROUP_SIZE );

		if ( $data['free_count'] > 0 && $remaining === self::GROUP_SIZE ) {
			wc_add_notice(
				sprintf(
					'<span class="evolve-bogo-notice evolve-bogo-notice--success">🎉 Buy 4 Get 5th Free applied! You\'re saving <strong>$%s</strong> on %s.</span>',
					number_format( $data['discount'], 2 ),
					esc_html( $data['label'] )
				),
				'success'
			);
		} elseif ( $remaining < self::GROUP_SIZE ) {
			wc_add_notice(
				sprintf(
					'<span class="evolve-bogo-notice">🛒 Add <strong>%d</strong> more %s to get 1 <strong>FREE</strong>! <small>Buy 4, Get 5th Free</small></span>',
					$remaining,
					esc_html( $data['label'] )
				),
				'notice'
			);
		}
	}

	/* ------------------------------------------------------------------
	 * Cart / checkout promo banner
	 * ----------------------------------------------------------------*/

	public function cart_promo_banner() {
		$counts = $this->get_qualifying_counts();
		if ( empty( $counts ) ) { return; }

		// Per-category progress notices (same style as product-page notices).
		foreach ( $counts as $slug => $data ) {
			$remaining = self::GROUP_SIZE - ( $data['qty'] % self::GROUP_SIZE );

			if ( $data['free_count'] > 0 && $remaining === self::GROUP_SIZE ) {
				printf(
					'<div class="evolve-bogo-notice-box"><span class="evolve-bogo-notice evolve-bogo-notice--success">🎉 Buy 4 Get 5th Free applied! You\'re saving <strong>$%s</strong> on %s.</span></div>',
					esc_html( number_format( $data['discount'], 2 ) ),
					esc_html( $data['label'] )
				);
			} else {
				printf(
					'<div class="evolve-bogo-notice-box"><span class="evolve-bogo-notice">🛒 Add <strong>%d</strong> more %s to get 1 <strong>FREE</strong>! <small>Buy 4, Get 5th Free</small></span></div>',
					(int) $remaining,
					esc_html( $data['label'] )
				);
			}
		}

		echo '<div class="evolve-bogo-banner">';
		echo '<h4 class="evolve-bogo-banner__title">Buy 4, Get 5th FREE</h4>';

		foreach ( $counts as $slug => $data ) {
			$filled    = $data['qty'] % self::GROUP_SIZE;
			$remaining = self::GROUP_SIZE - $filled;

			if ( $remaining === self::GROUP_SIZE && $data['free_count'] > 0 ) {
				$filled = self::GROUP_SIZE; // full bar
			}

			$pct = ( $filled / self::GROUP_SIZE ) * 100;

			echo '<div class="evolve-bogo-banner__row">';
			echo '<span class="evolve-bogo-banner__cat">' . esc_html( $data['label'] ) . '</span>';

			echo '<div class="evolve-bogo-banner__bar-wrap">';
			echo '<div class="evolve-bogo-banner__bar" style="width:' . esc_attr( $pct ) . '%"></div>';
			echo '</div>';

			if ( $data['free_count'] > 0 ) {
				echo '<span class="evolve-bogo-banner__status evolve-bogo-banner__status--active">';
				printf(
					'Applied! Saving $%s (%d free)',
					esc_html( number_format( $data['discount'], 2 ) ),
					(int) $data['free_count']
				);
				echo '</span>';
			} else {
				echo '<span class="evolve-bogo-banner__status">';
				printf(
					'%d of %d — add %d more for a free one!',
					(int) $data['qty'],
					self::GROUP_SIZE,
					(int) $remaining
				);
				echo '</span>';
			}

			echo '</div>'; // __row
		}

		echo '</div>'; // banner
	}

	/* ------------------------------------------------------------------
	 * Mini-cart sidebar banner
	 * ----------------------------------------------------------------*/

	public function mini_cart_banner() {
		$counts = $this->get_qualifying_counts();
		if ( empty( $counts ) ) { return; }

		echo '<div class="evolve-bogo-mini" id="evolve-bogo-mini">';
		echo '<div class="evolve-bogo-mini__title">Buy 4, Get 5th FREE</div>';

		foreach ( $counts as $slug => $data ) {
			$filled    = $data['qty'] % self::GROUP_SIZE;
			$remaining = self::GROUP_SIZE - $filled;

			if ( $remaining === self::GROUP_SIZE && $data['free_count'] > 0 ) {
				$filled = self::GROUP_SIZE;
			}

			$pct = ( $filled / self::GROUP_SIZE ) * 100;

			echo '<div class="evolve-bogo-mini__row">';
			echo '<div class="evolve-bogo-mini__header">';
			echo '<span class="evolve-bogo-mini__cat">' . esc_html( $data['label'] ) . '</span>';

			if ( $data['free_count'] > 0 ) {
				printf(
					'<span class="evolve-bogo-mini__badge">-%s</span>',
					esc_html( '$' . number_format( $data['discount'], 2 ) )
				);
			} else {
				printf(
					'<span class="evolve-bogo-mini__hint">%d more</span>',
					(int) $remaining
				);
			}

			echo '</div>'; // __header

			echo '<div class="evolve-bogo-mini__bar-wrap">';
			echo '<div class="evolve-bogo-mini__bar" style="width:' . esc_attr( $pct ) . '%"></div>';
			echo '</div>';

			echo '</div>'; // __row
		}

		echo '</div>'; // mini
	}

	/**
	 * Inject mini-cart banner HTML as an AJAX fragment so it updates live.
	 */
	public function mini_cart_fragment( $fragments ) {
		ob_start();
		$this->mini_cart_banner();
		$html = ob_get_clean();

		if ( $html ) {
			$fragments['#evolve-bogo-mini'] = $html;
		} else {
			// Empty cart — remove the banner.
			$fragments['#evolve-bogo-mini'] = '<div class="evolve-bogo-mini" id="evolve-bogo-mini" style="display:none"></div>';
		}

		return $fragments;
	}

	/* ------------------------------------------------------------------
	 * Helpers
	 * ----------------------------------------------------------------*/

	/**
	 * Returns the first qualifying category slug a product belongs to, or null.
	 */
	private function get_product_qualifying_cat( $product_id ) {
		foreach ( self::QUALIFYING_CATS as $cat_slug ) {
			if ( has_term( $cat_slug, 'product_cat', $product_id ) ) {
				return $cat_slug;
			}
		}
		return null;
	}
}
