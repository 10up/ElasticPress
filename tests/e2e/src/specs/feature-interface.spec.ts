import { test, expect } from '../fixtures.js';
import {
	activatePlugin,
	goToAdminPage,
	wpCli,
	maybeDisableFeature,
	maybeEnableFeature,
	setDefaultFeatureSettings,
	deactivatePlugin,
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

	test.describe('Settings Schema Updates on Save', () => {
		test.afterEach(async ({ loggedInPage }) => {
			await deactivatePlugin(loggedInPage, 'simulate-setting-dependency', 'wpCli');
			await setDefaultFeatureSettings();
		});

		test('Feature settings schema updates without page refresh when dependency changes', async ({
			loggedInPage,
		}) => {
			await activatePlugin(loggedInPage, 'simulate-setting-dependency', 'wpCli');
			await maybeEnableFeature('test_conditional_settings');

			// Start with Post Search disabled so conditional options are hidden
			await maybeDisableFeature('search');

			// Navigate to the settings page
			await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress');
			await expect(loggedInPage.locator('.ep-settings-page form')).toBeVisible();
			await loggedInPage.waitForFunction(() => {
				return !!(window as any).epDashboard && !!(window as any).epDashboard.features;
			});

			// Navigate to the Test Conditional Settings panel
			await loggedInPage.getByRole('button', { name: 'Core Search' }).click();
			await loggedInPage.getByRole('button', { name: 'Test Conditional Settings' }).click();
			await expect(
				loggedInPage.locator('div[id*="test_conditional_settings-view"]'),
			).toBeVisible();
			await loggedInPage.waitForTimeout(500);

			// Verify only the base options are visible (Post Search is not active)
			await expect(loggedInPage.getByLabel('Basic')).toBeVisible();
			await expect(loggedInPage.getByLabel('Standard')).toBeVisible();
			await expect(
				loggedInPage.getByLabel('Advanced (requires Post Search)'),
			).not.toBeVisible();
			await expect(
				loggedInPage.getByLabel('Expert (requires Post Search)'),
			).not.toBeVisible();

			// Navigate to Post Search and enable it
			await loggedInPage.getByRole('button', { name: 'Post Search' }).click();
			await expect(loggedInPage.locator('div[id*="search-view"]')).toBeVisible();
			await loggedInPage.waitForTimeout(500);
			await loggedInPage.getByRole('checkbox', { name: 'Enable' }).setChecked(true);

			// Save and wait for the save operation to complete
			const saveButton = loggedInPage.getByRole('button', { name: 'Save changes' });
			await saveButton.click();
			await expect(
				loggedInPage.locator('.components-snackbar').filter({
					hasText: 'Feature settings saved',
				}),
			).toBeVisible({ timeout: 10000 });

			// Navigate back to the Test Conditional Settings (no page refresh)
			await loggedInPage.getByRole('button', { name: 'Test Conditional Settings' }).click();
			await expect(
				loggedInPage.locator('div[id*="test_conditional_settings-view"]'),
			).toBeVisible();
			await loggedInPage.waitForTimeout(500);

			// Verify the conditional options now appear
			await expect(loggedInPage.getByLabel('Basic')).toBeVisible();
			await expect(loggedInPage.getByLabel('Standard')).toBeVisible();
			await expect(loggedInPage.getByLabel('Advanced (requires Post Search)')).toBeVisible();
			await expect(loggedInPage.getByLabel('Expert (requires Post Search)')).toBeVisible();

			// Now disable Post Search and verify the options disappear
			await loggedInPage.getByRole('button', { name: 'Post Search' }).click();
			await expect(loggedInPage.locator('div[id*="search-view"]')).toBeVisible();
			await loggedInPage.waitForTimeout(500);
			await loggedInPage.getByRole('checkbox', { name: 'Enable' }).setChecked(false);

			// Save and wait for the save operation to complete
			await saveButton.click();
			await expect(
				loggedInPage.locator('.components-snackbar').filter({
					hasText: 'Feature settings saved',
				}),
			).toBeVisible({ timeout: 10000 });

			// Navigate back to the Test Conditional Settings (no page refresh)
			await loggedInPage.getByRole('button', { name: 'Test Conditional Settings' }).click();
			await expect(
				loggedInPage.locator('div[id*="test_conditional_settings-view"]'),
			).toBeVisible();
			await loggedInPage.waitForTimeout(500);

			// Verify the conditional options are gone again
			await expect(loggedInPage.getByLabel('Basic')).toBeVisible();
			await expect(loggedInPage.getByLabel('Standard')).toBeVisible();
			await expect(
				loggedInPage.getByLabel('Advanced (requires Post Search)'),
			).not.toBeVisible();
			await expect(
				loggedInPage.getByLabel('Expert (requires Post Search)'),
			).not.toBeVisible();
		});
	});
});
