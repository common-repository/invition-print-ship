/**
 * @file Process print image actions on orders
 * @author Mike Sies <support@printeers.com>
 * @version 1.0
 */

/**
 * Post new print image for order item through Ajax
 */
jQuery(document).on('click', 'button[name=replace_print_image]', function (e) {
    e.preventDefault();
    e.stopPropagation();

    var orderID = this.id.split(/_/)[1];
    var itemID = this.id.split(/_/)[2];
    var newPrintImage = jQuery('#print_and_ship_print_image_' + itemID).prop('files');

    jQuery('#result_' + itemID).html('Please wait...');

    if (newPrintImage[0]) {
        var reader = new FileReader();

        reader.onload = function (thefile) {
            var postdata = {
                action: 'print_and_ship_admin_order_print_image',
                image: thefile.target.result,
                item_id: itemID,
                order_id: orderID,
            };
            var image_test = /^data:image/;

            if (image_test.test(postdata.image)) {
                jQuery.post(myAJAX.ajaxurl, postdata, function (response) {
                    if (!response) {
                        jQuery('#result_' + itemID).html('Something went wrong');
                    } else if (response.result !== true) {
                        jQuery('#result_' + itemID).html(response.message);
                    } else {
                        location.reload();
                    }
                });
            } else {
                jQuery('#result_' + itemID).html('The image is invalid');
            }
        };

        reader.readAsDataURL(newPrintImage[0]);
    } else {
        jQuery('#result_' + itemID).html('Please select an image');
    }
});
