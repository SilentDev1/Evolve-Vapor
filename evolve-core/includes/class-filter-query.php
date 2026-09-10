<?php
/**
 * Server-side filter handler.
 *
 * - Modifies the main product archive query via `pre_get_posts` so URL-driven
 *   filtering (no-JS fallback) works out of the box.
 * - Exposes an AJAX endpoint `evolve_filter_products` that re-runs the same
 *   filtered query and returns the inner HTML of a standard WooCommerce
 *   `ul.products` for the shop-filter widget's JS to swap in.
 */
namespace Evolve_Core;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Filter_Query {

	public function __construct() {
		add_action( 'pre_get_posts',                       [ $this, 'modify_main_query' ] );
		add_action( 'wp_ajax_evolve_filter_products',      [ $this, 'ajax' ] );
		add_action( 'wp_ajax_nopriv_evolve_filter_products', [ $this, 'ajax' ] );
	}

	/* ----------------------------------------------------------------- */

	public function modify_main_query( $q ) {
		if ( is_admin() || ! $q->is_main_query() ) { return; }
		if ( ! is_post_type_archive( 'product' ) && ! is_tax( 'product_cat' ) && ! is_tax( 'product_tag' ) ) { return; }

		// Strip zero / empty price params so WooCommerce's own price-
		// filter (posts_clauses) doesn't interpret 0→0 as "free only".
		foreach ( [ 'min_price', 'max_price' ] as $pk ) {
			if ( isset( $_GET[ $pk ] ) && ( $_GET[ $pk ] === '' || $_GET[ $pk ] === '0' || (float) $_GET[ $pk ] <= 0 ) ) {
				unset( $_GET[ $pk ] );
				unset( $_REQUEST[ $pk ] );
			}
		}

		$g = wp_unslash( $_GET );

		// When our AJAX-driven filter params are in the URL (e.g. after
		// a browser refresh), the requested paged value may exceed the
		// filtered result count — which WooCommerce turns into a 404.
		// Force page 1 so the Elementor template renders; the front-end
		// JS will immediately AJAX-fetch the correct page.
		$has_filters = ! empty( $g['evf_cat'] ) || ! empty( $g['evf_q'] )
			|| ! empty( $g['evf_rating'] ) || ! empty( $g['on_sale'] )
			|| ! empty( $g['in_stock'] ) || ! empty( $g['attr'] );
		if ( $has_filters ) {
			$q->set( 'paged', 1 );
		}

		$this->apply( $q );
	}

	public function apply( $q ) {
		$g = wp_unslash( $_GET );

		/* Keyword search */
		if ( ! empty( $g['evf_q'] ) ) {
			$q->set( 's', sanitize_text_field( $g['evf_q'] ) );
		}

		$tax = (array) $q->get( 'tax_query' );

		/* Categories (multi-select) — uses evf_cat to avoid clashing with
		   WooCommerce's registered product_cat taxonomy query-var. */
		if ( ! empty( $g['evf_cat'] ) ) {
			$tax[] = [
				'taxonomy' => 'product_cat',
				'field'    => 'slug',
				'terms'    => array_map( 'sanitize_title', (array) $g['evf_cat'] ),
				'operator' => 'IN',
			];
		}

		/* Attribute taxonomies — keyed by taxonomy slug */
		if ( ! empty( $g['attr'] ) && is_array( $g['attr'] ) ) {
			foreach ( $g['attr'] as $tax_slug => $values ) {
				if ( ! taxonomy_exists( $tax_slug ) ) { continue; }
				$tax[] = [
					'taxonomy' => $tax_slug,
					'field'    => 'slug',
					'terms'    => array_map( 'sanitize_title', (array) $values ),
					'operator' => 'IN',
				];
			}
		}

		/* Rating filter (min star) — uses evf_rating to avoid clashing with
		   WooCommerce's rating_filter query-var. */
		if ( ! empty( $g['evf_rating'] ) ) {
			$min = max( array_map( 'intval', (array) $g['evf_rating'] ) );
			$meta = (array) $q->get( 'meta_query' );
			$meta[] = [
				'key'     => '_wc_average_rating',
				'value'   => $min,
				'compare' => '>=',
				'type'    => 'DECIMAL(10,2)',
			];
			$q->set( 'meta_query', $meta );
		}

		/* On sale — use WC's helper to grab IDs */
		if ( ! empty( $g['on_sale'] ) ) {
			$ids = function_exists( 'wc_get_product_ids_on_sale' ) ? wc_get_product_ids_on_sale() : [];
			$ids = $ids ?: [ 0 ];
			$q->set( 'post__in', array_unique( array_merge( (array) $q->get( 'post__in' ), $ids ) ) );
		}

		/* In stock */
		if ( ! empty( $g['in_stock'] ) ) {
			$meta = (array) $q->get( 'meta_query' );
			$meta[] = [
				'key'     => '_stock_status',
				'value'   => 'instock',
			];
			$q->set( 'meta_query', $meta );
		}

		/* Price — WC's WC_Query already handles min_price/max_price for the main loop.
		   For our AJAX path we replicate it below. */
		if ( ! empty( $g['min_price'] ) || ! empty( $g['max_price'] ) ) {
			$min_p = isset( $g['min_price'] ) ? (float) $g['min_price'] : 0;
			$max_p = isset( $g['max_price'] ) ? (float) $g['max_price'] : PHP_INT_MAX;
			$meta = (array) $q->get( 'meta_query' );
			$meta[] = [
				'key'     => '_price',
				'value'   => [ $min_p, $max_p ],
				'compare' => 'BETWEEN',
				'type'    => 'DECIMAL(10,2)',
			];
			$q->set( 'meta_query', $meta );
		}

		if ( ! empty( $tax ) ) {
			$q->set( 'tax_query', $tax );
		}
	}

	/* ----------------------------------------------------------------- */

	public function ajax() {
		check_ajax_referer( 'evolve_filter', 'nonce' );

		// Hydrate $_GET so apply() picks up the filter state.
		parse_str( wp_unslash( $_POST['query'] ?? '' ), $parsed );
		$_GET = array_merge( $_GET, $parsed );

		// Use WooCommerce's per-page setting (child theme may override via
		// loop_shop_per_page filter) so AJAX returns the same count as SSR.
		$per_page = (int) ( $parsed['per_page'] ?? apply_filters( 'loop_shop_per_page', get_option( 'posts_per_page', 12 ) ) );
		$columns  = (int) ( $parsed['columns']  ?? apply_filters( 'loop_shop_columns', 4 ) );

		$args = [
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => max( 1, (int) ( $parsed['paged'] ?? 1 ) ),
		];

		// Match WooCommerce's default catalog ordering so AJAX results
		// appear in the same order as the initial server-rendered page.
		if ( function_exists( 'WC' ) && isset( WC()->query ) ) {
			$ordering = WC()->query->get_catalog_ordering_args();
			$args['orderby'] = $ordering['orderby'];
			$args['order']   = $ordering['order'];
			if ( ! empty( $ordering['meta_key'] ) ) {
				$args['meta_key'] = $ordering['meta_key'];
			}
		}

		$query = new \WP_Query( $args );
		// We just call apply() with a real WP_Query so set() works.
		$this->apply( $query );

		// Exclude products hidden from the catalog at the query level
		// (rather than post-loop is_visible() which silently drops rows
		// and breaks pagination counts).
		if ( function_exists( 'wc_get_product_visibility_term_ids' ) ) {
			$vis  = wc_get_product_visibility_term_ids();
			$tax  = (array) $query->get( 'tax_query' );
			if ( ! empty( $vis['exclude-from-catalog'] ) ) {
				$tax[] = [
					'taxonomy' => 'product_visibility',
					'field'    => 'term_taxonomy_id',
					'terms'    => [ $vis['exclude-from-catalog'] ],
					'operator' => 'NOT IN',
				];
			}
			$query->set( 'tax_query', $tax );
		}

		// Re-run with our modifications.
		$query = new \WP_Query( $query->query_vars );

		ob_start();

		if ( $query->have_posts() ) {
			wc_set_loop_prop( 'columns',    $columns );
			wc_set_loop_prop( 'is_paginated', true );
			wc_set_loop_prop( 'total',        $query->found_posts );
			wc_set_loop_prop( 'total_pages',  $query->max_num_pages );
			wc_set_loop_prop( 'current_page', $args['paged'] );
			wc_set_loop_prop( 'per_page',     $args['posts_per_page'] );

			woocommerce_product_loop_start();
			while ( $query->have_posts() ) {
				$query->the_post();
				wc_get_template_part( 'content', 'product' );
			}
			woocommerce_product_loop_end();

			/* -------------------------------------------------------------
			 * Pagination — woocommerce_pagination() bases its links on
			 * home_url($wp->request), which during admin-ajax.php would
			 * produce /wp-admin/admin-ajax.php?paged=2 links (blank "0"
			 * page on click). Override the base + preserve the filter
			 * state in the URL so a click on page 2 lands on
			 * /shop/?paged=2&min_price=… etc.
			 * --------------------------------------------------------- */
			$shop_url     = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' );
			$preserve     = $this->filter_state_for_url( $parsed );
			// Use a sentinel number so add_query_arg doesn't double-encode
			// the %#% placeholder that paginate_links() needs.
			$base_url     = add_query_arg( array_merge( [ 'paged' => '999999999' ], $preserve ), $shop_url );
			$base_url     = str_replace( '999999999', '%#%', $base_url );

			$pag_filter = function ( $args ) use ( $base_url ) {
				$args['base']      = esc_url_raw( $base_url );
				$args['format']    = '';
				$args['add_args']  = false;
				return $args;
			};
			add_filter( 'woocommerce_pagination_args', $pag_filter );

			global $wp_query;
			$saved_wp_query = $wp_query;
			$wp_query       = $query;        // so woocommerce_pagination() sees the right query
			$query->is_singular = false;
			$query->is_archive  = true;

			woocommerce_pagination();

			$wp_query = $saved_wp_query;
			remove_filter( 'woocommerce_pagination_args', $pag_filter );
		} else {
			echo '<p class="evolve-filter__empty">No products match these filters. <a href="#" data-evolve-filter-clear>Clear filters</a></p>';
		}

		wp_reset_postdata();

		wp_send_json_success( [
			'html'        => ob_get_clean(),
			'found'       => $query->found_posts,
			'total_pages' => $query->max_num_pages,
		] );
	}

	/**
	 * Reduces $parsed to just the filter params that should be preserved
	 * across pagination clicks.
	 */
	private function filter_state_for_url( $parsed ) {
		$keep = [];
		foreach ( [ 'evf_q', 'min_price', 'max_price', 'on_sale', 'in_stock' ] as $k ) {
			if ( ! empty( $parsed[ $k ] ) ) { $keep[ $k ] = is_array( $parsed[ $k ] ) ? $parsed[ $k ] : (string) $parsed[ $k ]; }
		}
		foreach ( [ 'evf_cat', 'evf_rating' ] as $k ) {
			if ( ! empty( $parsed[ $k ] ) ) { $keep[ $k ] = (array) $parsed[ $k ]; }
		}
		if ( ! empty( $parsed['attr'] ) && is_array( $parsed['attr'] ) ) {
			$keep['attr'] = $parsed['attr'];
		}
		return $keep;
	}
}
