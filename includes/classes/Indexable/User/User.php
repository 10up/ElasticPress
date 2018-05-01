<?php

namespace ElasticPress\Indexable\User;

use ElasticPress\Indexable as Indexable;
use ElasticPress\Elasticsearch as Elasticsearch;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class User extends Indexable {

	public $indexable_type = 'user';

	public function __construct() {
		$this->labels = [
			'plural'   => esc_html__( 'Users', 'elasticpress' ),
			'singular' => esc_html__( 'User', 'elasticpress' ),
		];
	}

	public function put_mapping() {
		$mapping = require( apply_filters( 'ep_user_mapping_file', __DIR__ . '/../../../mappings/user/initial.php' ) );

		return Elasticsearch::factory()->put_mapping( $this->get_index_name(), $mapping );
	}
}
