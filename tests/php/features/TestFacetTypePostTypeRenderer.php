<?php
/**
 * Test post type facet type feature
 *
 * @since 4.6.0
 * @package elasticpress
 */

namespace ElasticPressTest;

use \ElasticPress\Feature\Facets\Types\PostType\Renderer;
/**
 * Facets\Types\PostType\FacetType test class
 */
class TestFacetTypePostTypeRenderer extends BaseTestCase {
	/**
	 * Test render
	 *
	 * @group facets
	 */
	public function testRenderer() {
		$this->markTestIncomplete();
	}

	/**
	 * Test get_facet_item_value_html
	 *
	 * @group facets
	 */
	public function testGetPostTypeValueHtml() {
		$renderer = new Renderer();

		/**
		 * Test default behavior
		 */

		$value = [
			'value'       => 'page',
			'name'        => 'page',
			'count'       => 300,
			'is_selected' => true,
		];

		$url              = 'https://example.com';
		$href             = 'href="' . $url . '"';
		$accessible_label = 'Remove filter: ' . $value['name'];
		$link             = '<a aria-label="' . $accessible_label . '" ' . $href . ' rel="nofollow"><div class="ep-checkbox checked" role="presentation"></div>' . $value['name'] . '</a>';
		$html             = '<div class="term level-0 selected " data-term-name="' . $value['value'] . '" data-term-slug="' . $value['value'] . '">' . $link . '</div>';

		$this->assertEquals( $html, $renderer->get_facet_item_value_html( $value, $url, $value['is_selected'] ) );

		$value['is_selected'] = false;
		$accessible_label     = 'Apply filter: ' . $value['name'];
		$link                 = '<a aria-label="' . $accessible_label . '" ' . $href . ' rel="nofollow"><div class="ep-checkbox " role="presentation"></div>' . $value['name'] . '</a>';
		$html                 = '<div class="term level-0  " data-term-name="' . $value['value'] . '" data-term-slug="' . $value['value'] . '">' . $link . '</div>';

		$this->assertEquals( $html, $renderer->get_facet_item_value_html( $value, $url, $value['is_selected'] ) );

		$value['count'] = 0;
		$link           = '<a aria-label="' . $accessible_label . '" aria-role="link" aria-disabled="true" rel="nofollow"><div class="ep-checkbox " role="presentation"></div>' . $value['name'] . '</a>';
		$html           = '<div class="term level-0  empty-term" data-term-name="' . $value['value'] . '" data-term-slug="' . $value['value'] . '">' . $link . '</div>';

		$this->assertEquals( $html, $renderer->get_facet_item_value_html( $value, $url, $value['is_selected'] ) );

		/**
		 * Test the `ep_facet_post_type_value_label` filter
		 */
		$change_label = function( $label, $value ) {
			return ( 'page' === $value['value'] ) ? 'Different Label' : $label;
		};
		add_filter( 'ep_facet_post_type_value_label', $change_label, 10, 2 );

		$accessible_label = 'Apply filter: Different Label';
		$link             = '<a aria-label="' . $accessible_label . '" aria-role="link" aria-disabled="true" rel="nofollow"><div class="ep-checkbox " role="presentation"></div>Different Label</a>';
		$html             = '<div class="term level-0  empty-term" data-term-name="' . $value['value'] . '" data-term-slug="' . $value['value'] . '">' . $link . '</div>';

		$this->assertEquals( $html, $renderer->get_facet_item_value_html( $value, $url, $value['is_selected'] ) );

		remove_filter( 'ep_facet_post_type_value_label', $change_label );

		/**
		 * Test the `ep_facet_post_type_value_accessible_label` filter
		 */
		$change_accessible_label = function( $label, $value ) {
			return ( 'page' === $value['value'] ) ? 'Apply filter!' : $label;
		};
		add_filter( 'ep_facet_post_type_value_accessible_label', $change_accessible_label, 10, 2 );

		$accessible_label = 'Apply filter!';
		$link             = '<a aria-label="' . $accessible_label . '" aria-role="link" aria-disabled="true" rel="nofollow"><div class="ep-checkbox " role="presentation"></div>' . $value['name'] . '</a>';
		$html             = '<div class="term level-0  empty-term" data-term-name="' . $value['value'] . '" data-term-slug="' . $value['value'] . '">' . $link . '</div>';

		$this->assertEquals( $html, $renderer->get_facet_item_value_html( $value, $url, $value['is_selected'] ) );

		remove_filter( 'ep_facet_post_type_value_accessible_label', $change_label );

		/**
		 * Test the `ep_facet_post_type_value_html` filter
		 */
		$change_html = function( $html, $value, $url ) {
			return ( 'https://example.com' === $url ) ? '<p>Completely custom made element</p>' : $html;
		};
		add_filter( 'ep_facet_post_type_value_html', $change_html, 10, 3 );

		$this->assertEquals( '<p>Completely custom made element</p>', $renderer->get_facet_item_value_html( $value, $url, $value['is_selected'] ) );
	}
}
