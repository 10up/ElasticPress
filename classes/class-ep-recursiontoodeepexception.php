<?php

/**
 * Exception thrown when deep recursion is detected.
 *
 * @since  2.4
 * @package elasticpress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Exception thrown when deep recursion is detected.
 */
class EP_RecursionTooDeepException extends RuntimeException {

	/**
	 * A value passed via the Exception.
	 *
	 * @var mixed
	 */
	private $value;

	/**
	 * Constructs the Exception.
	 *
	 * @param mixed     $value A value passed via the Exception.
	 * @param string    $message The exception message.
	 * @param integer   $code The exception code.
	 * @param Exception $previous The previous exception used for the exception chaining.
	 */
	public function __construct( $value, $message = 'Recursion Too Deep', $code = 0, Exception $previous = null ) {
		$this->value = $value;
		parent::__construct( $message, $code, $previous );
	}

	/**
	 * Retrieve the value passed via this Exception.
	 *
	 * @return mixed
	 */
	public function value() {
		return $this->value;
	}

}
