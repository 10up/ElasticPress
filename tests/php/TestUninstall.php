<?php
/**
 * Test the uninstall class/process
 *
 * @since 4.7.0
 * @package elasticpress
 */

namespace ElasticPressTest;

/**
 * TestUninstall test class
 */
class TestUninstall extends BaseTestCase {
	/**
	 * Holds the EP_Uninstaller class instance.
	 *
	 * @var EP_Uninstaller
	 */
	protected $uninstaller;

	/**
	 * Setup each test.
	 */
	public function set_up() {
		require_once __DIR__ . '/../../uninstall.php';

		$this->uninstaller = new \EP_Uninstaller();

		parent::set_up();
	}

	/**
	 * Test the `delete_transients_by_option_name` method
	 *
	 * @group uninstall
	 */
	public function test_delete_transients_by_option_name() {
		set_transient( 'ep_index_settings_test', 'test' );
		set_transient( 'ep_index_settings_test_2', 'test' );
		set_transient( 'ep_related_posts_test', 'test' );
		set_transient( 'ep_related_posts_test_2', 'test' );

		$method = $this->get_protected_method( 'delete_transients_by_option_name' );
		$method->invoke( $this->uninstaller );

		$this->assertFalse( get_transient( 'ep_index_settings_test' ) );
		$this->assertFalse( get_transient( 'ep_index_settings_test_2' ) );
		$this->assertFalse( get_transient( 'ep_related_posts_test' ) );
		$this->assertFalse( get_transient( 'ep_related_posts_test_2' ) );
	}

	/**
	 * Test the `clean_site_meta` method on a single site
	 *
	 * @group uninstall
	 * @group skip-on-multi-site
	 */
	public function test_clean_site_meta_on_single_site() {
		$method = $this->get_protected_method( 'clean_site_meta' );
		$method->invoke( $this->uninstaller );
		$this->expectNotToPerformAssertions();
	}

	/**
	 * Test the `clean_site_meta` method on a multisite
	 *
	 * @group uninstall
	 * @group skip-on-single-site
	 */
	public function test_clean_site_meta_on_multi_site() {
		$blog_not_indexable = $this->factory->blog->create();
		update_site_meta( $blog_not_indexable, 'ep_indexable', 'no' );

		$blog_indexable = $this->factory->blog->create();
		update_site_meta( $blog_indexable, 'ep_indexable', 'yes' );

		$blog_indexable_2 = $this->factory->blog->create();

		$method = $this->get_protected_method( 'clean_site_meta' );
		$method->invoke( $this->uninstaller );

		$this->assertSame( '', get_site_meta( $blog_not_indexable, 'ep_indexable', true ) );
		$this->assertSame( '', get_site_meta( $blog_indexable, 'ep_indexable', true ) );
		$this->assertSame( '', get_site_meta( $blog_indexable_2, 'ep_indexable', true ) );
	}

	/**
	 * Return a protected method made public.
	 *
	 * This should NOT be copied to any other class.
	 *
	 * @param string $method_name The method name
	 * @return \ReflectionMethod
	 */
	protected function get_protected_method( string $method_name ) : \ReflectionMethod {
		$reflection = new \ReflectionClass( '\EP_Uninstaller' );
		$method     = $reflection->getMethod( $method_name );
		$method->setAccessible( true );

		return $method;
	}
}
