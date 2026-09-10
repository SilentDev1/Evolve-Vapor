<?php
/**
 * Elementor dynamic tags for site-wide data:
 *   - Store address
 *   - Store phone
 *   - Store hours
 *   - Cart count
 *
 * Editors can pick these from any Elementor field that supports dynamic tags.
 */
namespace Evolve_Core;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Dynamic_Tags {

	const GROUP = 'evolve';

	public static function register( $manager ) {
		if ( ! class_exists( '\\Elementor\\Core\\DynamicTags\\Tag' ) ) { return; }

		$manager->register_group( self::GROUP, [ 'title' => esc_html__( 'Evolve', 'evolve-core' ) ] );

		require_once __DIR__ . '/dynamic-tags/class-tag-store-address.php';
		require_once __DIR__ . '/dynamic-tags/class-tag-store-phone.php';
		require_once __DIR__ . '/dynamic-tags/class-tag-store-hours.php';
		require_once __DIR__ . '/dynamic-tags/class-tag-cart-count.php';

		$manager->register( new Dynamic_Tags\Tag_Store_Address() );
		$manager->register( new Dynamic_Tags\Tag_Store_Phone() );
		$manager->register( new Dynamic_Tags\Tag_Store_Hours() );
		$manager->register( new Dynamic_Tags\Tag_Cart_Count() );
	}

	/* --------------- Centralized store info (filterable) --------------- */
	public static function store() {
		return apply_filters( 'evolve_store_info', [
			'name'    => 'Evolve Vapor',
			'phone'   => '(603) 880-1014',
			'address' => 'Pheasant Lane Mall · 310 Daniel Webster Hwy · Nashua, NH 03060',
			'hours'   => "Mon–Sat 10:00–21:00\nSunday 11:00–18:00",
			'maps'    => 'https://maps.google.com/?q=Pheasant+Lane+Mall+Nashua+NH',
		] );
	}
}
