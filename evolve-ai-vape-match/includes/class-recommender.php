<?php
namespace EvolveAIVapeMatch;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Rule-based recommender — queries WooCommerce for in-stock published
 * products and scores each one against the user's quiz answers.
 *
 * NEVER invents products. The AI tier consumes whatever this class returns.
 */
class Recommender {

	/**
	 * Returns the primary candidate list — products whose device_type matches
	 * the shopper's chosen product_type (or any product when not-sure).
	 *
	 * Setup/accessory products are queried separately via get_setup_candidates()
	 * so the AI can't mistakenly label a kit as "Compatible Pods" / "Charger" / etc.
	 *
	 * @return array<int,array> Scored candidates, highest first.
	 */
	public function get_candidates( array $answers, int $candidate_pool = 60 ) : array {
		$query_args = $this->build_query_args( $answers, $candidate_pool );
		$query      = new \WP_Query( $query_args );

		$candidates = [];
		foreach ( $query->posts as $post_id ) {
			$product = wc_get_product( (int) $post_id );
			if ( ! $product || ! $product->is_visible() || ! $product->is_in_stock() ) {
				continue;
			}
			$payload = $this->product_payload( $product );
			[ $score, $breakdown ] = $this->score( $payload, $answers );
			if ( $score <= 0 ) { continue; }
			$payload['match_score']     = $score;
			$payload['score_breakdown'] = $breakdown;
			$candidates[] = $payload;
		}
		wp_reset_postdata();
		usort( $candidates, static fn( $a, $b ) => $b['match_score'] <=> $a['match_score'] );
		return $candidates;
	}

	/**
	 * Returns the SETUP candidate list — only products whose device_type is one of
	 * pod, coil, accessory, or vape-juice. The recommender returns these separately
	 * so the AI's "setup" array can only ever contain genuine accessories.
	 */
	public function get_setup_candidates( array $answers, int $candidate_pool = 40 ) : array {
		$args = [
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => $candidate_pool,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_query'     => [
				'relation' => 'AND',
				[ 'key' => '_stock_status', 'value' => 'instock' ],
				[ 'key' => '_price', 'value' => '', 'compare' => '!=' ],
				[
					'key'     => '_evolve_aivm_device_type',
					'value'   => [ 'pod', 'coil', 'accessory', 'vape-juice' ],
					'compare' => 'IN',
				],
			],
			'tax_query' => [
				[
					'taxonomy' => 'product_visibility',
					'field'    => 'name',
					'terms'    => [ 'exclude-from-search', 'exclude-from-catalog' ],
					'operator' => 'NOT IN',
				],
			],
		];

		$query = new \WP_Query( $args );
		$out   = [];
		foreach ( $query->posts as $post_id ) {
			$product = wc_get_product( (int) $post_id );
			if ( ! $product || ! $product->is_visible() || ! $product->is_in_stock() ) { continue; }
			$payload = $this->product_payload( $product );
			$out[]   = $payload;
		}
		wp_reset_postdata();
		return $out;
	}

	private function build_query_args( array $answers, int $pool ) : array {
		$args = [
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => $pool,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_query'     => [
				'relation' => 'AND',
				[ 'key' => '_stock_status', 'value' => 'instock' ],
				[ 'key' => '_price', 'value' => '', 'compare' => '!=' ],
			],
			'tax_query'      => [
				[
					'taxonomy' => 'product_visibility',
					'field'    => 'name',
					'terms'    => [ 'exclude-from-search', 'exclude-from-catalog' ],
					'operator' => 'NOT IN',
				],
			],
		];

		// Cap by budget if provided.
		if ( ! empty( $answers['budget_max'] ) ) {
			$args['meta_query'][] = [
				'key'     => '_price',
				'value'   => (float) $answers['budget_max'],
				'compare' => '<=',
				'type'    => 'DECIMAL(10,2)',
			];
		}
		if ( ! empty( $answers['budget_min'] ) ) {
			$args['meta_query'][] = [
				'key'     => '_price',
				'value'   => (float) $answers['budget_min'],
				'compare' => '>=',
				'type'    => 'DECIMAL(10,2)',
			];
		}

		// Hard-narrow by device type if requested (skip "not sure").
		// If the shopper picked mod-device or pod-system AND also said yes
		// to "looking for juice too", widen the primary pool to include
		// vape-juice so juice products show up in "flavor matches" instead
		// of being relegated to the setup section.
		if ( ! empty( $answers['product_type'] ) && $answers['product_type'] !== 'not-sure' ) {
			$wants_juice = ! empty( $answers['also_juice'] ) && $answers['also_juice'] === 'yes';
			if ( $wants_juice && in_array( $answers['product_type'], [ 'mod-device', 'pod-system' ], true ) ) {
				$args['meta_query'][] = [
					'key'     => '_evolve_aivm_device_type',
					'value'   => [ sanitize_key( $answers['product_type'] ), 'vape-juice' ],
					'compare' => 'IN',
				];
			} else {
				$args['meta_query'][] = [
					'key'     => '_evolve_aivm_device_type',
					'value'   => sanitize_key( $answers['product_type'] ),
					'compare' => '=',
				];
			}
		}

		return $args;
	}

	private function product_payload( \WC_Product $p ) : array {
		$id = $p->get_id();
		$image_id  = (int) $p->get_image_id();
		$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'medium_large' ) : wc_placeholder_img_src( 'medium_large' );

		$meta = [];
		foreach ( Product_Meta::FIELDS as $f ) {
			$meta[ $f ] = get_post_meta( $id, '_evolve_aivm_' . $f, true );
		}

		// Pull product categories + tags as supplementary signal.
		$cats = wp_get_post_terms( $id, 'product_cat', [ 'fields' => 'names' ] );
		$tags = wp_get_post_terms( $id, 'product_tag', [ 'fields' => 'names' ] );

		return [
			'id'         => $id,
			'name'       => $p->get_name(),
			'permalink'  => get_permalink( $id ),
			'price'      => (float) $p->get_price(),
			'price_html' => $p->get_price_html(),
			'image'      => $image_url ?: '',
			'on_sale'    => $p->is_on_sale(),
			'is_variable'=> $p->is_type( 'variable' ),
			'meta'       => $meta,
			'cats'       => is_wp_error( $cats ) ? [] : array_values( $cats ),
			'tags'       => is_wp_error( $tags ) ? [] : array_values( $tags ),
			'sku'        => $p->get_sku(),
			'short_desc' => wp_strip_all_tags( $p->get_short_description() ),
		];
	}

	/**
	 * Scoring rubric — additive points per matched dimension, weighted by
	 * how strongly the dimension correlates with shopper satisfaction.
	 *
	 * Max raw score is 100 (we cap there for clean % display).
	 */
	private function score( array $product, array $a ) : array {
		$m = $product['meta'];
		$score = 0;
		$break = [];

		// 1. Flavor family (15 pts) — biggest single signal for taste alignment.
		if ( ! empty( $a['flavor_family'] ) && ! empty( $m['flavor_family'] ) && $a['flavor_family'] === $m['flavor_family'] ) {
			$score += 15; $break[] = 'flavor-family +15';
		}

		// 2. Specific flavor keyword(s) — multi-select aware.
		//    Each matched flavor in the user's list adds 4 pts (cap 12).
		if ( ! empty( $a['flavor_specific'] ) ) {
			$flavors = array_filter( array_map( 'trim', explode( ',', strtolower( $a['flavor_specific'] ) ) ) );
			$hay     = strtolower( implode( ' ', array_filter( [
				$product['name'], $m['flavor_notes'], $m['ai_keywords'], implode( ' ', $product['tags'] ), $product['short_desc'],
			] ) ) );
			$hits = 0;
			if ( $hay ) {
				foreach ( $flavors as $f ) {
					if ( mb_strlen( $f ) > 1 && strpos( $hay, $f ) !== false ) { $hits++; }
				}
			}
			if ( $hits > 0 ) {
				$pts = min( 12, $hits * 4 );
				$score += $pts; $break[] = "flavor-keyword +$pts ($hits matched)";
			}
		}

		// 3. Sweetness proximity (up to 10 pts) — penalize 1pt per delta point.
		if ( isset( $a['sweetness_level'] ) && $a['sweetness_level'] !== '' && $m['sweetness_level'] !== '' ) {
			$delta = abs( (int) $a['sweetness_level'] - (int) $m['sweetness_level'] );
			$pts   = max( 0, 10 - $delta );
			$score += $pts; $break[] = "sweetness +$pts";
		}

		// 4. Cooling/ice proximity (up to 10 pts).
		if ( isset( $a['cooling_level'] ) && $a['cooling_level'] !== '' && $m['cooling_level'] !== '' ) {
			$delta = abs( (int) $a['cooling_level'] - (int) $m['cooling_level'] );
			$pts   = max( 0, 10 - $delta );
			$score += $pts; $break[] = "cooling +$pts";
		}

		// 5. Throat hit (8 pts).
		if ( ! empty( $a['throat_hit'] ) && ! empty( $m['throat_hit'] ) && $a['throat_hit'] === $m['throat_hit'] ) {
			$score += 8; $break[] = 'throat-hit +8';
		}

		// 6. Nicotine strength (10 pts exact / 6 pts close).
		if ( ! empty( $a['nicotine'] ) && ! empty( $m['nicotine_strength'] ) ) {
			$req  = (int) preg_replace( '/[^0-9]/', '', $a['nicotine'] );
			$have = (int) preg_replace( '/[^0-9]/', '', $m['nicotine_strength'] );
			if ( $req === $have ) {
				$score += 10; $break[] = 'nicotine-exact +10';
			} elseif ( $have && abs( $req - $have ) <= 10 ) {
				$score += 6;  $break[] = 'nicotine-close +6';
			}
		}

		// 7. Device type already pre-filtered in WP_Query (5 pts bonus when matched).
		if ( ! empty( $a['product_type'] ) && $a['product_type'] !== 'not-sure' && $m['device_type'] === $a['product_type'] ) {
			$score += 5; $break[] = 'device-type +5';
		}

		// 8. Experience level alignment (10 pts).
		if ( ! empty( $a['experience'] ) && ! empty( $m['beginner_friendly'] ) ) {
			if ( $a['experience'] === 'beginner' && $m['beginner_friendly'] === 'yes' ) { $score += 10; $break[] = 'beginner-fit +10'; }
			if ( $a['experience'] === 'advanced' && $m['beginner_friendly'] === 'no' )  { $score += 6;  $break[] = 'advanced-fit +6'; }
			if ( $a['experience'] === 'intermediate' )                                  { $score += 3;  $break[] = 'intermediate +3'; }
		}

		// 9. Brand preference (8 pts).
		if ( ! empty( $a['brand'] ) && ! empty( $m['brand'] ) && stripos( $m['brand'], $a['brand'] ) !== false ) {
			$score += 8; $break[] = 'brand +8';
		}

		// 10. AI keywords + tags semantic overlap (up to 12 pts).
		$kw = strtolower( $m['ai_keywords'] . ' ' . implode( ' ', $product['tags'] ) . ' ' . implode( ' ', $product['cats'] ) );
		$signals = array_filter( [ $a['flavor_family'] ?? '', $a['flavor_specific'] ?? '', $a['throat_hit'] ?? '' ] );
		$hits = 0;
		foreach ( $signals as $s ) {
			if ( $s && strpos( $kw, strtolower( (string) $s ) ) !== false ) { $hits++; }
		}
		if ( $hits ) {
			$pts = min( 12, $hits * 4 );
			$score += $pts; $break[] = "tag-overlap +$pts";
		}

		// Cap to 100 for clean % display.
		$score = min( 100, $score );
		return [ $score, $break ];
	}
}
