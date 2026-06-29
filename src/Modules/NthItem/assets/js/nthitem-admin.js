/* global jQuery */
( function ( $ ) {
	'use strict';

	function toggleType() {
		var isNth = $( '#discount_type' ).val() === 'moforcoupon_nth_item';
		$( 'li.moforcoupon_nth_item_tab' ).toggle( isNth );
		if ( ! isNth ) {
			$( '#moforcoupon_nth_item' ).hide();
		}
	}

	function toggleRows() {
		var free = $( '#_moforcoupon_nth_reward_mode' ).val() === 'free';
		$( '#_moforcoupon_nth_reward_value' ).closest( '.form-field' ).toggle( ! free );

		var repeat = $( '#_moforcoupon_nth_deal_mode' ).val() === 'repeat';
		$( '#_moforcoupon_nth_repeat_limit' ).closest( '.form-field' ).toggle( repeat );
	}

	$( function () {
		$( '#discount_type' ).on( 'change', toggleType );
		$( '#_moforcoupon_nth_reward_mode' ).on( 'change', toggleRows );
		$( '#_moforcoupon_nth_deal_mode' ).on( 'change', toggleRows );
		toggleType();
		toggleRows();
	} );
}( jQuery ) );
