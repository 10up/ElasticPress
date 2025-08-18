import { test, expect } from '../fixtures.js';
import { goToAdminPage } from '../utils.js';

test.describe('Status Report', { tag: '@group1' }, () => {
	test.beforeEach(async ({ loggedInPage }) => {
		await goToAdminPage(loggedInPage, '/admin.php?page=elasticpress-status-report');
	});

	test('should handle AJAX report generation and button states', async ({ loggedInPage }) => {
		// Check for notice component
		await expect(
			loggedInPage
				.locator('#indexable')
				.locator('xpath=../..')
				.locator('.components-notice__content'),
		).toBeVisible();

		// Verify buttons are not disabled by default
		await expect(loggedInPage.locator('#download-report')).not.toBeDisabled();
		await expect(loggedInPage.locator('#copy-report')).not.toBeDisabled();
		await expect(loggedInPage.locator('#generate-full-report')).not.toBeDisabled();

		// Click generate report button
		await loggedInPage.locator('#generate-full-report').click();

		// Verify generate button is disabled after click
		await expect(loggedInPage.locator('#generate-full-report')).toBeDisabled();

		// Verify notice is removed after report generation
		await expect(
			loggedInPage
				.locator('#indexable')
				.locator('xpath=../..')
				.locator('.components-notice__content'),
		).not.toBeVisible();

		// Verify button text changes
		await expect(loggedInPage.locator('#download-report')).toHaveText(
			'Download full status report',
		);
		await expect(loggedInPage.locator('#copy-report')).toHaveText(
			'Copy full status report to clipboard',
		);
	});
});
