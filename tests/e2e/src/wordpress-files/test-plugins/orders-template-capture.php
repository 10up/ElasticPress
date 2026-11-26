<?php
/**
 * Plugin Name: Orders Template Capture
 * Description: Isolated Orders template service adapter for dashboard sync tests.
 *
 * @package elasticpress
 */

add_filter( 'ep_woocommerce_orders_autosuggest_available', '__return_true' );
/**
 * Capture Orders template writes instead of contacting the hosted service.
 *
 * @param false|array|WP_Error $response Existing intercepted response.
 * @param array                $args HTTP request arguments.
 * @param string               $url Request URL.
 * @return false|array|WP_Error Intercepted response.
 */
function ep_test_capture_orders_template( $response, $args, $url ) {
	$is_template_write = 'PUT' === $args['method'] && false !== strpos( $url, '/api/v1/search/orders/' );

	if ( ! $is_template_write ) {
		return $response;
	}

	$writes   = get_option( 'ep_test_orders_templates', [] );
	$writes[] = [
		'url'  => $url,
		'body' => $args['body'],
	];
	update_option( 'ep_test_orders_templates', $writes );

	return [
		'response' => [ 'code' => 200 ],
		'body'     => '{}',
		'headers'  => [],
	];
}
add_filter( 'pre_http_request', 'ep_test_capture_orders_template', 10, 3 );
