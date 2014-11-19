var Yoast_Plugin_Toggler = {

	toggle_plugin: function (event) {
		'use strict';

		// Stop bubbling up the event
		event.stopPropagation();

		var link = jQuery(this);
		var plugin = link.attr('data-plugin');
		var nonce  = link.attr('data-nonce');

		jQuery.getJSON(
			ajaxurl,
			{
				action     : 'toggle_version',
				ajax_nonce: nonce,
				plugin     : plugin

			},
			function (response) {
				if (response.activated_version !== undefined) {
					link.html(response.activated_version);

					jQuery.get(
						ajaxurl,
						{
							action           : 'activate_toggled_version',
							ajax_nonce      : nonce,
							plugin           : plugin,
							activated_version: response.activated_version
						},
						function (response) {
							// do nothing
						}
					);
				}

			}
		);

		return true;
	},

	show_toggler: function (event) {
		'use strict';

		// Stop bubbling up the event
		event.stopPropagation();

		if (jQuery(this).hasClass('Yoast-Toggle-Active')) {
			var right = -235;
		} else {
			var right = 10;
		}

		jQuery(this).animate(
			{right: right},
			500,
			function () {
				jQuery(this).toggleClass('Yoast-Toggle-Active');
			}
		);
	}
};

jQuery(document).ready(
	function () {
		jQuery('.Yoast-Toggle').bind('click', Yoast_Plugin_Toggler.show_toggler);
		jQuery('.Yoast-Toggle a').bind('click', Yoast_Plugin_Toggler.toggle_plugin);
	}
);