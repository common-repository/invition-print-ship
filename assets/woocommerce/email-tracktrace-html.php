<?php
/**
 * @author Jacco Drabbe <jacco@qurl.nl>
 * @copyright 2017 Printeers
 * @version $Id$
 **/

namespace PrintAndShip;

defined('ABSPATH') or die('No script kiddies please!');

/**
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action('woocommerce_email_header', $email_heading, $email);

// Get track_and_trace_text if available. If not, use default text
$track_and_trace_text = get_option('print_and_ship_track_trace_text');

$addressee = "";
if (trim($customer_name) != "") {
    $addressee = " to " . $customer_name;
}

if (trim($track_and_trace_text) == "") {
    $track_and_trace_text = "Your order has been shipped".$addressee.". Track & Trace information can be found here: ";
}

echo "<p>" . $track_and_trace_text . "</p><p>" . $track_trace . "</p>";

/**
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action('woocommerce_email_footer', $email);
