<?php
/**
 * Tools → Evolve Shortcodes — admin reference page.
 *
 * Single-source documentation for every shortcode the Evolve Core plugin
 * registers. Each entry shows the attribute table, several copy-to-clipboard
 * examples, and (where safe) a live preview rendered server-side.
 */
namespace Evolve_Core;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Shortcode_Docs {

	const PAGE_SLUG = 'evolve-shortcodes';

	public function __construct() {
		add_action( 'admin_menu',             [ $this, 'menu' ] );
		add_action( 'admin_enqueue_scripts',  [ $this, 'enqueue' ] );
	}

	public function menu() {
		add_submenu_page(
			'tools.php',
			__( 'Evolve Shortcodes', 'evolve-core' ),
			__( 'Evolve Shortcodes', 'evolve-core' ),
			'edit_posts',
			self::PAGE_SLUG,
			[ $this, 'render' ]
		);
	}

	public function enqueue( $hook ) {
		if ( $hook !== 'tools_page_' . self::PAGE_SLUG ) { return; }
		// Load the storefront's pulse + subscribe styles so previews look right.
		wp_enqueue_style( 'evolve-shop-filter' );
		wp_enqueue_style( 'evolve-header-search' );
		wp_enqueue_style( 'evolve-subscribe' );
		wp_add_inline_style( 'evolve-subscribe', $this->admin_styles() );
	}

	/* ----------------------------------------------------------------- */

	private function admin_styles() {
		return <<<CSS
		.evolve-doc { max-width: 1100px; }
		.evolve-doc h1 { font-size: 26px; margin-bottom: 6px; }
		.evolve-doc__intro { color: #50575e; font-size: 14px; margin-bottom: 24px; }
		.evolve-doc__toc { display: flex; flex-wrap: wrap; gap: 6px; margin: 0 0 24px; }
		.evolve-doc__toc a {
			background: #f0f0f1; border: 1px solid #c3c4c7; padding: 6px 12px;
			border-radius: 999px; text-decoration: none; color: #1e1e1e;
			font-size: 12px; font-weight: 600;
		}
		.evolve-doc__toc a:hover { background: #1e1e1e; color: #fff; border-color: #1e1e1e; }
		.evolve-doc__sc {
			background: #fff; border: 1px solid #c3c4c7; border-radius: 8px;
			margin-bottom: 28px;
		}
		.evolve-doc__head {
			padding: 16px 20px; border-bottom: 1px solid #dcdcde;
			display: flex; justify-content: space-between; align-items: baseline; flex-wrap: wrap; gap: 12px;
		}
		.evolve-doc__head h2 { margin: 0; font-size: 18px; }
		.evolve-doc__head code { background: rgba(0,0,0,0.06); padding: 3px 8px; border-radius: 4px; font-size: 13px; }
		.evolve-doc__pill {
			display: inline-flex; align-items: center; gap: 6px;
			background: #B7FF00; color: #000; padding: 3px 10px;
			border-radius: 999px; font-size: 11px; font-weight: 800; letter-spacing: .08em;
		}
		.evolve-doc__body { padding: 18px 20px; }
		.evolve-doc__body p { margin: 0 0 14px; }
		.evolve-doc__table { width: 100%; border-collapse: collapse; margin: 6px 0 18px; }
		.evolve-doc__table th, .evolve-doc__table td {
			text-align: left; padding: 8px 10px; border-bottom: 1px solid #e5e5e5; font-size: 13px;
			vertical-align: top;
		}
		.evolve-doc__table th { background: #fafafa; font-weight: 700; }
		.evolve-doc__table code { background: rgba(0,0,0,0.06); padding: 1px 6px; border-radius: 4px; font-size: 12px; }
		.evolve-doc__example { margin: 0 0 14px; position: relative; }
		.evolve-doc__example pre {
			margin: 0; background: #0a0a0a; color: #B7FF00; padding: 14px 80px 14px 16px;
			border-radius: 8px; overflow-x: auto; font-size: 13px; line-height: 1.5;
			white-space: pre-wrap; word-break: break-word;
		}
		.evolve-doc__copy {
			position: absolute; top: 10px; right: 10px;
			background: rgba(255,255,255,0.10); color: #fff; border: 1px solid rgba(255,255,255,0.25);
			border-radius: 6px; padding: 4px 10px; font-size: 11px; font-weight: 700; letter-spacing: .06em;
			text-transform: uppercase; cursor: pointer;
		}
		.evolve-doc__copy:hover { background: #B7FF00; color: #000; border-color: #B7FF00; }
		.evolve-doc__copy.is-copied { background: #00ff99; color: #000; border-color: #00ff99; }
		.evolve-doc__preview {
			background: #050505; color: #fff;
			padding: 18px;
			border-radius: 8px;
			border: 1px solid #1E1E1E;
			margin-top: 8px;
		}
		.evolve-doc__preview-label {
			color: #6E6E6E; font-size: 10px; letter-spacing: .12em; text-transform: uppercase;
			margin: 0 0 10px; display: inline-block;
		}
		.evolve-doc__alt {
			background: #f6fbe6; border-left: 3px solid #B7FF00;
			padding: 10px 14px; font-size: 13px; margin: 14px 0;
		}
		.evolve-doc__alt code { background: rgba(0,0,0,0.08); padding: 1px 6px; border-radius: 4px; font-size: 12px; }
		.evolve-doc__notes {
			background: #fff8e1; border-left: 3px solid #f1a900;
			padding: 10px 14px; font-size: 13px; margin: 8px 0;
		}
CSS;
	}

	/* ----------------------------------------------------------------- */

	public function render() {
		if ( ! current_user_can( 'edit_posts' ) ) { return; }
		?>
		<div class="wrap evolve-doc">
			<h1><?php esc_html_e( 'Evolve Core — Shortcode Reference', 'evolve-core' ); ?></h1>
			<p class="evolve-doc__intro">
				All shortcodes that ship with the Evolve Core plugin. Paste them into a WordPress Page, into Elementor's <strong>Text Editor</strong> or <strong>Shortcode</strong> widget, or anywhere `do_shortcode()` runs.
			</p>

			<nav class="evolve-doc__toc">
				<?php foreach ( $this->shortcodes() as $key => $sc ) : ?>
					<a href="#sc-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $sc['title'] ); ?></a>
				<?php endforeach; ?>
			</nav>

			<?php foreach ( $this->shortcodes() as $key => $sc ) : ?>
				<section class="evolve-doc__sc" id="sc-<?php echo esc_attr( $key ); ?>">
					<header class="evolve-doc__head">
						<h2><?php echo esc_html( $sc['title'] ); ?> <code>[<?php echo esc_html( $sc['tag'] ); ?>]</code></h2>
						<?php if ( ! empty( $sc['pill'] ) ) : ?>
							<span class="evolve-doc__pill"><?php echo esc_html( $sc['pill'] ); ?></span>
						<?php endif; ?>
					</header>
					<div class="evolve-doc__body">
						<p><?php echo wp_kses_post( $sc['desc'] ); ?></p>

						<?php if ( ! empty( $sc['attrs'] ) ) : ?>
							<table class="evolve-doc__table">
								<thead><tr>
									<th style="width:130px;">Attribute</th>
									<th style="width:180px;">Values</th>
									<th style="width:120px;">Default</th>
									<th>What it does</th>
								</tr></thead>
								<tbody>
								<?php foreach ( $sc['attrs'] as $name => $a ) : ?>
									<tr>
										<td><code><?php echo esc_html( $name ); ?></code></td>
										<td><code><?php echo esc_html( $a['values'] ); ?></code></td>
										<td><code><?php echo esc_html( $a['default'] ); ?></code></td>
										<td><?php echo wp_kses_post( $a['desc'] ); ?></td>
									</tr>
								<?php endforeach; ?>
								</tbody>
							</table>
						<?php endif; ?>

						<?php foreach ( (array) $sc['examples'] as $i => $ex ) : ?>
							<?php $eid = 'ex-' . $key . '-' . $i; ?>
							<div class="evolve-doc__example">
								<?php if ( ! empty( $ex['label'] ) ) : ?>
									<p style="margin:8px 0 4px;color:#50575e;font-size:12px;text-transform:uppercase;letter-spacing:.08em;font-weight:700;">
										<?php echo esc_html( $ex['label'] ); ?>
									</p>
								<?php endif; ?>
								<pre id="<?php echo esc_attr( $eid ); ?>"><?php echo esc_html( $ex['code'] ); ?></pre>
								<button type="button" class="evolve-doc__copy" data-target="<?php echo esc_attr( $eid ); ?>">Copy</button>
								<?php if ( ! empty( $ex['preview'] ) ) : ?>
									<div class="evolve-doc__preview">
										<span class="evolve-doc__preview-label">Live preview</span>
										<div><?php echo do_shortcode( $ex['code'] ); ?></div>
									</div>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>

						<?php if ( ! empty( $sc['css_alt'] ) ) : ?>
							<div class="evolve-doc__alt">
								<strong>CSS-class alternative</strong> (no shortcode needed) — add to any element via Elementor's <em>Advanced → CSS Classes</em>:<br>
								<code><?php echo esc_html( $sc['css_alt'] ); ?></code>
							</div>
						<?php endif; ?>

						<?php if ( ! empty( $sc['notes'] ) ) : ?>
							<div class="evolve-doc__notes"><?php echo wp_kses_post( $sc['notes'] ); ?></div>
						<?php endif; ?>
					</div>
				</section>
			<?php endforeach; ?>

			<p style="margin-top:24px;color:#50575e;font-size:13px;">
				<strong>Tips</strong> &nbsp;·&nbsp; Use Elementor's <em>Shortcode</em> widget for a clean one-off drop. The <em>Text Editor</em> widget also runs shortcodes. Plain HTML widgets sometimes skip <code>do_shortcode()</code>; if a shortcode prints as raw text, switch widget type or use the CSS-class option.
			</p>
		</div>

		<script>
		document.addEventListener('click', function (e) {
			const b = e.target.closest('.evolve-doc__copy');
			if (!b) return;
			const t = document.getElementById(b.dataset.target);
			if (!t) return;
			const txt = t.innerText;
			(navigator.clipboard ? navigator.clipboard.writeText(txt) : Promise.reject())
				.catch(() => {
					const ta = document.createElement('textarea');
					ta.value = txt; document.body.appendChild(ta);
					ta.select(); document.execCommand('copy'); ta.remove();
				})
				.finally(() => {
					const orig = b.textContent;
					b.textContent = 'Copied ✓';
					b.classList.add('is-copied');
					setTimeout(() => { b.textContent = orig; b.classList.remove('is-copied'); }, 1400);
				});
		});
		</script>
		<?php
	}

	/* ----------------------------------------------------------------- */
	/* Shortcode catalogue                                                */
	/* ----------------------------------------------------------------- */

	private function shortcodes() {
		return [

			/* ============ [evolve_pulse] ============ */
			'pulse' => [
				'title' => 'Evolve Pulse',
				'tag'   => 'evolve_pulse',
				'desc'  => 'Wraps any content in a neon-green pulsing effect. Six animation styles, three speeds, hover-only / one-shot modifiers, and an optional custom color.',
				'attrs' => [
					'style'  => [ 'values' => 'glow · text · ring · dot · scale · combo · width · attention', 'default' => 'glow',     'desc' => 'Which animation. <strong>width</strong> = visible 1.0 → 1.08 horizontal stretch + glow (very obvious). <strong>attention</strong> = scale + glow + outward ring — strongest variant.' ],
					'speed'  => [ 'values' => 'slow · normal · fast',                    'default' => 'normal',   'desc' => 'Animation tempo. <code>slow</code> = 2.8s, <code>fast</code> = 1.0s.' ],
					'strong' => [ 'values' => 'yes · no',                                'default' => 'no',       'desc' => 'Bigger glow radius / brighter peak. Pairs well with <code>style="glow"</code> or <code>combo</code>.' ],
					'hover'  => [ 'values' => 'yes · no',                                'default' => 'no',       'desc' => 'Only animate while the element is hovered.' ],
					'once'   => [ 'values' => 'yes · no',                                'default' => 'no',       'desc' => 'Run the animation once, then stop.' ],
					'tag'    => [ 'values' => 'span · div',                              'default' => 'span',     'desc' => 'Wrapper element type. Use <code>div</code> for block-level wrapping.' ],
					'color'  => [ 'values' => 'any hex / rgb / css color',               'default' => '(neon)',   'desc' => 'Overrides the pulse color. E.g. <code>#FF4D4D</code> for an alert pulse.' ],
					'radius' => [ 'values' => 'px / % / em / inherit',                   'default' => '14px',     'desc' => 'Wrapper border-radius — the box-shadow halo follows this curve. Use <code>999</code> for pill shape, <code>20</code> to match a rounded card, <code>0</code> for square, <code>inherit</code> to take the parent\'s radius.' ],
					'class'  => [ 'values' => 'string',                                  'default' => '—',        'desc' => 'Extra CSS classes appended to the wrapper.' ],
				],
				'examples' => [
					[ 'label' => 'Default — neon box-shadow glow', 'code' => '[evolve_pulse]Limited offer this week.[/evolve_pulse]', 'preview' => true ],
					[ 'label' => 'Glowing headline text',          'code' => '[evolve_pulse style="text" speed="slow"]Order now[/evolve_pulse]', 'preview' => true ],
					[ 'label' => 'Pulsing dot (empty wrapper)',    'code' => '[evolve_pulse style="dot"][/evolve_pulse] Live now', 'preview' => true ],
					[ 'label' => 'Red alert',                      'code' => '[evolve_pulse strong="yes" speed="fast" color="#FF4D4D"]LIMITED STOCK[/evolve_pulse]', 'preview' => true ],
					[ 'label' => 'Outward ring (pulse a CTA)',     'code' => '[evolve_pulse style="ring"]<a class="button" href="/shop">Shop Now</a>[/evolve_pulse]', 'preview' => true ],
					[ 'label' => 'Width — visible breathing stretch', 'code' => '[evolve_pulse style="width" tag="div" radius="20"]BUY 4 E-JUICE, GET THE 5TH FREE[/evolve_pulse]', 'preview' => true ],
					[ 'label' => 'Pill-shaped attention pulse',     'code' => '[evolve_pulse style="attention" radius="999"]Order now[/evolve_pulse]', 'preview' => true ],
					[ 'label' => 'Square pulse',                    'code' => '[evolve_pulse style="width" radius="0"]NEW</br>DROP[/evolve_pulse]', 'preview' => false ],
					[ 'label' => 'Attention — strongest grab',      'code' => '[evolve_pulse style="attention"]Get yours today →[/evolve_pulse]', 'preview' => true ],
				],
				'css_alt' => 'evolve-pulse  ·  evolve-pulse--text  ·  evolve-pulse--ring  ·  evolve-pulse--dot  ·  evolve-pulse--scale  ·  evolve-pulse--combo  ·  evolve-pulse--width  ·  evolve-pulse--attention  ·  evolve-pulse--strong  ·  evolve-pulse--slow  ·  evolve-pulse--fast  ·  evolve-pulse--hover  ·  evolve-pulse-button',
				'notes'   => 'On a button: drop <code>evolve-pulse-button</code> into Advanced → CSS Classes. No shortcode needed.<br><br>The <strong>header cart badge auto-pulses</strong> when the cart has items — no config needed.',
			],

			/* ============ [evolve_subscribe] ============ */
			'subscribe' => [
				'title' => 'Evolve Subscribe',
				'tag'   => 'evolve_subscribe',
				'desc'  => 'Newsletter / lead-capture form. Submissions route to GhostPilot Ghost-Convert CRM when active, otherwise fall back to a local <em>Evolve Leads</em> CPT + admin email.',
				'pill'  => \Evolve_Core\Subscribe::ghostpilot_available() ? 'GhostPilot ON' : 'Local fallback',
				'attrs' => [
					'style'       => [ 'values' => 'card · inline · stacked', 'default' => 'card',           'desc' => 'Layout. <code>card</code> is the neon-bordered hero. <code>inline</code> is a compact email-pill row.' ],
					'eyebrow'     => [ 'values' => 'text',                    'default' => 'STAY IN THE LOOP', 'desc' => 'Small neon eyebrow above the title.' ],
					'title'       => [ 'values' => 'text',                    'default' => '…',              'desc' => 'Large headline.' ],
					'lede'        => [ 'values' => 'text',                    'default' => '…',              'desc' => 'Supporting line under the title.' ],
					'button'      => [ 'values' => 'text',                    'default' => 'Subscribe →',    'desc' => 'Submit button label.' ],
					'placeholder' => [ 'values' => 'text',                    'default' => 'you@email.com',  'desc' => 'Email input placeholder.' ],
					'show_name'   => [ 'values' => 'yes · no',                'default' => 'no',             'desc' => 'Show a Name field.' ],
					'show_phone'  => [ 'values' => 'yes · no',                'default' => 'no',             'desc' => 'Show a Phone field.' ],
					'consent'     => [ 'values' => 'yes · no',                'default' => 'yes',            'desc' => 'Show GDPR / CAN-SPAM consent checkbox.' ],
					'success'     => [ 'values' => 'text',                    'default' => 'Subscribed! Check your inbox.', 'desc' => 'Message shown after a successful submit.' ],
					'tags'        => [ 'values' => 'csv',                     'default' => '—',              'desc' => 'Intent tags forwarded to GhostPilot. E.g. <code>newsletter,footer</code>.' ],
					'source'      => [ 'values' => 'string',                  'default' => 'shortcode',      'desc' => 'Short identifier added as <code>evolve:&lt;source&gt;</code> tag in GhostPilot.' ],
					'class'       => [ 'values' => 'string',                  'default' => '—',              'desc' => 'Extra CSS classes.' ],
				],
				'examples' => [
					[ 'label' => 'Default card', 'code' => '[evolve_subscribe]', 'preview' => true ],
					[ 'label' => 'Configured for blog footer', 'code' => '[evolve_subscribe title="Join the Evolve insider list" lede="One short email a week. New drops and Pheasant Lane Mall events." button="Subscribe →" source="blog" tags="newsletter,blog"]', 'preview' => false ],
					[ 'label' => 'Inline pill (Name + Email + Phone)', 'code' => '[evolve_subscribe style="stacked" show_name="yes" show_phone="yes" source="contact_page" tags="newsletter,contact"]', 'preview' => false ],
				],
				'notes' => 'Powered by an AJAX endpoint at <code>wp_ajax_evolve_subscribe</code>. Local fallback leads live at <em>Tools → Evolve Leads</em>.',
			],

			/* ============ [evolve_cart_count] ============ */
			'cart' => [
				'title' => 'Evolve Cart Icon + Count',
				'tag'   => 'evolve_cart_count',
				'desc'  => 'Renders an Evolve-styled cart icon button linking to <code>/cart/</code>, with a live item-count badge.',
				'attrs' => [],
				'examples' => [
					[ 'label' => 'Drop anywhere', 'code' => '[evolve_cart_count]', 'preview' => true ],
				],
			],

			/* ============ [evolve_account_link] ============ */
			'account' => [
				'title' => 'Evolve Account Link',
				'tag'   => 'evolve_account_link',
				'desc'  => 'Renders an Evolve-styled account button linking to <code>/my-account/</code>. Label swaps between "Sign In" (logged out) and "Account" (logged in).',
				'attrs' => [],
				'examples' => [
					[ 'label' => 'Drop into the header', 'code' => '[evolve_account_link]', 'preview' => true ],
				],
			],
		];
	}
}
