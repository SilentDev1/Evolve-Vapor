<?php
/**
 * Template Importer — Tools → Evolve Templates admin page.
 *
 * 1.3.2 — Imports go through Elementor's documents API first (which is the
 *         only reliable path for Theme Builder document types: cart,
 *         checkout, my-account, single-product, product-archive, header,
 *         footer, archive, single-post, search-results, error-404, popup).
 *         If that path is unavailable, falls back to Source_Local for the
 *         generic page/section types.
 *
 *         Every import failure now bubbles its real error message into an
 *         admin notice, plus the index page shows ✅ / ❌ per row.
 */
namespace Evolve_Core;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Template_Importer {

	const PAGE_SLUG = 'evolve-templates';

	public function __construct() {
		add_action( 'admin_menu',                            [ $this, 'menu' ] );
		add_action( 'admin_post_evolve_import_template',     [ $this, 'handle_import' ] );
		add_action( 'admin_post_evolve_import_all',          [ $this, 'handle_import_all' ] );
		add_action( 'admin_notices',                         [ $this, 'admin_notice' ] );
	}

	public function menu() {
		add_submenu_page(
			'tools.php',
			__( 'Evolve Templates', 'evolve-core' ),
			__( 'Evolve Templates', 'evolve-core' ),
			'manage_options',
			self::PAGE_SLUG,
			[ $this, 'render_page' ]
		);
	}

	public function templates() {
		return [
			'kit-globals'             => [ 'Kit — Global Colors & Fonts',  'kit' ],
			'header'                  => [ 'Header (Desktop / Tablet)',     'header' ],
			'mobile-header'           => [ 'Mobile Header (≤767px)',        'header' ],
			'footer'                  => [ 'Footer (4-column dark)',        'footer' ],
			'homepage'                => [ 'Homepage',                      'page' ],
			'shop-archive'            => [ 'Shop / Product Archive',        'product-archive' ],
			'single-product'          => [ 'Single Product',                'product' ],
			'cart'                    => [ 'Cart (load into Cart page)',    'page' ],
			'checkout'                => [ 'Checkout (load into Checkout page)', 'page' ],
			'my-account'              => [ 'My Account (load into My Account page)', 'page' ],
			'blog-archive'            => [ 'Blog Archive',                  'archive' ],
			'single-blog'             => [ 'Single Blog Post',              'single-post' ],
			'search'                  => [ 'Search Results',                'search-results' ],
			'404'                     => [ '404',                           'error-404' ],
			'age-verification-popup'  => [ 'Age Verification Popup',        'popup' ],
			'mobile-menu-popup'       => [ 'Mobile Menu Popup',             'popup' ],
			'contact'                 => [ 'Contact Page',                  'page' ],
			'default-page'            => [ 'Default Page (reusable shell)', 'page' ],
			'order-received'          => [ 'Order Received (Thank-you)',    'page' ],
			'vape-match-popup'        => [ 'Vape Match Quiz (Popup)',       'popup' ],
		];
	}

	public function admin_notice() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || $screen->id !== 'tools_page_' . self::PAGE_SLUG ) { return; }

		$msg = sanitize_key( $_GET['evolve_msg'] ?? '' );
		if ( ! $msg ) { return; }

		$user_id = get_current_user_id();
		$detail  = get_transient( 'evolve_import_msg_' . $user_id );
		delete_transient( 'evolve_import_msg_' . $user_id );

		if ( $msg === 'imported' ) {
			echo '<div class="notice notice-success is-dismissible"><p><strong>✓ Template imported.</strong> ';
			if ( ! empty( $detail['label'] ) ) {
				echo esc_html( $detail['label'] );
				if ( ! empty( $detail['edit_url'] ) ) {
					echo ' — <a href="' . esc_url( $detail['edit_url'] ) . '">Edit in Elementor</a>';
				}
			}
			echo '</p></div>';
		} elseif ( $msg === 'all_imported' ) {
			$count_ok  = (int) ( $detail['ok'] ?? 0 );
			$count_err = (int) ( $detail['err'] ?? 0 );
			$class     = $count_err ? 'notice-warning' : 'notice-success';
			echo '<div class="notice ' . $class . ' is-dismissible"><p><strong>Imported ' . $count_ok . ' template(s).</strong>';
			if ( $count_err ) { echo ' ' . $count_err . ' failed — see ❌ rows below.'; }
			echo '</p></div>';
		} elseif ( $msg === 'error' ) {
			echo '<div class="notice notice-error is-dismissible"><p><strong>Import failed</strong>';
			if ( ! empty( $detail['error'] ) ) {
				echo ' — ' . esc_html( $detail['error'] );
			}
			echo '</p></div>';
		}
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }

		// Per-template result from the most recent "Import All".
		$user_id = get_current_user_id();
		$rows    = get_transient( 'evolve_import_rows_' . $user_id ) ?: [];
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Evolve — Elementor Templates', 'evolve-core' ); ?></h1>
			<p><?php esc_html_e( 'Import the bundled Elementor templates for evolvevapornh.com. After importing, head to Templates → Theme Builder and set the display conditions.', 'evolve-core' ); ?></p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:18px 0;">
				<?php wp_nonce_field( 'evolve_import_all' ); ?>
				<input type="hidden" name="action" value="evolve_import_all">
				<button class="button button-primary button-large" type="submit"><?php esc_html_e( 'Import All Templates', 'evolve-core' ); ?></button>
				<span class="description" style="margin-left:10px;">Already imported a template? Re-importing creates a second copy — delete duplicates in Templates → Saved Templates / Theme Builder.</span>
			</form>

			<table class="widefat striped">
				<thead><tr>
					<th><?php esc_html_e( 'Template', 'evolve-core' ); ?></th>
					<th><?php esc_html_e( 'Type',     'evolve-core' ); ?></th>
					<th><?php esc_html_e( 'File',     'evolve-core' ); ?></th>
					<th><?php esc_html_e( 'Last Result', 'evolve-core' ); ?></th>
					<th><?php esc_html_e( 'Action',   'evolve-core' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $this->templates() as $slug => $info ) :
					[ $label, $type ] = $info;
					$file   = EVOLVE_CORE_DIR . 'templates/' . $slug . '.json';
					$exists = file_exists( $file );
					$row    = $rows[ $slug ] ?? null;
					$status = '';
					if ( $row ) {
						$status = $row['ok']
							? '✅ <a href="' . esc_url( $row['edit_url'] ) . '">Edit</a>'
							: '❌ <code style="color:#c00">' . esc_html( $row['error'] ) . '</code>';
					}
					?>
					<tr>
						<td><strong><?php echo esc_html( $label ); ?></strong></td>
						<td><code><?php echo esc_html( $type ); ?></code></td>
						<td><code><?php echo esc_html( $slug ); ?>.json</code> <?php echo $exists ? '' : '⚠️ missing'; ?></td>
						<td><?php echo $status; // already escaped above ?></td>
						<td>
							<?php if ( $exists ) : ?>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
								<?php wp_nonce_field( 'evolve_import_' . $slug ); ?>
								<input type="hidden" name="action" value="evolve_import_template">
								<input type="hidden" name="slug" value="<?php echo esc_attr( $slug ); ?>">
								<button class="button"><?php esc_html_e( 'Import', 'evolve-core' ); ?></button>
							</form>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<h2 style="margin-top:32px"><?php esc_html_e( 'After importing', 'evolve-core' ); ?></h2>
			<ol>
				<li><strong>Theme Builder types</strong> (cart, checkout, my-account, single-product, header, footer, archive, single-post, search-results, 404, popup) — appear in <strong>Templates → Theme Builder</strong>. Click <strong>Add Condition</strong> on each to assign it (Cart → Cart page, Header → Entire Site, etc.).</li>
				<li><strong>Page-type templates</strong> (Homepage, Contact, Default Page, Order Received) — appear in <strong>Templates → Saved Templates</strong>. Open the page you want to use them on → Edit with Elementor → folder icon → My Templates → load.</li>
				<li><strong>Kit</strong> — appears in Saved Templates as type <code>kit</code>. Apply it from Site Settings → Site Identity → Active Kit.</li>
			</ol>
		</div>
		<?php
	}

	public function handle_import() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'nope' ); }
		$slug = sanitize_key( $_POST['slug'] ?? '' );
		check_admin_referer( 'evolve_import_' . $slug );

		$result  = $this->import_one( $slug );
		$user_id = get_current_user_id();

		if ( is_wp_error( $result ) ) {
			set_transient( 'evolve_import_msg_' . $user_id, [
				'error' => $result->get_error_message(),
				'slug'  => $slug,
			], 60 );
			$msg = 'error';
		} else {
			$labels = $this->templates();
			set_transient( 'evolve_import_msg_' . $user_id, [
				'label'    => $labels[ $slug ][0] ?? $slug,
				'edit_url' => $result['edit_url'] ?? '',
			], 60 );
			$msg = 'imported';
		}

		wp_safe_redirect( add_query_arg( [ 'page' => self::PAGE_SLUG, 'evolve_msg' => $msg ], admin_url( 'tools.php' ) ) );
		exit;
	}

	public function handle_import_all() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'nope' ); }
		check_admin_referer( 'evolve_import_all' );

		$user_id = get_current_user_id();
		$rows    = [];
		$ok      = 0;
		$err     = 0;

		foreach ( $this->templates() as $slug => $info ) {
			$result = $this->import_one( $slug );
			if ( is_wp_error( $result ) ) {
				$rows[ $slug ] = [ 'ok' => false, 'error' => $result->get_error_message() ];
				$err++;
			} else {
				$rows[ $slug ] = [ 'ok' => true, 'edit_url' => $result['edit_url'] ?? '', 'post_id' => $result['post_id'] ?? 0 ];
				$ok++;
			}
		}

		set_transient( 'evolve_import_rows_' . $user_id, $rows, 5 * MINUTE_IN_SECONDS );
		set_transient( 'evolve_import_msg_'  . $user_id, [ 'ok' => $ok, 'err' => $err ], 60 );

		wp_safe_redirect( add_query_arg( [ 'page' => self::PAGE_SLUG, 'evolve_msg' => 'all_imported' ], admin_url( 'tools.php' ) ) );
		exit;
	}

	/* ---------------------------------------------------------------- */

	/**
	 * Import a single bundled template into Elementor.
	 *
	 * Returns array { post_id, edit_url } on success, or WP_Error.
	 */
	private function import_one( $slug ) {
		$file = EVOLVE_CORE_DIR . 'templates/' . $slug . '.json';
		if ( ! file_exists( $file ) ) {
			return new \WP_Error( 'missing', 'File missing: ' . $slug . '.json' );
		}
		if ( ! class_exists( '\\Elementor\\Plugin' ) ) {
			return new \WP_Error( 'no_elementor', 'Elementor is not active' );
		}

		$raw = file_get_contents( $file );
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) || empty( $data['type'] ) ) {
			return new \WP_Error( 'invalid_json', 'JSON for ' . $slug . ' is missing the "type" field' );
		}

		$type    = $data['type'];
		$title   = $data['title'] ?? ucwords( str_replace( '-', ' ', $slug ) );
		$content = is_array( $data['content'] ?? null ) ? $data['content'] : [];
		$page    = $data['page_settings'] ?? [];

		// Theme Builder doc types need ElementorPro registered; check first
		// so we can return a clear error instead of a fatal/import "0".
		// Document types that require Elementor Pro to be registered.
		// (This Pro build doesn't expose cart/checkout/my-account as
		//  Theme Builder doc types — those are page-type templates that
		//  load into WordPress's Cart/Checkout/My-Account pages.)
		$theme_builder_types = [
			'header', 'footer', 'archive', 'single', 'single-page', 'single-post',
			'product', 'product-archive',
			'error-404', 'search-results', 'popup',
		];
		if ( in_array( $type, $theme_builder_types, true ) && ! class_exists( 'ElementorPro\\Plugin' ) ) {
			return new \WP_Error(
				'pro_required',
				'Elementor Pro must be active to import a "' . $type . '" template'
			);
		}

		// 1. Preferred path — Elementor's documents API. This creates the
		//    elementor_library post with the right `_elementor_template_type`
		//    meta so Theme Builder picks it up.
		try {
			$document = \Elementor\Plugin::$instance->documents->create(
				$type,
				[
					'post_title'  => $title,
					'post_status' => 'publish',
					'post_type'   => 'elementor_library',
				]
			);

			if ( $document && ! is_wp_error( $document ) ) {
				$document->save( [
					'elements' => $content,
					'settings' => $page,
				] );

				$post_id  = $document->get_main_id();
				$edit_url = method_exists( $document, 'get_edit_url' ) ? $document->get_edit_url() : get_edit_post_link( $post_id, '' );

				// Ensure the template type meta is set (some doc classes only set it on first publish).
				if ( $post_id && ! get_post_meta( $post_id, '_elementor_template_type', true ) ) {
					update_post_meta( $post_id, '_elementor_template_type', $type );
				}

				return [ 'post_id' => $post_id, 'edit_url' => $edit_url ];
			}
		} catch ( \Throwable $e ) {
			// fall through to Source_Local
		}

		// 2. Fallback — Source_Local::import_template. Works for kit / page / section.
		$source = \Elementor\Plugin::$instance->templates_manager->get_source( 'local' );
		if ( ! $source ) {
			return new \WP_Error( 'no_source', 'Elementor local template source is unavailable' );
		}

		$tmp = wp_tempnam( $slug . '.json' );
		copy( $file, $tmp );
		$result = $source->import_template( $slug . '.json', $tmp );
		if ( file_exists( $tmp ) ) { @unlink( $tmp ); }

		if ( is_wp_error( $result ) ) { return $result; }

		// Source_Local returns the imported template item arrays.
		$post_id = is_array( $result ) ? (int) ( $result[0]['template_id'] ?? 0 ) : 0;
		$edit_url = $post_id ? add_query_arg( [ 'post' => $post_id, 'action' => 'elementor' ], admin_url( 'post.php' ) ) : '';

		return [ 'post_id' => $post_id, 'edit_url' => $edit_url ];
	}
}
