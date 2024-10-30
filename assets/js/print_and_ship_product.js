jQuery(document).ready(function () {
	jQuery('.single_add_to_cart_button').hide();

	var urlParams = new URLSearchParams(window.location.search);
	if (urlParams.has('cart_item')) {
		jQuery('.quantity').remove();
	}
});

jQuery(document).on('change', '#image', function (e) {
	jQuery('#image-result').html('');

	if (jQuery(this).val().length > 0) {
		var files = e.target.files;
		var file = files[0];
		var reader = new FileReader();

		reader.onload = function (thefile) {
			var postdata = {
				action: 'print_and_ship_check_image_upload',
				image: thefile.target.result,
				post_ID: jQuery('button[name="add-to-cart"]').val()
			};
			var image_test = /^data:image/;

			if (image_test.test(postdata.image)) {
				jQuery('.single_add_to_cart_button').show();

				jQuery.post(myAJAX.ajaxurl, postdata, function (response) {
					if (typeof (response.valid) === 'boolean' && !response.valid) {
						jQuery('#image-result').html('Er is een onbekende fout opgetreden. Probeer het opnieuw.');
					} else {
						jQuery('#image-result').html(response.valid);
					}
				});
			} else {
				jQuery('#image-result').html('Dit is geen geldige afbeelding');
			}
		};

		reader.readAsDataURL(file);
	} else {
		jQuery('.single_add_to_cart_button').hide();
	}
});