<?php
/**
 * Search Algorithm Feature
 *
 * @since 5.4.0
 * @package ElasticPress
 */

namespace ElasticPress\Feature;

use ElasticPress\Feature;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * SearchAlgorithm class.
 *
 * @since 5.4.0
 */
class SearchAlgorithm extends Feature {

	/**
	 * Order of the feature in ElasticPress's Dashboard.
	 *
	 * @var integer
	 */
	public $order = 10;

	/**
	 * Initialize feature settings.
	 */
	public function __construct() {
		$this->slug = 'search_algorithm';

		if ( ! defined( 'EP_VERSION' ) || version_compare( EP_VERSION, '5.2.0', '<' ) ) {
			$this->set_i18n_strings();
		}

		$this->requires_install_reindex = false;
		$this->default_settings         = [
			'search_algorithm_version' => '3.5',
		];

		parent::__construct();
	}

	/**
	 * Sets i18n strings.
	 *
	 * @return void
	 * @since 5.4.0
	 */
	public function set_i18n_strings(): void {
		$this->title = esc_html__( 'Search Algorithm Version', 'elasticpress' );
	}

	/**
	 * Output feature box summary.
	 */
	public function output_feature_box_summary() {
		?>
		<p><?php esc_html_e( 'Change the version of the search algorithm.', 'elasticpress' ); ?></p>
		<?php
	}

	/**
	 * Output feature box long
	 */
	public function output_feature_box_long() {
		?>
		<p><?php esc_html_e( 'By default, the ElasticPress uses version 3.5 but you can change to version 3.4.', 'elasticpress' ); ?></p>
		<?php
	}

	/**
	 * Setup your feature functionality.
	 * Use this method to hook your feature functionality to ElasticPress or WordPress.
	 */
	public function setup() {
		$settings = $this->get_settings();

		if ( empty( $settings['active'] ) ) {
			return;
		}

		add_filter( 'ep_post_search_algorithm', [ $this, 'get_search_algorithm_version' ] );
		add_filter( 'ep_sanitize_feature_settings', [ $this, 'fix_search_algorithm_version' ], 10, 2 );
		add_filter( 'option_ep_feature_settings', [ $this, 'maybe_apply_default_search_algorithm_version' ] );
		add_filter( 'option_ep_feature_settings_draft', [ $this, 'maybe_apply_default_search_algorithm_version' ] );
	}

	/**
	 * Display field settings on the Dashboard.
	 */
	public function output_feature_box_settings() {
		if ( ! defined( 'EP_VERSION' ) || version_compare( EP_VERSION, '5.0.0', '<' ) ) {
			$settings = $this->get_settings();

			if ( ! $settings ) {
				$settings = [];
			}

			$settings = wp_parse_args( $settings, $this->default_settings );

			?>
			<div class="field">
				<div class="field-name status">
					<?php esc_html_e( 'Version', 'elasticpress' ); ?>
				</div>

				<div class="input-wrap">
					<?php
					$search_algorithms = \ElasticPress\SearchAlgorithms::factory()->get_all();
					foreach ( $search_algorithms as $search_algorithm ) {
						?>
						<label>
							<input
								name="settings[search_algorithm_version]"
								type="radio"
								<?php checked( $settings['search_algorithm_version'], $search_algorithm->get_slug() ); ?>
								value="<?php echo esc_attr( $search_algorithm->get_slug() ); ?>">
							<?php echo esc_html( $search_algorithm->get_name() ); ?>
						</label>
						<p class="field-description">
							<?php echo wp_kses_post( $search_algorithm->get_description() ); ?>
						</p>
						<br>
						<?php
					}
					?>
				</div>
			</div>
			<?php
			return;
		}

		_doing_it_wrong(
			__METHOD__,
			esc_html__( 'Settings are now generated via the set_settings_schema() method.', 'elasticpress' ),
			'ElasticPress 5.4.0'
		);
	}

	/**
	 * Set the `settings_schema` attribute
	 *
	 * @since 5.4.0
	 */
	public function set_settings_schema() {
		$search_algorithms = \ElasticPress\SearchAlgorithms::factory()->get_all();
		$options           = [];

		foreach ( $search_algorithms as $search_algorithm ) {
			$options[] = [
				'label' => $search_algorithm->get_name() . '<br><small>' . $search_algorithm->get_description() . '</small>',
				'value' => $search_algorithm->get_slug(),
			];
		}

		$this->settings_schema[] = [
			'default' => '3.5',
			'key'     => 'search_algorithm_version',
			'label'   => __( 'Version', 'elasticpress' ),
			'options' => $options,
			'type'    => 'radio',
		];
	}

	/**
	 * Get the current search algorithm
	 *
	 * @param string $search_algorithm The search algorithm slug
	 * @return string
	 */
	public function get_search_algorithm_version( $search_algorithm ) {
		$settings = $this->get_settings();

		return $settings['search_algorithm_version'] ?? $search_algorithm;
	}

	/**
	 * Maybe apply the default search algorithm version
	 *
	 * Applying it to the feature options (normal and draft), avoids getting an
	 * unselected search algorithm version.
	 *
	 * @since 5.4.0
	 * @param array $settings The settings to be sanitized
	 * @return array The sanitized settings
	 */
	public function maybe_apply_default_search_algorithm_version( $settings ) {
		if ( empty( $settings['search_algorithm'] ) ) {
			return $settings;
		}

		$available_search_algorithms = array_keys( \ElasticPress\SearchAlgorithms::factory()->get_all() );
		if ( ! in_array( $settings['search_algorithm']['search_algorithm_version'], $available_search_algorithms, true ) ) {
			$settings['search_algorithm']['search_algorithm_version'] = $this->default_settings['search_algorithm_version'];
		}

		return $settings;
	}

	/**
	 * Tell user whether requirements for feature are met or not.
	 *
	 * @return \ElasticPress\FeatureRequirementsStatus Status object
	 * @since 5.4.0
	 */
	public function requirements_status() {
		return new \ElasticPress\FeatureRequirementsStatus(
			1,
			esc_html__( 'Changes in this feature will be reflected only on the next page reload or expiration of any front-end caches.', 'elasticpress' ),
			$this
		);
	}

	/**
	 * Fixes the search algorithm version if it is not available.
	 *
	 * Some other features may provide their own search algorithm versions,
	 * but when they are deactivated, we need to revert to the default version.
	 *
	 * @since 5.4.0
	 * @param array                 $new_settings The settings to be saved
	 * @param \ElasticPress\Feature $feature      The feature object
	 * @return array The new settings
	 */
	public function fix_search_algorithm_version( $new_settings, $feature ) {
		$available_search_algorithms = array_keys( \ElasticPress\SearchAlgorithms::factory()->get_all() );

		if ( ! in_array( $new_settings['search_algorithm']['search_algorithm_version'], $available_search_algorithms, true ) ) {
			$new_settings['search_algorithm']['search_algorithm_version'] = $this->default_settings['search_algorithm_version'];
		}

		return $new_settings;
	}

	/**
	 * Sanitize the search algorithm version
	 *
	 * @since 5.4.0
	 * @param array $settings The settings to be sanitized
	 * @return array The sanitized settings
	 */
	public function sanitize_settings_callback( $settings ) {
		$available_search_algorithms = array_keys( \ElasticPress\SearchAlgorithms::factory()->get_all() );

		if ( ! in_array( $settings['search_algorithm_version'], $available_search_algorithms, true ) ) {
			$settings['search_algorithm_version'] = $this->default_settings['search_algorithm_version'];
		}

		return $settings;
	}
}
