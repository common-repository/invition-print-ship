<?php
/**
 * @author Jacco Drabbe <jacco@qurl.nl>
 * @copyright 2017 Printeers
 * @version $Id: class-ipp.php 736 2018-09-22 08:20:04Z jacco $
 **/

namespace PrintAndShip;

defined('ABSPATH') or die('No script kiddies please!');

if (! class_exists('Woo')) {
    require_once PRINT_AND_SHIP_CLASSES . '/class-woo.php';
}

/**
 * \PrintAndShip\IPP
 *
 * @author Jacco Drabbe <jacco@qurl.nl>
 * @copyright 2017 Printeers
 * @access public
 * @package PRINT_AND_SHIP
 */
class IPP
{
    private $_baseurl;
    private $_woo;

    /**
     * IPP constructor.
     */
    public function __construct()
    {
        $this->_baseurl = (get_option('print_and_ship_test_mode') == 'yes' ) ? PRINT_AND_SHIP_TEST_SERVER_URL : PRINT_AND_SHIP_PROD_SERVER_URL;
        $this->_baseurl = apply_filters('print_and_ship_server_base_url', $this->_baseurl);

        $this->_woo = new \PrintAndShip\Woo();
    }

    /**
     * Get stock item by Printeers SKU
     *
     * @param string $sku Printeers SKU
     * 
     * @return object|null
     */
    public function getStockItem($sku)
    {
        $list = $this->getStockList();
        foreach ($list->items as $item) {
            if ($item->sku == $sku) {
                return $item;
            }
        }

        return null;
    }

    /**
     * Get stock
     *
     * @return object
     */
    public function getStockList()
    {
        debuglog("Fetching latest stock using Printeers API.");
        $data = $this->_requestDataFromServer("stock");
        if ($data===false) {
            debuglog("Error: failed to fetch stock from invition API");
            return false;
        }

        return $data;
    }

    /**
     * Get stock attributes
     *
     * @return object
     */
    public function getAttributesList()
    {
        debuglog("Fetching latest stock-attributes using Printeers API.");
        $data = $this->_requestDataFromServer("stock-attributes");
        if ($data===false) {
            debuglog("Error: failed to fetch stock attributes from invition API");
            return false;
        }

        return $data;
    }

    /**
     * Request data from Printeers API
     *
     * @param string $path Path for REST
     * @param array  $args Extra arguments
     * 
     * @return string|bool
     */
    private function _requestDataFromServer($path, $args = array())
    {
        $url = $this->_baseurl . $path;
        $api_user = get_option('print_and_ship_api_user');
        $api_key = get_option('print_and_ship_api_key');

        if (empty($api_user) || empty($api_key)) {
            debuglog('No API user or key. Refusing to start working');
            return false;
        }

        $default_args = array(
            'headers' => array(
                'x-ipp-partnercode' => $api_user,
                'x-ipp-apikey'      => $api_key
            )
        );
        $args = array_merge($default_args, $args);
        $response = wp_remote_get($url, $args);
        debuglog('Requesting data using URL: ' . $url);

        if (is_wp_error($response)) {
            debuglog('Error getting data from Printeers API - Error: ' . $response->get_error_message());
            return false;
        }

        $data = json_decode($response['body']);
        if ($data===null) {
            debuglog("Error: failed to decode data from server.");
            return false;
        }

        return $data;
    }

    /**
     * Process callback received from Printeers
     *
     * @return void
     */
    public function processCallback()
    {
        global $wpdb;

        $order_id = 0;
        foreach ($_GET as $key => $value) {
            if ($order_id == 0) {
                switch ($key) {
                case 'order_reference':
                    $query = "SELECT post_id FROM " . $wpdb->postmeta . " WHERE meta_key = 'print_and_ship_order_reference' AND meta_value = '" . sanitize_text_field($value) . "'";
                    $order_id = $wpdb->get_var($query);
                    break;
    
                case 'partner_reference':
                    $order_id = (!empty($_GET[$value])) ? sanitize_text_field($_GET[$value]) : 0;
                    break;
                }
            }
        }

        // Is the ID still 0? Then the order is not found
        if ($order_id == 0) {
            debuglog('Callback received but order ID not found');
        }

        // We found an order, update the status
        $this->updateStatus($order_id);
    }

    /**
     * Send order to Printeers
     *
     * @param array $order Order data
     * 
     * @return bool
     */
    public function sendOrder($order)
    {
        $path = 'orders';
        $responseString = $this->_sendToServer($path, $order);

        if ($responseString === false) {
            debuglog('Could not send order' . $order["partner_reference"]);
    
            return false;
        }

        $responseArray = json_decode($responseString);

        // Is the response valid JSON?
        if ($responseArray === false) {
            debuglog('Could not decode JSON response.');
            debuglog('Response was ' . $responseString);
            
            return false;
        }
        
        // Did we receive an order reference?
        if (!property_exists($responseArray, 'reference')) {

            // Add a note in the order notes
            $note = 'Error sending order to Printeers';
            if (property_exists($responseArray, 'errorMessage')) {
                $note .= ': ' . $responseArray->errorMessage;
            }

            debuglog($responseString);
    
            $this->_woo->addNote((int) $order['partner_reference'], $note);
    
            // Notify admin through email
            $subject = 'Error sending order to Printeers';
            $message = 'Error sending order ' . $order['partner_reference']
                . ' to Printeers. The order has been put on hold. ' 
                . ' Please take manual action. The error was:'
                . ' <br /><br />' . $responseArray->errorMessage;
            wp_mail(get_option('admin_email'), $subject, $message);

            return false;
        }
        
        debuglog('I have commited a valid order: ' . $responseArray->reference);

        $metaReference = add_post_meta(
            $order['partner_reference'], 
            'print_and_ship_order_reference', 
            $responseArray->reference, 
            true
        );

        if (!$metaReference) {
            debuglog('Could not add order reference to order' . $order['partner_reference']);

            if (metadata_exists(
                'post', 
                $order['partner_reference'], 
                'print_and_ship_order_reference'
            )
            ) {
                // If the metadata already exists, we want to log why because it should not be possible
                $storedValue = get_post_meta($order['partner_reference'], 'print_and_ship_order_reference');
                debuglog('print_and_ship_order_reference already exists with value ' . $storedValue);
            }

            // Do not return false here because the order was placed
            // Other logic still needs to be done
        }

        // Changing structure a little for later
        $order_lines = array();
        foreach ($order['lines'] as $line) {
            $line['partial'] = 0;   // Adding partial status

            $sku = $line['item_sku'];
            $order_lines[$sku] = $line;
        }

        add_post_meta($order['partner_reference'], 'print_and_ship_order_lines', $order_lines);

        $note = 'Order has been sent to Printeers. Order reference: ' . $responseArray->reference;
        $this->_woo->addNote((int) $order['partner_reference'], $note);

        return true;
    }

    /**
     * Request a shipping quote from Printeers
     *
     * @param array $order Order data
     * 
     * @return bool
     */
    public function requestQuote($order)
    {
        $path = 'quote';
        $responseString = $this->_sendToServer($path, $order);

        if ($responseString === false) {
            debuglog('Printeers server did not return a quote');
    
            return false;
        }

        $responseObject = json_decode($responseString);

        // Is the response valid JSON?
        if ($responseObject === false) {
            debuglog('Could not decode JSON response.');
            debuglog('Response was ' . $responseString);
            
            return false;
        }

        // Does the response contain any shipping options?
        if (!property_exists($responseObject, 'shipping_options')) {
            debuglog('Received an invalid response from the Quote API. Response:');
            debuglog(print_r($responseObject, true));

            return false;
        }

        // Are there shipping options available?
        if (count($responseObject->shipping_options) == 0) {
            return false;
        }
        
        return $responseObject;
    }

    /**
     * Send data to IPP server
     *
     * @param string $path Path for REST
     * @param array  $data Data
     * @param array  $args Extra arguments
     * 
     * @return bool
     */
    private function _sendToServer($path, $data, $args = array())
    {
        $url = $this->_baseurl . $path;
        $api_user = get_option('print_and_ship_api_user');
        $api_key = get_option('print_and_ship_api_key');

        if (empty($api_user) && empty($api_key)) {
            debuglog('Could not send request, Partner code or API key missing');

            return false;
        }

        $json = json_encode($data);

        $default_args = array(
            'headers' => array(
                'x-ipp-partnercode' => $api_user,
                'x-ipp-apikey'      => $api_key,
            ),
            'body'    => $json,
            'timeout' => 20,
        );
        $args = array_merge($default_args, $args);
        $response = wp_remote_post($url, $args);

        if (is_wp_error($response)) {
            debuglog('Error posting data using URL ' . $url . ' - Error: ' . $response->get_error_message());

            return false;
        }

        return $response['body'];
    }

    /**
     * Save and send Track and Trace code
     *
     * @param int    $order_id  Order ID
     * @param object $shipments Shipment info from IPP server
     * 
     * @return void
     */
    private function _sendTrackTrace($order_id, $shipments)
    {
        // Get the email class
        if (!class_exists('\PrintAndShip\EmailTrackTrace')) {
            include_once PRINT_AND_SHIP_CLASSES . 'class-email-tracktrace.php';
        }

        $email = new \PrintAndShip\EmailTrackTrace();

        // Send a mail per shipment for now. We might combine this in a later update
        foreach ($shipments as $shipment) {
            if (!property_exists($shipment, "track_and_trace_code")
                || !property_exists($shipment, "track_and_trace_url")
            ) {
                // Don't send a tracking mail for shipments without tracking
                continue;
            }

            $note = 'Track & Trace URL: ' . $shipment->track_and_trace_url;
            $this->_woo->addNote($order_id, $note);

            $email->setTrackTraceUrl($shipment->track_and_trace_url);

            $order = wc_get_order($order_id);
            $shipping_first_name = $order->get_shipping_first_name();
            $shipping_last_name  = $order->get_shipping_last_name();
            $email->setCustomerName($shipping_first_name . " " . $shipping_last_name);

            $email->trigger($order_id);
        }
    }

    /**
     * Update status of an order
     *
     * @param int $order_id Order ID
     * 
     * @return void
     */
    public function updateStatus($order_id)
    {
        $orderReference = get_post_meta($order_id, 'print_and_ship_order_reference', true);

        // Is there an order reference for this ID?
        if (empty($orderReference)) {
            return;
        }

        // Get the order data from Printeers
        $path = 'orders/' . $orderReference;
        $data = $this->_requestDataFromServer($path);

        // Is the data valid?
        if ($data === false) {
            debuglog("Error getting order status from invition API");
            return false;
        }

        // Are there any shipments?
        if (count($data->shipments) == 0) {
            return;
        }

        /**
         * Process the shipments
         * 
         * This part will store all shipment data and determine if we want to send a tracking e-mail
         */
        $wooShipments = get_post_meta($order_id, 'print_and_ship_shipments', true);

        // Is it the first shipment? Make this variable an array to make the diff work
        if (!\is_array($wooShipments)) {
            $wooShipments = array();
        }

        // Compare received shipments to existing
        $newShipments = array_diff_key($data->shipments, $wooShipments);

        // Saving the new data
        update_post_meta($order_id, 'print_and_ship_shipments', $newShipments);
        
        // Send the mail to the customer
        $this->_sendTrackTrace($order_id, $newShipments);

        /**
         * Process the order lines
         * 
         * A little bookkeeping to check if the order was partially or fully shipped
         * depending on the outcome the status will be completed or partially-shipped
         */
        $order = get_post_meta($order_id, 'print_and_ship_order_lines', true);

        // Processing it
        $lines = $data->lines;
        foreach ($lines as $line) {
            if (isset($order[$line->item_sku])) {
                $order[$line->item_sku]['partial'] = $line->shipped_quantity;
            }
        }

        // Checking it
        $status = 'completed';
        foreach ($order as $lines) {
            if ($lines['partial'] != $lines['quantity']) {
                $status = 'partially-shipped';
            }
        }

        // Saving the new data
        update_post_meta($order_id, 'print_and_ship_order_lines', $order);

        $order = new \WC_Order($order_id);
        $order->set_status($status);
        $order->save();
    }

    /**
     * Upload image to Printeers
     *
     * @param string $filename Filename
     * 
     * @return bool|string
     */
    public function uploadImage($filename)
    {
        $data = file_get_contents($filename);
        $data = base64_encode($data);
        $data = array( 'image' => $data );

        $path = 'images';
        $response = $this->_sendToServer($path, $data);

        if ($response !== false) {
            $response = json_decode($response);

            if ($response !== false) {
                if (property_exists($response, 'reference')) {
                    return $response->reference;
                }
            }
        }

        return false;
    }

    /**
     * Render a product image
     *
     * @param string $sku      SKU of the product to render
     * @param string $filename Filename
     *
     * @return string|bool String containing the rendered mockup or false on error
     */
    public function renderImage($sku, $filename)
    {
            
        $imageData = file_get_contents($filename);
        $imageData = base64_encode($imageData);
        $data = array(
            "image" => $imageData,
            "sku" => $sku,
        );
            
        $path = "render";
        $response = $this->_sendToServer($path, $data);

        if ($response !== false) {
            $response = json_decode($response);
            
            if ($response !== false) {
                if (property_exists($response, "mockup")) {
                    return base64_decode($response->mockup);
                } else {
                    debuglog($response);
                }
            }
        }

        return false;
    }
}
