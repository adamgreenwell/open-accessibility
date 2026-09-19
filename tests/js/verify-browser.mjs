/**
 * Browser-driven checks for the Open Accessibility frontend.
 *
 * PHPUnit can only assert what PHP produces; the script's own behaviour — state
 * handling, what it applies to the page, what it restores — has no automated
 * coverage otherwise. This drives a real browser over the Chrome DevTools
 * Protocol to check exactly that, and needs no dependencies: Node 22+ ships both
 * `fetch` and `WebSocket`.
 *
 * Usage:
 *
 *   1. Start the site:            docker compose up -d
 *   2. Start Chrome with a debug port:
 *
 *        "/Applications/Google Chrome.app/Contents/MacOS/Google Chrome" \
 *          --headless=new --remote-debugging-port=9222 \
 *          --user-data-dir=/tmp/oa-chrome http://localhost:9080/
 *
 *   3. Run:                      node tests/js/verify-browser.mjs
 *
 * Environment:
 *   OA_CDP_URL   CDP endpoint (default http://127.0.0.1:9222)
 *   OA_SITE_URL  page to test    (default http://localhost:9080/)
 *
 * Exits non-zero if any check fails, so it can be wired into CI once a browser
 * is available there.
 */

const CDP_URL = process.env.OA_CDP_URL || 'http://127.0.0.1:9222';
const SITE_URL = process.env.OA_SITE_URL || 'http://localhost:9080/';

const results = [];

function check(name, passed, detail) {
	results.push({ name, passed: Boolean(passed), detail });

	const mark = passed ? 'PASS' : 'FAIL';
	process.stdout.write(`  ${mark}  ${name}${detail ? ` — ${detail}` : ''}\n`);
}

/**
 * Minimal CDP client over a single page target.
 */
async function connect() {
	const targets = await (await fetch(`${CDP_URL}/json/list`)).json();
	const page = targets.find((t) => t.type === 'page' && t.webSocketDebuggerUrl);

	if (!page) {
		throw new Error('No page target with a debugger URL. Is Chrome running with --remote-debugging-port?');
	}

	const socket = new WebSocket(page.webSocketDebuggerUrl);

	await new Promise((resolve, reject) => {
		socket.addEventListener('open', resolve, { once: true });
		socket.addEventListener('error', () => reject(new Error('Could not open the CDP socket.')), { once: true });
	});

	let nextId = 1;
	const pending = new Map();

	socket.addEventListener('message', (event) => {
		const message = JSON.parse(event.data);

		if (message.id && pending.has(message.id)) {
			const { resolve, reject } = pending.get(message.id);
			pending.delete(message.id);

			if (message.error) {
				reject(new Error(message.error.message));
			} else {
				resolve(message.result);
			}
		}
	});

	const send = (method, params = {}) =>
		new Promise((resolve, reject) => {
			const id = nextId++;
			pending.set(id, { resolve, reject });
			socket.send(JSON.stringify({ id, method, params }));
		});

	return { send, close: () => socket.close() };
}

/**
 * Evaluate an expression in the page and return its value.
 *
 * @param {{send: Function}} client CDP client.
 * @param {string}           expression JavaScript expression.
 * @return {Promise<any>} The evaluated value.
 */
async function evaluate(client, expression) {
	const result = await client.send('Runtime.evaluate', {
		expression,
		awaitPromise: true,
		returnByValue: true
	});

	if (result.exceptionDetails) {
		throw new Error(result.exceptionDetails.exception?.description || 'Evaluation threw.');
	}

	return result.result.value;
}

async function main() {
	process.stdout.write(`\nOpen Accessibility — browser checks against ${SITE_URL}\n\n`);

	const client = await connect();

	try {
		await client.send('Page.enable');
		await client.send('Page.navigate', { url: SITE_URL });

		// Wait for the widget script to expose its API.
		const ready = await evaluate(
			client,
			`new Promise((resolve) => {
				let tries = 0;
				const tick = () => {
					if (window.OpenAccessibility && document.querySelector('.open-accessibility-toggle-button')) {
						resolve(true);
					} else if (tries++ > 100) {
						resolve(false);
					} else {
						setTimeout(tick, 50);
					}
				};
				tick();
			})`
		);

		if (!ready) {
			check('page exposes window.OpenAccessibility', false, 'widget never initialised');
			return;
		}

		check('page exposes window.OpenAccessibility', true);

		// --- API surface -------------------------------------------------
		const api = await evaluate(
			client,
			`({
				getProfiles: typeof window.OpenAccessibility.getProfiles,
				setProfile: typeof window.OpenAccessibility.setProfile,
				getState: typeof window.OpenAccessibility.getState,
				setState: typeof window.OpenAccessibility.setState,
				profileCount: Object.keys(window.OpenAccessibility.getProfiles()).length
			})`
		);

		check('profile API is exposed', api.getProfiles === 'function' && api.setProfile === 'function');
		check('profiles reach the script', api.profileCount === 5, `${api.profileCount} profiles`);

		// --- Profile application ------------------------------------------
		const applied = await evaluate(
			client,
			`(() => {
				const ok = window.OpenAccessibility.setProfile('seizure_safe');
				const state = window.OpenAccessibility.getState();
				return { ok: ok, contrast: state.contrast, pause: state.pauseAnimations, active: state.activeProfile };
			})()`
		);

		check('applying a profile returns true', applied.ok === true);
		check('profile state is applied', applied.contrast === 'light' && applied.pause === true, JSON.stringify(applied));
		check('active profile is recorded', applied.active === 'seizure_safe');

		// --- Review finding: a profile must not clear the cursor size ------
		const carried = await evaluate(
			client,
			`(() => {
				window.OpenAccessibility.setState({ cursorSize: 'xlarge' });
				const before = document.documentElement.className;
				window.OpenAccessibility.setProfile('blind');
				return {
					beforeHasClass: before.indexOf('open-accessibility-cursor-xlarge') !== -1,
					afterHasClass: document.documentElement.className.indexOf('open-accessibility-cursor-xlarge') !== -1,
					stateCursor: window.OpenAccessibility.getState().cursorSize
				};
			})()`
		);

		check('cursor size class applies', carried.beforeHasClass, 'html.className before profile');
		check(
			'a profile does not clear the cursor size',
			carried.afterHasClass && carried.stateCursor === 'xlarge',
			`class=${carried.afterHasClass} state=${carried.stateCursor}`
		);

		// --- Review finding: a manual setState clears the profile marker ---
		const cleared = await evaluate(
			client,
			`(() => {
				window.OpenAccessibility.setProfile('blind');
				const had = window.OpenAccessibility.getState().activeProfile;
				window.OpenAccessibility.setState({ grayscale: true });
				return { had: had, now: window.OpenAccessibility.getState().activeProfile };
			})()`
		);

		check('a partial setState clears the profile marker', cleared.had === 'blind' && cleared.now === '', JSON.stringify(cleared));

		// --- Saturation ----------------------------------------------------
		const saturation = await evaluate(
			client,
			`(() => {
				window.OpenAccessibility.setState({ saturationLevel: 2 });
				const filtered = Array.from(document.querySelectorAll('[data-oa-saturation-managed="1"]'));
				const values = filtered.map((el) => el.style.filter).filter(Boolean);
				window.OpenAccessibility.setState({ saturationLevel: 0 });
				const afterReset = document.querySelectorAll('[data-oa-saturation-managed="1"]').length;
				return { applied: values.length, sample: values[0] || null, afterReset: afterReset };
			})()`
		);

		check('saturation filters target elements', saturation.applied > 0, `${saturation.applied} elements, e.g. ${saturation.sample}`);
		check('saturation is removed at level 0', saturation.afterReset === 0);

		// --- Highlight links ------------------------------------------------
		const highlight = await evaluate(
			client,
			`(() => {
				window.OpenAccessibility.setState({ highlightLinks: true });
				const marked = document.querySelectorAll('[data-oa-highlight-links-managed="1"]');
				const withBackground = Array.from(marked).filter((el) => el.style.backgroundColor).length;
				const sample = marked[0] ? marked[0].style.backgroundColor : null;
				window.OpenAccessibility.setState({ highlightLinks: false });
				return { marked: marked.length, withBackground: withBackground, sample: sample, afterOff: document.querySelectorAll('[data-oa-highlight-links-managed="1"]').length };
			})()`
		);

		check('highlight marks links', highlight.marked > 0, `${highlight.marked} links, background ${highlight.sample}`);
		check('highlight is fully restored', highlight.afterOff === 0);

		// --- Widget chrome is never inside a filtered ancestor --------------
		const widgetSafe = await evaluate(
			client,
			`(() => {
				window.OpenAccessibility.setState({ saturationLevel: 3, grayscale: true });
				const wrapper = document.querySelector('.open-accessibility-widget-wrapper');
				let node = wrapper.parentElement;
				let filteredAncestor = null;
				while (node && node !== document.documentElement) {
					const filter = window.getComputedStyle(node).filter;
					if (filter && filter !== 'none') { filteredAncestor = node.tagName + '.' + node.className; break; }
					node = node.parentElement;
				}
				window.OpenAccessibility.setState({ saturationLevel: 0, grayscale: false });
				return { filteredAncestor: filteredAncestor };
			})()`
		);

		check(
			'no filtered ancestor of the widget',
			widgetSafe.filteredAncestor === null,
			widgetSafe.filteredAncestor || 'none'
		);

		// --- Reset restores everything --------------------------------------
		const reset = await evaluate(
			client,
			`(() => {
				window.OpenAccessibility.setState({ saturationLevel: 2, highlightLinks: true, cursorSize: 'xlarge', grayscale: true });
				document.querySelector('.open-accessibility-reset-button').click();
				return {
					saturation: document.querySelectorAll('[data-oa-saturation-managed="1"]').length,
					highlight: document.querySelectorAll('[data-oa-highlight-links-managed="1"]').length,
					cursorClass: document.documentElement.className.indexOf('open-accessibility-cursor') !== -1,
					state: window.OpenAccessibility.getState()
				};
			})()`
		);

		check('reset clears saturation', reset.saturation === 0);
		check('reset clears highlighted links', reset.highlight === 0);
		check('reset clears the cursor size', reset.cursorClass === false);
		check('reset clears the profile marker', reset.state.activeProfile === '');
	} finally {
		client.close();
	}
}

main()
	.then(() => {
		const failed = results.filter((r) => !r.passed);

		process.stdout.write(`\n${results.length - failed.length}/${results.length} checks passed\n\n`);

		process.exit(failed.length === 0 ? 0 : 1);
	})
	.catch((error) => {
		process.stderr.write(`\nBrowser checks could not run: ${error.message}\n\n`);
		process.exit(2);
	});
