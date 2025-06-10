import { test, expect } from '../fixtures';
import { goToAdminPage } from '../utils';

/**
 * Test suite for the feature selection interface in ElasticPress settings.
 *
 * @module FeatureInterface
 */
test.describe('Feature Grouping and Persistence', () => {
	test('Renders group tabs and persists across reloads', async ({ loggedInPage }) => {
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
	});
});
