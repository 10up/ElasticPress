/**
 * WordPress dependencies.
 */
import { Button } from '@wordpress/components';
import { WPElement, useState } from '@wordpress/element';

/**
 * Internal dependencies.
 */
// import ReportHeader from './report/header';
import ReportContent from './report/content';
import ReportContainer from './report/container';
import { loadGroupAjax } from '../../utilities';

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

	const [group, setGroup] = useState(false);

	const loadAjax = async () => {
		const request = await loadGroupAjax(id);
		request.json().then((response) => {
			setGroup(response);
		});
	};

	if (is_ajax_report) {
		if (!group) {
			return (
				<ReportContainer id={id} title={title} messages={messages}>
					<Button variant="primary" onClick={loadAjax}>
						Load Report
					</Button>
				</ReportContainer>
			);
		}

		return (
			<ReportContainer id={id} title={title} actions={actions} messages={messages}>
				{group.map(({ fields, title }) => (
					<ReportContent key={title} fields={fields} title={title} />
				))}
			</ReportContainer>
		);

	}
	return (
		<ReportContainer id={id} title={title} actions={actions} messages={messages}>
			{groups.map(({ fields, title }) => (
				<ReportContent key={title} fields={fields} title={title} />
			))}
		</ReportContainer>
	);
};
