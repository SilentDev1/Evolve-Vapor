<?php
/**
 * Weekly Promo — featured product popup shown once per session.
 *
 * Displays a full-screen popup showcasing a WooCommerce product selected in
 * Settings → Evolve Weekly Promo. Appears 3 seconds after page load,
 * respects the age gate (waits for dismissal), and is dismissed for the
 * remainder of the browser session via sessionStorage.
 */
namespace Evolve_Core;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Weekly_Promo {

	const OPTION = 'evolve_weekly_promo';

	public function __construct() {
		// Admin settings page.
		add_action( 'admin_menu',    [ $this, 'add_menu' ] );
		add_action( 'admin_init',    [ $this, 'register_settings' ] );

		// Frontend popup.
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ] );
		add_action( 'wp_footer',          [ $this, 'render' ], 10 );
	}

	/* ------------------------------------------------------------------
	 * Helpers
	 * ----------------------------------------------------------------*/

	private function get_options() {
		return wp_parse_args( get_option( self::OPTION, [] ), [
			'enabled'    => 0,
			'product_id' => 0,
			'headline'   => 'Deal of the Week',
			'desc'       => '',
			'cta_text'   => 'Shop Now',
		] );
	}

	/**
	 * Returns the WC product object if the promo is enabled and the product
	 * is published. Returns null otherwise.
	 */
	private function get_promo_product() {
		if ( ! class_exists( 'WooCommerce' ) ) { return null; }

		$opts = $this->get_options();
		if ( empty( $opts['enabled'] ) || empty( $opts['product_id'] ) ) { return null; }

		$product = wc_get_product( (int) $opts['product_id'] );
		if ( ! $product || $product->get_status() !== 'publish' ) { return null; }

		return $product;
	}

	/* ------------------------------------------------------------------
	 * Admin — Settings page
	 * ----------------------------------------------------------------*/

	public function add_menu() {
		add_options_page(
			'Evolve Weekly Promo',
			'Evolve Weekly Promo',
			'manage_options',
			'evolve-weekly-promo',
			[ $this, 'render_settings_page' ]
		);
	}

	public function register_settings() {
		register_setting( 'evolve_weekly_promo_group', self::OPTION, [
			'type'              => 'array',
			'sanitize_callback' => [ $this, 'sanitize_options' ],
		] );
	}

	public function sanitize_options( $input ) {
		return [
			'enabled'    => ! empty( $input['enabled'] ) ? 1 : 0,
			'product_id' => absint( $input['product_id'] ?? 0 ),
			'headline'   => sanitize_text_field( $input['headline'] ?? '' ),
			'desc'       => sanitize_textarea_field( $input['desc'] ?? '' ),
			'cta_text'   => sanitize_text_field( $input['cta_text'] ?? '' ),
		];
	}

	public function render_settings_page() {
		$opts = $this->get_options();

		$products = get_posts( [
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		] );
		?>
		<div class="wrap">
			<h1>Evolve Weekly Promo</h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'evolve_weekly_promo_group' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">Enable Popup</th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo self::OPTION; ?>[enabled]" value="1" <?php checked( $opts['enabled'], 1 ); ?> />
								Show the weekly promo popup on the frontend
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row">Product</th>
						<td>
							<select name="<?php echo self::OPTION; ?>[product_id]">
								<option value="0">— Select a product —</option>
								<?php foreach ( $products as $p ) : ?>
									<option value="<?php echo esc_attr( $p->ID ); ?>" <?php selected( $opts['product_id'], $p->ID ); ?>>
										<?php echo esc_html( $p->post_title ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row">Headline</th>
						<td>
							<input type="text" name="<?php echo self::OPTION; ?>[headline]" value="<?php echo esc_attr( $opts['headline'] ); ?>" class="regular-text" placeholder="Deal of the Week" />
						</td>
					</tr>
					<tr>
						<th scope="row">Description</th>
						<td>
							<textarea name="<?php echo self::OPTION; ?>[desc]" rows="3" class="large-text" placeholder="Save 20% on this product this week only!"><?php echo esc_textarea( $opts['desc'] ); ?></textarea>
						</td>
					</tr>
					<tr>
						<th scope="row">CTA Button Text</th>
						<td>
							<input type="text" name="<?php echo self::OPTION; ?>[cta_text]" value="<?php echo esc_attr( $opts['cta_text'] ); ?>" class="regular-text" placeholder="Shop Now" />
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------
	 * Frontend — enqueue assets
	 * ----------------------------------------------------------------*/

	public function enqueue() {
		if ( is_admin() ) { return; }

		$product = $this->get_promo_product();
		if ( ! $product ) { return; }

		wp_enqueue_style(
			'evolve-weekly-promo',
			EVOLVE_CORE_URL . 'assets/css/weekly-promo.css',
			[],
			EVOLVE_CORE_VERSION
		);

		wp_enqueue_script(
			'evolve-weekly-promo',
			EVOLVE_CORE_URL . 'assets/js/weekly-promo.js',
			[],
			EVOLVE_CORE_VERSION,
			true
		);

		$opts  = $this->get_options();
		$image = wp_get_attachment_image_url( $product->get_image_id(), 'woocommerce_thumbnail' ) ?: '';

		wp_localize_script( 'evolve-weekly-promo', 'evolveWeeklyPromoData', [
			'image'       => $image,
			'productName' => $product->get_name(),
			'price'       => $product->get_price_html(),
			'permalink'   => $product->get_permalink(),
			'headline'    => $opts['headline'],
			'desc'        => $opts['desc'],
			'ctaText'     => $opts['cta_text'] ?: 'Shop Now',
		] );
	}

	/* ------------------------------------------------------------------
	 * Frontend — render popup HTML
	 * ----------------------------------------------------------------*/

	public function render() {
		if ( is_admin() ) { return; }

		$product = $this->get_promo_product();
		if ( ! $product ) { return; }

		$opts  = $this->get_options();
		$image = wp_get_attachment_image_url( $product->get_image_id(), 'woocommerce_thumbnail' ) ?: '';
		?>
		<div class="evolve-weekly-promo" id="evolveWeeklyPromo" role="dialog" aria-modal="true" style="display:none;">
			<div class="evolve-weekly-promo__bg"></div>
			<div class="evolve-weekly-promo__card">
				<button class="evolve-weekly-promo__close" data-evolve-promo="close" aria-label="Close">&times;</button>
				<?php if ( $image ) : ?>
					<img class="evolve-weekly-promo__img" src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( $product->get_name() ); ?>" />
				<?php endif; ?>
				<h3 class="evolve-weekly-promo__title"><?php echo esc_html( $opts['headline'] ); ?></h3>
				<p class="evolve-weekly-promo__product-name"><?php echo esc_html( $product->get_name() ); ?></p>
				<p class="evolve-weekly-promo__price"><?php echo $product->get_price_html(); ?></p>
				<?php if ( ! empty( $opts['desc'] ) ) : ?>
					<p class="evolve-weekly-promo__desc"><?php echo esc_html( $opts['desc'] ); ?></p>
				<?php endif; ?>
				<a href="<?php echo esc_url( $product->get_permalink() ); ?>" class="evolve-btn evolve-btn--primary evolve-weekly-promo__cta">
					<?php echo esc_html( $opts['cta_text'] ?: 'Shop Now' ); ?>
				</a>
			</div>
		</div>
		<?php
	}
}
