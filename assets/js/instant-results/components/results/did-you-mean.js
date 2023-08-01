/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { WPElement } from '@wordpress/element';

import { showSuggestions, suggestionsBehavior } from '../../config';

/**
 * Did you mean component.
 *
 * @param {object} props Components props.
 * @returns {WPElement} Component element.
 */
export default (props) => {
	const { suggestedTerms, searchFor, totalResults } = props;

	const onClick = (term) => {
		searchFor(term);
	};

	// Get other terms by excluding the first term from suggestedTerms
	const otherTerms = suggestedTerms.slice(1);

	return (
		<>
			{showSuggestions && suggestedTerms && suggestedTerms?.[0]?.text && (
				<div className="ep-search-suggestion">
					<p>
						{__('Did you mean: ', 'elasticpress')}
						{/* eslint-disable-next-line jsx-a11y/anchor-is-valid */}
						<a onClick={() => onClick(suggestedTerms?.[0]?.text)} href="#">
							{suggestedTerms?.[0]?.text}
						</a>
					</p>
				</div>
			)}

			{showSuggestions &&
				suggestionsBehavior === 'list' &&
				totalResults === 0 &&
				otherTerms.length > 0 && (
					<div>
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
					</div>
				)}
		</>
	);
};
