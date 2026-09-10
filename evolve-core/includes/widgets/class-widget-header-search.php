<?php
/**
 * Evolve — Header Search (custom Elementor widget)
 *
 * A compact search input that expands into a glass-panel dropdown with
 * live AJAX product results (image + title + price), a category quick-link
 * row, and a "View all results for X" button. Mobile breaks into a full-
 * screen overlay.
 */
namespace Evolve_Core\Widgets;

if ( ! defined( 'ABSPATH' ) ) { exit; }

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

class Widget_Header_Search extends Widget_Base {

	public function get_name()       { return 'evolve-header-search'; }
	public function get_title()      { return esc_html__( 'Evolve Header Search', 'evolve-core' ); }
	public function get_icon()       { return 'eicon-search'; }
	public function get_categories() { return [ 'evolve' ]; }
	public function get_keywords()   { return [ 'search', 'live', 'ajax', 'evolve', 'header' ]; }
	public function get_style_depends()  { return [ 'evolve-header-search' ]; }
	public function get_script_depends() { return [ 'evolve-header-search' ]; }

	protected function register_controls() {

		$this->start_controls_section( 'sec_content', [
			'label' => esc_html__( 'Content', 'evolve-core' ),
			'tab'   => Controls_Manager::TAB_CONTENT,
		] );

		$this->add_control( 'placeholder', [
			'label'   => 'Placeholder',
			'type'    => Controls_Manager::TEXT,
			'default' => 'Looking for?',
		] );
		$this->add_control( 'view_all_label', [
			'label'   => 'View-all Button Label',
			'type'    => Controls_Manager::TEXT,
			'default' => 'View all results',
		] );
		$this->add_control( 'empty_label', [
			'label'   => 'No-results Message',
			'type'    => Controls_Manager::TEXT,
			'default' => 'No products match your search.',
		] );
		$this->add_control( 'min_chars', [
			'label' => 'Minimum Characters to Search',
			'type'  => Controls_Manager::NUMBER,
			'default' => 2,
			'min'     => 1, 'max' => 6,
		] );
		$this->add_control( 'limit', [
			'label' => 'Max Results',
			'type'  => Controls_Manager::NUMBER,
			'default' => 8,
			'min'     => 4, 'max' => 24,
		] );
		$this->add_control( 'show_categories', [
			'label' => 'Show Category Quick-links',
			'type'  => Controls_Manager::SWITCHER,
			'default' => 'yes', 'return_value' => 'yes',
		] );
		$this->add_control( 'show_price', [
			'label' => 'Show Price',
			'type'  => Controls_Manager::SWITCHER,
			'default' => 'yes', 'return_value' => 'yes',
		] );
		$this->add_control( 'columns', [
			'label'   => 'Result Columns (desktop)',
			'type'    => Controls_Manager::SELECT,
			'default' => '4',
			'options' => [ '2'=>'2','3'=>'3','4'=>'4','5'=>'5' ],
		] );

		$this->add_control( 'mobile_popup', [
			'label'        => __( 'Mobile Popup Mode', 'evolve-core' ),
			'description'  => __( 'On phones, replace the search pill with a single search icon. Tap it to open a full-screen search overlay sized to the viewport.', 'evolve-core' ),
			'type'         => Controls_Manager::SWITCHER,
			'default'      => 'yes',
			'return_value' => 'yes',
		] );

		$this->add_control( 'mobile_breakpoint', [
			'label'   => __( 'Mobile Breakpoint (px)', 'evolve-core' ),
			'type'    => Controls_Manager::NUMBER,
			'default' => 768,
			'min'     => 360, 'max' => 1024,
			'condition' => [ 'mobile_popup' => 'yes' ],
		] );

		$this->add_control( 'mobile_columns', [
			'label'   => __( 'Result Columns (mobile)', 'evolve-core' ),
			'type'    => Controls_Manager::SELECT,
			'default' => '1',
			'options' => [ '1'=>'1','2'=>'2' ],
			'condition' => [ 'mobile_popup' => 'yes' ],
		] );

		$omni_active = \Evolve_Core\Search::omnisuggest_available();
		$this->add_control( 'use_omnisuggest', [
			'label'        => 'Use OmniSuggest AI',
			'type'         => Controls_Manager::SWITCHER,
			'description'  => $omni_active
				? __( 'OmniSuggest AI is active. Searches will be AI-ranked.', 'evolve-core' )
				: __( 'OmniSuggest AI plugin not detected — toggle ignored until installed.', 'evolve-core' ),
			'default'      => 'yes',
			'return_value' => 'yes',
		] );
		$this->add_control( 'show_ai_badge', [
			'label'        => 'Show "AI" Badge on Results',
			'type'         => Controls_Manager::SWITCHER,
			'default'      => 'yes',
			'return_value' => 'yes',
			'condition'    => [ 'use_omnisuggest' => 'yes' ],
		] );

		$this->end_controls_section();

		/* ---- Style ---- */
		$this->start_controls_section( 'sec_style', [
			'label' => esc_html__( 'Style', 'evolve-core' ),
			'tab'   => Controls_Manager::TAB_STYLE,
		] );

		$this->add_control( 'input_bg', [
			'label' => 'Input Background',
			'type'  => Controls_Manager::COLOR,
			'default' => 'rgba(255,255,255,0.04)',
			'selectors' => [ '{{WRAPPER}} .evolve-search__input' => 'background-color: {{VALUE}};' ],
		] );
		$this->add_control( 'input_border', [
			'label' => 'Input Border',
			'type'  => Controls_Manager::COLOR,
			'default' => '#1E1E1E',
			'selectors' => [ '{{WRAPPER}} .evolve-search__input' => 'border-color: {{VALUE}};' ],
		] );
		$this->add_control( 'accent', [
			'label' => 'Accent (neon)',
			'type'  => Controls_Manager::COLOR,
			'default' => '#B7FF00',
			'selectors' => [ '{{WRAPPER}}' => '--evolve-search-accent: {{VALUE}};' ],
		] );
		$this->add_control( 'dropdown_bg', [
			'label' => 'Dropdown Background',
			'type'  => Controls_Manager::COLOR,
			'default' => 'rgba(10,10,10,0.92)',
			'selectors' => [ '{{WRAPPER}} .evolve-search__dropdown' => 'background-color: {{VALUE}};' ],
		] );
		$this->add_control( 'dropdown_width', [
			'label' => 'Dropdown Width (px)',
			'type'  => Controls_Manager::NUMBER,
			'default' => 720,
			'min' => 320, 'max' => 1200,
			'selectors' => [ '{{WRAPPER}} .evolve-search__dropdown' => 'width: {{VALUE}}px;' ],
		] );

		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		$mobile_popup = ( $s['mobile_popup'] ?? 'yes' ) === 'yes';
		$mobile_bp    = max( 360, min( 1024, (int) ( $s['mobile_breakpoint'] ?? 768 ) ) );
		$mobile_cols  = max( 1, min( 2,  (int) ( $s['mobile_columns'] ?? 1 ) ) );

		$cfg = [
			'min'        => max( 1, (int) $s['min_chars'] ),
			'limit'      => max( 1, (int) $s['limit'] ),
			'columns'    => (int) $s['columns'],
			'mcols'      => $mobile_cols,
			'mpopup'     => $mobile_popup,
			'mbp'        => $mobile_bp,
			'price'      => $s['show_price'] === 'yes',
			'cats'       => $s['show_categories'] === 'yes',
			'view'       => $s['view_all_label'],
			'empty'      => $s['empty_label'],
			'ai'         => $s['use_omnisuggest'] === 'yes' && \Evolve_Core\Search::omnisuggest_available(),
			'ajax'       => admin_url( 'admin-ajax.php' ),
			'nonce'      => wp_create_nonce( 'evolve_search' ),
			'shop'       => function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' ),
		];

		$top_cats = [];
		if ( $cfg['cats'] ) {
			$top_cats = get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => true, 'number' => 6, 'parent' => 0, 'orderby' => 'count', 'order' => 'DESC' ] );
		}
		?>
		<div class="evolve-search <?php echo $mobile_popup ? 'evolve-search--mobile-popup' : ''; ?>"
		     style="--evolve-search-mbp: <?php echo (int) $mobile_bp; ?>px;"
		     data-evolve-search='<?php echo wp_json_encode( $cfg ); ?>'>

			<?php if ( $mobile_popup ) : ?>
			<button type="button" class="evolve-search__mobile-trigger" aria-label="Open search" data-evolve-search-open>
				<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"></circle><path d="M21 21l-4.3-4.3"></path></svg>
			</button>
			<?php endif; ?>

			<form class="evolve-search__form" role="search" method="get" action="<?php echo esc_url( $cfg['shop'] ); ?>">
				<?php if ( $mobile_popup ) : ?>
				<button type="button" class="evolve-search__mobile-close" aria-label="Close search" data-evolve-search-close>
					<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M6 6l12 12M6 18L18 6"/></svg>
				</button>
				<?php endif; ?>
				<svg class="evolve-search__icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"></circle><path d="M21 21l-4.3-4.3"></path></svg>
				<input class="evolve-search__input"
					type="search"
					name="s"
					placeholder="<?php echo esc_attr( $s['placeholder'] ); ?>"
					autocomplete="off"
					spellcheck="false"
					aria-label="Search products">
				<input type="hidden" name="post_type" value="product">
				<button type="button" class="evolve-search__clear" aria-label="Clear search">
					<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M6 6l12 12M6 18L18 6"/></svg>
				</button>
			</form>

			<div class="evolve-search__dropdown" hidden>
				<?php if ( $cfg['cats'] && ! empty( $top_cats ) && ! is_wp_error( $top_cats ) ) : ?>
					<div class="evolve-search__cats">
						<span class="evolve-search__cats-label">Popular:</span>
						<?php foreach ( $top_cats as $c ) : ?>
							<a href="<?php echo esc_url( get_term_link( $c ) ); ?>" class="evolve-search__cat"><?php echo esc_html( $c->name ); ?></a>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<div class="evolve-search__results" data-columns="<?php echo (int) $cfg['columns']; ?>">
					<!-- Idle state -->
					<div class="evolve-search__hint">
						<span>Start typing to search products…</span>
					</div>
				</div>

				<a href="#" class="evolve-search__view-all" data-template="<?php echo esc_attr( $cfg['view'] ); ?>" hidden>
					<span class="evolve-search__view-all-text"></span>
					<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
				</a>
			</div>
		</div>
		<?php
	}
}
