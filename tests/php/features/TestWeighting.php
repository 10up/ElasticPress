<?php
/**
 * Test weighting sub-feature
 *
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress;

/**
 * Weighting test class
 */
class TestWeighting extends BaseTestCase {

	/**
	 * Setup each test.
	 *
	 * @since 3.4.1
	 */
	public function setUp() {
		global $wpdb;
		parent::setUp();
		$wpdb->suppress_errors();

		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );

		wp_set_current_user( $admin_id );

		ElasticPress\Elasticsearch::factory()->delete_all_indices();
		ElasticPress\Indexables::factory()->get( 'post' )->put_mapping();

		ElasticPress\Indexables::factory()->get( 'post' )->sync_manager->sync_queue = [];

		$this->setup_test_post_type();
		ElasticPress\Features::factory()->activate_feature( 'search' );
	}

	/**
	 * Clean up after each test. Reset our mocks
	 *
	 * @since 2.1
	 */
	public function tearDown() {
		parent::tearDown();

		// make sure no one attached to this
		remove_filter( 'ep_sync_terms_allow_hierarchy', array( $this, 'ep_allow_multiple_level_terms_sync' ), 100 );
		$this->fired_actions = array();
		update_option( 'elasticpress_weighting', [] );
	}

	/**
	 * @return weighting sub-feature
	 */
	public function get_weighting_feature() {
		$search = ElasticPress\Features::factory()->get_registered_feature( 'search' );

		return $search->weighting;
	}

	/**
	 * Test searchable post_types exist after configuration change
	 */
	function testWeightablePostType() {
		$search = ElasticPress\Features::factory()->get_registered_feature( 'search' );

		$searchable_post_types = $search->get_searchable_post_types();

		$weighting_settings = [
			'weighting' => [
				'post' => [
					'post_title' => [
						'enabled' => 'on',
						'weight'  => 1
					]
				],
			]
		];

		$this->get_weighting_feature()->save_weighting_configuration( $weighting_settings );

		$weighting_configuration = $this->get_weighting_feature()->get_weighting_configuration();

		$this->assertEquals( count( $searchable_post_types ), count( array_keys( $weighting_configuration ) ) );

		$this->assertFalse( in_array( 'ep_test_not_public', array_keys( $weighting_configuration ), true ) );

	}

	/**
	 * Test settings toggle
	 */
	public function testWeightingConfiguration() {

		$weighting_ep_test = $this->get_weighting_feature()->get_post_type_default_settings( 'ep_test' );
		$this->assertTrue( $weighting_ep_test['post_title']['enabled'] );

		$weighting_configuration = $this->get_weighting_feature()->get_weighting_configuration();
		$this->assertEmpty( $weighting_configuration );

		$weighting_settings = [
			'weighting' => [
				'post' => [
					'post_title' => [
						'enabled' => 'on',
						'weight'  => 1
					]
				],
			]
		];

		// enable post_title weighting
		$this->get_weighting_feature()->save_weighting_configuration( $weighting_settings );
		$weighting_configuration = $this->get_weighting_feature()->get_weighting_configuration();
		$this->assertTrue( $weighting_configuration['post']['post_title']['enabled'] );
		$this->assertEquals( 1, $weighting_configuration['post']['post_title']['weight'] );

		// disable post_title weighting
		$weighting_settings['weighting']['post']['post_title']['enabled'] = '';
		$this->get_weighting_feature()->save_weighting_configuration( $weighting_settings );
		$weighting_configuration = $this->get_weighting_feature()->get_weighting_configuration();
		$this->assertFalse( $weighting_configuration['post']['post_title']['enabled'] );

	}

	public function testGetWeightableFieldsForPostType() {
		$fields = $this->get_weighting_feature()->get_weightable_fields_for_post_type( 'ep_test' );

		$this->assertEquals( 2, count( $fields ) );
		$this->assertTrue( in_array( 'post_title', array_keys( $fields['attributes']['children'] ) ) );
		$this->assertTrue( in_array( 'terms.category.name', array_keys( $fields['taxonomies']['children'] ) ) );
		$this->assertTrue( in_array( 'terms.post_tag.name', array_keys( $fields['taxonomies']['children'] ) ) );
	}

	public function testAddWeightingSubmenuPage() {
		$site_url = trailingslashit( get_option( 'siteurl' ) );

		add_menu_page(
			'ElasticPress',
			'ElasticPress',
			'manage_options',
			'elasticpress'
		);

		$this->get_weighting_feature()->add_weighting_submenu_page();

		$this->assertEquals( $site_url . 'wp-admin/admin.php?page=elasticpress-weighting', menu_page_url( 'elasticpress-weighting', false ) );
	}

	public function testRenderSettingsPage() {
		ob_start();
		$this->get_weighting_feature()->render_settings_page();
		$content = ob_get_clean();

		$search = ElasticPress\Features::factory()->get_registered_feature( 'search' );
		$post_types = $search->get_searchable_post_types();

		$this->assertRegexp( '/Manage Search Fields &amp; Weighting/', $content );

		foreach ( $post_types as $post_type ) {
			$post_type_object = get_post_type_object( $post_type );
			$this->assertRegexp( '/<h2 class="hndle">'.$post_type_object->labels->menu_name.'/', $content );
		}
	}

	public function testRenderSettingsPageSaveSuccess() {
		$_GET['settings-updated'] = true;
		ob_start();
		$this->get_weighting_feature()->render_settings_page();
		$content = ob_get_clean();

		$this->assertRegexp( '/Changes Saved/', $content );
	}

	public function testRenderSettingsPageSaveFailed() {
		$_GET['settings-updated'] = false;
		ob_start();
		$this->get_weighting_feature()->render_settings_page();
		$content = ob_get_clean();

		$this->assertRegexp( '/An error occurred when saving/', $content );
	}


	public function testHandleSave() {
		$weighting_class = $this->getMockBuilder( 'ElasticPress\Feature\Search\Weighting' )
			->setMethods( [ 'redirect' ] )
			->getMock();

		$_POST['ep-weighting-nonce'] = false;
		$this->assertEquals( null, $weighting_class->handle_save() );

		// Change to non admin user
		wp_set_current_user($this->factory->user->create( array( 'role' => 'author' ) ) );

		$_POST['ep-weighting-nonce'] = wp_create_nonce( 'save-weighting' );
		$this->assertEquals( null, $weighting_class->handle_save() );

		wp_set_current_user($this->factory->user->create( array( 'role' => 'administrator' ) ) );
		$_POST = [
			'ep-weighting-nonce' => wp_create_nonce( 'save-weighting' ),
			'weighting' => [
				'post' => [
					'post_title' => [
						'enabled' => 'on',
						'weight'  => 1
					],
				],
			],
		];

		$weighting_class->expects( $this->once() )->method( 'redirect' );
		$weighting_class->handle_save();
	}

	public function testSaveWeightingConfigurationInvalidPostType() {

		$weighting_settings = [
			'weighting' => [
				'post' => [
					'post_title' => [
						'enabled' => 'on',
						'weight'  => 1
					]
				],
			]
		];

		add_filter( 'ep_searchable_post_types', function( $config ) {
			return array_merge( $config, [ 'invalid_post_type' ] );
		} );

		add_filter( 'ep_weighting_configuration', function( $config ) {
			return array_merge( $config, [ 'invalid_post_type' ] );
		} );

		$this->assertFalse( in_array( 'invalid_post_type', $this->get_weighting_feature()->save_weighting_configuration( $weighting_settings ) ) );
	}

	public function testRecursivelyInjectWeightsToFieldsInvalidArgs() {
		$invalid_args = '';
		$this->assertEquals( null, $this->get_weighting_feature()->recursively_inject_weights_to_fields( $invalid_args, $this->weighting_settings['weighting']['post'] ) );
	}

	public function testPostTypeHasFieldsWithDefaultConfig() {
		$this->assertTrue( $this->get_weighting_feature()->post_type_has_fields( 'post' ) );
	}

	public function testPostTypeHasFieldsWithCustomConfig() {
		// Test with configuration saved for post only, page will return false.
		$weighting_settings = [
			'weighting' => [
				'post' => [
					'post_title' => [
						'enabled' => 'on',
						'weight'  => 1
					]
				],
			]
		];
		$this->get_weighting_feature()->save_weighting_configuration( $weighting_settings );

		$this->assertTrue( $this->get_weighting_feature()->post_type_has_fields( 'post' ) );
		$this->assertFalse( $this->get_weighting_feature()->post_type_has_fields( 'page' ) );
	}

	public function testDoWeightingWithQueryContainsSearchFields() {
		// Test search fields are set on the query.
		$this->assertEquals( ['do', 'nothing'], $this->get_weighting_feature()->do_weighting( ['do', 'nothing'], ['search_fields' => [ 'post_title' ] ] ) );
	}

	public function testDoWeightingInAdmin() {
		// Test if we're in admin area.
		set_current_screen( 'edit-post' );
		$this->assertEquals( ['do', 'nothing'], $this->get_weighting_feature()->do_weighting( ['do', 'nothing'], ['s' => 'blog' ] ) );
	}

	public function testDoWeightingWithEmptySearchQuery() {
		unset( $GLOBALS['current_screen'] );

		// Test if search query is empty.
		$this->assertEquals( ['do', 'nothing'], $this->get_weighting_feature()->do_weighting( ['do', 'nothing'], ['s' => '' ] ) );
	}

	public function testDoWeightingWithoutFunctionScore() {
		unset( $GLOBALS['current_screen'] );

		$this->get_weighting_feature()->save_weighting_configuration( $this->weighting_settings );

		$args = ['s' => 'blog', 'post_type' => ['post', 'page'] ];

		$new_formatted_args = $this->get_weighting_feature()->do_weighting( $this->formatted_args, $args );

		$this->assertEquals( 2, count( $new_formatted_args['query']['bool']['should'] ) );
	}

	public function testDoWeightingWithFunctionScore() {
		unset( $GLOBALS['current_screen'] );

		$this->get_weighting_feature()->save_weighting_configuration( $this->weighting_settings );

		$args = ['s' => 'blog', 'post_type' => ['post', 'page'] ];

		$new_formatted_args = $this->get_weighting_feature()->do_weighting( $this->formatted_args_with_function_score, $args );

		$this->assertEquals( 2, count( $new_formatted_args['query']['function_score']['query']['bool']['should'] ) );
	}

	public function testDoWeightingWithoutPageFields() {
		unset( $GLOBALS['current_screen'] );

		$weighting_settings = [
			'weighting' => [
				'post' => [
					'post_title' => [
						'enabled' => 'on',
						'weight'  => 1
					]
				],
			]
		];
		$this->get_weighting_feature()->save_weighting_configuration( $weighting_settings );

		$args = ['s' => 'blog', 'post_type' => ['post', 'page'] ];

		$new_formatted_args = $this->get_weighting_feature()->do_weighting( $this->formatted_args, $args );

		$this->assertEquals( 1, count( $new_formatted_args['query']['bool']['should'] ) );
	}

	public function testDoWeightingWithoutPageWeightConfig() {
		unset( $GLOBALS['current_screen'] );
		add_filter( 'ep_weighting_configuration_for_search', function( $config ) {
			return array_filter(
				$config,
				function( $key ) {
					return $key != 'page';
				},
				ARRAY_FILTER_USE_KEY
			);
		} );


		$this->get_weighting_feature()->save_weighting_configuration( $this->weighting_settings );

		$args = ['s' => 'blog', 'post_type' => ['post', 'page'] ];

		$new_formatted_args = $this->get_weighting_feature()->do_weighting( $this->formatted_args, $args );

		$this->assertEquals( 2, count( $new_formatted_args['query']['bool']['should'] ) );
	}

	public $weighting_settings = [
		'weighting' => [
			'post' => [
				'post_title'   => [
					'weight'  => 1,
					'enabled' => 'on',
				],
				'post_content' => [
					'weight'  => 1,
					'enabled' => 'on',
				],
				'post_excerpt' => [
					'weight'  => 1,
					'enabled' => 'on',
				],

				'author_name'  => [
					'weight'  => 0,
					'enabled' => 'on',
				],
			],
			'page' => [
				'post_title'   => [
					'weight'  => 1,
					'enabled' => 'on',
				],
				'post_content' => [
					'weight'  => 1,
					'enabled' => 'on',
				],
				'post_excerpt' => [
					'weight'  => 1,
					'enabled' => 'on',
				],

				'author_name'  => [
					'weight'  => 0,
					'enabled' => false,
				],
			],
		]
	];

	public $formatted_args = [
		'from' => 0,
		'size' => 10,
		'sort' => [
			0 => [
				'_score' => [
					'order' => 'desc',
				],
			],
		],
		'query' => [
			'bool' => [
				'should' => [
					0 => [
						'multi_match' => [
							'query' => 'blog',
							'type' => 'phrase',
							'fields' => [
								0 => 'post_title',
								1 => 'post_excerpt',
								2 => 'post_content',
							],
							'boost' => 4,
						],
					],
					1 => [
						'multi_match' => [
							'query' => 'blog',
							'fields' => [
								0 => 'post_title',
								1 => 'post_excerpt',
								2 => 'post_content',
							],
							'boost' => 2,
							'fuzziness' => 0,
							'operator' => 'and',
						],
					],
					2 => [
						'multi_match' => [
							'query' => 'blog',
							'fields' => [
								0 => 'post_title',
								1 => 'post_excerpt',
								2 => 'post_content',
							],
							'fuzziness' => 'auto',
						],
					],
				],
			],
		],
		'post_filter' => [
			'bool' => [
				'must' => [
					0 => [
						'terms' => [
							'post_type.raw' => [
								0 => 'post',
								1 => 'page',
							],
						],
					],
					1 => [
						'terms' => [
							'post_status' => [
								0 => 'publish',
							],
						],
					],
				],
			],
		],
		'aggs' => [
			'terms' => [
				'filter' => [
					'bool' => [
						'must' => [
							0 => [
								'terms' => [
									'post_type.raw' => [
										0 => 'post',
										1 => 'page',
									],
								],
							],
							1 => [
								'terms' => [
									'post_status' => [
										0 => 'publish',
									],
								],
							],
						],
					],
				],
				'aggs' => [
					'category' => [
						'terms' => [
							'size' => 10000,
							'field' => 'terms.category.slug',
						],
					],
					'post_tag' => [
						'terms' => [
							'size' => 10000,
							'field' => 'terms.post_tag.slug',
						],
					],
					'post_format' => [
						'terms' => [
							'size' => 10000,
							'field' => 'terms.post_format.slug',
						],
					],
					'ep_custom_result' => [
						'terms' => [
							'size' => 10000,
							'field' => 'terms.ep_custom_result.slug',
						],
					],
				],
			],
		],
	];

	public $formatted_args_with_function_score = [
		'from' => 0,
		'size' => 10,
		'sort' => [
			0 => [
				'_score' => [
					'order' => 'desc']]],
					'query' => [
						'function_score' => [
							'query' => [
								'bool' => [
									'should' => [
										0 => [
											'multi_match' => [
												'query' => 'blog',
												'type' => 'phrase',
												'fields' => [
													0 => 'post_title',
													1 => 'post_excerpt',
													2 => 'post_content',
												],
												'boost' => 4,
											],
										],
										1 => [
											'multi_match' => [
												'query' => 'blog',
												'fields' => [
													0 => 'post_title',
													1 => 'post_excerpt',
													2 => 'post_content',
												],
												'boost' => 2,
												'fuzziness' => 0,
												'operator' => 'and',
											],
										],
										2 => [
											'multi_match' => [
												'query' => 'blog',
												'fields' => [
													0 => 'post_title',
													1 => 'post_excerpt',
													2 => 'post_content',
												],
												'fuzziness' => 'auto',
											],
										],
									],
								],
							],
							'functions' => [
								0 => [
									'exp' => [
										'post_date_gmt' => [
											'scale' => '14d',
											'decay' => 0.25,
											'offset' => '7d',
										],
									],
								],
							],
							'score_mode' => 'avg',
							'boost_mode' => 'sum',
						],
					],
					'post_filter' => [
						'bool' => [
							'must' => [
								0 => [
									'terms' => [
										'post_type.raw' => [
											0 => 'post',
											1 => 'page',
										],
									],
								],
								1 => [
									'terms' => [
										'post_status' => [
											0 => 'publish',
										],
									],
								],
							],
						],
					],
					'aggs' => [
						'terms' => [
							'filter' => [
								'bool' => [
									'must' => [
										0 => [
											'terms' => [
												'post_type.raw' => [
													0 => 'post',
													1 => 'page',
												],
											],
										],
										1 => [
											'terms' => [
												'post_status' => [
													0 => 'publish',
												],
											],
										],
									],
								],
							],
							'aggs' => [
								'category' => [
									'terms' => [
										'size' => 10000,
										'field' => 'terms.category.slug',
									],
								],
								'post_tag' => [
									'terms' => [
										'size' => 10000,
										'field' => 'terms.post_tag.slug',
									],
								],
								'post_format' => [
									'terms' => [
										'size' => 10000,
										'field' => 'terms.post_format.slug',
									],
								],
								'ep_custom_result' => [
									'terms' => [
										'size' => 10000,
										'field' => 'terms.ep_custom_result.slug',
									],
								],
							],
						],
					],
	];

}
