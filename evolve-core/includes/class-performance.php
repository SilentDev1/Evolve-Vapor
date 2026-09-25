<?php
/**
 * Evolve Performance
 *
 * Front-end weight trims that don't change what customers see:
 *
 * 1. reCAPTCHA only where a form needs it. "No CAPTCHA reCAPTCHA for WooCommerce"
 *    prints api.js in wp_head on every page and "User Registration for WooCommerce"
 *    (Addify) enqueues a second copy plus its own JS/CSS sitewide. The only forms
 *    that use them are the WooCommerce login/registration (My Account) and
 *    checkout, so everything else skips ~400 KB of Google scripts.
 * 2. One Inter stylesheet. The child theme already loads Inter 300–700; Elementor
 *    was adding a second request for all 18 weights/italics.
 * 3. /shop/ → the WooCommerce shop page (premium-vape-products). Old links and
 *    habit URLs were landing on a 404.
 * 4. WebP where available. When an uploaded PNG/JPEG has a "<file>.webp" sibling
 *    (e.g. Kits-1024x1024.png.webp), image src/srcset point at it instead. The
 *    originals stay untouched, so deleting the .webp file reverts that image.
 *    The homepage banners/category tiles were 0.9–1.5 MB PNGs each.
 */
namespace Evolve_Core;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Performance {

	public function __construct() {
		add_action( 'wp',                           [ $this, 'trim_recaptcha' ] );
		add_action( 'wp_enqueue_scripts',           [ $this, 'dequeue_registration_assets' ], 100 );
		add_filter( 'elementor/frontend/print_google_fonts', [ $this, 'skip_duplicate_inter' ] );
		add_action( 'template_redirect',            [ $this, 'redirect_shop_slug' ], 1 );

		if ( ! is_admin() ) {
			add_filter( 'wp_get_attachment_image_src', [ $this, 'webp_image_src' ] );
			add_filter( 'wp_calculate_image_srcset',   [ $this, 'webp_srcset' ] );
		}
	}

	public function webp_image_src( $image ) {
		if ( is_array( $image ) && ! empty( $image[0] ) ) {
			$image[0] = $this->webp_url( $image[0] );
		}
		return $image;
	}

	public function webp_srcset( $sources ) {
		if ( is_array( $sources ) ) {
			foreach ( $sources as $w => $source ) {
				if ( ! empty( $source['url'] ) ) {
					$sources[ $w ]['url'] = $this->webp_url( $source['url'] );
				}
			}
		}
		return $sources;
	}

	/** Uploads URL of a .png/.jpg → its .webp sibling when that file exists. */
	private function webp_url( $url ) {
		static $uploads = null, $seen = [];
		if ( isset( $seen[ $url ] ) ) {
			return $seen[ $url ];
		}
		if ( $uploads === null ) {
			$uploads = wp_get_upload_dir();
		}
		$result = $url;
		$base   = set_url_scheme( $uploads['baseurl'] );
		$check  = set_url_scheme( $url );
		if ( preg_match( '/\.(png|jpe?g)$/i', $check ) && strpos( $check, $base . '/' ) === 0 ) {
			$file = $uploads['basedir'] . substr( $check, strlen( $base ) );
			if ( strpos( $file, '..' ) === false && is_file( $file . '.webp' ) ) {
				$result = $url . '.webp';
			}
		}
		return $seen[ $url ] = $result;
	}

	/** Pages that render a WooCommerce login/registration/checkout form. */
	private function needs_captcha() {
		if ( ! function_exists( 'is_account_page' ) ) {
			return true; // WooCommerce missing — leave the plugins alone.
		}
		return is_account_page() || is_checkout();
	}

	public function trim_recaptcha() {
		if ( is_admin() || $this->needs_captcha() ) {
			return;
		}
		if ( class_exists( 'WC_Ncr_No_Captcha_Recaptcha' ) ) {
			remove_action( 'wp_head', [ 'WC_Ncr_No_Captcha_Recaptcha', 'header_script' ] );
		}
	}

	public function dequeue_registration_assets() {
		if ( $this->needs_captcha() ) {
			return;
		}
		foreach ( [ 'Google reCaptcha JS', 'afreg-front-js', 'color-spectrum-js' ] as $handle ) {
			wp_dequeue_script( $handle );
		}
		foreach ( [ 'afreg-front-css', 'color-spectrum-css' ] as $handle ) {
			wp_dequeue_style( $handle );
		}
	}

	/**
	 * Only drop Elementor's Google Fonts when the child theme's Inter stylesheet is
	 * enqueued, so switching themes can never leave Elementor text unstyled.
	 */
	public function skip_duplicate_inter( $print ) {
		return wp_style_is( 'evolve-fonts', 'enqueued' ) ? false : $print;
	}

	public function redirect_shop_slug() {
		if ( ! is_404() || ! function_exists( 'wc_get_page_id' ) ) {
			return;
		}
		$path = trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
		if ( $path !== 'shop' ) {
			return;
		}
		$shop_id = wc_get_page_id( 'shop' );
		if ( $shop_id > 0 ) {
			wp_safe_redirect( get_permalink( $shop_id ), 301 );
			exit;
		}
	}
}
