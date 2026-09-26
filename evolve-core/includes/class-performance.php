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
 * 5. (1.11) Nothing that blocks the first paint without need: the Inter stylesheet loads
 *    without holding up rendering (fonts.gstatic is preconnected), and the WordPress block
 *    stylesheets are dropped — no page on the site is built with blocks.
 * 6. (1.11) No hover-zoom on product images: it downloads the full-size original of every
 *    product photo (hundreds of KB, off screen) and does nothing on phones. The lightbox stays.
 * 7. (1.11) Home page: the browser is told to fetch the hero banner first (it's the largest
 *    thing on screen), a phone-sized one on phones.
 * 8. (1.12.1) Product pages: the main product photo is fetched first (preload + high priority).
 *    Home category tiles say how wide they really are, so phones stop downloading 768–1024px
 *    images for ~170px tiles.
 * 9. (1.12.2) WooCommerce hides the product gallery (opacity 0) until its gallery script has
 *    run, which held the product photo back ~4s on phones. It's shown straight away.
 */
namespace Evolve_Core;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Performance {

	public function __construct() {
		add_action( 'wp',                           [ $this, 'trim_recaptcha' ] );
		add_action( 'wp_enqueue_scripts',           [ $this, 'dequeue_registration_assets' ], 100 );
		add_filter( 'elementor/frontend/print_google_fonts', [ $this, 'skip_duplicate_inter' ] );
		add_filter( 'style_loader_tag',             [ $this, 'async_fonts' ], 10, 4 );
		add_action( 'wp_head',                      [ $this, 'preconnect_and_preload' ], 1 );
		add_action( 'wp_enqueue_scripts',           [ $this, 'drop_block_styles' ], 200 );
		add_action( 'after_setup_theme',            [ $this, 'no_zoom' ], 100 );
		add_filter( 'wp_get_attachment_image_attributes', [ $this, 'image_hints' ], 20, 2 );
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
	/** Google Fonts CSS without blocking the first paint (text shows in the fallback font, then Inter). */
	public function async_fonts( $tag, $handle, $href, $media ) {
		if ( is_admin() || strpos( (string) $href, 'fonts.googleapis.com' ) === false ) {
			return $tag;
		}
		$href = esc_url( $href );
		return '<link rel="preload" as="style" href="' . $href . '" />' . "\n"
			. '<link rel="stylesheet" id="' . esc_attr( $handle ) . '-css" href="' . $href . '" media="print" onload="this.media=\'all\'" />' . "\n"
			. '<noscript><link rel="stylesheet" href="' . $href . '" /></noscript>' . "\n";
	}

	public function preconnect_and_preload() {
		if ( is_admin() ) {
			return;
		}
		echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />' . "\n";
		if ( function_exists( 'is_product' ) && is_product() ) {
			echo '<style id="evolve-gallery-visible">.woocommerce-product-gallery{opacity:1!important}</style>' . "\n";
		}
		if ( function_exists( 'is_product' ) && is_product() && ( $tid = get_post_thumbnail_id() ) ) {
			$src = wp_get_attachment_image_src( $tid, 'woocommerce_single' );
			if ( $src ) {
				printf( '<link rel="preload" as="image" href="%s" fetchpriority="high" />' . "\n", esc_url( $src[0] ) );
			}
		}
		if ( is_front_page() ) {
			$hero = (array) apply_filters( 'evolve_hero_preload', get_option( 'evolve_hero_preload', [] ) );
			if ( ! empty( $hero['desktop'] ) ) {
				$mobile = $hero['mobile'] ?? '';
				if ( $mobile ) {
					printf( '<link rel="preload" as="image" href="%s" media="(max-width: 767px)" fetchpriority="high" />' . "\n", esc_url( $mobile ) );
					printf( '<link rel="preload" as="image" href="%s" media="(min-width: 768px)" fetchpriority="high" />' . "\n", esc_url( $hero['desktop'] ) );
				} else {
					printf( '<link rel="preload" as="image" href="%s" fetchpriority="high" />' . "\n", esc_url( $hero['desktop'] ) );
				}
			}
		}
	}

	/** Home category tiles (Coils, Disposable, E-Juice, Kits, Pods, Salt). */
	const TILE_IDS = [ 34816, 34817, 34818, 34819, 34820, 34821 ];

	public function image_hints( $attr, $attachment ) {
		if ( is_admin() || ! $attachment ) {
			return $attr;
		}
		if ( is_front_page() && in_array( (int) $attachment->ID, self::TILE_IDS, true ) ) {
			$attr['sizes'] = '(max-width: 767px) 46vw, (max-width: 1200px) 17vw, 200px';
		}
		if ( function_exists( 'is_product' ) && is_product() && (int) $attachment->ID === (int) get_post_thumbnail_id() && strpos( (string) ( $attr['class'] ?? '' ), 'wp-post-image' ) !== false ) {
			$attr['fetchpriority'] = 'high';
			$attr['loading']       = 'eager';
		}
		return $attr;
	}

	public function drop_block_styles() {
		if ( is_admin() ) {
			return;
		}
		foreach ( [ 'wp-block-library', 'wp-block-library-theme', 'classic-theme-styles', 'global-styles', 'wc-blocks-style' ] as $h ) {
			wp_dequeue_style( $h );
		}
	}

	public function no_zoom() {
		remove_theme_support( 'wc-product-gallery-zoom' );
	}

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
