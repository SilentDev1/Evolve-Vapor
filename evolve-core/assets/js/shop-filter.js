/* Evolve — Shop Filter front-end
 * - Debounced AJAX product reload on filter change
 * - History.pushState so filters are shareable + back-button-aware
 * - Mobile drawer open/close (body-scroll locked while open)
 * - Loading state on the target product grid
 */
(function () {
	'use strict';

	const ready = (fn) => document.readyState !== 'loading' ? fn() : document.addEventListener('DOMContentLoaded', fn);

	ready(function () {
		document.querySelectorAll('.evolve-filter').forEach(initFilter);
	});

	function initFilter(panel) {
		let cfg = {};
		try { cfg = JSON.parse(panel.dataset.evolveFilter || '{}'); } catch (e) {}
		const form = panel.querySelector('.evolve-filter__form');
		if (!form) return;

		/* ---------- Mobile drawer ---------- */
		const openBtn  = panel.querySelector('[data-evolve-filter-open]');
		const closeBtn = panel.querySelector('[data-evolve-filter-close]');
		const inner    = panel.querySelector('.evolve-filter__panel');
		const toggle   = (open) => {
			panel.classList.toggle('is-open', open);
			document.body.classList.toggle('evolve-filter-locked', open);
		};

		if (openBtn) {
			openBtn.addEventListener('click', function (e) {
				e.preventDefault();
				e.stopPropagation();
				toggle(true);
			});
		}
		if (closeBtn) {
			closeBtn.addEventListener('click', function (e) {
				e.preventDefault();
				e.stopPropagation();
				toggle(false);
			});
		}
		// Backdrop click closes
		panel.addEventListener('click', function (e) {
			if (!cfg.mobile) return;
			if (!panel.classList.contains('is-open')) return;
			if (inner && inner.contains(e.target)) return;
			if (e.target === openBtn || (openBtn && openBtn.contains(e.target))) return;
			toggle(false);
		});
		// Esc closes
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && panel.classList.contains('is-open')) {
				toggle(false);
			}
		});

		/* ---------- Clear all ---------- */
		const clear = panel.querySelector('[data-evolve-filter-clear]');
		if (clear) {
			clear.addEventListener('click', function (e) {
				if (cfg.ajax) {
					e.preventDefault();
					form.reset();
					form.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);
					submit();
				}
			});
		}

		/* ---------- AJAX submit ---------- */
		if (!cfg.ajax) return;

		let timer;
		form.addEventListener('input',  () => { clearTimeout(timer); timer = setTimeout(submit, 280); });
		form.addEventListener('change', () => { clearTimeout(timer); timer = setTimeout(submit, 120); });
		form.addEventListener('submit', (e) => { e.preventDefault(); submit(); });

		// Bind back/forward.
		window.addEventListener('popstate', () => location.reload());

		// On page load / refresh: if the URL carries paged > 1, the
		// server forced page 1 to prevent a 404.  Re-fire AJAX now to
		// show the correct page with the current filter state.
		var urlPaged = parseInt(new URLSearchParams(window.location.search).get('paged'), 10);
		if (urlPaged > 1) {
			submit(urlPaged);
		}

		function submit(page) {
			const target = document.querySelector(cfg.target);
			if (!target) {
				// No grid on this page — fall through to a full submit so the URL changes.
				form.submit();
				return;
			}

			const fd = new FormData(form);
			// When paginating, carry the page number; when filters change
			// (page === undefined) reset to page 1.
			if (page && page > 1) {
				fd.set('paged', page);
			} else {
				fd.delete('paged');
			}
			// Strip empty / zero-value params so the URL stays clean and
			// WooCommerce's server-side price filter doesn't interpret
			// min_price=0&max_price=0 as "only free products".
			['evf_q', 'min_price', 'max_price'].forEach(function (k) {
				var v = fd.get(k);
				if (!v || v === '0') fd.delete(k);
			});
			const qs = new URLSearchParams(fd).toString();

			// Update URL for shareability.
			const newUrl = window.location.pathname + (qs ? '?' + qs : '');
			window.history.pushState({}, '', newUrl);

			target.classList.add('evolve-filter__loading');

			const body = new FormData();
			body.append('action', 'evolve_filter_products');
			body.append('nonce',  cfg.nonce || (window.evolveFilter && window.evolveFilter.nonce) || '');
			body.append('query',  qs);

			fetch(cfg.action || (window.evolveFilter && window.evolveFilter.ajax), {
				method: 'POST',
				body:    body,
				credentials: 'same-origin',
			})
			.then(r => r.json())
			.then(res => {
				target.classList.remove('evolve-filter__loading');
				if (res && res.success && res.data) {
					target.outerHTML = res.data.html;
					// Clean up any old pagination navs left behind by the
					// outerHTML swap (the AJAX response includes its own <nav>).
					var newTarget = document.querySelector(cfg.target);
					if (newTarget && newTarget.parentElement) {
						var pags = newTarget.parentElement.querySelectorAll(':scope > .woocommerce-pagination');
						for (var i = 1; i < pags.length; i++) pags[i].remove();
					}
					if ((cfg.scroll || page) && newTarget) {
						newTarget.scrollIntoView({ behavior: 'smooth', block: 'start' });
					}
					document.dispatchEvent(new CustomEvent('evolve:filtered', { detail: res.data }));

					// Close the mobile drawer so the user sees the filtered results.
					if (panel.classList.contains('is-open')) {
						toggle(false);
					}
				}
			})
			.catch(() => {
				target.classList.remove('evolve-filter__loading');
				form.submit();
			});
		}

		/* ---------- Pagination via AJAX ---------- */
		// Intercept WooCommerce pagination link clicks so they load via
		// AJAX instead of a full page reload (which breaks filter state
		// because product_cat[] in the URL conflicts with WC's taxonomy
		// query-var and causes a fatal error on PHP 8).
		document.addEventListener('click', function (e) {
			var link = e.target.closest('.woocommerce-pagination a');
			if (!link) return;
			if (!document.querySelector(cfg.target)) return;
			e.preventDefault();
			var page = 1;
			try {
				var url = new URL(link.href, window.location.origin);
				page = parseInt(url.searchParams.get('paged'), 10) || 1;
			} catch (_) {}
			submit(page);
		});
	}
})();
