<?php
/**
 * Evolve Pulse — `[evolve_pulse]` shortcode.
 *
 * Wraps any content in a neon-green pulsing element. The actual animation
 * lives in the child theme's evolve.css (.evolve-pulse / .evolve-pulse--*)
 * so the class works whether dropped in via Elementor's CSS Classes field,
 * raw HTML, or this shortcode.
 *
 * Examples:
 *   [evolve_pulse]Limited offer[/evolve_pulse]
 *   [evolve_pulse style="text" speed="slow"]Order now[/evolve_pulse]
 *   [evolve_pulse style="dot"][/evolve_pulse]              (just a pulsing dot)
 *   [evolve_pulse style="ring"]<a class="evolve-btn">Buy</a>[/evolve_pulse]
 *   [evolve_pulse strong="yes" speed="fast" color="#FF4D4D"]ALERT[/evolve_pulse]
 *
 * Supported attributes:
 *   style   — glow (default) · text · ring · dot · scale · combo
 *   speed   — slow · normal · fast
 *   strong  — yes · no       (boosts glow radius/intensity)
 *   hover   — yes · no       (only animate while hovered)
 *   once    — yes · no       (run animation once then stop)
 *   tag     — span (default) · div
 *   color   — any hex (e.g. #FF4D4D) overrides the neon green
 *   class   — extra CSS classes appended
 */
namespace Evolve_Core;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Pulse {

	public function __construct() {
		add_shortcode( 'evolve_pulse', [ $this, 'shortcode' ] );

		// Defensive: convert curly quotes (“ ” ‘ ’) to straight quotes inside
		// our shortcode opening tags BEFORE WP's shortcode parser runs.
		// Saves users who paste from Docs/Notes/Slack that auto-typographize.
		add_filter( 'the_content',                   [ $this, 'normalize_curly_quotes' ], 5 );
		add_filter( 'widget_text_content',           [ $this, 'normalize_curly_quotes' ], 5 );
		add_filter( 'elementor/widget/render_content',
			function ( $content ) { return $this->normalize_curly_quotes( $content ); }, 5 );
	}

	/**
	 * Replace typographic / curly quotes used as shortcode attribute delimiters
	 * inside `[evolve_pulse …]` and `[/evolve_pulse]` tags with straight ASCII.
	 * Leaves any curly quotes that live inside the visible content alone.
	 */
	public function normalize_curly_quotes( $content ) {
		if ( ! is_string( $content ) || strpos( $content, '[evolve_pulse' ) === false ) {
			return $content;
		}
		return preg_replace_callback(
			'/\[evolve_pulse[^\]]*\]/',
			function ( $m ) {
				return strtr( $m[0], [
					"\xE2\x80\x9C" => '"', // “
					"\xE2\x80\x9D" => '"', // ”
					"\xE2\x80\x98" => "'", // ‘
					"\xE2\x80\x99" => "'", // ’
				] );
			},
			$content
		);
	}

	public function shortcode( $atts = [], $content = '', $tag = '' ) {
		$atts = shortcode_atts( [
			'style'  => 'glow',
			'speed'  => 'normal',
			'strong' => 'no',
			'hover'  => 'no',
			'once'   => 'no',
			'tag'    => 'span',
			'color'  => '',
			'radius' => '',     // 14, 20, 999, "50%", "inherit"
			'class'  => '',
		], $atts, 'evolve_pulse' );

		$valid_styles = [ 'glow', 'text', 'ring', 'dot', 'scale', 'combo', 'width', 'attention' ];
		$style        = in_array( $atts['style'], $valid_styles, true ) ? $atts['style'] : 'glow';

		$classes = [ 'evolve-pulse' ];
		if ( $style !== 'glow' ) {
			$classes[] = 'evolve-pulse--' . $style;
		}
		if ( $atts['speed']  === 'slow' )  { $classes[] = 'evolve-pulse--slow'; }
		if ( $atts['speed']  === 'fast' )  { $classes[] = 'evolve-pulse--fast'; }
		if ( $atts['strong'] === 'yes' )   { $classes[] = 'evolve-pulse--strong'; }
		if ( $atts['hover']  === 'yes' )   { $classes[] = 'evolve-pulse--hover'; }
		if ( $atts['once']   === 'yes' )   { $classes[] = 'evolve-pulse--once'; }
		if ( ! empty( $atts['class'] ) )   { $classes[] = sanitize_html_class( $atts['class'] ); }

		$html_tag = in_array( $atts['tag'], [ 'span', 'div' ], true ) ? $atts['tag'] : 'span';

		// Optional inline CSS variables (color + border-radius).
		$style_props = [];
		if ( ! empty( $atts['color'] ) ) {
			$color = sanitize_hex_color( $atts['color'] );
			if ( ! $color ) {
				$candidate = trim( strip_tags( wp_unslash( $atts['color'] ) ) );
				if ( preg_match( '/^(#[0-9a-f]{3,8}|rgba?\\([^)]+\\)|hsla?\\([^)]+\\)|[a-z]+)$/i', $candidate ) ) {
					$color = $candidate;
				}
			}
			if ( $color ) {
				$style_props[] = '--evolve-pulse-color: ' . $color;
			}
		}
		if ( $atts['radius'] !== '' ) {
			$r = trim( (string) $atts['radius'] );
			// Bare number → assume px. "999" → 999px (pill shape).
			if ( ctype_digit( $r ) ) { $r .= 'px'; }
			if ( preg_match( '/^(\\d+(\\.\\d+)?(px|%|em|rem)|inherit|0)$/i', $r ) ) {
				$style_props[] = '--evolve-pulse-radius: ' . $r;
			}
		}
		$style_attr = $style_props
			? ' style="' . esc_attr( implode( '; ', $style_props ) ) . ';"'
			: '';

		// Run nested shortcodes inside the content.
		$content = do_shortcode( $content );

		// Dot style with no content — render an empty pulsing dot.
		if ( $style === 'dot' && trim( wp_strip_all_tags( $content ) ) === '' ) {
			return sprintf(
				'<%1$s class="%2$s"%3$s aria-hidden="true"></%1$s>',
				esc_attr( $html_tag ),
				esc_attr( implode( ' ', $classes ) ),
				$style_attr
			);
		}

		return sprintf(
			'<%1$s class="%2$s"%3$s>%4$s</%1$s>',
			esc_attr( $html_tag ),
			esc_attr( implode( ' ', $classes ) ),
			$style_attr,
			$content
		);
	}
}
