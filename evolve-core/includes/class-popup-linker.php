<?php
/**
 * Evolve Popup Linker
 *
 * Resolves the numeric post ID of the "Evolve — Mobile Menu" popup at runtime
 * and exposes it to the front-end JS via `window.evolveMobile.menuPopupId`.
 *
 * Why: Elementor's popup-open URL syntax requires the popup's actual numeric
 * ID (assigned by WP when the popup is imported / created). Hard-coding it
 * inside the template JSON is impossible — every install gets a different ID.
 *
 * The child theme's evolve-ui.js listens for clicks on the hamburger
 * (`.evolve-mh-icon--menu` / `[data-evolve-trigger="mobile-menu"]`) and calls
 * Elementor Pro's popup API with that ID.
 */
namespace Evolve_Core;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Popup_Linker {

	const TITLE          = 'Evolve — Mobile Menu';
	const OPT_KEY        = 'evolve_mobile_menu_popup_id';
	const VAPE_TITLE     = 'Evolve — Vape Match Quiz';
	const VAPE_OPT_KEY   = 'evolve_vape_match_popup_id';

	public function __construct() {
		add_action( 'wp_enqueue_scripts',          [ $this, 'localize' ], 30 );
		add_action( 'save_post_elementor_library', [ $this, 'invalidate' ] );
		add_action( 'deleted_post',                [ $this, 'invalidate' ] );
	}

	public function localize() {
		$menu_id = $this->get_popup_id_by_title( self::TITLE,      self::OPT_KEY,      'mobile menu' );
		$vape_id = $this->get_popup_id_by_title( self::VAPE_TITLE, self::VAPE_OPT_KEY, 'vape match' );

		$snippet = 'window.evolveMobile = ' . wp_json_encode( [ 'menuPopupId' => $menu_id ] ) . ';' .
		           'window.evolveVapeMatch = ' . wp_json_encode( [ 'popupId' => $vape_id ] ) . ';';

		wp_register_script( 'evolve-popup-linker', '', [], EVOLVE_CORE_VERSION, true );
		wp_enqueue_script(  'evolve-popup-linker' );
		wp_add_inline_script( 'evolve-popup-linker', $snippet, 'after' );
	}

	public function invalidate() {
		delete_option( self::OPT_KEY );
		delete_option( self::VAPE_OPT_KEY );
	}

	/**
	 * Generic popup-by-title lookup with an option cache + fuzzy fallback.
	 */
	private function get_popup_id_by_title( string $title, string $opt_key, string $fuzzy ) : int {
		$cached = (int) get_option( $opt_key, 0 );
		if ( $cached && get_post_status( $cached ) === 'publish' ) {
			return $cached;
		}

		$q = new \WP_Query( [
			'post_type'        => 'elementor_library',
			'post_status'      => 'publish',
			'posts_per_page'   => 1,
			'fields'           => 'ids',
			'title'            => $title,
			'meta_query'       => [ [ 'key' => '_elementor_template_type', 'value' => 'popup' ] ],
			'no_found_rows'    => true,
			'suppress_filters' => true,
		] );
		if ( $q->posts ) {
			$id = (int) $q->posts[0];
			update_option( $opt_key, $id, true );
			return $id;
		}

		$q2 = new \WP_Query( [
			'post_type'      => 'elementor_library',
			'post_status'    => 'publish',
			'posts_per_page' => 5,
			's'              => $fuzzy,
			'fields'         => 'ids',
			'meta_query'     => [ [ 'key' => '_elementor_template_type', 'value' => 'popup' ] ],
			'no_found_rows'  => true,
		] );
		foreach ( (array) $q2->posts as $pid ) {
			$id = (int) $pid;
			update_option( $opt_key, $id, true );
			return $id;
		}

		return 0;
	}

}
