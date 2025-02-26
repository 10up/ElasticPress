/**
 * WordPress dependencies.
 */
import { WPElement } from '@wordpress/element';

/**
 * Internal dependencies.
 */
// import ReportHeader from './report/header';
import ReportContent from './report/content';
import ReportPendingContent from './report/pendingContent';
import ReportContainer from './report/container';

/**
 * Report components.
 *
 * @param {object} props Component props.
 * @param {Array} props.actions Report actions.
 * @param {object} props.groups Report groups.
 * @param {string} props.id Report ID.
 * @param {string} props.messages Report messages.
 * @param {string} props.title Report title.
 * @param {boolean} props.is_ajax_report Whether the report is loaded via AJAX.
 *
 * @returns {WPElement} Report component.
 */
export default ({ actions, groups, id, messages, title, is_ajax_report }) => {
	if (groups.length < 1 && !is_ajax_report) {
		return null;
	}

	return (
		<ReportContainer id={id} title={title} actions={actions} messages={messages}>
			{is_ajax_report && groups.length < 1 ? (
				<ReportPendingContent />
			) : (
				groups.map(({ fields, title }) => (
					<ReportContent key={title} fields={fields} title={title} />
				))
			)}
		</ReportContainer>
	);
};
