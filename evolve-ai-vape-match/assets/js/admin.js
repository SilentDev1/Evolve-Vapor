/* Evolve AI Vape Match — admin JS
 * - Color-swatch preview next to [data-evolve-color] inputs
 * - "Auto-fill with AI" button on the product meta box
 */
(function ($) {
	'use strict';

	$(function () {
		/* ---- Color swatches ---- */
		$('input[data-evolve-color]').each(function () {
			const $i = $(this);
			const $s = $('<span class="evolve-aivm-color-swatch" style="display:inline-block;width:28px;height:28px;border-radius:6px;border:1px solid #c3c4c7;margin-left:8px;vertical-align:middle;"></span>');
			$i.after($s);
			const paint = () => $s.css('background', /^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/.test($i.val()) ? $i.val() : '#B7FF00');
			$i.on('input change', paint);
			paint();
		});

		/* ---- BULK auto-fill driver (settings page) ---- */
		const $startBtn  = $('#evolve-aivm-bulk-start');
		if ($startBtn.length) {
			const $stopBtn   = $('#evolve-aivm-bulk-stop');
			const $status    = $('#evolve-aivm-bulk-status');
			const $progress  = $('#evolve-aivm-bulk-progress');
			const $bar       = $('#evolve-aivm-bulk-bar');
			const $log       = $('#evolve-aivm-bulk-log');
			const $totalEl   = $('#evolve-aivm-bulk-total');
			const $doneEl    = $('#evolve-aivm-bulk-done');
			const $okEl      = $('#evolve-aivm-bulk-ok');
			const $skipEl    = $('#evolve-aivm-bulk-skipped');
			const $failEl    = $('#evolve-aivm-bulk-fail');
			const $skipChk   = $('#evolve-aivm-bulk-skip');
			const $overChk   = $('#evolve-aivm-bulk-overwrite');
			const nonce      = $('#evolve-aivm-bulk-nonce').val();

			let stopRequested = false;
			let queue   = [];
			let counts  = { total: 0, done: 0, ok: 0, skipped: 0, failed: 0 };

			function log(line, color) {
				$log.append('<div' + (color ? ' style="color:' + color + '"' : '') + '>' + line + '</div>');
				$log.scrollTop($log[0].scrollHeight);
			}
			function renderProgress() {
				const pct = counts.total ? Math.round((counts.done / counts.total) * 100) : 0;
				$bar.css('width', pct + '%');
				$doneEl.text(counts.done);
				$okEl.text(counts.ok);
				$skipEl.text(counts.skipped);
				$failEl.text(counts.failed);
			}

			$startBtn.on('click', function () {
				if (! confirm('This will send each product\'s title + description to OpenAI. Continue?')) return;

				stopRequested = false;
				counts = { total: 0, done: 0, ok: 0, skipped: 0, failed: 0 };
				$startBtn.prop('disabled', true);
				$stopBtn.prop('disabled', false);
				$status.css('color', '#50575e').text('Scanning products…');
				$progress.show(); $log.show().empty();
				$bar.css('width', '0%');

				$.post(ajaxurl, {
					action: 'evolve_aivm_bulk_scan',
					_ajax_nonce: nonce,
					skip_existing: $skipChk.is(':checked') ? 1 : 0,
				}, null, 'json')
				.done(function (res) {
					if (!res || !res.success) {
						$status.css('color', '#d63638').text((res && res.data && res.data.message) || 'Scan failed.');
						$startBtn.prop('disabled', false);
						$stopBtn.prop('disabled', true);
						return;
					}
					queue = res.data.ids.slice();
					counts.total = res.data.total;
					$totalEl.text(counts.total);
					if (! counts.total) {
						$status.css('color', '#50575e').text('Nothing to do — all products already have AI data.');
						$startBtn.prop('disabled', false);
						$stopBtn.prop('disabled', true);
						return;
					}
					log('Queued ' + counts.total + ' product(s). Starting…', '#50575e');
					processNext();
				})
				.fail(function () {
					$status.css('color', '#d63638').text('Scan request failed.');
					$startBtn.prop('disabled', false);
					$stopBtn.prop('disabled', true);
				});
			});

			$stopBtn.on('click', function () {
				stopRequested = true;
				$stopBtn.prop('disabled', true);
				$status.text('Stopping after current item…');
			});

			function processNext() {
				if (stopRequested || ! queue.length) {
					finish();
					return;
				}
				const id = queue.shift();
				$status.text('Processing #' + id + ' (' + (counts.done + 1) + ' / ' + counts.total + ')…');

				$.post(ajaxurl, {
					action: 'evolve_aivm_bulk_process',
					_ajax_nonce: nonce,
					product_id: id,
					skip_existing: $skipChk.is(':checked') ? 1 : 0,
					overwrite:     $overChk.is(':checked') ? 1 : 0,
				}, null, 'json')
				.done(function (res) {
					counts.done++;
					if (res && res.success && res.data) {
						if (res.data.status === 'skipped') {
							counts.skipped++;
							log('⤿  Skipped — ' + (res.data.name || '#' + id) + '  (already has data)', '#8a6d3b');
						} else {
							counts.ok++;
							const fields = (res.data.written || []).join(', ');
							log('✓  ' + (res.data.name || '#' + id) + '  → ' + fields, '#0a7d2a');
						}
					} else {
						counts.failed++;
						const msg = (res && res.data && res.data.message) || 'Failed';
						log('✗  #' + id + ' — ' + msg, '#d63638');
					}
				})
				.fail(function (xhr) {
					counts.done++;
					counts.failed++;
					const msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) || 'Server error';
					log('✗  #' + id + ' — ' + msg, '#d63638');
				})
				.always(function () {
					renderProgress();
					// Tiny pacing delay between calls so we don't hammer OpenAI rate limits.
					setTimeout(processNext, 350);
				});
			}

			function finish() {
				$startBtn.prop('disabled', false);
				$stopBtn.prop('disabled', true);
				const msg = stopRequested ? 'Stopped.' : 'Done.';
				$status.css('color', '#0a7d2a').text(
					msg + ' Filled ' + counts.ok + ' · Skipped ' + counts.skipped + ' · Failed ' + counts.failed
				);
				log('━━━ ' + msg + ' ' + counts.ok + ' filled / ' + counts.skipped + ' skipped / ' + counts.failed + ' failed ━━━', '#1e1e1e');
			}
		}

		/* ---- AI auto-fill on the product meta box ---- */
		$(document).on('click', '[data-evolve-aivm-autofill]', function (e) {
			e.preventDefault();
			const $btn  = $(this);
			const $meta = $btn.closest('[data-evolve-aivm-meta]');
			const $status = $meta.find('.evolve-aivm-meta__autofill-status');
			const productId = $meta.data('product-id');
			const nonce     = $meta.data('ajax-nonce');
			const ajaxUrl   = $meta.data('ajax-url');

			$btn.prop('disabled', true);
			$status.css('color', '#666').text('Reading product title + description with AI…');

			$.ajax({
				url: ajaxUrl,
				method: 'POST',
				dataType: 'json',
				data: {
					action: 'evolve_aivm_autofill',
					product_id: productId,
					_ajax_nonce: nonce,
				},
			})
			.done(function (res) {
				if (!res || !res.success || !res.data || !res.data.values) {
					$status.css('color', '#b32d2e').text((res && res.data && res.data.message) || 'AI auto-fill failed.');
					$btn.prop('disabled', false);
					return;
				}
				const values = res.data.values;
				const map = {
					flavor_family:     'select',
					flavor_notes:      'text',
					sweetness_level:   'number',
					cooling_level:     'number',
					throat_hit:        'select',
					nicotine_strength: 'text',
					device_type:       'select',
					beginner_friendly: 'radio',
					brand:             'text',
					compatibility:     'text',
					ai_keywords:       'text',
				};
				const filled = [];
				Object.keys(map).forEach(function (key) {
					if (!(key in values)) return;
					const sel = '[name="evolve_aivm[' + key + ']"]';
					const $f = $meta.find(sel);
					if (!$f.length) return;
					if (map[key] === 'radio') {
						$meta.find(sel + '[value="' + values[key] + '"]').prop('checked', true);
					} else {
						$f.val(values[key]).trigger('change');
					}
					$f.css({ outline: '2px solid #B7FF00', outlineOffset: '2px' });
					setTimeout(() => $f.css({ outline: '', outlineOffset: '' }), 1400);
					filled.push(key);
				});
				$status.css('color', '#0a7d2a').text((res.data.message || 'Filled.') + ' (' + filled.length + ' fields)');
				$btn.prop('disabled', false);
			})
			.fail(function (xhr) {
				const msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message)
					|| 'Server error. Check OpenAI API key in Vape Match → Settings.';
				$status.css('color', '#b32d2e').text(msg);
				$btn.prop('disabled', false);
			});
		});
	});
})(jQuery);
