<?php
namespace EvolveAIVapeMatch;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Admin {

	const OPTION = 'evolve_aivm_settings';

	public function register() : void {
		add_action( 'admin_menu',  [ $this, 'menu' ] );
		add_action( 'admin_init',  [ $this, 'settings' ] );
	}

	public function menu() : void {
		add_menu_page(
			__( 'Evolve AI Vape Match', 'evolve-ai-vape-match' ),
			__( 'Vape Match', 'evolve-ai-vape-match' ),
			'manage_options',
			'evolve-aivm',
			[ $this, 'render' ],
			'dashicons-search',
			58
		);
	}

	public function settings() : void {
		register_setting( 'evolve_aivm', self::OPTION, [
			'type'              => 'array',
			'sanitize_callback' => [ $this, 'sanitize' ],
			'default'           => [],
		] );

		add_settings_section(
			'evolve_aivm_ai', __( 'AI', 'evolve-ai-vape-match' ),
			static function () { echo '<p>' . esc_html__( 'OpenAI configuration for ranking and explanation.', 'evolve-ai-vape-match' ) . '</p>'; },
			'evolve-aivm'
		);
		$this->field( 'api_key',            __( 'OpenAI API Key', 'evolve-ai-vape-match' ),     'password', 'evolve_aivm_ai',  __( 'Stored server-side. Never sent to the front-end.', 'evolve-ai-vape-match' ) );
		$this->field( 'model',              __( 'Model',          'evolve-ai-vape-match' ),     'select',   'evolve_aivm_ai',  null, [
			'gpt-5-mini'   => 'gpt-5-mini (recommended)',
			'gpt-5'        => 'gpt-5',
			'gpt-4o-mini'  => 'gpt-4o-mini',
			'gpt-4o'       => 'gpt-4o',
		] );
		$this->field( 'ai_explanations',    __( 'AI explanations', 'evolve-ai-vape-match' ),    'checkbox','evolve_aivm_ai',  __( 'Generate short per-product reasons (otherwise show generic match copy).', 'evolve-ai-vape-match' ) );
		$this->field( 'max_products_to_ai', __( 'Max products sent to AI', 'evolve-ai-vape-match' ), 'number', 'evolve_aivm_ai', __( 'Higher = better ranking but more tokens. 8–16 is the sweet spot.', 'evolve-ai-vape-match' ) );
		$this->field( 'fallback_enabled',   __( 'Fallback to rule-based if API fails', 'evolve-ai-vape-match' ), 'checkbox', 'evolve_aivm_ai', __( 'Recommended on. Quiz still returns results even when OpenAI errors.', 'evolve-ai-vape-match' ) );

		add_settings_section(
			'evolve_aivm_design', __( 'Design', 'evolve-ai-vape-match' ),
			static function () { echo '<p>' . esc_html__( 'Theme accents the front-end wizard inherits.', 'evolve-ai-vape-match' ) . '</p>'; },
			'evolve-aivm'
		);
		$this->field( 'accent_color',   __( 'Accent color', 'evolve-ai-vape-match' ), 'color',  'evolve_aivm_design' );
		$this->field( 'button_color',   __( 'Button color', 'evolve-ai-vape-match' ), 'color',  'evolve_aivm_design' );
		$this->field( 'floating_button',__( 'Floating "Find My Match" button', 'evolve-ai-vape-match' ), 'checkbox', 'evolve_aivm_design',
			__( 'Adds a sticky button to every front-end page that opens the quiz.', 'evolve-ai-vape-match' )
		);

		add_settings_section(
			'evolve_aivm_compliance', __( 'Compliance', 'evolve-ai-vape-match' ),
			static function () { echo '<p>' . esc_html__( 'Age confirmation is required before recommendations render.', 'evolve-ai-vape-match' ) . '</p>'; },
			'evolve-aivm'
		);
		$this->field( 'age_text',       __( 'Age confirmation text', 'evolve-ai-vape-match' ), 'textarea', 'evolve_aivm_compliance' );
	}

	private function field( string $key, string $label, string $type, string $section, ?string $help = null, array $options = [] ) : void {
		add_settings_field(
			$key, $label,
			function () use ( $key, $type, $help, $options ) {
				$settings = (array) get_option( self::OPTION, [] );
				$value    = $settings[ $key ] ?? '';
				$name     = 'evolve_aivm_settings[' . $key . ']';

				switch ( $type ) {
					case 'password':
						printf( '<input type="password" autocomplete="off" name="%s" value="%s" class="regular-text">', esc_attr( $name ), esc_attr( $value ) );
						break;
					case 'number':
						printf( '<input type="number" min="1" max="40" name="%s" value="%s" class="small-text">', esc_attr( $name ), esc_attr( $value ?: 12 ) );
						break;
					case 'color':
						printf( '<input type="text" data-evolve-color name="%s" value="%s" class="small-text" placeholder="#B7FF00">', esc_attr( $name ), esc_attr( $value ?: '#B7FF00' ) );
						break;
					case 'checkbox':
						printf( '<label><input type="checkbox" name="%s" value="1" %s> %s</label>',
							esc_attr( $name ), checked( ! empty( $value ), true, false ), esc_html( $help ?? '' ) );
						$help = null; // already shown
						break;
					case 'select':
						printf( '<select name="%s">', esc_attr( $name ) );
						foreach ( $options as $k => $v ) {
							printf( '<option value="%s" %s>%s</option>', esc_attr( $k ), selected( $value, $k, false ), esc_html( $v ) );
						}
						echo '</select>';
						break;
					case 'textarea':
						printf( '<textarea name="%s" rows="2" class="large-text">%s</textarea>', esc_attr( $name ), esc_textarea( $value ) );
						break;
				}
				if ( $help ) {
					echo '<p class="description">' . wp_kses_post( $help ) . '</p>';
				}
			},
			'evolve-aivm',
			$section
		);
	}

	public function sanitize( $input ) : array {
		$out = (array) get_option( self::OPTION, [] );

		$out['api_key']            = isset( $input['api_key'] ) ? sanitize_text_field( $input['api_key'] ) : '';
		$out['model']              = isset( $input['model'] )   ? sanitize_text_field( $input['model'] ) : 'gpt-4o-mini';
		$out['ai_explanations']    = ! empty( $input['ai_explanations'] ) ? 1 : 0;
		$out['max_products_to_ai'] = isset( $input['max_products_to_ai'] ) ? max( 4, min( 40, (int) $input['max_products_to_ai'] ) ) : 12;
		$out['fallback_enabled']   = ! empty( $input['fallback_enabled'] ) ? 1 : 0;
		$out['accent_color']       = $this->sanitize_color( $input['accent_color'] ?? '#B7FF00' );
		$out['button_color']       = $this->sanitize_color( $input['button_color'] ?? '#B7FF00' );
		$out['floating_button']    = ! empty( $input['floating_button'] ) ? 1 : 0;
		$out['age_text']           = isset( $input['age_text'] ) ? sanitize_textarea_field( $input['age_text'] ) : '';

		return $out;
	}

	private function sanitize_color( string $color ) : string {
		$color = trim( $color );
		if ( preg_match( '/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', $color ) ) {
			return $color;
		}
		return '#B7FF00';
	}

	public function render() : void {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		?>
		<div class="wrap evolve-aivm-admin">
			<h1><?php esc_html_e( 'Evolve AI Vape Match', 'evolve-ai-vape-match' ); ?></h1>
			<p class="evolve-aivm-admin__lede">
				<?php esc_html_e( 'AI-powered vape matcher that only ever recommends real, in-stock, published WooCommerce products.', 'evolve-ai-vape-match' ); ?>
			</p>

			<div class="evolve-aivm-admin__card">
				<form action="options.php" method="post">
					<?php
					settings_fields( 'evolve_aivm' );
					do_settings_sections( 'evolve-aivm' );
					submit_button();
					?>
				</form>
			</div>

			<div class="evolve-aivm-admin__card evolve-aivm-admin__card--bulk">
				<h2><?php esc_html_e( 'Bulk Auto-Fill all products', 'evolve-ai-vape-match' ); ?></h2>
				<p>
					<?php esc_html_e( 'Run the AI over every WooCommerce product and fill the "AI Vape Match Data" fields from each product\'s title + description. Useful when you\'re seeding the catalog.', 'evolve-ai-vape-match' ); ?>
				</p>
				<p style="display:flex;flex-wrap:wrap;gap:18px;align-items:center;margin:14px 0;">
					<label>
						<input type="checkbox" id="evolve-aivm-bulk-skip" checked>
						<?php esc_html_e( 'Skip products that already have AI data', 'evolve-ai-vape-match' ); ?>
					</label>
					<label>
						<input type="checkbox" id="evolve-aivm-bulk-overwrite">
						<?php esc_html_e( 'Overwrite existing field values', 'evolve-ai-vape-match' ); ?>
						<span class="description" style="display:block;margin-left:24px;">
							<?php esc_html_e( 'Off = AI only fills empty fields. On = AI replaces all fields.', 'evolve-ai-vape-match' ); ?>
						</span>
					</label>
				</p>
				<p>
					<button type="button" class="button button-primary button-large" id="evolve-aivm-bulk-start">
						<span class="dashicons dashicons-superhero" style="vertical-align:text-bottom"></span>
						<?php esc_html_e( 'Start Bulk Auto-Fill', 'evolve-ai-vape-match' ); ?>
					</button>
					<button type="button" class="button" id="evolve-aivm-bulk-stop" disabled>
						<?php esc_html_e( 'Stop', 'evolve-ai-vape-match' ); ?>
					</button>
					<span id="evolve-aivm-bulk-status" style="margin-left:10px;color:#50575e;"></span>
				</p>

				<div id="evolve-aivm-bulk-progress" style="display:none;margin-top:14px;">
					<div style="background:#dcdcde;border-radius:999px;overflow:hidden;height:10px;margin-bottom:8px;">
						<div id="evolve-aivm-bulk-bar" style="height:100%;width:0%;background:linear-gradient(90deg,#B7FF00,#7CFF4F);transition:width .3s ease;"></div>
					</div>
					<div id="evolve-aivm-bulk-counts" style="font-size:12px;color:#50575e;display:flex;gap:18px;flex-wrap:wrap;">
						<span><strong>Total:</strong> <span id="evolve-aivm-bulk-total">0</span></span>
						<span><strong>Done:</strong> <span id="evolve-aivm-bulk-done">0</span></span>
						<span style="color:#00a32a"><strong>Filled:</strong> <span id="evolve-aivm-bulk-ok">0</span></span>
						<span style="color:#dba617"><strong>Skipped:</strong> <span id="evolve-aivm-bulk-skipped">0</span></span>
						<span style="color:#d63638"><strong>Failed:</strong> <span id="evolve-aivm-bulk-fail">0</span></span>
					</div>
				</div>

				<div id="evolve-aivm-bulk-log" style="display:none;margin-top:14px;max-height:300px;overflow-y:auto;background:#fafafa;border:1px solid #dcdcde;border-radius:6px;padding:10px;font-family:Menlo,Consolas,monospace;font-size:12px;line-height:1.6;"></div>

				<input type="hidden" id="evolve-aivm-bulk-nonce" value="<?php echo esc_attr( wp_create_nonce( 'evolve_aivm_bulk' ) ); ?>">
			</div>

			<div class="evolve-aivm-admin__card evolve-aivm-admin__card--help">
				<h2><?php esc_html_e( 'How to use', 'evolve-ai-vape-match' ); ?></h2>
				<ol>
					<li><?php echo wp_kses_post( __( 'Drop the <code>[evolve_ai_vape_match]</code> shortcode on any page (e.g. <em>/quiz/</em>) or load it in an Elementor Shortcode widget.', 'evolve-ai-vape-match' ) ); ?></li>
					<li><?php esc_html_e( 'Open any WooCommerce product → fill in the "AI Vape Match Data" meta box (flavor family, sweetness, cooling, throat hit, nicotine, etc.).', 'evolve-ai-vape-match' ); ?></li>
					<li><?php esc_html_e( 'Test the quiz on the front-end. The recommender returns only products with stock_status=instock that pass visibility.', 'evolve-ai-vape-match' ); ?></li>
				</ol>
				<p><strong><?php esc_html_e( 'Endpoint:', 'evolve-ai-vape-match' ); ?></strong> <code>POST <?php echo esc_url( rest_url( 'evolve-ai-vape-match/v1/recommend' ) ); ?></code></p>
			</div>
		</div>
		<?php
	}
}
