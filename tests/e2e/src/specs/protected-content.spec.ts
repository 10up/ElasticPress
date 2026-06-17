import { test, expect } from '../fixtures.js';
import {
	wpCli,
	refreshIndex,
	maybeEnableFeature,
	publishPost,
	createUser,
	goToAdminPage,
	createAutosavePost,
	logout,
	maybeDisableFeature,
} from '../utils.js';

test.describe('Protected Content Feature', { tag: '@group1' }, () => {
	const deleteOldDraftsAndSync = async () => {
		const wpCliResponse = await wpCli('post list --post_status=draft --format=ids');
		const ids = wpCliResponse.toString();
		if (ids) {
			await wpCli(`post delete ${ids} --force`);
		}

		await wpCli('elasticpress sync --setup --yes');
	};

	test('Can turn the feature on', async ({ loggedInPage }) => {
		maybeDisableFeature('protected_content');
		await loggedInPage.goto('/wp-admin/admin.php?page=elasticpress');

		await loggedInPage.getByRole('button', { name: 'Indexing Options' }).click();
		await loggedInPage.getByRole('button', { name: 'Protected Content' }).click();
		await loggedInPage.getByLabel('Enable').check();
		loggedInPage.on('dialog', (dialog) => dialog.accept());
		await loggedInPage.getByRole('button', { name: 'Save and sync now' }).click();

		await loggedInPage.getByRole('button', { name: 'Log' }).click();

		// Wait for sync messages
		const syncMessages = loggedInPage.locator('.ep-sync-messages');
		await expect(syncMessages).toContainText('Mapping sent');
		await expect(syncMessages).toContainText('Sync complete');

		// Verify feature is enabled via WP-CLI
		const wpCliResponse = await wpCli('elasticpress list-features');
		expect(wpCliResponse.toString()).toContain('protected_content');
	});

	test('Can use Elasticsearch in the Posts List Admin Screen', async ({ loggedInPage }) => {
		await maybeEnableFeature('protected_content');
		await goToAdminPage(loggedInPage, 'edit.php');
		await expect(
			loggedInPage.locator('#debug-menu-target-EP_Debug_Bar_ElasticPress'),
		).toContainText('Time Taken');
	});

	test('Can use Elasticsearch in the Draft Posts List Admin Screen', async ({ loggedInPage }) => {
		await maybeEnableFeature('protected_content');

		// Delete previous drafts
		await deleteOldDraftsAndSync();

		await publishPost(loggedInPage, {
			title: 'Test ElasticPress Draft',
			status: 'draft',
		});

		await goToAdminPage(loggedInPage, '/edit.php?post_status=draft&post_type=post');
		const results = JSON.parse(
			(await loggedInPage.locator('.query-results').textContent()) ?? '{}',
		);
		await expect(results.hits.total.value).toBe(1);
	});

	test('Can use Elasticsearch in the Media Library Admin Screen', async ({ loggedInPage }) => {
		await maybeEnableFeature('protected_content');
		await goToAdminPage(loggedInPage, 'upload.php?mode=list');
		await expect(
			loggedInPage.locator('#debug-menu-target-EP_Debug_Bar_ElasticPress'),
		).toContainText('Time Taken');

		// Check there are some rows in the list
		const initialRows = loggedInPage.locator('#the-list tr');
		await expect(initialRows.count()).resolves.toBeGreaterThan(0);

		await loggedInPage.locator('#media-search-input').fill('woocommerce-placeholder');
		await loggedInPage.locator('#media-search-input').press('Enter');

		// Wait for search results and verify exactly 1 row
		await loggedInPage.waitForLoadState('networkidle');
		const rows = loggedInPage.locator('#the-list tr');
		await expect(rows).toHaveCount(1);
	});

	test('Can sync autosaved drafts', async ({ loggedInPage }) => {
		await maybeEnableFeature('protected_content');

		// Delete previous drafts
		await deleteOldDraftsAndSync();

		// Create an autosave post
		await createAutosavePost(loggedInPage, {
			title: 'Autosave Test',
			content: 'Test content',
		});

		await goToAdminPage(loggedInPage, 'edit.php?post_status=draft&post_type=post');
		const results = JSON.parse(
			(await loggedInPage.locator('.query-results').textContent()) ?? '{}',
		);
		await expect(results.hits.total.value).toBe(1);
	});

	test('Can search password protected post', async ({ loggedInPage, page }) => {
		await maybeEnableFeature('protected_content');

		await publishPost(loggedInPage, {
			title: 'Password Protected',
			password: 'password',
		});

		// Real-time sync of newly-created posts is unreliable on the CI runners
		// (WP 7.0), so explicitly re-index via the bulk path before searching.
		await wpCli('elasticpress sync');
		await refreshIndex('post');

		// Admin can see post on front and search page
		await loggedInPage.goto('/');
		await expect(
			loggedInPage
				.locator('.site-content article h2', { hasText: 'Password Protected' })
				.first(),
		).toBeVisible();
		await loggedInPage.goto('/?s=Password+Protected');
		await expect(
			loggedInPage
				.locator('.site-content article h2', { hasText: 'Password Protected' })
				.first(),
		).toBeVisible();

		// Logout user can see the post on front but not on search page
		await logout(page);
		await page.goto('/');
		await expect(
			page.locator('.site-content article h2', { hasText: 'Password Protected' }).first(),
		).toBeVisible();
		await page.goto('/?s=Password+Protected');
		await expect(page.locator('.site-content article h2')).not.toBeVisible();

		// Create and login as subscriber
		await createUser(loggedInPage, {
			username: 'subscriber',
			password: 'password',
			email: 'subscriber@example.com',
			role: 'subscriber',
			login: true,
		});

		// Subscriber can see the post on front and on search page
		await page.goto('/');
		await expect(
			page.locator('.site-content article h2', { hasText: 'Password Protected' }).first(),
		).toBeVisible();
		await page.goto('/?s=Password+Protected');
		await expect(
			page.locator('.site-content article h2', { hasText: 'Password Protected' }).first(),
		).toBeVisible();
	});
});
