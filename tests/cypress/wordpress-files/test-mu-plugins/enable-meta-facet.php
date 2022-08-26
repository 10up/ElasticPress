<?php
/**
 * This can be deleted once Facet By Meta is made available.
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
