<?php

/*
Plugin Name: FEL Invoice Synchronization
Plugin URI: https://thestores.site/
Description: FelInvoice API is being used for Shops using WooCommerce to communicate with FelInvoice
Version: 1.2.0
Author: Mario
Author URI: https://thestores.site/
Update Server: https://thestores.site/
Min WP Version: 3.0.1
Max WP Version: 5.4.0
License: GPL2

WC requires at least: 3.0.0
WC tested up to: 4.2.0

FelInvoice API is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
any later version.
*/

define("FEL_INVOICE_BASE", plugin_dir_url(__FILE__));

//register rest handler, actions and filters
require_once __DIR__ . '/api/FelInvoiceHandler.php';
require_once __DIR__ . '/pages/FelInvoicePluginConfig.php';
require_once __DIR__ .'/plugin/FelInvoiceInstaller.php';
require_once __DIR__ .'/plugin/FelInvoiceRegisterOrder.php';
require_once __DIR__ .'/plugin/FelInvoiceRegisterProduct.php';

$installer = new FelInvoiceInstaller();
$installer->registerEvents();
$regOrder = new FelInvoiceRegisterOrder();
$regOrder->registerEvents();
/*
$regProduct = new FelInvoiceRegisterProduct();
$regProduct->registerEvents();
$handler = new FelInvoiceHandler();
$handler->registerEvents();
*/
if(!function_exists('fel_invoice_plugin_user')){
    function fel_invoice_plugin_user() {
        $user = new FelInvoicePluginConfig();
        $user->buildMarkup();
    }
}


//create tables on activation
register_activation_hook( __FILE__, 'fel_invoice_activation' );

/**
 * called for activating astroweb
 */
function fel_invoice_activation() {
	$installer = new FelInvoiceInstaller();
	$installer->setUp();
}

register_deactivation_hook(__FILE__, 'fel_invoice_deactivation');

/**
 * called when plugin is being deactivated
 */
function fel_invoice_deactivation() {
	$installer = new FelInvoiceInstaller();
	$installer->tearDown();
}

function fel_invoice_register_plugin_styles() {

	wp_register_style( 'astroweb', plugins_url( 'css/astrowebplugin-style.css', __FILE__ ) , array() , fel_invoice_css_js_suffix() );
	wp_enqueue_style( 'astroweb' );
}

function fel_invoice_register_plugin_scripts() {
	// make sure jQuery is loaded BEFORE our script as we are using it
	wp_register_script( 'fel-invoice-order', plugins_url( 'js/astroweb.order.js', __FILE__ ), array( 'jquery' ) , fel_invoice_css_js_suffix() );
	wp_enqueue_script( 'fel-invoice-order' );
	wp_localize_script('fel-invoice-order', 'astrowebOrder', array('ajax_url' => admin_url('admin-ajax.php')));
}

// load JS and CSS files on admin pages only
add_action('admin_enqueue_scripts', 'fel_invoice_register_plugin_styles' );
add_action('admin_enqueue_scripts', 'fel_invoice_register_plugin_scripts' );

function fel_invoice_autoload($class) {
	if ($class == "FelInvoiceInterface") {
		require_once __DIR__ .'/plugin/FelInvoiceInterface.php';
	}
}

function fel_invoice_css_js_suffix() {
    $result = '';
    $pluginInfo = get_plugin_data( __FILE__, $markup = false, $translate = false);
    if (is_array($pluginInfo) && isset($pluginInfo['Version'])) {
        $result = $pluginInfo['Version'];
    }
    return $result;
}

spl_autoload_register('fel_invoice_autoload');
