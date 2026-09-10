<?php
/**
 * Evolve — Shop Filter (custom Elementor widget)
 *
 * Renders the dark-luxury AJAX filter panel for the shop archive.
 * Every section (search, categories, price, rating, attributes, sale, in-stock,
 * clear button) is independently toggleable from Elementor's controls.
 */
namespace Evolve_Core\Widgets;

if ( ! defined( 'ABSPATH' ) ) { exit; }

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;

class Widget_Shop_Filter extends Widget_Base {

	public function get_name()       { return 'evolve-shop-filter'; }
	public function get_title()      { return esc_html__( 'Evolve Shop Filter', 'evolve-core' ); }
	public function get_icon()       { return 'eicon-filter'; }
	public function get_categories() { return [ 'woocommerce-elements', 'evolve' ]; }
	public function get_keywords()   { return [ 'filter', 'shop', 'woocommerce', 'product', 'evolve', 'sidebar', 'ajax' ]; }

	public function get_style_depends()  { return [ 'evolve-shop-filter' ]; }
	public function get_script_depends() { return [ 'evolve-shop-filter' ]; }

	/* ====================================================================
	 * Controls
	 * ================================================================= */
	protected function register_controls() {

		/* ---------------- Layout / Sections ---------------- */
		$this->start_controls_section( 'sec_layout', [
			'label' => esc_html__( 'Sections', 'evolve-core' ),
			'tab'   => Controls_Manager::TAB_CONTENT,
		] );

		$this->add_control( 'show_search',     $this->yn( 'Show Search',          'yes' ) );
		$this->add_control( 'show_categories', $this->yn( 'Show Categories',      'yes' ) );
		$this->add_control( 'show_price',      $this->yn( 'Show Price Range',     'yes' ) );
		$this->add_control( 'show_rating',     $this->yn( 'Show Rating Filter',   'yes' ) );
		$this->add_control( 'show_attributes', $this->yn( 'Show Product Attributes (brand, flavor, nicotine…)', 'yes' ) );
		$this->add_control( 'show_sale',       $this->yn( 'Show On-Sale Toggle',  'yes' ) );
		$this->add_control( 'show_stock',      $this->yn( 'Show In-Stock Toggle', 'yes' ) );
		$this->add_control( 'show_clear',      $this->yn( 'Show Clear-All Button','yes' ) );
		$this->add_control( 'collapsible',     $this->yn( 'Collapsible Sections (<details>)', 'yes' ) );
		$this->add_control( 'open_by_default', $this->yn( 'Open Sections By Default', 'yes' ) );

		$this->end_controls_section();

		/* ---------------- Labels ---------------- */
		$this->start_controls_section( 'sec_labels', [
			'label' => esc_html__( 'Labels', 'evolve-core' ),
			'tab'   => Controls_Manager::TAB_CONTENT,
		] );

		$labels = [
			'panel_label'       => [ 'Panel Heading',     'FILTERS' ],
			'search_label'      => [ 'Search Label',      'Search' ],
			'search_placeholder'=> [ 'Search Placeholder','Search products…' ],
			'categories_label'  => [ 'Categories Label',  'Categories' ],
			'price_label'       => [ 'Price Label',       'Price' ],
			'price_min_ph'      => [ 'Price Min Placeholder', 'Min' ],
			'price_max_ph'      => [ 'Price Max Placeholder', 'Max' ],
			'rating_label'      => [ 'Rating Label',      'Rating' ],
			'sale_label'        => [ 'On-Sale Label',     'On sale only' ],
			'stock_label'       => [ 'In-Stock Label',    'In stock only' ],
			'apply_label'       => [ 'Apply Button',      'Apply' ],
			'clear_label'       => [ 'Clear-All Button',  'Clear all' ],
			'mobile_open_label' => [ 'Mobile Open Btn',   'Filters' ],
			'mobile_close_label'=> [ 'Mobile Close Btn',  'Done' ],
		];
		foreach ( $labels as $key => [ $title, $default ] ) {
			$this->add_control( $key, [
				'label'   => $title,
				'type'    => Controls_Manager::TEXT,
				'default' => $default,
			] );
		}

		$this->end_controls_section();

		/* ---------------- Behavior ---------------- */
		$this->start_controls_section( 'sec_behavior', [
			'label' => esc_html__( 'Behavior', 'evolve-core' ),
			'tab'   => Controls_Manager::TAB_CONTENT,
		] );

		$this->add_control( 'ajax_enabled', $this->yn( 'AJAX Filtering', 'yes' ) );

		$this->add_control( 'target_selector', [
			'label'       => esc_html__( 'Target product grid (CSS selector)', 'evolve-core' ),
			'type'        => Controls_Manager::TEXT,
			'default'     => '.woocommerce ul.products, .elementor-widget-woocommerce-archive-products ul.products, .elementor-widget-woocommerce-products ul.products',
			'description' => esc_html__( 'The element whose contents will be replaced with the AJAX results.', 'evolve-core' ),
			'condition'   => [ 'ajax_enabled' => 'yes' ],
		] );

		$this->add_control( 'scroll_to_results', $this->yn( 'Scroll to results after filter', 'yes', [ 'ajax_enabled' => 'yes' ] ) );
		$this->add_control( 'mobile_drawer',     $this->yn( 'Mobile Drawer Mode', 'yes' ) );

		$this->add_control( 'price_min', [
			'label'   => esc_html__( 'Price Min (USD)', 'evolve-core' ),
			'type'    => Controls_Manager::NUMBER,
			'default' => 0,
			'condition' => [ 'show_price' => 'yes' ],
		] );
		$this->add_control( 'price_max', [
			'label'   => esc_html__( 'Price Max (USD)', 'evolve-core' ),
			'type'    => Controls_Manager::NUMBER,
			'default' => 200,
			'condition' => [ 'show_price' => 'yes' ],
		] );
		$this->add_control( 'price_step', [
			'label'   => esc_html__( 'Price Step', 'evolve-core' ),
			'type'    => Controls_Manager::NUMBER,
			'default' => 1,
			'condition' => [ 'show_price' => 'yes' ],
		] );

		$this->add_control( 'categories_hide_empty', $this->yn( 'Hide Empty Categories', 'yes', [ 'show_categories' => 'yes' ] ) );
		$this->add_control( 'categories_show_count', $this->yn( 'Show Category Counts',  'yes', [ 'show_categories' => 'yes' ] ) );

		$this->add_control( 'category_picker', [
			'label'       => esc_html__( 'Categories to Show', 'evolve-core' ),
			'type'        => Controls_Manager::SELECT2,
			'multiple'    => true,
			'options'     => $this->get_category_choices(),
			'default'     => [],
			'description' => esc_html__( 'Leave empty to show every product category. Pick to limit the list.', 'evolve-core' ),
			'condition'   => [ 'show_categories' => 'yes' ],
		] );
		$this->add_control( 'categories_flat', $this->yn( 'Flat List (ignore parent/child hierarchy)', 'no', [ 'show_categories' => 'yes' ] ) );

		$attrs = $this->get_attribute_choices();
		$this->add_control( 'attribute_taxonomies', [
			'label'       => esc_html__( 'Attribute Taxonomies', 'evolve-core' ),
			'type'        => Controls_Manager::SELECT2,
			'multiple'    => true,
			'options'     => $attrs,
			'default'     => array_keys( $attrs ),
			'description' => esc_html__( 'Leave empty to show all registered product attributes.', 'evolve-core' ),
			'condition'   => [ 'show_attributes' => 'yes' ],
		] );

		$this->end_controls_section();

		/* ---------------- Style: Panel ---------------- */
		$this->start_controls_section( 'sec_style_panel', [
			'label' => esc_html__( 'Panel', 'evolve-core' ),
			'tab'   => Controls_Manager::TAB_STYLE,
		] );

		$this->add_control( 'panel_bg', [
			'label' => esc_html__( 'Background', 'evolve-core' ),
			'type'  => Controls_Manager::COLOR,
			'default' => '#111111',
			'selectors' => [ '{{WRAPPER}} .evolve-filter' => 'background-color: {{VALUE}};' ],
		] );
		$this->add_group_control( Group_Control_Border::get_type(), [
			'name'     => 'panel_border',
			'selector' => '{{WRAPPER}} .evolve-filter',
		] );
		$this->add_control( 'panel_radius', [
			'label' => esc_html__( 'Border Radius (px)', 'evolve-core' ),
			'type'  => Controls_Manager::NUMBER,
			'default' => 20,
			'selectors' => [ '{{WRAPPER}} .evolve-filter' => 'border-radius: {{VALUE}}px;' ],
		] );

		$this->end_controls_section();

		/* ---------------- Style: Section Heads ---------------- */
		$this->start_controls_section( 'sec_style_heads', [
			'label' => esc_html__( 'Section Headings', 'evolve-core' ),
			'tab'   => Controls_Manager::TAB_STYLE,
		] );
		$this->add_control( 'head_color', [
			'label' => 'Heading Color',
			'type'  => Controls_Manager::COLOR,
			'default' => '#FFFFFF',
			'selectors' => [ '{{WRAPPER}} .evolve-filter__head, {{WRAPPER}} .evolve-filter__section > summary' => 'color: {{VALUE}};' ],
		] );
		$this->add_control( 'accent_color', [
			'label' => 'Accent Color',
			'type'  => Controls_Manager::COLOR,
			'default' => '#B7FF00',
			'selectors' => [ '{{WRAPPER}}' => '--evolve-filter-accent: {{VALUE}};' ],
		] );
		$this->add_control( 'count_color', [
			'label' => 'Count Color',
			'type'  => Controls_Manager::COLOR,
			'default' => '#6E6E6E',
			'selectors' => [ '{{WRAPPER}} .evolve-filter__count' => 'color: {{VALUE}};' ],
		] );
		$this->end_controls_section();
	}

	/* Helper for boolean (yes/no) switches */
	private function yn( $label, $default = 'yes', $condition = null ) {
		$ctrl = [
			'label'        => $label,
			'type'         => Controls_Manager::SWITCHER,
			'label_on'     => esc_html__( 'Yes', 'evolve-core' ),
			'label_off'    => esc_html__( 'No',  'evolve-core' ),
			'return_value' => 'yes',
			'default'      => $default,
		];
		if ( $condition ) { $ctrl['condition'] = $condition; }
		return $ctrl;
	}

	private function get_attribute_choices() {
		$out = [];
		if ( function_exists( 'wc_get_attribute_taxonomies' ) ) {
			foreach ( wc_get_attribute_taxonomies() as $a ) {
				$tax = wc_attribute_taxonomy_name( $a->attribute_name );
				$out[ $tax ] = $a->attribute_label ?: $a->attribute_name;
			}
		}
		return $out;
	}

	private function get_category_choices() {
		$out = [];
		if ( ! taxonomy_exists( 'product_cat' ) ) { return $out; }
		$terms = get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => false, 'orderby' => 'name' ] );
		if ( is_wp_error( $terms ) ) { return $out; }
		foreach ( $terms as $t ) {
			$indent = '';
			if ( ! empty( $t->parent ) ) {
				$indent = str_repeat( '— ', count( get_ancestors( $t->term_id, 'product_cat' ) ) );
			}
			$out[ $t->slug ] = $indent . $t->name;
		}
		return $out;
	}

	/* ====================================================================
	 * Render
	 * ================================================================= */
	protected function render() {
		if ( ! function_exists( 'WC' ) ) { echo '<p>WooCommerce is required.</p>'; return; }

		$s = $this->get_settings_for_display();
		$state = $this->current_state();

		$collapsible = $s['collapsible'] === 'yes';
		$open        = $s['open_by_default'] === 'yes' ? ' open' : '';
		$tag         = $collapsible ? 'details' : 'div';

		$data = [
			'ajax'    => $s['ajax_enabled'] === 'yes',
			'target'  => $s['target_selector'],
			'scroll'  => $s['scroll_to_results'] === 'yes',
			'mobile'  => $s['mobile_drawer'] === 'yes',
			'action'  => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'evolve_filter' ),
		];
		?>
		<aside class="evolve-filter <?php echo $s['mobile_drawer'] === 'yes' ? 'evolve-filter--drawer' : ''; ?>"
		       data-evolve-filter='<?php echo wp_json_encode( $data ); ?>'>

			<?php if ( $s['mobile_drawer'] === 'yes' ) : ?>
				<button type="button" class="evolve-filter__mobile-trigger" data-evolve-filter-open>
					<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M6 12h12M10 18h4"/></svg>
					<?php echo esc_html( $s['mobile_open_label'] ); ?>
				</button>
			<?php endif; ?>

			<div class="evolve-filter__panel">
				<div class="evolve-filter__topbar">
					<h3 class="evolve-filter__head"><?php echo esc_html( $s['panel_label'] ); ?></h3>
					<?php if ( $s['mobile_drawer'] === 'yes' ) : ?>
						<button type="button" class="evolve-filter__close" data-evolve-filter-close aria-label="Close"><?php echo esc_html( $s['mobile_close_label'] ); ?></button>
					<?php endif; ?>
				</div>

				<form class="evolve-filter__form" method="get" action="<?php echo esc_url( $this->shop_url() ); ?>" novalidate>

					<?php if ( $s['show_search'] === 'yes' ) : ?>
					<<?php echo $tag . $open; ?> class="evolve-filter__section">
						<summary class="evolve-filter__head"><?php echo esc_html( $s['search_label'] ); ?></summary>
						<div class="evolve-filter__body">
							<input type="search" name="evf_q" value="<?php echo esc_attr( $state['evf_q'] ); ?>" placeholder="<?php echo esc_attr( $s['search_placeholder'] ); ?>">
						</div>
					</<?php echo $tag; ?>>
					<?php endif; ?>

					<?php if ( $s['show_categories'] === 'yes' ) : ?>
					<<?php echo $tag . $open; ?> class="evolve-filter__section">
						<summary class="evolve-filter__head"><?php echo esc_html( $s['categories_label'] ); ?></summary>
						<div class="evolve-filter__body">
							<?php $this->render_categories( 0, 0, $state, $s ); ?>
						</div>
					</<?php echo $tag; ?>>
					<?php endif; ?>

					<?php if ( $s['show_price'] === 'yes' ) : ?>
					<<?php echo $tag . $open; ?> class="evolve-filter__section">
						<summary class="evolve-filter__head"><?php echo esc_html( $s['price_label'] ); ?></summary>
						<div class="evolve-filter__body evolve-filter__price">
							<input type="number" name="min_price" min="<?php echo (int) $s['price_min']; ?>" max="<?php echo (int) $s['price_max']; ?>" step="<?php echo (int) $s['price_step']; ?>" value="<?php echo esc_attr( $state['min_price'] ); ?>" placeholder="<?php echo esc_attr( $s['price_min_ph'] ); ?>">
							<span class="evolve-filter__dash">–</span>
							<input type="number" name="max_price" min="<?php echo (int) $s['price_min']; ?>" max="<?php echo (int) $s['price_max']; ?>" step="<?php echo (int) $s['price_step']; ?>" value="<?php echo esc_attr( $state['max_price'] ); ?>" placeholder="<?php echo esc_attr( $s['price_max_ph'] ); ?>">
						</div>
					</<?php echo $tag; ?>>
					<?php endif; ?>

					<?php if ( $s['show_rating'] === 'yes' ) : ?>
					<<?php echo $tag . $open; ?> class="evolve-filter__section">
						<summary class="evolve-filter__head"><?php echo esc_html( $s['rating_label'] ); ?></summary>
						<div class="evolve-filter__body evolve-filter__ratings">
							<?php for ( $r = 5; $r >= 1; $r-- ) : ?>
								<label class="evolve-filter__rating">
									<input type="checkbox" name="evf_rating[]" value="<?php echo $r; ?>" <?php checked( in_array( (string) $r, (array) $state['evf_rating'], true ) ); ?>>
									<span class="evolve-filter__stars" data-rating="<?php echo $r; ?>" aria-label="<?php echo $r; ?> stars and up"><?php echo str_repeat( '★', $r ) . str_repeat( '☆', 5 - $r ); ?></span>
									<span class="evolve-filter__rating-note">&amp; up</span>
								</label>
							<?php endfor; ?>
						</div>
					</<?php echo $tag; ?>>
					<?php endif; ?>

					<?php if ( $s['show_attributes'] === 'yes' ) {
						$attrs = ! empty( $s['attribute_taxonomies'] ) ? $s['attribute_taxonomies'] : array_keys( $this->get_attribute_choices() );
						foreach ( $attrs as $tax ) {
							if ( ! taxonomy_exists( $tax ) ) { continue; }
							$this->render_attribute_section( $tax, $state, $tag, $open, $s );
						}
					} ?>

					<?php if ( $s['show_sale'] === 'yes' ) : ?>
					<div class="evolve-filter__section evolve-filter__section--inline">
						<label class="evolve-filter__check">
							<input type="checkbox" name="on_sale" value="1" <?php checked( $state['on_sale'] === '1' ); ?>>
							<span class="evolve-filter__checkmark"></span>
							<span><?php echo esc_html( $s['sale_label'] ); ?></span>
						</label>
					</div>
					<?php endif; ?>

					<?php if ( $s['show_stock'] === 'yes' ) : ?>
					<div class="evolve-filter__section evolve-filter__section--inline">
						<label class="evolve-filter__check">
							<input type="checkbox" name="in_stock" value="1" <?php checked( $state['in_stock'] === '1' ); ?>>
							<span class="evolve-filter__checkmark"></span>
							<span><?php echo esc_html( $s['stock_label'] ); ?></span>
						</label>
					</div>
					<?php endif; ?>

					<div class="evolve-filter__actions">
						<button type="submit" class="evolve-filter__apply"><?php echo esc_html( $s['apply_label'] ); ?></button>
						<?php if ( $s['show_clear'] === 'yes' ) : ?>
							<a href="<?php echo esc_url( $this->shop_url() ); ?>" class="evolve-filter__clear" data-evolve-filter-clear><?php echo esc_html( $s['clear_label'] ); ?></a>
						<?php endif; ?>
					</div>
				</form>
			</div>
		</aside>
		<?php
	}

	private function shop_url() {
		if ( function_exists( 'wc_get_page_permalink' ) ) {
			return wc_get_page_permalink( 'shop' );
		}
		return home_url( '/shop/' );
	}

	private function current_state() {
		$g = wp_unslash( $_GET );
		// Use evf_cat / evf_rating (not product_cat / rating_filter) so
		// the URL params don't clash with WooCommerce's registered
		// taxonomy query-vars, which cause a fatal on page refresh.
		$pc = [];
		if ( ! empty( $g['evf_cat'] ) ) {
			$pc = (array) $g['evf_cat'];
		}
		$rf = [];
		if ( ! empty( $g['evf_rating'] ) ) {
			$rf = array_map( 'sanitize_text_field', (array) $g['evf_rating'] );
		}
		return [
			'evf_q'           => isset( $g['evf_q'] ) ? sanitize_text_field( $g['evf_q'] ) : '',
			'min_price'       => ! empty( $g['min_price'] ) && (int) $g['min_price'] > 0 ? (string) (int) $g['min_price'] : '',
			'max_price'       => ! empty( $g['max_price'] ) && (int) $g['max_price'] > 0 ? (string) (int) $g['max_price'] : '',
			'evf_cat'         => $pc,
			'evf_rating'      => $rf,
			'on_sale'         => isset( $g['on_sale'] )         ? '1' : '',
			'in_stock'        => isset( $g['in_stock'] )        ? '1' : '',
			'attr'            => isset( $g['attr'] )            ? (array) $g['attr'] : [],
		];
	}

	private function render_categories( $parent, $depth, $state, $s ) {
		$picked    = ! empty( $s['category_picker'] ) ? (array) $s['category_picker'] : [];
		$flat_mode = $s['categories_flat'] === 'yes' || ! empty( $picked );

		$args = [
			'taxonomy'   => 'product_cat',
			'hide_empty' => $s['categories_hide_empty'] === 'yes',
			'orderby'    => 'menu_order',
		];

		if ( ! empty( $picked ) ) {
			// Restrict to user-selected categories (slug match).
			$args['slug']    = $picked;
			$args['orderby'] = 'include_slugs';
		} else {
			$args['parent'] = $parent;   // hierarchical mode
		}

		$cats = get_terms( $args );
		if ( is_wp_error( $cats ) || empty( $cats ) ) { return; }

		echo '<ul class="evolve-filter__list" style="--depth:' . (int) $depth . '">';
		foreach ( $cats as $cat ) {
			$checked = in_array( (string) $cat->slug, (array) $state['evf_cat'], true ) ? ' checked' : '';
			echo '<li><label class="evolve-filter__check">';
			echo '<input type="checkbox" name="evf_cat[]" value="' . esc_attr( $cat->slug ) . '"' . $checked . '>';
			echo '<span class="evolve-filter__checkmark"></span>';
			echo '<span class="evolve-filter__label">' . esc_html( $cat->name ) . '</span>';
			if ( $s['categories_show_count'] === 'yes' ) {
				echo '<span class="evolve-filter__count">' . (int) $cat->count . '</span>';
			}
			echo '</label>';
			if ( ! $flat_mode ) {
				$this->render_categories( $cat->term_id, $depth + 1, $state, $s );
			}
			echo '</li>';
		}
		echo '</ul>';
	}

	private function render_attribute_section( $tax, $state, $tag, $open, $s ) {
		$terms = get_terms( [ 'taxonomy' => $tax, 'hide_empty' => true ] );
		if ( is_wp_error( $terms ) || empty( $terms ) ) { return; }

		$label_obj = function_exists( 'wc_attribute_label' ) ? wc_attribute_label( $tax ) : $tax;
		$selected  = isset( $state['attr'][ $tax ] ) ? (array) $state['attr'][ $tax ] : [];
		?>
		<<?php echo $tag . $open; ?> class="evolve-filter__section">
			<summary class="evolve-filter__head"><?php echo esc_html( $label_obj ); ?></summary>
			<div class="evolve-filter__body">
				<ul class="evolve-filter__list">
					<?php foreach ( $terms as $t ) : $checked = in_array( $t->slug, $selected, true ) ? ' checked' : ''; ?>
						<li><label class="evolve-filter__check">
							<input type="checkbox" name="attr[<?php echo esc_attr( $tax ); ?>][]" value="<?php echo esc_attr( $t->slug ); ?>"<?php echo $checked; ?>>
							<span class="evolve-filter__checkmark"></span>
							<span class="evolve-filter__label"><?php echo esc_html( $t->name ); ?></span>
							<?php if ( $s['categories_show_count'] === 'yes' ) : ?>
								<span class="evolve-filter__count"><?php echo (int) $t->count; ?></span>
							<?php endif; ?>
						</label></li>
					<?php endforeach; ?>
				</ul>
			</div>
		</<?php echo $tag; ?>>
		<?php
	}
}
