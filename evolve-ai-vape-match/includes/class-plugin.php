<?php
namespace EvolveAIVapeMatch;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Main plugin singleton — wires up subsystems.
 */
class Plugin {

	private static ?Plugin $instance = null;

	public static function instance() : Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function boot() : void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return; // soft-fail; admin notice already shown by main file
		}

		// Subsystems
		( new Admin() )->register();
		( new Product_Meta() )->register();
		( new REST_API() )->register();

		add_action( 'wp_enqueue_scripts',    [ $this, 'enqueue_frontend' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin' ] );
		add_shortcode( 'evolve_ai_vape_match', [ $this, 'shortcode' ] );

		add_action( 'wp_footer', [ $this, 'maybe_render_floating' ] );
	}

	public function enqueue_frontend() : void {
		$settings = (array) get_option( 'evolve_aivm_settings', [] );
		$accent   = $settings['accent_color'] ?? '#B7FF00';
		$button   = $settings['button_color'] ?? '#B7FF00';

		wp_register_style(
			'evolve-aivm-frontend',
			EVOLVE_AIVM_URL . 'assets/css/frontend.css',
			[],
			EVOLVE_AIVM_VERSION
		);
		// Inject the accent/button colors so admin Customizer settings paint the wizard.
		wp_add_inline_style( 'evolve-aivm-frontend', sprintf(
			'.evolve-aivm{--evolve-aivm-accent:%1$s;}.evolve-aivm__btn{background:%2$s!important;}.evolve-aivm-fab{background:%1$s;}',
			esc_attr( $accent ),
			esc_attr( $button )
		) );
		wp_register_script(
			'evolve-aivm-frontend',
			EVOLVE_AIVM_URL . 'assets/js/frontend.js',
			[],
			EVOLVE_AIVM_VERSION,
			true
		);

		$settings = (array) get_option( 'evolve_aivm_settings', [] );
		wp_localize_script( 'evolve-aivm-frontend', 'evolveAIVM', [
			'rest'    => esc_url_raw( rest_url( 'evolve-ai-vape-match/v1/recommend' ) ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
			'accent'  => $accent,
			'button'  => $button,
			'age_txt' => $settings['age_text']       ?? 'I confirm I am of legal age to purchase nicotine products in my location.',
			'i18n'    => [
				'next'      => __( 'Next →',     'evolve-ai-vape-match' ),
				'back'      => __( '← Back',     'evolve-ai-vape-match' ),
				'submit'    => __( 'Find My Match', 'evolve-ai-vape-match' ),
				'restart'   => __( 'Start over',  'evolve-ai-vape-match' ),
				'thinking'  => __( 'Matching products…', 'evolve-ai-vape-match' ),
				'error'     => __( 'Something went wrong. Please try again.', 'evolve-ai-vape-match' ),
				'no_match'  => __( 'No products match yet — try widening your filters.', 'evolve-ai-vape-match' ),
				'add_cart'  => __( 'Add to Cart', 'evolve-ai-vape-match' ),
				'view'      => __( 'View Product', 'evolve-ai-vape-match' ),
			],
		] );
	}

	public function enqueue_admin( $hook ) : void {
		// We need admin assets on our settings page AND on product edit screens (for the meta box).
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$is_product_edit = $screen && $screen->post_type === 'product';
		if ( $hook !== 'toplevel_page_evolve-aivm' && ! $is_product_edit ) {
			return;
		}
		wp_enqueue_style(
			'evolve-aivm-admin',
			EVOLVE_AIVM_URL . 'assets/css/admin.css',
			[],
			EVOLVE_AIVM_VERSION
		);
		wp_enqueue_script(
			'evolve-aivm-admin',
			EVOLVE_AIVM_URL . 'assets/js/admin.js',
			[ 'jquery' ],
			EVOLVE_AIVM_VERSION,
			true
		);
	}

	/**
	 * Renders the quiz wizard mounted at any [evolve_ai_vape_match] shortcode.
	 */
	public function shortcode( $atts = [], $content = '' ) : string {
		wp_enqueue_style( 'evolve-aivm-frontend' );
		wp_enqueue_script( 'evolve-aivm-frontend' );

		$atts = shortcode_atts( [
			'class'       => '',
			'show_intro'  => 'yes',
			'title'       => __( 'Find your perfect vape', 'evolve-ai-vape-match' ),
			'subtitle'    => __( 'Answer a few quick questions and we\'ll match you with real in-stock products from our shelves.', 'evolve-ai-vape-match' ),
		], $atts, 'evolve_ai_vape_match' );

		ob_start();
		?>
		<div class="evolve-aivm <?php echo esc_attr( $atts['class'] ); ?>" data-evolve-aivm>
			<?php if ( $atts['show_intro'] === 'yes' ) : ?>
				<div class="evolve-aivm__intro">
					<h2 class="evolve-aivm__title"><?php echo esc_html( $atts['title'] ); ?></h2>
					<p   class="evolve-aivm__subtitle"><?php echo esc_html( $atts['subtitle'] ); ?></p>
				</div>
			<?php endif; ?>

			<div class="evolve-aivm__progress" aria-hidden="true">
				<div class="evolve-aivm__progress-bar"></div>
			</div>

			<div class="evolve-aivm__wizard" role="form" aria-label="Vape match wizard">
				<!-- Steps are rendered by frontend.js so we keep the markup small server-side -->
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	public function maybe_render_floating() : void {
		$settings = (array) get_option( 'evolve_aivm_settings', [] );
		if ( empty( $settings['floating_button'] ) ) { return; }
		if ( is_admin() ) { return; }
		printf(
			'<button type="button" class="evolve-aivm-fab" data-evolve-aivm-fab aria-label="%s" style="--evolve-aivm-accent:%s">' .
				'<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>' .
				'<span>%s</span>' .
			'</button>',
			esc_attr__( 'Find my vape match', 'evolve-ai-vape-match' ),
			esc_attr( $settings['accent_color'] ?? '#B7FF00' ),
			esc_html__( 'Find My Match', 'evolve-ai-vape-match' )
		);
	}
}
