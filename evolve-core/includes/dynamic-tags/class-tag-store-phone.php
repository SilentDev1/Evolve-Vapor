<?php
namespace Evolve_Core\Dynamic_Tags;

use Elementor\Core\DynamicTags\Tag;
use Elementor\Modules\DynamicTags\Module;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Tag_Store_Phone extends Tag {
	public function get_name()         { return 'evolve-store-phone'; }
	public function get_title()        { return esc_html__( 'Store Phone', 'evolve-core' ); }
	public function get_group()        { return \Evolve_Core\Dynamic_Tags::GROUP; }
	public function get_categories()   { return [ Module::TEXT_CATEGORY, Module::URL_CATEGORY ]; }
	public function render() {
		$phone = \Evolve_Core\Dynamic_Tags::store()['phone'];
		echo esc_html( $phone );
	}
}
