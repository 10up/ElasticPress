<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

return array(
	'settings' => array(
		'analysis' => array(
			'analyzer' => array(
				'ewp_email'     => array(
					'tokenizer' => 'uax_url_email',
					'filter'    => array( 'standard', 'lowercase' )
				),
				'ewp_lowercase' => array(
					'type'      => 'custom',
					'tokenizer' => 'keyword',
					'filter'    => array( 'lowercase' ),
				),
			)
		)
	),
	'mappings' => array(
		'user' => array(
			'date_detection'    => false,
			'dynamic_templates' => array(
				array(
					'template_meta' => array(
						'path_match' => 'user_meta.*',
						'mapping'    => array(
							'type'   => 'multi_field',
							'path'   => 'full',
							'fields' => array(
								'{name}' => array(
									'type'  => 'string',
									'index' => 'analyzed',
								),
								'raw'    => array(
									'type'           => 'string',
									'index'          => 'not_analyzed',
									'include_in_all' => false,
								),
							),
						),
					),
				),
				array(
					'template_meta_types' => array(
						'path_match' => 'meta.*',
						'mapping'    => array(
							'type'       => 'object',
							'path'       => 'full',
							'properties' => array(
								'value'    => array(
									'type'   => 'string',
									'fields' => array(
										'sortable' => array(
											'type'           => 'string',
											'analyzer'       => 'ewp_lowercase',
											'include_in_all' => false,
										),
										'raw'      => array(
											'type'           => 'string',
											'index'          => 'not_analyzed',
											'include_in_all' => false,
										),
									),
								),
								'raw'      => array( /* Left for backwards compat */
									'type'           => 'string',
									'index'          => 'not_analyzed',
									'include_in_all' => false,
								),
								'long'     => array(
									'type'  => 'long',
									'index' => 'not_analyzed',
								),
								'double'   => array(
									'type'  => 'double',
									'index' => 'not_analyzed',
								),
								'boolean'  => array(
									'type'  => 'boolean',
									'index' => 'not_analyzed',
								),
								'date'     => array(
									'type'   => 'date',
									'format' => 'yyyy-MM-dd',
									'index'  => 'not_analyzed',
								),
								'datetime' => array(
									'type'   => 'date',
									'format' => 'yyyy-MM-dd HH:mm:ss',
									'index'  => 'not_analyzed',
								),
								'time'     => array(
									'type'   => 'date',
									'format' => 'HH:mm:ss',
									'index'  => 'not_analyzed',
								),
							),
						),
					),
				),
				array(
					'template_terms' => array(
						'path_match' => 'terms.*',
						'mapping'    => array(
							'type'       => 'object',
							'path'       => 'full',
							'properties' => array(
								'name'    => array(
									'type'   => 'string',
									'fields' => array(
										'raw'      => array(
											'type'  => 'string',
											'index' => 'not_analyzed',
										),
										'sortable' => array(
											'type'     => 'string',
											'analyzer' => 'ewp_lowercase',
										),
									),
								),
								'term_id' => array(
									'type' => 'long',
								),
								'parent'  => array(
									'type' => 'long',
								),
								'slug'    => array(
									'type'  => 'string',
									'index' => 'not_analyzed',
								),
							),
						),
					),
				),
			),
			'_all'              => array(
				'analyzer' => 'simple',
			),
			'properties'        => array(
				'user_id'         => array(
					'type'           => 'long',
					'index'          => 'not_analyzed',
					'include_in_all' => false,
				),
				'user_login'      => array(
					'type'   => 'multi_field',
					'fields' => array(
						'user_login' => array(
							'type'     => 'string',
							'analyzer' => 'standard',
							'store'    => 'yes',
						),
						'raw'        => array(
							'type'           => 'string',
							'index'          => 'not_analyzed',
							'include_in_all' => false,
						),
						'sortable'   => array(
							'type'           => 'string',
							'analyzer'       => 'ewp_lowercase',
							'include_in_all' => false,
						),
					),
				),
				'user_nicename'   => array(
					'type'   => 'multi_field',
					'fields' => array(
						'user_nicename' => array(
							'type'     => 'string',
							'analyzer' => 'standard',
							'store'    => 'yes',
						),
						'raw'           => array(
							'type'           => 'string',
							'index'          => 'not_analyzed',
							'include_in_all' => false,
						),
						'sortable'      => array(
							'type'           => 'string',
							'analyzer'       => 'ewp_lowercase',
							'include_in_all' => false,
						),
					),
				),
				'nickname'        => array(
					'type'     => 'string',
					'analyzer' => 'standard',
				),
				'user_email'      => array(
					'type'     => 'string',
					'analyzer' => 'ewp_email',
				),
				'description'     => array(
					'type'     => 'string',
					'analyzer' => 'default',
				),
				'first_name'      => array(
					'type' => 'string',
				),
				'last_name'       => array(
					'type' => 'string',
				),
				'user_url'        => array(
					'type'   => 'multi_field',
					'fields' => array(
						'user_url' => array(
							'type'  => 'string',
							'index' => 'not_analyzed',
						),
						'sortable' => array(
							'type'           => 'string',
							'analyzer'       => 'ewp_lowercase',
							'include_in_all' => false
						),
					),
				),
				'display_name'    => array(
					'type'   => 'multi_field',
					'fields' => array(
						'display_name' => array(
							'type'     => 'string',
							'analyzer' => 'standard',
							'store'    => 'yes',
						),
						'raw'          => array(
							'type'           => 'string',
							'index'          => 'not_analyzed',
							'include_in_all' => false,
						),
						'sortable'     => array(
							'type'           => 'string',
							'analyzer'       => 'ewp_lowercase',
							'include_in_all' => false
						),
					),
				),
				'user_registered' => array(
					'type'           => 'date',
					'format'         => 'YYYY-MM-dd HH:mm:ss',
					'include_in_all' => false,
				),
				'role'            => array(
					'type'   => 'multi_field',
					'fields' => array(
						'role' => array(
							'type'     => 'string',
							'analyzer' => 'standard',
							'store'    => 'yes',
						),
						'raw'  => array(
							'type'           => 'string',
							'index'          => 'not_analyzed',
							'include_in_all' => false,
						)
					)
				),
				'terms'           => array(
					'type' => 'object',
				),
				'user_meta'       => array(
					'type' => 'object',
				),
				'meta'            => array(
					'type' => 'object',
				)
			),
		)
	),
);
