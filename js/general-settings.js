/**
 * General Settings tab: presets, "use recommended" buttons, unsaved-change tracking.
 *
 * Depends on window.easy_php_settingsAdminVars (presets + i18n strings).
 */
( function () {
	'use strict';

	var form = document.getElementById( 'eps-settings-form' );
	if ( ! form ) {
		return;
	}

	var vars = window.easy_php_settingsAdminVars || {};
	var i18n = vars.i18n || {};
	var savebar = form.querySelector( '.eps-savebar' );
	var savebarText = form.querySelector( '.eps-savebar__text' );
	var presetStatus = form.querySelector( '.eps-presets__status' );
	var customIni = document.getElementById( 'easy_php_settings_custom_php_ini' );
	var FLASH_MS = 1200;
	var isSubmitting = false;

	/* ─── Custom INI editor (CodeMirror is optional) ─── */

	function codeMirror() {
		if ( ! customIni || ! customIni.nextElementSibling ) {
			return null;
		}
		var wrapper = customIni.nextElementSibling;
		return wrapper.classList.contains( 'CodeMirror' ) && wrapper.CodeMirror ? wrapper.CodeMirror : null;
	}

	function setCustomIni( value ) {
		var cm = codeMirror();
		if ( cm ) {
			cm.setValue( value );
			cm.save();
		} else if ( customIni ) {
			customIni.value = value;
		}
	}

	/* ─── Change tracking ─── */

	function trackedFields() {
		return Array.prototype.slice.call( form.querySelectorAll( '.eps-input' ) ).concat( customIni ? [ customIni ] : [] );
	}

	var initial = {};
	trackedFields().forEach( function ( field ) {
		initial[ field.id ] = field.value;
	} );

	function format( template, values ) {
		return ( template || '' ).replace( /%(\d)\$[sd]/g, function ( match, index ) {
			return values[ index - 1 ];
		} ).replace( /%[sd]/, values[ 0 ] );
	}

	function refreshState() {
		var changed = 0;

		trackedFields().forEach( function ( field ) {
			var isChanged = field.value !== initial[ field.id ];
			var row = field.closest( '.eps-row' );
			if ( isChanged ) {
				changed++;
			}
			if ( row ) {
				row.classList.toggle( 'is-changed', isChanged );
				var useRec = row.querySelector( '.eps-use-rec' );
				if ( useRec ) {
					useRec.hidden = field.value === useRec.getAttribute( 'data-value' );
				}
			}
		} );

		savebar.setAttribute( 'data-state', changed ? 'dirty' : 'clean' );
		if ( ! changed ) {
			savebarText.textContent = i18n.noChanges || 'No unsaved changes';
		} else if ( 1 === changed ) {
			savebarText.textContent = i18n.oneChange || '1 unsaved change';
		} else {
			savebarText.textContent = format( i18n.manyChanges || '%d unsaved changes', [ changed ] );
		}
		return changed;
	}

	function flash( field ) {
		var row = field.closest( '.eps-row' );
		if ( ! row ) {
			return;
		}
		row.classList.remove( 'is-flashing' );
		// Force a reflow so the animation restarts.
		void row.offsetWidth;
		row.classList.add( 'is-flashing' );
		window.setTimeout( function () {
			row.classList.remove( 'is-flashing' );
		}, FLASH_MS );
	}

	form.addEventListener( 'input', refreshState );

	var cm = codeMirror();
	if ( cm ) {
		cm.on( 'change', function () {
			cm.save();
			refreshState();
		} );
	}

	var advanced = form.querySelector( '.eps-advanced' );
	if ( advanced ) {
		advanced.addEventListener( 'toggle', function () {
			var editor = codeMirror();
			if ( advanced.open && editor ) {
				editor.refresh();
			}
		} );
	}

	/* ─── "Use recommended" ─── */

	form.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '.eps-use-rec' );
		if ( ! button ) {
			return;
		}
		var field = document.getElementById( button.getAttribute( 'data-target' ) );
		if ( ! field ) {
			return;
		}
		field.value = button.getAttribute( 'data-value' );
		field.dispatchEvent( new Event( 'input', { bubbles: true } ) );
		flash( field );
		field.focus();
	} );

	/* ─── Presets ─── */

	form.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '.eps-preset' );
		if ( ! button || ! vars.presets ) {
			return;
		}
		var preset = vars.presets[ button.getAttribute( 'data-preset' ) ];
		if ( ! preset ) {
			return;
		}

		var filled = 0;
		Object.keys( preset ).forEach( function ( key ) {
			if ( 'name' === key || 'description' === key ) {
				return;
			}
			if ( 'custom_php_ini' === key ) {
				setCustomIni( preset[ key ] );
				filled++;
				return;
			}
			var field = form.querySelector( 'input[name="easy_php_settings_settings[' + key + ']"]' );
			if ( field ) {
				field.value = preset[ key ];
				flash( field );
				filled++;
			}
		} );

		form.querySelectorAll( '.eps-preset' ).forEach( function ( other ) {
			other.setAttribute( 'aria-pressed', other === button ? 'true' : 'false' );
		} );

		refreshState();
		if ( presetStatus ) {
			presetStatus.textContent = format(
				i18n.presetApplied || '%1$s preset filled %2$d fields. Review them, then save.',
				[ preset.name, filled ]
			);
		}
	} );

	/* ─── Leave-page guard ─── */

	form.addEventListener( 'submit', function () {
		isSubmitting = true;
	} );

	window.addEventListener( 'beforeunload', function ( event ) {
		if ( isSubmitting || ! refreshState() ) {
			return;
		}
		event.preventDefault();
		event.returnValue = '';
	} );

	refreshState();
}() );
