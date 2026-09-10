<?php
/**
 * Evolve Subscribe — Elementor widget.
 *
 * Thin wrapper around \Evolve_Core\Subscribe::render() so the same markup
 * and submit pipeline are shared with the [evolve_subscribe] shortcode.
 */
namespace Evolve_Core\Widgets;

if ( ! defined( 'ABSPATH' ) ) { exit; }

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

class Widget_Subscribe extends Widget_Base {

	public function get_name()       { return 'evolve-subscribe'; }
	public function get_title()      { return esc_html__( 'Evolve Subscribe', 'evolve-core' ); }
	public function get_icon()       { return 'eicon-email-field'; }
	public function get_categories() { return [ 'evolve' ]; }
	public function get_keywords()   { return [ 'subscribe', 'newsletter', 'email', 'evolve', 'ghostpilot', 'lead', 'capture' ]; }
	public function get_style_depends()  { return [ 'evolve-subscribe' ]; }
	public function get_script_depends() { return [ 'evolve-subscribe' ]; }

	protected function register_controls() {

		$this->start_controls_section( 'sec_content', [
			'label' => esc_html__( 'Content', 'evolve-core' ),
			'tab'   => Controls_Manager::TAB_CONTENT,
		] );

		$this->add_control( 'style', [
			'label'   => esc_html__( 'Layout', 'evolve-core' ),
			'type'    => Controls_Manager::SELECT,
			'default' => 'card',
			'options' => [
				'card'    => 'Card (eyebrow + title + lede + input row)',
				'inline'  => 'Inline (input + button pill)',
				'stacked' => 'Stacked (full-width fields)',
			],
		] );

		$this->add_control( 'eyebrow', [
			'label' => 'Eyebrow', 'type' => Controls_Manager::TEXT,
			'default' => 'STAY IN THE LOOP',
		] );
		$this->add_control( 'title', [
			'label' => 'Title', 'type' => Controls_Manager::TEXT,
			'default' => 'Join the Evolve insider list',
		] );
		$this->add_control( 'lede', [
			'label' => 'Lede', 'type' => Controls_Manager::TEXTAREA,
			'default' => 'New drops, restocks, and Pheasant Lane Mall events. One short email a week.',
		] );
		$this->add_control( 'placeholder', [
			'label' => 'Email Placeholder', 'type' => Controls_Manager::TEXT,
			'default' => 'you@email.com',
		] );
		$this->add_control( 'button', [
			'label' => 'Button Text', 'type' => Controls_Manager::TEXT,
			'default' => 'Subscribe →',
		] );

		$this->add_control( 'show_name', [
			'label' => 'Show Name field', 'type' => Controls_Manager::SWITCHER,
			'default' => 'no', 'return_value' => 'yes',
		] );
		$this->add_control( 'show_phone', [
			'label' => 'Show Phone field', 'type' => Controls_Manager::SWITCHER,
			'default' => 'no', 'return_value' => 'yes',
		] );
		$this->add_control( 'consent', [
			'label' => 'Show consent checkbox', 'type' => Controls_Manager::SWITCHER,
			'default' => 'yes', 'return_value' => 'yes',
			'description' => 'GDPR / CAN-SPAM friendly — Ghost-Convert requires this be truthy to capture a lead.',
		] );

		$this->add_control( 'tags', [
			'label' => 'Intent Tags',
			'type'  => Controls_Manager::TEXT,
			'default' => '',
			'description' => 'Comma-separated. Forwarded to GhostPilot as intent_tags — e.g. "newsletter,homepage_footer".',
		] );
		$this->add_control( 'source', [
			'label' => 'Source Label',
			'type'  => Controls_Manager::TEXT,
			'default' => 'evolve_widget',
			'description' => 'Short identifier added to GhostPilot intent tags as "evolve:&lt;label&gt;" so you can segment in Ghost-Convert.',
		] );

		$this->add_control( 'success', [
			'label' => 'Success Message',
			'type'  => Controls_Manager::TEXT,
			'default' => 'Subscribed! Check your inbox.',
		] );

		$ghost = \Evolve_Core\Subscribe::ghostpilot_available();
		$this->add_control( 'status_note', [
			'type'  => Controls_Manager::RAW_HTML,
			'raw'   => $ghost
				? '<div style="padding:10px 12px;border:1px solid #00b56a;border-radius:8px;background:#e8fff3;color:#005c30;font-size:12px;">✓ GhostPilot Ghost-Convert CRM detected — submissions route to <code>/ghostpilot/v1/ghost-convert/capture</code>.</div>'
				: '<div style="padding:10px 12px;border:1px solid #d63638;border-radius:8px;background:#fcf0f1;color:#7a1a1c;font-size:12px;">⚠ GhostPilot not active — submissions log to Tools → Evolve Leads and email the admin.</div>',
		] );

		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		echo \Evolve_Core\Subscribe::render( $s );
	}
}
