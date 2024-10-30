<?php
/**
 * @author Jacco Drabbe <jacco@qurl.nl>
 * @version $Id$
 * @copyright 2017 Printeers
 **/

namespace PrintAndShip;

defined('ABSPATH') or die('No script kiddies please!');

$WooObj = new \WooCommerce();

if (! class_exists('WC_Email')) {
    require_once $WooObj->plugin_path() . '/includes/emails/class-wc-email.php';
}

/**
 * EmailTrackTrace
 *
 * @author Jacco Drabbe <jacco@qurl.nl>
 * @copyright 2017 Printeers
 * @access public
 */
class EmailTrackTrace extends \WC_Email
{
    private $track_trace_url;
    private $customer_name;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->id             = 'customer_tracktrace';
        $this->customer_email = true;
        $this->title          = 'Track & Trace code';
        $this->description    = 'Track & Trace';
        $this->template_html  = 'email-tracktrace-html.php';
        $this->template_plain = 'email-tracktrace-plain.php';
        $this->placeholders   = array(
            '{site_title}'   => $this->get_blogname(),
            '{order_date}'   => '',
            '{order_number}' => '',
        );

        // Call parent constructor
        parent::__construct();
    }

    /**
     * WC_Email_TrackTrace::init_form_fields() Admin form
     *
     * @return void
     */
    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled'    => array(
                'title'   => 'Enable/Disable',
                'type'    => 'checkbox',
                'label'   => 'Enable this email notification',
                'default' => 'yes'
            ),

            'subject'    => array(
                'title'       => 'Title',
                'type'        => 'text',
                'description' => sprintf('E-mail subject, leave empty for default subject: <code>%s</code>.', $this->get_default_subject()),
                'placeholder' => '',
                'default'     => ''
            ),

            'heading'    => array(
                'title'       => 'E-mail head',
                'type'        => 'text',
                'description' => sprintf(__('E-mail header, leave empty for default header: <code>%s</code>.'), $this->get_default_heading()),
                'placeholder' => '',
                'default'     => ''
            ),

            'email_type' => array(
                'title'       => 'Email type',
                'type'        => 'select',
                'description' => 'Choose which format of email to send.',
                'default'     => 'html',
                'class'       => 'email_type',
                'options'     => array(
                    'plain'     => 'Plain text',
                    'html'      => 'HTML',
                    'multipart' => 'Multipart'
                )
            )
        );
    }

    /**
     * WC_Email_TrackTrace::setTrackTraceUrl() Set TrackTrace URL
     *
     * @param string $url URL
     * 
     * @return void
     */
    public function setTrackTraceUrl($url)
    {
        $this->track_trace_url = $url;
    }

    /**
     * WC_Email_TrackTrace::setCustomerName() Set customer name
     *
     * @param string $name Customer name
     * 
     * @return void
     */
    public function setCustomerName($name)
    {
        $this->customer_name = $name;
    }

    /**
     * Trigger the sending of this email.
     *
     * @param int      $order_id The order ID.
     * @param WC_Order $order    Order object.
     * 
     * @return null
     */
    public function trigger($order_id, $order = false)
    {
        if ($order_id && ! is_a($order, 'WC_Order')) {
            $order = wc_get_order($order_id);
        }

        if (is_a($order, 'WC_Order')) {
            $this->object                         = $order;
            $this->recipient                      = $this->object->get_billing_email();
            $this->placeholders['{order_date}']   = wc_format_datetime($this->object->get_date_created());
            $this->placeholders['{order_number}'] = $this->object->get_order_number();
        }

        if (! $this->is_enabled() || ! $this->get_recipient()) {
            debuglog('Email not enabled');
            return null;
        }

        debuglog('Sending email with subject ' . $this->get_subject() . ' to ' . $this->get_recipient());

        $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());
    }

    /**
     * Get email subject.
     *
     * @since  3.1.0
     * @return string
     */
    public function get_default_subject()
    {
        $subject = get_option('print_and_ship_email_subject');

        if (trim($subject) != "") {
            return $subject;
        } else {
            return 'Your order has been shipped';
        }
    }

    /**
     * Get email heading.
     *
     * @since  3.1.0
     * @return string
     */
    public function get_default_heading()
    {
        $heading = get_option('print_and_ship_email_title');

        if (trim($heading) != "") {
            return $heading;
        } else {
            return 'Your order has been shipped';
        }
    }

    /**
     * Get content html.
     *
     * @access public
     * @return string
     */
    public function get_content_html()
    {
        return wc_get_template_html($this->template_html, array(
            'order'         => $this->object,
            'track_trace'   => $this->track_trace_url,
            'customer_name' => $this->customer_name,
            'email_heading' => $this->get_heading(),
            'sent_to_admin' => false,
            'plain_text'    => false,
            'email'         => $this,
        ), '', PRINT_AND_SHIP_BASEDIR . '/assets/woocommerce/');
    }

    /**
     * Get content plain.
     *
     * @return string
     */
    public function get_content_plain()
    {
        return wc_get_template_html($this->template_plain, array(
            'order'         => $this->object,
            'track_trace'   => $this->track_trace_url,
            'customer_name' => $this->customer_name,
            'email_heading' => $this->get_heading(),
            'sent_to_admin' => false,
            'plain_text'    => true,
            'email'         => $this,
        ), '', PRINT_AND_SHIP_BASEDIR . '/assets/woocommerce/');
    }
}
