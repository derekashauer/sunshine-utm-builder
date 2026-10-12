/**
 * Sunshine UTM Builder: the link builder form on the plugin's admin screen.
 *
 * The link is worked out in the browser on every keystroke. formatValue(), addParams(),
 * normalizeUrl(), isValidUrl(), parseExtra() and buildUrl() are twins of the same functions in
 * includes/class-url-builder.php, which builds bulk links. They have to give the same link for the
 * same input, so change both together. (The sunshine_utm_link_params filter only runs in PHP.)
 */
( function () {
	'use strict';

	var KEYS = [ 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content' ];
	var config = window.SunshineUTMBuilder || {};
	var texts = config.texts || {};

	function formatValue( value, format ) {
		value = String( value || '' ).replace( /\s+/g, ' ' ).trim();
		if ( format.lowercase ) {
			value = value.toLowerCase();
		}
		if ( format.strip_symbols ) {
			// Keep letters (any language), numbers, dash, underscore, period and space. Symbols go
			// before spaces are swapped, so "Sale & Clearance" becomes sale_clearance, not sale__clearance.
			value = value.replace( /[^\p{L}\p{N}_.\- ]/gu, '' ).replace( / {2,}/g, ' ' ).trim();
		}
		if ( format.spaces === '_' || format.spaces === '-' ) {
			value = value.split( ' ' ).join( format.spaces );
		}
		return value;
	}

	// rawurlencode() in PHP also encodes ! ' ( ) and *, which encodeURIComponent leaves alone.
	function encode( value ) {
		return encodeURIComponent( value ).replace( /[!'()*]/g, function ( c ) {
			return '%' + c.charCodeAt( 0 ).toString( 16 ).toUpperCase();
		} );
	}

	function decode( value ) {
		try {
			return decodeURIComponent( value.replace( /\+/g, ' ' ) );
		} catch ( e ) {
			return value;
		}
	}

	// params is a list of [ name, value ] pairs, so names like "1" keep their place.
	function addParams( url, params ) {
		var hash = '';
		var query = '';
		var pos = url.indexOf( '#' );
		if ( pos !== -1 ) {
			hash = url.slice( pos );
			url = url.slice( 0, pos );
		}
		pos = url.indexOf( '?' );
		if ( pos !== -1 ) {
			query = url.slice( pos + 1 );
			url = url.slice( 0, pos );
		}

		var replace = params.map( function ( param ) {
			return String( param[ 0 ] ).toLowerCase();
		} );
		var pairs = [];
		query.split( '&' ).forEach( function ( pair ) {
			if ( pair === '' ) {
				return;
			}
			var name = decode( pair.split( '=' )[ 0 ] ).toLowerCase();
			if ( name.indexOf( 'utm_' ) === 0 || replace.indexOf( name ) !== -1 ) {
				return;
			}
			pairs.push( pair );
		} );
		params.forEach( function ( param ) {
			pairs.push( encode( String( param[ 0 ] ) ) + '=' + encode( String( param[ 1 ] ) ) );
		} );

		return url + ( pairs.length ? '?' + pairs.join( '&' ) : '' ) + hash;
	}

	function normalizeUrl( url ) {
		url = String( url || '' ).trim();
		if ( url !== '' && url.indexOf( '://' ) === -1 ) {
			url = 'https://' + url.replace( /^\/+/, '' );
		}
		return url;
	}

	function isValidUrl( url ) {
		// mailto:, tel: and javascript: aren't pages. (A colon before a number is a port, like localhost:8080.)
		if ( /^[a-z][a-z0-9+.-]*:(?![0-9\/])/i.test( String( url || '' ).trim() ) ) {
			return false;
		}
		url = normalizeUrl( url );
		if ( url === '' || /\s/.test( url ) ) {
			return false;
		}
		try {
			var parsed = new URL( url );
			if ( ( parsed.protocol !== 'http:' && parsed.protocol !== 'https:' ) || parsed.hostname === '' ) {
				return false;
			}
			// A single word ("sale") is almost always a typo, not a site. localhost and IPv6 are fine.
			return parsed.hostname.indexOf( '.' ) !== -1 || parsed.hostname === 'localhost' || parsed.hostname.indexOf( '[' ) === 0;
		} catch ( e ) {
			return false;
		}
	}

	// "name=value" lines to [ name, value ] pairs. A repeated name keeps its first place, last value.
	function parseExtra( text ) {
		var params = [];
		String( text || '' ).split( /\r\n|\r|\n/ ).forEach( function ( line ) {
			var pos = line.indexOf( '=' );
			if ( pos === -1 ) {
				return;
			}
			setParam( params, line.slice( 0, pos ).trim(), line.slice( pos + 1 ).trim() );
		} );
		return params;
	}

	function setParam( params, name, value ) {
		if ( name === '' ) {
			return;
		}
		for ( var i = 0; i < params.length; i++ ) {
			if ( params[ i ][ 0 ] === name ) {
				params[ i ][ 1 ] = value;
				return;
			}
		}
		params.push( [ name, value ] );
	}

	function buildUrl( url, tags, extra, format ) {
		var params = [];
		KEYS.forEach( function ( key ) {
			var value = formatValue( tags[ key ], format );
			if ( value !== '' ) {
				params.push( [ key, value ] );
			}
		} );
		extra.forEach( function ( param ) {
			var name = String( param[ 0 ] ).trim();
			var value = String( param[ 1 ] ).trim();
			// The five main tags come from their own fields. Others, like utm_id, are fine here.
			if ( name === '' || value === '' || KEYS.indexOf( name.toLowerCase() ) !== -1 ) {
				return;
			}
			setParam( params, name, value );
		} );
		return addParams( normalizeUrl( url ), params );
	}

	function copyText( text, field ) {
		if ( navigator.clipboard && window.isSecureContext ) {
			return navigator.clipboard.writeText( text );
		}
		return new Promise( function ( resolve, reject ) {
			field.focus();
			field.select();
			try {
				if ( document.execCommand( 'copy' ) ) {
					resolve();
				} else {
					reject();
				}
			} catch ( e ) {
				reject( e );
			}
		} );
	}

	function post( data ) {
		var body = new FormData();
		Object.keys( data ).forEach( function ( key ) {
			body.append( key, data[ key ] );
		} );
		return fetch( config.ajax_url, { method: 'POST', body: body, credentials: 'same-origin' } ).then( function ( response ) {
			return response.json();
		} );
	}

	function Builder( form ) {
		var self = this;
		this.form = form;
		this.format = JSON.parse( form.getAttribute( 'data-format' ) || '{}' );
		this.nonce = form.getAttribute( 'data-nonce' );
		this.link = form.querySelector( '[data-sunshine-utm-link]' );
		this.status = form.querySelector( '[data-sunshine-utm-status]' );
		this.announce = form.querySelector( '[data-sunshine-utm-announce]' );
		this.open = form.querySelector( '[data-sunshine-utm-open]' );
		this.shorteners = form.querySelector( '[data-sunshine-utm-shorteners]' );
		this.service = '';
		this.shortBox = form.querySelector( '[data-sunshine-utm-short]' );
		this.shortUrl = form.querySelector( '[data-sunshine-utm-short-url]' );
		this.preset = form.querySelector( '[data-sunshine-utm-preset]' );
		this.channel = form.querySelector( '[data-sunshine-utm-channel]' );
		this.qr = form.querySelector( '[data-sunshine-utm-qr]' );
		this.allowed = JSON.parse( form.getAttribute( 'data-allowed' ) || '{}' );
		this.current = '';
		this.message = '';
		this.shortTimer = 0;
		this.shortCache = {};
		this.presetRequest = 0;

		form.addEventListener( 'input', function ( e ) {
			if ( e.target !== self.preset ) {
				self.update();
			}
		} );
		// Radios and selects, for browsers that don't send input events for them.
		form.addEventListener( 'change', function ( e ) {
			if ( e.target !== self.preset ) {
				self.update();
			}
		} );
		form.addEventListener( 'reset', function () {
			window.setTimeout( function () {
				self.update();
			}, 0 );
		} );
		// Enter in a field shouldn't reload the page.
		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
		} );

		form.querySelectorAll( '[data-sunshine-utm-copy]' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				self.copy( button );
			} );
		} );
		if ( this.preset ) {
			this.preset.addEventListener( 'change', function () {
				self.applyPreset();
			} );
		}

		this.update();
	}

	Builder.prototype.field = function ( name ) {
		var field = this.form.elements[ name ];
		return field ? field.value : '';
	};

	Builder.prototype.update = function () {
		var url = this.field( 'url' );
		var tags = {};
		var message = '';
		var link = '';
		var self = this;

		KEYS.forEach( function ( key ) {
			tags[ key ] = self.field( key );
		} );

		if ( url.trim() === '' ) {
			message = texts.need_url;
		} else if ( ! isValidUrl( url ) ) {
			message = texts.bad_url;
		} else if ( formatValue( tags.utm_source, this.format ) === '' ) {
			message = texts.need_source;
		} else {
			message = this.notAllowed( tags );
			if ( message === '' ) {
				link = buildUrl( url, tags, parseExtra( this.field( 'extra' ) ), this.format );
			}
		}

		var service = this.field( 'shortener' );
		if ( link !== this.current || service !== this.service ) {
			this.current = link;
			this.service = service;
			this.link.value = link;
			if ( this.shortBox ) {
				this.queueShorten();
			}
		}

		this.setStatus( message );
		this.showChannel( link ? tags : null );
		this.setQr();
		this.form.querySelector( '[data-sunshine-utm-copy="link"]' ).disabled = link === '';
		this.open.hidden = link === '';
		this.open.href = link || '#';
	};

	// The team's lists from Settings, when only they're allowed. Checked as the tag will be in the
	// link. An empty medium isn't checked, since it's optional (same as Sunshine_UTM::tag_problem()).
	Builder.prototype.notAllowed = function ( tags ) {
		var self = this;
		var problem = '';
		[ 'utm_source', 'utm_medium' ].forEach( function ( key ) {
			var list = self.allowed[ key ];
			var value = formatValue( tags[ key ], self.format );
			if ( ! problem && value !== '' && list && list.length && list.indexOf( value ) === -1 ) {
				problem = ( key === 'utm_source' ? texts.not_allowed_source : texts.not_allowed_medium ).replace( '%1$s', value ).replace( '%2$s', list.join( ', ' ) );
			}
		} );
		return problem;
	};

	// Which Google Analytics 4 channel the link lands in, with a warning for "Unassigned".
	Builder.prototype.showChannel = function ( tags ) {
		if ( ! this.channel || ! window.SunshineUTMChannels ) {
			return;
		}
		if ( ! tags ) {
			this.channel.hidden = true;
			return;
		}
		var format = this.format;
		var found = window.SunshineUTMChannels.channel( formatValue( tags.utm_source, format ), formatValue( tags.utm_medium, format ), formatValue( tags.utm_campaign, format ) );
		var text;
		if ( ! found.unassigned ) {
			text = texts.channel.replace( '%s', found.name );
		} else if ( found.hint ) {
			text = texts.unassigned_hint.replace( '%1$s', formatValue( tags.utm_medium, format ) ).replace( '%2$s', found.hint );
		} else {
			text = texts.unassigned;
		}
		this.channel.textContent = text;
		this.channel.classList.toggle( 'is-unassigned', found.unassigned );
		this.channel.hidden = false;
	};

	// The QR code is for the short link once there is one, the full link until then. An open QR
	// panel for an older link is closed.
	Builder.prototype.setQr = function () {
		if ( ! this.qr ) {
			return;
		}
		var text = ( this.shortUrl && this.shortUrl.value ) || this.current;
		var panel = this.qr.parentNode.nextElementSibling;
		if ( panel && panel.hasAttribute( 'data-sunshine-utm-qr-panel' ) && panel.getAttribute( 'data-sunshine-utm-qr-panel' ) !== text ) {
			panel.remove();
			this.qr.setAttribute( 'aria-expanded', 'false' );
		}
		this.qr.setAttribute( 'data-sunshine-utm-qr', text );
		this.qr.hidden = text === '';
	};

	// The status line is a live region, so only change it when the message changes, or a screen
	// reader would repeat it on every keystroke.
	Builder.prototype.setStatus = function ( message ) {
		if ( message !== this.message ) {
			this.message = message;
			this.status.textContent = message;
		}
	};

	// A button's own label, saved once, so a second click while it says "Copied!" can't keep that.
	function label( button ) {
		if ( ! button.hasAttribute( 'data-label' ) ) {
			button.setAttribute( 'data-label', button.textContent.trim() );
		}
		return button.getAttribute( 'data-label' );
	}

	Builder.prototype.copy = function ( button ) {
		var field = button.getAttribute( 'data-sunshine-utm-copy' ) === 'short' ? this.shortUrl : this.link;
		var self = this;
		if ( ! field.value ) {
			return;
		}
		var original = label( button );
		copyText( field.value, field ).then(
			function () {
				button.textContent = texts.copied;
				button.classList.add( 'is-copied' );
				self.announce.textContent = texts.copied;
				window.clearTimeout( button.sunshineUTMTimer );
				button.sunshineUTMTimer = window.setTimeout( function () {
					button.textContent = original;
					button.classList.remove( 'is-copied' );
					self.announce.textContent = '';
				}, 2000 );
			},
			function () {
				field.focus();
				field.select();
				self.setStatus( texts.copy_failed );
			}
		);
	};

	// The short link follows the "Shorten with" choice. It's made once typing pauses, so half-typed
	// links don't each get a short link (or use up Bitly credits), and each link and service pair is
	// only asked for once.
	Builder.prototype.queueShorten = function () {
		var self = this;
		var key = this.service + '|' + this.current;
		window.clearTimeout( this.shortTimer );
		if ( ! this.current || ! this.service ) {
			this.shortBox.hidden = true;
			this.shortUrl.value = '';
			return;
		}
		this.shortBox.hidden = false;
		if ( this.shortCache[ key ] ) {
			this.shortUrl.value = this.shortCache[ key ];
			this.setQr();
			return;
		}
		this.shortUrl.value = '';
		this.shortUrl.placeholder = texts.shortening;
		this.shortTimer = window.setTimeout( function () {
			self.shorten();
		}, 900 );
	};

	Builder.prototype.shorten = function () {
		var self = this;
		var link = this.current;
		var service = this.service;
		post( { action: 'sunshine_utm_shorten', nonce: this.nonce, url: link, service: service } )
			.then( function ( result ) {
				var ok = result && result.success;
				if ( ok ) {
					self.shortCache[ service + '|' + link ] = result.data.short_url;
				}
				// The link or the service changed while we waited, so this reply is out of date.
				if ( link !== self.current || service !== self.service ) {
					return;
				}
				if ( ok ) {
					self.shortUrl.value = result.data.short_url;
					self.announce.textContent = result.data.short_url;
					self.setQr();
				} else {
					self.shortBox.hidden = true;
					self.setStatus( ( result && result.data && result.data.message ) || texts.short_failed );
				}
			} )
			.catch( function () {
				if ( link === self.current && service === self.service ) {
					self.shortBox.hidden = true;
					self.setStatus( texts.short_failed );
				}
			} );
	};

	Builder.prototype.applyPreset = function () {
		var self = this;
		var id = this.preset.value;
		var request = ++this.presetRequest;
		if ( ! id ) {
			return;
		}
		this.setStatus( texts.preset_wait );
		post( { action: 'sunshine_utm_preset', nonce: this.nonce, id: id, url: this.field( 'url' ) } )
			.then( function ( result ) {
				// Another preset was picked while this one loaded.
				if ( request !== self.presetRequest ) {
					return;
				}
				if ( result && result.success ) {
					KEYS.forEach( function ( key ) {
						if ( self.form.elements[ key ] ) {
							self.form.elements[ key ].value = result.data[ key ] || '';
						}
					} );
				}
				self.update();
			} )
			.catch( function () {
				if ( request === self.presetRequest ) {
					self.update();
				}
			} );
	};

	function init() {
		document.querySelectorAll( '[data-sunshine-utm-builder]' ).forEach( function ( form ) {
			if ( ! form.sunshineUTM ) {
				form.sunshineUTM = new Builder( form );
			}
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

	// For tests, and anyone who wants the same link from their own script.
	window.SunshineUTM = {
		buildUrl: buildUrl,
		formatValue: formatValue,
		parseExtra: parseExtra,
		isValidUrl: isValidUrl,
	};
} )();
