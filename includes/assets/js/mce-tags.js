/**
 * Tag picker for the classic editor and for text inputs.
 *
 * Adds a button that opens a searchable list of the tags configured for that particular field, and
 * inserts the chosen one at the cursor. Several fields can sit on the same page, each offering a
 * different set: an editor receives its list as the wp2fa_tags init setting, an input carries its
 * own on the button, so the subject line and the body of one email offer the same tags.
 *
 * The panel is our own markup rather than a TinyMCE menu. TinyMCE's menus are a fixed row of
 * items with nowhere to put a search field, and their look is dictated by the skin — neither of
 * which suits this control. Only the button is TinyMCE's.
 *
 * Written against no single TinyMCE version: 5 and 6 register controls through
 * editor.ui.registry, 4 through editor.addButton, and whichever is present is used. WordPress has
 * shipped 4.x since 5.5, but nothing here assumes that.
 *
 * @since 4.2.0
 */
( function () {
	'use strict';

	/*
	 * TinyMCE loads this file as an editor plugin, and it is enqueued again for pages carrying a
	 * tag input. Either route may be the only one present, and both may fire, so the input half
	 * is registered once and the editor half only when TinyMCE is actually there.
	 */
	var PLUGIN = 'wp2fa_tags';

	var TAG_ICON = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"'
		+ ' fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
		+ '<path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>'
		+ '<line x1="7" y1="7" x2="7.01" y2="7"></line></svg>';

	var SEARCH_ICON = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"'
		+ ' fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
		+ '<circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>';

	/**
	 * Reads the tag list for one editor.
	 *
	 * WordPress emits a value that opens with "[" as a real array, but older releases quote it, so
	 * both shapes are accepted.
	 *
	 * @param {Object} editor TinyMCE editor.
	 * @return {Array} The tags, or an empty array.
	 */
	function readTags( editor ) {
		var raw = editor.getParam( 'wp2fa_tags' );

		if ( ! raw ) {
			return [];
		}

		if ( typeof raw === 'string' ) {
			try {
				raw = JSON.parse( raw );
			} catch ( e ) {
				return [];
			}
		}

		return Object.prototype.toString.call( raw ) === '[object Array]' ? raw : [];
	}

	/**
	 * Finds the toolbar button so the panel can be hung underneath it.
	 *
	 * TinyMCE 4 hands the control to the click handler and it can report its own element. 5 and 6
	 * do not, so the button is looked up in the editor's container. If neither works the panel is
	 * anchored to the editor itself, which is approximate but never leaves it stranded.
	 *
	 * @param {Object} editor  TinyMCE editor.
	 * @param {Object} control The control, when the running version provides one.
	 * @return {Element|null} The element to anchor to.
	 */
	function findAnchor( editor, control ) {
		if ( control && typeof control.getEl === 'function' ) {
			var own = control.getEl();

			if ( own ) {
				return own;
			}
		}

		var container = editor.getContainer ? editor.getContainer() : null;

		if ( ! container ) {
			return null;
		}

		return container.querySelector( '[data-mce-name="' + PLUGIN + '"]' )
			|| container.querySelector( '.mce-i-' + PLUGIN )
			|| container.querySelector( '.mce-toolbar, .tox-toolbar, .tox-toolbar__primary' )
			|| container;
	}

	/**
	 * Builds a panel. One per field, created on first use.
	 *
	 * Takes plain callbacks rather than an editor so the same panel serves a TinyMCE instance and
	 * a text input: what differs between them is only where the tag is inserted, where focus
	 * returns to, and whether there is an iframe whose clicks also have to close the panel.
	 *
	 * @param {Object}   config              Panel configuration.
	 * @param {Array}    config.tags         Tags to offer.
	 * @param {string}   config.searchLabel  Placeholder for the search field.
	 * @param {string}   config.emptyLabel   Shown when nothing matches.
	 * @param {Function} config.onInsert     Receives the chosen tag.
	 * @param {Function} config.onFocus      Returns focus to the field.
	 * @param {Function} [config.innerDoc]   Returns a same-origin iframe document, when there is one.
	 * @return {Object} Controller with open(), close() and isOpen().
	 */
	function createPanel( config ) {
		var tags = config.tags;
		var panel = document.createElement( 'div' );
		panel.className = 'wp2fa-tagpanel';
		panel.setAttribute( 'role', 'dialog' );
		panel.hidden = true;

		var searchRow = document.createElement( 'div' );
		searchRow.className = 'wp2fa-tagpanel__search';

		var icon = document.createElement( 'span' );
		icon.className = 'wp2fa-tagpanel__search-icon';
		icon.setAttribute( 'aria-hidden', 'true' );
		icon.innerHTML = SEARCH_ICON;

		var input = document.createElement( 'input' );
		input.type = 'text';
		input.className = 'wp2fa-tagpanel__input';
		input.placeholder = config.searchLabel || 'Search for a tag';
		input.setAttribute( 'aria-label', input.placeholder );
		input.autocomplete = 'off';

		searchRow.appendChild( icon );
		searchRow.appendChild( input );

		var list = document.createElement( 'div' );
		list.className = 'wp2fa-tagpanel__list';
		list.setAttribute( 'role', 'listbox' );

		var empty = document.createElement( 'p' );
		empty.className = 'wp2fa-tagpanel__empty';
		empty.textContent = config.emptyLabel || 'No tags found';
		empty.hidden = true;

		panel.appendChild( searchRow );
		panel.appendChild( list );
		panel.appendChild( empty );

		var rows = [];

		tags.forEach( function ( tag ) {
			var row = document.createElement( 'button' );
			row.type = 'button';
			row.className = 'wp2fa-tagpanel__tag';
			row.setAttribute( 'role', 'option' );
			row.textContent = tag;

			row.addEventListener( 'click', function () {
				config.onInsert( tag );
				close();
				config.onFocus();
			} );

			list.appendChild( row );
			rows.push( row );
		} );

		document.body.appendChild( panel );

		var isOpen = false;
		var anchorEl = null;
		var setActive = null;

		function visibleRows() {
			return rows.filter( function ( row ) {
				return ! row.hidden;
			} );
		}

		function filter() {
			var needle = input.value.trim().toLowerCase();
			var shown = 0;

			rows.forEach( function ( row ) {
				var match = '' === needle || -1 !== row.textContent.toLowerCase().indexOf( needle );
				row.hidden = ! match;

				if ( match ) {
					shown++;
				}
			} );

			empty.hidden = shown > 0;
		}

		function position() {
			if ( ! anchorEl ) {
				return;
			}

			var box = anchorEl.getBoundingClientRect();
			var width = panel.offsetWidth;

			// Hang under the button, pulled left so a button near the right edge stays on screen.
			var left = box.left + window.pageXOffset - width + box.width;
			var minLeft = window.pageXOffset + 8;
			var maxLeft = window.pageXOffset + document.documentElement.clientWidth - width - 8;

			panel.style.top = ( box.bottom + window.pageYOffset + 6 ) + 'px';
			panel.style.left = Math.round( Math.max( minLeft, Math.min( left, maxLeft ) ) ) + 'px';
		}

		function onDocumentDown( event ) {
			if ( panel.contains( event.target ) || ( anchorEl && anchorEl.contains( event.target ) ) ) {
				return;
			}

			close();
		}

		function onKeyDown( event ) {
			if ( 'Escape' === event.key || 'Esc' === event.key ) {
				event.preventDefault();
				close();
				config.onFocus();

				return;
			}

			if ( 'ArrowDown' !== event.key && 'ArrowUp' !== event.key && 'Enter' !== event.key ) {
				return;
			}

			var options = visibleRows();

			if ( ! options.length ) {
				return;
			}

			var current = options.indexOf( document.activeElement );

			if ( 'Enter' === event.key ) {
				if ( current === -1 ) {
					event.preventDefault();
					options[0].click();
				}

				return;
			}

			event.preventDefault();

			var next = 'ArrowDown' === event.key ? current + 1 : current - 1;

			if ( next < 0 ) {
				next = options.length - 1;
			}

			if ( next >= options.length ) {
				next = 0;
			}

			options[ next ].focus();
		}

		function open( anchor, onActive ) {
			anchorEl = anchor;
			setActive = onActive || null;
			panel.hidden = false;
			isOpen = true;

			input.value = '';
			filter();
			position();

			input.focus();

			document.addEventListener( 'mousedown', onDocumentDown, true );
			panel.addEventListener( 'keydown', onKeyDown );
			window.addEventListener( 'resize', position );
			window.addEventListener( 'scroll', position, true );

			/*
			 * The visual editor is an iframe, and events inside it never reach the document this
			 * panel lives in — so clicking into the text closed nothing and left the panel open
			 * over an editor that had already taken focus. Listen inside the iframe too.
			 */
			var innerDoc = config.innerDoc ? config.innerDoc() : null;

			if ( innerDoc ) {
				innerDoc.addEventListener( 'mousedown', close, true );
			}
		}

		function close() {
			if ( ! isOpen ) {
				return;
			}

			panel.hidden = true;
			isOpen = false;

			/*
			 * Reset the button here rather than in the caller. The panel can be dismissed by
			 * clicking away or pressing Escape as well as by pressing the button again, and all
			 * of those have to leave the button looking closed.
			 */
			if ( setActive ) {
				setActive( false );
			}

			if ( anchorEl ) {
				var openButton = anchorEl.closest
					? anchorEl.closest( '.mce-btn, .tox-tbtn, .wp2fa-taginput__button' )
					: null;

				if ( openButton ) {
					openButton.classList.remove( 'wp2fa-tagpanel-open' );
					openButton.setAttribute( 'aria-expanded', 'false' );
				}
			}

			document.removeEventListener( 'mousedown', onDocumentDown, true );
			panel.removeEventListener( 'keydown', onKeyDown );
			window.removeEventListener( 'resize', position );
			window.removeEventListener( 'scroll', position, true );

			var innerDoc = config.innerDoc ? config.innerDoc() : null;

			if ( innerDoc ) {
				innerDoc.removeEventListener( 'mousedown', close, true );
			}
		}

		input.addEventListener( 'input', filter );

		if ( config.onDestroy ) {
			config.onDestroy( function () {
				close();

				if ( panel.parentNode ) {
					panel.parentNode.removeChild( panel );
				}
			} );
		}

		return {
			open: open,
			close: close,
			isOpen: function () {
				return isOpen;
			}
		};
	}

	if ( typeof window.tinymce !== 'undefined' ) {
	window.tinymce.PluginManager.add( PLUGIN, function ( editor ) {
		var tags = readTags( editor );

		if ( ! tags.length ) {
			return;
		}

		var panel = null;
		var tooltip = editor.getParam( 'wp2fa_tags_i18n_button' ) || 'Insert a tag';

		function toggle( anchor, setActive ) {
			if ( ! panel ) {
				panel = createPanel( {
					tags: tags,
					searchLabel: editor.getParam( 'wp2fa_tags_i18n_search' ),
					emptyLabel: editor.getParam( 'wp2fa_tags_i18n_empty' ),
					onInsert: function ( tag ) {
						editor.insertContent( tag );
					},
					onFocus: function () {
						editor.focus();
					},
					innerDoc: function () {
						return editor.getDoc ? editor.getDoc() : null;
					},
					onDestroy: function ( teardown ) {
						editor.on( 'remove', teardown );
					}
				} );
			}

			if ( panel.isOpen() ) {
				panel.close();

				return;
			}

			panel.open( anchor, setActive );
			setActive( true );

			/*
			 * Marks the button as pressed for assistive technology. Nothing is styled from this:
			 * TinyMCE rewrites className from its own class list whenever a control's state
			 * changes, so a class put here does not survive the first press.
			 */
			var button = anchor && anchor.closest ? anchor.closest( '.mce-btn, .tox-tbtn' ) : null;

			if ( button ) {
				button.classList.add( 'wp2fa-tagpanel-open' );
			}
		}

		// TinyMCE 5 and 6.
		if ( editor.ui && editor.ui.registry && 'function' === typeof editor.ui.registry.addButton ) {
			editor.ui.registry.addIcon( PLUGIN, TAG_ICON );

			editor.ui.registry.addButton( PLUGIN, {
				icon: PLUGIN,
				tooltip: tooltip,
				onSetup: function ( api ) {
					api.setActive( false );

					return function () {};
				},
				onAction: function ( api ) {
					toggle( findAnchor( editor, null ), function ( state ) {
						api.setActive( state );
					} );
				}
			} );

			return;
		}

		// TinyMCE 4.
		if ( 'function' === typeof editor.addButton ) {
			editor.addButton( PLUGIN, {
				icon: PLUGIN,
				tooltip: tooltip,
				onclick: function () {
					var control = this;

					toggle( findAnchor( editor, control ), function ( state ) {
						if ( 'function' === typeof control.active ) {
							control.active( state );
						}
					} );
				}
			} );
		}

	} );
	}

	/**
	 * Inserts a tag at the caret of a plain input, leaving the caret after it.
	 *
	 * @param {HTMLInputElement} field The input to write into.
	 * @param {string}           tag   The tag to insert.
	 * @return {void}
	 */
	function insertIntoInput( field, tag ) {
		var value = field.value;
		var start = null === field.selectionStart ? value.length : field.selectionStart;
		var end = null === field.selectionEnd ? value.length : field.selectionEnd;

		field.value = value.slice( 0, start ) + tag + value.slice( end );

		var caret = start + tag.length;
		field.setSelectionRange( caret, caret );

		/*
		 * Announce the change. The value was set in script, which fires no event of its own, so
		 * anything watching the form for unsaved changes would never see it.
		 */
		field.dispatchEvent( new Event( 'input', { bubbles: true } ) );
		field.dispatchEvent( new Event( 'change', { bubbles: true } ) );
	}

	/**
	 * Wires up the tag buttons that sit inside text inputs.
	 *
	 * Delegated from the document, so a field revealed later — inside an accordion that was closed
	 * when the page loaded — works without rescanning. Each button builds its panel on first use.
	 *
	 * @return {void}
	 */
	function initInputPickers() {
		document.addEventListener( 'click', function ( event ) {
			var button = event.target.closest ? event.target.closest( '.wp2fa-taginput__button' ) : null;

			if ( ! button ) {
				return;
			}

			event.preventDefault();

			var panel = button.wp2faPanel;

			if ( ! panel ) {
				var tags;

				try {
					tags = JSON.parse( button.getAttribute( 'data-wp2fa-tags' ) || '[]' );
				} catch ( e ) {
					return;
				}

				if ( ! tags.length ) {
					return;
				}

				/*
				 * A textarea carries the same value/selection API as a text input, so the
				 * insertion below needs no special case — only the lookup has to know to
				 * find one.
				 */
				var field = button.parentNode
					? button.parentNode.querySelector( 'input[type="text"], input:not([type]), textarea' )
					: null;

				if ( ! field ) {
					return;
				}

				panel = createPanel( {
					tags: tags,
					searchLabel: button.getAttribute( 'data-wp2fa-i18n-search' ),
					emptyLabel: button.getAttribute( 'data-wp2fa-i18n-empty' ),
					onInsert: function ( tag ) {
						insertIntoInput( field, tag );
					},
					onFocus: function () {
						field.focus();
					}
				} );

				button.wp2faPanel = panel;
			}

			if ( panel.isOpen() ) {
				panel.close();

				return;
			}

			panel.open( button, null );
			button.classList.add( 'wp2fa-tagpanel-open' );
			button.setAttribute( 'aria-expanded', 'true' );
		} );
	}

	// Both load routes can fire on the same page; only the first one wires the inputs.
	if ( ! window.wp2faTagInputsReady ) {
		window.wp2faTagInputsReady = true;
		initInputPickers();
	}
}() );
