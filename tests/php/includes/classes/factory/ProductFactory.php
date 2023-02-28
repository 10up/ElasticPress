<?php
/**
 * Class for Product factory. Inspired by WooCommerce's WC_Helper_Product class.
 *
 * phpcs:disable WordPress.WP.AlternativeFunctions.rand_rand
 *
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress;

/**
 * Unit test factory for the product.
 *
 * @since 4.5.0
 */
class ProductFactory extends \WP_UnitTest_Factory_For_Post {

	/**
	 * Constructor.
	 *
	 * @param \WP_UnitTest_Factory $factory The factory.
	 */
	public function __construct( $factory = null ) {
		parent::__construct( $factory );
		$this->default_generation_definitions = array(
			'name'          => new \WP_UnitTest_Generator_Sequence( 'Product name %s' ),
			'regular_price' => rand( 1, 100 ),
			'price'         => rand( 1, 100 ),
			'sku'           => new \WP_UnitTest_Generator_Sequence( 'SKU %s' ),
		);
	}

	/**
	 * Creates a product object.
	 *
	 * @param array $args Array with elements for the product.
	 *
	 * @return int
	 */
	public function create_object( $args ) {
		$product = new \WC_Product_Simple();

		$default_props = array(
			'manage_stock' => false,
			'tax_status'   => 'taxable',
			'downloadable' => false,
			'virtual'      => false,
			'stock_status' => 'instock',
			'weight'       => '1.1',
		);

		$product->set_props( array_merge( $default_props, $args ) );
		$product->save();

		ElasticPress\Indexables::factory()->get( 'post' )->index( $product->get_id() );
		return $product->get_id();
	}

	/**
	 * Creates a variation product object.
	 *
	 * @param array $args Array with elements for the product.
	 *
	 * @return int
	 */
	public function create_variation_product( $args = array() ) {
		$product = new \WC_Product_Variable();

		$generated_args = $this->generate_args( $args, $this->default_generation_definitions );

		$product->set_props( $generated_args );

		$attributes = array();

		$attributes[] = $this->create_product_attribute_object( 'size', array( 'small', 'large', 'huge' ) );
		$attributes[] = $this->create_product_attribute_object( 'colour', array( 'red', 'blue' ) );
		$attributes[] = $this->create_product_attribute_object( 'number', array( '0', '1', '2' ) );

		$product->set_attributes( $attributes );
		$product->save();

		$variations = array();

		$variations[] = $this->create_product_variation_object(
			$product->get_id(),
			'DUMMY SKU VARIABLE SMALL',
			10,
			array( 'pa_size' => 'small' )
		);

		$variations[] = $this->create_product_variation_object(
			$product->get_id(),
			'DUMMY SKU VARIABLE LARGE',
			15,
			array( 'pa_size' => 'large' )
		);

		$variations[] = $this->create_product_variation_object(
			$product->get_id(),
			'DUMMY SKU VARIABLE HUGE RED 0',
			16,
			array(
				'pa_size'   => 'huge',
				'pa_colour' => 'red',
				'pa_number' => '0',
			)
		);

		$variations[] = $this->create_product_variation_object(
			$product->get_id(),
			'DUMMY SKU VARIABLE HUGE RED 2',
			17,
			array(
				'pa_size'   => 'huge',
				'pa_colour' => 'red',
				'pa_number' => '2',
			)
		);

		$variations[] = $this->create_product_variation_object(
			$product->get_id(),
			'DUMMY SKU VARIABLE HUGE BLUE 2',
			18,
			array(
				'pa_size'   => 'huge',
				'pa_colour' => 'blue',
				'pa_number' => '2',
			)
		);

		$variations[] = $this->create_product_variation_object(
			$product->get_id(),
			'DUMMY SKU VARIABLE HUGE BLUE ANY NUMBER',
			19,
			array(
				'pa_size'   => 'huge',
				'pa_colour' => 'blue',
				'pa_number' => '',
			)
		);

		$variation_ids = array_map(
			function ( $variation ) {
				return $variation->get_id();
			},
			$variations
		);
		$product->set_children( $variation_ids );

		ElasticPress\Indexables::factory()->get( 'post' )->index( $product->get_id() );
		return $product->get_id();
	}


	/**
	 * Creates an instance of WC_Product_Variation with the supplied parameters, optionally persisting it to the database.
	 *
	 * @param string $parent_id Parent product id.
	 * @param string $sku SKU for the variation.
	 * @param int    $price Price of the variation.
	 * @param array  $attributes Attributes that define the variation, e.g. ['pa_color'=>'red'].
	 * @param bool   $save If true, the object will be saved to the database after being created and configured.
	 *
	 * @return WC_Product_Variation The created object.
	 */
	public function create_product_variation_object( $parent_id, $sku, $price, $attributes, $save = true ) {
		$variation = new \WC_Product_Variation();
		$variation->set_props(
			array(
				'parent_id'     => $parent_id,
				'sku'           => $sku,
				'regular_price' => $price,
			)
		);
		$variation->set_attributes( $attributes );
		if ( $save ) {
			$variation->save();
		}
		return $variation;
	}

	/**
	 * Creates an instance of WC_Product_Attribute with the supplied parameters.
	 *
	 * @param string $raw_name Attribute raw name (without 'pa_' prefix).
	 * @param array  $terms Possible values for the attribute.
	 *
	 * @return WC_Product_Attribute The created attribute object.
	 */
	public function create_product_attribute_object( $raw_name = 'size', $terms = array( 'small' ) ) {
		$attribute      = new \WC_Product_Attribute();
		$attribute_data = $this->create_attribute( $raw_name, $terms );
		$attribute->set_id( $attribute_data['attribute_id'] );
		$attribute->set_name( $attribute_data['attribute_taxonomy'] );
		$attribute->set_options( $attribute_data['term_ids'] );
		$attribute->set_position( 1 );
		$attribute->set_visible( true );
		$attribute->set_variation( true );
		return $attribute;
	}

	/**
	 * Create a dummy attribute.
	 *
	 * @since 2.3
	 *
	 * @param string        $raw_name Name of attribute to create.
	 * @param array(string) $terms          Terms to create for the attribute.
	 * @return array
	 */
	public function create_attribute( $raw_name = 'size', $terms = array( 'small' ) ) {
		global $wc_product_attributes;

		// Make sure caches are clean.
		delete_transient( 'wc_attribute_taxonomies' );
		\WC_Cache_Helper::invalidate_cache_group( 'woocommerce-attributes' );

		// These are exported as labels, so convert the label to a name if possible first.
		$attribute_labels = wp_list_pluck( wc_get_attribute_taxonomies(), 'attribute_label', 'attribute_name' );
		$attribute_name   = array_search( $raw_name, $attribute_labels, true );

		if ( ! $attribute_name ) {
			$attribute_name = wc_sanitize_taxonomy_name( $raw_name );
		}

		$attribute_id = wc_attribute_taxonomy_id_by_name( $attribute_name );

		if ( ! $attribute_id ) {
			$taxonomy_name = wc_attribute_taxonomy_name( $attribute_name );

			// Degister taxonomy which other tests may have created...
			unregister_taxonomy( $taxonomy_name );

			$attribute_id = wc_create_attribute(
				array(
					'name'         => $raw_name,
					'slug'         => $attribute_name,
					'type'         => 'select',
					'order_by'     => 'menu_order',
					'has_archives' => 0,
				)
			);

			// Register as taxonomy.
			register_taxonomy(
				$taxonomy_name,
				apply_filters( 'woocommerce_taxonomy_objects_' . $taxonomy_name, array( 'product' ) ),
				apply_filters(
					'woocommerce_taxonomy_args_' . $taxonomy_name,
					array(
						'labels'       => array(
							'name' => $raw_name,
						),
						'hierarchical' => false,
						'show_ui'      => false,
						'query_var'    => true,
						'rewrite'      => false,
					)
				)
			);

			// Set product attributes global.
			$wc_product_attributes = array();

			foreach ( wc_get_attribute_taxonomies() as $taxonomy ) {
				$wc_product_attributes[ wc_attribute_taxonomy_name( $taxonomy->attribute_name ) ] = $taxonomy;
			}
		}

		$attribute = wc_get_attribute( $attribute_id );
		$return    = array(
			'attribute_name'     => $attribute->name,
			'attribute_taxonomy' => $attribute->slug,
			'attribute_id'       => $attribute_id,
			'term_ids'           => array(),
		);

		foreach ( $terms as $term ) {
			$result = term_exists( $term, $attribute->slug );

			if ( ! $result ) {
				$result               = wp_insert_term( $term, $attribute->slug );
				$return['term_ids'][] = $result['term_id'];
			} else {
				$return['term_ids'][] = $result['term_id'];
			}
		}

		return $return;
	}
}
