/* Evolve — Vape Match Button trigger
 * Two modes:
 *   data-evolve-vmb="popup"  → open the Vape Match popup via Elementor Pro API
 *   data-evolve-vmb="inline" → smooth-scroll to first .evolve-aivm on the page
 */
(function () {
	'use strict';

	document.addEventListener('click', function (e) {
		const t = e.target.closest('[data-evolve-vmb]');
		if (!t) return;
		e.preventDefault();

		const mode = t.getAttribute('data-evolve-vmb');

		if (mode === 'inline') {
			const target = document.querySelector('.evolve-aivm, [data-evolve-aivm]');
			if (target) {
				target.scrollIntoView({ behavior: 'smooth', block: 'start' });
				const first = target.querySelector('button, input, .evolve-aivm__option');
				if (first) setTimeout(() => first.focus({ preventScroll: true }), 500);
				return;
			}
			// No inline shortcode found — fall through and try popup as a backup
		}

		// Popup mode (or inline fallback)
		const popupId = window.evolveVapeMatch && window.evolveVapeMatch.popupId;
		if (popupId && window.elementorProFrontend && elementorProFrontend.modules && elementorProFrontend.modules.popup) {
			elementorProFrontend.modules.popup.showPopup({ id: popupId });
			return;
		}
		if (popupId && window.jQuery) {
			jQuery(document).trigger('elementor/popup/show', popupId);
			return;
		}

		// Final fallback — go to a /vape-match/ page if one exists.
		console.warn('[Evolve Vape Match] Popup not found. Import "Evolve — Vape Match Quiz" via Tools → Evolve Templates, or change the widget to "Link to a page URL".');
	});
})();
