<?php
/**
 * WebP copies of uploaded JPEG/PNG images ("photo.jpg" → "photo.jpg.webp", next to it).
 * Evolve Performance already serves the .webp copy in img src/srcset whenever it exists.
 *
 * - New uploads: every size gets a copy when WordPress makes the thumbnails.
 * - Existing library: `wp evolve webp [--limit=500]` works through uploads in batches.
 * - A copy is kept only when it's smaller than the original. Deleting a .webp file reverts that image.
 *
 * @package Evolve_Core
 */

namespace Evolve_Core;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Webp {

	const QUALITY  = 80;
	const MIN_SIZE = 10240; // Smaller files aren't worth it.

	public function __construct() {
		add_filter( 'wp_generate_attachment_metadata', [ $this, 'on_upload' ], 20, 2 );
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'evolve webp', [ $this, 'cli' ] );
		}
	}

	public function on_upload( $meta, $attachment_id ) {
		$file = get_attached_file( $attachment_id );
		if ( ! $file ) {
			return $meta;
		}
		self::make( $file );
		$dir = dirname( $file );
		foreach ( (array) ( $meta['sizes'] ?? [] ) as $s ) {
			if ( ! empty( $s['file'] ) ) {
				self::make( $dir . '/' . $s['file'] );
			}
		}
		return $meta;
	}

	/** @return int bytes saved (0 when skipped). */
	public static function make( string $path ) : int {
		if ( ! preg_match( '/\.(jpe?g|png)$/i', $path ) || ! is_file( $path ) || is_file( $path . '.webp' ) ) {
			return 0;
		}
		$size = (int) filesize( $path );
		if ( $size < self::MIN_SIZE ) {
			return 0;
		}
		try {
			if ( class_exists( '\Imagick' ) ) {
				$im = new \Imagick( $path );
				$im->setImageFormat( 'webp' );
				$im->setImageCompressionQuality( self::QUALITY );
				$im->setOption( 'webp:method', '4' );
				$im->stripImage();
				$im->writeImage( $path . '.webp' );
				$im->clear();
			} elseif ( function_exists( 'imagewebp' ) ) {
				$src = preg_match( '/\.png$/i', $path ) ? @imagecreatefrompng( $path ) : @imagecreatefromjpeg( $path );
				if ( ! $src ) {
					return 0;
				}
				imagepalettetotruecolor( $src );
				imagealphablending( $src, true );
				imagesavealpha( $src, true );
				imagewebp( $src, $path . '.webp', self::QUALITY );
				imagedestroy( $src );
			} else {
				return 0;
			}
		} catch ( \Throwable $e ) {
			@unlink( $path . '.webp' );
			return 0;
		}
		clearstatcache( true, $path . '.webp' );
		$new = is_file( $path . '.webp' ) ? (int) filesize( $path . '.webp' ) : 0;
		if ( ! $new || $new >= $size ) {
			@unlink( $path . '.webp' );
			return 0;
		}
		return $size - $new;
	}

	/**
	 * Make WebP copies for existing uploads.
	 *
	 * ## OPTIONS
	 * [--limit=<n>]
	 * : Stop after this many new copies (default 500).
	 *
	 * @param array $args  Positional.
	 * @param array $assoc Flags.
	 */
	public function cli( $args, $assoc ) {
		$limit = (int) ( $assoc['limit'] ?? 500 );
		$base  = wp_upload_dir()['basedir'];
		$made  = 0;
		$saved = 0;
		$it    = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $base, \FilesystemIterator::SKIP_DOTS ) );
		foreach ( $it as $f ) {
			$p = $f->getPathname();
			if ( strpos( $p, '/elementor/' ) !== false || strpos( $p, '/wc-logs/' ) !== false ) {
				continue;
			}
			$s = self::make( $p );
			if ( $s > 0 ) {
				$made++;
				$saved += $s;
				if ( $made >= $limit ) {
					break;
				}
			}
		}
		\WP_CLI::success( sprintf( '%d WebP copies made, %.1f MB saved', $made, $saved / 1048576 ) );
	}
}
