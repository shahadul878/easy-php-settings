/* global jQuery, easyPhpSettingsOfferNotice */
( function ( $ ) {
	'use strict';

	function pad( value ) {
		return value < 10 ? '0' + value : String( value );
	}

	function formatRemaining( seconds ) {
		if ( seconds <= 0 ) {
			return easyPhpSettingsOfferNotice.expiredLabel;
		}

		var days = Math.floor( seconds / 86400 );
		var hours = Math.floor( ( seconds % 86400 ) / 3600 );
		var minutes = Math.floor( ( seconds % 3600 ) / 60 );

		if ( days > 0 ) {
			return easyPhpSettingsOfferNotice.daysHoursLabel
				.replace( '%1$d', days )
				.replace( '%2$d', hours );
		}

		return easyPhpSettingsOfferNotice.hoursMinutesLabel
			.replace( '%1$d', hours )
			.replace( '%2$d', minutes );
	}

	$( function () {
		var $notice = $( '#easy-php-settings-offer-notice' );
		if ( ! $notice.length || 'undefined' === typeof easyPhpSettingsOfferNotice ) {
			return;
		}

		var settings = easyPhpSettingsOfferNotice;
		var $timer = $notice.find( '[data-offer-countdown]' );
		var expiresAt = parseInt( $notice.data( 'expires-at' ), 10 );

		function hideNotice() {
			$notice.slideUp( 180, function () {
				$notice.remove();
			} );
		}

		function sendAction( action ) {
			return $.post( settings.ajaxUrl, {
				action: 'plugin_tracker_offer_notice_action',
				offer_action: action,
				offer_id: settings.offerId,
				nonce: settings.nonce,
			} );
		}

		function updateCountdown() {
			if ( ! $timer.length || ! expiresAt ) {
				return;
			}

			var remaining = expiresAt - Math.floor( Date.now() / 1000 );
			$timer.text( formatRemaining( remaining ) );

			if ( remaining <= 0 ) {
				hideNotice();
			}
		}

		updateCountdown();
		window.setInterval( updateCountdown, 60000 );

		$notice.on( 'click', '[data-offer-action]', function ( event ) {
			event.preventDefault();
			var action = $( this ).data( 'offer-action' );
			if ( ! action ) {
				return;
			}

			if ( 'view' === action ) {
				sendAction( 'view' );
				if ( settings.offerUrl ) {
					window.open( settings.offerUrl, '_blank', 'noopener,noreferrer' );
				}
				hideNotice();
				return;
			}

			sendAction( action ).always( hideNotice );
		} );
	} );
} )( jQuery );
