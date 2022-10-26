/**
 * WordPress dependencies.
 */
import { WPElement } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import { postTypeLabels } from '../../config';
import { formatDate } from '../../functions';
import Result from '../common/result';

/**
 * Search result.
 *
 * @param {object} props Component props.
 * @param {object} props.hit Elasticsearch hit.
 * @returns {WPElement} Component element.
 */
export default ({ hit }) => {
	const {
		highlight: { post_title: title, post_content_plain: postContent = [] },
		_source: {
			meta: { _wc_average_rating: [{ value: averageRating } = {}] = [] },
			permalink: url,
			post_date: postDate,
			post_id: id,
			post_type: postType,
			price_html: priceHtml,
			thumbnail,
		},
	} = hit;

	const date = postType === 'post' ? formatDate(postDate) : null;
	const excerpt = postContent.join('…');
	const type = postTypeLabels[postType]?.singular;

	return (
		<Result
			{...{
				averageRating,
				date,
				hit,
				excerpt,
				id,
				priceHtml,
				thumbnail,
				title,
				type,
				url,
			}}
		/>
	);
};
