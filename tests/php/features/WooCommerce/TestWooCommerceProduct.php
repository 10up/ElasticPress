<?php
/**
 * Test woocommerce products class
 *
 * @since 4.7.0
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress;

require_once __DIR__ . '/WooCommerceBaseTestCase.php';

/**
 * WC products test class
 */
class TestWooCommerceProduct extends WooCommerceBaseTestCase {
	/**
	 * Products instance
	 *
	 * @var Products
	 */
	protected $products;

	/**
	 * Setup each test.
	 *
	 * @group woocommerce
	 * @group woocommerce-orders
	 */
	public function set_up() {
		parent::set_up();
		$this->products = ElasticPress\Features::factory()->get_registered_feature( 'woocommerce' )->products;
	}

	/**
	 * Test products post type query does not get integrated when the feature is active
	 *
	 * @group woocommerce
	 * @group woocommerce-products
	 */
	public function testProductsPostTypeQueryOn() {
		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->setup_features();

		$this->ep_factory->post->create();
		$this->ep_factory->product->create(
			array(
				'description' => 'product 1',
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			'post_type' => 'product',
		);

		$query = new \WP_Query( $args );

		$this->assertNull( $query->elasticsearch_success );
		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, $query->found_posts );
	}

	/**
	 * Test products post type query does not get automatically integrated when querying WC product_cat taxonomy
	 *
	 * @group woocommerce
	 * @group woocommerce-products
	 */
	public function testProductsPostTypeQueryProductCatTax() {
		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->setup_features();

		$args = array(
			'tax_query' => array(
				array(
					'taxonomy' => 'product_cat',
					'terms'    => array( 'cat' ),
					'field'    => 'slug',
				),
			),
		);

		$query = new \WP_Query( $args );

		$this->assertNull( $query->elasticsearch_success );

		$args = [ 'product_cat' => 'cat' ];

		$query = new \WP_Query( $args );

		$this->assertNull( $query->elasticsearch_success );
	}

	/**
	 * Test WC product_cat taxonomy queries do get automatically integrated when ep_integrate is set to true
	 *
	 * @since 4.7.0
	 * @group woocommerce
	 * @group woocommerce-products
	 */
	public function testProductsPostTypeQueryProductCatTaxWhenEPIntegrateSetTrue() {
		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->setup_features();

		$args = [
			'product_cat'  => 'cat',
			'ep_integrate' => true,
		];

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
	}

	/**
	 * Test WC product_cat taxonomy queries do get automatically integrated for the main query
	 *
	 * @since 4.7.0
	 * @group woocommerce
	 * @group woocommerce-products
	 */
	public function testProductsPostTypeQueryProductCatTaxWhenMainQuery() {
		global $wp_the_query;

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->setup_features();

		$args = [
			'product_cat' => 'cat',
		];

		$wp_the_query->query( $args );

		$this->assertTrue( $wp_the_query->elasticsearch_success );
	}

	/**
	 * Test products post type query does get automatically integrated for the main query
	 *
	 * @since 4.7.0
	 * @group woocommerce
	 * @group woocommerce-products
	 */
	public function testProductsPostTypeQueryProductWhenMainQuery() {
		global $wp_the_query;

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->setup_features();

		$wp_the_query->query( [ 'post_type' => 'product' ] );

		$this->assertTrue( $wp_the_query->elasticsearch_success );
	}

	/**
	 * Test search integration is on in general for product searches
	 *
	 * @group woocommerce
	 * @group woocommerce-products
	 */
	public function testSearchOnAllFrontEnd() {
		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->setup_features();

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's'         => 'findme',
			'post_type' => 'product',
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
	}

	/**
	 * Test all the product attributes are synced.
	 *
	 * @group woocommerce
	 * @group woocommerce-products
	 */
	public function testWoocommerceAttributeTaxonomiesAreSync() {
		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->setup_features();

		$product_id = $this->ep_factory->product->create_variation_product();

		$post     = new \ElasticPress\Indexable\Post\Post();
		$document = $post->prepare_document( $product_id );

		$this->assertArrayHasKey( 'pa_size', $document['terms'] );
		$this->assertArrayHasKey( 'pa_colour', $document['terms'] );
		$this->assertArrayHasKey( 'pa_number', $document['terms'] );
	}

	/**
	 * Data provider for the testProductQueryOrder method.
	 *
	 * @return array
	 */
	public function productQueryOrderDataProvider() : array {
		return [
			[
				'total_sales',
				[
					'orderby' => 'popularity',
				],
				false,
				[
					0 => [ 'meta.total_sales.double' => [ 'order' => 'desc' ] ],
					1 => [ 'post_date' => [ 'order' => 'desc' ] ],
				],
				'',
				true,
			],
			[
				'total_sales',
				[ 'meta_key' => 'total_sales' ],
				false,
				[
					0 => [ 'meta.total_sales.double' => [ 'order' => 'desc' ] ],
					1 => [ 'post_date' => [ 'order' => 'desc' ] ],
				],
			],
			[
				'average_rating',
				[ 'meta_key' => '_wc_average_rating' ],
				false,
				[
					0 => [ 'meta._wc_average_rating.double' => [ 'order' => 'desc' ] ],
					1 => [ 'post_date' => [ 'order' => 'desc' ] ],
				],
			],
			[
				'regular_price',
				[
					'orderby' => 'price',
					'order'   => 'DESC',
				],
				false,
				[
					0 => [ 'meta._price.double' => [ 'order' => 'desc' ] ],
					1 => [ 'post_date' => [ 'order' => 'desc' ] ],
				],
			],
			[
				'total_sales',
				[],
				'popularity',
				[
					0 => [ 'meta.total_sales.double' => [ 'order' => 'desc' ] ],
					1 => [ 'post_date' => [ 'order' => 'desc' ] ],
				],
			],
			[
				'regular_price',
				[],
				'price-desc',
				[
					0 => [ 'meta._price.double' => [ 'order' => 'desc' ] ],
					1 => [ 'post_date' => [ 'order' => 'desc' ] ],
				],
			],
			[
				'average_rating',
				[],
				'rating',
				[
					0 => [ 'meta._wc_average_rating.double' => [ 'order' => 'desc' ] ],
					1 => [ 'post_date' => [ 'order' => 'desc' ] ],
				],
			],
			[
				'regular_price',
				[],
				'price',
				[
					0 => [ 'meta._price.double' => [ 'order' => 'asc' ] ],
					1 => [ 'post_date' => [ 'order' => 'asc' ] ],
				],
				'asc',
			],
			[
				'sku',
				[],
				'sku',
				[
					0 => [ 'meta._sku.value.sortable' => [ 'order' => 'asc' ] ],
					1 => [ 'post_date' => [ 'order' => 'asc' ] ],
				],
				'asc',
			],
			[
				'name',
				[],
				'title',
				[
					0 => [ 'post_title.sortable' => [ 'order' => 'asc' ] ],
					1 => [ 'post_date' => [ 'order' => 'asc' ] ],
				],
				'asc',
			],
			[
				'',
				[],
				'default',
				[
					0 => [ 'menu_order' => [ 'order' => 'asc' ] ],
					1 => [ 'post_title.sortable' => [ 'order' => 'asc' ] ],
					2 => [ 'post_date' => [ 'order' => 'asc' ] ],
				],
			],
			[ '', [], '', [ 0 => [ 'post_date' => [ 'order' => 'desc' ] ] ] ],
		];
	}

	/**
	 * Test the product query order.
	 *
	 * @param string $product_arg_key    Field slug
	 * @param array  $query_args         Query array
	 * @param bool   $query_string       Query string
	 * @param array  $expected           Value expected
	 * @param string $order              Order
	 * @param bool   $force_type_archive Whether this should or should not be considered an archive
	 * @dataProvider productQueryOrderDataProvider
	 * @group woocommerce
	 * @group woocommerce-products
	 */
	public function testProductQueryOrder( $product_arg_key, $query_args, $query_string, $expected, $order = '', $force_type_archive = false ) {
		global $wp_the_query;

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->setup_features();

		$product_1 = $this->ep_factory->product->create(
			array(
				$product_arg_key => 200,
			)
		);

		$product_2 = $this->ep_factory->product->create(
			array(
				$product_arg_key => 100,
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		if ( $query_string ) {
			parse_str( 'orderby=' . $query_string, $_GET );
		}

		if ( $query_string || $force_type_archive ) {
			// mock the query as post type archive
			add_action(
				'parse_query',
				function( \WP_Query $query ) {
					$query->is_post_type_archive = true;
				}
			);
		}

		$args = array_merge( [ 'post_type' => 'product' ], $query_args );

		add_filter(
			'ep_post_formatted_args',
			function ( $formatted_args ) use ( $expected ) {
				$this->assertEquals( $expected, $formatted_args['sort'] );
				return $formatted_args;
			}
		);

		$wp_the_query->query( $args );

		$this->assertTrue( $wp_the_query->elasticsearch_success, 'Elasticsearch query failed' );
		$this->assertEquals( 2, count( $wp_the_query->posts ) );

		if ( 'asc' === $order ) {
			$this->assertEquals( $product_2, $wp_the_query->posts[0]->ID );
			$this->assertEquals( $product_1, $wp_the_query->posts[1]->ID );
		} elseif ( 'desc' === $order ) {
			$this->assertEquals( $product_1, $wp_the_query->posts[0]->ID );
			$this->assertEquals( $product_2, $wp_the_query->posts[1]->ID );
		}

		\WC_Query::reset_chosen_attributes();
	}

	/**
	 * Test the product query not use Elasticsearch if preview.
	 *
	 * @group woocommerce
	 * @group woocommerce-products
	 */
	public function testQueryShouldNotUseElasticsearchIfPreview() {
		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->setup_features();

		$args = array(
			'post_type' => 'product',
			'preview'   => true,
		);

		$query = new \WP_Query( $args );

		$this->assertNull( $query->elasticsearch_success );
	}


	/**
	 * Test that on Admin Product List use Elasticsearch.
	 *
	 * @group woocommerce
	 * @group woocommerce-products
	 */
	public function testProductListInAdminUseElasticSearch() {
		global $typenow, $wc_list_table;

		set_current_screen( 'edit.php' );
		$this->assertTrue( is_admin() );

		// load required files
		include_once ABSPATH . 'wp-admin/includes/class-wp-posts-list-table.php';
		include_once WC()->plugin_path() . '/includes/admin/list-tables/class-wc-admin-list-table-products.php';

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		// mock the global variables
		$typenow       = 'product';
		$wc_list_table = new \WC_Admin_List_Table_Products();

		add_filter(
			'ep_post_filters',
			function( $filters, $args, $query ) {
				$expected_result = array(
					'terms' => array(
						'post_type.raw' => array(
							'product',
						),
					),
				);

				$this->assertEquals( $expected_result, $filters['post_type'] );
				return $filters;
			},
			10,
			3
		);

		parse_str( 'post_type=product&s=product', $_GET );

		$wp_list_table = new \WP_Posts_List_Table();
		$wp_list_table->prepare_items();
	}

	/**
	 * Test that Search in Admin Product List use Elasticsearch.
	 *
	 * @group woocommerce
	 * @group woocommerce-products
	 */
	public function testProductListSearchInAdminUseElasticSearch() {
		global $typenow, $wc_list_table;

		set_current_screen( 'edit.php' );
		$this->assertTrue( is_admin() );

		// load required files
		include_once ABSPATH . 'wp-admin/includes/class-wp-posts-list-table.php';
		include_once WC()->plugin_path() . '/includes/admin/list-tables/class-wc-admin-list-table-products.php';

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		// mock the global variables
		$typenow       = 'product';
		$wc_list_table = new \WC_Admin_List_Table_Products();

		add_filter(
			'ep_post_formatted_args',
			function ( $formatted_args, $args, $wp_query ) {
				$this->assertEquals( 'findme', $formatted_args['query']['function_score']['query']['bool']['should'][0]['multi_match']['query'] );
				$this->assertEquals(
					$args['search_fields'],
					[
						'post_title',
						'post_content',
						'post_excerpt',
						'meta' => [
							'_sku',
							'_variations_skus',
						],
					]
				);

				return $formatted_args;
			},
			10,
			3
		);

		parse_str( 'post_type=product&s=findme', $_GET );

		$wp_list_table = new \WP_Posts_List_Table();
		$wp_list_table->prepare_items();
	}

	/**
	 * Test the product query when price filter is set.
	 *
	 * @group woocommerce
	 * @group woocommerce-products
	 */
	public function testPriceFilter() {
		global $wp_the_query, $wp_query;

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->setup_features();

		$this->ep_factory->product->create(
			[
				'name'          => 'Cap 1',
				'regular_price' => 100,
			]
		);
		$this->ep_factory->product->create(
			[
				'name'          => 'Cap 2',
				'regular_price' => 800,
			]
		);
		$this->ep_factory->product->create(
			[
				'name'          => 'Cap 3',
				'regular_price' => 10000,
			]
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		parse_str( 'min_price=1&max_price=999', $_GET );

		$args  = array(
			'post_type' => 'product',
		);
		$query = new \WP_Query( $args );

		// mock the query as main query and is_search
		$wp_the_query        = $query;
		$wp_query->is_search = true;

		add_filter(
			'ep_post_formatted_args',
			function ( $formatted_args ) {

				$expected_result = array(
					'range' => array(
						'meta._price.long' => array(
							'gte'   => 1,
							'lte'   => 999,
							'boost' => 2,
						),
					),
				);

				$this->assertEquals( $expected_result, $formatted_args['query'] );
				return $formatted_args;
			},
			15
		);

		$query = $query->query( $args );

		$this->assertTrue( $wp_the_query->elasticsearch_success, 'Elasticsearch query failed' );
		$this->assertEquals( 2, count( $query ) );
	}

	/**
	 * Test the product search query when price filter is set.
	 *
	 * @group woocommerce
	 * @group woocommerce-products
	 */
	public function testPriceFilterWithSearchQuery() {
		global $wp_the_query, $wp_query;

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->setup_features();

		$this->ep_factory->product->create(
			[
				'name'          => 'Cap 1',
				'regular_price' => 100,
			]
		);

		$this->ep_factory->product->create(
			[
				'name'          => 'Cap 2',
				'regular_price' => 1000,
			]
		);

		$this->ep_factory->product->create(
			[
				'name'          => 'Cap 3',
				'regular_price' => 10000,
			]
		);

		$this->ep_factory->product->create(
			[
				'name'          => 'Cap 4',
				'regular_price' => 800,
			]
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		parse_str( 'min_price=1&max_price=999', $_GET );

		$args  = array(
			's'         => 'Cap',
			'post_type' => 'product',
		);
		$query = new \WP_Query( $args );

		// mock the query as main query and is_search
		$wp_the_query        = $query;
		$wp_query->is_search = true;

		$query = $query->query( $args );

		$this->assertTrue( $wp_the_query->elasticsearch_success, 'Elasticsearch query failed' );
		$this->assertEquals( 2, count( $query ) );
	}

	/**
	 * Tests that attributes filter uses Elasticsearch.
	 *
	 * @group woocommerce
	 * @group woocommerce-products
	 */
	public function testAttributesFilterUseES() {
		global $wp_the_query;

		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->setup_features();

		$this->ep_factory->product->create_variation_product(
			[
				'name' => 'Cap',
			]
		);

		$this->ep_factory->product->create(
			[
				'name' => 'Shoes',
			]
		);

		$this->ep_factory->product->create(
			[
				'name' => 'T-Shirt',
			]
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		// mock the query as post type archive
		add_action(
			'parse_query',
			function( \WP_Query $query ) {
				$query->is_post_type_archive = true;
			}
		);

		parse_str( 'filter_colour=blue', $_GET );

		$args  = array(
			'post_type' => 'product',
		);
		$query = new \WP_Query( $args );

		// mock the query as main query
		$wp_the_query = $query;

		$query = $query->query( $args );

		$this->assertTrue( $wp_the_query->elasticsearch_success );
		$this->assertEquals( 1, count( $query ) );
		$this->assertEquals( 'Cap', $query[0]->post_title );
	}

	/**
	 * Tests that get_posts() uses Elasticsearch when ep_integrate is true.
	 *
	 * @group woocommerce
	 * @group woocommerce-products
	 */
	public function testGetPosts() {
		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->setup_features();

		$this->ep_factory->product->create();

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$posts = get_posts(
			[
				'post_type'    => 'product',
				'ep_integrate' => true,
			]
		);

		$this->assertTrue( $posts[0]->elasticsearch );
	}

	/**
	 * Tests that get_posts() does not use Elasticsearch when ep_integrate is not set.
	 *
	 * @group woocommerce
	 * @group woocommerce-products
	 */
	public function testGetPostQueryDoesNotUseElasticSearchByDefault() {
		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->setup_features();

		$this->ep_factory->product->create();

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$posts = get_posts(
			[
				'post_type' => 'product',
			]
		);

		$properties = get_object_vars( $posts[0] );
		$this->assertArrayNotHasKey( 'elasticsearch', $properties );
	}

	/**
	 * Tests that Weighting dashboard shows SKU and Variation SKUs option.
	 *
	 * @group woocommerce
	 */
	public function testSkuOptionAddInWeightDashboard() {
		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->setup_features();

		$search = ElasticPress\Features::factory()->get_registered_feature( 'search' );
		$fields = $search->weighting->get_weightable_fields_for_post_type( 'product' );

		$this->assertArrayHasKey( 'meta._sku.value', $fields['attributes']['children'] );
		$this->assertArrayHasKey( 'meta._variations_skus.value', $fields['attributes']['children'] );

		$this->assertEquals( 'meta._sku.value', $fields['attributes']['children']['meta._sku.value']['key'] );
		$this->assertEquals( 'SKU', $fields['attributes']['children']['meta._sku.value']['label'] );

		$this->assertEquals( 'meta._variations_skus.value', $fields['attributes']['children']['meta._variations_skus.value']['key'] );
		$this->assertEquals( 'Variations SKUs', $fields['attributes']['children']['meta._variations_skus.value']['label'] );
	}

	/**
	 * Test the addition of variations skus to product meta
	 *
	 * @group woocommerce
	 * @group woocommerce-products
	 */
	public function testAddVariationsSkusMeta() {
		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->setup_features();

		$this->assertTrue( class_exists( '\WC_Product_Variable' ) );
		$this->assertTrue( class_exists( '\WC_Product_Variation' ) );

		$main_product = new \WC_Product_Variable();
		$main_product->set_sku( 'main-product_sku' );
		$main_product_id = $main_product->save();

		$variation_1 = new \WC_Product_Variation();
		$variation_1->set_parent_id( $main_product_id );
		$variation_1->set_sku( 'child-sku-1' );
		$variation_1->save();

		$variation_2 = new \WC_Product_Variation();
		$variation_2->set_parent_id( $main_product_id );
		$variation_2->set_sku( 'child-sku-2' );
		$variation_2->save();

		$main_product_as_post  = get_post( $main_product_id );
		$product_meta_to_index = ElasticPress\Features::factory()
			->get_registered_feature( 'woocommerce' )
			->products
			->add_variations_skus_meta( [], $main_product_as_post );

		$this->assertArrayHasKey( '_variations_skus', $product_meta_to_index );
		$this->assertContains( 'child-sku-1', $product_meta_to_index['_variations_skus'] );
		$this->assertContains( 'child-sku-2', $product_meta_to_index['_variations_skus'] );
	}

	/**
	 * Test the translate_args_admin_products_list method
	 *
	 * @group woocommerce
	 * @group woocommerce-products
	 */
	public function testTranslateArgsAdminProductsList() {
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->setup_features();

		parse_str( 'post_type=product&s=product&product_type=downloadable&stock_status=instock', $_GET );

		$query_args = [
			'ep_integrate' => true,
		];

		$woocommerce_feature = ElasticPress\Features::factory()->get_registered_feature( 'woocommerce' );
		add_action( 'pre_get_posts', [ $woocommerce_feature->products, 'translate_args_admin_products_list' ] );

		$query = new \WP_Query( $query_args );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( $query->query_vars['s'], 'product' );
		$this->assertEquals( $query->query_vars['meta_query'][0]['key'], '_downloadable' );
		$this->assertEquals( $query->query_vars['meta_query'][0]['value'], 'yes' );
		$this->assertEquals( $query->query_vars['meta_query'][1]['key'], '_stock_status' );
		$this->assertEquals( $query->query_vars['meta_query'][1]['value'], 'instock' );
		$this->assertEquals(
			$query->query_vars['search_fields'],
			[
				'post_title',
				'post_content',
				'post_excerpt',
				'meta' => [
					'_sku',
					'_variations_skus',
				],
			]
		);
	}

	/**
	 * Test the ep_woocommerce_admin_products_list_search_fields filter
	 *
	 * @group woocommerce
	 * @group woocommerce-products
	 */
	public function testEPWoocommerceAdminProductsListSearchFields() {
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->setup_features();

		parse_str( 'post_type=product&s=product&product_type=downloadable', $_GET );

		$query_args = [
			'ep_integrate' => true,
		];

		$woocommerce_feature = ElasticPress\Features::factory()->get_registered_feature( 'woocommerce' );
		add_action( 'pre_get_posts', [ $woocommerce_feature->products, 'translate_args_admin_products_list' ] );

		$search_fields_function = function () {
			return [ 'post_title', 'post_content' ];
		};
		add_filter( 'ep_woocommerce_admin_products_list_search_fields', $search_fields_function );

		$query = new \WP_Query( $query_args );
		$this->assertEquals(
			$query->query_vars['search_fields'],
			[ 'post_title', 'post_content' ]
		);
	}

	/**
	 * Test if decaying is disabled on products.
	 *
	 * @dataProvider decayingDisabledOnProductsProvider
	 * @group woocommerce
	 * @group woocommerce-products
	 *
	 * @param string       $setting   Value for `decaying_enabled`
	 * @param array|string $post_type Post types to be queried
	 * @param string       $assert    Assert method name (`assertDecayDisabled` or `assertDecayEnabled`)
	 */
	public function testDecayingDisabledOnProducts( $setting, $post_type, $assert ) {
		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->setup_features();

		// Test decaying for product query when disabled_only_products is enabled
		ElasticPress\Features::factory()->update_feature(
			'search',
			[
				'active'           => true,
				'decaying_enabled' => $setting,
			]
		);

		$query          = new \WP_Query();
		$query_args     = [
			's'         => 'test',
			'post_type' => $post_type,
		];
		$formatted_args = \ElasticPress\Indexables::factory()->get( 'post' )->format_args( $query_args, $query );

		$this->$assert( $formatted_args['query'] );
	}

	/**
	 * Data provider for the testDecayingDisabledOnProducts method.
	 *
	 * @return array
	 */
	public function decayingDisabledOnProductsProvider() : array {
		return [
			[
				'disabled_only_products',
				'product',
				'assertDecayDisabled',
			],
			[
				'disabled_only_products',
				[ 'product' ],
				'assertDecayDisabled',
			],
			[
				'disabled_only_products',
				[ 'product', 'post' ],
				'assertDecayEnabled',
			],
			[
				'disabled_includes_products',
				'product',
				'assertDecayDisabled',
			],
			[
				'disabled_includes_products',
				[ 'product' ],
				'assertDecayDisabled',
			],
			[
				'disabled_includes_products',
				[ 'product', 'post' ],
				'assertDecayDisabled',
			],
			[
				'disabled_includes_products',
				[ 'post', 'page' ],
				'assertDecayEnabled',
			],
		];
	}

	/**
	 * Test the `get_supported_post_types` method
	 *
	 * @group woocommerce
	 * @group woocommerce-products
	 */
	public function testGetSupportedPostTypes() {
		$query = new \WP_Query( [] );

		$default_supported = $this->products->get_supported_post_types( $query );
		$this->assertSame( $default_supported, [] );

		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->setup_features();

		$default_supported = $this->products->get_supported_post_types( $query );
		$this->assertSame( $default_supported, [ 'product_variation' ] );

		/**
		 * Test the `ep_woocommerce_products_supported_post_types` filter
		 */
		$add_post_type = function( $post_types, $filter_query ) use ( $query ) {
			$this->assertSame( $filter_query, $query );
			$post_types[] = 'post';
			return $post_types;
		};
		add_filter( 'ep_woocommerce_products_supported_post_types', $add_post_type, 10, 2 );

		$custom_supported = $this->products->get_supported_post_types( $query );
		$this->assertSame( $custom_supported, [ 'product_variation', 'post' ] );

		$this->markTestIncomplete( 'This test should also test the addition of the `product` post type under some circumstances.' );
	}

	/**
	 * Test the `get_supported_taxonomies` method
	 *
	 * @group woocommerce
	 * @group woocommerce-products
	 */
	public function testGetSupportedTaxonomies() {
		$default_supported = $this->products->get_supported_taxonomies();
		$expected          = [
			'product_cat',
			'product_tag',
			'product_type',
			'product_visibility',
			'product_shipping_class',
		];
		$this->assertSame( $default_supported, $expected );

		/**
		 * Test the `ep_woocommerce_products_supported_taxonomies` filter
		 */
		$add_taxonomy = function( $taxonomies ) {
			$taxonomies[] = 'custom_category';
			return $taxonomies;
		};
		add_filter( 'ep_woocommerce_products_supported_taxonomies', $add_taxonomy );

		$custom_supported = $this->products->get_supported_taxonomies();
		$this->assertSame( $custom_supported, array_merge( $expected, [ 'custom_category' ] ) );
	}

	/**
	 * Test the `get_orderby_meta_mapping` method
	 *
	 * @dataProvider orderbyMetaMappingDataProvider
	 * @group woocommerce
	 * @group woocommerce-products
	 *
	 * @param string $meta_key   Original meta key value
	 * @param string $translated Expected translated version
	 */
	public function testOrderbyMetaMapping( $meta_key, $translated ) {
		$this->assertSame( $this->products->get_orderby_meta_mapping( $meta_key ), $translated );
	}

	/**
	 * Data provider for the testOrderbyMetaMapping method.
	 *
	 * @return array
	 */
	public function orderbyMetaMappingDataProvider() {
		return [
			[ 'ID', 'ID' ],
			[ 'title', 'title date' ],
			[ 'menu_order', 'menu_order title date' ],
			[ 'menu_order title', 'menu_order title date' ],
			[ 'total_sales', 'meta.total_sales.double date' ],
			[ '_wc_average_rating', 'meta._wc_average_rating.double date' ],
			[ '_price', 'meta._price.double date' ],
			[ '_sku', 'meta._sku.value.sortable date' ],
			[ 'custom_parameter', 'date' ],
		];
	}

	/**
	 * Test the `orderby_meta_mapping` filter
	 *
	 * @group woocommerce
	 * @group woocommerce-products
	 */
	public function testOrderbyMetaMappingFilter() {
		$add_value = function ( $mapping ) {
			$mapping['custom_parameter'] = 'meta.custom_parameter.long';
			return $mapping;
		};
		add_filter( 'orderby_meta_mapping', $add_value );

		$this->assertSame( $this->products->get_orderby_meta_mapping( 'custom_parameter' ), 'meta.custom_parameter.long' );
	}

	/**
	 * Test add_taxonomy_attributes.
	 *
	 * @group woocommerce
	 * @group woocommerce-products
	 */
	public function test_add_taxonomy_attributes() {
		$attributes = wc_get_attribute_taxonomies();

		$slugs = wp_list_pluck( $attributes, 'attribute_name' );

		if ( ! in_array( 'my_color', $slugs, true ) ) {

			$args = array(
				'slug'         => 'my_color',
				'name'         => 'My color',
				'type'         => 'select',
				'orderby'      => 'menu_order',
				'has_archives' => false,
			);

			wc_create_attribute( $args );
		}

		$facet_feature = ElasticPress\Features::factory()->get_registered_feature( 'facets' );
		$facet_type    = $facet_feature->types['taxonomy'];

		parse_str( 'ep_filter_taxonomy=dolor,amet&ep_filter_my_color=red', $_GET );

		$query_filters = $facet_type->add_query_filters( [] );

		$sample_test[0]['term']['terms.taxonomy.slug']    = 'dolor';
		$sample_test[1]['term']['terms.taxonomy.slug']    = 'amet';
		$sample_test[2]['term']['terms.pa_my_color.slug'] = 'red';

		$this->assertEquals( $sample_test, $query_filters );
	}
}
