/**
 * WordPress dependencies.
 */
import { Button, Flex, FlexItem, Notice, Panel, PanelBody, TabPanel } from '@wordpress/components';
import { useMemo, useState, WPElement, useRef, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { create as createPersistence } from '@wordpress/preferences-persistence';
import { dispatch, useDispatch, useSelect } from '@wordpress/data';
import { store as prefsStore } from '@wordpress/preferences';

/**
 * Internal dependencies.
 */
import { useSettingsScreen } from '../../settings-screen';
import { syncUrl, syncNonce } from '../config';
import { useFeatureSettings } from '../provider';
import Feature from '../components/feature';
import Tab from '../components/tab';

/**
 * Styles.
 */
import '../style.css';

/**
 * Keeps persistence of the last active feature setting across page reloads.
 */
wp.data.dispatch('core/preferences').setPersistenceLayer(createPersistence());

dispatch(prefsStore).setDefaults('elasticpress', {
	// userSetFeatures keeps track of 1. the active group and 2. the active feature within that group
	userSetFeatures: {
		activeGroup: '',
		activeFeature: '',
	},
});

/**
 * Feature settings dashboard app.
 *
 * @returns {WPElement} Reports component.
 */
export default () => {
	const { createNotice } = useSettingsScreen();
	const {
		features,
		isBusy,
		isModified,
		isSyncing,
		isSyncRequired,
		resetSettings,
		saveSettings,
		setIsSyncing,
	} = useFeatureSettings();

	const userSetFeatures = useSelect(
		(select) => select('core/preferences').get('elasticpress', 'userSetFeatures'),
		[],
	);

	// Flag to track initial render and prevent unnecessary updates
	const initialRenderRef = useRef(true);
	const userSetFeaturesRef = useRef(userSetFeatures);

	// Update the ref whenever userSetFeatures changes
	useEffect(() => {
		userSetFeaturesRef.current = userSetFeatures;
	}, [userSetFeatures]);

	/**
	 * URL to start a sync.
	 */
	const syncNowUrl = useMemo(() => {
		const url = new URL(syncUrl);

		url.searchParams.append('do_sync', 'features');
		url.searchParams.append('ep_sync_nonce', syncNonce);

		return url.toString();
	}, []);

	/**
	 * Generic error notice.
	 */
	const errorNotice = __('Could not save feature settings. Please try again.', 'elasticpress');

	/**
	 * Action when a sync is in progress
	 */
	const isSyncingActions = [
		{
			url: syncUrl,
			label: __('View sync status', 'elasticpress'),
		},
	];

	/**
	 * Notice when a sync is in progress.
	 */
	const isSyncingNotice = __('Cannot save settings while a sync is in progress.', 'elasticpress');

	/**
	 * Reset notice.
	 */
	const resetNotice = __('Changes to feature settings discarded.', 'elasticpress');

	/**
	 * Action when syncing later.
	 */
	const syncLaterActions = [
		{
			url: syncNowUrl,
			label: __('Sync', 'elasticpress'),
		},
	];

	/**
	 * Prompt when syncing later.
	 */
	const syncLaterConfirm = __(
		'If you choose to sync later some settings changes may not take effect until the sync is performed. Save and sync later?',
		'elasticpress',
	);

	/**
	 * Prompt when syncing now.
	 */
	const syncNowConfirm = __(
		'Saving these settings will begin re-syncing your content. Save and sync now?',
		'elasticpress',
	);

	/**
	 * Notice when syncing now.
	 */
	const syncNowNotice = __('Feature settings saved. Starting sync…', 'elasticpress');

	/**
	 * Success notice.
	 */
	const successNotice = __('Feature settings saved.', 'elasticpress');

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
				group: f.group,
			};
		});

	const { set } = useDispatch('core/preferences');

	/**
	 * Error handler.
	 *
	 * @param {Error} e Error object.
	 */
	const onError = (e) => {
		if (e.data === 'is_syncing') {
			createNotice('error', isSyncingNotice, { actions: isSyncingActions });
			setIsSyncing(true);
			return;
		}

		const errorMessage = `${__(
			'ElasticPress: Could not save feature settings.',
			'elasticpress',
		)}\n${e.message}`;

		console.error(errorMessage); // eslint-disable-line no-console

		createNotice('error', errorNotice);
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
			// eslint-disable-next-line no-alert
			if (!window.confirm(syncNowConfirm)) {
				return;
			}
		}

		setWillSyncLater(false);

		try {
			await saveSettings();

			if (isSyncRequired) {
				createNotice('success', syncNowNotice);

				window.location = syncNowUrl;
			} else {
				createNotice('success', successNotice);
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
		// eslint-disable-next-line no-alert
		if (!window.confirm(syncLaterConfirm)) {
			return;
		}

		setWillSyncLater(true);

		try {
			await saveSettings(false);

			createNotice('success', successNotice, { actions: syncLaterActions });
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

		createNotice('success', resetNotice);
	};

	const setUserSetFeatures = (activeGroup, activeFeature) => {
		// Skip setting userSetFeatures during initial render
		if (initialRenderRef.current) {
			return;
		}

		// Only update if values are actually changing to prevent unnecessary re-renders
		if (
			userSetFeaturesRef.current.activeGroup !== activeGroup ||
			userSetFeaturesRef.current.activeFeature !== activeFeature
		) {
			userSetFeaturesRef.current = { activeGroup, activeFeature };
			set('elasticpress', 'userSetFeatures', { activeGroup, activeFeature });
		}
	};

	const renderFeatureTabs = (group) => {
		const updatedTabs = group.tabs.map((tab) => ({
			...tab,
			isActive: tab.name === userSetFeatures.activeFeature,
		}));

		// Find initial tab - use stored preference if possible
		const initialTab =
			userSetFeatures.activeFeature &&
			group.tabs.some((tab) => tab.name === userSetFeatures.activeFeature)
				? userSetFeatures.activeFeature
				: updatedTabs[0]?.name;

		return (
			<Panel className="ep-dashboard-panel" key={group.title}>
				<PanelBody>
					{isSyncing ? (
						<Notice actions={isSyncingActions} isDismissible={false} status="warning">
							{isSyncingNotice}
						</Notice>
					) : null}
					<TabPanel
						className="ep-dashboard-tabs"
						orientation="vertical"
						initialTabName={initialTab}
						onSelect={(name) => {
							if (!initialRenderRef.current) {
								setUserSetFeatures(group.title, name);
							}
						}}
						tabs={updatedTabs}
					>
						{({ name }) => <Feature feature={name} key={name} />}
					</TabPanel>
				</PanelBody>
			</Panel>
		);
	};

	/**
	 * Render a feature group.
	 *
	 * @param {Array} groupTabs Group tabs.
	 * @returns {WPElement|null}
	 * */
	const renderFeatureGroup = (groupTabs) => {
		if (!groupTabs.length) return null;

		// Determine initial group tab
		const initialGroup =
			userSetFeatures.activeGroup &&
			groupTabs.some((group) => group.title === userSetFeatures.activeGroup)
				? userSetFeatures.activeGroup
				: groupTabs[0].title;

		return (
			<TabPanel
				className="ep-dashboard-outer-tabs"
				orientation="horizontal"
				tabs={groupTabs.map((group) => ({
					name: group.title,
					title: group.title,
				}))}
				initialTabName={initialGroup}
				onSelect={(name) => {
					if (initialRenderRef.current) {
						return;
					}

					const selectedGroup = groupTabs.find((group) => group.title === name);
					if (!selectedGroup) return;

					const selectedFeatureWithinGroup = selectedGroup.tabs[0].name;
					setUserSetFeatures(selectedGroup.title, selectedFeatureWithinGroup);
				}}
			>
				{({ name }) => {
					const group = groupTabs.find((g) => g.title === name);
					if (!group) return null;

					return renderFeatureTabs(group);
				}}
			</TabPanel>
		);
	};

	const groupedTabs = useMemo(() => {
		// Get unique groups from features
		const groups = [...new Set(features.map((f) => f.group).filter((slug) => slug))];
		// Group tabs by their group property
		const groupsWithTabs = groups.map((group) => ({
			title: group,
			tabs: tabs.filter((t) => t.group === group),
		}));

		// Add "Other" group for tabs without a group
		const otherTabs = tabs.filter((t) => !t.group || !groups.includes(t.group));
		if (otherTabs.length > 0) {
			groupsWithTabs.push({
				title: __('Other', 'elasticpress'),
				tabs: otherTabs,
			});
		}

		return groupsWithTabs;
	}, [features, tabs]);

	// Set initialRenderRef to false after component mounts to allow updates in callbacks
	useEffect(() => {
		// Wait for a tick to ensure the initial rendering completes
		const timeoutId = setTimeout(() => {
			initialRenderRef.current = false;
		}, 100);

		return () => clearTimeout(timeoutId);
	}, []);

	return (
		<form onReset={onReset} onSubmit={onSubmit}>
			{renderFeatureGroup(groupedTabs)}
			{isSyncing && (
				<Notice actions={isSyncingActions} isDismissible={false} status="warning">
					{isSyncingNotice}
				</Notice>
			)}
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
