<?php
/**
 * WC_Shipping_PrintAndShip_Premium class.
 *
 * @category Class
 * @package  Shipping-for-WooCommerce/Classes
 * @author   Mike Sies <mike@studiogewaagd.nl>
 */

namespace PrintAndShip;

class WC_Shipping_PrintAndShip_Premium extends \WC_Shipping_Method
{

    /**
     * Constructor. The instance ID is passed to this.
     */
    public function __construct( $instance_id = 0 ) 
    {
        $this->id                 = 'print_and_ship_premium';
        $this->instance_id        = absint($instance_id);
        $this->method_title       = __('Printeers - Express parcel');
        $this->method_description = __(
            'This shipment method is connected to shipping level Premium.
            Shipments using this shipping level will be sent with tracking
            and delivered as a premium parcel.
            <br /><strong>Please note! This method overrides the automated
            shipping calculation.</strong>'
        );
        $this->supports              = array(
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
            'title' => array(
                'title'       => __('Method Title'),
                'type'        => 'text',
                'description' => __('This controls the title which the user sees during checkout.'),
                'default'     => __('Express parcel (with tracking)'),
                'desc_tip'    => true
            ),
            'rate' => array(
                'title'       => __('Shipping price'),
                'type'        => 'text',
                'description' => __('Enter your price'),
                'desc_tip'    => true
            )
        );
        $this->enabled = $this->get_option('enabled');
        $this->title   = $this->get_option('title');
        $this->rate   = $this->get_option('rate');

        add_action('woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options'));
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
        $rate = array(
            'id' => $this->id,
            'label' => $this->title,
            'cost' => $this->get_option('rate'),
            'meta_data' => array(
                'print_and_ship_level' => 'premium',
            ),
        );

        // Register the rate
        $this->add_rate($rate);
    }
}
