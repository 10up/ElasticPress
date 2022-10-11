<?php
/**
 * Plugin Name:       ElasticPress - Facet By Meta
 * Plugin URI:        https://github.com/10up/ElasticPress
 * Description:       Enables the Facet By Meta block.
 * Version:           1.0.0
 * Requires at least: 5.6
 * Requires PHP:      7.0
 * Author:            10up
 * Author URI:        http://10up.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package  elasticpress
 */

add_filter(
	'ep_facet_types',
	function ( $facet_types ) {
		if ( ! isset( $facet_types['meta'] ) && class_exists( '\ElasticPress\Feature\Facets\Types\Meta\FacetType' ) ) {
			$facet_types['meta'] = '\ElasticPress\Feature\Facets\Types\Meta\FacetType';
		}
		return $facet_types;
	}
);
