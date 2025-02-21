/**
 * WordPress dependencies.
 */
import { Button, Flex, FlexItem } from '@wordpress/components';
import { useCopyToClipboard } from '@wordpress/compose';
import { WPElement, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import Report from './reports/report';
import { useSettingsScreen } from '../../settings-screen';
import { loadGroupAjax } from '../utilities';

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

	const [reportText, setReportText] = useState(null);
	const downloadUrl = `data:text/plain;charset=utf-8,${encodeURIComponent(plainTextReport)}`;

	/**
	 * Copy to clipboard button ref.
	 *
	 * @type {object}
	 */
	const ref = useCopyToClipboard(reportText, () => {
		createNotice(
			'success',
			__(
				'The status report has been copied to the clipboard. You can now paste it into a text editor.',
				'elasticpress',
			),
		);
	});

	/**
	 * Handle loading and building report plain text.
	 */
	const handleReportLoading = async () => {
		if (reportText) {
			return;
		}

		const ajaxReportPromises = Object.entries(reports)
			.filter(([key, reportData]) => reportData.is_ajax_report) // eslint-disable-line no-unused-vars
			.map(async ([key]) => {
				const response = await loadGroupAjax(key);
				const jsonData = await response.json();
				return jsonData;
			});

		const text = await Promise.all(ajaxReportPromises).then((ajaxReportTexts) => {
			return JSON.stringify(ajaxReportTexts, null, 2);
		});

		setReportText(text);
	};

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
							onClick={handleReportLoading}
							href={reportText ? null : downloadUrl}
							variant="primary"
						>
							{__('Download status report', 'elasticpress')}
						</Button>
					</FlexItem>
					<FlexItem>
						<Button ref={ref} onClick={handleReportLoading} variant="secondary">
							{__('Copy status report to clipboard', 'elasticpress')}
						</Button>
					</FlexItem>
				</Flex>
			</p>
			{Object.entries(reports).map(
				([key, { actions, groups, messages, title, is_ajax_report }]) => (
					<Report
						actions={actions}
						groups={groups}
						id={key}
						key={key}
						is_ajax_report={is_ajax_report}
						messages={messages}
						title={title}
					/>
				),
			)}
		</>
	);
};
