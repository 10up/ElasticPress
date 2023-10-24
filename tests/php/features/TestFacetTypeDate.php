<?php
/**
 * Test date facet type
 *
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress;
use ElasticPress\Features;

/**
 * Facets\Types\Date\FacetType test class
 */
class TestFacetTypeDate extends BaseTestCase {

	/**
	 * The facet type instance
	 *
	 * @var null|\ElasticPress\Feature\Facets\Types\Date\FacetType
	 */
	protected $facet_type = null;

	/**
	 * Setup each test.
	 */
	public function set_up() {
		$facet_feature    = Features::factory()->get_registered_feature( 'facets' );
		$this->facet_type = $facet_feature->types['date'];

		parent::set_up();
	}

	/**
	 * Test get_filter_name method.
	 *
	 * @group facets
	 */
	public function testGetFilterName() {
		/**
		 * Test default behavior
		 */
		$this->assertEquals( 'ep_date_filter', $this->facet_type->get_filter_name() );

		/**
		 * Test the `ep_facet_date_filter_name` filter
		 */
		$change_filter_name = function( $filter_name ) {
			return $filter_name . '_';
		};
		add_filter( 'ep_facet_date_filter_name', $change_filter_name );
		$this->assertEquals( 'ep_date_filter_', $this->facet_type->get_filter_name() );
	}

	/**
	 * Test get_filter_type method.
	 *
	 * @group facets
	 */
	public function testGetFilterType() {
		/**
		 * Test default behavior
		 */
		$this->assertEquals( 'ep_date', $this->facet_type->get_filter_type() );

		/**
		 * Test the `ep_facet_date_filter_type` filter
		 */
		$change_filter_type = function( $filter_type ) {
			return $filter_type . '_';
		};
		add_filter( 'ep_facet_date_filter_type', $change_filter_type );
		$this->assertEquals( 'ep_date_', $this->facet_type->get_filter_type() );
	}

	/**
	 * Test add_query_filters method.
	 *
	 * @group facets
	 */
	public function testAddQueryFilters() {
		parse_str( 'ep_date_filter=2023-01-01,2023-10-01', $_GET );

		$expected = [
			[
				'range' => [
					'post_date' => [
						'gte' => '2023-01-01 00:00:00',
					],
				],
			],
			[
				'range' => [
					'post_date' => [
						'lte' => '2023-10-01 23:59:59',
					],
				],
			],
		];
		$this->assertSame( $expected, $this->facet_type->add_query_filters( [] ) );
	}

	/**
	 * Test the add_query_filters method when there is only a start date.
	 *
	 * @group facets
	 */
	public function testAddQueryFiltersWithOnlyStartDate() {
		parse_str( 'ep_date_filter=2023-01-01,', $_GET );

		$expected = [
			[
				'range' => [
					'post_date' => [
						'gte' => '2023-01-01 00:00:00',
					],
				],
			],
		];
		$this->assertSame( $expected, $this->facet_type->add_query_filters( [] ) );
	}

	/**
	 * Test add_query_filters method when there is only an end date.
	 *
	 * @group facets
	 */
	public function testAddQueryFiltersWithOnlyEndDate() {
		parse_str( 'ep_date_filter=,2023-10-01', $_GET );

		$expected = [
			[
				'range' => [
					'post_date' => [
						'lte' => '2023-10-01 23:59:59',
					],
				],
			],
		];
		$this->assertSame( $expected, $this->facet_type->add_query_filters( [] ) );
	}

	/**
	 * Test the format_selected method.
	 *
	 * @group facets
	 */
	public function testFormatSelected() {
		$facet = 'test_facet';

		$filters = [];

		// Test with start and end date.
		$value  = '2023-01-01,2023-12-12';
		$result = $this->facet_type->format_selected( $facet, $value, $filters );
		$this->assertArrayHasKey( 'terms', $result[ $this->facet_type->get_filter_type() ] );
		$this->assertEquals(
			[
				'2023-01-01' => true,
				'2023-12-12' => true,
			],
			$result[ $this->facet_type->get_filter_type() ]['terms']
		);

		// Test with only start date.
		$value  = '2023-12-12,';
		$result = $this->facet_type->format_selected( $facet, $value, $filters );
		$this->assertEquals(
			[
				'2023-12-12' => true,
			],
			$result[ $this->facet_type->get_filter_type() ]['terms']
		);

		// Test with only end date.
		$value  = ',2023-12-12';
		$result = $this->facet_type->format_selected( $facet, $value, $filters );
		$this->assertEquals(
			[
				''           => true,
				'2023-12-12' => true,
			],
			$result[ $this->facet_type->get_filter_type() ]['terms']
		);
	}

	/**
	 * Test the add_query_params method.
	 *
	 * @group facets
	 */
	public function testAddQueryParams() {
		$new_filters = [
			'ep_date' => [
				'terms' => [
					'2023-01-01' => true,
					'2023-12-12' => true,
				],
			],
		];
		$filters     = $this->facet_type->add_query_params( [ 's' => 'test' ], $new_filters );
		$expected    = [
			's'              => 'test',
			'ep_date_filter' => '2023-01-01,2023-12-12',
		];

		$this->assertSame( $expected, $filters );
	}

	/**
	 * Test the get_facet_options method.
	 *
	 * @group facets
	 */
	public function testGetFacetOptions() {
		$expected_result = [
			[
				'label'     => 'Last 3 months',
				'value'     => '-3 months',
				'url-param' => 'last-3-months',
			],
			[
				'label'     => 'Last 6 months',
				'value'     => '-6 months',
				'url-param' => 'last-6-months',
			],
			[
				'label'     => 'Last 12 months',
				'value'     => '-12 months',
				'url-param' => 'last-12-months',
			],
		];

		$this->assertSame( $expected_result, $this->facet_type->get_facet_options() );

		/**
		 * Test the `ep_facet_date_options` filter
		 */
		$modified_options = [
			[
				'label'     => 'Last 1 week',
				'value'     => '-1 weeks',
				'url-param' => 'last-1-week',
			],
			[
				'label'     => 'Last 2 weeks',
				'value'     => '-1 weeks',
				'url-param' => 'last-1-weeks',
			],
		];

		$change_filter_type = function( $options ) use ( $modified_options ) {
			return $modified_options;
		};

		add_filter( 'ep_facet_date_options', $change_filter_type );
		$this->assertSame( $modified_options, $this->facet_type->get_facet_options() );
	}

	/**
	 * Data provider for the parseDateDataProvider method
	 *
	 * @return array
	 */
	public function parseDateDataProvider() : array {
		return [
			[
				[ '-1 week', '-1 month', '-1 year' ],
				[
					gmdate( 'Y-m-d 00:00:00', strtotime( '-1 week' ) ),
					gmdate( 'Y-m-d 23:59:59', strtotime( '-1 month' ) ),
				],
			],
			[
				[ 'invalid date', '-1 month' ],
				[
					'',
					gmdate( 'Y-m-d 23:59:59', strtotime( '-1 month' ) ),
				],
			],
			[
				[ '-1 week', '-1 month' ],
				[
					gmdate( 'Y-m-d 00:00:00', strtotime( '-1 week' ) ),
					gmdate( 'Y-m-d 23:59:59', strtotime( '-1 month' ) ),
				],
			],
			[
				[ '2023-01-01', '2023-12-31' ],
				[
					'2023-01-01 00:00:00',
					'2023-12-31 23:59:59',
				],
			],
		];
	}

	/**
	 * Test parse_dates method.
	 *
	 * @param array $dates Array of dates to parse.
	 * @param array $expected Expected result.
	 *
	 * @dataProvider parseDateDataProvider
	 * @group facets
	 */
	public function testParseDates( $dates, $expected ) {
		$this->assertSame( $expected, $this->facet_type->parse_dates( $dates ) );
	}

	/**
	 * Test WP Query integration.
	 *
	 * @group facets
	 */
	public function testQueryPost() {
		$this->ep_factory->post->create( [ 'post_date' => '2021-12-31 23:59:59' ] );
		$this->ep_factory->post->create( [ 'post_date' => '2022-01-01 00:00:00' ] );
		$this->ep_factory->post->create( [ 'post_date' => '2022-12-31 23:59:59' ] );
		$this->ep_factory->post->create( [ 'post_date' => '2023-01-01 00:00:00' ] );
		$this->ep_factory->post->create( [ 'post_date' => '2023-06-01 23:59:59' ] );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		add_filter( 'ep_is_facetable', '__return_true' );

		// get all the post between 2022-01-01 and 2022-12-31
		parse_str( 'ep_date_filter=2022-01-01,2022-12-31', $_GET );
		$query = new \WP_Query(
			[
				'ep_integrate' => true,
			]
		);
		$this->assertEquals( 2, $query->found_posts );

		// get all posts published on or after 2022-01-01.
		parse_str( 'ep_date_filter=2022-01-01', $_GET );
		$query = new \WP_Query(
			[
				'ep_integrate' => true,
			]
		);
		$this->assertEquals( 4, $query->found_posts );

		// get all posts published on or before 2022-01-01.
		parse_str( 'ep_date_filter=,2022-01-01', $_GET );
		$query = new \WP_Query(
			[
				'ep_integrate' => true,
			]
		);
		$this->assertEquals( 2, $query->found_posts );

		// passing invalid date shouldn't apply any filter.
		parse_str( 'ep_date_filter=invalid date', $_GET );
		$query = new \WP_Query(
			[
				'ep_integrate' => true,
			]
		);
		$this->assertEquals( 5, $query->found_posts );
	}
}
