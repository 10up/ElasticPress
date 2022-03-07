/**
 * Internal depenencies.
 */
import { useContext, useEffect, useRef, WPElement } from '@wordpress/element';
import { _n, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import Context from '../../context';
import Pagination from '../results/pagination';
import Result from '../results/result';
import Sort from '../tools/sort';

/**
 * Search results component.
 *
 * @returns {WPElement} Component element.
 */
export default () => {
	const {
		state: {
			args: { offset, per_page },
			searchResults,
			searchedTerm,
			totalResults,
		},
		dispatch,
	} = useContext(Context);

	const headingRef = useRef();

	/**
	 * Handle clicking next.
	 */
	const onNext = () => {
		dispatch({ type: 'NEXT_PAGE' });
	};

	/**
	 * Handle clicking previous.
	 */
	const onPrevious = () => {
		dispatch({ type: 'PREVIOUS_PAGE' });
	};

	/**
	 * Effects.
	 */
	useEffect(() => {
		headingRef.current.scrollIntoView({ behavior: 'smooth' });
	}, [offset]);

	return (
		<div className="ep-search-results">
			<header className="ep-search-results__header">
				<h1 className="ep-search-results__title" ref={headingRef} role="status">
					{searchedTerm
						? sprintf(
								/* translators: %1$d: results count. %2$s: Search term. */
								_n(
									'%1$d result for “%2$s“',
									'%1$d results for “%2$s“',
									totalResults,
									'elasticpress',
								),
								totalResults,
								searchedTerm,
						  )
						: sprintf(
								/* translators: %d: results count. */
								_n('%d result', '%d results', totalResults, 'elasticpress'),
								totalResults,
						  )}
				</h1>

				<Sort />
			</header>

			{searchResults.map((hit) => (
				<Result key={hit._id} hit={hit} />
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
