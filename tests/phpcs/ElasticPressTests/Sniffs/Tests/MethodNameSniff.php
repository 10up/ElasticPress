<?php
/**
 * Requires snake_case method names in the PHPUnit test suite.
 *
 * WPCS' ValidFunctionName sniff skips every method of a child class, which
 * includes all test classes, so test methods, data providers and helpers are
 * checked here instead.
 *
 * @since 5.3.6
 * @package elasticpress
 */

namespace ElasticPressTests\Sniffs\Tests;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHPCSUtils\Utils\FunctionDeclarations;
use PHPCSUtils\Utils\Scopes;
use WordPressCS\WordPress\Helpers\SnakeCaseHelper;

/**
 * MethodName sniff class.
 */
class MethodNameSniff implements Sniff {

	/**
	 * Methods that must keep their camelCase name because they override
	 * PHPUnit or WordPress test framework methods.
	 *
	 * @var string[]
	 */
	public $allowed_names = [
		'setUp',
		'tearDown',
		'setUpBeforeClass',
		'tearDownAfterClass',
		'wpSetUpBeforeClass',
		'wpTearDownAfterClass',
		'assertPreConditions',
		'assertPostConditions',
		'onNotSuccessfulTest',
	];

	/**
	 * Returns the tokens this sniff listens for.
	 *
	 * @return array
	 */
	public function register() {
		return [ T_FUNCTION ];
	}

	/**
	 * Checks the name of a method.
	 *
	 * @param File $phpcs_file The file being scanned.
	 * @param int  $stack_ptr  The position of the current token.
	 * @return void
	 */
	public function process( File $phpcs_file, $stack_ptr ) {
		if ( ! Scopes::isOOMethod( $phpcs_file, $stack_ptr ) ) {
			return;
		}

		$name = FunctionDeclarations::getName( $phpcs_file, $stack_ptr );
		if ( empty( $name ) || FunctionDeclarations::isMagicMethodName( $name ) ) {
			return;
		}

		if ( in_array( $name, (array) $this->allowed_names, true ) ) {
			return;
		}

		if ( preg_match( '/^[a-z0-9_]+$/', $name ) ) {
			return;
		}

		$phpcs_file->addError(
			'Method names in the test suite must be snake_case. Found: "%s"; expected something like "%s".',
			$stack_ptr,
			'NotSnakeCase',
			[ $name, SnakeCaseHelper::get_suggestion( $name ) ]
		);
	}
}
