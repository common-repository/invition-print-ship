jQuery(document).ready(function () {

    // Validate step 2
    jQuery('#submit_step_2').click(function (event) {
        var valid = true;

        jQuery('.error').remove();

        // Product name was not entered
        if (!jQuery('#product_name').val()) {
            jQuery('#product_name_div').append('<p class="red error">Please enter a product name!</p>');
            valid = false;
        }

        // No brand was selected
        if (!jQuery('#pa_device_brand').val()) {
            jQuery('#pa_device_brand_div').append('<p class="red error">Please select at least one value!</p>');
            valid = false;
        }

        // No model was selected
        if (!jQuery('#pa_device_model').val()) {
            jQuery('#pa_device_model_div').append('<p class="red error">Please select at least one value!</p>');
            valid = false;
        }

        // No case type & colour was selected
        if (!jQuery('#pa_case_type_colour').val()) {
            jQuery('#pa_case_type_colour_div').append('<p class="red error">Please select at least one value!</p>');
            valid = false;
        }

        if (!valid) {
            event.preventDefault();
        }
    });

    // Validate step 3
    jQuery('#submit_step_3').click(function (event) {
        var filename = jQuery("#print_and_ship_print_image").val();
        var extension = filename.replace(/^.*\./, '');
        var wanted = ["jpg", "jpeg", "png"];
        var valid = true;

        jQuery('.error').remove();

        // Was the image selected?
        if (!jQuery('#print_and_ship_print_image').val()) {
            jQuery('#print_and_ship_print_image_div').append('<p class="red error">Please select a print image!</p>');
            valid = false;
        }

        // The image does not have an extension
        if (extension == filename && valid) {
            jQuery('#print_and_ship_print_image_div').append('<p class="red error">Please select an image file!</p>');
            valid = false;
        }

        // The image has the wrong file extension
        if (!wanted.includes(extension.toLowerCase()) && valid) {
            jQuery('#print_and_ship_print_image_div').append('<p class="red error">Please select a JPEG or PNG file!</p>');
            valid = false;
        }

        if (!valid) {
            event.preventDefault();
        }

    });
});

