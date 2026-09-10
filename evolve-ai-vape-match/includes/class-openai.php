<?php
namespace EvolveAIVapeMatch;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * OpenAI thin client.
 *
 * Re-ranks a pre-filtered candidate list and produces short per-product
 * explanations + category labels. The prompt is locked down so the model
 * CANNOT invent products — it has to pick from the candidate IDs we send.
 */
class OpenAI {

	const ENDPOINT = 'https://api.openai.com/v1/chat/completions';

	private array $settings;

	public function __construct() {
		$this->settings = (array) get_option( 'evolve_aivm_settings', [] );
	}

	public function is_configured() : bool {
		return ! empty( $this->settings['api_key'] );
	}

	/**
	 * @param array $answers           Quiz answers
	 * @param array $primary_candidates Output of Recommender::get_candidates()
	 * @param array $setup_candidates  Output of Recommender::get_setup_candidates() — pods/coils/chargers/juice only
	 * @param int   $top_n             Final number of picks the AI must return
	 * @return array|null  ['primary' => match, 'flavor_matches' => [...], 'setup' => [...]]  or null on failure
	 */
	public function rerank( array $answers, array $primary_candidates, array $setup_candidates = [], int $top_n = 8 ) : ?array {
		if ( ! $this->is_configured() || empty( $primary_candidates ) ) { return null; }

		$max  = max( 4, min( 40, (int) ( $this->settings['max_products_to_ai'] ?? 12 ) ) );
		$slim = array_slice( array_map( [ $this, 'compact_payload' ], $primary_candidates ), 0, $max );

		// Setup pool is independent and never overlaps with primary — strictly accessories.
		$slim_setup = array_slice(
			array_map( [ $this, 'compact_payload' ], $setup_candidates ),
			0,
			max( 4, min( 20, $max ) )
		);

		$model  = sanitize_text_field( $this->settings['model'] ?? 'gpt-4o-mini' );
		$system = $this->system_prompt();
		$user   = wp_json_encode( [
			'shopper'           => $answers,
			'primary_products'  => $slim,
			'setup_products'    => $slim_setup,
			'instructions'      => sprintf(
				'Return %d picks total: 1 "primary" (best overall, MUST come from primary_products), up to 6 "flavor_matches" (from primary_products, variety of labels), and up to 3 "setup" items (MUST come from setup_products only — never pick a kit as a setup item).',
				$top_n
			),
		], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		$response = wp_remote_post( self::ENDPOINT, [
			'timeout' => 25,
			'headers' => [
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $this->settings['api_key'],
			],
			'body' => wp_json_encode( [
				'model'        => $model,
				'temperature'  => 0.2,
				'response_format' => [ 'type' => 'json_object' ],
				'messages'     => [
					[ 'role' => 'system', 'content' => $system ],
					[ 'role' => 'user',   'content' => $user ],
				],
			] ),
		] );

		if ( is_wp_error( $response ) ) { return null; }
		$code = wp_remote_retrieve_response_code( $response );
		if ( $code >= 400 ) { return null; }

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$content = $body['choices'][0]['message']['content'] ?? '';
		if ( ! $content ) { return null; }

		$parsed = json_decode( $content, true );
		if ( ! is_array( $parsed ) ) { return null; }

		return $this->validate_against_candidates( $parsed, $primary_candidates, $setup_candidates );
	}

	/**
	 * Compact representation we send to the model. Only the fields it needs
	 * to reason about — no images, no permalinks, no price_html. Saves tokens.
	 */
	private function compact_payload( array $p ) : array {
		return [
			'id'    => $p['id'],
			'name'  => $p['name'],
			'price' => $p['price'],
			'brand' => $p['meta']['brand']             ?? '',
			'device'=> $p['meta']['device_type']       ?? '',
			'beginner'   => $p['meta']['beginner_friendly'] ?? '',
			'flavor_fam' => $p['meta']['flavor_family']     ?? '',
			'flavor_notes' => $p['meta']['flavor_notes']    ?? '',
			'sweet' => $p['meta']['sweetness_level']   ?? '',
			'cool'  => $p['meta']['cooling_level']     ?? '',
			'throat'=> $p['meta']['throat_hit']        ?? '',
			'nic'   => $p['meta']['nicotine_strength'] ?? '',
			'compat'=> $p['meta']['compatibility']     ?? '',
			'kw'    => $p['meta']['ai_keywords']       ?? '',
			'tags'  => $p['tags'],
			'cats'  => $p['cats'],
			'rule_score' => $p['match_score'] ?? null,
		];
	}

	private function system_prompt() : string {
		return implode( ' ', [
			'You are a vape product matcher for a WooCommerce store.',
			'You will receive TWO separate product arrays plus a "shopper" object:',
			'  - `primary_products`: kits / devices / disposables / juice the shopper is browsing for. Pick BOTH "primary" and "flavor_matches" from this array.',
			'  - `setup_products`: ONLY accessories — replacement pods, coils, chargers, batteries, refill juice. Pick "setup" items ONLY from this array.',
			'STRICT RULES:',
			'(1) Every output `id` MUST appear in the array that owns it. NEVER pick a kit as a setup item. NEVER invent products.',
			'(2) Output JSON only. No prose, no markdown.',
			'(3) Schema: { "primary": { "id": <int>, "match_percent": <0-100>, "reason": "<one short sentence>" },',
			'             "flavor_matches": [ { "id": <int>, "match_percent": <int>, "label": "<one of: Sweeter Pick, More Ice, Less Ice, Same Flavor Type, Best Value, Popular Choice, Smoother Hit, Bolder Hit>", "reason": "<one short sentence>" }, ... ],',
			'             "setup": [ { "id": <int>, "label": "<label>", "reason": "<one short sentence>" }, ... ] }',
			'(4) For each "setup" item, the `label` MUST match the product\'s `device` field exactly:',
			'      device="pod"        → label "Compatible Pods"',
			'      device="coil"       → label "Replacement Coils"',
			'      device="accessory"  → label "Charger / Battery"',
			'      device="vape-juice" → label "Refill Juice"',
			'    Do NOT invent labels. Do NOT label a kit ("pod-system" or "mod-device") as any of those.',
			'(5) If `setup_products` is empty OR no setup items are appropriate, return setup: [].',
			'(6) Keep every "reason" under 18 words. Friendly retail-assistant tone.',
			'(7) Pick "flavor_matches" labels meaningfully — do not paste the same label everywhere.',
		] );
	}

	/**
	 * Ask the model to fill the "AI Vape Match Data" fields by reading a
	 * single product's title + description + categories + tags.
	 *
	 * Returns a sanitized associative array keyed by Product_Meta::FIELDS
	 * (minus values the model couldn't infer with confidence). On any
	 * error returns null — the caller can show an error toast.
	 */
	public function auto_fill_from_product( array $product_payload ) : ?array {
		if ( ! $this->is_configured() ) { return null; }

		$model  = sanitize_text_field( $this->settings['model'] ?? 'gpt-4o-mini' );
		$system = $this->autofill_system_prompt();
		$user   = wp_json_encode( $product_payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		$response = wp_remote_post( self::ENDPOINT, [
			'timeout' => 25,
			'headers' => [
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $this->settings['api_key'],
			],
			'body' => wp_json_encode( [
				'model'           => $model,
				'temperature'     => 0.15,
				'response_format' => [ 'type' => 'json_object' ],
				'messages'        => [
					[ 'role' => 'system', 'content' => $system ],
					[ 'role' => 'user',   'content' => $user ],
				],
			] ),
		] );

		if ( is_wp_error( $response ) ) { return null; }
		if ( wp_remote_retrieve_response_code( $response ) >= 400 ) { return null; }

		$body    = json_decode( wp_remote_retrieve_body( $response ), true );
		$content = $body['choices'][0]['message']['content'] ?? '';
		if ( ! $content ) { return null; }

		$parsed = json_decode( $content, true );
		if ( ! is_array( $parsed ) ) { return null; }

		return $this->sanitize_autofill( $parsed );
	}

	private function autofill_system_prompt() : string {
		return implode( ' ', [
			'You are a vape product metadata extractor for a WooCommerce store.',
			'Read the product title, short description, long description, categories, and tags I provide.',
			'Return ONLY a JSON object — no prose, no markdown — with these keys, inferring values from the text:',
			'{',
				'"flavor_family":     "fruity" | "mint-ice" | "candy" | "dessert" | "tobacco" | "drink-inspired" | "",',
				'"flavor_notes":      "<short comma-separated tasting notes inferred from the description>",',
				'"sweetness_level":   <integer 1-10 OR null if not inferable>,',
				'"cooling_level":     <integer 1-10 OR null if not inferable>,',
				'"throat_hit":        "smooth" | "medium" | "strong" | "",',
				'"nicotine_strength": "<extracted mg value e.g. 50, 35, 0, or empty>",',
				'"device_type":       "disposable" | "pod-system" | "vape-juice" | "mod-device" | "pod" | "coil" | "accessory" | "",',
				'"beginner_friendly": "yes" | "no" | "",',
				'"brand":             "<inferred brand or empty>",',
				'"compatibility":     "<compatible pods/coils/devices if mentioned>",',
				'"ai_keywords":       "<5-10 short comma-separated semantic tags>"',
			'}',
			'STRICT RULES:',
			'(1) JSON only. (2) Use empty string or null for fields you cannot confidently infer. (3) Do not invent nicotine strengths or compatibility — only extract what is in the source text. (4) Be conservative on sweetness/cooling — infer from descriptor words ("icy", "menthol", "sweet candy", "subtle"), not from product names alone.',
		] );
	}

	/**
	 * Tight whitelist for everything the auto-fill returns. Drops unknown fields.
	 */
	private function sanitize_autofill( array $in ) : array {
		$out = [];
		$flavor_choices = array_keys( Product_Meta::flavor_family_choices() );
		$device_choices = array_keys( Product_Meta::device_type_choices() );

		if ( ! empty( $in['flavor_family'] ) && in_array( $in['flavor_family'], $flavor_choices, true ) ) {
			$out['flavor_family'] = $in['flavor_family'];
		}
		if ( ! empty( $in['flavor_notes'] ) )   { $out['flavor_notes']   = sanitize_text_field( (string) $in['flavor_notes'] ); }
		if ( isset( $in['sweetness_level'] ) && is_numeric( $in['sweetness_level'] ) ) {
			$out['sweetness_level'] = max( 1, min( 10, (int) $in['sweetness_level'] ) );
		}
		if ( isset( $in['cooling_level'] ) && is_numeric( $in['cooling_level'] ) ) {
			$out['cooling_level'] = max( 1, min( 10, (int) $in['cooling_level'] ) );
		}
		if ( ! empty( $in['throat_hit'] ) && in_array( $in['throat_hit'], [ 'smooth', 'medium', 'strong' ], true ) ) {
			$out['throat_hit'] = $in['throat_hit'];
		}
		if ( ! empty( $in['nicotine_strength'] ) )       { $out['nicotine_strength'] = sanitize_text_field( (string) $in['nicotine_strength'] ); }
		if ( ! empty( $in['device_type'] ) && in_array( $in['device_type'], $device_choices, true ) ) {
			$out['device_type'] = $in['device_type'];
		}
		if ( ! empty( $in['beginner_friendly'] ) && in_array( $in['beginner_friendly'], [ 'yes', 'no' ], true ) ) {
			$out['beginner_friendly'] = $in['beginner_friendly'];
		}
		if ( ! empty( $in['brand'] ) )         { $out['brand']         = sanitize_text_field( (string) $in['brand'] ); }
		if ( ! empty( $in['compatibility'] ) ) { $out['compatibility'] = sanitize_text_field( (string) $in['compatibility'] ); }
		if ( ! empty( $in['ai_keywords'] ) )   { $out['ai_keywords']   = sanitize_text_field( (string) $in['ai_keywords'] ); }
		return $out;
	}

	/**
	 * Defensive validation.
	 *  - Primary + flavor_matches must come from primary_candidates.
	 *  - Setup items must come from setup_candidates AND their label is
	 *    OVERWRITTEN with one we derive from the product's actual device_type
	 *    (so the AI can't mislabel a juice as "Charger").
	 *  - Setup items with no compatible device_type are dropped, not faked.
	 */
	private function validate_against_candidates( array $parsed, array $primary_candidates, array $setup_candidates ) : array {
		$primary_lookup = array_column( $primary_candidates, null, 'id' );
		$setup_lookup   = array_column( $setup_candidates,   null, 'id' );
		$out = [
			'primary'        => null,
			'flavor_matches' => [],
			'setup'          => [],
		];

		if ( ! empty( $parsed['primary']['id'] ) && isset( $primary_lookup[ (int) $parsed['primary']['id'] ] ) ) {
			$id = (int) $parsed['primary']['id'];
			$out['primary'] = array_merge( $primary_lookup[ $id ], [
				'match_percent' => max( 0, min( 100, (int) ( $parsed['primary']['match_percent'] ?? $primary_lookup[ $id ]['match_score'] ) ) ),
				'reason'        => isset( $parsed['primary']['reason'] ) ? sanitize_text_field( (string) $parsed['primary']['reason'] ) : '',
				'label'         => __( 'Your Perfect Match', 'evolve-ai-vape-match' ),
			] );
		}

		foreach ( (array) ( $parsed['flavor_matches'] ?? [] ) as $row ) {
			if ( empty( $row['id'] ) || ! isset( $primary_lookup[ (int) $row['id'] ] ) ) { continue; }
			$id = (int) $row['id'];
			$out['flavor_matches'][] = array_merge( $primary_lookup[ $id ], [
				'match_percent' => max( 0, min( 100, (int) ( $row['match_percent'] ?? $primary_lookup[ $id ]['match_score'] ) ) ),
				'reason'        => isset( $row['reason'] ) ? sanitize_text_field( (string) $row['reason'] ) : '',
				'label'         => isset( $row['label'] )  ? sanitize_text_field( (string) $row['label'] )  : __( 'Flavor Match', 'evolve-ai-vape-match' ),
			] );
		}

		foreach ( (array) ( $parsed['setup'] ?? [] ) as $row ) {
			if ( empty( $row['id'] ) || ! isset( $setup_lookup[ (int) $row['id'] ] ) ) { continue; }
			$id = (int) $row['id'];
			$product = $setup_lookup[ $id ];
			$device  = (string) ( $product['meta']['device_type'] ?? '' );

			// Derive the correct label from device_type — don't trust the AI's label.
			$label = $this->setup_label_for_device( $device, (string) ( $product['name'] ?? '' ) );
			if ( ! $label ) { continue; } // unknown / non-accessory device → drop entirely

			$out['setup'][] = array_merge( $product, [
				'reason' => isset( $row['reason'] ) ? sanitize_text_field( (string) $row['reason'] ) : '',
				'label'  => $label,
			] );
		}

		return $out;
	}

	/**
	 * Authoritative label derivation — only accessory device_types qualify.
	 */
	private function setup_label_for_device( string $device, string $name = '' ) : string {
		$lc = strtolower( $name );
		if ( $device === 'pod' )       { return __( 'Compatible Pods',  'evolve-ai-vape-match' ); }
		if ( $device === 'coil' )      { return __( 'Replacement Coils','evolve-ai-vape-match' ); }
		if ( $device === 'accessory' ) {
			if ( strpos( $lc, 'charger' ) !== false ) { return __( 'Charger',         'evolve-ai-vape-match' ); }
			if ( strpos( $lc, 'battery' ) !== false ) { return __( 'Battery',         'evolve-ai-vape-match' ); }
			return __( 'Accessory', 'evolve-ai-vape-match' );
		}
		if ( $device === 'vape-juice' ) { return __( 'Refill Juice', 'evolve-ai-vape-match' ); }
		return ''; // kit / pod-system / mod-device / disposable → not a setup item
	}
}
