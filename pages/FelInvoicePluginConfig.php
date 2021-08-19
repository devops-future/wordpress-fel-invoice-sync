<?php

class FelInvoicePluginConfig
{
    public function __construct() {
    }

    /**
     * adds an input field for saving user authkey
     */
    private function set_user_auth_keys() {
        $auth_keys = array(
            array("form_key"=>"fel_invoice_auth_user",  "option_key"=>"fel_invoice_plugin_auth_user",   "label"=>"FEL_USER",    "placeholder"=>"82280363"),
            array("form_key"=>"fel_invoice_auth_key",   "option_key"=>"fel_invoice_plugin_auth_key",    "label"=>"FEL_APIKEY",  "placeholder"=>"XXjBo6QibrrpOgYtnp7nlOC"),
            //array("form_key"=>"fel_invoice_app_listen", "option_key"=>"fel_invoice_plugin_app_listen",  "label"=>"APP_LISTEN",  "placeholder"=>"8080"),
        );

        foreach ($auth_keys as $auth) {
            if (isset($_REQUEST[$auth['form_key']]) && get_option($auth['option_key']) != trim($_REQUEST[$auth['form_key']])) {
                $value = trim($_REQUEST[$auth['form_key']]);
                if (!add_option($auth['option_key'], $value)) {
                    update_option($auth['option_key'], $value);
                }
            }
            $option_value = get_option($auth['option_key']);
            $place_holder = isset($auth['placeholder']) ? $auth['placeholder'] : "Please enter the value you received from FelInvoice";

            echo '<p class="fel-invoice-config"><label for="' . $auth['form_key'] . '">' . $auth['label'] . '</label>';
            echo '<input required type="text" name="' . $auth['form_key'] . '" id="' . $auth['form_key'] .
                '" class="form-control" value="' . $option_value . '" placeholder="' . $place_holder. '"></p>';
        }

        if (isset($_REQUEST["check_connection"]))
        {
            $api = new FelInvoiceConnect();
            if($api->checkAuthKey()) {
                echo '<p>Key authentication: Correct</p>';
            } else {
                echo '<p>Key authentication: Incorrect</p>';
            }
        }
    }

    /**
     * generates form elements
     */
    private function set_order_trigger_field() {
        global $wpdb;
        $order_status = wc_get_order_statuses();

        $selected_value = get_option('_fel_invoice_trigerred_create_invoice');
        if (isset($_REQUEST['_fel_invoice_trigerred_create_invoice']) && $selected_value != trim($_REQUEST['_fel_invoice_trigerred_create_invoice'])) {
            $selected_value = trim($_REQUEST['_fel_invoice_trigerred_create_invoice']);
            update_option('_fel_invoice_trigerred_create_invoice', $selected_value);
        }
        echo '<p class="fel-invoice-config"><label for="_fel_invoice_trigerred_create_invoice">Estado del pedido que crea una factura</label>';
        echo '<select required name="_fel_invoice_trigerred_create_invoice" id="_fel_invoice_trigerred_create_invoice" class="form-control" placeholder="Select order state">';
        foreach ($order_status as $key => $status) {
            echo '<option value="'.$key.'"'.($selected_value == $key ? ' selected="selected"' : '').'>'.$status.'</option>';
        }
        echo '</select></p>';

        $selected_value = get_option('_fel_invoice_trigerred_cancel_invoice');
        if (isset($_REQUEST['_fel_invoice_trigerred_cancel_invoice'])) {
            $selected_value = is_array($_REQUEST['_fel_invoice_trigerred_cancel_invoice']) ? $_REQUEST['_fel_invoice_trigerred_cancel_invoice'] : array($_REQUEST['_fel_invoice_trigerred_cancel_invoice']);
            update_option('_fel_invoice_trigerred_cancel_invoice', $selected_value);
        }
        echo '<p class="fel-invoice-config"><label for="_fel_invoice_trigerred_cancel_invoice">Orden indica que cancela una factura</label>';
        echo '<select required name="_fel_invoice_trigerred_cancel_invoice[]" id="_fel_invoice_trigerred_cancel_invoice" class="form-control" placeholder="Select order states" multiple="multiple">';
        foreach ($order_status as $key => $status) {
            echo '<option value="'.$key.'"'.(in_array($key, $selected_value) ? ' selected="selected"' : '').'>'.$status.'</option>';
        }
        echo '</select></p>';
    }

    public function buildMarkup(){
        include __DIR__ . '/fel_invoice_plugin_configHTML.php';
    }
}