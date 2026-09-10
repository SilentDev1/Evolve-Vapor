<?php
namespace Evolve_Core\Dynamic_Tags;

use Elementor\Core\DynamicTags\Tag;
use Elementor\Modules\DynamicTags\Module;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Tag_Cart_Count extends Tag {
	public function get_name()         { return 'evolve-cart-count'; }
	public function get_title()        { return esc_html__( 'Cart Count', 'evolve-core' ); }
	public function get_group()        { return \Evolve_Core\Dynamic_Tags::GROUP; }
	public function get_categories()   { return [ Module::TEXT_CATEGORY ]; }
	public function render() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) { echo '0'; return; }
		echo (int) WC()->cart->get_cart_contents_count();
	}
}
