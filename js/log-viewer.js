/**
 * Debug log viewer: filter grouped entries by type.
 */
( function () {
	'use strict';

	var viewer = document.querySelector( '[data-eps-log]' );
	if ( ! viewer ) {
		return;
	}

	var filters = viewer.querySelectorAll( '.eps-log__filter' );
	var items = viewer.querySelectorAll( '.eps-log__item' );

	viewer.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '.eps-log__filter' );
		if ( ! button ) {
			return;
		}
		var level = button.getAttribute( 'data-level' );

		filters.forEach( function ( filter ) {
			filter.setAttribute( 'aria-pressed', filter === button ? 'true' : 'false' );
		} );
		items.forEach( function ( item ) {
			item.hidden = 'all' !== level && item.getAttribute( 'data-level' ) !== level;
		} );
	} );
}() );
