/**
 * WordPress dependencies.
 */
import { __, sprintf } from '@wordpress/i18n';
import { WPElement } from '@wordpress/element';

/**
 * Star rating component.
 *
 * @param {Option} props Component props.
 * @param {string} props.rating Rating.
 * @returns {WPElement} Component element.
 */
export default ({ rating }) => {
	const label = sprintf(
		/* translators: %1$f Rating. %2$d Max rating. */
		__('Rated %1$f out of %2$d', 'elasticpress'),
		rating,
		5,
	);

	return (
		<div className="woocommerce">
			<div aria-label={label} className="star-rating" role="img">
				<span style={{ width: `${(rating / 5) * 100}%` }}>{label}</span>
			</div>
		</div>
	);
};
