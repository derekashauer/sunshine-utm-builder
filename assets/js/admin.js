/**
 * Sunshine UTM Builder admin: the Bulk Builder, saved batches, presets and settings.
 * The single link builder is assets/js/builder.js.
 */
( function () {
	'use strict';

	var config = window.SunshineUTMAdmin || {};
	var texts = config.texts || {};

	function post( action, body ) {
		body = body || new FormData();
		body.append( 'action', action );
		body.append( 'nonce', config.nonce );
		return fetch( config.ajax_url, { method: 'POST', body: body, credentials: 'same-origin' } ).then( function ( response ) {
			return response.json();
		} );
	}

	function copyText( text, field ) {
		if ( navigator.clipboard && window.isSecureContext ) {
			return navigator.clipboard.writeText( text );
		}
		return new Promise( function ( resolve, reject ) {
			var temp = field;
			if ( ! temp ) {
				temp = document.createElement( 'textarea' );
				temp.value = text;
				temp.setAttribute( 'readonly', '' );
				temp.style.position = 'fixed';
				temp.style.opacity = '0';
				document.body.appendChild( temp );
			}
			temp.select();
			try {
				if ( document.execCommand( 'copy' ) ) {
					resolve();
				} else {
					reject();
				}
			} catch ( e ) {
				reject( e );
			}
			if ( ! field ) {
				temp.remove();
			}
		} );
	}

	function announce( message ) {
		var region = document.querySelector( '[data-sunshine-utm-announce]' );
		if ( region ) {
			region.textContent = message;
		}
	}

	function flash( button, label ) {
		var original = button.getAttribute( 'data-label' ) || button.innerHTML;
		button.setAttribute( 'data-label', original );
		button.textContent = label;
		button.classList.add( 'is-copied' );
		window.setTimeout( function () {
			button.innerHTML = original;
			button.classList.remove( 'is-copied' );
		}, 2000 );
	}

	function debounce( fn, wait ) {
		var timer;
		return function () {
			window.clearTimeout( timer );
			timer = window.setTimeout( fn, wait );
		};
	}

	function el( tag, className, text ) {
		var node = document.createElement( tag );
		if ( className ) {
			node.className = className;
		}
		if ( text !== undefined ) {
			node.textContent = text;
		}
		return node;
	}

	/* Copy buttons, copy all, delete confirmations */

	document.addEventListener( 'click', function ( e ) {
		var button = e.target.closest( '[data-sunshine-utm-copy]' );
		if ( button && ! button.closest( '[data-sunshine-utm-builder]' ) ) {
			var field = button.parentNode.querySelector( 'input, textarea' );
			copyText( field.value, field ).then(
				function () {
					flash( button, texts.copied );
					announce( texts.copied );
				},
				function () {
					field.select();
					announce( texts.copy_failed );
				}
			);
			return;
		}

		button = e.target.closest( '[data-sunshine-utm-copy-all]' );
		if ( button ) {
			var links = [];
			document.querySelectorAll( '[data-sunshine-utm-row]' ).forEach( function ( row ) {
				if ( ! row.hidden ) {
					links.push( row.getAttribute( 'data-share' ) );
				}
			} );
			copyText( links.join( '\n' ) ).then(
				function () {
					var message = texts.copied_all.replace( '%d', links.length );
					flash( button, texts.copied );
					announce( message );
				},
				function () {
					announce( texts.copy_failed );
				}
			);
			return;
		}

		button = e.target.closest( '[data-sunshine-utm-confirm]' );
		if ( button && ! window.confirm( button.getAttribute( 'data-sunshine-utm-confirm' ) ) ) {
			e.preventDefault();
		}
	} );

	/* Batch: filter and link notes */

	var filter = document.querySelector( '[data-sunshine-utm-filter]' );
	if ( filter ) {
		filter.addEventListener( 'input', function () {
			var term = filter.value.trim().toLowerCase();
			var shown = 0;
			document.querySelectorAll( '[data-sunshine-utm-row]' ).forEach( function ( row ) {
				var text = row.textContent;
				row.querySelectorAll( 'input, textarea' ).forEach( function ( field ) {
					text += ' ' + field.value;
				} );
				row.hidden = term !== '' && text.toLowerCase().indexOf( term ) === -1;
				if ( ! row.hidden ) {
					shown++;
				}
			} );
			document.querySelector( '[data-sunshine-utm-no-match]' ).hidden = shown > 0;
		} );
	}

	document.querySelectorAll( '[data-sunshine-utm-note]' ).forEach( function ( note ) {
		var saved = note.value;
		note.addEventListener( 'change', function () {
			var status = note.parentNode.querySelector( '[data-sunshine-utm-note-status]' );
			var body = new FormData();
			if ( note.value === saved ) {
				return;
			}
			body.append( 'link_id', note.getAttribute( 'data-sunshine-utm-note' ) );
			body.append( 'note', note.value );
			post( 'sunshine_utm_link_note', body )
				.then( function ( result ) {
					if ( result && result.success ) {
						saved = note.value;
						status.textContent = texts.saved;
						window.setTimeout( function () {
							status.textContent = '';
						}, 2000 );
					} else {
						status.textContent = ( result && result.data && result.data.message ) || texts.failed;
					}
				} )
				.catch( function () {
					status.textContent = texts.failed;
				} );
		} );
	} );

	/* Settings: the short link address and the formatting example */

	var prefixField = document.querySelector( '[data-sunshine-utm-prefix]' );
	if ( prefixField ) {
		prefixField.addEventListener( 'input', function () {
			var example = document.querySelector( '[data-sunshine-utm-prefix-example]' );
			// Same cleanup as Sunshine_UTM_Short_Links::clean_prefix(), minus the reserved words.
			var prefix = prefixField.value.replace( /[^A-Za-z0-9-]/g, '' ).toLowerCase().replace( /^-+|-+$/g, '' ).slice( 0, 20 );
			example.textContent = example.getAttribute( 'data-home' ) + '/' + ( prefix ? prefix + '/' : '' ) + 'x8d89c';
		} );
	}

	var tryField = document.querySelector( '[data-sunshine-utm-format-try]' );
	if ( tryField && window.SunshineUTM ) {
		var settingsForm = tryField.form;
		var showFormat = function () {
			var format = {
				lowercase: settingsForm.elements.lowercase.checked,
				spaces: settingsForm.elements.spaces.value,
				strip_symbols: settingsForm.elements.strip_symbols.checked,
			};
			document.querySelector( '[data-sunshine-utm-format-result]' ).textContent = window.SunshineUTM.formatValue( tryField.value, format );
		};
		settingsForm.addEventListener( 'input', showFormat );
		settingsForm.addEventListener( 'change', showFormat );
	}

	/* Bulk Builder */

	var bulk = document.querySelector( '[data-sunshine-utm-bulk]' );
	if ( ! bulk ) {
		return;
	}

	var previewBox = bulk.querySelector( '[data-sunshine-utm-preview]' );
	var countBox = bulk.querySelector( '[data-sunshine-utm-count]' );
	var createButton = bulk.querySelector( '[data-sunshine-utm-create]' );
	var createStatus = bulk.querySelector( '[data-sunshine-utm-create-status]' );
	var presets = JSON.parse( bulk.getAttribute( 'data-presets' ) || '{}' );
	var request = 0;
	var busy = false;

	function method() {
		var checked = bulk.querySelector( 'input[name="method"]:checked' );
		return checked ? checked.value : 'urls';
	}

	function showMethod() {
		var current = method();
		bulk.querySelectorAll( '[data-sunshine-utm-for]' ).forEach( function ( section ) {
			section.hidden = section.getAttribute( 'data-sunshine-utm-for' ).split( ' ' ).indexOf( current ) === -1;
		} );
	}

	function hasInput() {
		var current = method();
		var field = bulk.elements[ current === 'csv' ? 'csv' : 'urls' ];
		return field.value.trim() !== '';
	}

	function setCreate( total ) {
		createButton.disabled = busy || ! total;
		if ( ! total ) {
			createButton.textContent = texts.create_none;
		} else {
			createButton.textContent = total === 1 ? texts.create_one : texts.create.replace( '%s', total.toLocaleString() );
		}
	}

	function renderPreview( data ) {
		previewBox.innerHTML = '';
		countBox.textContent = '';

		if ( data.skipped && data.skipped.length ) {
			var alert = el( 'div', 'sunshine-utm-alert' );
			data.skipped.slice( 0, 5 ).forEach( function ( message ) {
				alert.appendChild( el( 'p', '', message ) );
			} );
			previewBox.appendChild( alert );
		}

		countBox.textContent = data.total === 1 ? texts.count_one : texts.count.replace( '%s', data.total.toLocaleString() );
		var table = el( 'table', 'sunshine-utm-table sunshine-utm-preview__table' );
		var head = el( 'tr' );
		[ texts.col_page, texts.col_link ].forEach( function ( title ) {
			var th = el( 'th', '', title );
			th.setAttribute( 'scope', 'col' );
			head.appendChild( th );
		} );
		table.appendChild( el( 'thead' ) ).appendChild( head );
		var tbody = el( 'tbody' );
		data.links.forEach( function ( link ) {
			var row = el( 'tr' );
			var tags = el( 'td' );
			tags.appendChild( el( 'span', 'sunshine-utm-preview__page', link.url ) );
			var list = el( 'dl', 'sunshine-utm-tags' );
			[ 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content' ].forEach( function ( key ) {
				if ( link[ key ] ) {
					var item = el( 'div' );
					item.appendChild( el( 'dt', '', key.replace( 'utm_', '' ) ) );
					item.appendChild( el( 'dd', '', link[ key ] ) );
					list.appendChild( item );
				}
			} );
			Object.keys( link.extra_params || {} ).forEach( function ( name ) {
				var item = el( 'div' );
				item.appendChild( el( 'dt', '', name ) );
				item.appendChild( el( 'dd', '', link.extra_params[ name ] ) );
				list.appendChild( item );
			} );
			tags.appendChild( list );
			// Which Google Analytics 4 channel the link lands in (assets/js/channels.js).
			if ( window.SunshineUTMChannels ) {
				var found = window.SunshineUTMChannels.channel( link.utm_source, link.utm_medium, link.utm_campaign );
				var note = found.unassigned ? ( found.hint ? texts.unassigned_hint.replace( '%s', found.hint ) : texts.unassigned ) : texts.channel.replace( '%s', found.name );
				tags.appendChild( el( 'span', 'sunshine-utm-preview__channel' + ( found.unassigned ? ' is-unassigned' : '' ), note ) );
			}
			row.appendChild( tags );
			row.appendChild( el( 'td', 'sunshine-utm-preview__link', link.tagged_url ) );
			tbody.appendChild( row );
		} );
		table.appendChild( tbody );
		previewBox.appendChild( table );

		if ( data.total > data.links.length ) {
			previewBox.appendChild( el( 'p', 'sunshine-utm-empty', texts.more.replace( '%s', ( data.total - data.links.length ).toLocaleString() ) ) );
		}
	}

	function preview() {
		var id = ++request;
		if ( ! hasInput() ) {
			previewBox.innerHTML = '';
			previewBox.appendChild( el( 'p', 'sunshine-utm-empty', texts.empty ) );
			countBox.textContent = '';
			setCreate( 0 );
			return;
		}
		previewBox.classList.add( 'is-loading' );
		post( 'sunshine_utm_bulk_preview', new FormData( bulk ) )
			.then( function ( result ) {
				if ( id !== request ) {
					return;
				}
				previewBox.classList.remove( 'is-loading' );
				if ( result && result.success ) {
					renderPreview( result.data );
					setCreate( result.data.total );
				} else {
					previewBox.innerHTML = '';
					previewBox.appendChild( el( 'div', 'sunshine-utm-alert sunshine-utm-alert--error', ( result && result.data && result.data.message ) || texts.failed ) );
					countBox.textContent = '';
					setCreate( 0 );
				}
			} )
			.catch( function () {
				if ( id === request ) {
					previewBox.classList.remove( 'is-loading' );
					setCreate( 0 );
				}
			} );
	}

	var queuePreview = debounce( preview, 350 );

	bulk.addEventListener( 'input', queuePreview );
	bulk.addEventListener( 'change', function ( e ) {
		if ( e.target.name === 'method' ) {
			showMethod();
		}
		queuePreview();
	} );

	var presetSelect = bulk.querySelector( '[data-sunshine-utm-preset]' );
	if ( presetSelect ) {
		presetSelect.addEventListener( 'change', function () {
			var preset = presets[ presetSelect.value ];
			if ( ! preset ) {
				return;
			}
			Object.keys( preset ).forEach( function ( key ) {
				var field = bulk.elements[ 'tags[' + key + ']' ];
				if ( field ) {
					field.value = preset[ key ] || '';
				}
			} );
			queuePreview();
		} );
	}

	var fileField = bulk.querySelector( '[data-sunshine-utm-csv-file]' );
	if ( fileField ) {
		fileField.addEventListener( 'change', function () {
			var file = fileField.files[ 0 ];
			if ( ! file ) {
				return;
			}
			// Excel on Windows saves "CSV (Comma delimited)" in Windows-1252, not UTF-8. Try UTF-8
			// first and fall back, so accented letters survive either way.
			var reader = new FileReader();
			reader.onload = function () {
				var text;
				try {
					text = new TextDecoder( 'utf-8', { fatal: true } ).decode( reader.result );
				} catch ( e ) {
					text = new TextDecoder( 'windows-1252' ).decode( reader.result );
				}
				bulk.elements.csv.value = text.replace( /^\uFEFF/, '' );
				preview();
			};
			reader.readAsArrayBuffer( file );
		} );
	}

	bulk.addEventListener( 'submit', function ( e ) {
		e.preventDefault();
		if ( busy ) {
			return;
		}
		busy = true;
		createButton.disabled = true;
		createStatus.textContent = texts.creating;
		createStatus.classList.add( 'sunshine-utm-loading' );

		post( 'sunshine_utm_bulk_create', new FormData( bulk ) )
			.then( function ( result ) {
				if ( result && result.success ) {
					window.location.href = result.data.redirect;
					return;
				}
				busy = false;
				createStatus.classList.remove( 'sunshine-utm-loading' );
				createStatus.textContent = ( result && result.data && result.data.message ) || texts.failed;
				createButton.disabled = false;
			} )
			.catch( function () {
				busy = false;
				createStatus.classList.remove( 'sunshine-utm-loading' );
				createStatus.textContent = texts.failed;
				createButton.disabled = false;
			} );
	} );

	showMethod();
	preview();
} )();
