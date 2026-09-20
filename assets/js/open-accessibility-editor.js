/**
 * Block editor content audit panel for Open Accessibility.
 *
 * A sidebar listing the accessibility problems in the post being edited, updated
 * as the author types. This is the one thing a presentation overlay cannot do: a
 * widget can restyle a missing alt attribute but it cannot write one.
 *
 * No build step. Everything is `wp.element.createElement` against the globals
 * core already loads, because this plugin has no package.json and adding one
 * would change how releases work.
 *
 * The rule *messages*, criteria and severities arrive from PHP so there is one
 * definition of what a rule is. The detection over editor blocks is implemented
 * here, because the block tree in the editor is not a string and cannot be parsed
 * by the PHP side.
 *
 * @package Open_Accessibility
 */

( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.plugins || ! wp.element || ! wp.data ) {
		return;
	}

	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var _n = wp.i18n._n;
	var sprintf = wp.i18n.sprintf;

	/**
	 * Rule metadata delivered from PHP, with a safe fallback.
	 *
	 * @type {Object}
	 */
	var config = ( window.open_accessibility_audit && typeof window.open_accessibility_audit === 'object' )
		? window.open_accessibility_audit
		: { rules: {}, unchecked: [] };

	/**
	 * Link text that says nothing about where the link goes.
	 *
	 * Matched against the whole string, so "read more about accessibility" is
	 * fine while a bare "read more" is not.
	 *
	 * @type {string[]}
	 */
	var VAGUE_LINK_TEXT = [
		'click here', 'here', 'read more', 'more', 'learn more', 'more info',
		'more information', 'link', 'this link', 'details', 'continue',
		'download', 'view', 'see more', 'website', 'this'
	];

	/**
	 * Alt text that describes the image as an image.
	 *
	 * @type {string[]}
	 */
	var REDUNDANT_ALT_PREFIXES = [
		'image of ', 'picture of ', 'photo of ', 'photograph of ', 'graphic of ',
		'icon of ', 'a picture of ', 'a photo of ', 'an image of '
	];

	/**
	 * Build a finding record using the metadata from PHP.
	 *
	 * @param {string} rule  Rule id.
	 * @param {string} block Client id of the offending block.
	 * @return {Object} Finding.
	 */
	function finding( rule, block ) {
		var meta = ( config.rules && config.rules[ rule ] ) || {};

		return {
			rule: rule,
			block: block,
			message: meta.message || rule,
			wcag: meta.wcag || '',
			severity: meta.severity || 'warning'
		};
	}

	/**
	 * Coerce a block attribute to a string.
	 *
	 * From WordPress 7.1 a rich-text attribute such as core/paragraph's content
	 * is a RichTextData instance rather than a plain string. It stringifies
	 * correctly and has no enumerable value property, so a `typeof === 'string'`
	 * check silently failed and every rule that reads markup — links, and the
	 * image alt fallback — never ran. Accepting anything stringifiable is what
	 * keeps this working across both shapes.
	 *
	 * @param {*} value Attribute value.
	 * @return {string} String form, or '' when there is nothing usable.
	 */
	function attributeToString( value ) {
		if ( typeof value === 'string' ) {
			return value;
		}

		if ( value === null || typeof value === 'undefined' ) {
			return '';
		}

		if ( typeof value === 'object' && typeof value.toString === 'function' ) {
			var text = value.toString();

			return typeof text === 'string' ? text : '';
		}

		return '';
	}

	/**
	 * Strip tags from a string without touching the document.
	 *
	 * @param {string} html Markup.
	 * @return {string} Text.
	 */
	function stripTags( html ) {
		var source = attributeToString( html );

		if ( ! source ) {
			return '';
		}

		var doc = new DOMParser().parseFromString( '<div>' + source + '</div>', 'text/html' );

		return ( doc.body.textContent || '' ).replace( /\s+/g, ' ' ).trim();
	}

	/**
	 * Parse a fragment of block markup so it can be queried.
	 *
	 * @param {string} html Markup.
	 * @return {Document} Detached document.
	 */
	function parseFragment( html ) {
		return new DOMParser().parseFromString( '<div>' + attributeToString( html ) + '</div>', 'text/html' );
	}

	/**
	 * Whether alt text fails to describe anything.
	 *
	 * @param {string} alt Alt text.
	 * @return {boolean} True when unhelpful.
	 */
	function isUnhelpfulAlt( alt ) {
		var value = ( alt || '' ).trim();

		if ( /\.(jpe?g|png|gif|webp|avif|svg|bmp|tiff?)$/i.test( value ) ) {
			return true;
		}

		if ( /^(img|image|dsc|dscn|photo|pic|picture|screenshot|untitled|untitled[-_ ]?\d*)[-_ ]?\d*$/i.test( value ) ) {
			return true;
		}

		var lower = value.toLowerCase();

		return REDUNDANT_ALT_PREFIXES.some( function ( prefix ) {
			return lower.indexOf( prefix ) === 0;
		} );
	}

	/**
	 * Whether link text says nothing about the destination.
	 *
	 * @param {string} text Link text.
	 * @return {boolean} True when vague.
	 */
	function isVagueLinkText( text ) {
		var normalised = ( text || '' ).toLowerCase().replace( /\s+/g, ' ' ).trim();

		if ( VAGUE_LINK_TEXT.indexOf( normalised ) !== -1 ) {
			return true;
		}

		return /^https?:\/\/\S+$/i.test( normalised );
	}

	/**
	 * Audit a list of blocks and return the findings.
	 *
	 * Recursive deliberately: an image inside columns inside a group is ordinary
	 * content, and a single-level loop would miss most of a real post.
	 *
	 * @param {Array} blocks Editor blocks.
	 * @return {Object} Findings and counts.
	 */
	function auditBlocks( blocks ) {
		var findings = [];
		var state = { lastHeadingLevel: 0 };

		/**
		 * Visit one block and its children.
		 *
		 * @param {Array} list Blocks to visit.
		 */
		function visit( list ) {
			( list || [] ).forEach( function ( block ) {
				if ( ! block || ! block.name ) {
					return;
				}

				var html = block.attributes
					? attributeToString( block.attributes.content )
					: '';

				if ( block.name === 'core/image' ) {
					checkImage( block, findings );
				} else if ( block.name === 'core/heading' ) {
					checkHeading( block, state, findings );
				} else if ( block.name === 'core/button' ) {
					checkButton( block, findings );
				} else if ( block.name === 'core/table' ) {
					checkTable( block, findings );
				}

				if ( html ) {
					checkLinks( html, block, findings );
				}

				if ( block.innerBlocks && block.innerBlocks.length ) {
					visit( block.innerBlocks );
				}
			} );
		}

		visit( blocks );

		var summary = { error: 0, warning: 0, review: 0 };

		findings.forEach( function ( item ) {
			if ( Object.prototype.hasOwnProperty.call( summary, item.severity ) ) {
				summary[ item.severity ] += 1;
			}
		} );

		return { findings: findings, summary: summary };
	}

	/**
	 * Image alt text.
	 *
	 * Read from the block's saved markup because core/image stores alt on the
	 * rendered <img>, not in its attributes. An empty alt is the correct markup
	 * for a decorative image and is deliberately not reported.
	 *
	 * @param {Object} block    Block.
	 * @param {Array}  findings Findings, appended to.
	 */
	function checkImage( block, findings ) {
		var attrs = block.attributes || {};
		var alt = attrs.alt;

		// core/image keeps alt in the block attributes while editing, even though
		// it saves to the rendered <img>. An empty value is the correct markup for
		// a decorative image and is deliberately not reported; only an absent one
		// is.
		if ( 'undefined' === typeof alt || null === alt ) {
			findings.push( finding( 'image_missing_alt', block.clientId ) );
			return;
		}

		if ( '' === attributeToString( alt ).trim() ) {
			// Deliberately empty: correct markup for a decorative image.
			return;
		}

		alt = attributeToString( alt );

		if ( isUnhelpfulAlt( alt ) ) {
			findings.push( finding( 'image_alt_unhelpful', block.clientId ) );
		}
	}

	/**
	 * Heading emptiness and level order.
	 *
	 * @param {Object} block    Block.
	 * @param {Object} state    Scan state.
	 * @param {Array}  findings Findings, appended to.
	 */
	function checkHeading( block, state, findings ) {
		var attrs = block.attributes || {};
		var level = parseInt( attrs.level, 10 ) || 2;
		var text = stripTags( attrs.content || '' );

		if ( '' === text ) {
			findings.push( finding( 'heading_empty', block.clientId ) );
		}

		// Only a downward jump of more than one level is a problem, and only once
		// a heading has been seen. A leading h2 is correct: the page title is
		// normally the h1 and comes from the theme.
		if ( state.lastHeadingLevel > 0 && level > state.lastHeadingLevel + 1 ) {
			findings.push( finding( 'heading_skipped_level', block.clientId ) );
		}

		state.lastHeadingLevel = level;
	}

	/**
	 * Buttons with no accessible name.
	 *
	 * @param {Object} block    Block.
	 * @param {Array}  findings Findings, appended to.
	 */
	function checkButton( block, findings ) {
		var attrs = block.attributes || {};
		var text = stripTags( attrs.text || '' );

		if ( '' === text ) {
			findings.push( finding( 'button_missing_label', block.clientId ) );
		}
	}

	/**
	 * Tables that look like data but have no header cells.
	 *
	 * @param {Object} block    Block.
	 * @param {Array}  findings Findings, appended to.
	 */
	function checkTable( block, findings ) {
		var attrs = block.attributes || {};
		var body = attrs.body || [];
		var head = attrs.head || [];

		if ( head.length ) {
			return;
		}

		var rows = body.length;
		var cols = rows ? ( body[ 0 ].cells || [] ).length : 0;

		if ( rows >= 2 && cols >= 2 ) {
			findings.push( finding( 'table_missing_headers', block.clientId ) );
		}
	}

	/**
	 * Link text inside a block's markup.
	 *
	 * @param {string} html     Block content.
	 * @param {Object} block    Block.
	 * @param {Array}  findings Findings, appended to.
	 */
	function checkLinks( html, block, findings ) {
		var doc = parseFragment( html );
		var links = doc.querySelectorAll( 'a[href]' );

		Array.prototype.forEach.call( links, function ( link ) {
			// A link wrapping an image is described by that image's alt text.
			if ( link.querySelector( 'img' ) ) {
				return;
			}

			var text = ( link.textContent || '' ).replace( /\s+/g, ' ' ).trim();

			// Nothing to judge: an icon link with an aria-label is fine.
			if ( '' === text ) {
				return;
			}

			if ( isVagueLinkText( text ) ) {
				findings.push( finding( 'link_text_unhelpful', block.clientId ) );
			}
		} );
	}

	/**
	 * The results view: a summary, the findings, and what was not checked.
	 *
	 * Kept separate from the container that supplies blocks so the rendering can
	 * be read on its own, and so the unchecked list sits beside the findings
	 * rather than somewhere else in the panel.
	 */
	var AuditResults = function ( props ) {
		var result = auditBlocks( props.blocks || [] );
		var findings = result.findings;
		var summary = result.summary;
		var children = [];

		children.push(
			el(
				'p',
				{ key: 'summary', className: 'open-accessibility-audit-summary' },
				findings.length
					? sprintf(
							/* translators: 1: total issues, 2: number of errors */
							_n( '%1$d issue found (%2$d of them errors).', '%1$d issues found (%2$d of them errors).', findings.length, 'open-accessibility' ),
							findings.length,
							summary.error
					  )
					: __( 'No issues found in the checks this audit can run.', 'open-accessibility' )
			)
		);

		if ( findings.length ) {
			children.push(
				el(
					'ul',
					{ key: 'findings', className: 'open-accessibility-audit-list' },
					findings.map( function ( item, index ) {
						return el(
							'li',
							{
								key: item.rule + '-' + index,
								className: 'open-accessibility-audit-item is-' + item.severity
							},
							el( 'span', { className: 'open-accessibility-audit-message' }, item.message ),
							item.wcag
								? el(
										'span',
										{ className: 'open-accessibility-audit-wcag' },
										sprintf(
											/* translators: %s: WCAG success criterion number */
											__( 'WCAG %s', 'open-accessibility' ),
											item.wcag
										)
								  )
								: null
						);
					} )
				)
			);
		}

		// Say what was not checked. An audit that lists problems without naming
		// its blind spots overstates itself, which is the failure this feature is
		// positioned against.
		if ( config.unchecked && config.unchecked.length ) {
			children.push(
				el(
					'details',
					{ key: 'unchecked', className: 'open-accessibility-audit-unchecked' },
					el(
						'summary',
						null,
						sprintf(
							/* translators: %d: number of categories not checked */
							_n( '%d category is not checked', '%d categories are not checked', config.unchecked.length, 'open-accessibility' ),
							config.unchecked.length
						)
					),
					el(
						'ul',
						null,
						config.unchecked.map( function ( category, index ) {
							return el( 'li', { key: index }, category );
						} )
					)
				)
			);
		}

		return el( 'div', { className: 'open-accessibility-audit' }, children );
	};

	/**
	 * Container that supplies the current block list.
	 *
	 * Subscribes to the store rather than reading blocks during render. A
	 * reactive read looked correct and was not: once the editor finished loading
	 * the post, the panel kept showing the block list captured before the content
	 * existed, so it reported a single heading issue and silently missed the rest.
	 * Holding the blocks in component state and refreshing them on change is what
	 * the store subscription is actually for, and it does not depend on a hook
	 * that only exists from WordPress 5.3.
	 */
	function useBlocks() {
		var useState = wp.element.useState;
		var useEffect = wp.element.useEffect;

		var pair = useState( function () {
			return wp.data.select( 'core/block-editor' ).getBlocks();
		} );

		var blocks = pair[0];
		var setBlocks = pair[1];

		useEffect( function () {
			var last = null;
			var timer = null;

			function refresh() {
				var next = wp.data.select( 'core/block-editor' ).getBlocks();

				// Compare each block's name and attributes, not just its id. The
				// ids exist as soon as the editor has a block list, while the
				// attributes arrive when the post finishes loading — so a
				// client-id-only signature treated the empty first snapshot as
				// final and every content-based rule silently missed everything.
				//
				// JSON.stringify over attributes rather than a deep compare: the
				// values are plain scalars and strings here, and it keeps this to
				// one pass.
				var signature = next.map( function ( block ) {
					return block.clientId + ':' + block.name + ':' + JSON.stringify( block.attributes || {} );
				} ).join( '|' );

				if ( signature === last ) {
					return;
				}

				last = signature;
				setBlocks( next );
			}

			// Debounced: findings should follow typing without running on every
			// keystroke.
			var unsubscribe = wp.data.subscribe( function () {
				if ( timer ) {
					clearTimeout( timer );
				}

				timer = setTimeout( refresh, 600 );
			} );

			refresh();

			return function () {
				if ( timer ) {
					clearTimeout( timer );
				}

				unsubscribe();
			};
		}, [] );

		return blocks;
	}

	var canUseHooks = typeof wp.element.useState === 'function'
		&& typeof wp.element.useEffect === 'function'
		&& typeof wp.data.subscribe === 'function';

	var AuditContainer;

	if ( canUseHooks ) {
		AuditContainer = function () {
			return el( AuditResults, { blocks: useBlocks() } );
		};
	} else {
		// No hooks, so no subscription: render whatever is there and refresh on
		// an interval. WordPress 5.2 ships hooks, so this is a floor for a
		// back-ported or filtered build rather than the normal path — but it is
		// still better than a panel that never updates or throws on render.
		AuditContainer = function () {
			var pair = wp.element.useState
				? wp.element.useState( function () { return wp.data.select( 'core/block-editor' ).getBlocks(); } )
				: [ wp.data.select( 'core/block-editor' ).getBlocks() ];

			return el( AuditResults, { blocks: pair[0] } );
		};
	}

	/**
	 * Sidebar slot.
	 *
	 * WordPress 6.6 unified the editor slots into wp.editor; wp.editPost still
	 * works but is on a deprecation path. The plugin supports 5.2, where only
	 * wp.editPost exists, so the fallback chain is required rather than tidy.
	 */
	var Sidebar = ( wp.editor && wp.editor.PluginSidebar )
		|| ( wp.editPost && wp.editPost.PluginSidebar );

	if ( ! Sidebar ) {
		return;
	}

	wp.plugins.registerPlugin( 'open-accessibility-audit', {
		render: function () {
			return el(
				Sidebar,
				{
					name: 'open-accessibility-audit',
					title: __( 'Accessibility', 'open-accessibility' ),
					icon: 'universal-access',
					className: 'open-accessibility-audit-panel'
				},
				el( AuditContainer )
			);
		}
	} );
} )( window.wp );
