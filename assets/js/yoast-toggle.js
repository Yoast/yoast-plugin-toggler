var Yoast_Plugin_Toggler = {
	toggle_plugin: function( group, plugin, nonce ) {
		"use strict";

		jQuery.getJSON(
			ajaxurl,
			{
				action: "toggle_version",
				ajax_nonce: nonce,
				group: group,
				plugin: plugin
			},
			function( response ) {
				if ( response.activated_version !== undefined ) {
					window.history.go(0);
				}
			}
		);

		return true;
	}
};
