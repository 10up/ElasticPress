import { test, expect } from '../fixtures.js';
import {
	activatePlugin,
	goToAdminPage,
	wpCli,
	maybeDisableFeature,
	maybeEnableFeature,
	setDefaultFeatureSettings,
	deactivatePlugin,
	updateFeatures,
	isEpIo,
} from '../utils.js';

/**
 * Test suite for the feature selection interface in ElasticPress settings.
 *
 * @module FeatureInterface
 */
test.describe('Features Interface', { tag: '@group1' }, () => {
	test('Renders group tabs, persists across reloads, and supports field dependency', async ({
		loggedInPage,
	}) => {
		// Visit the ElasticPress settings page in the WordPress admin
		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress');

		// Ensure the settings form is visible
		await expect(loggedInPage.locator('.ep-settings-page form')).toBeVisible();

		// Find and click the "Live Search" feature group tab button
		const liveSearchButton = loggedInPage.locator('button[id*="Live Search"]');
		await expect(liveSearchButton).toBeVisible();
		await expect(liveSearchButton).not.toBeDisabled();
		await liveSearchButton.click();

		// Assert that the "Live Search" panel is open and visible
		const panelSelector = 'div[id*="Live Search-view"]:has(.is-opened)';
		await expect(loggedInPage.locator(panelSelector)).toBeVisible();

		// Verifies that the group and feature tabs are clickable and persist their selections
		// Click the "Live Search" button again to ensure it's active
		await liveSearchButton.click();

		// Click the autosuggest feature tab
		const autosuggestButton = loggedInPage.locator('button[id*="autosuggest"]');
		await autosuggestButton.click();

		// Verify the autosuggest feature is active
		await expect(loggedInPage.locator('div[id*="autosuggest-view"]')).toBeVisible();

		// Reload the page to test persistence
		await loggedInPage.reload();
		await loggedInPage.waitForLoadState('domcontentloaded');

		// Wait for UI to load completely
		await expect(loggedInPage.locator('.ep-settings-page form')).toBeVisible();

		// Verify group selection persisted
		await expect(loggedInPage.locator('div[id*="Live Search-view"]')).toBeVisible();

		// Verify feature selection persisted
		await expect(loggedInPage.locator('div[id*="autosuggest-view"]')).toBeVisible();

		// Wait for epDashboard and features to be available
		await loggedInPage.waitForFunction(() => {
			return !!(window as any).epDashboard && !!(window as any).epDashboard.features;
		});

		// Push new fields into settingsSchema
		await loggedInPage.evaluate(() => {
			(window as any).epDashboard.features[0].settingsSchema.push({
				default: '1',
				key: 'test_field',
				label: 'Testing Field 1',
				options: [
					{ label: 'Option A', value: '0' },
					{ label: 'Option B', value: '1' },
				],
				type: 'radio',
			});
			(window as any).epDashboard.features[0].settingsSchema.push({
				default: '1',
				key: 'test_field_2',
				label: 'Testing Field 2',
				options: [
					{ label: 'Option A', value: '0' },
					{ label: 'Option B', value: '1' },
				],
				type: 'radio',
				requires_fields: {
					conditions: {
						test_field: '0',
					},
				},
			});
		});

		// Toggle Re-Render
		await loggedInPage.getByRole('button', { name: 'Live Search' }).click();
		await loggedInPage.getByRole('button', { name: 'Core Search' }).click();

		await expect(
			loggedInPage.locator('.ep-dashboard-control', {
				hasText: 'Testing Field 2',
			}),
		).not.toBeVisible();

		const testField = loggedInPage.locator('.ep-dashboard-control', {
			hasText: 'Testing Field 1',
		});

		// inside of testField, find the input with the label "Option A" and click it
		await testField.locator('input[value="0"]').click();

		// now, Testing Field 2 should be visible
		await expect(
			loggedInPage.locator('.ep-dashboard-control', {
				hasText: 'Testing Field 2',
			}),
		).toBeVisible();
	});

	test.describe('Multiple Required Features', () => {
		test.afterAll(async () => {
			await wpCli('plugin deactivate multiple-required-features');
		});

		test('supports multiple required features', async ({ loggedInPage }) => {
			await wpCli('plugin activate multiple-required-features');
			await maybeDisableFeature('facets');
			await maybeDisableFeature('documents');

			await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress');

			await loggedInPage.getByRole('button', { name: 'Core Search' }).click();
			await loggedInPage.getByRole('button', { name: 'Related Posts' }).click();

			await expect(
				loggedInPage.locator('.components-notice.is-error').filter({
					hasText:
						'The Filters and Documents features must be enabled to use this feature.',
				}),
			).toBeVisible();
		});
	});

	test('Temporarily disable a feature', async ({ loggedInPage }) => {
		// Regular workflow: Autosuggest is enabled
		await deactivatePlugin(loggedInPage, 'temporarily-disable-autosuggest', 'wpCli');
		await setDefaultFeatureSettings();

		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress');
		await loggedInPage.getByRole('button', { name: 'Live Search' }).click();
		await loggedInPage.getByRole('button', { name: 'Autosuggest' }).click();

		await expect(loggedInPage.getByRole('checkbox', { name: 'Enable' })).toBeEnabled();
		await expect(loggedInPage.getByRole('checkbox', { name: 'Enable' })).toBeChecked();

		// Temporarily disable Autosuggest
		await activatePlugin(loggedInPage, 'temporarily-disable-autosuggest', 'wpCli');
		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress');
		await loggedInPage.getByRole('button', { name: 'Live Search' }).click();
		await loggedInPage.getByRole('button', { name: 'Autosuggest' }).click();

		await expect(loggedInPage.getByRole('checkbox', { name: 'Enable' })).toBeDisabled();
		await expect(loggedInPage.getByRole('checkbox', { name: 'Enable' })).not.toBeChecked();
		await expect(
			loggedInPage.getByRole('checkbox', { name: 'Trigger Google Analytics' }),
		).toBeDisabled();

		// If the test plugin is disabled, Autosuggest should be enabled again
		await deactivatePlugin(loggedInPage, 'temporarily-disable-autosuggest', 'wpCli');
		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress');
		await loggedInPage.getByRole('button', { name: 'Live Search' }).click();
		await loggedInPage.getByRole('button', { name: 'Autosuggest' }).click();

		await expect(loggedInPage.getByRole('checkbox', { name: 'Enable' })).toBeEnabled();
		await expect(loggedInPage.getByRole('checkbox', { name: 'Enable' })).toBeChecked();
		await expect(
			loggedInPage.getByRole('checkbox', { name: 'Trigger Google Analytics' }),
		).not.toBeDisabled();
	});

	test.describe('Feature Dependency Updates', () => {
		test.afterEach(async ({ loggedInPage }) => {
			await deactivatePlugin(loggedInPage, 'simulate-instant-results-conflict', 'wpCli');
		});

		test('Feature dependencies update immediately and after save', async ({ loggedInPage }) => {
			// Track initial state for cleanup
			const featuresList = await wpCli('elasticpress list-features', true);
			const instantResultsWasEnabled = featuresList?.toString().includes('instant-results');
			const didYouMeanWasEnabled = featuresList?.toString().includes('did-you-mean');

			// Check if proxy plugin needs to be activated
			const needsProxy = !isEpIo();

			// Setup: Activate proxy plugin first if needed (before enabling features)
			if (needsProxy) {
				await wpCli('plugin activate elasticpress-proxy', true);
			}

			// Enable features and activate conflict plugin
			await maybeEnableFeature('instant-results');
			await maybeEnableFeature('did-you-mean');
			await activatePlugin(loggedInPage, 'simulate-instant-results-conflict', 'wpCli');

			await updateFeatures('did-you-mean', {
				active: true,
				conflicting_setting: false,
			});

			// Verify features are actually enabled via WP-CLI
			const featuresCheck = await wpCli('elasticpress list-features', true);
			if (!featuresCheck?.toString().includes('instant-results')) {
				throw new Error('Instant Results feature failed to activate');
			}

			// Navigate to settings and verify Instant Results is enabled initially
			await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress');

			// Wait for settings form to be fully loaded
			await expect(loggedInPage.locator('.ep-settings-page form')).toBeVisible();

			// Wait for epDashboard to be available (React app fully initialized)
			await loggedInPage.waitForFunction(() => {
				return !!(window as any).epDashboard && !!(window as any).epDashboard.features;
			});

			await loggedInPage.getByRole('button', { name: 'Live Search' }).click();
			await loggedInPage.getByRole('button', { name: 'Instant Results' }).click();

			// Wait for the feature panel to be visible before checking checkbox state
			await expect(loggedInPage.locator('div[id*="instant-results-view"]')).toBeVisible();

			// Wait for React to finish rendering and state to stabilize
			await loggedInPage.waitForTimeout(500);

			const enableCheckbox = loggedInPage.getByRole('checkbox', {
				name: 'Enable',
			});

			// Wait for checkbox to be fully initialized and in the correct state
			await expect(enableCheckbox).toBeEnabled({ timeout: 10000 });
			await expect(enableCheckbox).toBeChecked({ timeout: 10000 });

			// Enable conflicting setting in Did You Mean
			await loggedInPage.getByRole('button', { name: 'Core Search' }).click();
			await loggedInPage.getByRole('button', { name: 'Did You Mean' }).click();

			// Wait for Did You Mean panel to be visible
			await expect(loggedInPage.locator('div[id*="did-you-mean-view"]')).toBeVisible();

			await loggedInPage.getByLabel('Conflicting Setting').setChecked(true);

			// Save changes and wait for completion
			const saveButton = loggedInPage.getByRole('button', {
				name: 'Save changes',
			});
			await saveButton.click();

			// Wait for the save operation to complete by checking for success notice
			await expect(
				loggedInPage.locator('.components-snackbar').filter({
					hasText: 'Feature settings saved',
				}),
			).toBeVisible({ timeout: 10000 });

			// Verify Instant Results is now disabled with error message
			await loggedInPage.getByRole('button', { name: 'Live Search' }).click();
			await loggedInPage.getByRole('button', { name: 'Instant Results' }).click();

			// Wait for Instant Results panel to be visible again
			await expect(loggedInPage.locator('div[id*="instant-results-view"]')).toBeVisible();

			// Wait for React to finish rendering and state to stabilize
			await loggedInPage.waitForTimeout(500);

			const disabledCheckbox = loggedInPage.getByRole('checkbox', {
				name: 'Enable',
			});
			await expect(disabledCheckbox).toBeDisabled({ timeout: 10000 });
			await expect(disabledCheckbox).not.toBeChecked({ timeout: 10000 });
			await expect(
				loggedInPage.locator('.components-notice.is-error').filter({
					hasText:
						'This feature is temporarily disabled because it is incompatible with the Conflicting Setting enabled in Did You Mean.',
				}),
			).toBeVisible();

			// Cleanup: Restore original state
			await updateFeatures('did-you-mean', {
				active: didYouMeanWasEnabled,
				conflicting_setting: false,
			});
			if (!instantResultsWasEnabled) {
				await maybeDisableFeature('instant-results');
			}
			if (!didYouMeanWasEnabled) {
				await maybeDisableFeature('did-you-mean');
			}

			// Deactivate proxy plugin if we activated it
			if (needsProxy) {
				await wpCli('plugin deactivate elasticpress-proxy', true);
			}
		});
	});
});
