<?php

require_once __DIR__ . '/../api/FelInvoiceConnect.php';

/**
 * handles filters and actions for products
 * @author Patric Eid
 *
 */
class FelInvoiceRegisterProduct {

	/**
	 * sets actions
	 */
	public function registerEvents() {
	    /*
		add_action( 'woocommerce_product_after_variable_attributes', array($this, 'extendVariationsMetabox'), 20, 3 );
		add_action( 'woocommerce_save_product_variation', array($this, 'saveProductVariation'), 20, 2 );
		add_action( 'woocommerce_process_product_meta', array($this, 'updateProduct'), 10, 2 );
		add_action('save_post', array($this, 'savePost'), 10, 3);
		add_filter('woocommerce_product_data_tabs', array($this, 'addFelInvoiceTab'));
		add_action('woocommerce_product_data_panels', array($this, 'loadFelInvoiceTabContent'));

        // ADDING A CUSTOM COLUMN TITLE TO ADMIN PRODUCTS LIST
        add_filter( 'manage_edit-product_columns', array($this, 'addFelInvoiceColumn'),11);
        // ADDING THE DATA FOR EACH PRODUCTS BY COLUMN (EXAMPLE)
        add_action( 'manage_product_posts_custom_column' , array($this, 'loadFelInvoiceColumnContent'), 10, 2 );
        */
	}
	
	/**
	 * loads astroweb tab
	 */
	public function loadFelInvoiceTabContent() {
        $connect = new FelInvoiceConnect();
        $sku_options = $connect->getProductNameList(true);
        array_unshift($sku_options, '');

		$id = get_the_ID();
		echo '<div id="fel_invoice_product_data" class="panel woocommerce_options_panel hidden">';
		woocommerce_wp_select(
			array(
				'id'          => '_fel_invoice_sku',
				'label'       => 'FelInvoice Product',
				'desc_tip'    => 'true',
				'value'       => get_post_meta($id, '_fel_invoice_sku', true),
				'description' => __( 'select matched product of AstroWeb', 'woocommerce' ),
				'options' => $sku_options
			)
		);
		echo '</div>';
	}

	/**
	 * adds a astroweb tab
	 * @param array $tabs
	 * @return array
	 */
	public function addFelInvoiceTab($tabs) {
		$tabs['fel_invoice_tab'] = array(
			'label' 	=> 'FelInvoice',
			'priority' 	=> 150,
			'class' 	=> array('fel_invoice_product_data'),
			'target' 	=> 'fel_invoice_product_data'
		);
		return $tabs;	
	}
	
	/**
	 * called when a post is being saved
	 * @param int $post_id
	 * @param WP_POST $post
	 * @param boolean $update
	 */
	public function savePost($post_id, $post, $update){
	    /*
		$connect = new FelInvoiceConnect();
		update_post_meta($post_id, '_fel_invoice_upd_connect', $connect->transmitProductData($post));
	    */
		return;
	}
	
	/**
	 * called when updating a product
	 * @param int $post_id
	 */
	public function updateProduct( $post_id ){
        $connect = new FelInvoiceConnect();
        $sku_options = $connect->getProductNameList(false);
        array_unshift($sku_options, '');

		$aw_sku = $_POST['_fel_invoice_sku'];
		if( !empty( $aw_sku ) ) {
		    $prodid_name = $sku_options[$aw_sku];
            update_post_meta($post_id, '_fel_invoice_sku', esc_attr($aw_sku));
            if(!empty($prodid_name)) {
                $patterns = explode('|||', $prodid_name);
                if(!empty($patterns[0]) && !empty($patterns[1])) {
                    update_post_meta($post_id, '_fel_invoice_prodid', esc_attr($patterns[0]));
                    update_post_meta($post_id, '_fel_invoice_prodname', esc_attr($patterns[1]));
                }
            }
		} else {
            update_post_meta( $post_id, '_fel_invoice_sku', esc_attr( 0 ) );
            update_post_meta( $post_id, '_fel_invoice_prodid', esc_attr( 0 ) );
            update_post_meta( $post_id, '_fel_invoice_prodname', esc_attr( '' ) );
        }

	}
	
	/**
	* Save extra meta info for variable products
	*
	* @param int $variation_id
	* @param int $i
	* return void
	*/
	public function saveProductVariation( $variation_id, $i ){
        $connect = new FelInvoiceConnect();
        $sku_options = $connect->getProductNameList(false);
        array_unshift($sku_options, '');

		if ( isset( $_POST['variation_fel_invoice_sku'][$i] ) ) {
			// sanitize data in way that makes sense for your data type
			$custom_data = ( trim( $_POST['variation_fel_invoice_sku'][$i]  ) === '' ) ? '' : sanitize_title( $_POST['variation_fel_invoice_sku'][$i] );
            update_post_meta( $variation_id, '_fel_invoice_sku', $custom_data );
            $prodid_name = $sku_options[$custom_data];
            if(!empty($prodid_name)) {
                $patterns = explode('|||', $prodid_name);
                if(!empty($patterns[0]) && !empty($patterns[1])) {
                    update_post_meta( $variation_id, '_fel_invoice_prodid', $patterns[0] );
                    update_post_meta( $variation_id, '_fel_invoice_prodname', $patterns[1] );
                }
            }

		} else {
            update_post_meta( $variation_id, '_fel_invoice_sku', 0 );
            update_post_meta( $variation_id, '_fel_invoice_prodid', 0 );
            update_post_meta( $variation_id, '_fel_invoice_prodname', '' );
        }
		$this->savePost($variation_id, get_post($variation_id), true);
	}
	
	/**
	* Add new inputs to each variation
	*
	* @param string $loop
	* @param array $variation_data
	*/
	public function extendVariationsMetabox( $loop, $variation_data, $variation ){
        $connect = new FelInvoiceConnect();
        $sku_options = $connect->getProductNameList(true);
        array_unshift($sku_options, '');

	    $aw_sku = get_post_meta( $variation->ID, '_fel_invoice_sku', true );
	    echo '<div class="variable_custom_field">
	            <p class="form-row form-row-first">
	               <label>Seriennummer aktiv:</label>
	               <select name="variation_fel_invoice_sku['.$loop.']">';
	    foreach($sku_options as $key=>$value) {
	        echo '<option value="'.$key.'"'.($key==$aw_sku ? " selected" : "").'>'.$value.'</option>';
        }
	    echo '</select>
	            </p>
	        </div>';
	}

	/*
	 * Mikalai-added
	 */
    function addFelInvoiceColumn($columns)
    {
        // <img src="http://localhost:8080/woo/wp-content/plugins/fel-invoice-api/plugin/../images/felinvoice.png" alt="">
        //add columns
        return array_slice( $columns, 0, 4, true )
            + array( 'fel_invoice_sku' => __( '<img src="'.plugin_dir_url(__FILE__).'../images/felinvoice.png" alt="AstroWeb">', 'woocommerce') )
            + array_slice( $columns, 4, NULL, true );
    }

    /*
	 * Mikalai-added
	 */
    function loadFelInvoiceColumnContent( $column, $product_id )
    {
        global $post;

        // HERE get the data from your custom field (set the correct meta key below)
        $aw_prodname = get_post_meta( $product_id, '_fel_invoice_prodname', true);

        switch ( $column )
        {
            case 'fel_invoice_sku' :
                echo $aw_prodname; // display the data
                break;
        }
    }
}