import { test, expect } from '../fixtures';
import {
	activatePlugin,
	deactivatePlugin,
	getSyncTimeout,
	goToAdminPage,
	setPerIndexCycle,
	wpCli,
} from '../utils';

const indexNames = process.env?.EP_INDEX_NAMES || [];

test.describe('Dashboard Sync', () => {
	async function canSeeIndexesNames(loggedInPage) {
		await goToAdminPage(loggedInPage, '/admin.php?page=elasticpress-health');
		const text = await loggedInPage.locator('.metabox-holder').textContent();
		for (const index of indexNames) {
			expect(text).toContain(index);
		}
	}

	async function resumeAndWait(page) {
		await page.getByRole('button', { name: 'Resume sync' }).click();
		await expect(page.locator('.ep-sync-progress strong')).toContainText('Sync complete', {
			timeout: getSyncTimeout(),
		});
	}

	test.beforeAll(async () => {
		await wpCli('plugin deactivate sync-error'); // Page var is not available yet in beforeAll
	});

	test.afterEach(async ({ loggedInPage }) => {
		if (test.info().status === 'failed') {
			await deactivatePlugin(loggedInPage, 'elasticpress', 'wpCli', 'network');
			await activatePlugin(loggedInPage, 'elasticpress', 'wpCli', 'singleSite');
			await wpCli('wp elasticpress clear-sync');
		}
	});

	test('Should only display a single sync option for the initial sync', async ({
		loggedInPage,
	}) => {
		// Reset settings and skip install
		await wpCli('elasticpress settings-reset --yes');
		await goToAdminPage(loggedInPage, '/admin.php?page=elasticpress');
		await loggedInPage.getByRole('link', { name: 'Skip Install' }).click();

		// Check if "Delete all data" checkbox doesn't exist initially
		await goToAdminPage(loggedInPage, '/admin.php?page=elasticpress-sync');
		await expect(loggedInPage.getByText('Delete all data')).not.toBeVisible();

		// Perform initial sync
		await loggedInPage.getByRole('button', { name: 'Start sync' }).click();

		// Check sync log
		await loggedInPage.getByRole('button', { name: 'Log' }).click();
		const syncMessages = loggedInPage.locator('.ep-sync-messages');
		await expect(syncMessages).toContainText('Mapping sent');
		await expect(syncMessages).toContainText('Sync complete');

		// Check if "Delete all data" checkbox appears after sync
		await expect(loggedInPage.getByText('Delete all data')).toBeVisible();
	});

	test('Can sync via Dashboard when activated in single site', async ({ loggedInPage }) => {
		await wpCli('wp elasticpress delete-index --yes');

		await goToAdminPage(loggedInPage, '/admin.php?page=elasticpress-health');
		await expect(loggedInPage.locator('.wrap')).toContainText(
			'We could not find any data for your Elasticsearch indices.',
		);

		await goToAdminPage(loggedInPage, '/admin.php?page=elasticpress-sync');
		await loggedInPage.getByRole('button', { name: 'Start sync' }).click();
		await expect(loggedInPage.locator('.ep-sync-progress strong')).toContainText(
			'Sync complete',
			{
				timeout: getSyncTimeout(),
			},
		);

		await goToAdminPage(loggedInPage, '/admin.php?page=elasticpress-health');
		await expect(loggedInPage.locator('.wrap')).not.toContainText(
			'We could not find any data for your Elasticsearch indices.',
		);

		await canSeeIndexesNames(loggedInPage);
	});

	test('Can sync via Dashboard when activated in multisite', async ({ loggedInPage }) => {
		await activatePlugin(loggedInPage, 'elasticpress', 'wpCli', 'network');

		// Sync and remove, so EP doesn't think it is a fresh install
		await wpCli('wp elasticpress sync --setup --yes');
		await wpCli('wp elasticpress delete-index --yes --network-wide');

		await goToAdminPage(loggedInPage, 'network/admin.php?page=elasticpress-health');
		await expect(loggedInPage.locator('.wrap')).toContainText(
			'We could not find any data for your Elasticsearch indices.',
		);

		await goToAdminPage(loggedInPage, 'network/admin.php?page=elasticpress-sync');
		await loggedInPage.getByRole('button', { name: 'Start sync' }).click();
		await expect(loggedInPage.locator('.ep-sync-progress strong')).toContainText(
			'Sync complete',
			{
				timeout: getSyncTimeout(),
			},
		);

		await goToAdminPage(loggedInPage, 'network/admin.php?page=elasticpress-health');
		await expect(loggedInPage.locator('.wrap')).not.toContainText(
			'We could not find any data for your Elasticsearch indices.',
		);

		const wpCliResponse = await wpCli('elasticpress get-indices');
		const indexes = JSON.parse(wpCliResponse);
		await goToAdminPage(loggedInPage, 'network/admin.php?page=elasticpress-health');
		const text = await loggedInPage.locator('.metabox-holder').textContent();
		for (const index of indexes) {
			expect(text).toContain(index);
		}

		await deactivatePlugin(loggedInPage, 'elasticpress', 'wpCli', 'network');
		await activatePlugin(loggedInPage, 'elasticpress', 'wpCli', 'singleSite');
	});

	test('Can pause the dashboard sync, can not activate a feature during sync nor perform a sync via WP-CLI', async ({
		loggedInPage,
	}) => {
		await setPerIndexCycle(20);

		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress-sync');

		// Start sync via dashboard and pause it
		const responsePromise = loggedInPage.waitForResponse('**/wp-json/elasticpress/v1/sync*');
		await loggedInPage.getByRole('button', { name: 'Start sync' }).click();
		const response = await responsePromise;
		await expect(response.status()).toBe(200);
		await loggedInPage.getByRole('button', { name: 'Pause sync' }).click();

		// Can not activate a feature
		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress');
		await expect(loggedInPage.getByRole('button', { name: 'Save changes' })).toBeDisabled();

		// Can not start a sync via WP-CLI
		const wpCliResponse = await wpCli('wp elasticpress sync', true);
		await expect(wpCliResponse).toContain('An index is already occurring');

		// Check if it is paused
		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress-sync');
		await expect(loggedInPage.getByRole('button', { name: 'Resume sync' })).toBeVisible();
		await expect(loggedInPage.locator('.ep-sync-progress strong')).toContainText('Sync paused');

		await resumeAndWait(loggedInPage);
		await canSeeIndexesNames(loggedInPage);

		// Features should be accessible again
		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress');
		await expect(loggedInPage.getByRole('button', { name: 'Save changes' })).not.toBeDisabled();

		await setPerIndexCycle();
	});

	test('Should only display a single sync option if index is deleted', async ({
		loggedInPage,
	}) => {
		await wpCli('wp elasticpress delete-index --yes');

		// Check if "Delete all data" checkbox doesn't exist when index is missing
		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress-sync');
		await expect(loggedInPage.getByText('Delete all data')).not.toBeVisible();

		// Send mapping
		await wpCli('wp elasticpress put-mapping');

		// Check if "Delete all data" checkbox appears after mapping is sent
		await loggedInPage.reload();
		await expect(loggedInPage.getByText('Delete all data')).toBeVisible();
	});

	test('Should display a list of error types when errors occur during sync', async ({
		loggedInPage,
	}) => {
		// Activate error plugin and check initial state
		await activatePlugin(loggedInPage, 'sync-error', 'wpCli');
		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress-sync');

		await loggedInPage.getByRole('button', { name: 'Log' }).click();
		await loggedInPage.getByText('Errors (0)').click(); // Playwright is not finding the button with the text "Errors" with getByRole
		await expect(loggedInPage.locator('.ep-sync-errors')).toContainText(
			'No errors found in the log.',
		);

		// Start sync and check for errors
		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress-sync');
		await loggedInPage.getByRole('button', { name: 'Start sync' }).click();
		await expect(loggedInPage.locator('.ep-sync-errors__table')).toBeVisible();
		await expect(loggedInPage.locator('.ep-sync-errors tr')).toHaveCount(2);
		await expect(loggedInPage.locator('.ep-sync-errors tr').locator('nth=1')).toContainText(
			'Limit of total fields [???] in index [???] has been exceeded',
		);
		await expect(loggedInPage.locator('.ep-sync-errors tr').locator('nth=1')).not.toContainText(
			'Number of posts index errors',
		);

		// Deactivate error plugin and verify no errors
		await deactivatePlugin(loggedInPage, 'sync-error', 'wpCli');
		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress-sync');

		await loggedInPage.getByRole('button', { name: 'Start sync' }).click();
		await expect(loggedInPage.locator('.ep-sync-progress strong')).toContainText(
			'Sync complete',
			{
				timeout: getSyncTimeout(),
			},
		);
		await loggedInPage.getByRole('button', { name: 'Log' }).click();
		await loggedInPage.getByText('Errors (0)').click();
		await expect(loggedInPage.locator('.ep-sync-errors')).toContainText(
			'No errors found in the log.',
		);
	});
});
