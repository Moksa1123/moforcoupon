/* global jQuery */
( function ( $ ) {
	'use strict';

	function toggleType() {
		var isMix = $( '#discount_type' ).val() === 'moforcoupon_mixmatch';
		$( 'li.moforcoupon_mixmatch_tab' ).toggle( isMix );
		if ( ! isMix ) {
			$( '#moforcoupon_mixmatch' ).hide();
		}
	}

	function toggleRows() {
		var repeat = $( '#_moforcoupon_mixmatch_deal_mode' ).val() === 'repeat';
		$( '#_moforcoupon_mixmatch_repeat_limit' ).closest( '.form-field' ).toggle( repeat );
	}

	$( function () {
		$( '#discount_type' ).on( 'change', toggleType );
		$( '#_moforcoupon_mixmatch_deal_mode' ).on( 'change', toggleRows );
		toggleType();
		toggleRows();
	} );
}( jQuery ) );
