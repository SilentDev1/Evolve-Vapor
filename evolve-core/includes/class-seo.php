<?php
/**
 * Local SEO / AEO details for Evolve Vapor (2026-09 SEO pass).
 *
 * 1. One LocalBusiness (Store) node in Yoast's schema graph — address, map point, phone,
 *    hours, social profiles — linked to Yoast's #organization. Replaces the separate
 *    "WP SEO Structured Data Schema" graph, which conflicted with Yoast's.
 * 2. Cart, checkout and My Account stay out of the XML sitemap (they're noindex).
 * 3. robots.txt: no Crawl-delay (it only slows Bing down).
 * 4. llms.txt (GhostPilot): opening hours, in-store pickup and the 21+ policy.
 *
 * Phone and hours confirmed by the owner 2026-09-26. Change them here in one place.
 *
 * @package Evolve_Core
 */

namespace Evolve_Core;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Seo {

	const PHONE     = '+1-603-888-4514';
	const PHONE_TXT = '(603) 888-4514';
	const STREET    = '310 Daniel Webster Hwy';
	const CITY      = 'Nashua';
	const REGION    = 'NH';
	const ZIP       = '03060';
	const LAT       = 42.701017;
	const LNG       = -71.4369865;
	const SAME_AS   = [ 'https://www.facebook.com/evolvevapor/', 'https://www.instagram.com/evolvevapor/' ];

	/** [ days, opens, closes ] */
	const HOURS = [
		[ [ 'Monday', 'Tuesday', 'Wednesday', 'Thursday' ], '10:00', '20:00' ],
		[ [ 'Friday', 'Saturday' ], '10:00', '21:00' ],
		[ [ 'Sunday' ], '12:00', '18:00' ],
	];
	const HOURS_TXT = 'Mon–Thu 10 AM–8 PM, Fri–Sat 10 AM–9 PM, Sun 12–6 PM';

	public function __construct() {
		add_filter( 'wpseo_schema_graph', [ $this, 'local_business' ], 20, 2 );
		add_filter( 'wpseo_exclude_from_sitemap_by_post_ids', [ $this, 'sitemap_exclude' ] );
		add_filter( 'robots_txt', [ $this, 'robots' ], 999 );
		add_filter( 'ghostpilot_llms_contact_lines', [ $this, 'llms_lines' ] );
	}

	/**
	 * @param array $graph   Yoast schema graph.
	 * @param mixed $context Yoast Meta_Tags_Context.
	 */
	public function local_business( $graph, $context = null ) {
		if ( ! is_array( $graph ) || ! ( is_front_page() || is_page( [ 'contact-evolve-vapor', 'contact-us' ] ) ) ) {
			return $graph;
		}
		$home  = trailingslashit( home_url() );
		$hours = [];
		foreach ( self::HOURS as $h ) {
			$hours[] = [ '@type' => 'OpeningHoursSpecification', 'dayOfWeek' => $h[0], 'opens' => $h[1], 'closes' => $h[2] ];
		}
		$node = [
			'@type'                     => 'Store',
			'@id'                       => $home . '#localbusiness',
			'name'                      => 'Evolve Vapor',
			'url'                       => $home,
			'telephone'                 => self::PHONE,
			'priceRange'                => '$$',
			'address'                   => [
				'@type'           => 'PostalAddress',
				'streetAddress'   => self::STREET . ' (Pheasant Lane Mall)',
				'addressLocality' => self::CITY,
				'addressRegion'   => self::REGION,
				'postalCode'      => self::ZIP,
				'addressCountry'  => 'US',
			],
			'geo'                       => [ '@type' => 'GeoCoordinates', 'latitude' => self::LAT, 'longitude' => self::LNG ],
			'openingHoursSpecification' => $hours,
			'sameAs'                    => self::SAME_AS,
			'parentOrganization'        => [ '@id' => $home . '#organization' ],
			'hasMap'                    => 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( 'Evolve Vapor, ' . self::STREET . ', ' . self::CITY . ', ' . self::REGION . ' ' . self::ZIP ),
		];
		$logo = get_theme_mod( 'custom_logo' );
		if ( $logo && ( $src = wp_get_attachment_image_url( $logo, 'full' ) ) ) {
			$node['image'] = $src;
		}
		$graph[] = $node;
		return $graph;
	}

	public function sitemap_exclude( $ids ) {
		if ( ! function_exists( 'wc_get_page_id' ) ) {
			return $ids;
		}
		foreach ( [ 'cart', 'checkout', 'myaccount' ] as $page ) {
			$id = (int) wc_get_page_id( $page );
			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}
		return array_values( array_unique( array_map( 'intval', (array) $ids ) ) );
	}

	public function robots( $output ) {
		return preg_replace( '/^Crawl-delay:.*\R?/mi', '', (string) $output );
	}

	public function llms_lines( $lines ) {
		$lines   = array_values( array_filter( (array) $lines, function ( $l ) { return stripos( (string) $l, 'Phone:' ) !== 0; } ) );
		$lines[] = 'Phone: ' . self::PHONE_TXT;
		$lines[] = 'Location: inside Pheasant Lane Mall, Nashua, NH';
		$lines[] = 'Hours: ' . self::HOURS_TXT;
		$lines[] = 'Online orders are for in-store pickup only (no shipping). Customers must be 21+ with a valid photo ID at pickup.';
		return $lines;
	}
}
