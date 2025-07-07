import { test, expect } from '../../fixtures';
import {
	goToAdminPage,
	wpCli,
	maybeEnableFeature,
	maybeDisableFeature,
	createTerm,
	wpCliEval,
} from '../../utils';

const tags = ['Far From Home', 'No Way Home', 'The Most Fun Thing', 'search term'];

test.describe('Terms Feature', { tag: '@slow' }, () => {
	test.beforeAll(async () => {
		await wpCliEval(`
			WP_CLI::runcommand( 'plugin activate show-comments-and-terms', [ 'return' => true ] );
			$tags = get_terms(
				[
					'taxonomy'   => 'post_tag',
					'name__in'   => ${JSON.stringify(tags)},
					'hide_empty' => false,
				]
			);
			foreach ( $tags as $tag ) {
				wp_delete_term( $tag->term_id, 'post_tag' );
			}
		`);
	});

	test('Can turn the feature on', async ({ loggedInPage }) => {
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

	test('Can add, search and delete a term in the admin dashboard using Elasticsearch', async ({
		loggedInPage,
	}) => {
		await maybeEnableFeature('terms');

		const searchTerm = 'search term';
		await createTerm(loggedInPage, { taxonomy: 'post_tag', name: searchTerm });
		await goToAdminPage(loggedInPage, 'edit-tags.php?taxonomy=post_tag');

		await loggedInPage.getByLabel('Search Tags').fill(searchTerm);
		await loggedInPage.getByRole('button', { name: 'Search Tags' }).click();

		const rows = loggedInPage.locator('.wp-list-table tbody tr');
		await expect(rows).toHaveCount(1);
		await expect(rows).toContainText(searchTerm);

		await expect(
			loggedInPage
				.locator('#debug-menu-target-EP_Debug_Bar_ElasticPress .ep-query-debug')
				.filter({ hasText: 'term' })
				.first(),
		).toContainText(searchTerm);

		// Delete the term
		await rows.first().locator('.row-actions .delete a').dispatchEvent('click');
		await loggedInPage.waitForTimeout(4000);

		await loggedInPage.getByRole('button', { name: 'Search Tags' }).click();
		const tbody = loggedInPage.locator('.wp-list-table tbody');
		await expect(tbody).toContainText('No categories found');
		await expect(
			loggedInPage
				.locator('#debug-menu-target-EP_Debug_Bar_ElasticPress .ep-query-debug')
				.filter({ hasText: 'term' })
				.first(),
		).toContainText('Query Response Code: HTTP 200');
	});

	test('Can return a correct tag on searching a tag in admin dashboard', async ({
		loggedInPage,
	}) => {
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
