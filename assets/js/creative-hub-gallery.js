/* Creative Hub — image gallery ([ch_gallery]) with lightbox */
( function () {
	'use strict';

	var DEFAULT_LABELS = {
		open: 'View image full screen',
		viewer: 'Image viewer',
		close: 'Close',
		prev: 'Previous image',
		next: 'Next image',
		zoomIn: 'Zoom in',
		zoomOut: 'Zoom out'
	};

	var ICON_ZOOM = '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><circle cx="10.5" cy="10.5" r="6.5" fill="none" stroke="currentColor" stroke-width="2"/><path d="M15.5 15.5L21 21M10.5 7.5v6M7.5 10.5h6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';
	var ICON_PREV = '<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false"><path d="M15 5l-7 7 7 7" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
	var ICON_NEXT = '<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false"><path d="M9 5l7 7-7 7" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
	var ICON_CLOSE = '<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>';

	function Gallery( root ) {
		this.root = root;
		this.stage = root.querySelector( '.ch-gallery__stage' );
		this.slides = Array.prototype.slice.call( root.querySelectorAll( '.ch-gallery__slide' ) );
		this.thumbs = Array.prototype.slice.call( root.querySelectorAll( '.ch-gallery__thumb' ) );
		this.counter = root.querySelector( '[data-ch-gal-current]' );
		this.strip = root.querySelector( '.ch-gallery__thumbs' );
		this.index = 0;
		this.lb = null;

		var labels = {};
		try {
			labels = JSON.parse( root.getAttribute( 'data-ch-labels' ) || '{}' );
		} catch ( err ) { /* use defaults */ }
		this.labels = Object.assign( {}, DEFAULT_LABELS, labels );

		if ( ! this.stage || ! this.slides.length ) {
			return;
		}
		this.syncHeight();
		if ( this.slides.length > 1 ) {
			this.bind();
		}
		this.bindLightbox();
	}

	/* ------------------------------------------------------------------ */
	/* Layout                                                              */
	/* ------------------------------------------------------------------ */

	/**
	 * Publish the gallery's real height as a pixel min-height.
	 *
	 * Inside a flex column (Elementor containers, especially when a phone layout
	 * reverses them), the browser sizes the gallery's wrapper from its intrinsic
	 * height, ignoring anything that depends on width (aspect-ratio, percentage
	 * padding). The wrapper collapses and the content below slides up under the
	 * gallery. A fixed pixel min-height is always honoured, so keep one in sync.
	 */
	Gallery.prototype.syncHeight = function () {
		var root = this.root;
		var stage = this.stage;

		function measure() {
			root.style.minHeight = '0px';
			var h = root.getBoundingClientRect().height;
			root.style.minHeight = h ? h + 'px' : '';
		}

		measure();

		if ( window.ResizeObserver ) {
			new window.ResizeObserver( measure ).observe( stage );
		} else {
			window.addEventListener( 'resize', measure );
		}
	};

	/* ------------------------------------------------------------------ */
	/* Slider                                                              */
	/* ------------------------------------------------------------------ */

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
			var left = thumb.offsetLeft - this.strip.offsetLeft - ( this.strip.clientWidth - thumb.offsetWidth ) / 2;
			this.strip.scrollTo( { left: left, behavior: 'smooth' } );
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
			if ( e.key === 'ArrowLeft' ) {
				e.preventDefault();
				self.go( self.index - 1, inStrip );
			} else if ( e.key === 'ArrowRight' ) {
				e.preventDefault();
				self.go( self.index + 1, inStrip );
			}
		} );

		// Swipe on the main image
		var x0 = null;
		var y0 = null;
		this.stage.addEventListener( 'touchstart', function ( e ) {
			x0 = e.touches[ 0 ].clientX;
			y0 = e.touches[ 0 ].clientY;
		}, { passive: true } );
		this.stage.addEventListener( 'touchend', function ( e ) {
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

	/* ------------------------------------------------------------------ */
	/* Lightbox                                                            */
	/* ------------------------------------------------------------------ */

	Gallery.prototype.bindLightbox = function () {
		var self = this;

		// A transparent button over the main image: click or Enter/Space opens the lightbox.
		var open = document.createElement( 'button' );
		open.type = 'button';
		open.className = 'ch-gallery__open';
		open.setAttribute( 'aria-label', this.labels.open );
		open.addEventListener( 'click', function () {
			self.openLightbox( self.index );
		} );
		this.slides[ this.slides.length - 1 ].insertAdjacentElement( 'afterend', open );
		this.openButton = open;

		var icon = document.createElement( 'span' );
		icon.className = 'ch-gallery__zoom-icon';
		icon.setAttribute( 'aria-hidden', 'true' );
		icon.innerHTML = ICON_ZOOM;
		this.stage.appendChild( icon );
	};

	Gallery.prototype.buildLightbox = function () {
		var self = this;
		var multi = this.slides.length > 1;
		var L = this.labels;

		var lb = document.createElement( 'div' );
		lb.className = 'ch-lightbox';
		lb.setAttribute( 'role', 'dialog' );
		lb.setAttribute( 'aria-modal', 'true' );
		lb.setAttribute( 'aria-label', L.viewer );
		lb.innerHTML =
			'<div class="ch-lightbox__stage"><img class="ch-lightbox__img" alt="" draggable="false"></div>' +
			'<div class="ch-lightbox__tools">' +
				'<button type="button" class="ch-lightbox__zoom" aria-label="' + L.zoomIn + '" aria-pressed="false">' + ICON_ZOOM + '</button>' +
				'<button type="button" class="ch-lightbox__close" aria-label="' + L.close + '">' + ICON_CLOSE + '</button>' +
			'</div>' +
			( multi
				? '<button type="button" class="ch-lightbox__nav ch-lightbox__nav--prev" aria-label="' + L.prev + '">' + ICON_PREV + '</button>' +
					'<button type="button" class="ch-lightbox__nav ch-lightbox__nav--next" aria-label="' + L.next + '">' + ICON_NEXT + '</button>' +
					'<span class="ch-lightbox__count" aria-live="polite"></span>'
				: '' );
		document.body.appendChild( lb );

		this.lb = lb;
		this.lbStage = lb.querySelector( '.ch-lightbox__stage' );
		this.lbImg = lb.querySelector( '.ch-lightbox__img' );
		this.lbCount = lb.querySelector( '.ch-lightbox__count' );
		this.lbIndex = 0;
		this.lbToken = 0;

		this.lbZoomBtn = lb.querySelector( '.ch-lightbox__zoom' );
		this.lbZoomBtn.addEventListener( 'click', function () {
			self.toggleZoom( null );
		} );
		lb.querySelector( '.ch-lightbox__close' ).addEventListener( 'click', function () {
			self.closeLightbox();
		} );
		if ( multi ) {
			lb.querySelector( '.ch-lightbox__nav--prev' ).addEventListener( 'click', function () {
				self.showLightbox( self.lbIndex - 1 );
			} );
			lb.querySelector( '.ch-lightbox__nav--next' ).addEventListener( 'click', function () {
				self.showLightbox( self.lbIndex + 1 );
			} );
		}

		// Click the backdrop (not the picture) to close.
		this.lbStage.addEventListener( 'click', function ( e ) {
			if ( e.target === self.lbStage && ! lb.classList.contains( 'is-zoomed' ) ) {
				self.closeLightbox();
			}
		} );

		// Click the picture to zoom; drag to pan once zoomed.
		var drag = null;
		this.lbImg.addEventListener( 'click', function ( e ) {
			if ( self.lbDragged ) {
				self.lbDragged = false;
				return;
			}
			self.toggleZoom( e );
		} );
		this.lbStage.addEventListener( 'pointerdown', function ( e ) {
			if ( e.pointerType !== 'mouse' || e.button !== 0 || ! lb.classList.contains( 'is-zoomed' ) ) {
				return;
			}
			drag = { x: e.clientX, y: e.clientY, left: self.lbStage.scrollLeft, top: self.lbStage.scrollTop };
			self.lbDragged = false;
			lb.classList.add( 'is-dragging' );
		} );
		window.addEventListener( 'pointermove', function ( e ) {
			if ( ! drag ) {
				return;
			}
			var dx = e.clientX - drag.x;
			var dy = e.clientY - drag.y;
			if ( Math.abs( dx ) + Math.abs( dy ) > 4 ) {
				self.lbDragged = true;
			}
			self.lbStage.scrollLeft = drag.left - dx;
			self.lbStage.scrollTop = drag.top - dy;
		} );
		window.addEventListener( 'pointerup', function () {
			if ( drag ) {
				drag = null;
				lb.classList.remove( 'is-dragging' );
			}
		} );

		// Swipe between images when not zoomed.
		var x0 = null;
		var y0 = null;
		this.lbStage.addEventListener( 'touchstart', function ( e ) {
			x0 = e.touches.length === 1 ? e.touches[ 0 ].clientX : null;
			y0 = e.touches.length === 1 ? e.touches[ 0 ].clientY : null;
		}, { passive: true } );
		this.lbStage.addEventListener( 'touchend', function ( e ) {
			if ( x0 === null || ! multi || lb.classList.contains( 'is-zoomed' ) ) {
				x0 = y0 = null;
				return;
			}
			var dx = e.changedTouches[ 0 ].clientX - x0;
			var dy = e.changedTouches[ 0 ].clientY - y0;
			if ( Math.abs( dx ) > 50 && Math.abs( dx ) > Math.abs( dy ) ) {
				self.showLightbox( self.lbIndex + ( dx < 0 ? 1 : -1 ) );
			}
			x0 = y0 = null;
		}, { passive: true } );

		this.lbKeydown = function ( e ) {
			if ( e.key === 'Escape' ) {
				e.preventDefault();
				self.closeLightbox();
			} else if ( e.key === 'ArrowLeft' && multi ) {
				e.preventDefault();
				self.showLightbox( self.lbIndex - 1 );
			} else if ( e.key === 'ArrowRight' && multi ) {
				e.preventDefault();
				self.showLightbox( self.lbIndex + 1 );
			} else if ( e.key === 'Tab' ) {
				// Keep focus inside the dialog.
				var focusable = Array.prototype.slice.call( lb.querySelectorAll( 'button' ) );
				var first = focusable[ 0 ];
				var last = focusable[ focusable.length - 1 ];
				if ( e.shiftKey && document.activeElement === first ) {
					e.preventDefault();
					last.focus();
				} else if ( ! e.shiftKey && document.activeElement === last ) {
					e.preventDefault();
					first.focus();
				}
			}
		};
	};

	Gallery.prototype.showLightbox = function ( i ) {
		var n = this.slides.length;
		i = ( i + n ) % n;
		this.lbIndex = i;
		this.setZoom( false );

		var slideImg = this.slides[ i ].querySelector( 'img' );
		var full = slideImg.getAttribute( 'data-full' ) || slideImg.currentSrc || slideImg.src;
		var token = ++this.lbToken;
		var self = this;

		// Show what's already loaded straight away, then swap in the full-size file.
		this.lbImg.alt = slideImg.alt || '';
		this.lbImg.src = slideImg.currentSrc || slideImg.src;
		this.lb.classList.remove( 'can-zoom' );

		var pre = new Image();
		pre.onload = function () {
			if ( token !== self.lbToken ) {
				return;
			}
			self.lbImg.src = full;
			// Only offer zoom if the file is bigger than what fits on screen.
			window.requestAnimationFrame( function () {
				var fits = self.lbImg.clientWidth + 4 >= pre.naturalWidth && self.lbImg.clientHeight + 4 >= pre.naturalHeight;
				self.lb.classList.toggle( 'can-zoom', ! fits );
			} );
		};
		pre.src = full;

		if ( this.lbCount ) {
			this.lbCount.textContent = ( i + 1 ) + ' / ' + n;
		}
		this.go( i );
	};

	Gallery.prototype.openLightbox = function ( i ) {
		if ( ! this.lb ) {
			this.buildLightbox();
		}
		this.lastFocus = document.activeElement;
		this.showLightbox( i );
		this.lb.classList.add( 'is-open' );
		document.documentElement.classList.add( 'ch-lightbox-open' );
		document.addEventListener( 'keydown', this.lbKeydown );
		this.lb.querySelector( '.ch-lightbox__close' ).focus( { preventScroll: true } );
	};

	Gallery.prototype.closeLightbox = function () {
		if ( ! this.lb || ! this.lb.classList.contains( 'is-open' ) ) {
			return;
		}
		this.lb.classList.remove( 'is-open' );
		this.setZoom( false );
		this.lbToken++;
		document.documentElement.classList.remove( 'ch-lightbox-open' );
		document.removeEventListener( 'keydown', this.lbKeydown );
		if ( this.lastFocus && this.lastFocus.focus ) {
			this.lastFocus.focus( { preventScroll: true } );
		}
	};

	Gallery.prototype.setZoom = function ( on ) {
		if ( ! this.lb ) {
			return;
		}
		this.lb.classList.toggle( 'is-zoomed', on );
		this.lbZoomBtn.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
		this.lbZoomBtn.setAttribute( 'aria-label', on ? this.labels.zoomOut : this.labels.zoomIn );
		if ( ! on ) {
			this.lbStage.scrollLeft = 0;
			this.lbStage.scrollTop = 0;
		}
	};

	Gallery.prototype.toggleZoom = function ( e ) {
		if ( ! this.lb.classList.contains( 'can-zoom' ) ) {
			return;
		}

		if ( this.lb.classList.contains( 'is-zoomed' ) ) {
			this.setZoom( false );
			return;
		}

		// Zoom in around the point that was clicked.
		var rect = this.lbImg.getBoundingClientRect();
		var fx = e ? ( e.clientX - rect.left ) / rect.width : 0.5;
		var fy = e ? ( e.clientY - rect.top ) / rect.height : 0.5;
		this.setZoom( true );
		var img = this.lbImg;
		var stage = this.lbStage;
		stage.scrollLeft = fx * img.offsetWidth - stage.clientWidth / 2;
		stage.scrollTop = fy * img.offsetHeight - stage.clientHeight / 2;
	};

	/* ------------------------------------------------------------------ */

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
