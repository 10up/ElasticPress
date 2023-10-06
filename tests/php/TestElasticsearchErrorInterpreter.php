<?php
/**
 * Test the ElasticsearchErrorInterpreter class methods
 *
 * @since 5.0.0
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress\ElasticsearchErrorInterpreter;
use ElasticPress\Utils;

/**
 * TestElasticsearchErrorInterpreter test class
 */
class TestElasticsearchErrorInterpreter extends BaseTestCase {
	/**
	 * Test the `maybe_suggest_solution_for_es` method
	 *
	 * @group elasticsearch-error-interpreter
	 */
	public function test_maybe_suggest_solution_for_es() {
		$error_interpreter = new ElasticsearchErrorInterpreter();

		$error     = 'Not set';
		$solution  = 'We did not recognize this error. Please consider opening a <a href="https://github.com/10up/ElasticPress/issues/new/choose">GitHub Issue</a> so we can add it to our list of supported errors.';
		$suggested = $error_interpreter->maybe_suggest_solution_for_es( $error );

		$this->assertSame( $error, $suggested['error'] );
		$this->assertSame( $solution, $suggested['solution'] );
	}

	/**
	 * Test the `maybe_suggest_solution_for_es` method when an index is missing
	 *
	 * @group elasticsearch-error-interpreter
	 */
	public function test_maybe_suggest_solution_for_es_no_index() {
		$error_interpreter = new ElasticsearchErrorInterpreter();
		$sync_url          = Utils\get_sync_url();

		$error     = 'no such index [elasticpresstest-post-1]';
		$solution  = 'It seems the <code>elasticpresstest-post-1</code> index is missing. <a href="' . $sync_url . '">Delete all data and sync</a> to fix the issue.';
		$suggested = $error_interpreter->maybe_suggest_solution_for_es( $error );

		$this->assertSame( 'no such index [???]', $suggested['error'] );
		$this->assertSame( $solution, $suggested['solution'] );
	}

	/**
	 * Test the `maybe_suggest_solution_for_es` method when sorting on a wrong field
	 *
	 * @group elasticsearch-error-interpreter
	 */
	public function test_maybe_suggest_solution_for_es_order() {
		$error_interpreter = new ElasticsearchErrorInterpreter();
		$sync_url          = Utils\get_sync_url();

		$error     = 'No mapping found for [fieldname] in order to sort on';
		$solution  = 'The field <code>fieldname</code> was not found. Make sure it is added to the list of indexed fields and run <a href="' . $sync_url . '">a new sync</a> to fix the issue.';
		$suggested = $error_interpreter->maybe_suggest_solution_for_es( $error );

		$this->assertSame( 'No mapping found for [???] in order to sort on', $suggested['error'] );
		$this->assertSame( $solution, $suggested['solution'] );
	}

	/**
	 * Test the `maybe_suggest_solution_for_es` method when using the wrong mapping (fielddata error)
	 *
	 * @group elasticsearch-error-interpreter
	 */
	public function test_maybe_suggest_solution_for_es_wrong_mapping_fielddata() {
		$error_interpreter = new ElasticsearchErrorInterpreter();
		$sync_url          = Utils\get_sync_url();

		$error     = 'Fielddata is disabled on text fields by default. Set fielddata=true on [fieldname]';
		$solution  = 'It seems you saved a post without doing a full sync first because <code>fieldname</code> is missing the correct mapping type. <a href="' . $sync_url . '">Delete all data and sync</a> to fix the issue.';
		$suggested = $error_interpreter->maybe_suggest_solution_for_es( $error );

		$this->assertSame( 'Fielddata is disabled on text fields by default. Set fielddata=true on [???]', $suggested['error'] );
		$this->assertSame( $solution, $suggested['solution'] );
	}

	/**
	 * Test the `maybe_suggest_solution_for_es` method when using the wrong mapping (numeric error)
	 *
	 * @group elasticsearch-error-interpreter
	 */
	public function test_maybe_suggest_solution_for_es_wrong_mapping_numeric() {
		$error_interpreter = new ElasticsearchErrorInterpreter();
		$sync_url          = Utils\get_sync_url();

		$error     = 'field [fieldname] is of type [type], but only numeric types are supported.';
		$solution  = 'It seems you saved a post without doing a full sync first because <code>fieldname</code> is missing the correct mapping type. <a href="' . $sync_url . '">Delete all data and sync</a> to fix the issue.';
		$suggested = $error_interpreter->maybe_suggest_solution_for_es( $error );

		$this->assertSame( 'field [???] is of type [???], but only numeric types are supported.', $suggested['error'] );
		$this->assertSame( $solution, $suggested['solution'] );
	}

	/**
	 * Test the `maybe_suggest_solution_for_es` method when using the wrong mapping (inverted error)
	 *
	 * @group elasticsearch-error-interpreter
	 */
	public function test_maybe_suggest_solution_for_es_wrong_mapping_inverted() {
		$error_interpreter = new ElasticsearchErrorInterpreter();
		$sync_url          = Utils\get_sync_url();

		$error     = 'Alternatively, set fielddata=true on [fieldname] in order to load field data by uninverting the inverted index.';
		$solution  = 'It seems you saved a post without doing a full sync first because <code>fieldname</code> is missing the correct mapping type. <a href="' . $sync_url . '">Delete all data and sync</a> to fix the issue.';
		$suggested = $error_interpreter->maybe_suggest_solution_for_es( $error );

		$this->assertSame( 'Alternatively, set fielddata=true on [???] in order to load field data by uninverting the inverted index.', $suggested['error'] );
		$this->assertSame( $solution, $suggested['solution'] );
	}

	/**
	 * Test the `maybe_suggest_solution_for_es` method when the fields limit is reached
	 *
	 * @group elasticsearch-error-interpreter
	 */
	public function test_maybe_suggest_solution_for_es_limit_fields() {
		$error_interpreter = new ElasticsearchErrorInterpreter();
		$sync_url          = Utils\get_sync_url();

		$error     = 'Limit of total fields [1000] in index [elasticpresstest-post-1] has been exceeded';
		$solution  = 'Your website content has more public custom fields than Elasticsearch is able to store. Check our articles about <a href="https://elasticpress.zendesk.com/hc/en-us/articles/360051401212-I-get-the-error-Limit-of-total-fields-in-index-has-been-exceeded-">Elasticsearch field limitations</a> and <a href="https://elasticpress.zendesk.com/hc/en-us/articles/360052019111">how to index just the custom fields you need</a> and sync again.';
		$suggested = $error_interpreter->maybe_suggest_solution_for_es( $error );

		$this->assertSame( 'Limit of total fields [???] in index [???] has been exceeded', $suggested['error'] );
		$this->assertSame( $solution, $suggested['solution'] );
	}
}
