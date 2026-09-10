<?php
namespace EvolveAIVapeMatch;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * "AI Vape Match Data" meta box on the WooCommerce product editor.
 * Every field is namespaced with `_evolve_aivm_` post meta.
 */
class Product_Meta {

	const NONCE = 'evolve_aivm_meta';

	const FIELDS = [
		'flavor_family',
		'flavor_notes',
		'sweetness_level',
		'cooling_level',
		'throat_hit',
		'nicotine_strength',
		'device_type',
		'beginner_friendly',
		'compatibility',
		'brand',
		'ai_keywords',
	];

	public function register() : void {
		add_action( 'add_meta_boxes',    [ $this, 'add_meta_box' ] );
		add_action( 'save_post_product', [ $this, 'save' ], 10, 2 );
		add_action( 'wp_ajax_evolve_aivm_autofill',          [ $this, 'ajax_autofill' ] );
		add_action( 'wp_ajax_evolve_aivm_bulk_scan',         [ $this, 'ajax_bulk_scan' ] );
		add_action( 'wp_ajax_evolve_aivm_bulk_process',      [ $this, 'ajax_bulk_process' ] );
	}

	public function add_meta_box() : void {
		add_meta_box(
			'evolve_aivm_meta',
			__( 'AI Vape Match Data', 'evolve-ai-vape-match' ),
			[ $this, 'render' ],
			'product',
			'side',
			'default'
		);
	}

	public function render( \WP_Post $post ) : void {
		wp_nonce_field( self::NONCE, self::NONCE . '_nonce' );

		$values = [];
		foreach ( self::FIELDS as $f ) {
			$values[ $f ] = get_post_meta( $post->ID, '_evolve_aivm_' . $f, true );
		}
		$ai_nonce = wp_create_nonce( 'evolve_aivm_autofill_' . $post->ID );
		?>
		<div class="evolve-aivm-meta"
		     data-evolve-aivm-meta
		     data-product-id="<?php echo esc_attr( (string) $post->ID ); ?>"
		     data-ajax-nonce="<?php echo esc_attr( $ai_nonce ); ?>"
		     data-ajax-url="<?php echo esc_attr( admin_url( 'admin-ajax.php' ) ); ?>">

			<p>
				<button type="button" class="button button-primary evolve-aivm-meta__autofill" data-evolve-aivm-autofill>
					<span class="dashicons dashicons-superhero" style="vertical-align:text-bottom"></span>
					<?php esc_html_e( 'Auto-fill with AI', 'evolve-ai-vape-match' ); ?>
				</button>
				<span class="description" style="display:block;margin-top:6px;">
					<?php esc_html_e( 'Reads the product title + description and proposes values for every field below. Review before saving.', 'evolve-ai-vape-match' ); ?>
				</span>
				<span class="evolve-aivm-meta__autofill-status" aria-live="polite" style="display:block;margin-top:6px;font-size:12px;"></span>
			</p>
			<hr>
			<p>
				<label><strong><?php esc_html_e( 'Flavor family', 'evolve-ai-vape-match' ); ?></strong></label><br>
				<select name="evolve_aivm[flavor_family]" class="widefat">
					<option value=""><?php esc_html_e( '— Select —', 'evolve-ai-vape-match' ); ?></option>
					<?php foreach ( $this->flavor_family_choices() as $k => $v ) : ?>
						<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $values['flavor_family'], $k ); ?>><?php echo esc_html( $v ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			<p>
				<label><strong><?php esc_html_e( 'Flavor notes', 'evolve-ai-vape-match' ); ?></strong></label><br>
				<input type="text" class="widefat" name="evolve_aivm[flavor_notes]" value="<?php echo esc_attr( $values['flavor_notes'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. mixed berry, ice, slight tart', 'evolve-ai-vape-match' ); ?>">
				<span class="description"><?php esc_html_e( 'Comma-separated free-form notes. Used by the AI for semantic matching.', 'evolve-ai-vape-match' ); ?></span>
			</p>
			<p>
				<label><strong><?php esc_html_e( 'Sweetness level (1–10)', 'evolve-ai-vape-match' ); ?></strong></label><br>
				<input type="number" min="1" max="10" class="small-text" name="evolve_aivm[sweetness_level]" value="<?php echo esc_attr( $values['sweetness_level'] ); ?>">
			</p>
			<p>
				<label><strong><?php esc_html_e( 'Cooling / ice level (1–10)', 'evolve-ai-vape-match' ); ?></strong></label><br>
				<input type="number" min="1" max="10" class="small-text" name="evolve_aivm[cooling_level]" value="<?php echo esc_attr( $values['cooling_level'] ); ?>">
			</p>
			<p>
				<label><strong><?php esc_html_e( 'Throat hit', 'evolve-ai-vape-match' ); ?></strong></label><br>
				<select name="evolve_aivm[throat_hit]" class="widefat">
					<option value=""><?php esc_html_e( '— Select —', 'evolve-ai-vape-match' ); ?></option>
					<?php foreach ( [ 'smooth' => __( 'Smooth', 'evolve-ai-vape-match' ), 'medium' => __( 'Medium', 'evolve-ai-vape-match' ), 'strong' => __( 'Strong', 'evolve-ai-vape-match' ) ] as $k => $v ) : ?>
						<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $values['throat_hit'], $k ); ?>><?php echo esc_html( $v ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			<p>
				<label><strong><?php esc_html_e( 'Nicotine strength (mg)', 'evolve-ai-vape-match' ); ?></strong></label><br>
				<input type="text" class="widefat" name="evolve_aivm[nicotine_strength]" value="<?php echo esc_attr( $values['nicotine_strength'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. 50, 35, 20, 0', 'evolve-ai-vape-match' ); ?>">
			</p>
			<p>
				<label><strong><?php esc_html_e( 'Device type', 'evolve-ai-vape-match' ); ?></strong></label><br>
				<select name="evolve_aivm[device_type]" class="widefat">
					<option value=""><?php esc_html_e( '— Select —', 'evolve-ai-vape-match' ); ?></option>
					<?php foreach ( $this->device_type_choices() as $k => $v ) : ?>
						<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $values['device_type'], $k ); ?>><?php echo esc_html( $v ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			<p>
				<label><strong><?php esc_html_e( 'Beginner friendly?', 'evolve-ai-vape-match' ); ?></strong></label><br>
				<label><input type="radio" name="evolve_aivm[beginner_friendly]" value="yes" <?php checked( $values['beginner_friendly'], 'yes' ); ?>> <?php esc_html_e( 'Yes', 'evolve-ai-vape-match' ); ?></label>
				&nbsp;
				<label><input type="radio" name="evolve_aivm[beginner_friendly]" value="no"  <?php checked( $values['beginner_friendly'], 'no' ); ?>> <?php esc_html_e( 'No', 'evolve-ai-vape-match' ); ?></label>
			</p>
			<p>
				<label><strong><?php esc_html_e( 'Brand', 'evolve-ai-vape-match' ); ?></strong></label><br>
				<input type="text" class="widefat" name="evolve_aivm[brand]" value="<?php echo esc_attr( $values['brand'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. SMOK, Geek Bar, Naked', 'evolve-ai-vape-match' ); ?>">
			</p>
			<p>
				<label><strong><?php esc_html_e( 'Compatible devices / pods / coils', 'evolve-ai-vape-match' ); ?></strong></label><br>
				<input type="text" class="widefat" name="evolve_aivm[compatibility]" value="<?php echo esc_attr( $values['compatibility'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. Smok Novo 5, RPM 4 coils', 'evolve-ai-vape-match' ); ?>">
				<span class="description"><?php esc_html_e( 'Used in the "Complete Your Setup" upsells.', 'evolve-ai-vape-match' ); ?></span>
			</p>
			<p>
				<label><strong><?php esc_html_e( 'AI keywords', 'evolve-ai-vape-match' ); ?></strong></label><br>
				<input type="text" class="widefat" name="evolve_aivm[ai_keywords]" value="<?php echo esc_attr( $values['ai_keywords'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. icy, sweet, premium, salt nic, refillable', 'evolve-ai-vape-match' ); ?>">
				<span class="description"><?php esc_html_e( 'Free-form tags the AI uses for semantic ranking.', 'evolve-ai-vape-match' ); ?></span>
			</p>
		</div>
		<?php
	}

	public function save( int $post_id, \WP_Post $post ) : void {
		if ( ! isset( $_POST[ self::NONCE . '_nonce' ] ) ) { return; }
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE . '_nonce' ] ) ), self::NONCE ) ) { return; }
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
		if ( ! current_user_can( 'edit_post', $post_id ) ) { return; }

		$raw = (array) ( $_POST['evolve_aivm'] ?? [] );

		$clean = [];
		$clean['flavor_family']     = isset( $raw['flavor_family'] )     ? sanitize_key( $raw['flavor_family'] ) : '';
		$clean['flavor_notes']      = isset( $raw['flavor_notes'] )      ? sanitize_text_field( $raw['flavor_notes'] ) : '';
		$clean['sweetness_level']   = isset( $raw['sweetness_level'] )   ? max( 0, min( 10, (int) $raw['sweetness_level'] ) ) : '';
		$clean['cooling_level']     = isset( $raw['cooling_level'] )     ? max( 0, min( 10, (int) $raw['cooling_level'] ) ) : '';
		$clean['throat_hit']        = isset( $raw['throat_hit'] )        ? sanitize_key( $raw['throat_hit'] ) : '';
		$clean['nicotine_strength'] = isset( $raw['nicotine_strength'] ) ? sanitize_text_field( $raw['nicotine_strength'] ) : '';
		$clean['device_type']       = isset( $raw['device_type'] )       ? sanitize_key( $raw['device_type'] ) : '';
		$clean['beginner_friendly'] = isset( $raw['beginner_friendly'] ) && in_array( $raw['beginner_friendly'], [ 'yes', 'no' ], true ) ? $raw['beginner_friendly'] : '';
		$clean['compatibility']     = isset( $raw['compatibility'] )     ? sanitize_text_field( $raw['compatibility'] ) : '';
		$clean['brand']             = isset( $raw['brand'] )             ? sanitize_text_field( $raw['brand'] ) : '';
		$clean['ai_keywords']       = isset( $raw['ai_keywords'] )       ? sanitize_text_field( $raw['ai_keywords'] ) : '';

		foreach ( $clean as $k => $v ) {
			if ( $v === '' || $v === null ) {
				delete_post_meta( $post_id, '_evolve_aivm_' . $k );
			} else {
				update_post_meta( $post_id, '_evolve_aivm_' . $k, $v );
			}
		}
	}

	/**
	 * AJAX → call OpenAI with the product's title + description, return
	 * sanitized field values for the JS to paint into the meta box form.
	 *
	 * Requires the `edit_post` cap on this specific product + a fresh nonce.
	 * Never auto-saves — user must click Update.
	 */
	public function ajax_autofill() : void {
		$product_id = (int) ( $_POST['product_id'] ?? 0 );
		$nonce      = (string) ( $_POST['_ajax_nonce'] ?? '' );

		if ( ! $product_id || ! current_user_can( 'edit_post', $product_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Forbidden.', 'evolve-ai-vape-match' ) ], 403 );
		}
		if ( ! wp_verify_nonce( $nonce, 'evolve_aivm_autofill_' . $product_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Stale nonce. Reload the page.', 'evolve-ai-vape-match' ) ], 403 );
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			wp_send_json_error( [ 'message' => __( 'Product not found.', 'evolve-ai-vape-match' ) ], 404 );
		}

		$openai = new OpenAI();
		if ( ! $openai->is_configured() ) {
			wp_send_json_error( [ 'message' => __( 'OpenAI API key is not set in Vape Match → Settings.', 'evolve-ai-vape-match' ) ], 400 );
		}

		$cats = wp_get_post_terms( $product_id, 'product_cat', [ 'fields' => 'names' ] );
		$tags = wp_get_post_terms( $product_id, 'product_tag', [ 'fields' => 'names' ] );

		$payload = [
			'title'            => $product->get_name(),
			'short_description'=> wp_strip_all_tags( $product->get_short_description() ),
			'description'      => mb_substr( wp_strip_all_tags( $product->get_description() ), 0, 2500 ),
			'categories'       => is_wp_error( $cats ) ? [] : array_values( $cats ),
			'tags'             => is_wp_error( $tags ) ? [] : array_values( $tags ),
			'price'            => (float) $product->get_price(),
			'sku'              => $product->get_sku(),
		];

		$values = $openai->auto_fill_from_product( $payload );
		if ( ! is_array( $values ) || ! $values ) {
			wp_send_json_error( [ 'message' => __( 'AI returned no usable data. Try again or fill manually.', 'evolve-ai-vape-match' ) ], 502 );
		}

		wp_send_json_success( [
			'values'  => $values,
			'message' => __( 'Filled. Review the fields and click Update to save.', 'evolve-ai-vape-match' ),
		] );
	}

	/**
	 * BULK — scan: returns the list of product IDs the bulk job will process,
	 * respecting the "skip products that already have data" toggle.
	 *
	 * Front-end calls this once, then iterates the IDs in batches through
	 * `ajax_bulk_process`. Keeps server work small per request to avoid PHP
	 * timeouts and OpenAI rate limits.
	 */
	public function ajax_bulk_scan() : void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Forbidden.', 'evolve-ai-vape-match' ) ], 403 );
		}
		check_ajax_referer( 'evolve_aivm_bulk', '_ajax_nonce' );

		$skip_existing = ! empty( $_POST['skip_existing'] );

		$args = [
			'post_type'      => 'product',
			'post_status'    => [ 'publish', 'draft', 'pending' ],
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		];

		if ( $skip_existing ) {
			$args['meta_query'] = [
				'relation' => 'OR',
				[ 'key' => '_evolve_aivm_flavor_family', 'compare' => 'NOT EXISTS' ],
				[ 'key' => '_evolve_aivm_flavor_family', 'value'   => '' ],
			];
		}

		$q = new \WP_Query( $args );
		$ids = array_map( 'intval', (array) $q->posts );

		wp_send_json_success( [
			'ids'   => $ids,
			'total' => count( $ids ),
		] );
	}

	/**
	 * BULK — process one product at a time. The driver script loops this
	 * once per ID. Returns the values it wrote so the UI can preview them.
	 */
	public function ajax_bulk_process() : void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Forbidden.', 'evolve-ai-vape-match' ) ], 403 );
		}
		check_ajax_referer( 'evolve_aivm_bulk', '_ajax_nonce' );

		$product_id    = (int) ( $_POST['product_id'] ?? 0 );
		$overwrite     = ! empty( $_POST['overwrite'] );
		$skip_existing = ! empty( $_POST['skip_existing'] );

		$product = $product_id ? wc_get_product( $product_id ) : null;
		if ( ! $product ) {
			wp_send_json_error( [ 'product_id' => $product_id, 'message' => __( 'Product not found.', 'evolve-ai-vape-match' ) ], 404 );
		}

		// If skip_existing, bail when flavor_family already set.
		if ( $skip_existing && get_post_meta( $product_id, '_evolve_aivm_flavor_family', true ) ) {
			wp_send_json_success( [
				'product_id' => $product_id,
				'name'       => $product->get_name(),
				'status'     => 'skipped',
				'message'    => __( 'Already has data', 'evolve-ai-vape-match' ),
			] );
		}

		$openai = new OpenAI();
		if ( ! $openai->is_configured() ) {
			wp_send_json_error( [ 'product_id' => $product_id, 'message' => __( 'OpenAI API key is not set.', 'evolve-ai-vape-match' ) ], 400 );
		}

		$cats = wp_get_post_terms( $product_id, 'product_cat', [ 'fields' => 'names' ] );
		$tags = wp_get_post_terms( $product_id, 'product_tag', [ 'fields' => 'names' ] );

		$payload = [
			'title'            => $product->get_name(),
			'short_description'=> wp_strip_all_tags( $product->get_short_description() ),
			'description'      => mb_substr( wp_strip_all_tags( $product->get_description() ), 0, 2500 ),
			'categories'       => is_wp_error( $cats ) ? [] : array_values( $cats ),
			'tags'             => is_wp_error( $tags ) ? [] : array_values( $tags ),
			'price'            => (float) $product->get_price(),
			'sku'              => $product->get_sku(),
		];

		$values = $openai->auto_fill_from_product( $payload );
		if ( ! is_array( $values ) || ! $values ) {
			wp_send_json_error( [
				'product_id' => $product_id,
				'name'       => $product->get_name(),
				'message'    => __( 'AI returned no usable data.', 'evolve-ai-vape-match' ),
			], 502 );
		}

		// Persist. When `overwrite` is OFF, keep existing values that aren't empty.
		$written = [];
		foreach ( $values as $k => $v ) {
			if ( ! in_array( $k, self::FIELDS, true ) ) { continue; }
			if ( ! $overwrite ) {
				$existing = get_post_meta( $product_id, '_evolve_aivm_' . $k, true );
				if ( $existing !== '' && $existing !== null ) { continue; }
			}
			update_post_meta( $product_id, '_evolve_aivm_' . $k, $v );
			$written[] = $k;
		}

		wp_send_json_success( [
			'product_id' => $product_id,
			'name'       => $product->get_name(),
			'status'     => 'filled',
			'written'    => $written,
			'values'     => $values,
		] );
	}

	public static function flavor_family_choices() : array {
		return [
			'fruity'         => __( 'Fruity', 'evolve-ai-vape-match' ),
			'mint-ice'       => __( 'Mint / Ice', 'evolve-ai-vape-match' ),
			'candy'          => __( 'Candy', 'evolve-ai-vape-match' ),
			'dessert'        => __( 'Dessert', 'evolve-ai-vape-match' ),
			'tobacco'        => __( 'Tobacco', 'evolve-ai-vape-match' ),
			'drink-inspired' => __( 'Drink-inspired', 'evolve-ai-vape-match' ),
		];
	}

	public static function device_type_choices() : array {
		return [
			'disposable'  => __( 'Disposable', 'evolve-ai-vape-match' ),
			'pod-system'  => __( 'Pod system', 'evolve-ai-vape-match' ),
			'vape-juice'  => __( 'Vape juice', 'evolve-ai-vape-match' ),
			'mod-device'  => __( 'Mod / device', 'evolve-ai-vape-match' ),
			'pod'         => __( 'Replacement pod', 'evolve-ai-vape-match' ),
			'coil'        => __( 'Coil', 'evolve-ai-vape-match' ),
			'accessory'   => __( 'Accessory (charger, battery, etc.)', 'evolve-ai-vape-match' ),
		];
	}
}
