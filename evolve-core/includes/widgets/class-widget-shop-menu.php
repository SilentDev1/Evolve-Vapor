<?php
/**
 * Evolve — Shop Menu (custom Elementor widget)
 *
 * Renders a clean 2-column grid of product-category buttons for the mobile
 * slide-out menu (or anywhere a compact category nav is needed).
 * Replaces the old Text Editor hack with a proper repeater-based widget.
 */
namespace Evolve_Core\Widgets;

if ( ! defined( 'ABSPATH' ) ) { exit; }

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Repeater;
use Elementor\Group_Control_Typography;

class Widget_Shop_Menu extends Widget_Base {

	public function get_name()       { return 'evolve-shop-menu'; }
	public function get_title()      { return esc_html__( 'Evolve Shop Menu', 'evolve-core' ); }
	public function get_icon()       { return 'eicon-gallery-grid'; }
	public function get_categories() { return [ 'evolve' ]; }
	public function get_keywords()   { return [ 'shop', 'menu', 'category', 'grid', 'mobile', 'evolve' ]; }

	public function get_style_depends()  { return [ 'evolve-shop-menu' ]; }

	/* ================================================================
	 * Controls
	 * ============================================================= */
	protected function register_controls() {

		/* ---------- Content: Items ---------- */
		$this->start_controls_section( 'sec_items', [
			'label' => esc_html__( 'Menu Items', 'evolve-core' ),
			'tab'   => Controls_Manager::TAB_CONTENT,
		] );

		$repeater = new Repeater();

		$repeater->add_control( 'label', [
			'label'   => __( 'Label', 'evolve-core' ),
			'type'    => Controls_Manager::TEXT,
			'default' => __( 'Category', 'evolve-core' ),
		] );

		$repeater->add_control( 'link', [
			'label'       => __( 'Link', 'evolve-core' ),
			'type'        => Controls_Manager::URL,
			'default'     => [ 'url' => '/product-category/disposable-vapes/' ],
			'placeholder' => '/product-category/slug/',
		] );

		$repeater->add_control( 'icon', [
			'label'   => __( 'Icon (optional)', 'evolve-core' ),
			'type'    => Controls_Manager::ICONS,
		] );

		$this->add_control( 'items', [
			'label'       => __( 'Categories', 'evolve-core' ),
			'type'        => Controls_Manager::REPEATER,
			'fields'      => $repeater->get_controls(),
			'default'     => [
				[ 'label' => 'Disposable Vapes', 'link' => [ 'url' => '/product-category/disposable-vapes/' ] ],
				[ 'label' => 'E-Juice',          'link' => [ 'url' => '/product-category/e-juice/' ] ],
				[ 'label' => 'Kits',             'link' => [ 'url' => '/product-category/kits/' ] ],
				[ 'label' => 'Pods',             'link' => [ 'url' => '/product-category/pods/' ] ],
				[ 'label' => 'Coils',            'link' => [ 'url' => '/product-category/coils/' ] ],
				[ 'label' => 'Salt Nicotine',    'link' => [ 'url' => '/product-category/salt-nicotine/' ] ],
			],
			'title_field' => '{{{ label }}}',
		] );

		$this->end_controls_section();

		/* ---------- Content: Layout ---------- */
		$this->start_controls_section( 'sec_layout', [
			'label' => esc_html__( 'Layout', 'evolve-core' ),
			'tab'   => Controls_Manager::TAB_CONTENT,
		] );

		$this->add_control( 'heading', [
			'label'   => __( 'Section Heading', 'evolve-core' ),
			'type'    => Controls_Manager::TEXT,
			'default' => 'SHOP',
		] );

		$this->add_control( 'columns', [
			'label'   => __( 'Columns', 'evolve-core' ),
			'type'    => Controls_Manager::SELECT,
			'default' => '2',
			'options' => [
				'1' => '1',
				'2' => '2',
				'3' => '3',
			],
		] );

		$this->add_control( 'gap', [
			'label'     => __( 'Gap (px)', 'evolve-core' ),
			'type'      => Controls_Manager::NUMBER,
			'default'   => 8,
			'selectors' => [ '{{WRAPPER}} .evolve-sm__grid' => 'gap: {{VALUE}}px;' ],
		] );

		$this->end_controls_section();

		/* ---------- Style: Heading ---------- */
		$this->start_controls_section( 'sec_style_heading', [
			'label' => esc_html__( 'Heading', 'evolve-core' ),
			'tab'   => Controls_Manager::TAB_STYLE,
		] );

		$this->add_control( 'heading_color', [
			'label'     => __( 'Color', 'evolve-core' ),
			'type'      => Controls_Manager::COLOR,
			'default'   => '#B7FF00',
			'selectors' => [ '{{WRAPPER}} .evolve-sm__heading' => 'color: {{VALUE}};' ],
		] );

		$this->add_group_control( Group_Control_Typography::get_type(), [
			'name'     => 'heading_typo',
			'selector' => '{{WRAPPER}} .evolve-sm__heading',
		] );

		$this->add_control( 'heading_spacing', [
			'label'     => __( 'Bottom Spacing (px)', 'evolve-core' ),
			'type'      => Controls_Manager::NUMBER,
			'default'   => 12,
			'selectors' => [ '{{WRAPPER}} .evolve-sm__heading' => 'margin-bottom: {{VALUE}}px;' ],
		] );

		$this->end_controls_section();

		/* ---------- Style: Buttons ---------- */
		$this->start_controls_section( 'sec_style_btn', [
			'label' => esc_html__( 'Buttons', 'evolve-core' ),
			'tab'   => Controls_Manager::TAB_STYLE,
		] );

		$this->add_control( 'btn_bg', [
			'label'     => __( 'Background', 'evolve-core' ),
			'type'      => Controls_Manager::COLOR,
			'default'   => '#111111',
			'selectors' => [ '{{WRAPPER}} .evolve-sm__btn' => 'background-color: {{VALUE}};' ],
		] );

		$this->add_control( 'btn_color', [
			'label'     => __( 'Text Color', 'evolve-core' ),
			'type'      => Controls_Manager::COLOR,
			'default'   => '#FFFFFF',
			'selectors' => [ '{{WRAPPER}} .evolve-sm__btn' => 'color: {{VALUE}};' ],
		] );

		$this->add_control( 'btn_border_color', [
			'label'     => __( 'Border Color', 'evolve-core' ),
			'type'      => Controls_Manager::COLOR,
			'default'   => '#1E1E1E',
			'selectors' => [ '{{WRAPPER}} .evolve-sm__btn' => 'border-color: {{VALUE}};' ],
		] );

		$this->add_control( 'btn_hover_bg', [
			'label'     => __( 'Hover Background', 'evolve-core' ),
			'type'      => Controls_Manager::COLOR,
			'default'   => '#1A1A1A',
			'selectors' => [ '{{WRAPPER}} .evolve-sm__btn:hover, {{WRAPPER}} .evolve-sm__btn:focus' => 'background-color: {{VALUE}};' ],
		] );

		$this->add_control( 'btn_hover_border', [
			'label'     => __( 'Hover Border Color', 'evolve-core' ),
			'type'      => Controls_Manager::COLOR,
			'default'   => '#B7FF00',
			'selectors' => [ '{{WRAPPER}} .evolve-sm__btn:hover, {{WRAPPER}} .evolve-sm__btn:focus' => 'border-color: {{VALUE}};' ],
		] );

		$this->add_control( 'btn_hover_color', [
			'label'     => __( 'Hover Text Color', 'evolve-core' ),
			'type'      => Controls_Manager::COLOR,
			'default'   => '#B7FF00',
			'selectors' => [ '{{WRAPPER}} .evolve-sm__btn:hover, {{WRAPPER}} .evolve-sm__btn:focus' => 'color: {{VALUE}};' ],
		] );

		$this->add_control( 'btn_radius', [
			'label'     => __( 'Border Radius (px)', 'evolve-core' ),
			'type'      => Controls_Manager::NUMBER,
			'default'   => 10,
			'selectors' => [ '{{WRAPPER}} .evolve-sm__btn' => 'border-radius: {{VALUE}}px;' ],
		] );

		$this->add_control( 'btn_padding', [
			'label'     => __( 'Padding (px)', 'evolve-core' ),
			'type'      => Controls_Manager::NUMBER,
			'default'   => 14,
			'selectors' => [ '{{WRAPPER}} .evolve-sm__btn' => 'padding: {{VALUE}}px;' ],
		] );

		$this->add_group_control( Group_Control_Typography::get_type(), [
			'name'     => 'btn_typo',
			'selector' => '{{WRAPPER}} .evolve-sm__btn',
		] );

		$this->end_controls_section();

		/* ---------- Style: Active ---------- */
		$this->start_controls_section( 'sec_style_active', [
			'label' => esc_html__( 'Active State', 'evolve-core' ),
			'tab'   => Controls_Manager::TAB_STYLE,
		] );

		$this->add_control( 'active_bg', [
			'label'     => __( 'Active Background', 'evolve-core' ),
			'type'      => Controls_Manager::COLOR,
			'default'   => '#B7FF00',
			'selectors' => [ '{{WRAPPER}} .evolve-sm__btn--active' => 'background-color: {{VALUE}};' ],
		] );

		$this->add_control( 'active_color', [
			'label'     => __( 'Active Text Color', 'evolve-core' ),
			'type'      => Controls_Manager::COLOR,
			'default'   => '#050505',
			'selectors' => [ '{{WRAPPER}} .evolve-sm__btn--active' => 'color: {{VALUE}};' ],
		] );

		$this->add_control( 'active_border', [
			'label'     => __( 'Active Border Color', 'evolve-core' ),
			'type'      => Controls_Manager::COLOR,
			'default'   => '#B7FF00',
			'selectors' => [ '{{WRAPPER}} .evolve-sm__btn--active' => 'border-color: {{VALUE}};' ],
		] );

		$this->end_controls_section();
	}

	/* ================================================================
	 * Render
	 * ============================================================= */
	protected function render() {
		$s     = $this->get_settings_for_display();
		$items = $s['items'] ?? [];
		$cols  = max( 1, (int) ( $s['columns'] ?? 2 ) );

		if ( empty( $items ) ) {
			echo '<p>' . esc_html__( 'Add categories in the widget settings.', 'evolve-core' ) . '</p>';
			return;
		}

		// Detect current category for active state.
		$current_cat = '';
		if ( is_tax( 'product_cat' ) ) {
			$current_cat = get_queried_object()->slug ?? '';
		}
		?>
		<nav class="evolve-sm" aria-label="<?php esc_attr_e( 'Shop categories', 'evolve-core' ); ?>">

			<?php if ( ! empty( $s['heading'] ) ) : ?>
				<h4 class="evolve-sm__heading"><?php echo esc_html( $s['heading'] ); ?></h4>
			<?php endif; ?>

			<div class="evolve-sm__grid" style="--sm-cols:<?php echo $cols; ?>">
				<?php foreach ( $items as $item ) :
					$url   = $item['link']['url'] ?? '#';
					$label = $item['label'] ?? '';
					$new_tab = ! empty( $item['link']['is_external'] ) ? ' target="_blank" rel="noopener noreferrer"' : '';

					// Check if this is the active category.
					$active = '';
					if ( $current_cat && strpos( $url, $current_cat ) !== false ) {
						$active = ' evolve-sm__btn--active';
					}

					$icon_html = '';
					if ( ! empty( $item['icon']['value'] ) ) {
						ob_start();
						\Elementor\Icons_Manager::render_icon( $item['icon'], [ 'aria-hidden' => 'true' ] );
						$icon_html = '<span class="evolve-sm__icon">' . ob_get_clean() . '</span>';
					}
				?>
					<a href="<?php echo esc_url( $url ); ?>" class="evolve-sm__btn<?php echo $active; ?>"<?php echo $new_tab; ?>>
						<?php echo $icon_html; ?>
						<span class="evolve-sm__label"><?php echo esc_html( $label ); ?></span>
					</a>
				<?php endforeach; ?>
			</div>
		</nav>
		<?php
	}
}
