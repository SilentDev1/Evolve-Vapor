<?php
/**
 * Product category pages: an H1 and a short intro above the products, a longer section and
 * an FAQ below them, plus matching FAQPage JSON-LD. The Elementor product-archive template
 * printed neither a title nor the category description.
 *
 * Content lives on the category itself (WooCommerce → Products → Categories):
 *   - Description           → the intro under the H1
 *   - term meta evolve_h1   → the H1 (falls back to the category name)
 *   - term meta evolve_more → HTML shown below the products
 *   - term meta evolve_faq  → [ [question, answer], … ]
 *
 * Only on the first page of a category, so paginated pages don't repeat it.
 *
 * @package Evolve_Core
 */

namespace Evolve_Core;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Category_Seo {

	public function __construct() {
		add_action( 'elementor/theme/before_do_archive', [ $this, 'top' ] );
		add_action( 'elementor/theme/after_do_archive', [ $this, 'bottom' ] );
		add_action( 'wp_footer', [ $this, 'faq_schema' ], 20 );
		add_action( 'wp_head', [ $this, 'styles' ], 30 );
	}

	private function term() {
		if ( ! function_exists( 'is_product_category' ) || ! is_product_category() ) {
			return null;
		}
		$t = get_queried_object();
		return $t instanceof \WP_Term ? $t : null;
	}

	public function top() {
		$t = $this->term();
		if ( ! $t ) {
			return;
		}
		$h1    = trim( (string) get_term_meta( $t->term_id, 'evolve_h1', true ) ) ?: $t->name;
		$intro = is_paged() ? '' : trim( (string) $t->description );
		echo '<header class="evolve-cat-head"><h1 class="evolve-cat-head__title">' . esc_html( $h1 ) . '</h1>';
		if ( $intro !== '' ) {
			echo '<p class="evolve-cat-head__intro">' . wp_kses_post( $intro ) . '</p>';
		}
		echo '</header>';
	}

	public function bottom() {
		$t = $this->term();
		if ( ! $t || is_paged() ) {
			return;
		}
		$more = (string) get_term_meta( $t->term_id, 'evolve_more', true );
		$faq  = $this->faq( $t );
		if ( $more === '' && ! $faq ) {
			return;
		}
		echo '<section class="evolve-cat-more">';
		if ( $more !== '' ) {
			echo wp_kses_post( $more );
		}
		if ( $faq ) {
			echo '<h2>' . esc_html__( 'Frequently asked questions', 'evolve-core' ) . '</h2><div class="evolve-cat-faq">';
			foreach ( $faq as $q ) {
				echo '<details><summary>' . esc_html( $q[0] ) . '</summary><p>' . esc_html( $q[1] ) . '</p></details>';
			}
			echo '</div>';
		}
		echo '</section>';
	}

	private function faq( \WP_Term $t ) : array {
		$faq = get_term_meta( $t->term_id, 'evolve_faq', true );
		return is_array( $faq ) ? array_values( array_filter( $faq, function ( $q ) { return is_array( $q ) && ! empty( $q[0] ) && ! empty( $q[1] ); } ) ) : [];
	}

	public function faq_schema() {
		$t = $this->term();
		if ( ! $t || is_paged() ) {
			return;
		}
		$faq = $this->faq( $t );
		if ( ! $faq ) {
			return;
		}
		$schema = [
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'mainEntity' => array_map( function ( $q ) {
				return [ '@type' => 'Question', 'name' => $q[0], 'acceptedAnswer' => [ '@type' => 'Answer', 'text' => $q[1] ] ];
			}, $faq ),
		];
		echo '<script type="application/ld+json" id="evolve-category-faq">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
	}

	public function styles() {
		if ( ! $this->term() ) {
			return;
		}
		?>
		<style id="evolve-category-seo">
			.evolve-cat-head { max-width: 1200px; margin: 28px auto 8px; padding: 0 20px; }
			.evolve-cat-head__title { font-family: 'Poppins', 'Inter', sans-serif; font-weight: 800; font-size: clamp(1.6rem, 4vw, 2.4rem); line-height: 1.15; color: #fff; margin: 0 0 8px; }
			.evolve-cat-head__intro { color: #B3B3B3; font-size: 15px; line-height: 1.6; max-width: 820px; margin: 0; }
			.evolve-cat-more { max-width: 1200px; margin: 40px auto; padding: 0 20px; color: #B3B3B3; line-height: 1.7; }
			.evolve-cat-more h2 { color: #fff; font-size: 1.3rem; margin: 24px 0 8px; }
			.evolve-cat-more a { color: #B7FF00; }
			.evolve-cat-faq details { border-top: 1px solid rgba(255,255,255,.1); padding: 12px 0; }
			.evolve-cat-faq summary { color: #fff; font-weight: 600; cursor: pointer; }
			.evolve-cat-faq p { margin: 8px 0 0; }
		</style>
		<?php
	}
}
