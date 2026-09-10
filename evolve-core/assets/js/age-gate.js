(function () {
	'use strict';
	if (typeof evolveAgeGate === 'undefined') return;

	const gate = document.getElementById('evolveAgeGate');
	if (!gate) return;

	function setCookie(days) {
		const d = new Date();
		d.setTime(d.getTime() + (days * 24 * 60 * 60 * 1000));
		document.cookie = 'evolve_age_verified=1; expires=' + d.toUTCString() + '; path=/; samesite=lax' + (location.protocol === 'https:' ? '; secure' : '');
	}

	function enter() {
		// Set client-side cookie immediately so the gate stays gone on refresh,
		// then hit the server so the cookie is also set with the proper domain/secure flags.
		setCookie(evolveAgeGate.days || 30);
		gate.classList.add('is-hidden');
		setTimeout(() => gate.remove(), 400);

		const fd = new FormData();
		fd.append('action', 'evolve_verify_age');
		fd.append('nonce', evolveAgeGate.nonce);
		fetch(evolveAgeGate.ajax, { method: 'POST', body: fd, credentials: 'same-origin' }).catch(() => {});
	}

	function exit() {
		window.location.href = evolveAgeGate.exit || 'https://www.google.com/';
	}

	gate.addEventListener('click', function (e) {
		const btn = e.target.closest('[data-evolve-age]');
		if (!btn) return;
		if (btn.dataset.evolveAge === 'enter') enter();
		else if (btn.dataset.evolveAge === 'exit') exit();
	});

	document.addEventListener('keydown', function (e) {
		if (e.key === 'Enter')  enter();
		if (e.key === 'Escape') exit();
	});
})();
