/**
 * WordPress dependencies.
 */
import { WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { useFeatureSettings } from '../provider';

/**
 * Styles.
 */
import '../style.css';

/**
 * Feature tab component.
 *
 * @param {object} props Component props.
 * @param {WPElement} props.feature Feature slug.
 * @returns {WPElement} Feature tab component.
 */
export default ({ feature }) => {
	const { getFeature, featuresRequiringSync } = useFeatureSettings();

	const { shortTitle, isAvailable } = getFeature(feature);

	const availabilityStatus = isAvailable ? '' : __('Unavailable', 'elasticpress');

	const status = featuresRequiringSync.includes(feature)
		? __('Sync required', 'elasticpress')
		: availabilityStatus;

	return (
		<div className="ep-feature-tab">
			{shortTitle}
			{status ? <small className="ep-feature-tab__status">{status}</small> : null}
		</div>
	);
};
