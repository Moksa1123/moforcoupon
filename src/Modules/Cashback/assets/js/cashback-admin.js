/* global jQuery */
/**
 * Shows / hides the "回饋金 / 點數" coupon tab depending on the selected discount type — the
 * tab is only relevant for moforcoupon_cashback coupons (mirrors the BOGO tab toggle).
 */
( function ( $ ) {
	'use strict';

	function toggleType() {
		var isCashback = $( '#discount_type' ).val() === 'moforcoupon_cashback';
		$( 'li.moforcoupon_cashback_tab' ).toggle( isCashback );
		if ( ! isCashback ) {
			$( '#moforcoupon_cashback' ).hide();
		}
	}

	$( function () {
		$( '#discount_type' ).on( 'change', toggleType );
		toggleType();
	} );
}( jQuery ) );
