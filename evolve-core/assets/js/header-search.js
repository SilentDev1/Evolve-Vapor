/* Evolve — Header Search
 * Live AJAX product dropdown below a header search input.
 * Keyboard nav (↑/↓/Enter/Esc), outside-click close, mobile full-screen overlay.
 */
(function () {
	'use strict';

	const ready = (fn) => document.readyState !== 'loading' ? fn() : document.addEventListener('DOMContentLoaded', fn);

	ready(function () {
		document.querySelectorAll('.evolve-search').forEach(initSearch);
	});

	function initSearch(root) {
		let cfg = {};
		try { cfg = JSON.parse(root.dataset.evolveSearch || '{}'); } catch (e) {}

		const input    = root.querySelector('.evolve-search__input');
		const dropdown = root.querySelector('.evolve-search__dropdown');
		const results  = root.querySelector('.evolve-search__results');
		const clearBtn = root.querySelector('.evolve-search__clear');
		const viewAll  = root.querySelector('.evolve-search__view-all');
		const mTrigger = root.querySelector('[data-evolve-search-open]');
		const mClose   = root.querySelector('[data-evolve-search-close]');
		if (!input || !dropdown || !results) return;

		const mobileBP = parseInt(cfg.mbp || 768, 10);
		const isMobile = () => window.matchMedia('(max-width: ' + mobileBP + 'px)').matches;

		let timer;
		let lastQuery = '';
		let activeIdx = -1;

		function open()  {
			dropdown.hidden = false;
			root.classList.add('is-open');
			document.body.classList.add('evolve-search-open');
			if (isMobile() && cfg.mpopup) {
				root.classList.add('is-mobile-open');
				// Focus input after the slide-in finishes
				setTimeout(() => input.focus(), 50);
			}
		}
		function close() {
			dropdown.hidden = true;
			root.classList.remove('is-open');
			root.classList.remove('is-mobile-open');
			document.body.classList.remove('evolve-search-open');
			activeIdx = -1;
		}

		// Mobile trigger icon → open overlay
		if (mTrigger) {
			mTrigger.addEventListener('click', () => {
				open();
				renderIdle();
			});
		}
		if (mClose) {
			mClose.addEventListener('click', (e) => { e.preventDefault(); close(); });
		}

		function setLoading(on) { results.classList.toggle('is-loading', on); }

		function renderIdle() {
			results.innerHTML = '<div class="evolve-search__hint"><span>Start typing to search products…</span></div>';
			if (viewAll) viewAll.hidden = true;
		}

		function renderSkeleton(cols) {
			const cells = Array.from({ length: cols * 2 }, () => '<li class="evolve-search__skel"></li>').join('');
			results.innerHTML = '<ul class="evolve-search__list" style="--cols:' + cols + '">' + cells + '</ul>';
		}

		function activeCols() {
			return (isMobile() && cfg.mpopup) ? (cfg.mcols || 1) : (cfg.columns || 4);
		}

		function performSearch(q) {
			if (q === lastQuery) return;
			lastQuery = q;

			if (q.length < (cfg.min || 2)) {
				renderIdle();
				return;
			}

			const cols = activeCols();
			renderSkeleton(cols);
			open();

			const body = new FormData();
			body.append('action',  'evolve_search_products');
			body.append('nonce',   cfg.nonce || (window.evolveSearch && window.evolveSearch.nonce) || '');
			body.append('q',       q);
			body.append('limit',   cfg.limit || 8);
			body.append('columns', cols);
			body.append('price',   cfg.price ? 1 : 0);
			body.append('use_ai',  cfg.ai    ? 1 : 0);

			setLoading(true);
			fetch(cfg.ajax || (window.evolveSearch && window.evolveSearch.ajax), {
				method: 'POST',
				body:    body,
				credentials: 'same-origin',
			})
			.then(r => r.json())
			.then(res => {
				setLoading(false);
				if (lastQuery !== q) return; // a newer query already fired
				if (res && res.success) {
					results.innerHTML = res.data.html || '';
					if (viewAll) {
						const found = res.data.found || 0;
						if (found > 0) {
							const tpl = viewAll.getAttribute('data-template') || 'View all results';
							viewAll.querySelector('.evolve-search__view-all-text').textContent =
								tpl + ' for "' + q + '" (' + found + ')';
							viewAll.setAttribute('href', res.data.view_all_url || '#');
							viewAll.hidden = false;
						} else {
							viewAll.hidden = true;
						}
					}
				}
			})
			.catch(() => {
				setLoading(false);
				results.innerHTML = '<div class="evolve-search__empty"><p>Search failed. Try again.</p></div>';
			});
		}

		input.addEventListener('input', () => {
			clearTimeout(timer);
			const q = input.value.trim();
			if (!q) { renderIdle(); return; }
			timer = setTimeout(() => performSearch(q), 240);
		});

		input.addEventListener('focus', () => {
			if (input.value.trim().length >= (cfg.min || 2)) {
				open();
			} else {
				open();
				renderIdle();
			}
		});

		clearBtn && clearBtn.addEventListener('click', () => {
			input.value = '';
			lastQuery = '';
			input.focus();
			renderIdle();
		});

		/* Keyboard navigation */
		input.addEventListener('keydown', (e) => {
			const items = results.querySelectorAll('.evolve-search__link');
			if (!items.length) return;

			if (e.key === 'ArrowDown') {
				e.preventDefault();
				activeIdx = Math.min(items.length - 1, activeIdx + 1);
				items.forEach((el, i) => el.classList.toggle('is-active', i === activeIdx));
				items[activeIdx].scrollIntoView({ block: 'nearest' });
			} else if (e.key === 'ArrowUp') {
				e.preventDefault();
				activeIdx = Math.max(0, activeIdx - 1);
				items.forEach((el, i) => el.classList.toggle('is-active', i === activeIdx));
				items[activeIdx].scrollIntoView({ block: 'nearest' });
			} else if (e.key === 'Enter') {
				if (activeIdx >= 0) {
					e.preventDefault();
					window.location.href = items[activeIdx].getAttribute('href');
				}
			} else if (e.key === 'Escape') {
				close();
				input.blur();
			}
		});

		/* Outside-click close */
		document.addEventListener('click', (e) => {
			if (!root.contains(e.target)) close();
		});

		/* Prevent form submit on Enter with no selection — let it submit the form natively */
	}
})();
