jQuery("#tabs").tabs();

// Immediately show the value of the input field in the example
jQuery(document).on('keyup', "#email_title", function () {
	var value = jQuery("#email_title").val();
	jQuery("#example_email_title").html(value);
});

// Immediately show the value of the input field in the example
jQuery(document).on('keyup', "#track_trace_text", function () {
	var value = jQuery("#track_trace_text").val();
	jQuery("#example_track_trace_text").html(value);
});


jQuery(document).on('click', '#btn_save_settings', function () {
	jQuery('.notice').hide();
	var postvars = { action: 'print_and_ship_admin_save_settings' };

	jQuery('#result').html('Please wait...');

	jQuery('#print_and_ship_config :input').not('#btn_save_settings').each(function () {
		var myObj = {};

		if (jQuery(this).is(':checkbox')) {
			if (jQuery(this).is(':checked')) {
				myObj[this.name] = 'yes';
			} else {
				myObj[this.name] = 'no';
			}

			jQuery.extend(postvars, myObj);
		} else if (jQuery(this).is('select')) {
			if (jQuery('option:selected', this)) {
				myObj[this.name] = this.value;
				jQuery.extend(postvars, myObj);
			}
		} else {
			myObj[this.name] = this.value;
			jQuery.extend(postvars, myObj);
		}
	});

	console.log(postvars);

	jQuery.post(ajaxurl, postvars, function (data) {
		jQuery('#result').html(data.message);

		if (data.result === 'OK') {
			setTimeout(function () {
				jQuery('#result').html('');
			}, 3500);

			if (!data.auth) {
				jQuery('.notice').show();
			}
		}
	});
});