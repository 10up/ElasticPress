/**
 * WordPress dependencies.
 */
import { Button, Flex, FlexItem, Notice, Panel, PanelBody, TabPanel } from '@wordpress/components';
import { useMemo, useState, WPElement, useRef, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';

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
 * Helper functions for URL parameter handling
 *
 * Retrieves the current 'group' and 'feature' parameters from the URL's query string.
 *
 * @returns {object} An object containing:
 * - {string} activeGroup   The value of the 'group' URL parameter, or an empty string if not set.
 * - {string} activeFeature The value of the 'feature' URL parameter, or an empty string if not set.
 */
const getUrlParams = () => {
	const searchParams = new URLSearchParams(window.location.search);
	return {
		activeGroup: searchParams.get('group') || '',
		activeFeature: searchParams.get('feature') || '',
	};
};

const updateUrlParams = (group, feature) => {
	// Create new URL with updated parameters
	const newUrl = addQueryArgs(window.location.href, {
		group,
		feature,
	});

	// Update URL without reloading the page
	window.history.pushState({ group, feature }, '', newUrl);
};

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

	// Get initial state from URL parameters
	const initialParams = useMemo(() => getUrlParams(), []);

	// State to track active group and feature
	const [activeState, setActiveState] = useState({
		activeGroup: initialParams.activeGroup,
		activeFeature: initialParams.activeFeature,
	});

	// Flag to track initial render and prevent unnecessary updates
	const initialRenderRef = useRef(true);

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

	/**
	 * Update active selection and URL
	 *
	 * @param {string} group - The active group name
	 * @param {string} feature - The active feature name
	 */
	const updateActiveState = (group, feature) => {
		// Skip updating during initial render
		if (initialRenderRef.current) {
			return;
		}

		// Only update if values are actually changing to prevent unnecessary re-renders
		if (activeState.activeGroup !== group || activeState.activeFeature !== feature) {
			setActiveState({ activeGroup: group, activeFeature: feature });
			updateUrlParams(group, feature);
		}
	};

	const renderFeatureTabs = (group) => {
		const updatedTabs = group.tabs.map((tab) => ({
			...tab,
			isActive: tab.name === activeState.activeFeature,
		}));

		// Find initial tab - use URL parameter if possible
		const initialTab =
			activeState.activeFeature &&
			group.tabs.some((tab) => tab.name === activeState.activeFeature)
				? activeState.activeFeature
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
								updateActiveState(group.title, name);
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

		// Determine initial group tab from URL parameter
		const initialGroup =
			activeState.activeGroup &&
			groupTabs.some((group) => group.title === activeState.activeGroup)
				? activeState.activeGroup
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
					updateActiveState(selectedGroup.title, selectedFeatureWithinGroup);
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

	// Handle URL change events (back/forward browser navigation)
	useEffect(() => {
		const handlePopState = () => {
			const params = getUrlParams();
			setActiveState({
				activeGroup: params.activeGroup,
				activeFeature: params.activeFeature,
			});
		};

		window.addEventListener('popstate', handlePopState);
		return () => window.removeEventListener('popstate', handlePopState);
	}, []);

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
