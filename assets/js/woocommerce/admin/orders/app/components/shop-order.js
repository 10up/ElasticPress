/**
 * WordPress dependencies.
 */
import { dateI18n } from '@wordpress/date';
import { WPElement } from '@wordpress/element';
import { _n, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { dateFormat, statusLabels, timeFormat } from '../../config';

/**
 * Shop order component.
 *
 * @param {object} props Component props.
 * @param {string} props.emailAddress Billing email address.
 * @param {string} props.firstName Billing first name.
 * @param {string} props.itemCount Order item count.
 * @param {string} props.lastName Billing last name.
 * @param {string} props.orderNumber Order number.
 * @param {string} props.orderDate Order date in GMT.
 * @param {string} props.orderStatus Order status.
 * @returns {WPElement} Rendered component.
 */
export default ({
	emailAddress,
	firstName,
	itemCount,
	lastName,
	orderDate,
	orderNumber,
	orderStatus,
}) => {
	const formattedDate = dateI18n(dateFormat, orderDate);
	const formattedDateTime = dateI18n('c', orderDate);
	const formattedTime = dateI18n(timeFormat, orderDate);
	const statusClass = `status-${orderStatus.substring(3)}`;
	const statusLabel = statusLabels[orderStatus];

	return (
		<article className="ep-shop-order">
			<p>
				<strong>
					{`#${orderNumber}`} {firstName} {lastName}
				</strong>
				{emailAddress ? <small>({emailAddress})</small> : ''}
			</p>
			<footer>
				<time dateTime={formattedDateTime}>
					<small>
						{sprintf(
							// translators: %1$d: Order item count. %2$s: Order time.
							_n('%1$d item @ %2$s', '%1$d items @ %2$s', itemCount, 'elasticpress'),
							itemCount,
							formattedTime,
						)}
						<br />
						{formattedDate}
					</small>
				</time>
				{statusLabel && (
					<mark className={`order-status ${statusClass} tips`}>
						<span>{statusLabel}</span>
					</mark>
				)}
			</footer>
		</article>
	);
};
