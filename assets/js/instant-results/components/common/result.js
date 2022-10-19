/**
 * WordPress dependencies.
 */
import { React, WPElement } from '@wordpress/element';
import { applyFilters } from '@wordpress/hooks';

/**
 * Internal dependencies.
 */
import StarRating from './star-rating';
import Image from './image';

/**
 * Search result.
 *
 * @param {object} props Component props.
 * @param {number} props.averageRating Average rating.
 * @param {string} props.date Localized date.
 * @param {string} props.excerpt Highlighted excerpt.
 * @param {string} props.priceHtml Product price HTML.
 * @param {object} props.thumbnail Thumbnail image attributes.
 * @param {string} props.title Highlighted title.
 * @param {string} props.type Type label.
 * @param {string} props.url URL.
 * @returns {WPElement} Component element.
 */
const Result = ({ averageRating = 0, date, excerpt, priceHtml, thumbnail, title, type, url }) => {
	return (
		<article
			className={`ep-search-result ${thumbnail ? 'ep-search-result--has-thumbnail' : null}`}
		>
			{thumbnail && (
				<a className="ep-search-result__thumbnail" href={url}>
					<Image {...thumbnail} />
				</a>
			)}

			<header className="ep-search-result__header">
				{type ? <span className="ep-search-result__type">{type}</span> : null}

				<h2 className="ep-search-result__title">
					{/* eslint-disable-next-line jsx-a11y/control-has-associated-label */}
					<a
						href={url}
						/* eslint-disable-next-line react/no-danger */
						dangerouslySetInnerHTML={{ __html: title }}
					/>
				</h2>

				{priceHtml ? (
					<p
						className="price"
						/* eslint-disable-next-line react/no-danger */
						dangerouslySetInnerHTML={{
							__html: priceHtml,
						}}
					/>
				) : null}
			</header>

			{excerpt.length > 0 ? (
				<p
					className="ep-search-result__description"
					/* eslint-disable-next-line react/no-danger */
					dangerouslySetInnerHTML={{ __html: excerpt }}
				/>
			) : null}

			<footer className="ep-search-result__footer">
				{averageRating > 0 ? <StarRating rating={averageRating} /> : null}
				{date}
			</footer>
		</article>
	);
};

/**
 * Filter the Result component.
 *
 * @filter ep.InstantResults.Result
 * @since 4.4.0
 *
 * @param {React.Component|React.FunctionComponent} Result Result component.
 * @returns {React.Component|React.FunctionComponent} Result component.
 */
export default applyFilters('ep.InstantResults.Result', Result);
