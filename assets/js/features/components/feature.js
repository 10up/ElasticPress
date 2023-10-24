/**
 * WordPress dependencies.
 */
import { Notice } from '@wordpress/components';
import { safeHTML } from '@wordpress/dom';
import { RawHTML, WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { useFeatureSettings } from '../provider';
import Settings from './settings';

/**
 * Reports component.
 *
 * @param {object} props Component props.
 * @param {WPElement} props.feature Feature slug.
 * @returns {WPElement} Reports component.
 */
export default ({ feature }) => {
	const { epioLogoUrl, getFeature } = useFeatureSettings();

	const { isPoweredByEpio, reqStatusCode, reqStatusMessages, settingsSchema, summary, title } =
		getFeature(feature);

	return (
		<>
			{/* eslint-disable-next-line react/no-danger */}
			<h3 className="ep-dashboard-heading">
				<RawHTML>{safeHTML(title)}</RawHTML>
				{isPoweredByEpio ? (
					<img
						alt={__('ElasticPress.io logo')}
						height="20"
						src={epioLogoUrl}
						width="110"
					/>
				) : null}
			</h3>
			{/* eslint-disable-next-line react/no-danger */}
			<p dangerouslySetInnerHTML={{ __html: safeHTML(summary) }} />
			{reqStatusMessages.map((m) => (
				<Notice isDismissible={false} status={reqStatusCode === 2 ? 'error' : 'warning'}>
					{/* eslint-disable-next-line react/no-danger */}
					<span dangerouslySetInnerHTML={{ __html: safeHTML(m) }} />
				</Notice>
			))}
			<Settings feature={feature} settingsSchema={settingsSchema} />
		</>
	);
};
