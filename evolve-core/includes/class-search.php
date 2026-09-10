<?php
/**
 * AJAX handler for the Evolve Header Search widget.
 *
 * Routing:
 *  - If OmniSuggest AI (Cao-Tech LLC) is active AND the widget has
 *    "Use OmniSuggest AI" enabled, queries are forwarded to
 *    `GET /wp-json/omnisuggest/v1/search-suggest` so we get AI-ranked
 *    matches with per-product `reason` strings. We render those into
 *    Evolve's standard search card markup so styling stays consistent.
 *  - When OmniSuggest is unavailable, errors, or returns zero matches,
 *    we silently fall back to a local WP_Query (`s=`, in-stock).
 *
 * Endpoint: action=evolve_search_products
 * POST args: q, limit, columns, price (1|0), use_ai (1|0), nonce
 * Returns:   { html, found, view_all_url, source: 'omnisuggest'|'local', ai_fallback?: bool }
 */
namespace Evolve_Core;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Search {

	public function __construct() {
		add_action( 'wp_ajax_evolve_search_products',        [ $this, 'ajax' ] );
		add_action( 'wp_ajax_nopriv_evolve_search_products', [ $this, 'ajax' ] );
	}

	public static function omnisuggest_available() {
		return function_exists( 'omnisuggest_is_pro' )
			|| class_exists( 'OmniSuggest_AI_Engine' )
			|| class_exists( 'OmniSuggest_Tracker' );
	}

	public function ajax() {
		check_ajax_referer( 'evolve_search', 'nonce' );

		$q       = sanitize_text_field( wp_unslash( $_POST['q'] ?? '' ) );
		$limit   = max( 1, min( 24, (int) ( $_POST['limit']   ?? 8 ) ) );
		$cols    = max( 2, min( 5,  (int) ( $_POST['columns'] ?? 4 ) ) );
		$price   = ! empty( $_POST['price'] );
		$use_ai  = ! empty( $_POST['use_ai'] ) && self::omnisuggest_available();

		if ( $q === '' ) {
			wp_send_json_success( [ 'html' => '', 'found' => 0, 'view_all_url' => '', 'source' => 'none' ] );
		}

		$matches     = [];
		$source      = 'local';
		$ai_fallback = false;

		if ( $use_ai ) {
			$ai = $this->query_omnisuggest( $q, min( 10, $limit ) );
			if ( $ai && ! empty( $ai['matches'] ) ) {
				$matches     = $ai['matches'];
				$source      = 'omnisuggest';
				$ai_fallback = ! empty( $ai['fallback'] );
			}
		}

		if ( empty( $matches ) ) {
			$matches = $this->local_search( $q, $limit );
			$source  = 'local';
		}

		ob_start();
		if ( ! empty( $matches ) ) {
			$ai_badge = $source === 'omnisuggest';
			echo '<ul class="evolve-search__list" style="--cols:' . (int) $cols . '">';
			foreach ( $matches as $m ) {
				$this->render_card( $m, $price, $ai_badge );
			}
			echo '</ul>';

			if ( $ai_badge && $ai_fallback ) {
				echo '<div class="evolve-search__ai-note">';
				echo '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v3M12 18v3M3 12h3M18 12h3M5.6 5.6l2 2M16.4 16.4l2 2M5.6 18.4l2-2M16.4 7.6l2-2"/></svg>';
				echo 'No direct matches — these are AI-picked alternatives.';
				echo '</div>';
			} elseif ( $ai_badge ) {
				echo '<div class="evolve-search__ai-note evolve-search__ai-note--small"><span class="evolve-search__ai-pill">AI</span> Ranked by OmniSuggest AI for "' . esc_html( $q ) . '"</div>';
			}
		} else {
			echo '<div class="evolve-search__empty">';
			echo '<svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>';
			echo '<p>' . esc_html__( 'No products match your search.', 'evolve-core' ) . '</p>';
			echo '<span>' . esc_html( sprintf( __( 'We searched for "%s"', 'evolve-core' ), $q ) ) . '</span>';
			echo '</div>';
		}

		wp_send_json_success( [
			'html'         => ob_get_clean(),
			'found'        => count( $matches ),
			'view_all_url' => add_query_arg( [ 's' => $q, 'post_type' => 'product' ], home_url( '/' ) ),
			'source'       => $source,
			'ai_fallback'  => $ai_fallback,
		] );
	}

	/* ------------------------------------------------------------------ */

	private function query_omnisuggest( $q, $limit ) {
		if ( ! function_exists( 'rest_do_request' ) ) { return null; }

		$req = new \WP_REST_Request( 'GET', '/omnisuggest/v1/search-suggest' );
		$req->set_query_params( [
			's'     => $q,
			'limit' => $limit,
		] );
		// OmniSuggest's permission_callback accepts a valid `wp_rest` nonce header.
		$req->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );

		$res = rest_do_request( $req );
		if ( ! $res || $res->is_error() ) { return null; }

		$data = $res->get_data();
		if ( ! is_array( $data ) || empty( $data['matches'] ) ) { return null; }

		// Normalize into our internal shape — OmniSuggest already returns
		// id/title/url/image/price_html/in_stock/reason.
		$matches = [];
		foreach ( $data['matches'] as $m ) {
			if ( empty( $m['id'] ) ) { continue; }
			$matches[] = [
				'id'         => (int) $m['id'],
				'title'      => isset( $m['title'] )      ? (string) $m['title']      : '',
				'url'        => isset( $m['url'] )        ? (string) $m['url']        : get_permalink( (int) $m['id'] ),
				'image'      => isset( $m['image'] )      ? (string) $m['image']      : '',
				'price_html' => isset( $m['price_html'] ) ? (string) $m['price_html'] : '',
				'in_stock'   => ! empty( $m['in_stock'] ),
				'reason'     => isset( $m['reason'] )     ? (string) $m['reason']     : '',
				'on_sale'    => $this->product_on_sale( (int) $m['id'] ),
			];
		}
		return [
			'matches'  => $matches,
			'fallback' => ! empty( $data['fallback'] ),
		];
	}

	private function local_search( $q, $limit ) {
		$args = [
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			's'              => $q,
			'no_found_rows'  => true,
			'meta_query'     => [
				[ 'key' => '_stock_status', 'value' => 'instock', 'compare' => '=' ],
			],
		];
		$wp_q = new \WP_Query( $args );
		$out  = [];
		while ( $wp_q->have_posts() ) {
			$wp_q->the_post();
			$p = wc_get_product( get_the_ID() );
			if ( ! $p ) { continue; }
			$out[] = [
				'id'         => $p->get_id(),
				'title'      => $p->get_name(),
				'url'        => get_permalink( $p->get_id() ),
				'image'      => wp_get_attachment_image_url( $p->get_image_id() ?: 0, 'woocommerce_thumbnail' ) ?: wc_placeholder_img_src( 'woocommerce_thumbnail' ),
				'price_html' => $p->get_price_html(),
				'in_stock'   => $p->is_in_stock(),
				'reason'     => '',
				'on_sale'    => $p->is_on_sale(),
			];
		}
		wp_reset_postdata();
		return $out;
	}

	private function product_on_sale( $id ) {
		$p = wc_get_product( $id );
		return $p ? $p->is_on_sale() : false;
	}

	private function render_card( $m, $show_price, $ai_badge ) {
		$img = $m['image']
			? '<img src="' . esc_url( $m['image'] ) . '" alt="' . esc_attr( $m['title'] ) . '" loading="lazy">'
			: '';
		?>
		<li class="evolve-search__item">
			<a href="<?php echo esc_url( $m['url'] ); ?>" class="evolve-search__link">
				<span class="evolve-search__img"><?php echo $img; ?></span>
				<span class="evolve-search__meta">
					<span class="evolve-search__title"><?php echo esc_html( $m['title'] ); ?></span>
					<?php if ( $show_price && ! empty( $m['price_html'] ) ) : ?>
						<span class="evolve-search__price"><?php echo $m['price_html']; ?></span>
					<?php endif; ?>
					<?php if ( ! empty( $m['reason'] ) ) : ?>
						<span class="evolve-search__reason" title="<?php echo esc_attr( $m['reason'] ); ?>"><?php echo esc_html( $this->shorten( $m['reason'], 90 ) ); ?></span>
					<?php endif; ?>
				</span>
				<?php if ( ! empty( $m['on_sale'] ) ) : ?>
					<span class="evolve-search__deal">DEAL</span>
				<?php endif; ?>
				<?php if ( $ai_badge ) : ?>
					<span class="evolve-search__ai" aria-hidden="true" title="Ranked by OmniSuggest AI">AI</span>
				<?php endif; ?>
				<?php if ( empty( $m['in_stock'] ) ) : ?>
					<span class="evolve-search__oos">Out of stock</span>
				<?php endif; ?>
			</a>
		</li>
		<?php
	}

	private function shorten( $s, $len ) {
		$s = trim( wp_strip_all_tags( $s ) );
		return mb_strlen( $s ) > $len ? mb_substr( $s, 0, $len - 1 ) . '…' : $s;
	}
}
