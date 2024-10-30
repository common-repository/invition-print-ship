<?php
/**
 * @author    Mike Sies <support@printeers.com>, Jacco Drabbe <jacco@qurl.nl>
 * @copyright 2020 Printeers
 **/

namespace PrintAndShip;

defined('ABSPATH') or die('No script kiddies please!');

/**
 * Woo
 *
 * @author    Mike Sies <support@printeers.com>, Jacco Drabbe <jacco@qurl.nl>
 * @copyright 2020 Printeers
 * @access    public
 */
class Woo
{
    private $_db;

    /**
     * Woo constructor.
     */
    public function __construct()
    {
        global $wpdb;
        $this->_db = $wpdb;
    }

    /**
     * Add a note to the order
     *
     * @param int    $order_id Order ID
     * @param string $note     Note
     *
     * @return void
     */
    public static function addNote($order_id, $note)
    {
        $args = array(
            'comment_post_ID'       => $order_id,
            'comment_author'        => 'WooCommerce',
            'comment_author_email'  => 'woocommerce@' . $_SERVER['SERVER_NAME'],
            'comment_content'       => $note,
            'comment_type'          => 'order_note',
            'comment_date'          => current_time('mysql'),
            'comment_approved'      => 1,
            'user_id'               => 0
        );
        
        wp_insert_comment($args);
    }

    /**
     * Import all data for all Printeers products
     *
     * @param bool $force Force import stock data
     *
     * @return void
     */
    public function importProductData($force = false)
    {
        // Select all products with an Printeers SKU
        $query = "  SELECT 
                        sku.post_id AS product_id, 
                        sku.meta_value AS invition_sku,
                        stock.meta_value AS quantity,
                        backorders.meta_value AS backorders,
                        print_size.meta_value AS print_size,
                        posts.post_type
                    FROM 
                        " . $this->_db->postmeta . " AS sku
                    INNER JOIN
                        " . $this->_db->posts . " AS posts
                        ON sku.post_id = posts.ID
                    INNER JOIN
                        " . $this->_db->postmeta . " AS stock
                        ON sku.post_id = stock.post_id 
                        AND stock.meta_key = %s
                    INNER JOIN
                        " . $this->_db->postmeta . " AS backorders
                        ON sku.post_id = backorders.post_id 
                        AND backorders.meta_key = %s
                    LEFT JOIN
                        " . $this->_db->postmeta . " AS print_size
                        ON sku.post_id = print_size.post_id 
                        AND print_size.meta_key = %s
                    WHERE 
                        sku.meta_key = %s
                        AND sku.meta_value != \"\"";
        $query = $this->_db->prepare(
            $query,
            '_stock',
            '_backorders',
            'print_and_ship_print_size',
            'print_and_ship_sku'
        );
        $invitionProducts = $this->_db->get_results($query);

        // No products to update
        if (empty($invitionProducts)) {
            return;
        }

        // Get the latest stocklist
        $ipp = new \PrintAndShip\IPP();
        $data = $ipp->getStockList();

        // Did we receive a stocklist?
        if (!$data) {
            debuglog('Tried to import stock but did not receive a stock list');
            return;
        }

        // Organise stock feed by SKU so we can use $item lookup by SKU later
        foreach ($data->items as $item) {
            $stockList[$item->sku] = $item;
        }

        // Iterate through all Printeers products in WooCommerce and import stocks
        foreach ($invitionProducts as $invitionProduct) {
            // Only import stocks when Printeers services are enabled
            if (!$this->isPrinteersEnabled($invitionProduct->product_id)) {
                continue;
            }

            // Does the WooCommerce product exist in the received stocklist?
            if (!array_key_exists($invitionProduct->invition_sku, $stockList)) {
                debuglog('Product ' . $invitionProduct->invition_sku . ' exists in WooCommerce but not in received stocklist');
                continue;
            }

            // Did the stock value change since last import?
            if ($force || !$this->_compareStockValues($stockList[$invitionProduct->invition_sku], $invitionProduct)) {
                $this->importStock($invitionProduct->product_id, $stockList[$invitionProduct->invition_sku]);
            }

            // The next steps are only for print items. 
            if ($stockList[$invitionProduct->invition_sku]->kind != "print") {
                continue;
            }
            
            // Get the current print size
            $printSize = explode('x', $invitionProduct->print_size); // Print size is stored as heightxwidth

            // Should we update the print size in the database?
            if ($force
                || count($printSize) < 2 // The print size does not exist yet
                || $printSize[0] != $stockList[$invitionProduct->invition_sku]->dimension_width_mm
                || $printSize[1] != $stockList[$invitionProduct->invition_sku]->dimension_height_mm
            ) {
                $this->importPrintSize($invitionProduct->product_id, $stockList[$invitionProduct->invition_sku]);
            }
        }
    }

    /**
     * Compare stock values from DB with Printeers to see if change is needed
     *
     * @param object $item  Printeers stock item
     * @param array  $stock WooCommerce stock values
     *
     * @return bool Should we change the DB stock value?
     */
    private function _compareStockValues($item, $stock)
    {
        $wooStockQuantity = (float) $stock->quantity;
        $invitionStockQuantity = (float) 0;

        // Did Printeers supply stock quantity?
        if (property_exists($item->availability, 'amount_left')) {
            $invitionStockQuantity = (float) $item->availability->amount_left;
        }
        
        // Is the quantity still up to date
        if ($wooStockQuantity != $invitionStockQuantity) {
            return false;
        }
        
        // Does WooCommerce allow backorders?
        if ($stock->backorders == "notify" || $stock->backorders == "yes") {
            $wooCanBackorder = true;
        } else {
            $wooCanBackorder = false;
        }

        // Is the backorder setting still up to date?
        if ($item->availability->can_backorder != $wooCanBackorder) {
            return false;
        }

        return true;
    }

    /**
     * Import Printeers attributes to WooCommerce
     *
     * @return void
     */
    public function importAttributesData()
    {
        debuglog('Importing attributes data');

        $ipp = new \PrintAndShip\IPP();

        $stock = $ipp->getStockList();
        $attributes = $ipp->getAttributesList();

        // Create an array with all the terms
        $taxonomies_terms["device_model"] = ((array)$attributes)["models"];
        $taxonomies_terms["device_brand"] = ((array)$attributes)["brands"];
        $taxonomies_terms["case_type_colour"] = $this->createCaseTypeColourCombinations($stock);

        // Iterate over our attributes map,containing attributes that we want to sync
        foreach ($taxonomies_terms as $taxonomy => $terms) {
            // Iterate over possible values for the attribute and insert them.
            foreach ($terms as $term) {
                if (!term_exists($term, wc_attribute_taxonomy_name($taxonomy))) {
                    $args = array('slug' => sanitize_title($term));
                    wp_insert_term($term, wc_attribute_taxonomy_name($taxonomy), $args);
                }
            }
        }

        // TODO: loop over terms and remove old terms that are no longer in use with Printeers.
    }

    /**
     * Import stock quantities from Printeers
     *
     * @param int    $product_id Product ID
     * @param object $item       Item data
     *
     * @return void
     */
    public function importStock($product_id, $item)
    {
        // Did we receive a valid product id?
        if (!\is_numeric($product_id) || $product_id == 0) {
            debuglog('importStock received an invalid product_id');
            return;
        }

        // Is the Printeers Stock Item valid?
        if (!\is_object($item)) {
            debuglog('importStock received an invalid item object');
            return;
        }

        $stock = 0;
        
        if (property_exists($item->availability, 'amount_left')) {
            $stock = $item->availability->amount_left;
        }

        // Force enable manage stock
        update_post_meta($product_id, '_manage_stock', 'yes');
        
        // Update the quantity
        update_post_meta($product_id, '_stock', $stock);
        
        if ($stock == 0) {
            // Out of stock
            if ($item->availability->can_backorder) {
                update_post_meta($product_id, '_backorders', get_option("print_and_ship_allow_backorders", "notify"));
                delete_post_meta($product_id, '_stock_status');
            } else {
                update_post_meta($product_id, '_backorders', 'no');
                update_post_meta($product_id, '_stock_status', 'outofstock');
            }

            if (property_exists($item->availability, 'expected_available_date')
                && $item->availability->expected_available_date != ""
            ) {
                update_post_meta($product_id, 'expected_available_date', $item->availability->expected_available_date);
            }
        } else {
            // In stock
            if ($item->availability->can_backorder) {
                update_post_meta($product_id, '_backorders', get_option("print_and_ship_allow_backorders", "notify"));
            } else {
                update_post_meta($product_id, '_backorders', 'no');
            }

            delete_post_meta($product_id, '_stock_status');
            delete_post_meta($product_id, 'expected_available_date');
        }
        
        wp_set_post_terms($product_id, $stock > 0 ? 'instock' : 'outofstock', 'product_visibility', true);
        wc_delete_product_transients($product_id);
    }

    /**
     * Import print size from Printeers
     *
     * @param int    $product_id Product ID
     * @param object $item       Item data
     *
     * @return void
     */
    public function importPrintSize($product_id, $item)
    {
        debuglog('Importing print size');

        $printSize = $item->dimension_width_mm . 'x' . $item->dimension_height_mm;
        update_post_meta($product_id, 'print_and_ship_print_size', $printSize);

        do_action('print_and_ship_print_size_changed', $product_id);
    }

    /**
     * Import product attributes
     *
     * @param int    $product_id Product ID
     * @param object $item       Item data
     *
     * @return void
     */
    public function importAttributes($product_id, $item)
    {
        debuglog('Importing attributes');

        $product = wc_get_product($product_id);

        if (method_exists($product, "is_type")) {
            if ($product->is_type("simple")) {
                // Add Brand
                if (!empty($item->attributes->device_brand)) {
                    $this->_linkAttribute($product_id, "device_brand", $item->attributes->device_brand);
                }
                
                // Add Model
                if (!empty($item->attributes->device_models)) {
                    foreach ($item->attributes->device_models as $model) {
                        $this->_linkAttribute($product_id, "device_model", $model);
                    }
                }

                // Add Case Type & Colour
                if (!empty($item->attributes->case_type)) {
                    $this->_linkAttribute($product_id, "case_type_colour", $this->createCaseTypeColour($item));
                }
            }
        }
    }

    /**
     * Link a single attribute
     *
     * @param int    $product_id      ID of the product
     * @param string $attribute_name  Name of the attribute
     * @param string $attribute_value Value of the attribute
     *
     * @return void
     */
    private function _linkAttribute($product_id, $attribute_name, $attribute_value)
    {
        wp_set_object_terms($product_id, $attribute_value, 'pa_' . $attribute_name, true);
        
        $newAttribute = array(
            $attribute_name => array(
                'name' => 'pa_' . $attribute_name,
                'value' => $attribute_value,
                'is_visible' => '1',
                'is_taxonomy' => '1'
            )
        );

        $product_attributes = get_post_meta($product_id, '_product_attributes', true);

        if (is_array($product_attributes)) {
            // TODO: Check if it already exists IPP-1136
            $product_attributes = array_merge($product_attributes, $newAttribute);
        } else {
            $product_attributes = $newAttribute;
        }

        update_post_meta($product_id, '_product_attributes', $product_attributes);
    }

    /**
     * Import all example images for one product from Printeers
     *
     * @param int    $product_id ID of the product
     * @param object $item       WooCommerce item
     *
     * @return void
     */
    public function importExampleImages($product_id, $item)
    {
        if (get_option("print_and_ship_auto_add_product_image", true) != "yes") {
            // Importing images is disabled
            return;
        }

        if (has_post_thumbnail($product_id)) {
            // We already have an image (we are not checking if image is outdated for now, just skip when present)
            return;
        }

        $ids = array();
        foreach ($item->example_images as $image) {
            $ids[] = $this->downloadExampleImage($image, $item->name, $product_id);
        }

        if (!has_post_thumbnail($product_id) && isset($ids[0])) {
            // Only add thumbnail once
            if (set_post_thumbnail($product_id, $ids[0])) {
                unset($ids[0]);
            }
        }

        update_post_meta($product_id, 'product_image_gallery', implode(',', $ids));
    }

    /**
     * Download an example image from Printeers and add it to the product
     *
     * @param string $fileurl URL of the image
     * @param string $filealt Alt text of the image
     * @param int    $post_id The post it should be attached to
     *
     * @return int $attach_id ID of the newly added image
     */
    public function downloadExampleImage($fileurl, $filealt, $post_id)
    {
        include_once ABSPATH . 'wp-admin/includes/image.php';
        include_once ABSPATH . 'wp-admin/includes/file.php';

        $filename = $post_id."-".basename($fileurl);
        $wp_upload_dir = wp_upload_dir();
            
        $destination = $wp_upload_dir['path'] . '/';

        $ch = curl_init($fileurl);
        $fp = fopen($destination.$filename, 'wb');
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_exec($ch);
        curl_close($ch);
        fclose($fp);

        $filetype = wp_check_filetype($destination.$filename);
        $attachment = array(
        'guid'           => $destination.$filename,
        'post_mime_type' => $filetype['type'],
        'post_title'     => $filename,
        'post_author'    => 1,
        'post_content'   => ''
        );

        $attach_id = wp_insert_attachment($attachment, $destination.$filename, $post_id);

        $attach_data = wp_generate_attachment_metadata($attach_id, $destination.$filename);

        wp_update_attachment_metadata($attach_id, $attach_data);
        update_post_meta($attach_id, '_wp_attachment_image_alt', $filealt);

        return $attach_id;
    }

    /**
     * Renders an example using the print image set at the product
     *
     * @param int $product_id The ID of the product
     * @param string $type The type of the product (simple, variable, variation)
     *
     * @return bool
     */
    public function renderProductImage($product_id, $type)
    {
        include_once ABSPATH . 'wp-admin/includes/image.php';
        include_once ABSPATH . 'wp-admin/includes/file.php';

        // If the product is a variation, get the parent print image
        if ($type == 'variation') {
            $parent_id = wp_get_post_parent_id($product_id);
            $print_id = get_field('print_and_ship_print_image', $parent_id);
        } else {
            $print_id = get_field('print_and_ship_print_image', $product_id);
        }
        
        // If the product is a variable product, get the base SKU
        if ($type == 'variable') {
            $print_and_ship_sku = get_option("print_and_ship_render_image_base_sku");
        } else {
            $print_and_ship_sku = get_post_meta($product_id, 'print_and_ship_sku', true);
        }

        if (empty($print_and_ship_sku)) {
            debuglog("No SKU found for product " . $product_id);
            return false;
        }
            
        $wp_upload_dir = wp_upload_dir();
        $destination = $wp_upload_dir['path'] . '/';
        $render_file_name = $product_id . "-" . $print_and_ship_sku . '.jpg';

        // We already have an image (we don't check if image is outdated for now)
        if (has_post_thumbnail($product_id)) {
            return false;
        }

        $print_file_name = get_attached_file($print_id);
        if (!file_exists($print_file_name)) {
            debuglog("Cannot find attached print image for product " . $product_id);
            return false;
        }
            
        $ipp = new \PrintAndShip\IPP();
        $rendered_image = $ipp->renderImage($print_and_ship_sku, $print_file_name);

        if ($rendered_image === false) {
            debuglog("Cannot render image for product " . $product_id);
            return false;
        }

        if (!file_put_contents($destination.$render_file_name, $rendered_image)) {
            debuglog("Cannot store rendered image, please check your permissions");
            return false;
        }
            
        $filetype = wp_check_filetype($destination.$render_file_name);
        $attachment = array(
            'guid'           => $destination.$render_file_name,
            'post_mime_type' => $filetype['type'],
            'post_title'     => $render_file_name,
            'post_author'    => 1,
            'post_content'   => ''
        );

        $attach_id = wp_insert_attachment($attachment, $destination.$render_file_name, $product_id);
        $attach_data = wp_generate_attachment_metadata($attach_id, $destination.$render_file_name);

        wp_update_attachment_metadata($attach_id, $attach_data);
        
        // update_post_meta($attach_id, '_wp_attachment_image_alt', $filealt);
        // TODO add Alt text to image
            
        if (!set_post_thumbnail($product_id, $attach_id)) {
            debuglog("Couldnt set rendered image as post thumbnail for product " . $product_id);
            return false;
        }
            
        update_post_meta($product_id, 'product_image_gallery', $attach_id);

        return true;
    }

    /**
     * Is the Printeers service enabled for this product?
     *
     * @param int $product_id Product ID
     *
     * @return bool
     */
    public function isPrinteersEnabled($product_id)
    {
        if (get_post_type($product_id) == 'product_variation') {
            $product_id = wp_get_post_parent_id($product_id);
        }
            
        $value = get_post_meta($product_id, 'print_and_ship_enable', true);

        if ($value == '1' || $value == 'yes') {
            return true;
        }

        return false;
    }

    /**
     * Generate the right combination of values for Case Type & Colour field
     *
     * @param object $item Printeers stocklist item object
     * @return string generated Case Type & Colour attribute value
     */
    public function createCaseTypeColour($item)
    {
        // When fully printed, replace the colour with the string Full print
        if ($item->attributes->print_side == "Fully printed") {
            $string = $item->attributes->case_type . " - Full print";
        } else {
            $string = $item->attributes->case_type;
            $string .= " - ";
            $string .= $item->attributes->case_colour;
        }

        // Add non-standard finish (Matte is standard)
        if ($item->attributes->print_finish != "Matte"
            && !empty($item->attributes->print_finish)
        ) {
            $string .= " " . $item->attributes->print_finish;
        }

        return $string;
    }


    /**
     * Returns an array of possible combinations based on the Printeers Stocklist
     *
     * @param object $stock Printeers stocklist object
     * @return array
     */
    public function createCaseTypeColourCombinations($stock)
    {
        $combinations = array();

        // We did not receive a stock object, probably API details are incorrect
        if (!is_object($stock)) {
            return array();
        }

        foreach ($stock->items as $invitionStockItem) {
            if (!in_array($this->createCaseTypeColour($invitionStockItem), $combinations)
                && !is_null($invitionStockItem->attributes->case_type)
                && !is_null($invitionStockItem->attributes->case_colour)
            ) {
                $combinations[] = $this->createCaseTypeColour($invitionStockItem);
            }
        }

        return $combinations;
    }

    /**
     * Prepare order for sending
     *
     * @param WC_Order $order Order object
     * 
     * @return mixed
     */
    public static function prepareOrder($order)
    {
        $invitionItems = 0;

        $order_data = $order->get_data();

        if (isset($order_data['shipping']['toevoeging'])) {
            $street = $order_data['shipping']['address_1'] . ' ' . $order_data['shipping']['toevoeging'];
        } else {
            $street = $order_data['shipping']['address_1'];
        }

        // Get the receiver's phone and email if available
        $shippingPhone = $order->get_meta('_shipping_phone');
        $shippingEmail = $order->get_meta('_shipping_email');

        $lines = array();
        $address = array(
            'firstname'       => $order_data['shipping']['first_name'],
            'lastname'        => $order_data['shipping']['last_name'],
            'company'         => $order_data['shipping']['company'],
            'streetname'      => $street,
            'additional_info' => $order_data['shipping']['address_2'],
            'city'            => $order_data['shipping']['city'],
            'state'           => $order_data['shipping']['state'],
            'zipcode'         => $order_data['shipping']['postcode'],
            'country_code'    => $order_data['shipping']['country'],
            'phonenumber'     => !empty($shippingPhone) ? $shippingPhone : $order_data['billing']['phone'],
            'email'           => !empty($shippingEmail) ? $shippingEmail : $order_data['billing']['email'],
        );

        foreach ($order_data['line_items'] as $item) {
            $image_reference = null;

            // Get the right product ID (Variation / Simple)
            $product_id = ($item->get_variation_id() != 0) ? $item->get_variation_id() : $item->get_product_id();
                
            // Is it a print item?
            if (isPrintItem($product_id)) {
                $image_reference = self::uploadImage($item);

                if (! $image_reference) {
                    // Image could not be uploaded, stop placing the order to prevent comitting a half order
                    $note = "WARNING: Image not found for " . $item->get_name() . ". Upload print image and try again.";
                    self::addNote((int) $order->get_order_number(), $note);
                        
                    return false;
                }
            }
                
            // Get the Printeers data
            $print_and_ship_enable = get_post_meta($item->get_product_id(), 'print_and_ship_enable', true);
            $print_and_ship_sku = get_post_meta($product_id, 'print_and_ship_sku', true);

            // Is it an Printeers item?
            if (!empty($print_and_ship_sku) && $print_and_ship_enable) {
                $orderline = array(
                'item_sku'  => $print_and_ship_sku,
                'quantity'  => $item->get_quantity()
                );
                if (isset($image_reference)) {
                    $orderline['image_reference'] = $image_reference;
                }

                $lines[] = $orderline;

                $invitionItems++;
            }
        }

        if ($invitionItems == 0) {
            // Order does not contain Printeers items. Skipping...
            return null;
        }

        // Add extra gift item per product
        $productGiftItem = get_option("print_and_ship_auto_add_ipp_sku_product");
        if (trim($productGiftItem) != "") {
            $totalItems = 0;
            foreach ($lines as $line) {
                $totalItems += $line["quantity"];
            }

            $lines[] = array(
            "item_sku"    => $productGiftItem,
            "quantity"    => $totalItems,
            );
        }

        // Add extra gift item per order
        $orderGiftItem = get_option("print_and_ship_auto_add_ipp_sku_order");
        if (trim($orderGiftItem) != "") {
            $lines[] = array(
            "item_sku"    => $orderGiftItem,
            "quantity"    => 1,
            );
        }

        $shippingMinimalLevel = "";

        // Get the shipping object
        $shippingArray = $order->get_items('shipping');
        $shippingObject = reset($shippingArray);
        
        // Is there any shipping information on the order?
        if (\is_object($shippingObject)) {

            // New way of getting the level
            $shippingMinimalLevel = $shippingObject->get_meta('print_and_ship_level');

            /* Backwards compatibility check (06-2020, support until 09-2020) */
            if ($shippingMinimalLevel == "") {
                $oldLevel = $shippingObject->get_method_id();
                
                switch ($oldLevel) {
                case 'print_and_ship_normal':
                case 'print_and_ship_tracked':
                case 'print_and_ship_premium':
                    $shippingMinimalLevel = \str_replace('print_and_ship_', '', $oldLevel);
                    break;
                }
            }
        }

        // Was the level set? If not, use fallback methods
        if ($shippingMinimalLevel == "") {
            // Check for master setting (Settings > Shipping > Minimal level)
            $shippingMinimalLevel = get_option("print_and_ship_shipping_minimal_level");
            if ($shippingMinimalLevel != "normal"
                && $shippingMinimalLevel != "tracked"
                && $shippingMinimalLevel != "premium"
            ) {
                // No general setting found. Use normal
                $shippingMinimalLevel = "normal";
            }
        }

        $data = array(
            'address'                    => $address,
            'lines'                      => $lines,
            'partner_reference'          => (string) $order_data['id'],
            'shipping_minimal_level'     => $shippingMinimalLevel,
        );

        return $data;
    }

    /**
     * uploadImage() Upload an image to Printeers API
     *
     * @param  WC_Order_Itemproduct $item Item
     * @return bool|string
     */
    public static function uploadImage($item)
    {
        $product_id = $item->get_product_id();

        if ($item->get_meta('print_and_ship_print_image')) {
            // Get the order line print image
            $printImage = $item->get_meta('print_and_ship_print_image');
        } else {
            // Get the product print image
            $printImage = get_field('print_and_ship_print_image', $product_id);
        }

        // Is it an ID or a file name?
        if (is_numeric($printImage)) {
            // The print image is stored in the media library
            $filename = get_attached_file($printImage, true);
        
        } else {
            // The print image is stored in the Print and Ship upload dir
            $filename = PRINT_AND_SHIP_UPLOADDIR . '/' . $printImage;   
        }
        
        if (!file_exists($filename)) {
            return false;
        }

        $ipp = new \PrintAndShip\IPP();
        $image_reference = $ipp->uploadImage($filename);

        return $image_reference;
    }

    /**
     * Replace the print image of an order item
     *
     * @param  int  $item_id Order item ID
     * @param  file $image   base64 of the image
     * @return array boolean success, string result
     */
    public static function updateOrderPrintImage($order_id, $item_id, $image)
    {
        include_once ABSPATH . 'wp-admin/includes/image.php';
        include_once ABSPATH . 'wp-admin/includes/file.php';
        
        // The postdata is incomplete
        if (!$order_id || !$item_id || !$image) {
            return array('success' => false, 'message' => 'Some fields are missing');
        }
        
        if (!preg_match('/^data:image\/(\w+);base64,/', $image, $extension)) {
            return array('success' => false, 'message' => 'Its not a valid image');
        }
        
        $image = substr($image, strpos($image, ',') + 1);
        $extension = strtolower($extension[1]);
        
        // Image is invalid
        if (!in_array($extension, [ 'jpg', 'jpeg', 'png' ])) {
            return array(
                'success' => false, 
                'message' => 'Image must be JPG or PNG'
            );
        }
                
        $image = base64_decode($image);
        
        // The base64 is invalid
        if ($image === false) {
            return array('success' => false, 'message' => 'Could not decode image');
        }
        
        // Too lazy to write a random string function
        $filename = $item_id . '-' . wp_generate_password(10, false);
        
        // Make sure the file does not exist
        while (file_exists(PRINT_AND_SHIP_UPLOADDIR . '/' . $filename.'.'.$extension)) {
            $filename = $item_id . '-' . wp_generate_password(10, false);
        }
        
        $full_filename = PRINT_AND_SHIP_UPLOADDIR . '/' . $filename.'.'.$extension;
        
        if (!file_put_contents($full_filename, $image)) {
            return array('success' => false, 'message' => 'Could not save image');
        }
            
        // Add it to the right order item
        $order = wc_get_order($order_id);
        $order_item = $order->get_items()[$item_id];

        $order_item->update_meta_data('print_and_ship_print_image', $filename . '.' . $extension);
        $order_item->save_meta_data();

        return array('success' => true, 'message' => 'The print image was uploaded successfully');
    }

    /**
     * Create a new Printeers Simple product in WooCommerce
     * 
     * @param object $stockItem Printeers Stocklist Item
     * @param string $addon     Does the product require an addon? (e.g. Zakeke)
     * 
     * @return array Did the product import succesfully?
     */
    function createProduct($stockItem, $addon = '')
    {
        // First we have to create the product
        $post = array(
            'post_title'   => $stockItem->name,
            'post_type'    => 'product',
            'post_status'  => 'draft',
        );
        $product_id = wp_insert_post($post);

        // Was the product added?
        if (empty($product_id)) {
            return array(
                'succes'  => false,
                'message' => 'Could not create product in WordPress',
            );
        }

        // Define as simple product in database
        wp_set_object_terms($product_id, 'simple', 'product_type', false);

        // Add the SKU and enable the Printeers services
        add_post_meta($product_id, 'print_and_ship_sku', $stockItem->sku, true);
        add_post_meta($product_id, 'print_and_ship_enable', '1', true);

        switch ($addon) {
        case 'zakeke':
            update_post_meta($product_id, 'print_and_ship_addon', $addon);
            break;
        
        case '':
            break; // Just skip when no addon selected
        
        default:
            debuglog('invalid addon selected');
            break;
        }
        $woo = new \PrintAndShip\Woo();

        // Do we want a product image?
        if ($addon == 'zakeke' || $stockItem->kind != "print") {
            // Currently we import a product image when it is Zakeke because the user designs itself
            // and when it is a non-print product. For print products, we expect the webshop to
            // add a print image themselves. This should be extended with image rendering later
            $woo->importExampleImages($product_id, $stockItem);
        }

        $woo->importStock($product_id, $stockItem);
        $woo->importAttributes($product_id, $stockItem);
        
        if ($stockItem->kind == "print") {
            $woo->importPrintSize($product_id, $stockItem);
        }

        return array(
            'success' => true,
            'message' => $stockItem->name . ' was added succesfully',
            'product_id' => $product_id,
        );
    }
}
