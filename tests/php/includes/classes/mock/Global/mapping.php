<?php
/**
 * Global Feature Mapping
 *
 * @since 5.0.0
 * @package elasticpress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

return array(
	'mappings' => array(
		'properties' => array(
			'ID' => array(
				'type' => 'long',
			),
		),
	),
);
