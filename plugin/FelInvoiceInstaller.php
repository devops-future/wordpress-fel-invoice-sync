<?php
require_once __DIR__ . '/../plugin/FelInvoiceRegisterOrder.php';
require_once __DIR__ . '/../api/FelInvoiceConnect.php';

/**
 * is used for setting basic actions, e.g. adding FelInvoice to menu and in later versions this class
 * might be used for upgrading to newer versions
 * @author Patric Eid
 *
 */
class FelInvoiceInstaller {
	private $supportMail = "woo-plugin@astroweb.com";
	
	/**
	 * adds actions/filters
	 */	
	public function registerEvents() {
		add_action('admin_menu', array($this, 'addMenu'));
		add_action('admin_head', array($this, 'addFelInvoiceStyle'));
	}

    private function unregisterWebhooksFromFelInvoice()
    {
        delete_option("fel_invoice_hook_token");
        $connect = new FelInvoiceConnect();
        //$connect->unregisterWebHooks();
    }

	/**
	 * it will be called, when the plugin is disabled
	 */
	public function tearDown() {
        //wp_mail($this->supportMail, "Deactivate", "Plugin deactivated: ".WEMALO_BASE);
        $this->clearCronJob();
		//unregister status update webhook
		$this->unregisterWebhooksFromFelInvoice();
        delete_option("fel_invoice_plugin_auth_key");
	}

    /**
     * deactivates checking cron job
     */
    private function clearCronJob() {
        wp_clear_scheduled_hook('fel_invoice_creditcode_check');
    }

    /**
     * sets up the wemalo plugin
     */
    public function setUp() {
        //wp_mail($this->supportMail, "Activate", "Plugin activated ".WEMALO_BASE);
        delete_option('_fel_invoice_mail_content');
        //create tables
        //$this->createTables();
        //set up cron job
        //$this->setUpCronJob();
    }

    /**
     * sets up cronJobs
     */
    public function setUpCronJob() {
        if (!wp_next_scheduled('fel_invoice_creditcode_check')) {
            wp_schedule_event(time(), 'hourly', 'fel_invoice_creditcode_check');
        }
        add_action('fel_invoice_creditcode_check', array($this, 'checkDelayedCreditCode'));
    }

    /**
     * checks order status of partially reserved orders
     */
    public function checkDelayedCreditCode() {
        $pluginOrder = new FelInvoiceRegisterOrder();
        global $wpdb;
        $table_name = $wpdb->prefix."fel_invoice_delayed_creditcodes";
        $sql = "SELECT * FROM ".$table_name." WHERE is_precessed=0;";
        $results = $wpdb->get_results($sql);
        foreach($results as $row) {
            $aw_delayed_prod_ids = json_decode($row->aw_delayed_prod_ids, true);
            $aw_delayed_prod_ids = $pluginOrder->attachFelInvoiceCreditCode($row->order_id, $aw_delayed_prod_ids);
            if(empty($aw_delayed_prod_ids)) {
                $sql = "UPDATE ".$table_name." SET is_precessed=1, aw_delayed_prod_ids=%s WHERE id=".$row->id.";";
                $sql = $wpdb->prepare($sql, json_encode($aw_delayed_prod_ids));
                $wpdb->query($sql);
            } else {
                $sql = "UPDATE ".$table_name." SET is_precessed=0, aw_delayed_prod_ids=%s WHERE id=".$row->id.";";
                $sql = $wpdb->prepare($sql, json_encode($aw_delayed_prod_ids));
                $wpdb->query($sql);
            }
        }
    }

    /**
     * creates database tables
     */
    public function createTables() {
        //create table for return-shippment
        global $wpdb;
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        $charset_collate = $wpdb->get_charset_collate();
        //table for storing information about returned positions
        $table_name = $wpdb->prefix."fel_invoice_delayed_creditcodes";
        $sql = "CREATE TABLE $table_name (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			order_id mediumint(9) NOT NULL DEFAULT 0,
			aw_delayed_prod_ids varchar(2047) NOT NULL DEFAULT '',
			is_precessed tinyint(1) NOT NULL DEFAULT 0,
			PRIMARY KEY id (id)
		) $charset_collate;";
        dbDelta($sql);
    }

	/**
	 * create admin-menue for the plugin
	 */
	public function addMenu() {
		add_menu_page('FelInvoice API - das Wordpress-Plugin für FelInvoice', 'FelInvoice', 'manage_options', __FILE__,
		'fel_invoice_plugin_user', plugin_dir_url(__FILE__).'../images/felinvoice.png');
	}
	
	/**
	 * adds font icons in order table etc.
	 */
	public function addFelInvoiceStyle() {
		echo '<style>
				.widefat .column-order_status mark.return-booked:after{
					font-family:WooCommerce;
					speak:none;
					font-weight:400;
					font-variant:normal;
					text-transform:none;
					line-height:1;
					-webkit-font-smoothing:antialiased;
					margin:0;
					text-indent:0;
					position:absolute;
					top:0;
					left:0;
					width:100%;
					height:100%;
					text-align:center;
				}
				.widefat .column-order_status mark.return-booked:after{
					content:"\e014";
					color:#e37622;
				}
			
				.widefat .column-order_status mark.return-announced:after{
					font-family:WooCommerce;
					speak:none;
					font-weight:400;
					font-variant:normal;
					text-transform:none;
					line-height:1;
					-webkit-font-smoothing:antialiased;
					margin:0;
					text-indent:0;
					position:absolute;
					top:0;
					left:0;
					width:100%;
					height:100%;
					text-align:center;
				}
				.widefat .column-order_status mark.return-announced:after{
					content:"\e001";
					color:#e37622;
				}
                .widefat .column-order_status mark.reclam-booked:after{
					font-family:WooCommerce;
					speak:none;
					font-weight:400;
					font-variant:normal;
					text-transform:none;
					line-height:1;
					-webkit-font-smoothing:antialiased;
					margin:0;
					text-indent:0;
					position:absolute;
					top:0;
					left:0;
					width:100%;
					height:100%;
					text-align:center;
				}
				.widefat .column-order_status mark.reclam-booked:after{
					content:"\e014";
					color:#e37622;
				}
			
				.widefat .column-order_status mark.reclam-announced:after{
					font-family:WooCommerce;
					speak:none;
					font-weight:400;
					font-variant:normal;
					text-transform:none;
					line-height:1;
					-webkit-font-smoothing:antialiased;
					margin:0;
					text-indent:0;
					position:absolute;
					top:0;
					left:0;
					width:100%;
					height:100%;
					text-align:center;
				}
				.widefat .column-order_status mark.reclam-announced:after{
					content:"\e001";
					color:#e37622;
				}
			
			.widefat .column-order_status mark.fel-invoice-cancel:after{
					font-family:WooCommerce;
					speak:none;
					font-weight:400;
					font-variant:normal;
					text-transform:none;
					line-height:1;
					-webkit-font-smoothing:antialiased;
					margin:0;
					text-indent:0;
					position:absolute;
					top:0;
					left:0;
					width:100%;
					height:100%;
					text-align:center;
				}
				.widefat .column-order_status mark.fel-invoice-cancel:after{
					content:"\e013";
					color:#e37622;
				}
				
				.widefat .column-order_status mark.fel-invoice-fulfill:after{
					font-family:WooCommerce;
					speak:none;
					font-weight:400;
					font-variant:normal;
					text-transform:none;
					line-height:1;
					-webkit-font-smoothing:antialiased;
					margin:0;
					text-indent:0;
					position:absolute;
					top:0;
					left:0;
					width:100%;
					height:100%;
					text-align:center;
				}
				.widefat .column-order_status mark.fel-invoice-fulfill:after{
					content:"\e019";
					color:#e37622;
				}
				
				.widefat .column-order_status mark.fel-invoice-block:after{
					font-family:WooCommerce;
					speak:none;
					font-weight:400;
					font-variant:normal;
					text-transform:none;
					line-height:1;
					-webkit-font-smoothing:antialiased;
					margin:0;
					text-indent:0;
					position:absolute;
					top:0;
					left:0;
					width:100%;
					height:100%;
					text-align:center;
					opacity: 0.5;
				}
				.widefat .column-order_status mark.fel-invoice-block:after{
					content:"\e019";
					color:#e37622;
				}
				
				.fel-invoice-table {
					width:100%;
					max-width:800px;
					margin-top: 50px;
				}
				
				.fel-invoice-table thead {
				    display: table-header-group;
				    vertical-align: middle;
				    border-color: inherit;
				}
				
				.fel-invoice-table>tbody>tr>td, .fel-invoice-table>tbody>tr>th, .fel-invoice-table>tfoot>tr>td, .fel-invoice-table>tfoot>tr>th, .fel-invoice-table>thead>tr>td, .fel-invoice-table>thead>tr>th {
				    padding: 8px;
				    line-height: 1.42857143;
				    vertical-align: top;
				    border-top: 1px solid #ddd;
				}
				
				.fel-invoice-table>thead>tr>th {
				    vertical-align: bottom;
				    border-bottom: 2px solid #ddd;
					border-top: 0;
				}
	  </style>';
	}
}
