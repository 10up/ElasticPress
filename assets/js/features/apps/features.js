/**
 * External dependencies.
 */
// eslint-disable-next-line import/no-extraneous-dependencies
import { Route, Routes, Navigate, HashRouter, useParams, useNavigate } from 'react-router-dom';

/**
 * WordPress dependencies.
 */
import { Button, Flex, FlexItem, Notice, Panel, PanelBody } from '@wordpress/components';
import { useMemo, useState, WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { useSettingsScreen } from '../../settings-screen';
import { syncUrl, syncNonce, featureGroups } from '../config';
import { useFeatureSettings } from '../provider';
import Feature from '../components/feature';

/**
 * Styles.
 */
import '../style.css';

/**
 * Navigation Tab Component for features
 *
 * @param {object} props Component props
 * @param {string} props.title Feature title
 * @param {string} props.to URL to navigate to
 * @param {boolean} props.isActive Whether this tab is active
 * @returns {WPElement} Tab component
 */
const NavigationTab = ({ title, to, isActive }) => {
	const navigate = useNavigate();
	return (
		<button
			className={`ep-dashboard-tab ${isActive ? 'is-active' : ''}`}
			aria-current={isActive ? 'page' : undefined}
			onClick={() => navigate(to)}
			type="button"
			id={`title-${title}-to-${to}`}
		>
			{title}
		</button>
	);
};

/**
 * Group navigation component that determines which group is active based on the current feature
 *
 * @param {object} props Component props
 * @param {Array} props.groupedFeatures Grouped features data
 * @param {string} props.activeFeature Currently active feature slug
 * @returns {WPElement} Group navigation component
 */
const GroupNavigation = ({ groupedFeatures, activeFeature }) => {
	// Find which group contains the active feature
	const activeGroup = groupedFeatures.find((group) =>
		group.features.some((feature) => feature.slug === activeFeature),
	);

	const activeGroupSlug = activeGroup?.groupSlug || groupedFeatures[0]?.groupSlug;

	return (
		<div className="ep-dashboard-outer-tabs">
			<div className="ep-dashboard-tabs-nav">
				{groupedFeatures.map(({ groupSlug, title, features }) => (
					<NavigationTab
						key={groupSlug}
						slug={groupSlug}
						title={title}
						to={`/${features[0]?.slug || ''}`}
						isActive={groupSlug === activeGroupSlug}
					/>
				))}
			</div>
		</div>
	);
};

/**
 * Feature navigation component for features within a group
 *
 * @param {object} props Component props
 * @param {Array} props.groupedFeatures Grouped features data
 * @param {string} props.activeFeature Currently active feature
 * @returns {WPElement} Feature navigation component
 */
const FeatureNavigation = ({ groupedFeatures, activeFeature }) => {
	// Find which group contains the active feature
	const currentGroup = groupedFeatures.find((group) =>
		group.features.some((feature) => feature.slug === activeFeature),
	);

	if (!currentGroup) {
		return null;
	}

	return (
		<Panel className="ep-dashboard-panel">
			<PanelBody>
				<div className="ep-dashboard-tabs">
					<div className="ep-dashboard-tabs-nav">
						{currentGroup.features.map(({ slug, shortTitle, title }) => (
							<NavigationTab
								key={slug}
								slug={slug}
								title={shortTitle || title || slug}
								to={`/${slug}`}
								isActive={activeFeature === slug}
							/>
						))}
					</div>
				</div>
			</PanelBody>
		</Panel>
	);
};

/**
 * Feature settings dashboard app content, using react-router-dom for navigation.
 *
 * @returns {WPElement} Feature Settings component
 */
const FeatureSettingsContent = () => {
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

	// Get feature from URL parameters
	const { feature } = useParams();

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
	 * Action when a sync is in progress.
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
	 * Whether the user has chosen to sync later when saving.
	 */
	const [willSyncLater, setWillSyncLater] = useState(false);

	/**
	 * Group visible features by their group property using centralized featureGroups
	 */
	const groupedFeatures = useMemo(() => {
		const groupSlugs = Object.keys(featureGroups || {});

		// Map group slugs to group info (label, slug)
		const groups = groupSlugs.map((slug) => ({
			title: featureGroups[slug].label,
			groupSlug: slug,
			features: [],
		}));

		// Map for quick lookup
		const groupMap = groups.reduce((acc, group) => {
			acc[group.groupSlug] = group;
			return acc;
		}, {});

		// Features with a valid group
		features.forEach((feature) => {
			if (feature.isVisible && feature.group && featureGroups[feature.group]) {
				groupMap[feature.group].features.push(feature);
			}
		});

		// Remove empty groups
		const nonEmptyGroups = groups.filter((g) => g.features.length > 0);

		// Features with no group or unknown group
		const otherFeatures = features.filter(
			(f) => f.isVisible && (!f.group || !featureGroups[f.group]),
		);

		if (otherFeatures.length > 0) {
			nonEmptyGroups.push({
				title: __('Other', 'elasticpress'),
				groupSlug: 'other',
				features: otherFeatures,
			});
		}

		return nonEmptyGroups;
	}, [features]);

	/**
	 * Error handler.
	 *
	 * @param {Error} e Error object.
	 */
	const onError = (e) => {
		if (e.data === 'is_syncing') {
			createNotice('error', isSyncingNotice, {
				actions: isSyncingActions,
			});
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

				// Use window.location for full page redirect to sync URL
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

			createNotice('success', successNotice, {
				actions: syncLaterActions,
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

		createNotice('success', resetNotice);
	};

	// Find which group contains the active feature
	const currentGroup =
		groupedFeatures.find((group) => group.features.some((feat) => feat.slug === feature))
			?.title || null;

	// If we can't find the current group, use the first one
	const activeGroup = currentGroup || groupedFeatures[0];

	return (
		<form onReset={onReset} onSubmit={onSubmit}>
			{isSyncing ? (
				<Notice actions={isSyncingActions} isDismissible={false} status="warning">
					{isSyncingNotice}
				</Notice>
			) : null}
			<div className="form-grid">
				{/* Group Navigation */}
				<GroupNavigation groupedFeatures={groupedFeatures} activeFeature={feature} />

				<div className="group-content" id={`${activeGroup}-view`}>
					{/* Feature Navigation for the current group */}
					<FeatureNavigation groupedFeatures={groupedFeatures} activeFeature={feature} />

					{/* Feature Content based on route parameters */}
					<div className="ep-dashboard-content" id={`${feature}-view`}>
						{feature ? (
							<Feature feature={feature} />
						) : (
							<Notice status="info" isDismissible={false}>
								{__('Select a feature above.', 'elasticpress')}
							</Notice>
						)}
					</div>
				</div>
			</div>
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

/**
 * Root layout component with HashRouter configuration
 *
 * @returns {WPElement} Root component with routing
 */
export default () => {
	const { features } = useFeatureSettings();

	// Determine default routes for redirects
	const defaultRouteInfo = useMemo(() => {
		const visibleFeatures = features.filter((f) => f.isVisible);

		// Get the first visible feature as default
		const defaultFeature = visibleFeatures[0]?.slug || '';
		const hasFeatures = visibleFeatures.length > 0;

		return { defaultFeature, hasFeatures };
	}, [features]);

	return (
		<HashRouter>
			<Routes>
				{/* Main route for displaying feature settings with specific feature */}
				<Route path="/:feature" element={<FeatureSettingsContent />} />

				{/* Default redirect when accessing root or invalid URLs */}
				{defaultRouteInfo.hasFeatures ? (
					<Route
						path="*"
						element={<Navigate to={`/${defaultRouteInfo.defaultFeature}`} replace />}
					/>
				) : (
					<Route
						path="*"
						element={
							<Notice status="info" isDismissible={false}>
								{__('No features available.', 'elasticpress')}
							</Notice>
						}
					/>
				)}
			</Routes>
		</HashRouter>
	);
};
