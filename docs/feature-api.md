Since ElasticPress 3.0, to register a feature you will need to extend the Feature class. Here is a code example:

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Your feature class.
 */
class My_ElasticPress_Feature extends \ElasticPress\Feature {

	/**
	 * Initialize feature settings.
	 */
	public function __construct() {
		$this->slug = 'feature_slug';

		$this->title = esc_html__( 'Feature Name', 'plugin-text-domain' );

		$this->requires_install_reindex = true;
		$this->default_settings         = [
			'my_feature_setting' => '',
		];

		parent::__construct();
	}

	/**
	 * Output feature box summary.
	 */
	public function output_feature_box_summary() {
		?>
		<p><?php esc_html_e( 'Your feature short description.', 'plugin-text-domain' ); ?></p>
		<?php
	}

	/**
	 * Output feature box long
	 */
	public function output_feature_box_long() {
		?>
		<p><?php esc_html_e( 'Your feature long description.', 'plugin-text-domain' ); ?></p>
		<?php
	}

	/**
	 * Setup your feature functionality.
	 * Use this method to hook your feature functionality to ElasticPress or WordPress.
	 */
	public function setup() {
		add_filter( 'ep_post_sync_args', [ $this, 'method_example' ] );
	}

	/**
	 * Display field settings on the Dashboard.
	 */
	public function output_feature_box_settings() {
		$settings = $this->get_settings();

		if ( ! $settings ) {
			$settings = [];
		}

		$settings = wp_parse_args( $settings, $this->default_settings );

		?>
		<div class="field js-toggle-feature" data-feature="<?php echo esc_attr( $this->slug ); ?>">
			<div class="field-name status">
				<label for="feature_my_feature_setting">
					<?php esc_html_e( 'Your custom field', 'plugin-text-domain' ); ?>
				</label>
			</div>
			<div class="input-wrap">
				<input
					type="text"
					class="setting-field"
					id="feature_my_feature_setting"
					value="<?php echo empty( $settings['my_feature_setting'] ) ? '' : esc_attr( $settings['my_feature_setting'] ); ?>"
					data-field-name="my_feature_setting">
				<p class="field-description">
					<?php esc_html_e( 'Your custom field description.', 'plugin-text-domain' ); ?>
				</p>
			</div>
		</div>
		<?php
	}
}
```

The Feature class is an abstract class, which means it is **required** to implement some methods:
- `setup()`
- `output_feature_box_summary()`
- `output_feature_box_long()`

Optionally, you can implement some other methods, like `output_feature_box_settings()` to display additional setting fields, or `requirements_status()` to change the feature availability based on custom checks.

Don't forget to register your new feature:

```php
function load_my_elasticpress_feature() {
	if ( class_exists( '\ElasticPress\Features' ) ) {
		// Include your class file.
    require 'class-my-elasticpress-feature.php';
		// Register your feature in ElasticPress.
		\ElasticPress\Features::factory()->register_feature(
			new My_ElasticPress_Feature()
		);
	}
}
add_action( 'plugins_loaded', 'load_my_elasticpress_feature', 11 );
```

If you build an open source custom feature, let us know! We'd be happy to list the feature within ElasticPress documentation.