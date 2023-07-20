/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { WPElement } from '@wordpress/element';

import { useApiSearch } from '../../../api-search';

/**
 * Did you mean component.
 *
 * @returns {WPElement} Component element.
 */
export default () => {
	const { suggestedTerms, searchFor, totalResults } = useApiSearch();

	const { isDidYouMean, searchBehavior } = window.epInstantResults;

	const onClick = (term) => {
		searchFor(term);
	};

	// Get other terms by excluding the first term from suggestedTerms
	const otherTerms = suggestedTerms.slice(1);

	return (
		<>
			{isDidYouMean && suggestedTerms && suggestedTerms?.[0]?.text && (
				<div className="ep-spell-suggestion">
					{__('Did you Mean: ', 'elasticpress')}
					{/* eslint-disable-next-line jsx-a11y/anchor-is-valid */}
					<a onClick={() => onClick(suggestedTerms?.[0]?.text)} href="#">
						{suggestedTerms?.[0]?.text}
					</a>
				</div>
			)}

			{searchBehavior === 'list' && totalResults === 0 && otherTerms.length > 0 && (
				<>
					<p>{__('Other suggestions', 'elasticpress')}</p>
					<ul>
						{otherTerms.map((term) => (
							<li key={term.text}>
								{/* eslint-disable-next-line jsx-a11y/anchor-is-valid */}
								<a href="#" onClick={() => onClick(term.text)}>
									{term.text}
								</a>
							</li>
						))}
					</ul>
				</>
			)}
		</>
	);
};
