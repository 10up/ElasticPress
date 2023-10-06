/**
 * Internal depenencies.
 */
import { useEffect, useRef, WPElement } from '@wordpress/element';
import { _n, sprintf, __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { useApiSearch } from '../../../api-search';
import Pagination from '../results/pagination';
import Result from '../results/result';
import Sort from '../tools/sort';
import DidYouMean from '../results/did-you-mean';

/**
 * Search results component.
 *
 * @returns {WPElement} Component element.
 */
export default () => {
	const {
		args: { offset, per_page, highlight },
		nextPage,
		previousPage,
		searchResults,
		searchTerm,
		totalResults,
		searchFor,
		suggestedTerms,
		isFirstSearch,
	} = useApiSearch();

	const headingRef = useRef();

	/**
	 * Handle clicking next.
	 */
	const onNext = () => {
		nextPage();
	};

	/**
	 * Handle clicking previous.
	 */
	const onPrevious = () => {
		previousPage();
	};

	/**
	 * Effects.
	 */
	useEffect(() => {
		headingRef.current.scrollIntoView({ behavior: 'smooth' });
	}, [offset]);

	/**
	 * Display results text.
	 *
	 * @returns {string} Results text.
	 */
	const displayResults = () => {
		if (searchTerm) {
			return sprintf(
				/* translators: %1$d: results count. %2$s: Search term. */
				_n(
					'%1$d result for “%2$s“',
					'%1$d results for “%2$s“',
					totalResults,
					'elasticpress',
				),
				totalResults,
				searchTerm,
			);
		}
		return sprintf(
			/* translators: %d: results count. */
			_n('%d result', '%d results', totalResults, 'elasticpress'),
			totalResults,
		);
	};

	return (
		<div className="ep-search-results">
			<header className="ep-search-results__header">
				<h1 className="ep-search-results__title" ref={headingRef} role="status">
					{!isFirstSearch
						? displayResults()
						: sprintf(__('Loading results', 'elasticpress'))}
				</h1>

				<Sort />
			</header>
			<DidYouMean
				searchFor={searchFor}
				suggestedTerms={suggestedTerms}
				totalResults={totalResults}
			/>
			{searchResults.map((hit) => (
				<Result key={hit._id} hit={hit} searchTerm={searchTerm} highlightTag={highlight} />
			))}

			<Pagination
				offset={offset}
				onNext={onNext}
				onPrevious={onPrevious}
				perPage={per_page}
				total={totalResults}
			/>
		</div>
	);
};
