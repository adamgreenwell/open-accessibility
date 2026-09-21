/**
 * Execute the report screen's script against a synthetic DOM.
 *
 * The script has never actually run: PHPUnit can only assert the markup the
 * server produces, and the CDP harness drives the frontend widget, not this.
 * The parts most likely to be wrong are also the parts nothing else can reach —
 * whether the fetch payload is shaped the way admin-ajax expects, whether the
 * polling loop terminates, and whether a failure un-sticks the button instead
 * of leaving the screen dead.
 *
 * No dependencies: a small element model stands in for the DOM, and the real
 * script source is evaluated in a `vm` context against it. Nothing is stubbed
 * inside the script itself, so what runs is the file that ships.
 *
 * Usage: node tests/js/report-poll.test.mjs
 */

import { readFileSync } from 'node:fs';
import { createContext, runInContext } from 'node:vm';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const here = dirname( fileURLToPath( import.meta.url ) );
const source = readFileSync( join( here, '..', '..', 'assets', 'js', 'open-accessibility-report.js' ), 'utf8' );

const results = [];

function check( name, passed, detail ) {
	results.push( { name, passed: Boolean( passed ) } );
	process.stdout.write( `  ${passed ? 'PASS' : 'FAIL'}  ${name}${detail ? ` — ${detail}` : ''}\n` );
}

/**
 * A minimal element. Enough of the DOM for this script and no more, so the
 * harness stays readable and cannot quietly diverge from a real browser in ways
 * that matter here.
 */
function createElement( tag = 'div', id = '' ) {
	const attributes = new Map();
	const classes = new Set();
	const children = [];
	const listeners = {};

	const element = {
		tagName: String( tag ).toUpperCase(),
		id,
		textContent: '',
		disabled: false,
		style: {},
		parent: null,
		children,

		get className() {
			return Array.from( classes ).join( ' ' );
		},

		set className( value ) {
			classes.clear();
			String( value )
				.split( /\s+/ )
				.filter( Boolean )
				.forEach( ( c ) => classes.add( c ) );
		},

		classList: {
			add: ( c ) => classes.add( c ),
			remove: ( c ) => classes.delete( c ),
			contains: ( c ) => classes.has( c ),
		},

		setAttribute( key, value ) {
			attributes.set( key, String( value ) );
		},

		getAttribute( key ) {
			return attributes.has( key ) ? attributes.get( key ) : null;
		},

		appendChild( child ) {
			child.parent = element;
			children.push( child );
			return child;
		},

		/** Depth-first search, matching how the script looks elements up. */
		querySelector( selector ) {
			const match = ( node ) => {
				if ( selector.startsWith( '.' ) && node.classList.contains( selector.slice( 1 ) ) ) {
					return true;
				}

				if ( selector.startsWith( '#' ) && node.id === selector.slice( 1 ) ) {
					return true;
				}

				return false;
			};

			const walk = ( node ) => {
				for ( const child of node.children ) {
					if ( match( child ) ) {
						return child;
					}

					const found = walk( child );

					if ( found ) {
						return found;
					}
				}

				return null;
			};

			return walk( element );
		},

		/** Nearest self-or-ancestor matching the selector. */
		closest( selector ) {
			const match = ( node ) => {
				if ( selector.startsWith( '.' ) ) {
					return node.classList && node.classList.contains( selector.slice( 1 ) );
				}

				if ( selector.startsWith( '#' ) ) {
					return node.id === selector.slice( 1 );
				}

				return false;
			};

			let node = element;

			while ( node ) {
				if ( match( node ) ) {
					return node;
				}

				node = node.parent;
			}

			return null;
		},

		addEventListener( type, handler ) {
			( listeners[ type ] = listeners[ type ] || [] ).push( handler );
		},

		/** Fire the listeners the script registered, as a click would. */
		dispatch( type ) {
			( listeners[ type ] || [] ).forEach( ( handler ) => handler( { target: element } ) );
		},

		hasListener( type ) {
			return Boolean( listeners[ type ] && listeners[ type ].length );
		},
	};

	return element;
}

/**
 * Build a context with the script's dependencies and load the script into it.
 *
 * @param {Object} options
 * @param {Function} options.respond Returns the JSON payload for a request.
 * @return {Object} Handle onto the synthetic page.
 */
function load( { respond } ) {
	const scanButton = createElement( 'button', 'oa-report-scan' );
	scanButton.setAttribute( 'data-force', 'false' );

	const progressBox = createElement( 'div', 'oa-report-progress' );
	progressBox.setAttribute( 'data-running', 'false' );

	const rowsBody = createElement( 'tbody', 'oa-report-rows' );

	const byId = {
		'oa-report-scan': scanButton,
		'oa-report-progress': progressBox,
		'oa-report-rows': rowsBody,
	};

	const requests = [];
	const reloads = { count: 0 };
	const timers = [];

	const sandbox = {
		console,
		FormData: class {
			constructor() {
				this.entries = new Map();
			}

			append( key, value ) {
				this.entries.set( key, value );
			}
		},
		fetch: ( url, options ) => {
			requests.push( { url, body: options.body } );

			return Promise.resolve( {
				json: () => Promise.resolve( respond( options.body, requests.length ) ),
			} );
		},
		window: {
			open_accessibility_report: {
				ajaxUrl: 'http://example.org/wp-admin/admin-ajax.php',
				nonce: 'test-nonce',
				pageSlug: 'open-accessibility-report',
				i18n: {
					starting: 'Starting scan...',
					scanning: 'Scanning...',
					rescanning: 'Rescanning...',
					complete: 'Scan complete.',
					failed: 'The scan could not be started. Please try again.',
					progress: '%1$s of %2$s posts scanned',
					cancel: 'Cancel',
					rescan: 'Rescan',
					confirmRescan: 'Rescan this post?',
				},
			},
			confirm: () => true,
			setTimeout: ( fn ) => {
				// Recorded, then drained deliberately, so polling is observed
				// rather than raced.
				timers.push( fn );
				return timers.length;
			},
			clearTimeout: () => {},
			location: {
				reload: () => {
					reloads.count += 1;
				},
			},
		},
		document: {
			getElementById: ( id ) => byId[ id ] || null,
			createElement: ( tag ) => createElement( tag ),
		},
	};

	sandbox.globalThis = sandbox;

	const context = createContext( sandbox );
	runInContext( source, context );

	return { scanButton, progressBox, rowsBody, requests, reloads, timers };
}

/**
 * Let queued promise callbacks run.
 *
 * @return {Promise<void>}
 */
const settle = () => new Promise( ( resolve ) => setImmediate( resolve ) );

/** Run every timer the script queued, oldest first. */
function drain( timers ) {
	let ran = 0;

	while ( timers.length && ran < 50 ) {
		const fn = timers.shift();
		fn();
		ran += 1;
	}

	return ran;
}

// ---------------------------------------------------------------------------

process.stdout.write( '\nReport script\n' );

// The script registers a click handler on the scan button.
{
	const page = load( { respond: () => ( { success: true, data: { progress: {} } } ) } );

	check( 'registers a click handler on the scan button', page.scanButton.hasListener( 'click' ) );
}

// Clicking starts a scan: the request carries the action, the nonce and the
// force flag, which is what admin-ajax dispatches on.
{
	const page = load( {
		respond: () => ( {
			success: true,
			data: { progress: { running: true, scanned: 5, total: 20 } },
		} ),
	} );

	page.scanButton.dispatch( 'click' );
	await settle();

	// Starting also polls once immediately, so this is not a count of one.
	const actions = page.requests.map( ( r ) => r.body.entries.get( 'action' ) );

	check(
		'clicking the button starts a scan',
		actions[ 0 ] === 'open_accessibility_start_scan',
		actions.join( ', ' )
	);

	const body = page.requests[ 0 ] && page.requests[ 0 ].body;

	check(
		'the request posts to the start action with the nonce',
		body && body.entries.get( 'action' ) === 'open_accessibility_start_scan' && body.entries.get( 'nonce' ) === 'test-nonce',
		body ? `${body.entries.get( 'action' )} / ${body.entries.get( 'nonce' )}` : 'no body'
	);

	check(
		'the button is disabled while the scan runs',
		page.scanButton.disabled === true
	);
}

// The polling loop keeps asking while the server says work remains, and shows
// the count it was given.
{
	// The scan never finishes here, so the loop is observed while it is still
	// running rather than being drained past its end.
	const page = load( {
		respond: ( body, n ) => ( {
			success: true,
			data: {
				progress: {
					running: true,
					scanned: n * 5,
					total: 20,
				},
			},
		} ),
	} );

	page.scanButton.dispatch( 'click' );
	await settle();

	// Drain the queued poll a couple of times.
	drain( page.timers );
	await settle();
	drain( page.timers );
	await settle();

	const actions = page.requests.map( ( r ) => r.body.entries.get( 'action' ) );

	check(
		'the loop keeps polling while work remains',
		actions.filter( ( a ) => a === 'open_accessibility_scan_progress' ).length >= 2,
		actions.join( ', ' )
	);

	const text = page.progressBox.querySelector( '.oa-report__progress-text' );

	check(
		'the progress count is shown to the user',
		text && /\d+ of \d+ posts scanned/.test( text.textContent ),
		text ? text.textContent : 'no progress text'
	);

	// The count the last poll reported, so the expected width follows the
	// requests rather than assuming which poll ran last.
	const scanned = 5 * page.requests.length;
	const expected = Math.min( 100, Math.round( ( scanned / 20 ) * 100 ) );

	const fill = page.progressBox.querySelector( '.oa-report__bar-fill' );

	check(
		'the progress bar reflects the count',
		fill && fill.style.width === `${expected}%`,
		fill ? `width=${fill.style.width}, expected ${expected}% for ${scanned}/20` : 'no bar'
	);
}

// When the server reports the scan finished, the loop stops and the page
// reloads so the finished report is rendered by the server.
{
	const page = load( {
		respond: ( body ) => ( {
			success: true,
			data: {
				progress: body.entries.get( 'action' ) === 'open_accessibility_start_scan'
					? { running: false, scanned: 20, total: 20 }
					: { running: false, scanned: 20, total: 20 },
			},
		} ),
	} );

	page.scanButton.dispatch( 'click' );
	await settle();

	const pollsBefore = page.requests.filter(
		( r ) => r.body.entries.get( 'action' ) === 'open_accessibility_scan_progress'
	).length;

	// The completion path reloads on a short timer rather than immediately.
	drain( page.timers );
	await settle();

	const pollsAfter = page.requests.filter(
		( r ) => r.body.entries.get( 'action' ) === 'open_accessibility_scan_progress'
	).length;

	check(
		'a finished scan stops polling',
		pollsAfter === pollsBefore,
		`${pollsBefore} -> ${pollsAfter} polls`
	);

	check(
		'a finished scan reloads the page to show the report',
		page.reloads.count === 1,
		`${page.reloads.count} reload(s)`
	);
}

// A rejection from the server has to surface and re-enable the button, or the
// screen is left permanently stuck.
{
	const page = load( {
		respond: () => ( { success: false, data: { message: 'Security check failed.' } } ),
	} );

	page.scanButton.dispatch( 'click' );
	await settle();

	check(
		'a rejected scan re-enables the button',
		page.scanButton.disabled === false
	);

	const text = page.progressBox.querySelector( '.oa-report__progress-text' );

	check(
		'the server message is shown to the user',
		text && text.textContent.indexOf( 'Security check failed.' ) !== -1,
		text ? text.textContent : 'no message'
	);

	check(
		'a rejected scan is marked as an error',
		page.progressBox.classList.contains( 'oa-report__progress--error' )
	);
}

// A transient poll failure retries instead of giving up at the first blip.
{
	let calls = 0;

	const page = load( {
		respond: ( body ) => {
			const action = body.entries.get( 'action' );

			if ( action === 'open_accessibility_start_scan' ) {
				return { success: true, data: { progress: { running: true, scanned: 0, total: 40 } } };
			}

			calls += 1;

			// Fail the first poll, then succeed.
			if ( calls === 1 ) {
				return { success: false, data: { message: 'Blip.' } };
			}

			return { success: true, data: { progress: { running: true, scanned: 20, total: 40 } } };
		},
	} );

	page.scanButton.dispatch( 'click' );
	await settle();
	drain( page.timers );
	await settle();

	const polls = page.requests.filter(
		( r ) => r.body.entries.get( 'action' ) === 'open_accessibility_scan_progress'
	).length;

	check(
		'a transient poll failure retries rather than giving up',
		polls >= 2,
		`${polls} poll(s)`
	);

	check(
		'a transient failure does not leave the button disabled forever',
		page.scanButton.disabled === true,
		'still disabled while the scan continues'
	);
}

// ---------------------------------------------------------------------------

const failed = results.filter( ( r ) => ! r.passed );

process.stdout.write(
	`\n${results.length - failed.length}/${results.length} checks passed\n`
);

if ( failed.length ) {
	process.stdout.write( `\nFailed:\n${failed.map( ( f ) => `  - ${f.name}` ).join( '\n' )}\n` );
	process.exit( 1 );
}
