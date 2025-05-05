import { test, expect } from '../../fixtures';
import {
	activatePlugin,
	deactivatePlugin,
	getSyncTimeout,
	goToAdminPage,
	setPerIndexCycle,
	wpCli,
	refreshIndex,
	publishPost,
	setPostPassword,
} from '../../utils';

test.describe('Post Indexable', () => {
	test('Can conditionally update posts when a term is edited', async ({ loggedInPage }) => {
		/**
		 * At this point, using the default content:
		 * - the `Classic` (ID 29) term has 36 posts
		 * - the `Block` (ID 54) term has 7 posts
		 * Important: There is no post with both categories, as that would skew results.
		 */

		// Make sure post categories are searchable
		await goToAdminPage(loggedInPage, '/admin.php?page=elasticpress-weighting');
		const apiRequestPromise = loggedInPage.waitForResponse(
			'**/wp-json/elasticpress/v1/weighting*',
		);

		const postsPanel = loggedInPage
			.locator('.components-panel')
			.filter({ has: loggedInPage.locator('h2', { hasText: 'Posts' }) });
		await postsPanel
			.locator('fieldset')
			.filter({ has: loggedInPage.locator('legend', { hasText: 'Categories' }) })
			.locator('input[type="checkbox"]')
			.check();

		await loggedInPage.getByRole('button', { name: 'Save changes' }).click();
		await apiRequestPromise;

		await setPerIndexCycle();
		await goToAdminPage(loggedInPage, '/edit-tags.php?taxonomy=category');
		await expect(
			loggedInPage.locator('div[data-ep-notice="too_many_posts_on_term"]'),
		).not.toBeVisible();

		await setPerIndexCycle(35);
		await goToAdminPage(
			loggedInPage,
			'/edit-tags.php?taxonomy=category&orderby=count&order=desc',
		);
		await expect(
			loggedInPage.locator('div[data-ep-notice="too_many_posts_on_term"]'),
		).toBeVisible();

		// Change the `Classic` term, should not index
		await goToAdminPage(loggedInPage, '/term.php?taxonomy=category&tag_ID=29');
		await expect(
			loggedInPage.locator('div[data-ep-notice="edited_single_term"]'),
		).toBeVisible();
		await loggedInPage.locator('#name').fill('totallydifferenttermname');
		await loggedInPage.locator('input.button-primary').click();

		// Change the `Block` term, should index
		await goToAdminPage(loggedInPage, '/term.php?taxonomy=category&tag_ID=20');
		await expect(
			loggedInPage.locator('div[data-ep-notice="edited_single_term"]'),
		).not.toBeVisible();
		await loggedInPage.locator('#name').fill('b10ck');
		await loggedInPage.locator('input.button-primary').click();

		// Make sure the changes are processed by ES
		await refreshIndex('post');

		await loggedInPage.goto('/?s=totallydifferenttermname');
		await expect(loggedInPage.locator('.hentry')).not.toBeVisible();

		await loggedInPage.goto('/?s=b10ck');
		const count = await loggedInPage.locator('.hentry').count();
		expect(count).toBeGreaterThanOrEqual(1);
		await expect(
			loggedInPage
				.locator('#debug-menu-target-EP_Debug_Bar_ElasticPress .ep-query-debug')
				.filter({ hasText: 'Main query' }),
		).toBeAttached();

		// Restore
		await goToAdminPage(loggedInPage, '/term.php?taxonomy=category&tag_ID=29');
		await loggedInPage.locator('#name').fill('Classic');
		await loggedInPage.locator('input.button-primary').click();

		await goToAdminPage(loggedInPage, '/term.php?taxonomy=category&tag_ID=20');
		await loggedInPage.locator('#name').fill('Block');
		await loggedInPage.locator('input.button-primary').click();

		await setPerIndexCycle();
	});

	test('Can delete posts if a password is added or removed', async ({ loggedInPage }) => {
		// If the post is published with a password, it should not be indexed at all
		await publishPost(
			loggedInPage,
			{
				title: 'Password Protected',
				password: 'password',
			},
			true,
		);

		await loggedInPage
			.locator('#debug-menu-target-EP_Debug_Bar_ElasticPress .ep-retrieve-es-document')
			.dispatchEvent('click');
		await expect(
			loggedInPage.locator('.ep-query-debug').filter({
				has: loggedInPage.locator('.ep-query-type', { hasText: 'Raw ES document' }),
			}),
		).toContainText('HTTP 404');

		// If the password is removed, the post should be indexed
		await loggedInPage.locator('#wp-admin-bar-edit a').click();
		await loggedInPage.waitForLoadState('domcontentloaded');
		await setPostPassword(loggedInPage, '');

		await loggedInPage.locator('#wp-admin-bar-view a').click();
		await loggedInPage
			.locator('#debug-menu-target-EP_Debug_Bar_ElasticPress .ep-retrieve-es-document')
			.dispatchEvent('click');
		await expect(
			loggedInPage.locator('.ep-query-debug').filter({
				has: loggedInPage.locator('.ep-query-type', { hasText: 'Raw ES document' }),
			}),
		).toContainText('HTTP 200');

		// If the password is re-added, it should be removed from the index
		await loggedInPage.locator('#wp-admin-bar-edit a').click();
		await loggedInPage.waitForLoadState('domcontentloaded');
		await setPostPassword(loggedInPage, 'password');

		await loggedInPage.locator('#wp-admin-bar-view a').click();
		await loggedInPage
			.locator('#debug-menu-target-EP_Debug_Bar_ElasticPress .ep-retrieve-es-document')
			.dispatchEvent('click');
		await expect(
			loggedInPage.locator('.ep-query-debug').filter({
				has: loggedInPage.locator('.ep-query-type', { hasText: 'Raw ES document' }),
			}),
		).toContainText('HTTP 404');
	});
});
