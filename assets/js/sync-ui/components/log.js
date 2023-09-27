/**
 * WordPress dependencies.
 */
import { Button, Flex, FlexItem, TabPanel } from '@wordpress/components';
import { useCopyToClipboard } from '@wordpress/compose';
import { dateI18n } from '@wordpress/date';
import { Fragment, useMemo, WPElement } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { useSettingsScreen } from '../../settings-screen';
import { useSync } from '../../sync';

/**
 * Sync logs component.
 *
 * @returns {WPElement} Component.
 */
export default () => {
	const { clearLog, log } = useSync();
	const { createNotice } = useSettingsScreen();

	/**
	 * The log as plain text.
	 *
	 * @type {string}
	 */
	const plainTextLog = useMemo(() => {
		return log.map((m) => `${m.dateTime} ${m.message}`).join('\n');
	}, [log]);

	/**
	 * Error messages from the log.
	 *
	 * @type {Array}
	 */
	const errorLog = useMemo(
		() => log.filter((m) => m.status === 'error' || m.status === 'warning'),
		[log],
	);

	/**
	 * Handle clicking the clear log button.
	 *
	 * @returns {void}
	 */
	const onClear = () => {
		clearLog();
	};

	/**
	 * Copy to clipboard button ref.
	 *
	 * @type {object}
	 */
	const ref = useCopyToClipboard(plainTextLog, () => {
		createNotice('info', __('Copied log to clipboard.', 'elasticpress'));
	});

	/**
	 * Log tabs.
	 *
	 * @type {Array}
	 */
	const tabs = [
		{
			messages: log,
			name: 'full',
			title: __('Full Log', 'elasticpress'),
		},
		{
			messages: errorLog,
			name: 'error',
			title: sprintf(
				/* translators: %d: Error message count. */
				__('Errors (%d)', 'elasticpress'),
				errorLog.length,
			),
		},
	];

	return (
		<>
			<TabPanel className="ep-sync-log" tabs={tabs}>
				{({ messages }) => (
					<div className="ep-sync-messages">
						{messages.map((m) => (
							<Fragment key={m.id}>
								<div className="ep-sync-messages__line-number" role="presentation">
									{dateI18n('Y-m-d H:i:s', m.dateTime)}
								</div>
								<div
									className={`ep-sync-messages__message ep-sync-messages__message--${m.status}`}
								>
									{m.message}
								</div>
							</Fragment>
						))}
					</div>
				)}
			</TabPanel>
			<Flex justify="start">
				<FlexItem>
					<Button disabled={!log.length} onClick={onClear} variant="secondary">
						{__('Clear log', 'elasticpress')}
					</Button>
				</FlexItem>
				<FlexItem>
					<Button disabled={!log.length} ref={ref} variant="secondary">
						{__('Copy log to clipboard', 'elasticpress')}
					</Button>
				</FlexItem>
			</Flex>
		</>
	);
};
