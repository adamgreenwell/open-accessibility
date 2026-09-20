/**
 * The site-wide audit report: start a scan, follow its progress, rescan a post.
 *
 * No dependencies. The page reloads its own rows from the server rather than
 * rendering findings in JavaScript, so there is one source of truth for how a
 * finding reads.
 *
 * @since 1.4.2
 */
( function () {
	'use strict';

	var config = window.open_accessibility_report;

	if ( ! config ) {
		return;
	}

	var POLL_INTERVAL = 1500;

	var scanButton = document.getElementById( 'oa-report-scan' );
	var progressBox = document.getElementById( 'oa-report-progress' );
	var rowsBody = document.getElementById( 'oa-report-rows' );

	var pollTimer = null;
	var failures = 0;

	/**
	 * POST to an admin-ajax action.
	 *
	 * @param {string} action Action name, without the wp_ajax_ prefix.
	 * @param {Object} data   Extra fields.
	 * @return {Promise<Object>} Resolves with the response payload.
	 */
	function post( action, data ) {
		var body = new FormData();

		body.append( 'action', action );
		body.append( 'nonce', config.nonce );

		Object.keys( data || {} ).forEach( function ( key ) {
			body.append( key, data[ key ] );
		} );

		return fetch( config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body
		} ).then( function ( response ) {
			return response.json();
		} ).then( function ( payload ) {
			if ( ! payload || ! payload.success ) {
				throw new Error( payload && payload.data && payload.data.message ? payload.data.message : config.i18n.failed );
			}

			return payload.data;
		} );
	}

	/**
	 * Replace the progress line.
	 *
	 * @param {string} text Message to show.
	 */
	function setMessage( text ) {
		if ( ! progressBox ) {
			return;
		}

		var line = progressBox.querySelector( '.oa-report__progress-text' );

		if ( ! line ) {
			line = document.createElement( 'p' );
			line.className = 'oa-report__progress-text';
			progressBox.appendChild( line );
		}

		line.textContent = text;
	}

	/**
	 * Move the progress bar.
	 *
	 * @param {number} percent 0-100.
	 */
	function setPercent( percent ) {
		if ( ! progressBox ) {
			return;
		}

		var bar = progressBox.querySelector( '.oa-report__bar' );

		if ( ! bar ) {
			bar = document.createElement( 'div' );
			bar.className = 'oa-report__bar';

			var fill = document.createElement( 'span' );
			fill.className = 'oa-report__bar-fill';
			bar.appendChild( fill );
			progressBox.appendChild( bar );
		}

		bar.querySelector( '.oa-report__bar-fill' ).style.width = percent + '%';
	}

	/**
	 * Report a failure in the progress region.
	 *
	 * @param {string} message Message to show.
	 */
	function setError( message ) {
		setMessage( message );

		if ( progressBox ) {
			progressBox.classList.add( 'oa-report__progress--error' );
		}

		if ( scanButton ) {
			scanButton.disabled = false;
		}
	}

	/**
	 * Stop polling.
	 */
	function stopPolling() {
		if ( pollTimer ) {
			window.clearTimeout( pollTimer );
			pollTimer = null;
		}
	}

	/**
	 * Ask the server for progress until the scan finishes.
	 */
	function poll() {
		post( 'open_accessibility_scan_progress', {} ).then( function ( data ) {
			failures = 0;

			var progress = data.progress || {};
			var total = parseInt( progress.total, 10 ) || 0;
			var scanned = parseInt( progress.scanned, 10 ) || 0;

			if ( total > 0 ) {
				setPercent( Math.min( 100, Math.round( ( scanned / total ) * 100 ) ) );
			}

			if ( progress.running ) {
				setMessage(
					config.i18n.progress
						.replace( '%1$s', scanned.toLocaleString() )
						.replace( '%2$s', total.toLocaleString() )
				);
				pollTimer = window.setTimeout( poll, POLL_INTERVAL );
				return;
			}

			// Done. A reload is the honest way to show the finished report: the
			// summary figures, pagination and row order all change, and the
			// server already renders them.
			setMessage( config.i18n.complete );
			setPercent( 100 );

			window.setTimeout( function () {
				window.location.reload();
			}, 600 );
		} ).catch( function ( error ) {
			failures = failures + 1;

			// A single failed poll is usually a blip, and the scan continues
			// server side regardless, so retry before giving up on the user's
			// screen.
			if ( failures < 3 ) {
				pollTimer = window.setTimeout( poll, POLL_INTERVAL );
				return;
			}

			setError( error.message );
		} );
	}

	/**
	 * Begin a scan and follow it.
	 *
	 * @param {boolean} force Rescan posts whose content is unchanged.
	 */
	function startScan( force ) {
		if ( scanButton ) {
			scanButton.disabled = true;
		}

		if ( progressBox ) {
			progressBox.classList.remove( 'oa-report__progress--error' );
		}

		setMessage( config.i18n.starting );
		setPercent( 0 );

		post( 'open_accessibility_start_scan', { force: force ? 'true' : 'false' } ).then( function () {
			poll();
		} ).catch( function ( error ) {
			setError( error.message );
		} );
	}

	if ( scanButton ) {
		scanButton.addEventListener( 'click', function () {
			startScan( scanButton.getAttribute( 'data-force' ) === 'true' );
		} );
	}

	if ( rowsBody ) {
		rowsBody.addEventListener( 'click', function ( event ) {
			var button = event.target.closest( '.oa-report__rescan' );

			if ( ! button ) {
				return;
			}

			if ( ! window.confirm( config.i18n.confirmRescan ) ) {
				return;
			}

			var cell = button.closest( 'td' );
			var original = button.textContent;

			button.disabled = true;
			button.textContent = config.i18n.rescanning;

			post( 'open_accessibility_rescan_post', {
				post_id: button.getAttribute( 'data-post-id' )
			} ).then( function () {
				// The row's count, its breakdown and the totals all move
				// together, so reload rather than patch three places.
				window.location.reload();
			} ).catch( function ( error ) {
				button.disabled = false;
				button.textContent = original;

				if ( cell ) {
					var notice = document.createElement( 'span' );
					notice.className = 'oa-report__row-error';
					notice.textContent = ' ' + error.message;
					cell.appendChild( notice );
				}
			} );
		} );
	}

	// A scan left running by a previous visit keeps running after the page
	// closes, so pick it back up on load instead of showing a stale bar.
	var initial = document.getElementById( 'oa-report-progress' );

	if ( initial && initial.getAttribute( 'data-running' ) === 'true' ) {
		poll();
	}
}() );
