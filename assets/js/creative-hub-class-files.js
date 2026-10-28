/* Creative Hub — class files popup ([ch_class_files]) */
( function () {
	'use strict';

	var openModal = null;
	var lastFocus = null;

	function $$( sel, root ) {
		return Array.prototype.slice.call( ( root || document ).querySelectorAll( sel ) );
	}

	function focusable( modal ) {
		return $$( 'button, a[href]', modal ).filter( function ( el ) {
			return ! el.disabled && el.offsetParent !== null;
		} );
	}

	function onKeydown( e ) {
		if ( ! openModal ) {
			return;
		}
		if ( e.key === 'Escape' ) {
			e.preventDefault();
			close();
			return;
		}
		if ( e.key !== 'Tab' ) {
			return;
		}
		var items = focusable( openModal );
		if ( ! items.length ) {
			return;
		}
		var first = items[ 0 ];
		var last = items[ items.length - 1 ];
		if ( ! openModal.contains( document.activeElement ) ) {
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

	function open( modal, trigger ) {
		if ( openModal ) {
			close();
		}
		openModal = modal;
		lastFocus = trigger || document.activeElement;
		modal.classList.add( 'is-open' );
		document.documentElement.classList.add( 'ch-cfm-open' );
		document.addEventListener( 'keydown', onKeydown );

		// Start on the main action: "Download all" when there is one, otherwise the close button.
		var start = modal.querySelector( '.ch-cfm__all' ) || modal.querySelector( '.ch-cfm__dl' ) || modal.querySelector( '.ch-cfm__close' );
		if ( start ) {
			start.focus( { preventScroll: true } );
		}
	}

	function close() {
		if ( ! openModal ) {
			return;
		}
		openModal.classList.remove( 'is-open' );
		document.documentElement.classList.remove( 'ch-cfm-open' );
		document.removeEventListener( 'keydown', onKeydown );
		openModal = null;
		if ( lastFocus && lastFocus.focus ) {
			lastFocus.focus( { preventScroll: true } );
		}
	}

	// Building a zip can take a few seconds. Say so, and stop a second click starting another one.
	function markBusy( link ) {
		if ( link.classList.contains( 'is-busy' ) ) {
			return false;
		}
		var label = link.querySelector( '[data-ch-cfm-zip-label]' );
		var original = label ? label.textContent : '';
		link.classList.add( 'is-busy' );
		link.setAttribute( 'aria-busy', 'true' );
		if ( label && link.getAttribute( 'data-busy' ) ) {
			label.textContent = link.getAttribute( 'data-busy' );
		}
		window.setTimeout( function () {
			link.classList.remove( 'is-busy' );
			link.removeAttribute( 'aria-busy' );
			if ( label ) {
				label.textContent = original;
			}
		}, 8000 );
		return true;
	}

	document.addEventListener( 'click', function ( e ) {
		if ( ! e.target.closest ) {
			return;
		}

		var trigger = e.target.closest( '[data-ch-cfm-open]' );
		if ( trigger ) {
			var modal = document.getElementById( trigger.getAttribute( 'data-ch-cfm-open' ) );
			if ( modal ) {
				e.preventDefault();
				open( modal, trigger );
			}
			return;
		}

		if ( openModal && e.target.closest( '[data-ch-cfm-close]' ) && openModal.contains( e.target ) ) {
			close();
			return;
		}

		var zip = e.target.closest( '[data-ch-cfm-zip]' );
		if ( zip && ! markBusy( zip ) ) {
			e.preventDefault();
		}
	} );
} )();
