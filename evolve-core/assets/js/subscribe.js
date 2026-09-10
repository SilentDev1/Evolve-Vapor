/* Evolve — Subscribe form handler
 * Submits .evolve-subscribe forms via AJAX → wp_ajax_evolve_subscribe.
 * The PHP handler routes onward to GhostPilot's Ghost-Convert CRM or the
 * local CPT log, transparently.
 */
(function () {
	'use strict';

	const ready = (fn) => document.readyState !== 'loading' ? fn() : document.addEventListener('DOMContentLoaded', fn);

	ready(() => {
		document.querySelectorAll('.evolve-subscribe').forEach(initForm);
	});

	function initForm(form) {
		if (form.dataset.evolveSubscribeBound) return;
		form.dataset.evolveSubscribeBound = '1';

		let cfg = {};
		try { cfg = JSON.parse(form.dataset.evolveSubscribe || '{}'); } catch (e) {}

		const msgEl   = form.querySelector('.evolve-subscribe__msg');
		const btn     = form.querySelector('.evolve-subscribe__btn');
		const spinner = form.querySelector('.evolve-subscribe__spinner');
		const fields  = form.querySelectorAll('input, button');

		function setMsg(text, type) {
			if (!msgEl) return;
			msgEl.textContent = text;
			msgEl.hidden = !text;
			msgEl.dataset.type = type || '';
		}
		function setBusy(busy) {
			form.classList.toggle('is-loading', busy);
			fields.forEach(el => el.disabled = busy);
			if (spinner) spinner.hidden = !busy;
		}

		form.addEventListener('submit', async function (e) {
			e.preventDefault();
			setMsg('');

			const fd = new FormData(form);
			fd.append('action', 'evolve_subscribe');
			fd.append('nonce',  (window.evolveSubscribe && window.evolveSubscribe.nonce) || '');

			setBusy(true);
			try {
				const r = await fetch((window.evolveSubscribe && window.evolveSubscribe.ajax) || '/wp-admin/admin-ajax.php', {
					method: 'POST', body: fd, credentials: 'same-origin'
				});
				const json = await r.json();
				if (json && json.success) {
					setMsg(cfg.success || (json.data && json.data.message) || 'Subscribed!', 'success');
					form.classList.add('is-done');
					// keep visible but lock further submissions
					setTimeout(() => { fields.forEach(el => el.disabled = true); }, 50);
					document.dispatchEvent(new CustomEvent('evolve:subscribed', { detail: json.data || {} }));
				} else {
					const msg = (json && json.data && (json.data.message || json.data.error)) || 'Something went wrong. Try again.';
					setMsg(msg, 'error');
					setBusy(false);
				}
			} catch (err) {
				setMsg('Network error. Try again in a moment.', 'error');
				setBusy(false);
			}
		});
	}
})();
