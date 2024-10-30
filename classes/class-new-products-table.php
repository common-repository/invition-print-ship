<?php
/**
 * @author    Mike Sies <support@printeers.com>
 * @copyright 2018 Printeers
 **/

namespace PrintAndShip;

defined('ABSPATH') or die('No script kiddies please!');

if (! class_exists('\WP_List_Table')) {
    include_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * This class overwrites WP_List_Table to create a table with Printeers products to add
 *
 * @author Jacco Drabbe <jacco@qurl.nl>
 * @copyright 2018 Printeers
 * @access public
 */
class NewProductsTable extends \WP_List_Table
{
    protected $_column_headers;

    private $all_items;
    private $_db;
    private $_ipp;
    private $_woo;

    /**
     * NewProductsTable constructor
     */
    public function __construct()
    {
        global $wpdb;
        $this->_db = $wpdb;

        $this->_ipp = new \PrintAndShip\IPP();
        $this->_woo = new \PrintAndShip\Woo();

        parent::__construct(
            array(
                'plural'    => 'Products',
                'singular'  => 'Product',
                'ajax'      => false
            )
        );
    }

    /**
     * Checkmark column
     *
     * @param array $item Item data
     * 
     * @return string
     */
    public function column_cb($item)
    {
        $check = false;
            
        if (isset($item['checkmark'])) {
            $check = ( $item['checkmark'] ==  '1' ) ? true : false;
        }

        $html  = '<input type="checkbox" name="products_checkmark[]" value="' . $item['ID'] . '"';
        $html .= ( $check ) ? ' checked="checked"' : '';
        $html .= ' />';

        return $html;
    }

    /**
     * Deafault process columns
     *
     * @param array  $item        Column item
     * @param string $column_name Column name
     * 
     * @return string
     */
    public function column_default($item, $column_name)
    {
        return $item[$column_name];
    }

    /**
     * Process column product
     *
     * @param $item
     * 
     * @return string
     */
    public function column_product($item)
    {
        return '<input name="sku_' . $item['sku'] . '" type="text" list="products" onkeyup="selectRow(this)" />';
    }

    /**
     * Create HTML datalist
     *
     * @return string
     */
    public function create_datalist()
    {

        $list = array();
        $query = "SELECT post_title FROM " . $this->_db->posts . " WHERE post_type = 'product' ORDER BY post_title";
        $results = $this->_db->get_results($query);

        foreach ($results as $row) {
            $list[ ] = '<option value="' . $row->post_title . '">' . $row->post_title . '</option>';
        }

        return '<datalist id="products">' . implode(' ', $list) . '</datalist>';
    }

    /**
     * Set bulk actions
     *
     * @return array
     */
    public function get_bulk_actions()
    {
        $actions = array(
            'link'   => 'Connect to existing products',
            'create' => 'Add as new products',
        );

        if (isAddonActive("zakeke")) {
            $actions["create_zakeke"] = "Add as Zakeke products";
        }

        return $actions;
    }

    /**
     * Get the columns
     *
     * @return array
     */
    public function get_columns()
    {
        $cols = array(
            'cb'        => '<input type="checkbox" />',
            'name'      => 'Product',
            'sku'       => 'SKU',
            'product'   => 'Existing product',
        );

        return $cols;
    }

    /**
     * Prepare content
     *
     * @return bool
     */
    public function prepare_items()
    {
        $items = array();
        $limit = 25;
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = array();
        $this->_column_headers = array($columns, $hidden, $sortable);

        $col = array();
        if (isset($_GET['filter_existing']) && $_GET['filter_existing'] == 'yes') {
            $query = "SELECT meta_value FROM " . $this->_db->postmeta . " WHERE meta_key = %s";
            $query = $this->_db->prepare($query, 'print_and_ship_sku');
            $col = $this->_db->get_col($query);
        }
        $data = $this->_ipp->getStockList();

        if (is_object($data)) {
            foreach ($data->items as $item) {
                if ($this->process_search($item) && !in_array($item->sku, $col) && $this->_checkLifeCycle($item)) {
                    $product = array(
                        'ID'         => $item->id,
                        'name'       => $item->name,
                        'sku'        => $item->sku,
                    );
                    $items[] = $product;
                }
            }
            $this->all_items = $items;

            $current_page = $this->get_pagenum();
            $total_items = count($items);

            $data = array_slice($items, (($current_page - 1) * $limit), $limit);
            $this->items = $data;

            $this->set_pagination_args(
                array(
                    'total_items' => $total_items,               // WE have to calculate the total number of items
                    'per_page'    => $limit,                     // WE have to determine how many items to show on a page
                    'total_pages' => ceil($total_items/$limit)   // WE have to calculate the total number of pages
                )
            );

            return true;
        }

        return false;
    }

    /**
     * Process bulk actions
     *
     * @return array
     */
    public function process_bulk_action()
    {
        $result = array();
        
        switch ($_POST['action']) {
        // Create a new product
        case 'create':
        case 'create_zakeke':

            $stockListObject = $this->_ipp->getStockList();
            $stockListArray = array();

            foreach ($stockListObject->items as $item) {
                $stockListArray[$item->id] = $item;
            }

            // Iterate through posted products
            foreach ($_POST['products_checkmark'] as $stockListID) {

                // Get the full Printeers item from the stock list
                $stockItem = $stockListArray[$stockListID];

                // Did we find the stock item?
                if (!is_object($stockItem)) {
                    debuglog('Skipping product because Printeers Stock Item cannot be found.');
                    $result[] = 'There was a problem adding product ' . $stockListID . '. Please try again later or contact Printeers if this problem keeps occurring';

                    continue; // Skipping
                }

                // Was an addon requested?
                $addon = '';
                if ($_POST['action'] == 'create_zakeke') {
                    $addon = 'zakeke';
                }

                // If it is Zakeke, is it a print item?
                if ($addon == 'zakeke' && $stockItem->kind != 'print') {
                    $result[] = $stockItem->name . ' cannot be added to Zakeke because it\'s not a print item';
                    continue;
                }

                // Create the product
                $createProductResult = $this->_woo->createProduct($stockItem, $addon);
                $result[] = $createProductResult['message'];

                // Action for Print & Ship Extensions to hook in to
                if ($createProductResult['success']) {
                    do_action('print_and_ship_product_created', $createProductResult['product_id']);
                }
            }
            break;

        // Connect to an existing product
        case 'link':
            foreach ($_POST as $key => $value) {
                // No or wrong information was posted, skipping
                if (empty($value) || !preg_match('/^sku_([a-z0-9\-]+)$/i', $key, $match)) {
                    continue;
                }
                
                $sku = $match[1];
                $title = sanitize_text_field($value);

                // Select the post we want to connect to
                $query = "SELECT ID FROM " . $this->_db->posts . " WHERE post_type = %s AND post_title = %s";
                $query = $this->_db->prepare($query, 'product', $title);
                $product_id = $this->_db->get_var($query);

                // Post was not found, skipping to the next iteration
                if (empty($product_id)) {
                    $result[] = 'Product ' . $title . ' was not found.';
                    continue;
                }
                    
                // Fetch data from Printeers
                $item = $this->_ipp->getStockItem($sku);
                    
                if (is_null($item)) {
                    $result[] = 'Could not connect ' . $item['name'];
                    continue;
                }

                // Add post meta
                $meta = array(
                    'print_and_ship_sku'            => $item->sku,
                    'print_and_ship_enable'         => '1',
                );
                
                // Add all meta to database
                foreach ($meta as $key => $value) {
                    update_post_meta($product_id, $key, $value);
                }
                
                // Run imports
                $this->_woo->importStock($product_id, $item);
                $this->_woo->importAttributes($product_id, $item);
                
                // Add print size when it's a print item
                if ($item->kind == 'print') {
                    $this->_woo->importPrintSize($product_id, $item);
                }
                
                $result[] = $title . ' was connected to SKU ' . $sku;
            }
            break;
        }

        return $result;
    }

    /**
     * Check for and process search
     *
     * @param object $item Product item
     * 
     * @return bool
     */
    private function process_search($item)
    {
        if (isset($_REQUEST['s']) && ! empty($_REQUEST['s'])) {
            $search = sanitize_text_field($_REQUEST['s']);

            if (stristr($item->name, $search) || stristr($item->sku, $search)) {
                return true;
            } else {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if product is still saleable
     *
     * @param object $item Product item
     * 
     * @return bool
     */
    private function _checkLifeCycle($item)
    {
        if (!$item->availability->can_backorder && $item->availability->status == "out-of-stock") {
            return false;
        }
            
        return true;
    }
}
