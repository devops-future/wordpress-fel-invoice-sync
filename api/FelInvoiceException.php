<?php

/**
 * used for throwing specific api exceptions
 * @author Patric Eid
 *
 */
class FelInvoiceException extends Exception {
	private $returnCode = 500;
	
	function __construct($message, $returnCode) {
		parent::__construct($message);
		$this->returnCode = $returnCode;
	}
	
	/**
	 * returns return code
	 * @return number
	 */
	public function getReturnCode() {
		return $this->returnCode;
	}
}