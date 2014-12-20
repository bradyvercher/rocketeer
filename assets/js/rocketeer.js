(function( window, $, undefined ) {
	'use strict';

	// Prevent disabled checkboxes from being toggled.
	$.propHooks.checked = {
		set: function ( el, value ) {
			if ( el.disabled ) {
				return null;
			}

			if ( el.checked !== value ) {
				el.checked = value;
			}
		}
	};
})( this, jQuery );
