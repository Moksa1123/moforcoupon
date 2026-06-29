/* global jQuery */
( function ( $ ) {
	'use strict';

	function toggleType() {
		var isBogo = $( '#discount_type' ).val() === 'moforcoupon_bogo';
		// Hide/show the BOGO tab in BOTH the native coupon-data tabs and our
		// consolidated-metabox tabs — both carry the moforcoupon_bogo_tab class.
		$( 'li.moforcoupon_bogo_tab' ).toggle( isBogo );
		// When not a BOGO coupon, also hide its panel (in case it was the active one).
		if ( ! isBogo ) {
			$( '#moforcoupon_bogo' ).hide();
		}
	}

	function toggleRows() {
		var free = $( '#_moforcoupon_bogo_reward_mode' ).val() === 'free';
		$( '#_moforcoupon_bogo_reward_value' ).closest( '.form-field' ).toggle( ! free );

		var repeat = $( '#_moforcoupon_bogo_deal_mode' ).val() === 'repeat';
		$( '#_moforcoupon_bogo_repeat_limit' ).closest( '.form-field' ).toggle( repeat );
	}

	$( function () {
		$( '#discount_type' ).on( 'change', toggleType );
		$( '#_moforcoupon_bogo_reward_mode' ).on( 'change', toggleRows );
		$( '#_moforcoupon_bogo_deal_mode' ).on( 'change', toggleRows );
		toggleType();
		toggleRows();
	} );
}( jQuery ) );
