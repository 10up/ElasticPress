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

	public function query_db( $args ) {
		$defaults = [
			'number'  => 350,
			'offset'  => 0,
			'orderby' => 'id',
			'order'   => 'desc',
		];

		$parsed_args = $args;

		if ( isset( $args['per_page'] ) ) {
			$args['number'] = $args['per_page'];
		}

		$args = apply_filters( 'ep_user_query_db_args', wp_parse_args( $args, $defaults ) );

		$query = new WP_Query( $args );

		return [
			'objects'       => $query->results,
			'total_objects' => $query->total_users,
		];
	}

	public function put_mapping() {
		$mapping = require( apply_filters( 'ep_user_mapping_file', __DIR__ . '/../../../mappings/user/initial.php' ) );

		return Elasticsearch::factory()->put_mapping( $this->get_index_name(), $mapping );
	}

	public function prepare_document( $user_id ) {
		$user = get_user_by( 'ID', $user_id );

		$user_args = [
			'ID'              => $user_id,
			'user_login'      => $user->user_login,
			'user_email'      => $user->user_email,
			'spam'            => $user->spam,
			'deleted'         => $user->spam,
			'user_status'     => $user->user_status,
			'display_name'    => $user->display_name,
			'user_registered' => $user->user_registered,
			'user_url'        => $user->user_url,
			'meta'            => $this->prepare_meta_types( $this->prepare_meta( $user_id ) ),
		];

		$user_args = apply_filters( 'ep_user_sync_args', $user_args, $user_id );

		return $user_args;
	}

	/**
	 * Prepare meta to send to ES
	 *
	 * @param int $user_id
	 * @since 2.6
	 * @return array
	 */
	public function prepare_meta( $user_id ) {
		$meta = (array) get_user_meta( $user_id );

		if ( empty( $meta ) ) {
			return [];
		}

		$prepared_meta = [];

		/**
		 * Filter index-able private meta
		 *
		 * Allows for specifying private meta keys that may be indexed in the same manor as public meta keys.
		 *
		 * @since 2.6
		 *
		 * @param         array Array of index-able private meta keys.
		 * @param WP_Post $post The current post to be indexed.
		 */
		$allowed_protected_keys = apply_filters( 'ep_prepare_user_meta_allowed_protected_keys', [], $user_id );

		/**
		 * Filter non-indexed public meta
		 *
		 * Allows for specifying public meta keys that should be excluded from the ElasticPress index.
		 *
		 * @since 2.6
		 *
		 * @param         array Array of public meta keys to exclude from index.
		 * @param WP_Post $post The current post to be indexed.
		 */
		$excluded_public_keys = apply_filters( 'ep_prepare_user_meta_excluded_public_keys', [], $user_id );

		foreach ( $meta as $key => $value ) {

			$allow_index = false;

			if ( is_protected_meta( $key ) ) {

				if ( true === $allowed_protected_keys || in_array( $key, $allowed_protected_keys ) ) {
					$allow_index = true;
				}
			} else {

				if ( true !== $excluded_public_keys && ! in_array( $key, $excluded_public_keys )  ) {
					$allow_index = true;
				}
			}

			if ( true === $allow_index || apply_filters( 'ep_prepare_user_meta_whitelist_key', false, $key, $user_id ) ) {
				$prepared_meta[ $key ] = maybe_unserialize( $value );
			}
		}

		return $prepared_meta;
	}
}
