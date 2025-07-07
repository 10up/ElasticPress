import { test, expect } from '../../fixtures';
import {
	goToAdminPage,
	wpCli,
	login,
	maybeEnableFeature,
	maybeDisableFeature,
	createTerm,
} from '../../utils';

const tags = ['Far From Home', 'No Way Home', 'The Most Fun Thing'];

test.describe('Terms Feature', { tag: '@slow' }, () => {
	test.beforeAll(async () => {
		await wpCli('wp plugin activate show-comments-and-terms');
		// Delete all tags
		await Promise.all(
			tags.map((tag) =>
				wpCli(
					`wp term delete post_tag $(wp term get post_tag -s='${tag}' --field=ids)`,
					true,
				),
			),
		);
	});

	test('Can turn the feature on', async ({ loggedInPage }) => {
		await login(loggedInPage);
		await maybeDisableFeature('terms');
		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress');

		await loggedInPage.getByRole('button', { name: 'Other' }).click();
		await loggedInPage.getByRole('button', { name: 'Terms' }).click();
		await loggedInPage.getByRole('checkbox', { name: 'Enable' }).click();
		loggedInPage.on('dialog', (dialog) => dialog.accept());
		await loggedInPage.getByRole('button', { name: 'Save and sync now' }).click();

		await loggedInPage.getByRole('button', { name: 'Log' }).click();
		const syncMessages = loggedInPage.locator('.ep-sync-messages');
		await expect(syncMessages).toContainText('Mapping sent');
		await expect(syncMessages).toContainText('Sync complete');

		const listFeaturesResult = await wpCli('elasticpress list-features', true);
		expect(listFeaturesResult.toString()).toContain('terms');
	});

	test('Can search a term in the admin dashboard using Elasticsearch', async ({
		loggedInPage,
	}) => {
		await login(loggedInPage);
		await maybeEnableFeature('terms');

		const searchTerm = 'search term';
		await createTerm(loggedInPage, { taxonomy: 'post_tag', name: searchTerm });

		await loggedInPage.getByLabel('Search Tags').fill(searchTerm);
		await loggedInPage.getByRole('button', { name: 'Search Tags' }).click();

		const rows = loggedInPage.locator('.wp-list-table tbody tr');
		await expect(rows).toHaveCount(1);
		await expect(rows).toContainText(searchTerm);

		const debugResult = loggedInPage.locator(
			'#debug-menu-target-EP_Debug_Bar_ElasticPress .ep-query-debug .ep-query-result',
		);
		await expect(debugResult).toContainText(searchTerm);

		// Delete the term
		await rows.first().locator('.row-actions .delete a').click({ force: true });
	});

	test('Can a term be removed from the admin dashboard after deleting it', async ({
		loggedInPage,
	}) => {
		await login(loggedInPage);
		await maybeEnableFeature('terms');

		const term = 'amazing term';
		await createTerm(loggedInPage, { taxonomy: 'post_tag', name: term });

		await loggedInPage.getByLabel('Search Tags').fill(term);
		await loggedInPage.getByRole('button', { name: 'Search Tags' }).click();
		const rows = loggedInPage.locator('.wp-list-table tbody tr');
		await expect(rows).toHaveCount(1);
		await expect(rows).toContainText(term);

		const debugResult = loggedInPage.locator(
			'#debug-menu-target-EP_Debug_Bar_ElasticPress .ep-query-debug .ep-query-result',
		);
		await expect(debugResult).toContainText(term);

		await rows.first().locator('.row-actions .delete a').click({ force: true });
		await loggedInPage.waitForTimeout(2000);

		await loggedInPage.getByRole('button', { name: 'Search Tags' }).click();
		const tbody = loggedInPage.locator('.wp-list-table tbody');
		await expect(tbody).toContainText('No categories found');
		const debug = loggedInPage.locator(
			'#debug-menu-target-EP_Debug_Bar_ElasticPress .ep-query-debug',
		);
		await expect(debug).toContainText('Query Response Code: HTTP 200');
	});

	test('Can return a correct tag on searching a tag in admin dashboard', async ({
		loggedInPage,
	}) => {
		await login(loggedInPage);
		await maybeEnableFeature('terms');
		await goToAdminPage(loggedInPage, 'edit-tags.php?taxonomy=post_tag');

		await Promise.all(
			tags.map((tag) => createTerm(loggedInPage, { taxonomy: 'post_tag', name: tag })),
		);

		await loggedInPage.getByLabel('Search Tags').fill('the most fun thing');
		await loggedInPage.getByRole('button', { name: 'Search Tags' }).click();

		const rowTitle = loggedInPage.locator('.wp-list-table tbody tr .row-title');
		await expect(rowTitle).toContainText('The Most Fun Thing');

		const debugResult = loggedInPage.locator(
			'#debug-menu-target-EP_Debug_Bar_ElasticPress .ep-query-debug .ep-query-result',
		);
		await expect(debugResult).toContainText('The Most Fun Thing');
	});

	test('Can update a child term when a parent term is deleted', async ({ loggedInPage }) => {
		await login(loggedInPage);
		await maybeEnableFeature('terms');

		const parentTerm = 'bar-parent';
		const childTerm = 'baz-child';

		await createTerm(loggedInPage, { taxonomy: 'post_tag', name: parentTerm });
		await createTerm(loggedInPage, {
			taxonomy: 'post_tag',
			name: childTerm,
			parent: parentTerm,
		});

		await loggedInPage.getByLabel('Search Tags').fill(`${parentTerm}\n`);

		await loggedInPage.route('**/wp-admin/admin-ajax.php*', (route) => route.continue());
		const rows = loggedInPage.locator('.wp-list-table tbody tr');
		await rows.first().locator('.row-actions .delete a').click({ force: true });
		// Wait for ajax
		await loggedInPage.waitForResponse(
			(resp) => resp.url().includes('admin-ajax.php') && resp.status() === 200,
		);

		await loggedInPage.getByLabel('Search Tags').fill('');
		await loggedInPage.getByLabel('Search Tags').fill(`${childTerm}\n`);
		await loggedInPage.locator('.wp-list-table tbody tr .column-primary a').first().click();
		await expect(loggedInPage.getByLabel('Parent')).toHaveValue('-1');

		await loggedInPage.getByRole('link', { name: 'Delete' }).click();
	});
});
