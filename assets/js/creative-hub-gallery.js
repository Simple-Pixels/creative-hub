/* Creative Hub — image gallery ([ch_gallery]) */
( function () {
	'use strict';

	function Gallery( root ) {
		this.root = root;
		this.slides = Array.prototype.slice.call( root.querySelectorAll( '.ch-gallery__slide' ) );
		this.thumbs = Array.prototype.slice.call( root.querySelectorAll( '.ch-gallery__thumb' ) );
		this.counter = root.querySelector( '[data-ch-gal-current]' );
		this.strip = root.querySelector( '.ch-gallery__thumbs' );
		this.index = 0;
		if ( this.slides.length < 2 ) {
			return;
		}
		this.bind();
	}

	Gallery.prototype.go = function ( i, focusThumb ) {
		var n = this.slides.length;
		i = ( i + n ) % n; // loop
		if ( i === this.index ) {
			return;
		}
		this.index = i;

		this.slides.forEach( function ( s, k ) {
			var on = k === i;
			s.classList.toggle( 'is-active', on );
			if ( on ) {
				s.removeAttribute( 'aria-hidden' );
			} else {
				s.setAttribute( 'aria-hidden', 'true' );
			}
		} );

		this.thumbs.forEach( function ( t, k ) {
			var on = k === i;
			t.classList.toggle( 'is-active', on );
			t.setAttribute( 'aria-selected', on ? 'true' : 'false' );
			t.tabIndex = on ? 0 : -1;
		} );

		if ( this.counter ) {
			this.counter.textContent = i + 1;
		}

		// Keep the active thumb in view (scrolls the strip only, not the page)
		var thumb = this.thumbs[ i ];
		if ( thumb && this.strip ) {
			var vertical = this.strip.scrollHeight > this.strip.clientHeight + 1;
			if ( vertical ) {
				var top = thumb.offsetTop - this.strip.offsetTop - ( this.strip.clientHeight - thumb.offsetHeight ) / 2;
				this.strip.scrollTo( { top: top, behavior: 'smooth' } );
			} else {
				var left = thumb.offsetLeft - this.strip.offsetLeft - ( this.strip.clientWidth - thumb.offsetWidth ) / 2;
				this.strip.scrollTo( { left: left, behavior: 'smooth' } );
			}
			if ( focusThumb ) {
				thumb.focus( { preventScroll: true } );
			}
		}
	};

	Gallery.prototype.bind = function () {
		var self = this;

		this.root.querySelector( '[data-ch-gal-prev]' ).addEventListener( 'click', function () {
			self.go( self.index - 1 );
		} );
		this.root.querySelector( '[data-ch-gal-next]' ).addEventListener( 'click', function () {
			self.go( self.index + 1 );
		} );

		this.thumbs.forEach( function ( t, k ) {
			t.tabIndex = k === 0 ? 0 : -1;
			t.addEventListener( 'click', function () {
				self.go( k );
			} );
		} );

		// Arrow keys when the gallery has focus
		this.root.addEventListener( 'keydown', function ( e ) {
			var inStrip = self.strip && self.strip.contains( document.activeElement );
			if ( e.key === 'ArrowLeft' || e.key === 'ArrowUp' ) {
				e.preventDefault();
				self.go( self.index - 1, inStrip );
			}
			if ( e.key === 'ArrowRight' || e.key === 'ArrowDown' ) {
				e.preventDefault();
				self.go( self.index + 1, inStrip );
			}
		} );

		// Swipe on the main image
		var stage = this.root.querySelector( '.ch-gallery__stage' );
		var x0 = null;
		var y0 = null;
		stage.addEventListener( 'touchstart', function ( e ) {
			x0 = e.touches[ 0 ].clientX;
			y0 = e.touches[ 0 ].clientY;
		}, { passive: true } );
		stage.addEventListener( 'touchend', function ( e ) {
			if ( x0 === null ) {
				return;
			}
			var dx = e.changedTouches[ 0 ].clientX - x0;
			var dy = e.changedTouches[ 0 ].clientY - y0;
			if ( Math.abs( dx ) > 40 && Math.abs( dx ) > Math.abs( dy ) ) {
				self.go( self.index + ( dx < 0 ? 1 : -1 ) );
			}
			x0 = y0 = null;
		}, { passive: true } );
	};

	function boot( scope ) {
		( scope || document ).querySelectorAll( '[data-ch-gallery]' ).forEach( function ( el ) {
			if ( el.__chGal ) {
				return;
			}
			el.__chGal = new Gallery( el );
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			boot();
		} );
	} else {
		boot();
	}

	// Elementor re-renders widgets inside its editor; initialise those too.
	window.addEventListener( 'elementor/frontend/init', function () {
		if ( window.elementorFrontend && window.elementorFrontend.hooks ) {
			window.elementorFrontend.hooks.addAction( 'frontend/element_ready/global', function ( $scope ) {
				boot( $scope[ 0 ] );
			} );
		}
	} );
} )();
