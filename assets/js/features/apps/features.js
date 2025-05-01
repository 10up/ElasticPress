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
import { syncUrl, syncNonce } from '../config';
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

const GroupNavigation = ({ groupedFeatures, groupSlug }) => (
	<div className="ep-dashboard-outer-tabs">
		<div className="ep-dashboard-tabs-nav">
			{groupedFeatures.map((groupObj) => (
				<NavigationTab
					key={groupObj.title}
					slug={groupObj.groupSlug}
					title={groupObj.title}
					to={`/${groupObj.groupSlug}/${groupObj.features[0]?.slug || ''}`}
					isActive={groupSlug === groupObj.groupSlug}
				/>
			))}
		</div>
	</div>
);

const FeatureNavigation = ({
	groupedFeatures,
	groupSlug,
	isSyncing,
	isSyncingActions,
	isSyncingNotice,
	feature,
}) => {
	const currentGroup = groupedFeatures.find((g) => g.groupSlug === groupSlug);

	if (!currentGroup) {
		return null;
	}

	return (
		<Panel className="ep-dashboard-panel">
			<PanelBody>
				{isSyncing ? (
					<Notice actions={isSyncingActions} isDismissible={false} status="warning">
						{isSyncingNotice}
					</Notice>
				) : null}
				<div className="ep-dashboard-tabs">
					<div className="ep-dashboard-tabs-nav">
						{currentGroup.features.map((featureObj) => (
							<NavigationTab
								key={featureObj.slug}
								slug={featureObj.slug}
								title={featureObj.shortTitle || featureObj.title || featureObj.slug}
								to={`/${currentGroup.groupSlug}/${featureObj.slug}`}
								isActive={feature === featureObj.slug}
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

	// Get group slug and feature from URL parameters
	const { groupSlug, feature } = useParams();

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
	 * Group visible features by their group property and use group_slug for URLs
	 */
	const groupedFeatures = useMemo(() => {
		// Get unique groups with their slugs from features that are visible and have a group
		const groups = features
			.filter((f) => f.isVisible && f.group)
			.reduce((uniqueGroups, feature) => {
				// Skip if we already have this group
				if (uniqueGroups.some((g) => g.name === feature.group)) {
					return uniqueGroups;
				}

				// Add the group with its name and slug
				return [
					...uniqueGroups,
					{
						name: feature.group,
						// Use group_slug if available, otherwise slugify the group name
						slug:
							feature.group_slug || feature.group.toLowerCase().replace(/\s+/g, '-'),
					},
				];
			}, []);

		// Group visible features by their group property
		const groupsWithFeatures = groups.map((groupInfo) => ({
			title: groupInfo.name,
			groupSlug: groupInfo.slug,
			features: features.filter((f) => f.isVisible && f.group === groupInfo.name),
		}));

		// Add "Other" group for visible features without a group
		const otherFeatures = features.filter(
			(f) => f.isVisible && (!f.group || !groups.some((g) => g.name === f.group)),
		);

		if (otherFeatures.length > 0) {
			groupsWithFeatures.push({
				title: __('Other', 'elasticpress'),
				groupSlug: 'other',
				features: otherFeatures,
			});
		}

		return groupsWithFeatures;
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

	const currentGroup = groupedFeatures.find((g) => g.groupSlug === groupSlug);

	return (
		<form onReset={onReset} onSubmit={onSubmit}>
			<div className="form-grid">
				{/* Group Navigation */}
				<GroupNavigation groupedFeatures={groupedFeatures} groupSlug={groupSlug} />

				<div className="group-content" id={`${currentGroup.title}-view`}>
					{/* Feature Navigation for the current group */}
					<FeatureNavigation
						groupedFeatures={groupedFeatures}
						groupSlug={groupSlug}
						isSyncing={isSyncing}
						isSyncingActions={isSyncingActions}
						isSyncingNotice={isSyncingNotice}
						feature={feature}
					/>

					{/* Feature Content based on route parameters */}
					<div className="ep-dashboard-content" id={`${feature}-view`}>
						{groupSlug && feature ? (
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

		// Get unique groups with their slugs
		const groups = visibleFeatures
			.filter((f) => f.group)
			.reduce((uniqueGroups, feature) => {
				// Skip if we already have this group
				if (uniqueGroups.some((g) => g.name === feature.group)) {
					return uniqueGroups;
				}

				// Add the group with its name and slug
				return [
					...uniqueGroups,
					{
						name: feature.group,
						// Use group_slug if available, otherwise slugify the group name
						slug:
							feature.group_slug || feature.group.toLowerCase().replace(/\s+/g, '-'),
					},
				];
			}, []);

		// Create grouped features with slugs
		const groupedItems = groups.map((groupInfo) => ({
			title: groupInfo.name,
			groupSlug: groupInfo.slug,
			features: visibleFeatures.filter((f) => f.group === groupInfo.name),
		}));

		// Add "Other" group for features without a group
		const otherFeatures = visibleFeatures.filter(
			(f) => !f.group || !groups.some((g) => g.name === f.group),
		);

		if (otherFeatures.length > 0) {
			groupedItems.push({
				title: __('Other', 'elasticpress'),
				groupSlug: 'other',
				features: otherFeatures,
			});
		}

		const defaultGroup = groupedItems[0]?.groupSlug || '';
		const defaultFeature = groupedItems[0]?.features[0]?.slug || '';
		const hasFeatures = groupedItems.length > 0 && groupedItems[0]?.features.length > 0;

		return { defaultGroup, defaultFeature, hasFeatures };
	}, [features]);

	return (
		<HashRouter>
			<Routes>
				{/* Main route for displaying feature settings with specific group slug and feature */}
				<Route path="/:groupSlug/:feature" element={<FeatureSettingsContent />} />

				{/* Group-level route that redirects to the first feature in that group */}
				<Route
					path="/:groupSlug"
					element={
						<GroupRedirect
							features={features}
							defaultFeature={defaultRouteInfo.defaultFeature}
						/>
					}
				/>

				{/* Default redirect when accessing root or invalid URLs */}
				{defaultRouteInfo.hasFeatures ? (
					<Route
						path="*"
						element={
							<Navigate
								to={`/${defaultRouteInfo.defaultGroup}/${defaultRouteInfo.defaultFeature}`}
								replace
							/>
						}
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

/**
 * Helper component for redirecting from group-level URLs to specific features
 *
 * @param {object} props Component props
 * @param {Array} props.features All features
 * @param {string} props.defaultFeature Default feature to redirect to if none found
 * @returns {WPElement} Redirect component
 */
const GroupRedirect = ({ features, defaultFeature }) => {
	const { groupSlug } = useParams();

	// Get all visible features
	const visibleFeatures = features.filter((f) => f.isVisible);

	// Find the first feature in the specified group slug
	// First look for features with matching group_slug
	let featuresInGroup = visibleFeatures.filter((f) => f.group_slug === groupSlug);

	// If no features found with group_slug, try finding by slugified group name
	if (featuresInGroup.length === 0) {
		featuresInGroup = visibleFeatures.filter(
			(f) => f.group && f.group.toLowerCase().replace(/\s+/g, '-') === groupSlug,
		);
	}

	// Sort by order and get the first feature
	const firstFeatureInGroup = featuresInGroup.sort((a, b) => a.order - b.order)[0];

	// If we found a feature, redirect to it, otherwise use the default
	const targetFeature = firstFeatureInGroup?.slug || defaultFeature;

	// Redirect to the first feature in the group
	return <Navigate to={`/${groupSlug}/${targetFeature}`} replace />;
};
