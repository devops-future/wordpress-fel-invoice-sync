<?php

require_once __DIR__.'/FelInvoiceConnect.php';
require_once __DIR__.'/FelInvoiceException.php';
require_once __DIR__.'/FelInvoiceBasic.php';

/**
 * registers api rest calls
 * @author Patric Eid
 *
 */
class FelInvoiceHandler extends FelInvoiceBasic {
	private $products;
	private $orders;
	private $stock;
	
	/**
	 * setting app actions
	 */
	public function registerEvents() {
		add_action('rest_api_init', array( $this, 'register'));
	}
	
	/**
	 * registeres product routes
	 * @param string $namespace
	 */
	private function registerProductRoutes($namespace) {
		$this->registerRoute($namespace, 'getall/(?P<timestamp>\d+)', 'getProducts');
		$this->registerRoute($namespace, '(?P<post_id>\d+)/', 'getProductById');
	}
	
	/**
	 * registers stock routes
	 * @param string $namespace
	 */
	private function registerStockRoutes($namespace) {
		$this->registerRoute($namespace, 'alt/set/(?P<post_id>\d+)', 'setAlternativeStock', 'PUT');
		$this->registerRoute($namespace, 'set/(?P<post_id>\d+)', 'setStock', 'PUT');
		$this->registerRoute($namespace, 'lot/(?P<post_id>\d+)', 'setLot', 'PUT');
	}
	
	/**
	 * registers a route
	 * @param string $namespace
	 * @param string $route
	 * @param string $callback
	 * @param string $method
	 */
	private function registerRoute($namespace, $route, $callback, $method="GET") {
		register_rest_route( $namespace, $route, array(
			'methods'   => $method,
			'callback'  => array($this, $callback)
		));
	}
	
	/**
	 * registers order routes
	 * @param String $namespace
	 */
	private function registerOrderRoutes($namespace) {
		$this->registerRoute($namespace, 'all/(?P<max>\d+)/', 'getOrders');
		$this->registerRoute($namespace, '(?P<order_id>\d+)', 'getOrder');
		$this->registerRoute($namespace, 'return/announced/(?P<max>\d+)', 'getReturns');
		$this->registerRoute($namespace, 'completed/(?P<order_id>\d+)', 'setOrderCompleted', 'PUT');
		$this->registerRoute($namespace, 'downloaded/(?P<order_id>\d+)', 'setOrderDownloaded', 'PUT');
		$this->registerRoute($namespace, 'track/(?P<order_id>\d+)', 'addTrackingInfo', 'PUT');
		$this->registerRoute($namespace, 'return/booked/(?P<order_id>\d+)', 'returnOrderBooked', 'PUT');
		$this->registerRoute($namespace, 'cancel/(?P<order_id>\d+)', 'cancelOrder', 'PUT');
		$this->registerRoute($namespace, 'fulfillment/(?P<order_id>\d+)', 'fulfillment', 'PUT');
		$this->registerRoute($namespace, 'addItemInformationInOrder/(?P<order_id>\d+)', 'addItemInformationInOrder', 'PUT');
		$this->registerRoute($namespace, 'fulfillmentBlocked/(?P<order_id>\d+)', 'fulfillmentBlocked', 'PUT');
		$this->registerRoute($namespace, 'transmit/(?P<order_id>\d+)', 'transmitOrder', 'PUT');
		$this->registerRoute($namespace, 'hook/status', 'statusHook', 'POST');
        $this->registerRoute($namespace, 'track', 'addTrackingInfo', 'POST');
    }
	
	/**
	 * called when status updates where submitted by astroweb backend
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function statusHook(WP_REST_Request $request) {
		try {
			//check if hook is activated
			$hook = get_option("fel_invoice_hook_token");
			$params = $request->get_params();
			if ($hook) {
				if ($hook === $params['token']) {
					$eventData = $params['eventData'];
					$status = $eventData['statusName'];
					if ($status == "CANCELLED") {
						return $this->generateOutput("skip ".$status);
					}
					$orderId = $eventData['externalId'];
					//get current status from astroweb
					$connect = new FelInvoiceConnect();
					$json = $connect->getCurrentStatus($orderId);
					if ($json) {
						$this->orders->handleOrderStatus($orderId, $json, $status);
					}
					return $this->generateOutput("order:".$orderId." status: ".$status);
				}
				else {
					return $this->generateOutput("token not valid ! ", "Webhook", false, 403, $params['token']);
				}
			}
			return $this->generateOutput("no hook !", "Webhook", false, 404);
		}
		catch (Exception $e) {
			return $this->handleError($e);
		}
	}
	
	/**
	 * loads orders that were set to status return announced 
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function getReturns(WP_REST_Request $request) {
		try {
			$this->checkAuth($request);
			$params = $request->get_params();
			return $this->generateOutput($this->orders->getOrders(false, $params['max']));
		}
		catch (Exception $e) {
			return $this->handleError($e);
		}
	}
	
	/**
	 * loads all orders since time
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function getOrders(WP_REST_Request $request) {
		try {
			$this->checkAuth($request);
			$params = $request->get_params();
			return $this->generateOutput($this->orders->getOrders(true, $params['max']));
		}
		catch (Exception $e) {
			return $this->handleError($e);
		}
	}
	
	/**
	 * loads order details by order id
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function getOrder(WP_REST_Request $request) {
		try {
			$this->checkAuth($request);
			$params = $request->get_params();
			return $this->generateOutput($this->orders->getOrder($params['order_id']));
		}
		catch (Exception $e) {
			return $this->handleError($e);
		}
	}
	
	/**
	 * sets an order as completed
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function setOrderCompleted(WP_REST_Request $request) {
		try {
			$this->checkAuth($request);
			$params = $request->get_params();
			return $this->generateOutput($this->orders->setOrderStatus($params['order_id'], 'wc-completed'));
		}
		catch (Exception $e) {
			return $this->handleError($e);
		}
	}
	
	/**
	 * sets an order as downloaded
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function setOrderDownloaded(WP_REST_Request $request) {
		try {
			$this->checkAuth($request);
			$params = $request->get_params();
			return $this->generateOutput($this->orders->setOrderDownloaded($params['order_id'], null));
		}
		catch (Exception $e) {
			return $this->handleError($e);
		}
	}
	
	/**
	 * adds tracking information to an order
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
    public function addTrackingInfo(WP_REST_Request $request) {
        try {
            //check if hook is activated
            $hook = get_option("fel_invoice_hook_token");
            $params = $request->get_params();
            if ($hook) {
                if ($hook === $params['token']) {
                    $params = $params['eventData'];

                    $connect = new FelInvoiceConnect();
                    $json = $connect->getTrackingNumber($params['goodsOrderParcelId']);
                    if ($json != false) {
                        $carrier = "";
                        if (array_key_exists('goodsOrderExternalId', $params)){
                            $externalID = $params['goodsOrderExternalId'];
                            $carrier = $json->profileName;
                            $trackingNumber = $json->trackingNumber;
                            return $this->generateOutput($this->orders->addTrackingInfo($externalID, $trackingNumber, $carrier));
                        }else{
                            return $this->generateOutput("goodsOrderExternalId Not found", "Webhook", false, 403, $params['token']);
                        }
                    }else{
                        return $this->generateOutput("No tracking number found", "Webhook", false, 403, $params['token']);
                    }
                }
                else {
                    return $this->generateOutput("token not valid ! ", "Webhook", false, 403, $params['token']);
                }
            }
            return $this->generateOutput("no hook !", "Webhook", false, 404);
        }
        catch (Exception $e) {
            return $this->handleError($e);
        }
    }
	
	/**
	 * adds item information to an order
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function addItemInformationInOrder(WP_REST_Request $request) {
		try {
			$this->checkAuth($request);
			$params = $request->get_params();
                        if (!key_exists('order_id', $params) || !$params['order_id']) {
                            return $this->generateOutput(null, 'order ID is required', false, 500);
                        }
                        if (!key_exists('posId', $params) || !$params['posId']) {
                            return $this->generateOutput(null, 'item ID (posId) is required', false, 500);
                        }
                        $order = wc_get_order($params['order_id']);
                        if (!$order) {
                            return $this->generateOutput(null, 'order '.$params['order_id'].' not found', false, 500);
                        }
                        
                        $hasItem = false;
                        foreach($order->get_items() as $itemId => $item ){
                            if ($itemId == $params['posId']) {
                                $hasItem = true;
                                break;
                            }
                        }
                        if (!$hasItem) {
                            return $this->generateOutput(null, 'order '.$params['order_id'].' has no item '.$params['posId'], false, 500);
                        }
                        
                        $this->orders->addSerialNumber($params['posId'], $params['serialNumber']);
                        $this->orders->addLot($params['posId'], $params['lot']);
                        $this->orders->addMetaSku($params['posId'], $params['sku']);
			return $this->generateOutput(0);
		}
		catch (Exception $e) {
			return $this->handleError($e);
		}
	}
	
	/**
	 * books a returned order
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function returnOrderBooked(WP_REST_Request $request) {
		try {
			$this->checkAuth($request);
			$params = $request->get_params();
			return $this->generateOutput($this->orders->bookedReturnShipment($params['order_id'], 
				$params['product_id'], $params['quantity'], $params['choice'], $params['reason'],
			    $params['serialNumber'], $params['lot'], $params['sku']));
		}
		catch (Exception $e) {
			return $this->handleError($e);
		}
	}
	
	/**
	 * cancels an order
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function cancelOrder(WP_REST_Request $request) {
		try {
			$this->checkAuth($request);
			$params = $request->get_params();
			return $this->generateOutput($this->orders->setOrderCancelled($params['order_id']));
		}
		catch (Exception $e) {
			return $this->handleError($e);
		}
	}
	
	/**
	 * sets order status fulfillment
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function fulfillment(WP_REST_Request $request) {
		try {
			$this->checkAuth($request);
			$params = $request->get_params();
			return $this->generateOutput($this->orders->setOrderFulfillment($params['order_id']));
		}
		catch (Exception $e) {
			return $this->handleError($e);
		}
	}
	
	/**
	 * sets an order as fulfillment blocked (e.g. shipping label couldn't be created successfully)
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function fulfillmentBlocked(WP_REST_Request $request) {
		try {
			$this->checkAuth($request);
			$params = $request->get_params();
			return $this->generateOutput($this->orders->setOrderFulfillment(
				$params['order_id']), $params['reason']);
		}
		catch (Exception $e) {
			return $this->handleError($e);
		}
	}
	
	/**
	 * sets stock
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function setStock(WP_REST_Request $request) {
		try {
			$this->checkAuth($request);
			$params = $request->get_params();
			$post_id = $params['post_id'];
			$new_stock = $params['quantity'];
			return $this->generateOutput($this->stock->newStock($post_id, $new_stock));
		}
		catch (Exception $e) {
			return $this->handleError($e);
		}
	}
	
	/**
	 * sets alternative stock
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function setAlternativeStock(WP_REST_Request $request) {
		try {
			$this->checkAuth($request);
			$params = $request->get_params();
			$post_id = $params['post_id'];
			$new_stock = $params['quantity'];
			return $this->generateOutput($this->stock->setBStock($post_id, $new_stock));
		}
		catch (Exception $e) {
			return $this->handleError($e);
		}
	}
	
	/**
	 * adds lot information
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function setLot(WP_REST_Request $request) {
		try {
			$this->checkAuth($request);
			$params = $request->get_params();
			$post_id = $params['post_id'];
			$data = $params['data'];
			return $this->generateOutput($this->stock->addLotInformation($post_id, 
				$data, $params['notReserved']));
		}
		catch (Exception $e) {
			return $this->handleError($e);
		}
	}
	
	/**
	 * registers methods
	 */
	public function register() {
		$namespace = 'rest/v1/astroweb';
		$this->registerProductRoutes($namespace.'/product/');
		$this->registerOrderRoutes($namespace.'/order/');
		$this->registerStockRoutes($namespace.'/stock/');
		$this->registerRoute($namespace, '/info', 'getPluginData');
		$this->registerRoute($namespace, '/resetHook', 'resetWebHooks', 'POST');
	}
	
	/**
	 * re-registers to webhooks
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function resetWebHooks(WP_REST_Request $request) {
		try {
			$this->checkAuth($request);
			$data = array();
			$connect = new FelInvoiceConnect();
			$data['token'] = $connect->registerWebHooks();
			return $this->generateOutput($data);
		}
		catch (Exception $e) {
			return $this->handleError($e);
		}
	}
	
	/**
	 * reads current plugin data (e.g. version number) and returns it
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function getPluginData(WP_REST_Request $request) {
		try {
			$this->checkAuth($request);
			$plugin_data = array();
			if (function_exists('get_plugin_data')) {
				$plugin_data = get_plugin_data(__DIR__.'/../FelInvoice.php');
			}
			$plugin_data['fel_invoice_hook_token'] = get_option("fel_invoice_hook_token");
			$plugin_data['shop'] = get_option('fel_invoice_plugin_shop_name');
			return $this->generateOutput($plugin_data);
		}
		catch (Exception $e) {
			return $this->handleError($e);
		}
	}
	
	/**
	 * returns product data
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function getProductById(WP_REST_Request $request) {
		try {
			$this->checkAuth($request);
			$params = $request->get_params();
			$post = get_post($params['post_id']);
			return $this->generateOutput($this->products->getProductData($post, true));
		}
		catch (Exception $e) {
			return $this->handleError($e);
		}
	}	
	
	/**
	 * loads products
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function getProducts(WP_REST_Request $request) {
		try {
			$this->checkAuth($request);
			$params = $request->get_params();
			$timestamp = "";
			if (array_key_exists("timestamp", $params)) {
				$timestamp = $params['timestamp'];
			}
			return $this->generateOutput($this->products->getAllProducts($timestamp));
		}
		catch (Exception $e) {
			return $this->handleError($e);
		}
	}	
}