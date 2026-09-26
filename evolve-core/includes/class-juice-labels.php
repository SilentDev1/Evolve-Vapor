<?php
/**
 * Square names the only option of a single-strength item "Regular". At Evolve Vapor that
 * means 3mg for regular e-juice and 35mg for salt nicotine (owner, 2026-09-26), so every hour
 * a live "Regular" option on an E-Juice or Salt Nicotine E-Juice listing is renamed to match.
 * Skipped when the listing already has a live option with that name (two identical choices
 * can't be told apart) — those need one of the two removed in Square.
 *
 * The Square sync matches "Regular" by its stored Square link and never renames an option back
 * to "Regular", so the rename sticks.
 *
 * @package Evolve_Core
 */

namespace Evolve_Core;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Juice_Labels {

	const HOOK   = 'evolve_juice_labels';
	const SALT   = 'salt-nicotine'; // Salt Nicotine E-Juice
	const EJUICE = 'e-juice';

	public function __construct() {
		add_action( self::HOOK, [ $this, 'run' ] );
		add_action( 'init', function () {
			if ( ! wp_next_scheduled( self::HOOK ) ) {
				wp_schedule_event( time() + 300, 'hourly', self::HOOK );
			}
		} );
	}

	public function run() {
		global $wpdb;
		$rows = $wpdb->get_results(
			"SELECT pm.post_id AS vid, pm.meta_key AS mkey, v.post_parent AS pid
			 FROM {$wpdb->postmeta} pm
			 JOIN {$wpdb->posts} v ON v.ID = pm.post_id AND v.post_type = 'product_variation' AND v.post_status = 'publish'
			 WHERE pm.meta_key LIKE 'attribute\\_%' AND LOWER(pm.meta_value) = 'regular'"
		);
		foreach ( (array) $rows as $r ) {
			$pid = (int) $r->pid;
			if ( has_term( self::SALT, 'product_cat', $pid ) ) {
				$to = '35mg';
			} elseif ( has_term( self::EJUICE, 'product_cat', $pid ) ) {
				$to = '3mg';
			} else {
				continue;
			}
			$this->rename( $pid, (int) $r->vid, substr( $r->mkey, strlen( 'attribute_' ) ), $to );
		}
	}

	private function rename( int $pid, int $vid, string $key, string $to ) {
		$p = wc_get_product( $pid );
		if ( ! $p || ! $p->is_type( 'variable' ) ) {
			return;
		}
		foreach ( $p->get_children() as $cid ) {
			$c = wc_get_product( $cid );
			if ( $c && (int) $cid !== $vid && $c->get_status() === 'publish' && strtolower( trim( implode( ' ', $c->get_attributes() ) ) ) === strtolower( $to ) ) {
				return; // Already has that strength.
			}
		}
		$attrs = $p->get_attributes();
		$attr  = $attrs[ $key ] ?? null;
		if ( ! $attr ) {
			return;
		}
		if ( $attr->is_taxonomy() ) {
			$term = get_term_by( 'name', $to, $key );
			if ( ! $term ) {
				$made = wp_insert_term( $to, $key );
				if ( is_wp_error( $made ) ) {
					return;
				}
				$term = get_term( $made['term_id'], $key );
			}
			wp_set_object_terms( $pid, (int) $term->term_id, $key, true );
			$opts = array_map( 'intval', (array) $attr->get_options() );
			if ( ! in_array( (int) $term->term_id, $opts, true ) ) {
				$opts[] = (int) $term->term_id;
				$attr->set_options( $opts );
			}
			$value = $term->slug;
		} else {
			$opts = (array) $attr->get_options();
			if ( ! in_array( $to, $opts, true ) ) {
				$opts[] = $to;
				$attr->set_options( $opts );
			}
			$value = $to;
		}
		$attrs[ $key ] = $attr;
		$p->set_attributes( $attrs );
		$p->save();
		update_post_meta( $vid, 'attribute_' . $key, $value );
		wc_delete_product_transients( $vid );
		wc_delete_product_transients( $pid );
	}
}
