import { test, expect } from '../fixtures.js';
import { goToAdminPage, openSyncLog, wpCli } from '../utils.js';

test.describe('Network sync target eligibility', { tag: '@group2' }, () => {
	test.describe.configure({ mode: 'serial', timeout: 120000 });
	let siteId: string;
	let mainSiteId: string;
	let pluginStatus: string;

	test.beforeAll(async () => {
		pluginStatus = await wpCli('plugin list --name=elasticpress --field=status');
		await wpCli('plugin activate elasticpress --network');
		mainSiteId = await wpCli('site list --field=blog_id --number=1');
		siteId = await wpCli(
			'site create --slug=ep-sync-eligibility --title="Sync eligibility target" --porcelain',
		);
		await wpCli('elasticpress sync --network-wide --indexables=post --yes');
	});

	test.afterEach(async () => {
		if (!siteId) {
			return;
		}

		await wpCli('elasticpress clear-sync');
		await wpCli(`site meta delete ${siteId} ep_indexable`, true);
		await wpCli(`site unarchive ${siteId}`);
		await wpCli(`site unspam ${siteId}`);
		await wpCli(`site activate ${siteId}`);
	});

	test.afterAll(async () => {
		if (siteId) {
			await wpCli(`site delete ${siteId} --yes`);
		}

		if (pluginStatus !== 'active-network') {
			await wpCli('plugin deactivate elasticpress --network');

			if (pluginStatus === 'active') {
				await wpCli('plugin activate elasticpress');
			}
		}
	});

	test('Syncs the selected site and retains its target after pause and reload', async ({
		loggedInPage,
	}) => {
		await goToAdminPage(loggedInPage, 'network/admin.php?page=elasticpress-sync');
		const sites = loggedInPage.getByRole('combobox', { name: 'Sites to sync' });
		await sites.selectOption(siteId);
		await loggedInPage.getByRole('button', { name: 'Start sync', exact: true }).click();
		await loggedInPage.getByRole('button', { name: 'Pause sync', exact: true }).click();
		await openSyncLog(loggedInPage);
		await expect(loggedInPage.locator('.ep-sync-messages')).toContainText('Sync paused');
		await loggedInPage.reload();
		await expect(sites).toHaveValue(siteId);
		await expect(sites).toBeDisabled();
		await loggedInPage.getByRole('button', { name: 'Resume sync', exact: true }).click();
		await openSyncLog(loggedInPage);
		await expect(loggedInPage.locator('.ep-sync-progress strong')).toHaveText('Sync complete', {
			timeout: 90000,
		});
		await expect(loggedInPage.locator('.ep-sync-messages')).toContainText(
			`Number of posts indexed on site ${siteId}:`,
		);
		await expect(loggedInPage.locator('.ep-sync-messages')).not.toContainText(
			`Number of posts indexed on site ${mainSiteId}:`,
		);
		await expect(sites).toBeEnabled();
		await sites.selectOption('');
		await expect(sites).toHaveValue('');
	});

	['disabled', 'archived', 'spam', 'deleted'].forEach((state) => {
		test(`Rejects a stale ${state} target and removes it from the selector`, async ({
			loggedInPage,
		}) => {
			await goToAdminPage(loggedInPage, 'network/admin.php?page=elasticpress-sync');
			const sites = loggedInPage.getByRole('combobox', { name: 'Sites to sync' });
			await sites.selectOption(siteId);

			if (state === 'disabled') {
				await wpCli(`site meta update ${siteId} ep_indexable no`);
			} else {
				const command = { archived: 'archive', spam: 'spam', deleted: 'deactivate' }[state];
				await wpCli(`site ${command} ${siteId}`);
			}

			const rejected = loggedInPage.waitForResponse(
				(response) =>
					response.url().includes('/elasticpress/v1/sync') && response.status() === 400,
			);
			await loggedInPage.getByRole('button', { name: 'Start sync', exact: true }).click();
			const response = await rejected;
			expect((await response.json()).code).toBe('ep_invalid_sync_site');
			await expect(loggedInPage.locator('.ep-sync-messages')).toContainText(
				'The selected site no longer exists or is not eligible for indexing.',
			);
			await expect(
				loggedInPage.getByRole('button', { name: 'Start sync', exact: true }),
			).toBeVisible();
			await loggedInPage.reload();
			await expect(sites.locator(`option[value="${siteId}"]`)).toHaveCount(0);
			await expect(
				loggedInPage.getByRole('button', { name: 'Start sync', exact: true }),
			).toBeVisible();
			await expect(
				loggedInPage.getByRole('button', { name: 'Resume sync', exact: true }),
			).toHaveCount(0);
		});
	});

	test('Keeps the existing all-eligible-sites sync behavior', async ({ loggedInPage }) => {
		await goToAdminPage(loggedInPage, 'network/admin.php?page=elasticpress-sync');
		await expect(loggedInPage.getByRole('combobox', { name: 'Sites to sync' })).toHaveValue('');
		await loggedInPage.getByRole('button', { name: 'Start sync', exact: true }).click();
		await openSyncLog(loggedInPage);
		await expect(loggedInPage.locator('.ep-sync-progress strong')).toHaveText('Sync complete', {
			timeout: 90000,
		});
		const log = loggedInPage.locator('.ep-sync-messages');
		await expect(log).toContainText(`Number of posts indexed on site ${mainSiteId}:`);
		await expect(log).toContainText(`Number of posts indexed on site ${siteId}:`);
	});

	test('Does not show network targets when activated for one site', async ({ loggedInPage }) => {
		try {
			await wpCli('plugin deactivate elasticpress --network');
			await wpCli('plugin activate elasticpress');
			await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress-sync');
			const skipInstall = loggedInPage.getByRole('link', { name: 'Skip Install' });

			if (await skipInstall.isVisible()) {
				await skipInstall.click();
				await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress-sync');
			}

			await expect(
				loggedInPage.getByRole('button', { name: 'Start sync', exact: true }),
			).toBeVisible();
			await expect(loggedInPage.getByRole('combobox', { name: 'Sites to sync' })).toHaveCount(
				0,
			);
		} finally {
			await wpCli('plugin deactivate elasticpress');
			await wpCli('plugin activate elasticpress --network');
		}
	});
});
