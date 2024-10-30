<?php
/**
 * WC_Shipping_PrintAndShip_Auto class.
 *
 * @category Class
 * @package  Shipping-for-WooCommerce/Classes
 * @author   Mike Sies <mike@studiogewaagd.nl>
 */

namespace PrintAndShip;

class WC_Shipping_PrintAndShip_Auto extends \WC_Shipping_Method
{

    /**
     * Constructor. The instance ID is passed to this.
     */
    public function __construct( $instance_id = 0 ) 
    {
        $this->id                 = 'print_and_ship_auto';
        $this->instance_id        = absint($instance_id);
        $this->method_title       = __('Printeers - Automatic shipping calculator');
        $this->method_description = __(
            'This shipment will check the contents of the cart 
            and the shipment destination and calculate shipping through 
            the Partner API based on the latest Printeers rates.'
        );
        $this->supports = array(
            'shipping-zones',
            'instance-settings',
        );
        $this->instance_form_fields = array(
            'enabled' => array(
                'title'   => __('Enable'),
                'type'    => 'checkbox',
                'label'   => __('Enable this shipping method'),
                'default' => 'yes',
            ),
        );
        $this->enabled = $this->get_option('enabled');
        $this->title   = 'Automatic Shipping Calculator';

        add_action('woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options'));
    }

    /**
     * Make calculations on the shipping amount
     * 
     * @param float $price The base price to start with
     * 
     * @return float $price The price after calculations
     */
    public function makePrinteersCalculation($price)
    {
        // Get the settings
        $action     = get_option('print_and_ship_automatic_shipping_action');
        $amount     = get_option('print_and_ship_automatic_shipping_amount');
        $type       = get_option('print_and_ship_automatic_shipping_type');
        $multiplier = get_option('print_and_ship_automatic_shipping_multiplier');
        $round      = get_option('print_and_ship_automatic_shipping_round');

        // We need 3 variables to complete calculation, are they all set?
        if ($action != "" && $amount != "" && $type != "") {
            switch ($type) {
            // Calculate by percentage
            case 'percent':
                if ($action == 'add') {
                    $price = $price + (($price / 100) * $amount);
                }
                if ($action == 'subtract') {
                    $price = $price - (($price / 100) * $amount);
                }
                break;
            // Calculate by amount
            case 'currency':
                if ($action == 'add') {
                    $price = $price + $amount;
                }
                if ($action == 'subtract') {
                    $price = $price - $amount;
                }
                break;
            }
        }

        // Multiplying the price can be done standalone
        if ($multiplier != "") {
            $price = $price * $multiplier;
        }

        // Rounding the price can be done standalone
        switch ($round) {
        case 'dot95':
            return $price = round($price + 0.05) - 0.05;
        case 'dot99':
            return $price = round($price + 0.01) - 0.01;
        case 'round':
            return $price = round($price);
        default:
            return $price;
            break;
        }

        return $price;
    }

    /**
     * Add the shipping rate chosen by the user
     *
     * @param mixed $package Not sure what this does
     * 
     * @return void
     */
    public function calculate_shipping($package = array())
    {
        $orderLines = array();
        
        // Map the order lines
        foreach ($package['contents'] as $product) {

            // Get the right product ID (Variation / Simple)
            $product_id = ($product['variation_id'] != 0) ? $product['variation_id'] : $product['product_id'];

            $print_and_ship_sku = "";
            $print_and_ship_sku = get_post_meta($product_id, 'print_and_ship_sku', true);

            // It's not an Printeers product
            if ($print_and_ship_sku == "") {
                continue;
            }

            // We have a match, add to array
            $orderLines[] = array(
                'quantity' => $product['quantity'],
                'item_sku' => $print_and_ship_sku,
            );
        }

        // No Printeers products found
        if (count($orderLines) == 0) {
            debuglog('no invition products found');
            return;
        }

        // Map the address
        $address = array(
            'streetname'      => $package['destination']['address'],
            'city'            => $package['destination']['city'],
            'zipcode'         => $package['destination']['postcode'],
            'country_code'    => $package['destination']['country'],
        );
        
        // Merge it all in one array
        $data = array(
            'address'       => $address,
            'lines'         => $orderLines,
            'shipping_kind' => 'dropship',
        );

        // Request the actual quote
        $ipp = new \PrintAndShip\IPP();
        $quote = $ipp->requestQuote($data);

        // No shipping options received.
        if (!$quote) {
            debuglog('No options received');
            return false;
        }

        // Set the default names
        $methodNames = array(
            'normal'  => __('Regular mail (no tracking)'),
            'tracked' => __('Tracked mail'),
            'premium' => __('Premium shipment (with track and trace)'),
        );

        $minimumShippingLevel = get_option('print_and_ship_shipping_minimal_level');

        // Make a shipping rate for each result
        foreach ($quote->shipping_options as $option) {

            // A minimum level was set in the settings, is the option higher then the minimum level?
            if ($minimumShippingLevel != '') {
                
                switch ($minimumShippingLevel) {
                case 'tracked':
                    if ($option->level == 'normal') {
                        continue 2;
                    }
                    break;
                case 'premium':
                    if ($option->level == 'normal' || $option->level == 'tracked') {
                        continue 2;
                    }
                    break;
                }
            }

            $rate = array(
                'id' => 'print_and_ship_' . $option->level,
                'label' => $methodNames[$option->level],
                'cost' => $this->makePrinteersCalculation($option->price),
                'meta_data' => array(
                    'print_and_ship_level' => $option->level,
                ),
            );

            // Register the rate
            $this->add_rate($rate);
        }
    }
}
