/**
 * WordPress dependencies.
 */
import { __, sprintf } from '@wordpress/i18n';
import { WPElement, createInterpolateElement } from '@wordpress/element';

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
						{createInterpolateElement(
							sprintf(
								/* translators: Search term */
								__('Did you mean: <a>%s</a>', 'elasticpress'),
								suggestedTerms?.[0]?.text,
							),
							{
								a: (
									// eslint-disable-next-line jsx-a11y/control-has-associated-label, jsx-a11y/anchor-has-content, jsx-a11y/anchor-is-valid
									<a
										href="#"
										onClick={() => onClick(suggestedTerms?.[0]?.text)}
									/>
								),
							},
						)}
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
