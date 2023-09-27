/**
 * WordPress dependencies.
 */
import { Button, Flex, FlexItem } from '@wordpress/components';
import { useCopyToClipboard } from '@wordpress/compose';
import { WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import Report from './reports/report';
import { useSettingsScreen } from '../../settings-screen';

/**
 * Styles.
 */
import '../style.css';

/**
 * Reports component.
 *
 * @param {object} props Component props.
 * @param {string} props.plainTextReport Plain text report.
 * @param {object} props.reports Status reports.
 * @returns {WPElement} Reports component.
 */
export default ({ plainTextReport, reports }) => {
	const { createNotice } = useSettingsScreen();

	const downloadUrl = `data:text/plain;charset=utf-8,${encodeURIComponent(plainTextReport)}`;

	/**
	 * Copy to clipboard button ref.
	 *
	 * @type {object}
	 */
	const ref = useCopyToClipboard(plainTextReport, () => {
		createNotice('info', __('Copied status report to clipboard.', 'elasticpress'));
	});

	return (
		<>
			<p>
				{__(
					'This screen provides a list of information related to ElasticPress and synced content that can be helpful during troubleshooting. This list can also be copy/pasted and shared as needed.',
					'elasticpress',
				)}
			</p>
			<p>
				<Flex justify="start">
					<FlexItem>
						<Button
							download="elasticpress-report.txt"
							href={downloadUrl}
							variant="primary"
						>
							{__('Download status report', 'elasticpress')}
						</Button>
					</FlexItem>
					<FlexItem>
						<Button ref={ref} variant="secondary">
							{__('Copy status report to clipboard', 'elasticpress')}
						</Button>
					</FlexItem>
				</Flex>
			</p>
			{Object.entries(reports).map(([key, { actions, groups, messages, title }]) => (
				<Report
					actions={actions}
					groups={groups}
					id={key}
					key={key}
					messages={messages}
					title={title}
				/>
			))}
		</>
	);
};
