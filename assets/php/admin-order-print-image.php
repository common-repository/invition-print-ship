<?php
/**
 * @version $Id$
 * @copyright 2019 Printeers
 * @author Mike Sies
 *
 * This file is called for each item on the WooCommerce order page
 **/

namespace PrintAndShip;

defined('ABSPATH') or die('No script kiddies please!');

// Is the product a Printeers print item?
if (method_exists($item, "get_product_id") 
    && (isPrintItem($item->get_product_id()) || isPrintItem($item->get_variation_id()))
) {
    $printImageURL = adminGetPrintImageURL($item);
    $order_id = $item->get_order_id();
    $order = wc_get_order($order_id);
    $status = $order->get_status();

    // Only display a form if the order is not yet placed at Printeers
    if (!$order->get_meta('print_and_ship_order_reference')
        && $status != 'completed'
        && $status != 'cancelled'
    ) {
        $template = file_get_contents(PRINT_AND_SHIP_TEMPLATES . 'admin-order-print-image-form.html');
    } else {
        $template = file_get_contents(PRINT_AND_SHIP_TEMPLATES . 'admin-order-print-image.html');
    }

    $fields = array(
        'printImageURL'  => $printImageURL,
        'itemId'         => $item_id,
        'orderId'        => $item->get_order_id(),
        'ajaxurl'        => admin_url('admin-ajax.php'),
    );

    echo applyTemplate($template, $fields);
}
