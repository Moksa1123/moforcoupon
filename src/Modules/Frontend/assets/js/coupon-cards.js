/**
 * Copy-to-clipboard for the [moforcoupon_coupons] card list.
 */
( function () {
	'use strict';

	var liveRegion = null;

	// A single polite live region so screen-reader users hear that the copy succeeded
	// (a button's own text change is not reliably announced).
	function announce( message ) {
		if ( ! liveRegion ) {
			liveRegion = document.createElement( 'div' );
			liveRegion.setAttribute( 'aria-live', 'polite' );
			liveRegion.setAttribute( 'role', 'status' );
			liveRegion.className = 'moforcoupon-sr-only';
			document.body.appendChild( liveRegion );
		}
		// Clear then set so an identical message re-announces.
		liveRegion.textContent = '';
		window.setTimeout( function () {
			liveRegion.textContent = message;
		}, 50 );
	}

	function flash( button ) {
		var original = button.textContent;
		var copied = button.getAttribute( 'data-copied' ) || '已複製';
		button.classList.add( 'is-copied' );
		button.textContent = copied;
		announce( ( button.getAttribute( 'aria-label' ) || copied ) + ' — ' + copied );
		window.setTimeout( function () {
			button.classList.remove( 'is-copied' );
			button.textContent = original;
		}, 1500 );
	}

	function copy( code, button ) {
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard.writeText( code ).then(
				function () {
					flash( button );
				},
				function () {
					legacyCopy( code, button );
				}
			);
		} else {
			legacyCopy( code, button );
		}
	}

	function legacyCopy( code, button ) {
		var field = document.createElement( 'textarea' );
		field.value = code;
		field.setAttribute( 'readonly', '' );
		field.style.position = 'absolute';
		field.style.left = '-9999px';
		document.body.appendChild( field );
		field.select();
		try {
			document.execCommand( 'copy' );
			flash( button );
		} catch ( e ) {
			/* no-op */
		}
		document.body.removeChild( field );
	}

	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '.moforcoupon-coupon__copy' );
		if ( ! button ) {
			return;
		}
		event.preventDefault();
		copy( button.getAttribute( 'data-code' ) || '', button );
	} );
} )();
