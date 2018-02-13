<?php

/**
 * Guard against recursive WordPress filter application.
 *
 * @since  2.4
 * @package elasticpress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Guard against recursive WordPress filter application.
 */
class EP_RecursionGuard {

	/**
	 * The maximum level of recursion permitted.
	 *
	 * @var int
	 */
	private $max_levels;

	/**
	 * The filter this guard protects.
	 *
	 * @var string
	 */
	private $tag;

	/**
	 * Constructor. Sets up a guarded version of the filter.
	 *
	 * @param integer $max_levels The maximum level of recursion permitted.
	 * @param string  $tag The name of the filter hook.
	 */
	public function __construct( $max_levels = 1, $tag ) {
		$this->max_levels = $max_levels;
		$this->tag = $tag;
		add_filter( $this->tag, $this->get_fn_ref(), 1 );
	}

	/**
	 * Returns a reference to this instance's filter handler function.
	 * Pass the result of this function to add_filter() so it can
	 * later be removed by remove_filter().
	 *
	 * @return Callable
	 */
	public function get_fn_ref() {
		return [ $this, 'handle' ];
	}

	/**
	 * Filter handler with a guard against too much recursion.
	 * If the filter is applied recursively too many times, it
	 * throws a EP_RecursionTooDeepException with its $value set
	 * to the current $value WordPress passed it for filtering.
	 *
	 * @param mixed $value The value on which the filters hooked to `$tag` are applied on.
	 * @return mixed
	 * @throws EP_RecursionTooDeepException Passes a value back when recursion goes too deep.
	 */
	public function handle( $value ) {
		$this->max_levels -= 1;
		if ( 0 === $this->max_levels ) {
			throw new EP_RecursionTooDeepException( $value );
		}
		return $value;
	}

	/**
	 * Clean up after use. Removes the filter handler.
	 *
	 * @return void
	 */
	public function cleanup() {
		remove_filter( $this->tag, $this->get_fn_ref() );
	}

}
