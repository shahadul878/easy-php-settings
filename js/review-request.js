/* global jQuery, easyPhpSettingsReview */
( function ( $ ) {
	'use strict';

	$( function () {
		var $notice = $( '#easy-php-settings-review-notice' );
		if ( ! $notice.length || 'undefined' === typeof easyPhpSettingsReview ) {
			return;
		}

		var settings = easyPhpSettingsReview;

		function hideNotice() {
			$notice.slideUp( 180, function () {
				$notice.remove();
			} );
		}

		function sendAction( action ) {
			return $.post( settings.ajaxUrl, {
				action: 'easy_php_settings_review_action',
				review_action: action,
				nonce: settings.nonce,
			} );
		}

		$notice.on( 'click', '[data-review-action]', function ( event ) {
			event.preventDefault();
			var action = $( this ).data( 'review-action' );
			if ( ! action ) {
				return;
			}

			if ( 'rate' === action ) {
				sendAction( 'rate' );
				window.open( settings.reviewUrl, '_blank', 'noopener,noreferrer' );
				hideNotice();
				return;
			}

			sendAction( action ).always( hideNotice );
		} );
	} );
} )( jQuery );
