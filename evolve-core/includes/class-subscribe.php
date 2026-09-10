<?php
/**
 * Evolve Subscribe — newsletter / lead capture engine.
 *
 * Routes all subscribe submissions through GhostPilot's Ghost-Convert CRM
 * (`POST /wp-json/ghostpilot/v1/ghost-convert/capture`) when the plugin is
 * active. Falls back to a local custom-post-type log + admin email so the
 * site keeps capturing leads even if GhostPilot is disabled or breaks.
 *
 * Exposes a `[evolve_subscribe]` shortcode and a render helper that the
 * Evolve_Subscribe Elementor widget shares.
 */
namespace Evolve_Core;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Subscribe {

	const NONCE_ACTION = 'evolve_subscribe';
	const LOG_CPT      = 'evolve_lead';

	public function __construct() {
		add_action( 'init',                                    [ $this, 'register_cpt' ] );
		add_action( 'wp_ajax_evolve_subscribe',                [ $this, 'ajax' ] );
		add_action( 'wp_ajax_nopriv_evolve_subscribe',         [ $this, 'ajax' ] );
		add_action( 'wp_enqueue_scripts',                      [ $this, 'register_assets' ] );

		add_shortcode( 'evolve_subscribe',                     [ $this, 'shortcode' ] );
	}

	/* ----------------------------------------------------------------- */

	public static function ghostpilot_available() {
		return class_exists( 'GhostPilot\\Engine\\GhostConvert' );
	}

	public function register_cpt() {
		register_post_type( self::LOG_CPT, [
			'label'        => 'Evolve Leads',
			'public'       => false,
			'show_ui'      => true,
			'show_in_menu' => 'tools.php',
			'supports'     => [ 'title', 'editor', 'custom-fields' ],
			'capability_type' => 'post',
			'menu_icon'    => 'dashicons-email-alt',
			'labels'       => [
				'name'          => 'Evolve Leads',
				'singular_name' => 'Evolve Lead',
				'menu_name'     => 'Evolve Leads',
				'all_items'     => 'All Leads',
			],
		] );
	}

	public function register_assets() {
		wp_register_style(
			'evolve-subscribe',
			EVOLVE_CORE_URL . 'assets/css/subscribe.css',
			[],
			EVOLVE_CORE_VERSION
		);
		wp_register_script(
			'evolve-subscribe',
			EVOLVE_CORE_URL . 'assets/js/subscribe.js',
			[],
			EVOLVE_CORE_VERSION,
			true
		);
		wp_localize_script( 'evolve-subscribe', 'evolveSubscribe', [
			'ajax'  => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( self::NONCE_ACTION ),
		] );
	}

	/* ----------------------------------------------------------------- */

	public function ajax() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		if ( ! $email || ! is_email( $email ) ) {
			wp_send_json_error( [ 'message' => 'Please enter a valid email address.' ] );
		}

		// Consent is required if the form asked for it.
		$consent_required = ! empty( $_POST['consent_required'] );
		if ( $consent_required && empty( $_POST['consent'] ) ) {
			wp_send_json_error( [ 'message' => 'Please tick the consent box to continue.' ] );
		}

		// Honeypot — bots love to fill hidden fields.
		if ( ! empty( $_POST['evolve_hp'] ) ) {
			wp_send_json_success( [ 'message' => 'Subscribed.' ] ); // pretend OK
		}

		$payload = [
			'email'             => $email,
			'name'              => sanitize_text_field( wp_unslash( $_POST['name']  ?? '' ) ),
			'phone'             => sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) ),
			'gdpr_consent'      => true,
			'source_type'       => 'cta',
			'source_url'        => esc_url_raw( wp_unslash( $_POST['source_url']        ?? wp_get_referer() ?: '' ) ),
			'source_page_title' => sanitize_text_field( wp_unslash( $_POST['source_page_title'] ?? '' ) ),
			'intent_tags'       => $this->parse_tags( $_POST['tags'] ?? '' ),
		];

		$source_label = sanitize_text_field( wp_unslash( $_POST['source_label'] ?? '' ) );
		if ( $source_label ) {
			$payload['intent_tags'][] = 'evolve:' . $source_label;
		}
		$payload['intent_tags'] = array_values( array_unique( $payload['intent_tags'] ) );

		// 1. Forward to GhostPilot's Ghost-Convert CRM if installed
		if ( self::ghostpilot_available() ) {
			$result = $this->forward_to_ghostpilot( $payload );
			if ( ! is_wp_error( $result ) ) {
				$this->log_local( $payload, [ 'forwarded' => 'ghostpilot', 'lead_id' => $result['lead_id'] ?? 0 ] );
				wp_send_json_success( [
					'message' => 'Subscribed! Check your inbox.',
					'source'  => 'ghostpilot',
					'lead_id' => $result['lead_id'] ?? null,
				] );
			}
			// GhostPilot threw — fall through to local logging so we still capture.
			$forward_error = $result->get_error_message();
		}

		// 2. Local fallback
		$this->log_local( $payload, [ 'forwarded' => 'local', 'note' => $forward_error ?? '' ] );
		$this->notify_admin( $payload );

		wp_send_json_success( [
			'message' => 'Subscribed! We\'ll be in touch.',
			'source'  => 'local',
		] );
	}

	/* ----------------------------------------------------------------- */

	private function parse_tags( $raw ) {
		if ( is_array( $raw ) ) {
			$raw = array_map( 'sanitize_text_field', wp_unslash( $raw ) );
		} else {
			$raw = array_filter( array_map( 'trim', explode( ',', sanitize_text_field( wp_unslash( $raw ) ) ) ) );
		}
		return array_values( array_filter( $raw ) );
	}

	private function forward_to_ghostpilot( $payload ) {
		if ( ! function_exists( 'rest_do_request' ) ) {
			return new \WP_Error( 'no_rest', 'WP REST API unavailable' );
		}

		$req = new \WP_REST_Request( 'POST', '/ghostpilot/v1/ghost-convert/capture' );
		$req->set_header( 'Content-Type', 'application/json' );
		$req->set_body( wp_json_encode( $payload ) );

		try {
			$res = rest_do_request( $req );
		} catch ( \Throwable $e ) {
			return new \WP_Error( 'forward_exception', $e->getMessage() );
		}

		if ( ! $res ) {
			return new \WP_Error( 'forward_null', 'No response from GhostPilot' );
		}
		if ( $res->is_error() ) {
			$data = $res->get_data();
			$msg  = is_array( $data ) ? ( $data['message'] ?? $data['error'] ?? 'GhostPilot error' ) : 'GhostPilot error';
			return new \WP_Error( 'forward_error', $msg );
		}

		$data = $res->get_data();
		if ( is_array( $data ) && isset( $data['error'] ) ) {
			return new \WP_Error( 'forward_response_error', $data['error'] );
		}

		return $data;
	}

	private function log_local( $payload, $meta = [] ) {
		$post_id = wp_insert_post( [
			'post_type'   => self::LOG_CPT,
			'post_title'  => sprintf( '%s — %s', $payload['email'], $payload['name'] ?: 'guest' ),
			'post_status' => 'private',
			'post_content' => isset( $payload['intent_tags'] ) ? implode( ', ', $payload['intent_tags'] ) : '',
		], true );
		if ( is_wp_error( $post_id ) ) { return; }

		foreach ( array_merge( $payload, $meta ) as $k => $v ) {
			update_post_meta( $post_id, $k, is_array( $v ) ? wp_json_encode( $v ) : $v );
		}
	}

	private function notify_admin( $payload ) {
		if ( self::ghostpilot_available() ) { return; } // Ghost-Convert handles its own notifications
		$to      = get_option( 'admin_email' );
		$subject = '[Evolve] New newsletter subscriber';
		$body    = "Email: {$payload['email']}\nName: {$payload['name']}\nPhone: {$payload['phone']}\nSource: {$payload['source_url']}\nTags: " . implode( ', ', (array) $payload['intent_tags'] );
		wp_mail( $to, $subject, $body );
	}

	/* ----------------------------------------------------------------- */

	public function shortcode( $atts = [], $content = '' ) {
		$atts = shortcode_atts( [
			'style'         => 'card',          // card | inline | stacked
			'eyebrow'       => 'STAY IN THE LOOP',
			'title'         => 'Get new drops & deals in your inbox',
			'lede'          => 'One short email a week. New flavors, restocks, and Pheasant Lane Mall events. No spam — unsubscribe any time.',
			'button'        => 'Subscribe →',
			'placeholder'   => 'you@email.com',
			'show_name'     => 'no',
			'show_phone'    => 'no',
			'consent'       => 'yes',
			'success'       => 'Subscribed! Check your inbox.',
			'tags'          => '',
			'source'        => 'shortcode',
			'class'         => '',
		], $atts, 'evolve_subscribe' );

		return self::render( $atts );
	}

	/**
	 * Render the subscribe form. Shared by the shortcode and the Elementor widget.
	 * Caller passes a flat associative array of settings (see shortcode defaults).
	 */
	public static function render( $s ) {
		// Ensure assets are present whenever the markup renders.
		wp_enqueue_style( 'evolve-subscribe' );
		wp_enqueue_script( 'evolve-subscribe' );

		$style       = in_array( $s['style'] ?? 'card', [ 'card', 'inline', 'stacked' ], true ) ? $s['style'] : 'card';
		$show_name   = ( $s['show_name']  ?? 'no' )  === 'yes';
		$show_phone  = ( $s['show_phone'] ?? 'no' )  === 'yes';
		$consent     = ( $s['consent']    ?? 'yes' ) === 'yes';
		$tags        = $s['tags'] ?? '';
		$source      = $s['source'] ?? 'evolve';
		$extra_class = trim( ( $s['class'] ?? '' ) . ' evolve-subscribe--' . $style );

		$data = [
			'success' => $s['success']  ?? 'Subscribed! Check your inbox.',
			'tags'    => $tags,
			'source'  => $source,
		];

		$page_id   = get_queried_object_id();
		$page_title= $page_id ? get_the_title( $page_id ) : '';

		ob_start();
		?>
		<form class="evolve-subscribe <?php echo esc_attr( $extra_class ); ?>" method="post" novalidate
		      data-evolve-subscribe='<?php echo esc_attr( wp_json_encode( $data ) ); ?>'>

			<?php if ( ! empty( $s['eyebrow'] ) || ! empty( $s['title'] ) || ! empty( $s['lede'] ) ) : ?>
			<div class="evolve-subscribe__copy">
				<?php if ( ! empty( $s['eyebrow'] ) ) : ?>
					<span class="evolve-subscribe__eyebrow"><?php echo esc_html( $s['eyebrow'] ); ?></span>
				<?php endif; ?>
				<?php if ( ! empty( $s['title'] ) ) : ?>
					<h3 class="evolve-subscribe__title"><?php echo wp_kses_post( $s['title'] ); ?></h3>
				<?php endif; ?>
				<?php if ( ! empty( $s['lede'] ) ) : ?>
					<p class="evolve-subscribe__lede"><?php echo wp_kses_post( $s['lede'] ); ?></p>
				<?php endif; ?>
			</div>
			<?php endif; ?>

			<div class="evolve-subscribe__fields">
				<?php if ( $show_name ) : ?>
					<label class="evolve-subscribe__field">
						<span class="evolve-subscribe__label">Name</span>
						<input type="text" name="name" autocomplete="name" placeholder="Your name">
					</label>
				<?php endif; ?>

				<label class="evolve-subscribe__field evolve-subscribe__field--email">
					<span class="evolve-subscribe__label">Email</span>
					<input type="email" name="email" required autocomplete="email"
					       placeholder="<?php echo esc_attr( $s['placeholder'] ?? 'you@email.com' ); ?>">
				</label>

				<?php if ( $show_phone ) : ?>
					<label class="evolve-subscribe__field">
						<span class="evolve-subscribe__label">Phone (optional)</span>
						<input type="tel" name="phone" autocomplete="tel" placeholder="(603) 555-0100">
					</label>
				<?php endif; ?>

				<input type="hidden" name="source_label"      value="<?php echo esc_attr( $source ); ?>">
				<input type="hidden" name="source_url"        value="<?php echo esc_attr( get_permalink( $page_id ) ?: home_url( add_query_arg( null, null ) ) ); ?>">
				<input type="hidden" name="source_page_title" value="<?php echo esc_attr( $page_title ); ?>">
				<input type="hidden" name="tags"              value="<?php echo esc_attr( $tags ); ?>">
				<input type="hidden" name="consent_required"  value="<?php echo $consent ? '1' : '0'; ?>">
				<input type="text" name="evolve_hp" tabindex="-1" autocomplete="off" class="evolve-subscribe__hp" aria-hidden="true">

				<button type="submit" class="evolve-subscribe__btn">
					<span class="evolve-subscribe__btn-label"><?php echo esc_html( $s['button'] ?? 'Subscribe →' ); ?></span>
					<span class="evolve-subscribe__spinner" aria-hidden="true" hidden></span>
				</button>
			</div>

			<?php if ( $consent ) : ?>
			<label class="evolve-subscribe__consent">
				<input type="checkbox" name="consent" required>
				<span>I agree to receive emails from Evolve Vapor. Unsubscribe any time.</span>
			</label>
			<?php endif; ?>

			<div class="evolve-subscribe__msg" hidden role="status" aria-live="polite"></div>
		</form>
		<?php
		return ob_get_clean();
	}
}
