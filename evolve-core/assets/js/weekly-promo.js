(function () {
	'use strict';
	if (typeof evolveWeeklyPromoData === 'undefined') return;

	var STORAGE_KEY = 'evolve_weekly_promo_dismissed';
	var DELAY_MS    = 3000;

	// Already dismissed this session.
	if (sessionStorage.getItem(STORAGE_KEY)) return;

	var popup = document.getElementById('evolveWeeklyPromo');
	if (!popup) return;

	function show() {
		popup.style.display = '';
		// Force reflow before adding class so the transition fires.
		popup.offsetHeight; // eslint-disable-line no-unused-expressions
		popup.classList.add('is-visible');
	}

	function dismiss() {
		popup.classList.remove('is-visible');
		popup.classList.add('is-hidden');
		sessionStorage.setItem(STORAGE_KEY, '1');
		setTimeout(function () { popup.remove(); }, 400);
	}

	// Close on button click.
	popup.addEventListener('click', function (e) {
		if (e.target.closest('[data-evolve-promo="close"]')) {
			dismiss();
			return;
		}
		// Click on background (outside card) dismisses.
		if (e.target.classList.contains('evolve-weekly-promo__bg')) {
			dismiss();
		}
	});

	// Escape key closes.
	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape' && popup.classList.contains('is-visible')) {
			dismiss();
		}
	});

	function scheduleShow() {
		setTimeout(show, DELAY_MS);
	}

	// If age gate is present, wait for it to be dismissed.
	var ageGate = document.getElementById('evolveAgeGate');
	if (ageGate && !ageGate.classList.contains('is-hidden')) {
		var observer = new MutationObserver(function (mutations) {
			for (var i = 0; i < mutations.length; i++) {
				if (ageGate.classList.contains('is-hidden') || !document.getElementById('evolveAgeGate')) {
					observer.disconnect();
					scheduleShow();
					return;
				}
			}
		});
		observer.observe(ageGate, { attributes: true, attributeFilter: ['class'] });

		// Also watch for removal from DOM.
		var parentObserver = new MutationObserver(function () {
			if (!document.getElementById('evolveAgeGate')) {
				parentObserver.disconnect();
				observer.disconnect();
				scheduleShow();
			}
		});
		parentObserver.observe(ageGate.parentNode, { childList: true });
	} else {
		// No age gate or already verified — show after delay.
		scheduleShow();
	}
})();
