<?php

require_once __DIR__ . '/../api/FelInvoiceConnect.php';
require_once __DIR__ . '/FelInvoiceInstaller.php';
require_once __DIR__ . '/FelInvoiceRegisterAjaxOrder.php';

/**
 * handles filters and actions for orders
 * @author Patric Eid
 *
 */
class FelInvoiceRegisterOrder {
    /**
     * ajax handler
     * @var FelInvoiceRegisterAjaxOrder
     */
    private $ajaxHandler = null;

    /**
     * defines filters and actions
     */
    public function registerEvents() {
        //add_action('woocommerce_process_shop_order_meta', array($this, 'updateOrder'), 10, 1);
        add_action('woocommerce_admin_order_data_after_order_details', array($this, 'addOrderFields'), 10, 3);

        add_action('woocommerce_order_status_changed', array($this, 'statusChanged'), 10, 3);
        /*
        //new columns in orders view
        add_filter('woocommerce_shop_order_search_fields', array($this, 'addCustomSearchFields'));
        add_filter("manage_edit-shop_order_sortable_columns", array($this, 'sortCustomFields'));
        add_action('manage_shop_order_posts_custom_column' , array($this, 'loadCustomColumnContent'), 10, 2 );
        add_filter('manage_edit-shop_order_columns', array($this, 'addCustomOrderColumns'), 11);
        add_action('pre_get_posts', array($this, 'orderByMetaFields'));
        //adds a astroweb meta box for showing/storing additional information
        add_action('add_meta_boxes', array($this, 'addFelInvoiceOrderBox'));
        */
        //register ajax events
        $this->ajaxHandler = new FelInvoiceRegisterAjaxOrder();
        $this->ajaxHandler->registerEvents();
    }
    
    /**
     * adds a custom astroweb order box
     */
    function addFelInvoiceOrderBox()
    {
        add_meta_box(
            'woocommerce-order-fel-invoice-orderbox',
            'FelInvoice',
            array($this, 'addFelInvoiceOrderBoxContent'),
            'shop_order',
            'side',
            'default'
            );
    }
    
    /**
     * adds content to astroweb custom order box
     * @param stdClass $post
     */
    function addFelInvoiceOrderBoxContent($post) {
        //load order
        $order = wc_get_order($post->ID);
        //we need to know the ajax url
        echo '<input type="hidden" name="fel_invoice_order_id" id="fel_invoice_order_id" value="'.$post->ID.'" />';
        $orderCreated = true;
        if (!$order || $order->get_date_created() == null) {
            //order not created. But we want to display skipping serial number checks
            $orderCreated = false;
        }
        if ($orderCreated) {
            //TODO: if not processing/fulfillment, some values should not be changable anymore
            //priority
            woocommerce_wp_select(
                array(
                    'id'          => '_fel_invoice_priority',
                    'label'       => 'Priorität',
                    'class' => 'fel-invoice-select',
                    'desc_tip'    => 'true',
                    'value'       => get_post_meta($post->ID, '_fel_invoice_priority', true),
                    'description' => __( 'Priorität des Auftrages in FelInvoice', 'woocommerce' ),
                    'options' => array(
                        '3' => "Normal",
                        '2' => "Hoch",
                        '1' => "Sehr hoch"
                    )
                )
                );
            woocommerce_wp_text_input(
                array(
                    'id'          => '_fel_invoice_linked_order',
                    'label'       => 'Auftrag',
                    'desc_tip'    => 'true',
                    'class' => 'fel-invoice-select',
                    'value' => get_post_meta($post->ID, '_fel_invoice_linked_order', true),
                    'description' => __('Verlinkter Auftrag, zum Beispiel für Austausch-Aufträge', 'woocommerce')
                )
            );
        }
        if ($orderCreated) {
            echo '<hr />';
        }
        echo '<h4>Retoure</h4>';
        $createWECheck = get_post_meta($post->ID, '_fel_invoice_return_we', true);
        woocommerce_wp_checkbox(
            array(
                'id'          => '_fel_invoice_return_we',
                'label'       => 'WE',
                'desc_tip'    => 'true',
                'value' => $createWECheck,
                'class' => 'fel-invoice-select',
                'description' => __('Retoure auf Wareneingang', 'woocommerce'),
            )
            );
        $createWACheck = get_post_meta($post->ID, '_fel_invoice_return_wa', true);
        woocommerce_wp_checkbox(
            array(
                'id'          => '_fel_invoice_return_wa',
                'label'       => 'WA',
                'desc_tip'    => 'true',
                'value' => $createWACheck,
                'class' => 'fel-invoice-select',
                'description' => __('Retoure auf Warenausgang', 'woocommerce'),
            )
            );
        $skipSerialNumberCheck = get_post_meta($post->ID, '_fel_invoice_return_checkserial', true);
        woocommerce_wp_checkbox(
            array(
                'id'          => '_fel_invoice_return_checkserial',
                'label'       => 'Kein SN-Check',
                'desc_tip'    => 'true',
                'value' => $skipSerialNumberCheck,
                'cbvalue' => 'yes',
                'class' => 'fel-invoice-select',
                'description' => __('Verhindert bei Retouren die Prüfung der Seriennummer', 'woocommerce'),
            )
            );
    }

    /**
     * adds a custom search field
     * @param array $search_fields
     * @return array
     */
    public function addCustomSearchFields($search_fields) {
        $search_fields[] = 'FEL_INVOICE_PARTIALLY_REASON';
        return $search_fields;
    }

    /**
     * called for sorting custom columns
     * @param array $columns
     * @return array
     */
    function sortCustomFields($columns)
    {
        $columns['fel-invoice-partially'] = 'FEL_INVOICE_PARTIALLY_REASON';
        $columns['fel-invoice-download'] = 'FEL_INVOICE_DOWNLOAD_ORDERKEY';
        $columns['fel-invoice-priority'] = '_fel_invoice_priority';
        $columns['fel-invoice-fulfill'] = 'fulfillment-blocked';
        return $columns;
    }
    
    /**
     * called for displaying custom fields
     * @param array $column
     * @param int $post_id
     */
    function loadCustomColumnContent($column, $post_id)
    {
        switch ($column)
        {
            case 'fel-invoice-fulfill':
                echo get_post_meta($post_id, "fulfillment-blocked", true);
                break;
            case 'fel-invoice-partially':
                echo get_post_meta($post_id, 'FEL_INVOICE_PARTIALLY_REASON', true);
                break;
            case 'fel-invoice-download':
                echo get_post_meta($post_id, 'FEL_INVOICE_DOWNLOAD_ORDERKEY', true);
                break;
            case 'fel-invoice-priority':
                $prio = get_post_meta($post_id, '_fel_invoice_priority', true);
                if (is_array($prio)) {
                    if (array_key_exists(0, $prio)) {
                        if (!is_array($prio[0])) {
                            $prio = $prio[0];
                        }
                        else {
                            $prio = 3;
                        }
                    }
                    else {
                        $prio = 3;
                    }
                }
                echo ($prio > 0 && $prio < 4 ? $prio : 3);
                break;
        }
    }
    
    /**
     * allows ordering by meta fields
     * @param $query
     */
    function orderByMetaFields($query) {
        if(!is_admin()) {
            return;
        }
        $orderby = $query->get('orderby');
        switch ($orderby) {
            case 'fulfillment-blocked':
                $query->set('meta_query', 'fulfillment-blocked');
                $query->set('orderby', 'meta_value');
                break;
            case '_fel_invoice_priority':
                $query->set('meta_query', '_fel_invoice_priority');
                $query->set('orderby', 'meta_value_num');
                break;
        }
    }

    /**
     * adds custom order columns to admin view
     * @param array $columns
     * @return array
     */
    function addCustomOrderColumns($columns)
    {
        $columns['fel-invoice-priority'] = "Prio";
        $columns['fel-invoice-partially'] = "Grund";
        $columns['fel-invoice-fulfill'] = "Blockiert";
        $columns['fel-invoice-download'] = "Download";
        return $columns;
    }

    /**
     * checks whether order status can be changed to fulfillment
     * @param int $post_id
     * @throws Exception
     */
    public function orderLoaded($post_id) {
        $order = wc_get_order($post_id);
        if ($order->get_status() == 'processing') {
            //try to reserve goods
            $reload = get_post_meta($post_id, "_fel-invoice-reload");
        }
    }
    
    /**
     * adds custom fields to order details
     */
    public function addOrderFields() {
        global $woocommerce, $post;

        //notice
        woocommerce_wp_textarea_input(
            array(
                'id'          => '_fel_invoice_uuid',
                'label'       => __( 'Factura UUID (FelInvoice)', 'woocommerce' ),
                'desc_tip'    => 'true',
                'value'       => get_post_meta( $post->ID, '_fel_invoice_uuid', true ),
                'description' => __( 'generated from FelInvoice.', 'woocommerce' ),
                'custom_attributes' => array('readonly' => 'readonly'),
            )
        );

        woocommerce_wp_textarea_input(
            array(
                'id'          => '_fel_invoice_uuid_cancel',
                'label'       => __( 'Anula UUID (FelInvoice)', 'woocommerce' ),
                'desc_tip'    => 'true',
                'value'       => get_post_meta( $post->ID, '_fel_invoice_uuid_cancel', true ),
                'description' => __( 'generated from FelInvoice.', 'woocommerce' ),
                'custom_attributes' => array('readonly' => 'readonly'),
            )
        );

        $this->orderLoaded($post->ID);
    }

    /**
     * called when an order is being updated
     * @param int $order_id
     * @throws Exception
     */
    public function updateOrder($post_id) {
        return;
        $woocommerce_text_field = $_POST['_fel_invoice_delivery'];
        if( !empty( $woocommerce_text_field ) ) {
            update_post_meta( $post_id, '_fel_invoice_delivery', esc_attr( $woocommerce_text_field ) );
        }
        update_post_meta($post_id, '_fel_invoice_linked_order', esc_attr($_POST['_fel_invoice_linked_order']));
        $woocommerce_text_field = $_POST['_fel_invoice_return_note'];
        if( !empty( $woocommerce_text_field ) ) {
            update_post_meta( $post_id, '_fel_invoice_return_note', esc_attr( $woocommerce_text_field ) );
        }
        $woocommerce_text_field = isset( $_POST['_fel_invoice_order_blocked'] ) ? 'yes' : 'no';
        update_post_meta( $post_id, '_fel_invoice_order_blocked', $woocommerce_text_field );
        //save priority
        $wasPriority = get_post_meta($post_id, '_fel_invoice_priority');
        $currentPriority = (int)$_POST['_fel_invoice_priority'];
        update_post_meta($post_id, '_fel_invoice_priority', $currentPriority);
        //wareneingang check
        update_post_meta($post_id, '_fel_invoice_return_we',
            isset($_POST['_fel_invoice_return_we']) ? $_POST['_fel_invoice_return_we'] : "");
        //warenausgang check
        update_post_meta($post_id, '_fel_invoice_return_wa',
            isset($_POST['_fel_invoice_return_wa']) ? $_POST['_fel_invoice_return_wa'] : "");
        //skip serial number check
        update_post_meta($post_id, '_fel_invoice_return_checkserial',
            isset($_POST['_fel_invoice_return_checkserial']) ? $_POST['_fel_invoice_return_checkserial'] : "");
        //send to connect?
        $handler = new FelInvoicePluginOrders();
        $orderObj = wc_get_order($post_id);
        
        if ($_POST['order_status'] == "wc-processing") {
            //save dispatcher and profile
            if (isset($_POST['_fel_invoice_dispatcher']) && isset($_POST['_fel_invoice_dispatcher_product'])) {
                update_post_meta($post_id, '_fel_invoice_dispatcher', $_POST['_fel_invoice_dispatcher']);
                update_post_meta($post_id, '_fel_invoice_dispatcher_product', $_POST['_fel_invoice_dispatcher_product']);
            }
        }else if ($_POST['order_status'] == "wc-cancelled" || $_POST['order_status'] == "wc-fel-invoice-cancel") {
            sleep(1);
            $this->orderCancelled($post_id);
        }else {
            //update priority
            if ($currentPriority != $wasPriority && $handler->isOrderDownloaded($post_id)) {
                if (!$handler->getConnect()->updateOrderPriority($post_id, $currentPriority)) {
                    //reset priority on error
                    update_post_meta($post_id, '_fel_invoice_priority', $wasPriority);
                }
            }
        }
        
        if ($_POST['order_status'] == "wc-return-announced" || $_POST['order_status'] == "wc-reclam-announced") {
            // 2018-02-09, Patric Eid: handled by separat event
            //check announced returns
            /*$order = $handler->getReturnObject($post_id, $orderObj);
             if ($order) {
             //not sent to astroweb yet
             if ($handler->getConnect()->transmitReturn($order, $post_id)) {
             $handler->setOrderDownloaded($post_id, FelInvoicePluginOrders::$FEL_INVOICE_RETURN_ORDERKEY);
             }
             }*/
        }else {
            update_post_meta($post_id, "_fel-invoice-reload", 1);
        }
    }

    public function statusChanged($order_id, $statusFrom, $statusTo) {
        global $wpdb;

        $felCreateInvoice = get_option('_fel_invoice_trigerred_create_invoice');
        if(strpos($felCreateInvoice, $statusTo) !== false) {
            $connect = new FelInvoiceConnect();
            $connect->factura($order_id);
        }
        $felCancelInvoice = get_option('_fel_invoice_trigerred_cancel_invoice');
        foreach($felCancelInvoice as $orderTriggered) {
            if(strpos($orderTriggered, $statusTo) !== false) {
                $connect = new FelInvoiceConnect();
                $connect->anulacion($order_id);
                break;
            }
        }
    }
}