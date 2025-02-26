/**
 * WordPress dependencies.
 */
import { Button, Flex, FlexItem } from '@wordpress/components';
import { useCopyToClipboard } from '@wordpress/compose';
import { useState, WPElement } from '@wordpress/element';
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
 * @param {object} props.reports Status reports
 *
 * @returns {WPElement} Reports component.
 */
export default ({ plainTextReport, reports }) => {
	const { createNotice } = useSettingsScreen();
	const [updatedReports, setUpdatedReports] = useState(reports);
	const [updatedPlainTextReport, setUpdatedPlainTextReport] = useState(plainTextReport);
	const [generatedReport, setGeneratedReport] = useState(false);

	const ref = useCopyToClipboard(updatedPlainTextReport, () => {
		createNotice('info', __('Copied status report to clipboard.', 'elasticpress'));
	});

	const downloadUrl = `data:text/plain;charset=utf-8,${encodeURIComponent(updatedPlainTextReport)}`;

	/**
	 * Convert report data to plain text.
	 *
	 * @param {string} title Report title.
	 * @param {Array} groups Report groups.
	 *
	 * @returns {string} Plain text report.
	 */
	const toPlainTextReport = (title, groups) => {
		let output = `## ${title} ##\n\n`;
		groups.forEach((group) => {
			output += `### ${group.title} ###\n`;
			Object.entries(group.fields).forEach(([slug, field]) => {
				const value = field.value ?? '';
				output += `${slug}: ${value}\n`;
			});
			output += `\n`;
		});
		return output;
	};

	/**
	 * Load a group report data via AJAX
	 *
	 * @param {string} id Group ID.
	 *
	 *
	 * @returns {Promise} Group data.
	 */
	const loadGroupAjax = async (id) => {
		const { ajaxurl } = window;

		const data = new FormData();
		data.append('action', 'ep_load_groups');
		data.append('ep-status-report-nonce', window.epStatusReport.nonce);
		data.append('report', id);
		return fetch(ajaxurl, { method: 'POST', body: data });
	};

	/**
	 * Handle report loading.
	 *
	 * @returns {void}
	 */
	const handleReportLoading = async () => {
		if (generatedReport) {
			return;
		}

		createNotice('info', __('Generating full status report ...', 'elasticpress'));

		const newReports = { ...reports };

		const ajaxTasks = Object.entries(newReports)
			.filter(([, reportData]) => reportData.is_ajax_report)
			.map(async ([key, reportData]) => {
				const response = await loadGroupAjax(key);
				const body = await response.json();
				if (!response.ok) {
					if (body.data.message) {
						createNotice('info', body.data.message);
					}
					return;
				}

				newReports[key] = {
					...reportData,
					groups: body.data.groups,
					messages: body.data.messages,
				};
			});

		await Promise.all(ajaxTasks);

		setUpdatedReports(newReports);

		const additionalText = Object.entries(newReports)
			.filter(([, reportData]) => reportData.is_ajax_report)
			.map(([, reportData]) => toPlainTextReport(reportData.title, reportData.groups || []))
			.join('\n');

		const combinedReport = `${plainTextReport}\n\n${additionalText}`;
		setUpdatedPlainTextReport(combinedReport);

		setGeneratedReport(true);

		createNotice('info', __('Successfully generated status report.', 'elasticpress'));
	};

	return (
		<>
			<p>
				{__(
					'This screen provides a list of information related to ElasticPress and synced content that can be helpful during troubleshooting. As the process can be resource-intensive, you must click the "Generate Full Status Report" button to generate a full report list. Once generated, this list can also be copy/pasted and shared as needed.',
					'elasticpress',
				)}
			</p>
			<p>
				<Flex justify="start">
					<FlexItem>
						<Button
							disabled={generatedReport}
							onClick={handleReportLoading}
							variant="primary"
						>
							{__('Generate Full Status Report', 'elasticpress')}
						</Button>
					</FlexItem>
					<FlexItem>
						<Button
							disabled={!generatedReport}
							download="elasticpress-report.txt"
							href={downloadUrl}
							variant="primary"
						>
							{__('Download status report', 'elasticpress')}
						</Button>
					</FlexItem>
					<FlexItem>
						<Button disabled={!generatedReport} ref={ref} variant="secondary">
							{__('Copy status report to clipboard', 'elasticpress')}
						</Button>
					</FlexItem>
				</Flex>
			</p>

			{Object.entries(updatedReports).map(
				([key, { actions, groups, messages, title, is_ajax_report }]) => (
					<Report
						key={key}
						id={key}
						title={title}
						actions={actions}
						groups={groups}
						messages={messages}
						is_ajax_report={is_ajax_report}
					/>
				),
			)}
		</>
	);
};
