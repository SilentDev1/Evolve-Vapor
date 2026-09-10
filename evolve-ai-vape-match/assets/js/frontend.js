/* Evolve AI Vape Match — multi-step wizard (event-delegation version)
 *
 * v1.2.1
 * - All event handling is delegated to `document`, so the quiz survives:
 *     · being moved into / cloned out of an Elementor popup
 *     · being re-rendered after each step
 *     · being mounted multiple times on the same page
 * - Strict-CSP compatible: no eval, no `new Function`, no string-form
 *   setTimeout/setInterval, no inline event handlers in generated HTML.
 * - Per-instance state lives in a WeakMap keyed by the root element.
 */
(function () {
	'use strict';

	// Helper predicates for conditional steps + option filters.
	// "Looking for juice/flavor" = disposable & vape-juice are flavor-centric
	// by definition; everything else only counts if the shopper said yes to
	// the "also juice?" gate.
	function isLookingForJuice(a) {
		if (a.product_type === 'disposable' || a.product_type === 'vape-juice') return true;
		return a.also_juice === 'yes';
	}

	// Nicotine option whitelists per device class.
	function filterNicotineOptions(options, a) {
		// Pod-system & disposables → salt-nic only (20, 35, 50)
		if (a.product_type === 'pod-system') {
			return options.filter(function (o) { return ['20','35','50'].indexOf(o.value) !== -1; });
		}
		// Mod / sub-ohm devices → freebase only (0, 3, 6, 12)
		if (a.product_type === 'mod-device') {
			return options.filter(function (o) { return ['0','3','6','12'].indexOf(o.value) !== -1; });
		}
		// Vape juice / not-sure → show everything
		return options;
	}

	const STEPS = [
		{ key: 'experience',       title: 'How experienced are you?', type: 'choice', options: [
			{ value: 'beginner',     label: 'Beginner',     hint: 'New to vaping' },
			{ value: 'intermediate', label: 'Intermediate', hint: 'I know what I like' },
			{ value: 'advanced',     label: 'Advanced',     hint: 'Mods + sub-ohm' },
		]},
		{ key: 'product_type',     title: 'What are you looking for?', type: 'choice', options: [
			{ value: 'disposable',  label: 'Disposable',  hint: 'Grab-and-go · 5% nic' },
			{ value: 'pod-system',  label: 'Pod System',  hint: 'Refillable / replaceable pods' },
			{ value: 'vape-juice',  label: 'Vape Juice',  hint: 'For your existing device' },
			{ value: 'mod-device',  label: 'Mod / Device',hint: 'Tanks, mods, kits' },
			{ value: 'not-sure',    label: 'Not Sure',    hint: 'Show me everything' },
		]},

		/* "Also juice?" gate — only asked when the product_type isn't
		   inherently flavor-centric. Disposables and vape juice skip this
		   and jump straight to Pick a flavor family. */
		{ key: 'also_juice', title: 'Are you looking for juice / flavor too?', type: 'choice',
			showIf: function (a) {
				return ['mod-device', 'pod-system', 'not-sure'].indexOf(a.product_type) !== -1;
			},
			options: [
				{ value: 'yes', label: 'Yes',                hint: 'Recommend flavors that pair well' },
				{ value: 'no',  label: 'No, just the device',hint: 'Skip flavor questions' },
			],
		},

		/* All flavor-related questions are now gated on `isLookingForJuice`. */
		{ key: 'flavor_family',    title: 'Pick a flavor family', type: 'choice',
			showIf: isLookingForJuice,
			options: [
				{ value: 'fruity',         label: 'Fruity',           hint: '🍓 Berry, tropical, citrus' },
				{ value: 'mint-ice',       label: 'Mint / Ice',       hint: '❄️ Cool & refreshing' },
				{ value: 'candy',          label: 'Candy',            hint: '🍬 Sweet treats' },
				{ value: 'dessert',        label: 'Dessert',           hint: '🍰 Creamy, indulgent' },
				{ value: 'tobacco',        label: 'Tobacco',          hint: '🚬 Classic & roasted' },
				{ value: 'drink-inspired', label: 'Drink-Inspired',   hint: '🥤 Cola, lemonade, mojito' },
			],
		},
		{ key: 'flavor_specific',  title: 'Any specific flavors? (pick all that apply)', type: 'pills-multi',
			showIf: isLookingForJuice,
			placeholder: 'Type custom flavors, separated by commas',
			// Chips adapt to the user's flavor_family pick so a "Tobacco" shopper
			// doesn't see "Strawberry / Cola" suggestions.
			chipsFor: function (a) {
				switch (a.flavor_family) {
					case 'mint-ice':
						return ['Menthol','Spearmint','Peppermint','Wintergreen','Cool Mint','Frost','Ice'];
					case 'candy':
						return ['Strawberry Candy','Watermelon Candy','Sour Belts','Gummy','Cotton Candy','Bubblegum','Lollipop','Rainbow'];
					case 'dessert':
						return ['Vanilla','Custard','Cake','Cookie','Cream','Caramel','Chocolate','Cheesecake','Donut','Pie'];
					case 'tobacco':
						return ['Classic','Smooth','RY4','Sweet Tobacco','Menthol Tobacco','Cigar','Pipe','Cured'];
					case 'drink-inspired':
						return ['Cola','Lemonade','Mojito','Coffee','Energy Drink','Iced Tea','Champagne','Pina Colada'];
					case 'fruity':
					default:
						return ['Berry','Tropical','Citrus','Watermelon','Grape','Mixed Fruit','Mango','Strawberry','Peach','Apple','Blueberry','Pineapple','Kiwi'];
				}
			},
			chips: ['Berry','Tropical','Citrus','Watermelon','Grape','Mixed Fruit','Mango','Strawberry','Peach','Apple','Blueberry','Pineapple'],
		},
		{ key: 'sweetness_level',  title: 'How sweet do you like it?', type: 'slider', min: 1, max: 10, default: 5, leftLabel: 'Subtle', rightLabel: 'Candy-sweet',
			showIf: isLookingForJuice,
		},
		{ key: 'cooling_level',    title: 'How icy / cooling?',         type: 'slider', min: 1, max: 10, default: 3, leftLabel: 'No ice', rightLabel: 'Arctic blast',
			showIf: isLookingForJuice,
		},
		{ key: 'throat_hit',       title: 'Throat hit', type: 'choice',
			showIf: isLookingForJuice,
			options: [
				{ value: 'smooth', label: 'Smooth', hint: 'Easy inhale' },
				{ value: 'medium', label: 'Medium', hint: 'Balanced' },
				{ value: 'strong', label: 'Strong', hint: 'Big hit' },
			],
		},
		{ key: 'nicotine',         title: 'Nicotine strength', type: 'choice',
			showIf: function (a) {
				if (a.product_type === 'disposable') return false; // forced 50mg
				return isLookingForJuice(a);
			},
			filterOptions: filterNicotineOptions,
			options: [
				{ value: '0',  label: '0 mg',  hint: 'Nicotine-free' },
				{ value: '3',  label: '3 mg',  hint: 'Very low (freebase)' },
				{ value: '6',  label: '6 mg',  hint: 'Low (freebase)' },
				{ value: '12', label: '12 mg', hint: 'Medium (freebase / sub-ohm)' },
				{ value: '20', label: '20 mg', hint: 'High (salt-nic only)' },
				{ value: '35', label: '35 mg', hint: 'Strong (salt-nic only)' },
				{ value: '50', label: '50 mg', hint: 'Max (salt-nic only)' },
			],
		},

		/* Always asked. */
		{ key: 'budget',           title: 'Budget range (USD)', type: 'budget' },
		{ key: 'brand',            title: 'Preferred brand (optional)', type: 'text', placeholder: 'e.g. SMOK, Geek Bar, Naked — or leave blank' },
		{ key: 'age',              title: 'One last thing', type: 'age' },
	];

	const STATES = new WeakMap();

	function ready(fn) {
		if (document.readyState !== 'loading') { fn(); return; }
		document.addEventListener('DOMContentLoaded', fn);
	}

	ready(function () {
		document.querySelectorAll('[data-evolve-aivm]').forEach(initInstance);
		// Re-scan when Elementor reveals a popup so newly visible quiz roots get state.
		document.addEventListener('elementor/popup/show', function () {
			document.querySelectorAll('[data-evolve-aivm]').forEach(function (el) {
				if (!STATES.has(el)) { initInstance(el); }
				else { renderStep(el); }
			});
		});
	});

	function initInstance(root) {
		if (STATES.has(root)) return;
		STATES.set(root, { step: 0, answers: {} });
		renderStep(root);
	}

	function getState(root) {
		if (!STATES.has(root)) { initInstance(root); }
		return STATES.get(root);
	}

	function getWizard(root) {
		let w = root.querySelector('.evolve-aivm__wizard');
		if (!w) {
			w = document.createElement('div');
			w.className = 'evolve-aivm__wizard';
			root.appendChild(w);
		}
		return w;
	}

	function getProgress(root) {
		return root.querySelector('.evolve-aivm__progress-bar');
	}

	function visibleStepsFor(answers) {
		return STEPS.filter(function (s) {
			return typeof s.showIf !== 'function' || s.showIf(answers);
		});
	}

	function setProgress(root) {
		const state = getState(root);
		const bar   = getProgress(root);
		if (!bar) return;
		const visible = visibleStepsFor(state.answers);
		const cur     = STEPS[state.step];
		let idx = -1;
		if (cur) {
			for (let i = 0; i < visible.length; i++) {
				if (visible[i].key === cur.key) { idx = i; break; }
			}
		}
		const total = Math.max(1, visible.length);
		if (idx < 0) idx = total - 1; // we're past the last visible step (submit)
		const pct = total > 1 ? Math.round((idx / (total - 1)) * 100) : 100;
		bar.style.width = pct + '%';
	}

	function renderStep(root) {
		const state  = getState(root);
		const wizard = getWizard(root);
		setProgress(root);

		if (state.step >= STEPS.length) { submit(root); return; }

		const s = STEPS[state.step];
		wizard.innerHTML = '';

		const card = document.createElement('div');
		card.className = 'evolve-aivm__card';

		const stepBadge = document.createElement('span');
		stepBadge.className = 'evolve-aivm__step';
		// Show position in the VISIBLE step list, not the raw STEPS array,
		// so skipped questions don't inflate the count.
		const visible = visibleStepsFor(state.answers);
		let visIdx = 0;
		for (let i = 0; i < visible.length; i++) {
			if (visible[i].key === s.key) { visIdx = i; break; }
		}
		stepBadge.textContent = 'Step ' + (visIdx + 1) + ' of ' + visible.length;
		card.appendChild(stepBadge);

		const h = document.createElement('h3');
		h.className = 'evolve-aivm__qtitle';
		h.textContent = s.title;
		card.appendChild(h);

		const body = document.createElement('div');
		body.className = 'evolve-aivm__body';
		body.appendChild(renderField(root, s, state));
		card.appendChild(body);

		card.appendChild(renderNav(root, s, state));
		wizard.appendChild(card);
	}

	function renderField(root, s, state) {
		const el = document.createElement('div');
		switch (s.type) {
			case 'choice': {
				el.className = 'evolve-aivm__choices';
				// Apply per-step option filter (e.g. salt-nic-only for pod-system).
				const opts = (typeof s.filterOptions === 'function') ? s.filterOptions(s.options, state.answers) : s.options;
				opts.forEach(function (o) {
					const b = document.createElement('button');
					b.type = 'button';
					b.className = 'evolve-aivm__option';
					if (state.answers[s.key] === o.value) b.classList.add('is-selected');
					b.setAttribute('data-aivm-choice', s.key);
					b.setAttribute('data-aivm-value', o.value);
					const strong = document.createElement('strong');
					strong.textContent = o.label;
					b.appendChild(strong);
					if (o.hint) {
						const span = document.createElement('span');
						span.textContent = o.hint;
						b.appendChild(span);
					}
					el.appendChild(b);
				});
				break;
			}
			case 'pills': {
				el.className = 'evolve-aivm__pills-wrap';
				const input = document.createElement('input');
				input.type = 'text';
				input.placeholder = s.placeholder || '';
				input.className = 'evolve-aivm__input';
				input.value = state.answers[s.key] || '';
				input.setAttribute('data-aivm-text', s.key);
				el.appendChild(input);

				const pills = document.createElement('div');
				pills.className = 'evolve-aivm__pills';
				s.chips.forEach(function (c) {
					const b = document.createElement('button');
					b.type = 'button';
					b.className = 'evolve-aivm__pill';
					if ((state.answers[s.key] || '').toLowerCase() === c.toLowerCase()) b.classList.add('is-selected');
					b.textContent = c;
					b.setAttribute('data-aivm-pill', s.key);
					b.setAttribute('data-aivm-value', c);
					pills.appendChild(b);
				});
				el.appendChild(pills);
				break;
			}
			case 'pills-multi': {
				el.className = 'evolve-aivm__pills-wrap';

				// Normalize stored value to an array.
				if (!Array.isArray(state.answers[s.key])) {
					const raw = state.answers[s.key];
					state.answers[s.key] = raw
						? String(raw).split(',').map(function (x) { return x.trim(); }).filter(Boolean)
						: [];
				}
				const selected = state.answers[s.key];

				const input = document.createElement('input');
				input.type = 'text';
				input.placeholder = s.placeholder || 'Type custom flavors, separated by commas';
				input.className = 'evolve-aivm__input';
				input.value = selected.join(', ');
				input.setAttribute('data-aivm-multi', s.key);
				el.appendChild(input);

				const hint = document.createElement('p');
				hint.className = 'evolve-aivm__hint';
				hint.textContent = 'Tap any chip to add or remove it. Multiple selections are encouraged.';
				el.appendChild(hint);

				const pills = document.createElement('div');
				pills.className = 'evolve-aivm__pills evolve-aivm__pills--multi';
				const selLower = selected.map(function (x) { return String(x).toLowerCase(); });
				const chipList = (typeof s.chipsFor === 'function') ? s.chipsFor(state.answers) : s.chips;
				chipList.forEach(function (c) {
					const b = document.createElement('button');
					b.type = 'button';
					b.className = 'evolve-aivm__pill';
					if (selLower.indexOf(c.toLowerCase()) !== -1) b.classList.add('is-selected');
					b.setAttribute('data-aivm-pill-multi', s.key);
					b.setAttribute('data-aivm-value', c);

					// Add a tiny "+" mark that flips to "✓" on selection (CSS-driven)
					const mark = document.createElement('span');
					mark.className = 'evolve-aivm__pill-mark';
					mark.textContent = '+';
					mark.setAttribute('aria-hidden', 'true');
					const text = document.createElement('span');
					text.className = 'evolve-aivm__pill-text';
					text.textContent = c;
					b.appendChild(mark);
					b.appendChild(text);

					pills.appendChild(b);
				});
				el.appendChild(pills);

				// Selected-count caption
				const count = document.createElement('p');
				count.className = 'evolve-aivm__pill-count';
				count.setAttribute('data-aivm-pill-count', s.key);
				count.textContent = selected.length
					? selected.length + ' selected'
					: 'None selected — optional, skip to continue';
				el.appendChild(count);
				break;
			}
			case 'slider': {
				el.className = 'evolve-aivm__slider-wrap';
				const val = (state.answers[s.key] !== undefined && state.answers[s.key] !== '') ? state.answers[s.key] : s.default;
				const big = document.createElement('div');
				big.className = 'evolve-aivm__slider-value';
				big.textContent = String(val);
				el.appendChild(big);

				const slider = document.createElement('input');
				slider.type = 'range';
				slider.min = s.min;
				slider.max = s.max;
				slider.value = val;
				slider.className = 'evolve-aivm__slider';
				slider.setAttribute('data-aivm-slider', s.key);
				el.appendChild(slider);

				const labels = document.createElement('div');
				labels.className = 'evolve-aivm__slider-labels';
				const left  = document.createElement('span'); left.textContent  = s.leftLabel;
				const right = document.createElement('span'); right.textContent = s.rightLabel;
				labels.appendChild(left); labels.appendChild(right);
				el.appendChild(labels);

				state.answers[s.key] = parseInt(slider.value, 10);
				break;
			}
			case 'budget': {
				el.className = 'evolve-aivm__budget';
				el.appendChild(mkBudgetField(state, 'budget_min', 'Min $', '0'));
				el.appendChild(mkBudgetField(state, 'budget_max', 'Max $', 'no limit'));
				break;
			}
			case 'text': {
				el.className = 'evolve-aivm__text-wrap';
				const input = document.createElement('input');
				input.type = 'text';
				input.className = 'evolve-aivm__input';
				input.placeholder = s.placeholder || '';
				input.value = state.answers[s.key] || '';
				input.setAttribute('data-aivm-text', s.key);
				el.appendChild(input);
				break;
			}
			case 'age': {
				el.className = 'evolve-aivm__age';
				const cfg = window.evolveAIVM || {};
				const note = document.createElement('p');
				note.className = 'evolve-aivm__age-text';
				note.textContent = cfg.age_txt || 'I confirm I am of legal age to purchase nicotine products in my location.';
				el.appendChild(note);

				const lbl = document.createElement('label');
				lbl.className = 'evolve-aivm__check';
				const cb = document.createElement('input');
				cb.type = 'checkbox';
				cb.className = 'evolve-aivm__age-check';
				cb.checked = !!state.answers.age_confirmed;
				cb.setAttribute('data-aivm-age', '1');
				const checkmark = document.createElement('span'); checkmark.className = 'evolve-aivm__checkmark';
				const text = document.createElement('span'); text.textContent = 'I confirm I am of legal age.';
				lbl.appendChild(cb); lbl.appendChild(checkmark); lbl.appendChild(text);
				el.appendChild(lbl);
				break;
			}
		}
		return el;
	}

	function mkBudgetField(state, key, label, placeholder) {
		const wrap = document.createElement('label');
		wrap.className = 'evolve-aivm__field';
		const span = document.createElement('span'); span.textContent = label;
		const inp = document.createElement('input');
		inp.type = 'number';
		inp.min = '0';
		inp.placeholder = placeholder;
		inp.className = 'evolve-aivm__input';
		inp.setAttribute('data-aivm-budget', key);
		if (state.answers[key] !== undefined && state.answers[key] !== '') inp.value = state.answers[key];
		wrap.appendChild(span); wrap.appendChild(inp);
		return wrap;
	}

	function renderNav(root, s, state) {
		const nav = document.createElement('div');
		nav.className = 'evolve-aivm__nav';
		const cfg = window.evolveAIVM || {};

		if (state.step > 0) {
			const back = document.createElement('button');
			back.type = 'button';
			back.className = 'evolve-aivm__btn evolve-aivm__btn--ghost';
			back.textContent = (cfg.i18n && cfg.i18n.back) || '← Back';
			back.setAttribute('data-aivm-prev', '1');
			nav.appendChild(back);
		}

		const isLast = state.step === STEPS.length - 1;
		const fwd = document.createElement('button');
		fwd.type = 'button';
		fwd.className = 'evolve-aivm__btn evolve-aivm__btn--primary';
		fwd.textContent = isLast
			? ((cfg.i18n && cfg.i18n.submit) || 'Find My Match')
			: ((cfg.i18n && cfg.i18n.next)   || 'Next →');
		fwd.setAttribute(isLast ? 'data-aivm-submit' : 'data-aivm-next', '1');
		nav.appendChild(fwd);

		return nav;
	}

	/**
	 * Step-skip rules. Returns true when a given step should be skipped
	 * based on prior answers — useful for "disposables are always 5%"
	 * type logic. Pre-fills the answer at the same time.
	 */
	function shouldSkip(state, step) {
		if (!step) return false;

		// Per-step showIf predicate is the primary gate.
		if (typeof step.showIf === 'function' && !step.showIf(state.answers)) {
			return true;
		}

		// Disposables in the US are always 5% nicotine salt (50mg) — auto-set + skip.
		if (step.key === 'nicotine' && state.answers.product_type === 'disposable') {
			state.answers.nicotine = '50';
			return true;
		}
		return false;
	}

	function next(root) {
		const s = getState(root);
		s.step++;
		while (s.step < STEPS.length && shouldSkip(s, STEPS[s.step])) { s.step++; }
		renderStep(root);
	}
	function prev(root) {
		const s = getState(root);
		s.step = Math.max(0, s.step - 1);
		while (s.step > 0 && shouldSkip(s, STEPS[s.step])) { s.step--; }
		renderStep(root);
	}

	function flashError(root, msg) {
		const wizard = getWizard(root);
		const card = wizard.querySelector('.evolve-aivm__card');
		if (!card) return;
		const existing = card.querySelector('.evolve-aivm__error');
		if (existing) existing.remove();
		const el = document.createElement('div');
		el.className = 'evolve-aivm__error';
		el.textContent = msg;
		card.appendChild(el);
	}

	function submit(root) {
		const cfg = window.evolveAIVM || {};
		const state = getState(root);
		const wizard = getWizard(root);
		wizard.innerHTML = '';
		const wrap = document.createElement('div');
		wrap.className = 'evolve-aivm__loading';
		const spin = document.createElement('div'); spin.className = 'evolve-aivm__spinner';
		const p    = document.createElement('p');   p.textContent  = (cfg.i18n && cfg.i18n.thinking) || 'Matching products…';
		wrap.appendChild(spin); wrap.appendChild(p);
		wizard.appendChild(wrap);

		const bar = getProgress(root);
		if (bar) bar.style.width = '100%';

		fetch(cfg.rest, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce':   cfg.nonce || '',
			},
			body: JSON.stringify(state.answers),
		})
		.then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
		.then(function (res) {
			if (!res.ok) {
				wizard.innerHTML = '';
				flashError(root, res.j.error || (cfg.i18n && cfg.i18n.error) || 'Something went wrong.');
				addRestart(root);
				return;
			}
			renderResults(root, res.j);
		})
		.catch(function () {
			wizard.innerHTML = '';
			flashError(root, (cfg.i18n && cfg.i18n.error) || 'Network error.');
			addRestart(root);
		});
	}

	function addRestart(root) {
		const cfg = window.evolveAIVM || {};
		const wizard = getWizard(root);
		const b = document.createElement('button');
		b.type = 'button';
		b.className = 'evolve-aivm__btn evolve-aivm__btn--ghost';
		b.setAttribute('data-aivm-restart', '1');
		b.textContent = (cfg.i18n && cfg.i18n.restart) || 'Start over';
		wizard.appendChild(b);
	}

	function renderResults(root, data) {
		const cfg = window.evolveAIVM || {};
		const wizard = getWizard(root);
		wizard.innerHTML = '';

		if (!data.primary && (!data.flavor_matches || !data.flavor_matches.length)) {
			const empty = document.createElement('div');
			empty.className = 'evolve-aivm__empty';
			const p = document.createElement('p');
			p.textContent = data.message || (cfg.i18n && cfg.i18n.no_match) || 'No products match yet.';
			empty.appendChild(p);
			wizard.appendChild(empty);
			addRestart(root);
			return;
		}

		if (data.primary) {
			const section = document.createElement('section');
			section.className = 'evolve-aivm__primary';
			const eye = document.createElement('span');
			eye.className = 'evolve-aivm__section-eyebrow';
			eye.textContent = 'YOUR PERFECT MATCH';
			section.appendChild(eye);
			section.appendChild(buildCard(data.primary, 'primary'));
			wizard.appendChild(section);
		}

		if (data.flavor_matches && data.flavor_matches.length) {
			const section = document.createElement('section');
			section.className = 'evolve-aivm__section';
			const h = document.createElement('h3');
			h.className = 'evolve-aivm__section-title';
			h.textContent = 'Flavor matches you may like';
			section.appendChild(h);
			const grid = document.createElement('div');
			grid.className = 'evolve-aivm__grid';
			data.flavor_matches.forEach(function (p) { grid.appendChild(buildCard(p, 'flavor')); });
			section.appendChild(grid);
			wizard.appendChild(section);
		}

		if (data.setup && data.setup.length) {
			const section = document.createElement('section');
			section.className = 'evolve-aivm__section';
			const h = document.createElement('h3');
			h.className = 'evolve-aivm__section-title';
			h.textContent = 'Complete your setup';
			section.appendChild(h);
			const grid = document.createElement('div');
			grid.className = 'evolve-aivm__grid';
			data.setup.forEach(function (p) { grid.appendChild(buildCard(p, 'setup')); });
			section.appendChild(grid);
			wizard.appendChild(section);
		}

		const footer = document.createElement('div');
		footer.className = 'evolve-aivm__footer';
		const src = document.createElement('span');
		src.className = 'evolve-aivm__source';
		src.textContent = 'Source: ' + (data.source || 'rule-based');
		footer.appendChild(src);
		const restart = document.createElement('button');
		restart.type = 'button';
		restart.className = 'evolve-aivm__btn evolve-aivm__btn--ghost';
		restart.setAttribute('data-aivm-restart', '1');
		restart.textContent = (cfg.i18n && cfg.i18n.restart) || 'Start over';
		footer.appendChild(restart);
		wizard.appendChild(footer);
	}

	function buildCard(p, kind) {
		const cfg = window.evolveAIVM || {};
		const card = document.createElement('article');
		card.className = 'evolve-aivm-card evolve-aivm-card--' + kind;

		const mediaLink = document.createElement('a');
		mediaLink.href = p.permalink || '#';
		mediaLink.className = 'evolve-aivm-card__media';
		const img = document.createElement('img');
		img.src = p.image || '';
		img.alt = p.name || '';
		img.loading = 'lazy';
		mediaLink.appendChild(img);
		card.appendChild(mediaLink);

		if (p.label) {
			const label = document.createElement('span');
			label.className = 'evolve-aivm__label';
			label.textContent = p.label;
			card.appendChild(label);
		}

		const body = document.createElement('div');
		body.className = 'evolve-aivm-card__body';

		const titleLink = document.createElement('a');
		titleLink.className = 'evolve-aivm-card__title';
		titleLink.href = p.permalink || '#';
		titleLink.textContent = p.name || '';
		body.appendChild(titleLink);

		const price = document.createElement('div');
		price.className = 'evolve-aivm-card__price';
		// price_html is server-sanitized WC output — safe to assign
		if (p.price_html) { price.innerHTML = p.price_html; } else { price.textContent = '$' + (p.price || 0); }
		body.appendChild(price);

		if (typeof p.match_percent === 'number' || p.match_score) {
			const pct = document.createElement('span');
			pct.className = 'evolve-aivm__pct';
			pct.textContent = (p.match_percent || p.match_score) + '% match';
			body.appendChild(pct);
		}

		if (p.reason) {
			const reason = document.createElement('p');
			reason.className = 'evolve-aivm__reason';
			reason.textContent = p.reason;
			body.appendChild(reason);
		}

		const cta = document.createElement('div');
		cta.className = 'evolve-aivm-card__cta';

		const add = document.createElement('button');
		add.type = 'button';
		add.className = 'evolve-aivm-card__add';
		add.setAttribute('data-aivm-add', String(p.id));
		add.setAttribute('data-aivm-url', p.add_to_cart_url || '');
		add.setAttribute('data-aivm-ajax', p.ajax_add_to_cart ? '1' : '0');
		add.textContent = (cfg.i18n && cfg.i18n.add_cart) || 'Add to Cart';
		cta.appendChild(add);

		const view = document.createElement('a');
		view.className = 'evolve-aivm-card__view';
		view.href = p.permalink || '#';
		view.textContent = (cfg.i18n && cfg.i18n.view) || 'View';
		cta.appendChild(view);

		body.appendChild(cta);
		card.appendChild(body);
		return card;
	}

	/* ====================================================================
	 * Single document-level delegated listeners
	 * ================================================================= */
	document.addEventListener('click', function (e) {
		const root = e.target.closest('[data-evolve-aivm]');

		// FAB → scroll to or open quiz
		const fab = e.target.closest('[data-evolve-aivm-fab]');
		if (fab) {
			const target = document.querySelector('[data-evolve-aivm]');
			if (target) {
				target.scrollIntoView({ behavior: 'smooth', block: 'start' });
				const first = target.querySelector('.evolve-aivm__option, input, button');
				if (first) window.setTimeout(function () { first.focus(); }, 400);
			} else {
				openModalQuiz();
			}
			return;
		}

		if (!root) return;
		const state = getState(root);
		const target = e.target;

		// Restart
		if (target.closest('[data-aivm-restart]')) {
			state.step = 0;
			state.answers = {};
			renderStep(root);
			return;
		}

		// Choice option
		const choice = target.closest('[data-aivm-choice]');
		if (choice) {
			e.preventDefault();
			const key = choice.getAttribute('data-aivm-choice');
			const val = choice.getAttribute('data-aivm-value');
			state.answers[key] = val;

			// If the user changes product_type AWAY from "disposable" via
			// the Back button, clear the auto-set 50mg so the nicotine
			// step shows again.
			if (key === 'product_type' && val !== 'disposable' && state.answers.nicotine === '50') {
				// Only clear if it was auto-set — heuristic: nicotine === '50' but no manual touch.
				// We can't track "manual" cheaply, so always clear on change. User will see the nic
				// step again and can re-pick 50mg manually.
				state.answers.nicotine = '';
			}

			root.querySelectorAll('.evolve-aivm__option').forEach(function (x) { x.classList.remove('is-selected'); });
			choice.classList.add('is-selected');
			// Auto-advance — CSP-safe function form of setTimeout
			window.setTimeout(function () { next(root); }, 200);
			return;
		}

		// Pill (single-select flavor chip)
		const pill = target.closest('[data-aivm-pill]');
		if (pill) {
			e.preventDefault();
			const key = pill.getAttribute('data-aivm-pill');
			const val = pill.getAttribute('data-aivm-value');
			state.answers[key] = val;
			const input = root.querySelector('[data-aivm-text="' + cssEsc(key) + '"]');
			if (input) input.value = val;
			root.querySelectorAll('[data-aivm-pill="' + cssEsc(key) + '"]').forEach(function (x) { x.classList.remove('is-selected'); });
			pill.classList.add('is-selected');
			return;
		}

		// Pill (MULTI-select flavor chip) — toggle in/out of array
		const pillMulti = target.closest('[data-aivm-pill-multi]');
		if (pillMulti) {
			e.preventDefault();
			const key = pillMulti.getAttribute('data-aivm-pill-multi');
			const val = pillMulti.getAttribute('data-aivm-value');
			if (!Array.isArray(state.answers[key])) state.answers[key] = [];
			const idx = state.answers[key].findIndex(function (x) {
				return String(x).toLowerCase() === String(val).toLowerCase();
			});
			if (idx === -1) {
				state.answers[key].push(val);
				pillMulti.classList.add('is-selected');
			} else {
				state.answers[key].splice(idx, 1);
				pillMulti.classList.remove('is-selected');
			}
			// Sync the visible text input + count caption
			const input = root.querySelector('[data-aivm-multi="' + cssEsc(key) + '"]');
			if (input) input.value = state.answers[key].join(', ');
			const count = root.querySelector('[data-aivm-pill-count="' + cssEsc(key) + '"]');
			if (count) {
				count.textContent = state.answers[key].length
					? state.answers[key].length + ' selected'
					: 'None selected — optional, skip to continue';
			}
			return;
		}

		// Next / Previous / Submit
		if (target.closest('[data-aivm-next]')) {
			e.preventDefault();
			next(root);
			return;
		}
		if (target.closest('[data-aivm-prev]')) {
			e.preventDefault();
			prev(root);
			return;
		}
		if (target.closest('[data-aivm-submit]')) {
			e.preventDefault();
			if (!state.answers.age_confirmed) {
				flashError(root, 'Please confirm your age to continue.');
				return;
			}
			submit(root);
			return;
		}

		// Add to Cart
		const addBtn = target.closest('[data-aivm-add]');
		if (addBtn) {
			const id   = addBtn.getAttribute('data-aivm-add');
			const url  = addBtn.getAttribute('data-aivm-url');
			const ajax = addBtn.getAttribute('data-aivm-ajax') === '1';
			if (!ajax) { return; } // let anchor-style fallback navigate naturally
			e.preventDefault();
			addBtn.classList.add('is-loading');
			fetch('/?wc-ajax=add_to_cart', {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: 'product_id=' + encodeURIComponent(id) + '&quantity=1',
			})
			.then(function () {
				addBtn.classList.remove('is-loading');
				addBtn.classList.add('is-added');
				addBtn.textContent = '✓ Added';
				document.body.dispatchEvent(new CustomEvent('added_to_cart'));
			})
			.catch(function () {
				addBtn.classList.remove('is-loading');
				if (url) window.location.href = url;
			});
			return;
		}
	});

	// Live input updates (text fields, slider, budget, age checkbox)
	document.addEventListener('input', function (e) {
		const root = e.target.closest('[data-evolve-aivm]');
		if (!root) return;
		const state = getState(root);
		const target = e.target;

		const text = target.getAttribute && target.getAttribute('data-aivm-text');
		if (text) { state.answers[text] = (target.value || '').trim(); return; }

		// Multi-select text input — parse comma-separated into the array and
		// re-sync pill highlighting + count caption.
		const multi = target.getAttribute && target.getAttribute('data-aivm-multi');
		if (multi) {
			const parts = (target.value || '')
				.split(',')
				.map(function (x) { return x.trim(); })
				.filter(Boolean);
			state.answers[multi] = parts;
			const lower = parts.map(function (x) { return x.toLowerCase(); });
			root.querySelectorAll('[data-aivm-pill-multi="' + cssEsc(multi) + '"]').forEach(function (b) {
				const v = (b.getAttribute('data-aivm-value') || '').toLowerCase();
				b.classList.toggle('is-selected', lower.indexOf(v) !== -1);
			});
			const count = root.querySelector('[data-aivm-pill-count="' + cssEsc(multi) + '"]');
			if (count) {
				count.textContent = parts.length
					? parts.length + ' selected'
					: 'None selected — optional, skip to continue';
			}
			return;
		}

		const slider = target.getAttribute && target.getAttribute('data-aivm-slider');
		if (slider) {
			state.answers[slider] = parseInt(target.value, 10);
			const big = target.parentElement && target.parentElement.querySelector('.evolve-aivm__slider-value');
			if (big) big.textContent = target.value;
			return;
		}

		const budget = target.getAttribute && target.getAttribute('data-aivm-budget');
		if (budget) {
			state.answers[budget] = target.value === '' ? '' : parseFloat(target.value);
			return;
		}
	});

	document.addEventListener('change', function (e) {
		const root = e.target.closest('[data-evolve-aivm]');
		if (!root) return;
		const state = getState(root);
		if (e.target.getAttribute && e.target.getAttribute('data-aivm-age') === '1') {
			state.answers.age_confirmed = !!e.target.checked;
		}
	});

	function openModalQuiz() {
		let modal = document.querySelector('.evolve-aivm-modal');
		if (modal) { modal.classList.add('is-open'); return; }
		modal = document.createElement('div');
		modal.className = 'evolve-aivm-modal is-open';
		const inner = document.createElement('div');
		inner.className = 'evolve-aivm-modal__inner';
		const close = document.createElement('button');
		close.className = 'evolve-aivm-modal__close';
		close.setAttribute('aria-label', 'Close');
		close.textContent = '×';
		close.addEventListener('click', function () { modal.classList.remove('is-open'); });
		inner.appendChild(close);

		const mount = document.createElement('div');
		mount.className = 'evolve-aivm';
		mount.setAttribute('data-evolve-aivm', '');
		const prog = document.createElement('div'); prog.className = 'evolve-aivm__progress';
		const bar  = document.createElement('div'); bar.className  = 'evolve-aivm__progress-bar';
		prog.appendChild(bar); mount.appendChild(prog);
		const wiz = document.createElement('div'); wiz.className = 'evolve-aivm__wizard';
		mount.appendChild(wiz);
		inner.appendChild(mount);

		modal.appendChild(inner);
		modal.addEventListener('click', function (e) { if (e.target === modal) modal.classList.remove('is-open'); });
		document.body.appendChild(modal);
		initInstance(mount);
	}

	function cssEsc(s) {
		// Minimal CSS attribute-value escape — keys are alphanumeric+underscore so just guard quotes.
		return String(s == null ? '' : s).replace(/["\\]/g, '\\$&');
	}
})();
