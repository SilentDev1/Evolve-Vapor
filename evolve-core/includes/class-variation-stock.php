<?php
/**
 * Option dropdowns show how many are in stock: "cool mint — 12 in stock", "grape ice — out of stock".
 *
 * Only for products with one option list (flavor, strength, color…): with two lists, one choice
 * alone doesn't point at a single item. Options whose stock isn't tracked are left as they are.
 *
 * @package Evolve_Core
 */

namespace Evolve_Core;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Variation_Stock {

	public function __construct() {
		add_filter( 'woocommerce_dropdown_variation_attribute_options_html', [ $this, 'label_stock' ], 20, 2 );
	}

	/**
	 * @param string $html Dropdown HTML.
	 * @param array  $args attribute, options, product, …
	 */
	public function label_stock( $html, $args ) {
		$product = $args['product'] ?? null;
		if ( ! $product instanceof \WC_Product_Variable || empty( $args['attribute'] ) ) {
			return $html;
		}
		$lists = array_filter( $product->get_attributes(), function ( $a ) { return $a->get_variation(); } );
		if ( count( $lists ) !== 1 ) {
			return $html;
		}

		// Stock per option value (compared as slugs, so "Cool Mint" and "cool-mint" are the same option).
		$key   = 'attribute_' . sanitize_title( $args['attribute'] );
		$stock = [];
		foreach ( $product->get_children() as $vid ) {
			$v = wc_get_product( $vid );
			if ( ! $v || $v->get_status() !== 'publish' ) {
				continue;
			}
			$value = (string) ( $v->get_attributes()[ sanitize_title( $args['attribute'] ) ] ?? get_post_meta( $vid, $key, true ) );
			if ( $value === '' ) {
				continue; // "Any" — not one option.
			}
			$slug = sanitize_title( $value );
			if ( ! $v->is_in_stock() ) {
				$stock[ $slug ] = ( $stock[ $slug ] ?? 0 ) + 0;
			} elseif ( $v->managing_stock() ) {
				$stock[ $slug ] = ( $stock[ $slug ] ?? 0 ) + max( 0, (int) $v->get_stock_quantity() );
			} else {
				$stock[ $slug ] = null; // In stock, count not tracked.
			}
		}
		if ( ! $stock ) {
			return $html;
		}

		return preg_replace_callback(
			'#(<option\b[^>]*\bvalue="([^"]*)"[^>]*>)(.*?)(</option>)#s',
			function ( $m ) use ( $stock ) {
				$slug = sanitize_title( html_entity_decode( $m[2], ENT_QUOTES, 'UTF-8' ) );
				if ( $m[2] === '' || ! array_key_exists( $slug, $stock ) || $stock[ $slug ] === null ) {
					return $m[0];
				}
				$n    = (int) $stock[ $slug ];
				$note = $n > 0
					/* translators: %d: quantity in stock */
					? sprintf( _n( '%d in stock', '%d in stock', $n, 'evolve-core' ), $n )
					: __( 'out of stock', 'evolve-core' );
				// Sold out: still listed, but can't be picked.
				$open = $n > 0 ? $m[1] : preg_replace( '#>$#', ' disabled="disabled">', $m[1] );
				return $open . $m[3] . ' — ' . esc_html( $note ) . $m[4];
			},
			$html
		);
	}
}
