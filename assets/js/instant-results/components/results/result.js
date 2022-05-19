/**
 * WordPress dependencies.
 */
import { WPElement } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import { postTypeLabels, isWooCommerce } from '../../config';
import { formatDate } from '../../functions';
import StarRating from '../common/star-rating';
import Image from '../common/image';

/**
 * Search result.
 *
 * @param {object} props     Component props.
 * @param {object} props.hit Elasticsearch hit.
 * @returns {WPElement} Component element.
 */
export default ({ hit }) => {
	const {
		highlight: { post_title: resultTitle, post_content_plain: resultContent = [] },
		_source: {
			meta: { _wc_average_rating: [{ value: resultRating = 0 } = {}] = [] },
			post_date: resultDate,
			permalink: resultPermalink,
			post_type: resultPostType,
			price_html: priceHtml,
			thumbnail: resultThumbnail = false,
		},
	} = hit;

	const postTypeLabel = postTypeLabels[resultPostType]?.singular;

	return (
		<article
			className={`ep-search-result ${resultThumbnail && 'ep-search-result--has-thumbnail'}`}
		>
			{resultThumbnail && (
				<a className="ep-search-result__thumbnail" href={resultPermalink}>
					<Image {...resultThumbnail} />
				</a>
			)}

			<header className="ep-search-result__header">
				{postTypeLabel && <span className="ep-search-result__type">{postTypeLabel}</span>}

				<h2 className="ep-search-result__title">
					{/* eslint-disable-next-line jsx-a11y/control-has-associated-label */}
					<a
						href={resultPermalink}
						/* eslint-disable-next-line react/no-danger */
						dangerouslySetInnerHTML={{ __html: resultTitle }}
					/>
				</h2>

				{isWooCommerce && priceHtml && (
					// eslint-disable-next-line react/no-danger
					<p className="price" dangerouslySetInnerHTML={{ __html: priceHtml }} />
				)}
			</header>

			{resultContent.length > 0 && (
				<p
					className="ep-search-result__description"
					/* eslint-disable-next-line react/no-danger */
					dangerouslySetInnerHTML={{ __html: resultContent.join('…') }}
				/>
			)}

			<footer className="ep-search-result__footer">
				{isWooCommerce && resultRating > 0 && <StarRating rating={resultRating} />}
				{resultPostType === 'post' && formatDate(resultDate)}
			</footer>
		</article>
	);
};
