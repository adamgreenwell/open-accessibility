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
	 * Strip tags from a string without touching the document.
	 *
	 * @param {string} html Markup.
	 * @return {string} Text.
	 */
	function stripTags( html ) {
		if ( ! html ) {
			return '';
		}

		var doc = new DOMParser().parseFromString( '<div>' + html + '</div>', 'text/html' );

		return ( doc.body.textContent || '' ).replace( /\s+/g, ' ' ).trim();
	}

	/**
	 * Parse a fragment of block markup so it can be queried.
	 *
	 * @param {string} html Markup.
	 * @return {Document} Detached document.
	 */
	function parseFragment( html ) {
		return new DOMParser().parseFromString( '<div>' + ( html || '' ) + '</div>', 'text/html' );
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

				var html = block.attributes && typeof block.attributes.content === 'string'
					? block.attributes.content
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
		if ( typeof alt !== 'string' || '' === alt.trim() ) {
			if ( typeof alt !== 'string' ) {
				findings.push( finding( 'image_missing_alt', block.clientId ) );
			}

			return;
		}

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
	 * Container that supplies the current block list.
	 *
	 * Built once at load time rather than decided during render: useSelect is a
	 * hook and may not be called conditionally, and it does not exist before
	 * WordPress 5.3, which this plugin still supports. Choosing the implementation
	 * up front keeps both facts true.
	 */
	var AuditContainer;

	if ( wp.data.useSelect ) {
		AuditContainer = function () {
			// eslint-disable-next-line react-hooks/rules-of-hooks
			var blocks = wp.data.useSelect( function ( select ) {
				return select( 'core/block-editor' ).getBlocks();
			}, [] );

			return el( AuditResults, { blocks: blocks } );
		};
	} else if ( wp.data.withSelect ) {
		AuditContainer = wp.data.withSelect( function ( select ) {
			return { blocks: select( 'core/block-editor' ).getBlocks() };
		} )( function ( props ) {
			return el( AuditResults, { blocks: props.blocks } );
		} );
	} else {
		AuditContainer = function () {
			return el( AuditResults, { blocks: [] } );
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
