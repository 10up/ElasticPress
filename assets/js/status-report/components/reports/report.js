/**
 * WordPress dependencies.
 */
import { WPElement } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import ReportContent from './report/content';
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
 * @param {boolean} props.isAjaxReport Whether the report is loaded via AJAX.
 *
 * @returns {WPElement} Report component.
 */
export default ({ actions, groups, id, messages, title, isAjaxReport }) => {
	if (groups.length < 1 && !isAjaxReport) {
		return null;
	}

	if (groups.length < 1 && isAjaxReport) {
		return <ReportContainer id={id} title={title} actions={actions} messages={messages} />;
	}

	return (
		<ReportContainer id={id} title={title} actions={actions} messages={messages}>
			{groups.map(({ fields, title }) => (
				<ReportContent key={title} fields={fields} title={title} />
			))}
		</ReportContainer>
	);
};
