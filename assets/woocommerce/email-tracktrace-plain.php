<?php
/**
 * @author Jacco Drabbe <jacco@qurl.nl>
 * @copyright 2017 Printeers
 * @version $Id$
 **/

namespace PrintAndShip;

defined('ABSPATH') or die('No script kiddies please!');

echo "= " . $email_heading . " =\n\n";

// Get track_and_trace_text if available. If not, use default text
$track_and_trace_text = get_option('print_and_ship_track_trace_text');

$addressee = "";
if (trim($customer_name) != "") {
    $addressee = " to " . $customer_name;
}

if (trim($track_and_trace_text) == "") {
    $track_and_trace_text = "Your order has been shipped".$addressee.". Track & Trace information can be found here: ";
}

echo $track_and_trace_text;
echo "\n\n";
echo $track_trace;

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

echo apply_filters('woocommerce_email_footer_text', get_option('woocommerce_email_footer_text'));
