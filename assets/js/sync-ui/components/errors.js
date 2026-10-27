/**
 * WordPress dependencies.
 */
import { safeHTML } from '@wordpress/dom';
import { RawHTML, WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { useSync } from '../../sync';

/**
 * Stack trace component.
 *
 * @param {object} props Component props.
 * @param {string} props.stackTrace Stack trace for the error.
 * @returns {WPElement} Component.
 */
const StackTrace = ({ stackTrace }) => {
	return (
		<details className="ep-sync-errors__stack-trace">
			<summary>{__('Stack trace', 'elasticpress')}</summary>
			<pre>{stackTrace}</pre>
		</details>
	);
};

/**
 * Log messages component.
 *
 * @returns {WPElement} Component.
 */
export default () => {
	const { errorCounts } = useSync();

	return (
		<div className="ep-sync-errors">
			{errorCounts.length ? (
				<table className="ep-sync-errors__table">
					<thead>
						<tr>
							<th>{__('Count', 'elasticpress')}</th>
							<th>{__('Error type', 'elasticpress')}</th>
						</tr>
					</thead>
					{errorCounts.map((e) => (
						<tr key={e.type}>
							<td className="ep-sync-errors__count">{e.count}</td>
							<td>
								{e.type}
								<RawHTML className="ep-sync-errors__solution">
									{safeHTML(e.solution)}
								</RawHTML>
								{e.stack_trace ? <StackTrace stackTrace={e.stack_trace} /> : null}
							</td>
						</tr>
					))}
				</table>
			) : (
				<p>{__('No errors found in the log.', 'elasticpress')}</p>
			)}
		</div>
	);
};
