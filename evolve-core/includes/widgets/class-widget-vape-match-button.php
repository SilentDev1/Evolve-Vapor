<?php
/**
 * Evolve — Vape Match Button
 *
 * Elementor widget for the "Find My Match" CTA. Two modes:
 *   - Popup:  opens the "Evolve — Vape Match Quiz" Elementor popup (auto-discovered by Popup_Linker)
 *   - Inline: scrolls the page to the first [evolve_ai_vape_match] shortcode
 */
namespace Evolve_Core\Widgets;

if ( ! defined( 'ABSPATH' ) ) { exit; }

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;

class Widget_Vape_Match_Button extends Widget_Base {

	public function get_name()       { return 'evolve-vape-match-button'; }
	public function get_title()      { return esc_html__( 'Evolve Vape Match Button', 'evolve-core' ); }
	public function get_icon()       { return 'eicon-search-bold'; }
	public function get_categories() { return [ 'evolve' ]; }
	public function get_keywords()   { return [ 'vape', 'match', 'quiz', 'evolve', 'cta', 'button' ]; }

	protected function register_controls() : void {

		$this->start_controls_section( 'sec_content', [
			'label' => esc_html__( 'Content', 'evolve-core' ),
			'tab'   => Controls_Manager::TAB_CONTENT,
		] );

		$this->add_control( 'text', [
			'label'   => __( 'Button Text', 'evolve-core' ),
			'type'    => Controls_Manager::TEXT,
			'default' => __( 'Find My Match', 'evolve-core' ),
		] );
		$this->add_control( 'eyebrow', [
			'label'   => __( 'Eyebrow (optional)', 'evolve-core' ),
			'type'    => Controls_Manager::TEXT,
			'default' => '',
			'description' => __( 'Small neon tag above the button. Leave empty to hide.', 'evolve-core' ),
		] );

		$this->add_control( 'mode', [
			'label'   => __( 'Target', 'evolve-core' ),
			'type'    => Controls_Manager::SELECT,
			'default' => 'popup',
			'options' => [
				'popup'  => __( 'Open Vape Match popup',             'evolve-core' ),
				'inline' => __( 'Scroll to inline shortcode on page','evolve-core' ),
				'page'   => __( 'Link to a page URL',                'evolve-core' ),
			],
		] );
		$this->add_control( 'link', [
			'label'     => __( 'Link', 'evolve-core' ),
			'type'      => Controls_Manager::URL,
			'condition' => [ 'mode' => 'page' ],
			'default'   => [ 'url' => '/vape-match/' ],
		] );

		$this->add_control( 'icon', [
			'label'   => __( 'Icon', 'evolve-core' ),
			'type'    => Controls_Manager::ICONS,
			'default' => [ 'value' => 'fas fa-magic', 'library' => 'fa-solid' ],
		] );
		$this->add_control( 'icon_position', [
			'label'   => __( 'Icon Position', 'evolve-core' ),
			'type'    => Controls_Manager::SELECT,
			'default' => 'left',
			'options' => [ 'left' => 'Left', 'right' => 'Right' ],
		] );

		$this->add_control( 'pulse', [
			'label'        => __( 'Pulsing effect', 'evolve-core' ),
			'type'         => Controls_Manager::SWITCHER,
			'default'      => 'yes',
			'return_value' => 'yes',
			'description'  => __( 'Adds the neon-pulse animation to draw attention.', 'evolve-core' ),
		] );
		$this->add_control( 'size', [
			'label'   => __( 'Size', 'evolve-core' ),
			'type'    => Controls_Manager::SELECT,
			'default' => 'md',
			'options' => [ 'sm' => 'Small', 'md' => 'Medium', 'lg' => 'Large' ],
		] );
		$this->add_control( 'full_width', [
			'label'        => __( 'Full width', 'evolve-core' ),
			'type'         => Controls_Manager::SWITCHER,
			'default'      => 'no',
			'return_value' => 'yes',
		] );

		$this->end_controls_section();

		$this->start_controls_section( 'sec_style', [
			'label' => esc_html__( 'Style', 'evolve-core' ),
			'tab'   => Controls_Manager::TAB_STYLE,
		] );

		$this->add_control( 'bg', [
			'label'     => __( 'Background', 'evolve-core' ),
			'type'      => Controls_Manager::COLOR,
			'default'   => '#B7FF00',
			'selectors' => [ '{{WRAPPER}} .evolve-vmb' => 'background-color: {{VALUE}};' ],
		] );
		$this->add_control( 'fg', [
			'label'     => __( 'Text color', 'evolve-core' ),
			'type'      => Controls_Manager::COLOR,
			'default'   => '#000000',
			'selectors' => [ '{{WRAPPER}} .evolve-vmb' => 'color: {{VALUE}};' ],
		] );
		$this->add_control( 'bg_hover', [
			'label'     => __( 'Background (hover)', 'evolve-core' ),
			'type'      => Controls_Manager::COLOR,
			'default'   => '#7CFF4F',
			'selectors' => [ '{{WRAPPER}} .evolve-vmb:hover' => 'background-color: {{VALUE}};' ],
		] );
		$this->add_control( 'radius', [
			'label'     => __( 'Border radius (px)', 'evolve-core' ),
			'type'      => Controls_Manager::NUMBER,
			'default'   => 14,
			'selectors' => [ '{{WRAPPER}} .evolve-vmb' => 'border-radius: {{VALUE}}px;' ],
		] );
		$this->add_group_control( Group_Control_Typography::get_type(), [
			'name'     => 'typo',
			'selector' => '{{WRAPPER}} .evolve-vmb .evolve-vmb__text',
		] );

		$this->end_controls_section();
	}

	protected function render() : void {
		$s    = $this->get_settings_for_display();
		$mode = in_array( $s['mode'] ?? 'popup', [ 'popup', 'inline', 'page' ], true ) ? $s['mode'] : 'popup';
		$text = $s['text'] ?? __( 'Find My Match', 'evolve-core' );

		$classes = [ 'evolve-vmb', 'evolve-vmb--' . ( $s['size'] ?? 'md' ) ];
		if ( ( $s['pulse'] ?? 'yes' ) === 'yes' ) {
			$classes[] = 'evolve-pulse-button';
		}
		if ( ( $s['full_width'] ?? 'no' ) === 'yes' ) {
			$classes[] = 'evolve-vmb--full';
		}

		$attrs = [
			'class'      => esc_attr( implode( ' ', $classes ) ),
			'type'       => 'button',
		];

		if ( $mode === 'page' ) {
			$href = $s['link']['url'] ?? '#';
			printf(
				'<a href="%1$s" class="%2$s">%3$s</a>',
				esc_url( $href ),
				$attrs['class'],
				$this->inner_html( $s, $text )
			);
			return;
		}

		$data_attr = $mode === 'popup'
			? ' data-evolve-vmb="popup"'
			: ' data-evolve-vmb="inline"';

		printf(
			'<button type="button" class="%1$s"%2$s>%3$s</button>',
			$attrs['class'],
			$data_attr,
			$this->inner_html( $s, $text )
		);
	}

	private function inner_html( array $s, string $text ) : string {
		$eyebrow = ! empty( $s['eyebrow'] ) ? '<span class="evolve-vmb__eye">' . esc_html( $s['eyebrow'] ) . '</span>' : '';
		$icon_html = '';
		if ( ! empty( $s['icon']['value'] ) ) {
			ob_start();
			\Elementor\Icons_Manager::render_icon( $s['icon'], [ 'aria-hidden' => 'true' ] );
			$icon_html = ob_get_clean();
			$icon_html = '<span class="evolve-vmb__icon">' . $icon_html . '</span>';
		}
		$text_html = '<span class="evolve-vmb__text">' . esc_html( $text ) . '</span>';
		$body = ( ( $s['icon_position'] ?? 'left' ) === 'right' ) ? ( $text_html . $icon_html ) : ( $icon_html . $text_html );
		return $eyebrow . $body;
	}
}
