<?php
namespace Evolve_Core\Dynamic_Tags;

use Elementor\Core\DynamicTags\Tag;
use Elementor\Modules\DynamicTags\Module;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Tag_Store_Hours extends Tag {
	public function get_name()         { return 'evolve-store-hours'; }
	public function get_title()        { return esc_html__( 'Store Hours', 'evolve-core' ); }
	public function get_group()        { return \Evolve_Core\Dynamic_Tags::GROUP; }
	public function get_categories()   { return [ Module::TEXT_CATEGORY ]; }
	public function render() {
		echo nl2br( esc_html( \Evolve_Core\Dynamic_Tags::store()['hours'] ) );
	}
}
