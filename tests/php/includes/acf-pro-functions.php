<?php
/**
 * ACF Pro mock functions
 *
 * @since 5.3.0
 * @package elasticpress
 */

/**
 * Mock version of acf_get_field_groups
 *
 * @param array $args Function args
 * @return array
 */
function acf_get_field_groups( $args = [] ) {
	if ( ! $args['post_id'] ) {
		return [];
	}

	return [
		[
			'name' => 'test_group', // Not really used. See acf_get_fields.
		],
	];
}

/**
 * Mock versino of acf_render_field_setting
 *
 * @return void
 */
function acf_render_field_setting() {
	\ElasticPressTest\FunctionsCallCounter::get_instance()->update_counter( 'acf_render_field_setting' );
}

/**
 * Mock version of acf_get_fields
 *
 * @return array
 */
function acf_get_fields() {
	return [
		[
			'ep_acf_repeater_index_field' => 1,
			'name'                        => 'should_be_added',
		],
		[
			'ep_acf_repeater_index_field' => 0,
			'name'                        => 'should_not_be_added',
		],
		[
			'name' => 'should_not_be_added',
		],
	];
}

/**
 * Mock version of acf_get_field
 *
 * @param string $key Field key
 * @return bool
 */
function acf_get_field( $key ) {
	$fields = [
		'repeater_test' => [
			'ep_acf_repeater_index_field' => 1,
			'type'                        => 'repeater',
		],
	];

	return $fields[ $key ] ?? false;
}

/**
 * Mock version of get_field
 *
 * @return array
 */
function get_field() {
	return [
		'child_1' => 'value_1',
		'child_2' => 'value_2',
	];
}
