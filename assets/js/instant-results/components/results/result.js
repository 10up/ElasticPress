/**
 * WordPress dependencies.
 */
import { WPElement } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import { postTypeLabels } from '../../config';
import { formatDate } from '../../utilities';
import Result from '../common/result';

/**
 * Search result.
 *
 * @param {object} props Component props.
 * @param {object} props.hit Elasticsearch hit.
 * @param {object} props.searchTerm Search term from input search.
 * @param {object} props.highlightTag Selected highlight tag.
 * @returns {WPElement} Component element.
 */
export default ({ hit, searchTerm, highlightTag }) => {
	const {
		highlight: { post_content_plain: postContent = [] },
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

	/**
	 * Note: highlighting is redone here because the unified highlight type is not supported in ES5
	 */
	const regex = new RegExp(`\\b(${searchTerm})`, 'gi');
	let title;

	if (highlightTag === '' || highlightTag === undefined) {
		title = hit._source.post_title;
	} else {
		title = hit._source.post_title.replace(
			regex,
			(word) => `<${highlightTag}>${word}</${highlightTag}>`,
		);
	}

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
