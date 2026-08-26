/**
 * WordPress dependencies.
 */
import { Component, FunctionComponent, WPElement } from '@wordpress/element';
import { applyFilters } from '@wordpress/hooks';

/**
 * Internal dependencies.
 */
import Result from './result';

/**
 * List of search results.
 *
 * @param {object} props Component props.
 * @param {object} props.aggregations Elasticsearch aggregations for the current query.
 * @param {string} props.highlightTag Selected highlight tag.
 * @param {object[]} props.searchResults Elasticsearch hits.
 * @param {string} props.searchTerm Search term from input search.
 * @param {number} props.totalResults Total number of results across all pages.
 * @returns {WPElement} Component element.
 */
const ResultsList = (props) => {
	const { highlightTag, searchResults, searchTerm } = props;

	return (
		<>
			{searchResults.map((hit) => (
				<Result
					key={hit._id}
					hit={hit}
					searchTerm={searchTerm}
					highlightTag={highlightTag}
				/>
			))}
		</>
	);
};

/**
 * Filter the ResultsList component.
 *
 * @filter ep.InstantResults.ResultsList
 * @since 5.4.0
 *
 * @param {Component|FunctionComponent} ResultsList ResultsList component.
 * @returns {Component|FunctionComponent} ResultsList component.
 */
export default applyFilters('ep.InstantResults.ResultsList', ResultsList);
