<?php
/**
 * @author Mike Sies <support@printeers.com>
 * @copyright 2019 Printeers
 * @version $Id$
 **/

namespace PrintAndShip;

defined('ABSPATH') or die('No script kiddies please!');

/**
 * uploadPrintImage() Upload the print image and connect it to the product
 *
 * @return array
 */
function uploadPrintImage($product_id)
{

    // No print image was uploaded
    if (!isset($_FILES['print_and_ship_print_image'])) {
        return array(false, 'Could not find print image');
    }

    // The product ID is not numeric
    if (!is_numeric($product_id)) {
        return array(false, 'Could not find the product ID');
    }

    // Check the uploaded image on mime type
    $mime = \mime_content_type($_FILES['print_and_ship_print_image']['tmp_name']);
    if ($mime === false) {
        return array(false, "Could not check MIME type");
    }

    if ($mime != ("image/png") && $mime != ("image/jpeg")) {
        return array(false, 'Please select a valid JPEG or PNG file');
    }
    
    $uploaded = media_handle_upload('print_and_ship_print_image', $product_id);
    
    // Error checking using WP functions
    if (!is_numeric($uploaded)) {
        return array(false, $uploaded->get_error_message());
    }
    
    // Connect the image to the created product
    update_post_meta($product_id, 'print_and_ship_print_image', $uploaded);
    
    return array(true, '');
}
    
    
$product_id = (int) $_POST['product_id'];
$result = uploadPrintImage($product_id);
    
// Something went wrong, reverse post creation and report to user
if ($result[0] !== true) {
    // Remove the post because it was half done
    if (!wp_delete_post($product_id, true)) {
        debuglog('Could not delete post ' . $product_id);
    }

    $title = 'Something went wrong';
    $status = $result[1];

    // Product was created succesfully
} else {
    $title = 'Your product was succesfully created!';
    $status = 'The product was added to WooCommerce and the required settings are done. What to do next?';
    $status .= '<div class="w20 left"><a href="?page=print-and-ship-update-products" class="invitionButton">Generate the variations</a></div>';
    $status .= '<div class="w20 left"><a href="post.php?post=' . $product_id . '&action=edit" class="invitionButton">Enter more product details</a></div>';
}

// Get the full template
$fields = array(
    'title' => $title,
    'status' => $status,
);

$template = file_get_contents(PRINT_AND_SHIP_TEMPLATES . '/create-a-product/variable-finish.html');

echo applyTemplate($template, $fields);
