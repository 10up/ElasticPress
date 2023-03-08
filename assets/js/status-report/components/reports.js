/**
 * WordPress dependencies.
 */
import { WPElement } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import Report from './reports/report';

/**
 * Reports component.
 *
 * @param {object} props Component props.
 * @param {object} props.reports Status reports.
 * @returns {WPElement} Reports component.
 */
export default ({ reports }) => {
	return Object.entries(reports).map(([key, { actions, groups, messages, title }]) => (
		<Report
			actions={actions}
			groups={groups}
			id={key}
			key={key}
			messages={messages}
			title={title}
		/>
	));
};
