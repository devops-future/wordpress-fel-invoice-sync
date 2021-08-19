<?php

/**
 * provides an interface for accessing functions from other plugins
 * @author Patric Eid
 *
 */
class FelInvoiceInterface {

    /**
     * called for cancelling an order
     * @param int $postId
     * @return Boolean true if order was cancelled
     * @throws Exception
     */
	public function cancelOrder($postId) {
		$handler = new FelInvoicePluginOrders();
		return $handler->cancelOrder($postId);
	}

    /**
     * sends an order to astroweb
     * @param int $postId
     * @param WC_Order $orderObj
     * @throws Exception
     */
	public function transmitOrder($postId, $orderObj=null) {
		$handler = new FelInvoicePluginOrders();
		$handler->transmitOrder($postId, $orderObj);
	}
}