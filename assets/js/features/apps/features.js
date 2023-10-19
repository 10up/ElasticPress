/**
 * WordPress dependencies.
 */
import { Button, Flex, FlexItem, Panel, PanelBody, TabPanel } from '@wordpress/components';
import { useState, WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { useSettingsScreen } from '../../settings-screen';
import { syncUrl } from '../config';
import { useFeatureSettings } from '../provider';
import Feature from '../components/feature';
import Tab from '../components/tab';

/**
 * Styles.
 */
import '../style.css';

/**
 * Feature settings dashboard app.
 *
 * @returns {WPElement} Reports component.
 */
export default () => {
	const { createNotice } = useSettingsScreen();
	const { features, isBusy, isModified, isSyncing, isSyncRequired, resetSettings, saveSettings } =
		useFeatureSettings();

	/**
	 * Whether the user has chosen to sync later when saving. Used to show the
	 * busy state on the correct button.
	 */
	const [willSyncLater, setWillSyncLater] = useState(false);

	/**
	 * Feature settings tabs.
	 */
	const tabs = features
		.filter((f) => f.isVisible)
		.map((f) => {
			return {
				name: f.slug,
				title: <Tab feature={f.slug} />,
			};
		});

	/**
	 * Error handler.
	 *
	 * @param {Error} e Error object.
	 */
	const onError = (e) => {
		const errorMessage = `${__(
			'ElasticPress: Could not save feature settings.',
			'elasticpress',
		)}\n${e.message}`;

		console.error(errorMessage); // eslint-disable-line no-console

		createNotice(
			'error',
			__('Could not save feature settings. Please try again.', 'elasticpress'),
		);
	};

	/**
	 * Form submission event handler.
	 *
	 * @param {Event} event Submit event.
	 * @returns {void}
	 */
	const onSubmit = async (event) => {
		event.preventDefault();

		if (isSyncRequired) {
			if (
				// eslint-disable-next-line no-alert
				!window.confirm(
					__(
						'Saving these settings will begin re-syncing your content. Save and sync now?',
					),
				)
			) {
				return;
			}
		}

		setWillSyncLater(false);

		try {
			await saveSettings();

			if (isSyncRequired) {
				createNotice('success', __('Feature settings saved. Starting sync…'));
				window.location = syncUrl;
			} else {
				createNotice('success', __('Feature settings saved.'));
			}
		} catch (e) {
			onError(e);
		}
	};

	/**
	 * Save and sync later button click event.
	 *
	 * @returns {void}
	 */
	const onClickSyncLater = async () => {
		if (
			// eslint-disable-next-line no-alert
			!window.confirm(
				__(
					'If you choose to sync later some settings changes may not take effect until the sync is performed. Save and sync later?',
				),
			)
		) {
			return;
		}

		setWillSyncLater(true);

		try {
			await saveSettings(false);

			createNotice('success', __('Feature settings saved.', 'elasticpress'), {
				actions: [
					{
						url: syncUrl,
						label: __('Sync', 'elasticpress'),
					},
				],
			});
		} catch (e) {
			onError(e);
		}
	};

	/**
	 * Form reset event handler.
	 *
	 * @param {Event} event Reset event.
	 * @returns {void}
	 */
	const onReset = (event) => {
		event.preventDefault();

		resetSettings();

		createNotice('success', __('Changes to feature settings discarded.', 'elasticpress'));
	};

	return (
		<form onReset={onReset} onSubmit={onSubmit}>
			<Panel className="ep-dashboard-panel">
				<PanelBody>
					<TabPanel className="ep-dashboard-tabs" orientation="vertical" tabs={tabs}>
						{({ name }) => <Feature feature={name} key={name} />}
					</TabPanel>
				</PanelBody>
			</Panel>
			<Flex justify="start">
				<FlexItem>
					<Button
						disabled={isBusy || isSyncing}
						isBusy={isBusy && !willSyncLater}
						type="submit"
						variant="primary"
					>
						{isSyncRequired
							? __('Save and sync now', 'elasticpress')
							: __('Save changes', 'elasticpress')}
					</Button>
				</FlexItem>
				{isSyncRequired ? (
					<FlexItem>
						<Button
							disabled={isBusy || isSyncing}
							isBusy={isBusy && willSyncLater}
							onClick={onClickSyncLater}
							type="button"
							variant="secondary"
						>
							{__('Save and sync later', 'elasticpress')}
						</Button>
					</FlexItem>
				) : null}
				{isModified ? (
					<FlexItem>
						<Button disabled={isBusy} type="reset" variant="tertiary">
							{__('Discard changes', 'elasticpress')}
						</Button>
					</FlexItem>
				) : null}
			</Flex>
		</form>
	);
};
