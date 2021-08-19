<?php

require_once __DIR__.'/FelInvoiceException.php';

/**
 * contains basic methods for working with rest api
 * @author Patric Eid
 *
 */
class FelInvoiceBasic {
	
	/**
	 * checks whether authkey was set in header and matches option
	 * @param WP_REST_Request $request
	 * @throws FelInvoiceException
	 */
	protected function checkAuth($request) {
		$headers = $request->get_headers();
		if (array_key_exists("authkey", $headers)) {
			if ($headers['authkey'][0] != get_option('fel_invoice_plugin_auth_key')) {
				throw new FelInvoiceException("2: You don't have permissions accessing this site.", 500);
			}
		}
		else {
			throw new FelInvoiceException("2: You don't have permissions accessing this site.", 404);
		}
	}

    /**
     * generates json output
     * @param array|string $data
     * @param string $message
     * @param bool $success
     * @param int $code
     * @param string $token
     * @return WP_REST_Response
     */
	protected function generateOutput($data=null, $message="", $success=true, $code=200, $token=""){
		$result = array();
		if ($data != null) {
			$result['data'] = $data;
			if ($token != "") {
				$result['token'] = $token;
			}
			if (is_array($data) && array_key_exists("Error", $data)) {
				$success = false;
				if ($code == 200) {
					$code = 418;
				}
			}
		}
		if ($message != "") {
			$result['message'] = $message;
		}
		$result['success'] = $success;
		return new WP_REST_Response($result, $code);
	}
	
	/**
	 * handles error messages
	 * @param Exception|FelInvoiceException $e
	 * @return WP_REST_Response
	 */
	protected function handleError($e) {
		$code = 500;
		if ($e instanceof FelInvoiceException) {
			$code = $e->getReturnCode();
		}
		return $this->generateOutput(null, $e->getMessage(), false, $code);
	}
}