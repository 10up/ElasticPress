<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

return array(
	'settings' => array(
		'analysis' => array(
			'analyzer' => array(
				'email' => array(
					'tokenizer' => 'uax_url_email',
					'filter'    => array( 'standard', 'lowercase' )
				)
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
					'template_terms' => array(
						'path_match' => 'terms.*',
						'mapping'    => array(
							'type'       => 'object',
							'path'       => 'full',
							'properties' => array(
								'name'    => array(
									'type' => 'string',
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
					'type'     => 'string',
					'analyzer' => 'standard',
				),
				'user_nicename'   => array(
					'type'           => 'string',
					'index'          => 'not_analyzed',
					'include_in_all' => false,
				),
				'nickname'        => array(
					'type'     => 'string',
					'analyzer' => 'standard',
				),
				'user_email'      => array(
					'type'     => 'string',
					'analyzer' => 'email',
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
					'type' => 'string',
				),
				'display_name'    => array(
					'type'   => 'multi_field',
					'fields' => array(
						'post_title' => array(
							'type'     => 'string',
							'analyzer' => 'standard',
							'store'    => 'yes',
						),
						'raw'        => array(
							'type'           => 'string',
							'index'          => 'not_analyzed',
							'include_in_all' => false,
						),
					),
				),
				'user_registered' => array(
					'type'           => 'date',
					'format'         => 'YYYY-MM-dd HH:mm:ss',
					'include_in_all' => false,
				),
				'role'            => array(
					'type' => 'string',
				),
				'terms'           => array(
					'type' => 'object',
				),
				'user_meta'       => array(
					'type'   => 'multi_field',
					'fields' => array(
						'post_type' => array(
							'type' => 'string',
						),
						'raw'       => array(
							'type'           => 'string',
							'index'          => 'not_analyzed',
							'include_in_all' => false,
						),
					),
				),
			),
		)
	),
);
