/**
 * WordPress dependencies.
 */
import { dateI18n } from '@wordpress/date';
import { WPElement } from '@wordpress/element';
import { _n, sprintf } from '@wordpress/i18n';

/**
 * Suggestion component.
 *
 * @param {object} props Component props.
 * @param {string} props.dateFormat Date format string.
 * @param {string} props.hit Elasticsearch hit.
 * @param {object} props.statusLabels Post status labels.
 * @param {string} props.timeFormat Time format string.
 * @returns {WPElement} Rendered component.
 */
export default ({ dateFormat, hit, statusLabels, timeFormat }) => {
	/**
	 * Get data from Elasticsearch hit.
	 */
	const {
		meta: {
			_billing_email: [{ value: emailAddress } = {}] = [],
			_billing_first_name: [{ value: firstName } = {}] = [],
			_billing_last_name: [{ value: lastName } = {}] = [],
			_items: [{ value: items } = {}] = [],
		},
		post_date_gmt: postDate,
		post_id: postId,
		post_status: postStatus,
	} = hit._source;

	/**
	 * Format values.
	 */
	const dateTime = `${postDate.split(' ').join('T')}+00:00`;
	const formattedDate = dateI18n(dateFormat, dateTime);
	const formattedTime = dateI18n(timeFormat, dateTime);
	const itemCount = items ? items.split('|').length : 0;
	const statusClass = `status-${postStatus.substring(3)}`;
	const statusLabel = statusLabels[postStatus];

	/**
	 * Render.
	 */
	return (
		<div className="ep-suggestion">
			<div className="ep-suggestion__header">
				<div className="ep-suggestion__title">
					{`#${postId}`} {firstName} {lastName}
				</div>
				{emailAddress}
			</div>
			<div className="ep-suggestion__footer">
				<div className="ep-suggestion__details">
					{sprintf(
						// translators: %1$d: Order item count. %2$s: Order time.
						_n('%1$d item @ %2$s', '%1$d items @ %2$s', itemCount, 'elasticpress'),
						itemCount,
						formattedTime,
					)}
					<br />
					{formattedDate}
				</div>
				{statusLabel && (
					<div className={`order-status ${statusClass} tips`}>
						<span>{statusLabel}</span>
					</div>
				)}
			</div>
		</div>
	);
};
