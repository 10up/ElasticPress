<?php
/**
 * Plugin Name:       ElasticPress - Field Grouping - Test Plugin
 * Version:           1.0.0
 * Author:            10up | ElasticPress.io
 * Author URI:        https://elasticpress.io
 * Text Domain:       field-grouping
 * Domain Path:       /languages
 * Update URI:        https://github.com/10up/elasticpress-proxy
 * Requires at least: 5.6
 * Requires PHP:      7.0
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package ElasticPress_Custom_Proxy
 */

namespace ElasticPress_FieldGrouping_Plugin;

use ElasticPress\Indexables;
use ElasticPress\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Adds a demo field group to the settings schema.
 *
 * @param array $settings_schema Existing settings schema.
 * @return array Modified settings schema with an additional field group.
 */
function add_demo_field_groups( $settings_schema ) {
	$settings_schema[] = [
		'type'   => 'field_group',
		'key'    => 'field_group_a',
		'label'  => 'Field Group Example',
		'fields' => [
			[
				'default' => 'Lorem ipsum',
				'key'     => 'field_subgroup_a',
				'label'   => 'Lorem Ipsum',
				'type'    => 'text',
			],
			[
				'default' => 'Lorem ipsum dolor sit',
				'key'     => 'field_subgroup_b',
				'label'   => 'Lorem Ipsum dolor sit',
				'type'    => 'text',
			],
		],
	];
	return $settings_schema;
}


add_filter( 'ep_feature_settings_schema', __NAMESPACE__ . '\add_demo_field_groups' );
