/* Evolve — Front-end UI glue
 * - Sticky header glass-blur on scroll
 * - Reveal on scroll for [data-evolve-reveal]
 * - Mobile menu open/close (triggered by [data-evolve-menu="toggle"])
 * - Lightweight cart-count refresh when Elementor's mini-cart fires events
 */
(function () {
	'use strict';

	const ready = (fn) => document.readyState !== 'loading' ? fn() : document.addEventListener('DOMContentLoaded', fn);

	ready(function () {

		// --- Sticky header ---
		const header = document.querySelector('.evolve-header, .ehp-header'); // ehp-header is Hello Elementor's
		if (header) {
			const onScroll = () => {
				header.classList.toggle('is-scrolled', window.scrollY > 8);
			};
			window.addEventListener('scroll', onScroll, { passive: true });
			onScroll();
		}

		// --- Reveal on scroll ---
		const reveal = document.querySelectorAll('[data-evolve-reveal]');
		if (reveal.length && 'IntersectionObserver' in window) {
			const io = new IntersectionObserver((entries) => {
				entries.forEach((entry) => {
					if (entry.isIntersecting) {
						entry.target.classList.add('is-visible');
						io.unobserve(entry.target);
					}
				});
			}, { threshold: 0.12 });
			reveal.forEach(el => io.observe(el));
		}

		// --- Mobile menu toggle ---
		document.querySelectorAll('[data-evolve-menu="toggle"]').forEach((btn) => {
			btn.addEventListener('click', () => {
				const target = document.querySelector(btn.dataset.target || '.evolve-mobile-menu');
				if (!target) return;
				const open = target.classList.toggle('is-open');
				document.body.classList.toggle('evolve-menu-open', open);
				btn.setAttribute('aria-expanded', open ? 'true' : 'false');
			});
		});

		// --- Update cart count badge after AJAX add-to-cart ---
		document.body.addEventListener('added_to_cart', function () {
			const badge = document.querySelector('.evolve-cart-count');
			if (!badge) return;
			// Woo's fragments handler will replace the cart node; just bump visually.
			badge.classList.add('is-bumped');
			setTimeout(() => badge.classList.remove('is-bumped'), 350);
		});

		// --- Mobile-menu trigger ---
		// Anything tagged `.evolve-mh-icon--menu` or `[data-evolve-trigger="mobile-menu"]`
		// opens the Evolve Mobile Menu popup. The popup's numeric ID is
		// resolved server-side by Popup_Linker and exposed at
		// `window.evolveMobile.menuPopupId`.
		document.addEventListener('click', function (e) {
			const t = e.target.closest('.evolve-mh-icon--menu, [data-evolve-trigger="mobile-menu"]');
			if (!t) return;
			e.preventDefault();

			const popupId = window.evolveMobile && window.evolveMobile.menuPopupId;
			if (!popupId) {
				console.warn('[Evolve] No mobile-menu popup found. Import "Evolve — Mobile Menu" via Tools → Evolve Templates.');
				return;
			}

			// Prefer Elementor Pro's popup module if loaded.
			if (window.elementorProFrontend && elementorProFrontend.modules && elementorProFrontend.modules.popup) {
				elementorProFrontend.modules.popup.showPopup({ id: popupId });
				return;
			}
			// Fallback — fire Elementor's jQuery-based popup event.
			if (window.jQuery) {
				jQuery(document).trigger('elementor/popup/show', popupId);
				return;
			}
			// Last-resort fallback: navigate to a URL with the popup hash that
			// Elementor's link handler picks up on page load.
			const settings = btoa('{"id":"' + popupId + '","toggle":"false"}');
			window.location.hash = '#elementor-action:action=popup:open&settings=' + settings;
		});

		// --- Smooth anchor scrolling for in-page links (skip popup-trigger anchors) ---
		document.querySelectorAll('a[href^="#"]').forEach((a) => {
			const id = a.getAttribute('href');
			if (id.length <= 1) return;
			if (id.indexOf('elementor-action') !== -1) return; // leave popup links alone
			a.addEventListener('click', (e) => {
				const target = document.querySelector(id);
				if (!target) return;
				e.preventDefault();
				target.scrollIntoView({ behavior: 'smooth', block: 'start' });
			});
		});
	});
})();
