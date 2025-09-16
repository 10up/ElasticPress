<?php
/**
 * ElasticPress.io Template Manager trait for search API interactions
 *
 * @package elasticpress
 * @since 5.3.0
 */

namespace ElasticPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * ElasticPress.io Template Manager trait.
 *
 * This trait provides common functionality for features that interact with
 * ElasticPress.io search APIs, including search template management and API requests.
 *
 * @since 5.3.0
 */
trait ElasticPressIoTemplateManager {

	/**
	 * Get the endpoint for the search template.
	 *
	 * @return string Search template endpoint.
	 */
	abstract public function get_template_endpoint(): string;

	/**
	 * Generate and return a search template.
	 *
	 * @return string The search template as JSON.
	 */
	abstract public function get_search_template(): string;

	/**
	 * Get the feature slug.
	 *
	 * @return string Feature slug.
	 */
	abstract public function get_feature_slug(): string;

	/**
	 * Get the hook prefix
	 *
	 * @return string Hook prefix.
	 */
	public function get_hook_prefix(): string {
		return 'ep_' . str_replace( '-', '_', $this->get_feature_slug() );
	}

	/**
	 * Save the search template to ElasticPress.io.
	 *
	 * @return void
	 */
	public function epio_save_search_template(): void {
		$endpoint = $this->get_template_endpoint();
		$template = $this->get_search_template();

		Elasticsearch::factory()->remote_request(
			$endpoint,
			[
				'blocking' => false,
				'body'     => $template,
				'method'   => 'PUT',
			]
		);

		/**
		 * Fires after the request is sent the search template API endpoint.
		 *
		 * @since 5.3.0
		 * @hook {hook_prefix}_template_saved
		 * @param {string} $template The search template (JSON).
		 */
		do_action( $this->get_hook_prefix() . '_template_saved', $template );
	}

	/**
	 * Delete the search template from ElasticPress.io.
	 *
	 * @return void
	 */
	public function epio_delete_search_template(): void {
		$endpoint = $this->get_template_endpoint();

		Elasticsearch::factory()->remote_request(
			$endpoint,
			[
				'blocking' => false,
				'method'   => 'DELETE',
			]
		);

		/**
		 * Fires after the request is sent the search template API endpoint.
		 *
		 * @since 5.3.0
		 * @hook {hook_prefix}_template_deleted
		 */
		do_action( $this->get_hook_prefix() . '_template_deleted' );
	}

	/**
	 * Get the saved search template from ElasticPress.io.
	 *
	 * @return string|\WP_Error Search template if found, WP_Error on error.
	 */
	public function epio_get_search_template() {
		$endpoint = $this->get_template_endpoint();
		$request  = Elasticsearch::factory()->remote_request( $endpoint );

		if ( is_wp_error( $request ) ) {
			return $request;
		}

		$response = wp_remote_retrieve_body( $request );

		return $response;
	}

	/**
	 * Handle feature activation/deactivation to save or delete templates.
	 *
	 * @param string $feature  Feature slug
	 * @param array  $settings Feature settings
	 * @param array  $data     Feature activation data
	 *
	 * @return void
	 */
	public function after_update_feature( string $feature, array $settings, array $data ): void {
		if ( $feature !== $this->get_feature_slug() ) {
			return;
		}

		if ( true === $data['active'] ) {
			$this->epio_save_search_template();
		} else {
			$this->epio_delete_search_template();
		}
	}
}
