<?php
namespace EvolveAIVapeMatch;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class REST_API {

	public function register() : void {
		add_action( 'rest_api_init', [ $this, 'routes' ] );
	}

	public function routes() : void {
		register_rest_route( 'evolve-ai-vape-match/v1', '/recommend', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'recommend' ],
			'permission_callback' => [ $this, 'permission' ],
		] );
	}

	/**
	 * Allow requests that carry a valid wp_rest nonce. wp_localize_script ships
	 * one to the front-end; logged-out shoppers therefore still pass.
	 */
	public function permission( \WP_REST_Request $req ) : bool {
		$nonce = $req->get_header( 'X-WP-Nonce' );
		if ( $nonce && wp_verify_nonce( $nonce, 'wp_rest' ) ) { return true; }
		return false;
	}

	public function recommend( \WP_REST_Request $req ) : \WP_REST_Response {
		$params = (array) ( $req->get_json_params() ?: $req->get_params() );

		// Age gate is non-negotiable.
		if ( empty( $params['age_confirmed'] ) ) {
			return new \WP_REST_Response( [
				'error' => __( 'Age confirmation is required.', 'evolve-ai-vape-match' ),
			], 403 );
		}

		$answers = $this->sanitize_answers( $params );

		// 1. Rule-based candidate pools — primary + accessory (strictly separate).
		$recommender = new Recommender();
		$primary     = $recommender->get_candidates( $answers, 60 );
		$setup       = $recommender->get_setup_candidates( $answers, 40 );

		if ( empty( $primary ) ) {
			return new \WP_REST_Response( [
				'source'         => 'none',
				'primary'        => null,
				'flavor_matches' => [],
				'setup'          => [],
				'message'        => __( 'No in-stock products matched. Try widening your filters.', 'evolve-ai-vape-match' ),
			], 200 );
		}

		// 2. Try AI rerank when configured + enabled.
		$settings   = (array) get_option( 'evolve_aivm_settings', [] );
		$ai_enabled = ! empty( $settings['ai_explanations'] );
		$ai_result  = null;

		if ( $ai_enabled ) {
			$ai_result = ( new OpenAI() )->rerank( $answers, $primary, $setup, 8 );
		}

		if ( $ai_result && $ai_result['primary'] ) {
			$ai_result['source']  = 'ai';
			$ai_result['answers'] = $answers;
			$ai_result['primary'] = $this->add_cart_data( $ai_result['primary'] );
			$ai_result['flavor_matches'] = array_map( [ $this, 'add_cart_data' ], $ai_result['flavor_matches'] );
			$ai_result['setup']          = array_map( [ $this, 'add_cart_data' ], $ai_result['setup'] );
			return new \WP_REST_Response( $ai_result, 200 );
		}

		// 3. Fallback (or when AI disabled): use rule-based ranking + generic copy.
		if ( ! empty( $settings['fallback_enabled'] ) || ! $ai_enabled ) {
			$out = $this->build_fallback( $primary, $setup );
			$out['source']  = $ai_enabled ? 'fallback' : 'rule-based';
			$out['answers'] = $answers;
			return new \WP_REST_Response( $out, 200 );
		}

		return new \WP_REST_Response( [
			'error' => __( 'AI is unavailable and fallback is disabled.', 'evolve-ai-vape-match' ),
		], 502 );
	}

	private function build_fallback( array $primary_pool, array $setup_pool ) : array {
		$primary_data = $primary_pool[0] ?? null;
		$primary = $primary_data ? array_merge( $primary_data, [
			'match_percent' => $primary_data['match_score'],
			'reason'        => __( 'Best overall match based on your preferences.', 'evolve-ai-vape-match' ),
			'label'         => __( 'Your Perfect Match', 'evolve-ai-vape-match' ),
		] ) : null;
		if ( $primary ) { $primary = $this->add_cart_data( $primary ); }

		// "flavor_matches" — slices 1..6 of the primary pool.
		$labels = [ 'Same Flavor Type', 'Sweeter Pick', 'More Ice', 'Smoother Hit', 'Popular Choice', 'Best Value' ];
		$flavors = [];
		foreach ( array_slice( $primary_pool, 1, 6 ) as $i => $c ) {
			$flavors[] = $this->add_cart_data( array_merge( $c, [
				'match_percent' => (int) $c['match_score'],
				'reason'        => __( 'Strong match on your flavor and intensity preferences.', 'evolve-ai-vape-match' ),
				'label'         => __( $labels[ $i ] ?? 'Flavor Match', 'evolve-ai-vape-match' ),
			] ) );
		}

		// "Setup" — STRICTLY from the accessory pool. Empty pool → empty setup.
		$setup = [];
		foreach ( $setup_pool as $c ) {
			$dt    = $c['meta']['device_type'] ?? '';
			$label = $this->setup_label_for_device( $dt, $c['name'] );
			if ( ! $label ) { continue; } // unknown device → don't fake-label
			$setup[] = $this->add_cart_data( array_merge( $c, [
				'label'  => $label,
				'reason' => __( 'Pairs well with your main pick.', 'evolve-ai-vape-match' ),
			] ) );
			if ( count( $setup ) >= 3 ) { break; }
		}

		return [
			'primary'        => $primary,
			'flavor_matches' => $flavors,
			'setup'          => $setup,
		];
	}

	/**
	 * Authoritative setup label — only true accessories get one. Anything else
	 * returns '' so the caller drops the row entirely (never mislabels a kit).
	 */
	private function setup_label_for_device( string $device, string $name = '' ) : string {
		$lc = strtolower( $name );
		if ( $device === 'pod' )       { return __( 'Compatible Pods',   'evolve-ai-vape-match' ); }
		if ( $device === 'coil' )      { return __( 'Replacement Coils', 'evolve-ai-vape-match' ); }
		if ( $device === 'accessory' ) {
			if ( strpos( $lc, 'charger' ) !== false ) { return __( 'Charger', 'evolve-ai-vape-match' ); }
			if ( strpos( $lc, 'battery' ) !== false ) { return __( 'Battery', 'evolve-ai-vape-match' ); }
			return __( 'Accessory', 'evolve-ai-vape-match' );
		}
		if ( $device === 'vape-juice' ) { return __( 'Refill Juice', 'evolve-ai-vape-match' ); }
		return ''; // not a setup item
	}

	/**
	 * Adds Add-to-Cart URL + variability flag for the front-end.
	 */
	private function add_cart_data( ?array $p ) : ?array {
		if ( ! $p ) { return null; }
		$p['add_to_cart_url'] = wc_get_cart_url() ? add_query_arg( [ 'add-to-cart' => $p['id'] ], wc_get_cart_url() ) : '';
		// Simple products → AJAX add-to-cart URL; variable → push them to product page.
		if ( empty( $p['is_variable'] ) ) {
			$p['ajax_add_to_cart'] = true;
			$p['add_to_cart_url'] = '?add-to-cart=' . $p['id'];
		} else {
			$p['ajax_add_to_cart'] = false;
			$p['add_to_cart_url'] = $p['permalink'];
		}
		return $p;
	}

	/**
	 * Sanitize every quiz answer to known shapes. Drop unknown keys entirely.
	 */
	public function sanitize_answers( array $in ) : array {
		$out = [];
		$out['experience']      = in_array( $in['experience'] ?? '', [ 'beginner', 'intermediate', 'advanced' ], true ) ? $in['experience'] : '';
		$out['product_type']    = in_array( $in['product_type'] ?? '', [ 'disposable', 'pod-system', 'vape-juice', 'mod-device', 'not-sure' ], true ) ? $in['product_type'] : '';
		$out['also_juice']      = in_array( $in['also_juice']   ?? '', [ 'yes', 'no' ], true ) ? $in['also_juice'] : '';
		$out['flavor_family']   = in_array( $in['flavor_family'] ?? '', array_keys( Product_Meta::flavor_family_choices() ), true ) ? $in['flavor_family'] : '';
		// Accept array (multi-select) OR comma-separated string. Store as comma-list internally.
		if ( isset( $in['flavor_specific'] ) ) {
			if ( is_array( $in['flavor_specific'] ) ) {
				$cleaned = array_map( 'sanitize_text_field', wp_unslash( $in['flavor_specific'] ) );
				$cleaned = array_filter( array_map( 'trim', $cleaned ) );
				$out['flavor_specific'] = implode( ', ', array_values( array_unique( $cleaned ) ) );
			} else {
				$out['flavor_specific'] = sanitize_text_field( (string) $in['flavor_specific'] );
			}
		} else {
			$out['flavor_specific'] = '';
		}
		$out['sweetness_level'] = isset( $in['sweetness_level'] ) && $in['sweetness_level'] !== '' ? max( 1, min( 10, (int) $in['sweetness_level'] ) ) : '';
		$out['cooling_level']   = isset( $in['cooling_level'] )   && $in['cooling_level']   !== '' ? max( 1, min( 10, (int) $in['cooling_level'] ) )   : '';
		$out['throat_hit']      = in_array( $in['throat_hit'] ?? '', [ 'smooth', 'medium', 'strong' ], true ) ? $in['throat_hit'] : '';
		$out['nicotine']        = isset( $in['nicotine'] ) ? sanitize_text_field( (string) $in['nicotine'] ) : '';
		// Disposables in the US are always 5% nicotine salt (50mg). If the
		// quiz UI skipped the nicotine step for disposable shoppers, fill it
		// here so the scoring rule still matches.
		if ( $out['product_type'] === 'disposable' && $out['nicotine'] === '' ) {
			$out['nicotine'] = '50';
		}
		$out['budget_min']      = isset( $in['budget_min'] ) ? max( 0, (float) $in['budget_min'] ) : '';
		$out['budget_max']      = isset( $in['budget_max'] ) ? max( 0, (float) $in['budget_max'] ) : '';
		$out['brand']           = isset( $in['brand'] ) ? sanitize_text_field( (string) $in['brand'] ) : '';
		return $out;
	}
}
