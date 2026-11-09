<?php
/**
 * Requires a method-level group on every PHPUnit test and kebab-case group names.
 *
 * A test counts as grouped when its own docblock has a `@group` tag or the
 * method has a PHPUnit `#[Group( '...' )]` attribute. Class-level groups do
 * not count.
 *
 * @since 5.3.6
 * @package elasticpress
 */

namespace ElasticPressTests\Sniffs\Tests;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHPCSUtils\Utils\FunctionDeclarations;
use PHPCSUtils\Utils\Scopes;
use PHPCSUtils\Utils\TextStrings;

/**
 * Group sniff class.
 */
class GroupSniff implements Sniff {

	/**
	 * Valid group names: lowercase words and digits separated by single hyphens.
	 */
	const KEBAB_CASE = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

	/**
	 * Returns the tokens this sniff listens for.
	 *
	 * @return array
	 */
	public function register() {
		return [ T_FUNCTION, T_DOC_COMMENT_TAG ];
	}

	/**
	 * Dispatches to the method or the docblock tag check.
	 *
	 * @param File $phpcs_file The file being scanned.
	 * @param int  $stack_ptr  The position of the current token.
	 * @return void
	 */
	public function process( File $phpcs_file, $stack_ptr ) {
		$tokens = $phpcs_file->getTokens();

		if ( T_DOC_COMMENT_TAG === $tokens[ $stack_ptr ]['code'] ) {
			$this->process_group_tag( $phpcs_file, $stack_ptr );
			return;
		}

		$this->process_method( $phpcs_file, $stack_ptr );
	}

	/**
	 * Checks the name given to a `@group` docblock tag.
	 *
	 * @param File $phpcs_file The file being scanned.
	 * @param int  $stack_ptr  The position of the tag token.
	 * @return void
	 */
	protected function process_group_tag( File $phpcs_file, $stack_ptr ) {
		$tokens = $phpcs_file->getTokens();
		if ( '@group' !== $tokens[ $stack_ptr ]['content'] ) {
			return;
		}

		$name = $this->get_tag_value( $phpcs_file, $stack_ptr );
		if ( '' === $name ) {
			$phpcs_file->addError( 'The @group tag must name a group.', $stack_ptr, 'Empty' );
			return;
		}

		$this->check_group_name( $phpcs_file, $stack_ptr, $name );
	}

	/**
	 * Checks that a public test method belongs to at least one group.
	 *
	 * @param File $phpcs_file The file being scanned.
	 * @param int  $stack_ptr  The position of the function token.
	 * @return void
	 */
	protected function process_method( File $phpcs_file, $stack_ptr ) {
		if ( ! Scopes::isOOMethod( $phpcs_file, $stack_ptr ) ) {
			return;
		}

		$properties = FunctionDeclarations::getProperties( $phpcs_file, $stack_ptr );
		if ( 'public' !== $properties['scope'] || $properties['is_abstract'] ) {
			return;
		}

		$tokens     = $phpcs_file->getTokens();
		$docblock   = false;
		$attributes = [];

		// Walk back over modifiers and attributes to the method's docblock.
		$skip = [ T_WHITESPACE, T_PUBLIC, T_STATIC, T_FINAL ];
		for ( $i = $stack_ptr - 1; $i >= 0; $i-- ) {
			if ( in_array( $tokens[ $i ]['code'], $skip, true ) ) {
				continue;
			}
			if ( T_ATTRIBUTE_END === $tokens[ $i ]['code'] && isset( $tokens[ $i ]['attribute_opener'] ) ) {
				$attributes[] = [ $tokens[ $i ]['attribute_opener'], $i ];
				$i            = $tokens[ $i ]['attribute_opener'];
				continue;
			}
			if ( T_DOC_COMMENT_CLOSE_TAG === $tokens[ $i ]['code'] ) {
				$docblock = $tokens[ $i ]['comment_opener'];
			}
			break;
		}

		$tags = $docblock ? $this->get_tags( $phpcs_file, $docblock ) : [];

		$name    = FunctionDeclarations::getName( $phpcs_file, $stack_ptr );
		$is_test = 0 === stripos( (string) $name, 'test' ) || isset( $tags['@test'] );
		if ( ! $is_test ) {
			return;
		}

		if ( ! empty( $tags['@group'] ) ) {
			return;
		}

		foreach ( $attributes as $attribute ) {
			if ( $this->has_group_attribute( $phpcs_file, $attribute[0], $attribute[1] ) ) {
				return;
			}
		}

		$phpcs_file->addError(
			'Test method "%s" must belong to at least one group: add a method-level @group tag.',
			$stack_ptr,
			'Missing',
			[ $name ]
		);
	}

	/**
	 * Collects the tags of a docblock that have a value, keyed by tag.
	 *
	 * @param File $phpcs_file The file being scanned.
	 * @param int  $opener     The position of the docblock opener.
	 * @return array Tag => list of values.
	 */
	protected function get_tags( File $phpcs_file, $opener ) {
		$tokens = $phpcs_file->getTokens();
		$tags   = [];
		foreach ( $tokens[ $opener ]['comment_tags'] as $tag_ptr ) {
			$tag = $tokens[ $tag_ptr ]['content'];
			if ( ! isset( $tags[ $tag ] ) ) {
				$tags[ $tag ] = [];
			}
			$value = $this->get_tag_value( $phpcs_file, $tag_ptr );
			if ( '' !== $value ) {
				$tags[ $tag ][] = $value;
			}
		}
		return $tags;
	}

	/**
	 * Returns the text that follows a docblock tag on the same line.
	 *
	 * @param File $phpcs_file The file being scanned.
	 * @param int  $tag_ptr    The position of the tag token.
	 * @return string
	 */
	protected function get_tag_value( File $phpcs_file, $tag_ptr ) {
		$tokens = $phpcs_file->getTokens();
		$next   = $tag_ptr + 1;
		while ( isset( $tokens[ $next ] ) && $tokens[ $next ]['line'] === $tokens[ $tag_ptr ]['line'] ) {
			if ( T_DOC_COMMENT_STRING === $tokens[ $next ]['code'] ) {
				return trim( $tokens[ $next ]['content'] );
			}
			if ( T_DOC_COMMENT_WHITESPACE !== $tokens[ $next ]['code'] ) {
				break;
			}
			++$next;
		}
		return '';
	}

	/**
	 * Whether an attribute block contains PHPUnit's `Group` attribute.
	 *
	 * The group name passed to the attribute is checked as well.
	 *
	 * @param File $phpcs_file The file being scanned.
	 * @param int  $opener     The position of the attribute opener.
	 * @param int  $closer     The position of the attribute closer.
	 * @return bool
	 */
	protected function has_group_attribute( File $phpcs_file, $opener, $closer ) {
		$tokens = $phpcs_file->getTokens();
		$found  = false;
		for ( $i = $opener + 1; $i < $closer; $i++ ) {
			$content = $tokens[ $i ]['content'];
			if ( 'Group' !== $content && '\\Group' !== substr( $content, -6 ) ) {
				continue;
			}
			$paren = $phpcs_file->findNext( T_WHITESPACE, $i + 1, $closer, true );
			if ( false === $paren || T_OPEN_PARENTHESIS !== $tokens[ $paren ]['code'] ) {
				continue;
			}
			$found = true;
			$arg   = $phpcs_file->findNext( T_WHITESPACE, $paren + 1, $closer, true );
			if ( false !== $arg && T_CONSTANT_ENCAPSED_STRING === $tokens[ $arg ]['code'] ) {
				$this->check_group_name( $phpcs_file, $arg, TextStrings::stripQuotes( $tokens[ $arg ]['content'] ) );
			}
		}
		return $found;
	}

	/**
	 * Reports a group name that is not kebab-case.
	 *
	 * @param File   $phpcs_file The file being scanned.
	 * @param int    $stack_ptr  The position to report on.
	 * @param string $name       The group name.
	 * @return void
	 */
	protected function check_group_name( File $phpcs_file, $stack_ptr, $name ) {
		if ( preg_match( self::KEBAB_CASE, $name ) ) {
			return;
		}

		$phpcs_file->addError(
			'Group names must be kebab-case (lowercase words separated by hyphens). Found: "%s".',
			$stack_ptr,
			'NotKebabCase',
			[ $name ]
		);
	}
}
