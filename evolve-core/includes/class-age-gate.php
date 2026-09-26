<?php
/**
 * Age Gate — 21+ overlay shown until the user confirms.
 *
 * Renders a full-screen glass card overlay and stores the verified state in
 * a cookie (default: 30 days, configurable via the `evolve_age_gate_days` filter).
 *
 * If the user clicks Exit, they're redirected to google.com — admins should
 * change this via the `evolve_age_gate_exit_url` filter or by editing the popup
 * inside Elementor once the JSON template is imported.
 */
namespace Evolve_Core;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Age_Gate {

	const COOKIE = 'evolve_age_verified';

	public function __construct() {
		add_action( 'wp_enqueue_scripts',  [ $this, 'enqueue' ] );
		add_action( 'wp_footer',           [ $this, 'render' ], 5 );
		add_action( 'wp_ajax_nopriv_evolve_verify_age', [ $this, 'ajax_verify' ] );
		add_action( 'wp_ajax_evolve_verify_age',        [ $this, 'ajax_verify' ] );
	}

	public function enqueue() {
		if ( $this->is_verified() ) { return; }

		wp_enqueue_style(
			'evolve-age-gate',
			EVOLVE_CORE_URL . 'assets/css/age-gate.css',
			[],
			EVOLVE_CORE_VERSION
		);

		wp_enqueue_script(
			'evolve-age-gate',
			EVOLVE_CORE_URL . 'assets/js/age-gate.js',
			[],
			EVOLVE_CORE_VERSION,
			true
		);

		wp_localize_script( 'evolve-age-gate', 'evolveAgeGate', [
			'ajax'    => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'evolve_age_gate' ),
			'exit'    => apply_filters( 'evolve_age_gate_exit_url', 'https://www.google.com/' ),
			'days'    => (int) apply_filters( 'evolve_age_gate_days', 30 ),
		] );
	}

	public function render() {
		if ( $this->is_verified() ) { return; }

		$logo = function_exists( 'evolve_logo_url' ) ? evolve_logo_url() : '';
		?>
		<div class="evolve-agegate" id="evolveAgeGate" role="dialog" aria-modal="true" aria-labelledby="evolveAgeGateTitle">
			<div class="evolve-agegate__bg" aria-hidden="true"></div>
			<div class="evolve-agegate__card">
				<?php if ( $logo ) : ?>
					<img class="evolve-agegate__logo" src="<?php echo esc_url( $logo ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" />
				<?php endif; ?>
				<h2 id="evolveAgeGateTitle" class="evolve-agegate__title">You must be 21+ to enter.</h2>
				<?php
				$terms   = function_exists( 'wc_terms_and_conditions_page_id' ) && wc_terms_and_conditions_page_id() ? get_permalink( wc_terms_and_conditions_page_id() ) : home_url( '/website-terms-conditions/' );
				$privacy = get_privacy_policy_url() ?: home_url( '/evolve-vapor-privacy-policy/' );
				?>
				<p class="evolve-agegate__lede">By entering this site you certify that you are at least 21 years of age and accept our <a href="<?php echo esc_url( $terms ); ?>">Terms of Use</a> and <a href="<?php echo esc_url( $privacy ); ?>">Privacy Policy</a>.</p>
				<div class="evolve-agegate__cta">
					<button type="button" class="evolve-btn evolve-btn--primary" data-evolve-age="enter">I'm 21 or older — Enter</button>
					<button type="button" class="evolve-btn evolve-btn--ghost"   data-evolve-age="exit">Exit</button>
				</div>
				<p class="evolve-agegate__note">Nashua, NH · Pheasant Lane Mall · ID required at pickup.</p>
			</div>
		</div>
		<?php
	}

	public function ajax_verify() {
		check_ajax_referer( 'evolve_age_gate', 'nonce' );

		$days = (int) apply_filters( 'evolve_age_gate_days', 30 );
		setcookie(
			self::COOKIE,
			'1',
			[
				'expires'  => time() + ( DAY_IN_SECONDS * $days ),
				'path'     => COOKIEPATH ?: '/',
				'domain'   => COOKIE_DOMAIN ?: '',
				'secure'   => is_ssl(),
				'httponly' => false,        // needs to be readable client-side too
				'samesite' => 'Lax',
			]
		);
		wp_send_json_success();
	}

	public function is_verified() : bool {
		// Logged-in users skip the gate.
		if ( is_user_logged_in() ) { return true; }
		// Bypass on admin / login pages.
		if ( is_admin() || $GLOBALS['pagenow'] === 'wp-login.php' ) { return true; }
		return ! empty( $_COOKIE[ self::COOKIE ] );
	}
}
