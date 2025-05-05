import { test, expect } from '../fixtures';
import {
	activatePlugin,
	deactivatePlugin,
	goToAdminPage,
	isEpIo,
	publishPost,
	wpCli,
} from '../utils';

test.describe('WordPress can perform standard ElasticPress actions', () => {
	test('Can see the settings page link in WordPress Dashboard', async ({ loggedInPage }) => {
		await activatePlugin(loggedInPage, 'elasticpress');

		await expect(
			loggedInPage.locator('.toplevel_page_elasticpress .wp-menu-name'),
		).toContainText('ElasticPress');
	});

	test('Can see quick setup message after enabling the plugin for the first time', async ({
		loggedInPage,
	}) => {
		await activatePlugin(loggedInPage, 'fake-new-activation', 'wpCli');

		await goToAdminPage(loggedInPage, '');
		await expect
			.soft(loggedInPage.locator('.wrap'))
			.toContainText('ElasticPress is almost ready to go.');

		await deactivatePlugin(loggedInPage, 'fake-new-activation', 'wpCli');
	});

	test('Can select features if user is setting up plugin for the first time', async ({
		loggedInPage,
	}) => {
		await activatePlugin(loggedInPage, 'fake-new-activation', 'wpCli');

		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress');
		await expect.soft(loggedInPage.locator('.setup-button')).toContainText('Save Features');

		await deactivatePlugin(loggedInPage, 'fake-new-activation', 'wpCli');
	});

	test('Can sync post data and meta details in Elasticsearch if user creates/updates a published post', async ({
		loggedInPage,
	}) => {
		const postTitle = 'Test ElasticPress 1';

		await publishPost(loggedInPage, {
			title: postTitle,
		});

		await loggedInPage.reload();
		await expect(loggedInPage.locator('#wp-admin-bar-ep-doc-status')).toContainText(
			'Content in sync',
		);

		await loggedInPage.goto('/?s=Test+ElasticPress+1');
		await expect(
			loggedInPage.locator('.site-content article h2').filter({ hasText: postTitle }).first(),
		).toBeVisible();
	});

	test('Can see a warning in the dashboard if user activates plugin with an Elasticsearch version before or after min/max requirements', async ({
		loggedInPage,
	}) => {
		if (isEpIo()) {
			return;
		}

		await activatePlugin(loggedInPage, 'unsupported-elasticsearch-version', 'wpCli');

		await goToAdminPage(loggedInPage, 'plugins.php');
		await expect
			.soft(loggedInPage.locator('[data-ep-notice="es_above_compat"].notice'))
			.toContainText('ElasticPress may or may not work properly.');

		await deactivatePlugin(loggedInPage, 'unsupported-elasticsearch-version', 'wpCli');
	});

	test('Can see a warning in the dashboard if using other software than Elasticsearch', async ({
		loggedInPage,
	}) => {
		if (isEpIo()) {
			return;
		}

		await activatePlugin(loggedInPage, 'unsupported-server-software', 'wpCli');

		await goToAdminPage(loggedInPage, 'plugins.php');
		await expect
			.soft(loggedInPage.locator('[data-ep-notice="different_server_type"].notice'))
			.toContainText('Your server software is not supported.');

		await deactivatePlugin(loggedInPage, 'unsupported-server-software', 'wpCli');
	});

	test('Can see Sync and Settings buttons on Settings Page', async ({ loggedInPage }) => {
		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress-settings');
		await expect(loggedInPage.locator('.dashicons.start-sync')).toHaveAttribute(
			'title',
			'Sync Page',
		);
		await expect(loggedInPage.locator('.dashicons.dashicons-admin-generic')).toHaveAttribute(
			'title',
			'Settings Page',
		);
	});

	test('Cannot save settings while a sync is in progress', async ({ loggedInPage }) => {
		// Make sure the sync is not in progress yet
		await wpCli('eval "delete_option( \'ep_index_meta\' );"');

		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress');
		await wpCli("eval \"update_option( 'ep_index_meta', [ 'indexing' => true ] );\"");

		await loggedInPage.click('button:has-text("Save changes")');
		await expect
			.soft(loggedInPage.locator('.components-snackbar'))
			.toContainText('Cannot save settings');

		await wpCli('eval "delete_option( \'ep_index_meta\' );"');
	});

	test('Can see ElasticPress Last Sync Accordion', async ({ loggedInPage }) => {
		await goToAdminPage(loggedInPage, 'site-health.php?tab=debug');
		await loggedInPage.click('[aria-controls="health-check-accordion-block-ep-last-sync"]');

		const syncTable = loggedInPage.locator(
			'#health-check-accordion-block-ep-last-sync .health-check-table',
		);
		const selector = process.env.WP_VERSION === '6.2' ? 'td' : 'th';
		const cells = [
			'Method',
			'Full Sync',
			'Start Date Time',
			'End Date Time',
			'Total Time',
			'Total',
			'Synced',
			'Skipped',
			'Failed',
			'Errors',
		];
		cells.forEach(async (cell, index) => {
			await expect(
				syncTable.locator(`tr:nth-child(${index + 1}) ${selector}`).first(),
			).toContainText(cell);
		});
	});
});
