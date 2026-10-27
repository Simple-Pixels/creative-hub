/* Creative Hub — freebies: sign-up / log-in popup before a download ([ch_freebies]) */
( function () {
	'use strict';

	var cfg = window.chFreebies || {};
	var modal = document.getElementById( 'ch-fbm' );
	var i18n = {};
	if ( modal ) {
		try {
			i18n = JSON.parse( modal.getAttribute( 'data-ch-i18n' ) || '{}' );
		} catch ( err ) { /* fall back to server messages */ }
	}

	var nonce = '';
	var pendingUrl = '';
	var lastFocus = null;
	var loggedIn = false;

	function $( sel, root ) {
		return ( root || document ).querySelector( sel );
	}

	function $$( sel, root ) {
		return Array.prototype.slice.call( ( root || document ).querySelectorAll( sel ) );
	}

	/* ------------------------------------------------------------------ */
	/* Server                                                              */
	/* ------------------------------------------------------------------ */

	function request( action, data ) {
		var isPost = !! data;
		var url = cfg.ajax + ( isPost ? '' : '?action=' + encodeURIComponent( action ) + '&_=' + Date.now() );
		var opts = { credentials: 'same-origin' };

		if ( isPost ) {
			var body = new FormData();
			body.append( 'action', action );
			Object.keys( data ).forEach( function ( key ) {
				body.append( key, data[ key ] );
			} );
			opts.method = 'POST';
			opts.body = body;
		}

		return fetch( url, opts ).then( function ( res ) {
			return res.json().catch( function () {
				return { success: false, data: { message: i18n.network } };
			} );
		} );
	}

	// A fresh nonce each time the popup opens, so a cached page never holds a stale one.
	function loadState() {
		return request( 'ch_freebie_state' ).then( function ( res ) {
			if ( res && res.success ) {
				nonce = res.data.nonce;
				return res.data;
			}
			return { loggedIn: false };
		} ).catch( function () {
			return { loggedIn: false };
		} );
	}

	/* ------------------------------------------------------------------ */
	/* Downloading                                                         */
	/* ------------------------------------------------------------------ */

	// A hidden iframe, so a server error can never replace the page the visitor is on.
	function startDownload( url ) {
		var frame = document.createElement( 'iframe' );
		frame.setAttribute( 'aria-hidden', 'true' );
		frame.tabIndex = -1;
		frame.style.cssText = 'position:absolute;width:0;height:0;border:0;visibility:hidden';
		frame.src = url;
		document.body.appendChild( frame );
		window.setTimeout( function () {
			if ( frame.parentNode ) {
				frame.parentNode.removeChild( frame );
			}
		}, 60000 );
	}

	function markLoggedIn() {
		loggedIn = true;
		$$( '[data-ch-freebies]' ).forEach( function ( el ) {
			el.setAttribute( 'data-logged-in', '1' );
		} );
	}

	/* ------------------------------------------------------------------ */
	/* Popup                                                               */
	/* ------------------------------------------------------------------ */

	function focusable() {
		return $$( 'button, a[href], input, select, textarea', modal ).filter( function ( el ) {
			return ! el.disabled && el.offsetParent !== null && el.getAttribute( 'tabindex' ) !== '-1' && ! el.closest( '[hidden]' ) && ! el.closest( '.ch-fbm__hp' );
		} );
	}

	function showView( name ) {
		$$( '[data-ch-fbm-view]', modal ).forEach( function ( view ) {
			view.hidden = view.getAttribute( 'data-ch-fbm-view' ) !== name;
		} );
	}

	function setError( form, message ) {
		var box = $( '[data-ch-fbm-error]', form );
		box.textContent = message || '';
		box.classList.toggle( 'is-visible', !! message );
	}

	function selectTab( name, focusTab ) {
		$$( '[data-ch-fbm-tab]', modal ).forEach( function ( tab ) {
			var on = tab.getAttribute( 'data-ch-fbm-tab' ) === name;
			tab.setAttribute( 'aria-selected', on ? 'true' : 'false' );
			tab.tabIndex = on ? 0 : -1;
			if ( on && focusTab ) {
				tab.focus();
			}
		} );
		$$( '[data-ch-fbm-form]', modal ).forEach( function ( form ) {
			form.hidden = form.getAttribute( 'data-ch-fbm-form' ) !== name;
			setError( form, '' );
		} );
	}

	function openModal( url, trigger ) {
		pendingUrl = url;
		lastFocus = trigger || document.activeElement;
		showView( 'form' );
		selectTab( 'register' );
		modal.classList.add( 'is-open' );
		document.documentElement.classList.add( 'ch-fbm-open' );
		document.addEventListener( 'keydown', onKeydown );
		var first = $( '#ch-fbm-reg-email' );
		if ( first ) {
			first.focus( { preventScroll: true } );
		}
	}

	function closeModal() {
		if ( ! modal || ! modal.classList.contains( 'is-open' ) ) {
			return;
		}
		modal.classList.remove( 'is-open' );
		document.documentElement.classList.remove( 'ch-fbm-open' );
		document.removeEventListener( 'keydown', onKeydown );
		$$( '.ch-fbm__form', modal ).forEach( function ( form ) {
			var password = $( 'input[type="password"]', form );
			if ( password ) {
				password.value = '';
			}
		} );
		if ( lastFocus && lastFocus.focus ) {
			lastFocus.focus( { preventScroll: true } );
		}
	}

	function onKeydown( e ) {
		if ( e.key === 'Escape' ) {
			e.preventDefault();
			closeModal();
			return;
		}
		if ( e.key !== 'Tab' ) {
			return;
		}
		var items = focusable();
		if ( ! items.length ) {
			return;
		}
		var first = items[ 0 ];
		var last = items[ items.length - 1 ];
		if ( ! modal.contains( document.activeElement ) ) {
			e.preventDefault();
			first.focus();
		} else if ( e.shiftKey && document.activeElement === first ) {
			e.preventDefault();
			last.focus();
		} else if ( ! e.shiftKey && document.activeElement === last ) {
			e.preventDefault();
			first.focus();
		}
	}

	function validate( form, kind ) {
		var email = form.elements[ kind === 'login' ? 'log' : 'email' ].value.trim();
		var password = form.elements.password.value;

		if ( kind === 'register' ) {
			if ( ! /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test( email ) ) {
				return { message: i18n.badEmail, field: 'email' };
			}
			if ( password.length < 8 ) {
				return { message: i18n.shortPass, field: 'password' };
			}
		} else if ( ! email || ! password ) {
			return { message: i18n.noLogin, field: email ? 'password' : 'log' };
		}
		return null;
	}

	function submit( form, kind, retried ) {
		var button = $( 'button[type="submit"]', form );
		var label = button.textContent;
		var problem = validate( form, kind );

		if ( problem ) {
			setError( form, problem.message );
			form.elements[ problem.field ].focus();
			return;
		}

		setError( form, '' );
		button.disabled = true;
		button.setAttribute( 'aria-busy', 'true' );
		button.textContent = i18n.working || label;

		var data = { nonce: nonce, password: form.elements.password.value };
		if ( kind === 'register' ) {
			data.email = form.elements.email.value.trim();
			data.website = form.elements.website.value;
		} else {
			data.log = form.elements.log.value.trim();
		}

		function done() {
			button.disabled = false;
			button.removeAttribute( 'aria-busy' );
			button.textContent = label;
		}

		request( 'ch_freebie_' + kind, data ).then( function ( res ) {
			if ( res && res.success ) {
				done();
				markLoggedIn();
				showView( 'done' );
				$( '[data-ch-fbm-link]', modal ).href = pendingUrl;
				startDownload( pendingUrl );
				var close = $( '[data-ch-fbm-view="done"] [data-ch-fbm-close]', modal );
				if ( close ) {
					close.focus( { preventScroll: true } );
				}
				return;
			}

			var info = ( res && res.data ) || {};

			// The nonce expired while the popup sat open: get a new one and try once more.
			if ( info.code === 'nonce' && ! retried ) {
				return loadState().then( function () {
					done();
					submit( form, kind, true );
				} );
			}

			done();

			if ( info.code === 'exists' ) {
				var email = form.elements.email.value.trim();
				selectTab( 'login' );
				var loginForm = $( '[data-ch-fbm-form="login"]', modal );
				loginForm.elements.log.value = email;
				setError( loginForm, i18n.exists || info.message );
				loginForm.elements.password.focus();
				return;
			}

			setError( form, info.message || i18n.network );
		} ).catch( function () {
			done();
			setError( form, i18n.network );
		} );
	}

	/* ------------------------------------------------------------------ */
	/* Wiring                                                              */
	/* ------------------------------------------------------------------ */

	document.addEventListener( 'click', function ( e ) {
		var link = e.target.closest ? e.target.closest( '[data-ch-freebie-dl]' ) : null;
		if ( ! link ) {
			return;
		}

		var wrap = link.closest( '[data-ch-freebies]' );
		if ( loggedIn || ( wrap && wrap.getAttribute( 'data-logged-in' ) === '1' ) ) {
			return; // Logged in: the link downloads the file directly.
		}
		if ( ! modal ) {
			return; // No popup on the page: the link sends them to log in instead.
		}

		e.preventDefault();
		var url = link.href;
		openModal( url, link );

		// They may have logged in elsewhere (another tab, a cached page): check, and skip the popup if so.
		loadState().then( function ( state ) {
			if ( state.loggedIn ) {
				markLoggedIn();
				closeModal();
				startDownload( url );
			}
		} );
	} );

	if ( ! modal ) {
		return;
	}

	modal.addEventListener( 'click', function ( e ) {
		if ( e.target.closest( '[data-ch-fbm-close]' ) ) {
			closeModal();
			return;
		}
		var tab = e.target.closest( '[data-ch-fbm-tab]' );
		if ( tab ) {
			selectTab( tab.getAttribute( 'data-ch-fbm-tab' ) );
		}
	} );

	// Arrow keys move between the two tabs.
	modal.addEventListener( 'keydown', function ( e ) {
		var tab = e.target.closest ? e.target.closest( '[data-ch-fbm-tab]' ) : null;
		if ( ! tab || ( e.key !== 'ArrowLeft' && e.key !== 'ArrowRight' ) ) {
			return;
		}
		e.preventDefault();
		selectTab( tab.getAttribute( 'data-ch-fbm-tab' ) === 'register' ? 'login' : 'register', true );
	} );

	$$( '[data-ch-fbm-form]', modal ).forEach( function ( form ) {
		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			submit( form, form.getAttribute( 'data-ch-fbm-form' ), false );
		} );
	} );
} )();
