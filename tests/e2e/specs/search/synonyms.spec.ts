import { test, expect, Page } from '../../fixtures';
import { wpCliEval, goToAdminPage, activatePlugin, deactivatePlugin } from '../../utils';

test.describe('Post Search Feature - Synonyms Functionality', () => {
	/**
	 * Save the synonyms settings and wait for the API response.
	 *
	 * @param {Page} page - The Playwright Page object.
	 */
	async function saveSynonyms(page) {
		const responsePromise = page.waitForResponse('**/wp-json/elasticpress/v1/synonyms*');
		await page.getByRole('button', { name: 'Save changes' }).click();
		await responsePromise;
		await expect(
			page.locator('.components-snackbar').filter({ hasText: 'Synonym settings saved.' }),
		).toBeVisible();
	}

	/**
	 * Delete synonyms recreate test posts before running tests.
	 */
	test.beforeAll(async () => {
		await wpCliEval(`
			$ep_synonyms_tests = get_posts([
				'post_type'   => 'any',
				'meta_key'    => '_synonyms_tests',
				'meta_value'  => 1,
				'numberposts' => 999,
			]);
			foreach( $ep_synonyms_tests as $test ) {
				wp_delete_post( $test->ID, true );
			}
			$posts = [ 'Plugin', 'Extension', 'Module', 'ElasticPress', 'Safe Redirect Manager', 'Bandeirole', 'Flag', 'Banner', 'Red', 'Carmine', 'Cordovan', 'Crimson' ];
			foreach ( $posts as $post ) {
				wp_insert_post([
					'post_title'   => $post,
					'post_content' => '',
					'post_status'  => 'publish',
					'meta_input'   => [ '_synonyms_tests' => 1 ],
				]);
			}
		`);
	});

	/**
	 * Reset things before each test.
	 */
	test.beforeEach(async ({ loggedInPage }) => {
		await wpCliEval(`
			$ep_synonyms = get_posts([
				'post_type'   => 'ep-synonym',
				'post_status' => 'any',
				'numberposts' => 999,
			]);
			foreach( $ep_synonyms as $synonym ) {
				wp_delete_post( $synonym->ID, true );
			}
		`);

		/**
		 * Save synonyms settings.
		 */
		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress-synonyms');
		await saveSynonyms(loggedInPage);
	});

	test('Is possible to create, edit, and delete synonym rules', async ({ loggedInPage }) => {
		/**
		 * Confirm that only results with our search term are returned.
		 */
		await loggedInPage.goto('/?s=plugin');
		await expect(loggedInPage.locator('article h2', { hasText: 'Plugin' })).toBeVisible();
		await expect(
			loggedInPage.locator('article h2', { hasText: 'Extension' }),
		).not.toBeVisible();
		await expect(loggedInPage.locator('article h2', { hasText: 'Module' })).not.toBeVisible();

		/**
		 * Enter a synonym.
		 */
		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress-synonyms');
		const panel = loggedInPage.locator('.ep-synonyms-edit-panel');
		const input = panel.locator('input[type="text"]');
		const addButton = panel.getByRole('button', { name: 'Add synonyms' });
		await expect(addButton).toBeVisible();
		await input.fill('plugin,');

		/**
		 * Add button should be disabled when there's only one synonym.
		 */
		await expect(panel.getByRole('button', { name: 'Add synonyms' })).toBeDisabled();

		/**
		 * Enter another synonym and submit.
		 */
		await input.fill('extension,');
		await addButton.click();

		/**
		 * The synonyms should appear in the list.
		 */
		await expect(
			loggedInPage.locator('.ep-synonyms-list-table tr', { hasText: 'plugin, extension' }),
		).toBeVisible();

		await saveSynonyms(loggedInPage);

		/**
		 * Results should reflect the synonym rules.
		 */
		await loggedInPage.goto('/?s=plugin');
		await expect(loggedInPage.locator('article h2', { hasText: 'Plugin' })).toBeVisible();
		await expect(loggedInPage.locator('article h2', { hasText: 'Extension' })).toBeVisible();
		await expect(loggedInPage.locator('article h2', { hasText: 'Module' })).not.toBeVisible();

		/**
		 * It should be possible to edit synonym rules.
		 */
		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress-synonyms');
		const row = loggedInPage.locator('.ep-synonyms-list-table tr', {
			hasText: 'plugin, extension',
		});
		await row.getByRole('button', { name: 'Edit' }).click();
		await panel.getByText('Edit Synonyms').isVisible();
		await panel.locator('input').press('Backspace');
		await panel.locator('input').fill('module,');
		await panel.getByRole('button', { name: 'Save changes' }).click();
		await expect(
			loggedInPage.locator('.ep-synonyms-list-table tr', { hasText: 'plugin, module' }),
		).toBeVisible();

		await saveSynonyms(loggedInPage);

		/**
		 * Results should reflect the new synonyms.
		 */
		await loggedInPage.goto('/?s=plugin');
		await expect(loggedInPage.locator('article h2', { hasText: 'Plugin' })).toBeVisible();
		await expect(
			loggedInPage.locator('article h2', { hasText: 'Extension' }),
		).not.toBeVisible();
		await expect(loggedInPage.locator('article h2', { hasText: 'Module' })).toBeVisible();

		/**
		 * In the advanced editor, synonyms should be represented as expected.
		 */
		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress-synonyms');
		await loggedInPage.getByRole('button', { name: 'Switch to advanced text editor' }).click();
		await expect(loggedInPage.locator('textarea')).toContainText('plugin, module');

		/**
		 * It should be possible to delete synonym rules.
		 */
		await loggedInPage.getByRole('button', { name: 'Switch to visual editor' }).click();
		const row2 = loggedInPage.locator('.ep-synonyms-list-table tr', {
			hasText: 'plugin, module',
		});
		await row2.getByRole('button', { name: 'Delete' }).click();
		await expect(
			loggedInPage.locator('.ep-synonyms-list-table tr', { hasText: 'plugin' }),
		).not.toBeVisible();

		await saveSynonyms(loggedInPage);

		/**
		 * Results should reflect the deleted synonyms.
		 */
		await loggedInPage.goto('/?s=plugin');
		await expect(loggedInPage.locator('article h2', { hasText: 'Plugin' })).toBeVisible();
		await expect(
			loggedInPage.locator('article h2', { hasText: 'Extension' }),
		).not.toBeVisible();
		await expect(loggedInPage.locator('article h2', { hasText: 'Module' })).not.toBeVisible();
	});

	test('Is possible to create, edit, and delete hyponym rules', async ({ loggedInPage }) => {
		/**
		 * Confirm that only results with our search term are returned.
		 */
		await loggedInPage.goto('/?s=plugin');
		await expect(loggedInPage.locator('article h2', { hasText: 'Plugin' })).toBeVisible();
		await expect(
			loggedInPage.locator('article h2', { hasText: 'ElasticPress' }),
		).not.toBeVisible();
		await expect(
			loggedInPage.locator('article h2', { hasText: 'Safe Redirect Manager' }),
		).not.toBeVisible();

		/**
		 * Enter a hypernym.
		 */
		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress-synonyms');
		await loggedInPage.locator('button', { hasText: 'Hyponyms' }).click();
		const panel = loggedInPage.locator('.ep-synonyms-edit-panel');
		const addButton = panel.getByRole('button', { name: 'Add hyponyms' });
		await expect(addButton).toBeVisible();
		await panel.locator('input[type="text"]').nth(0).fill('plugin');

		/**
		 * Add button should be disabled when there's no hyponyms.
		 */
		await expect(addButton).toBeDisabled();

		/**
		 * Enter a hyponym and submit.
		 */
		await panel.locator('input[type="text"]').nth(1).fill('ElasticPress,');
		await addButton.click();

		/**
		 * The rule should appear in the list,
		 */
		await expect(
			loggedInPage.locator('.ep-synonyms-list-table tr', { hasText: 'plugin' }),
		).toBeVisible();

		await saveSynonyms(loggedInPage);

		/**
		 * Results should reflect the hyponym rules.
		 */
		await loggedInPage.goto('/?s=plugin');
		await expect(loggedInPage.locator('article h2', { hasText: 'Plugin' })).toBeVisible();
		await expect(loggedInPage.locator('article h2', { hasText: 'ElasticPress' })).toBeVisible();
		await expect(
			loggedInPage.locator('article h2', { hasText: 'Safe Redirect Manager' }),
		).not.toBeVisible();
		await loggedInPage.goto('/?s=elasticpress');
		await expect(loggedInPage.locator('article h2', { hasText: 'Plugin' })).not.toBeVisible();
		await expect(loggedInPage.locator('article h2', { hasText: 'ElasticPress' })).toBeVisible();
		await expect(
			loggedInPage.locator('article h2', { hasText: 'Safe Redirect Manager' }),
		).not.toBeVisible();
		await loggedInPage.goto('/?s=redirect');
		await expect(loggedInPage.locator('article h2', { hasText: 'Plugin' })).not.toBeVisible();
		await expect(
			loggedInPage.locator('article h2', { hasText: 'ElasticPress' }),
		).not.toBeVisible();
		await expect(
			loggedInPage.locator('article h2', { hasText: 'Safe Redirect Manager' }),
		).toBeVisible();

		/**
		 * It should be possible to edit hyponym rules.
		 */
		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress-synonyms');
		await loggedInPage.locator('button', { hasText: 'Hyponyms' }).click();
		const row = loggedInPage.locator('.ep-synonyms-list-table tr', { hasText: 'plugin' });
		await row.getByRole('button', { name: 'Edit' }).click();
		await panel.getByText('Edit Hyponyms').isVisible();
		await panel.locator('input').nth(1).fill('Safe Redirect Manager,');
		await panel.getByRole('button', { name: 'Save changes' }).click();
		await expect(
			row.locator('td', { hasText: 'ElasticPress, Safe Redirect Manager' }),
		).toBeVisible();

		await saveSynonyms(loggedInPage);

		/**
		 * Results should reflect the new hyponyms.
		 */
		await loggedInPage.goto('/?s=plugin');
		await expect(loggedInPage.locator('article h2', { hasText: 'Plugin' })).toBeVisible();
		await expect(loggedInPage.locator('article h2', { hasText: 'ElasticPress' })).toBeVisible();
		await expect(
			loggedInPage.locator('article h2', { hasText: 'Safe Redirect Manager' }),
		).toBeVisible();
		await loggedInPage.goto('/?s=elasticpress');
		await expect(loggedInPage.locator('article h2', { hasText: 'Plugin' })).not.toBeVisible();
		await expect(loggedInPage.locator('article h2', { hasText: 'ElasticPress' })).toBeVisible();
		await expect(
			loggedInPage.locator('article h2', { hasText: 'Safe Redirect Manager' }),
		).not.toBeVisible();
		await loggedInPage.goto('/?s=redirect');
		await expect(loggedInPage.locator('article h2', { hasText: 'Plugin' })).not.toBeVisible();
		await expect(
			loggedInPage.locator('article h2', { hasText: 'ElasticPress' }),
		).not.toBeVisible();
		await expect(
			loggedInPage.locator('article h2', { hasText: 'Safe Redirect Manager' }),
		).toBeVisible();

		/**
		 * In the advanced editor, hyponyms should be represented as
		 * replacements where the hypernym is also included as a replacement.
		 */
		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress-synonyms');
		await loggedInPage.getByRole('button', { name: 'Switch to advanced text editor' }).click();
		await expect(loggedInPage.locator('textarea')).toContainText(
			'plugin => plugin, ElasticPress, Safe Redirect Manager',
		);

		/**
		 * It should be possible to delete hyponym rules.
		 */
		await loggedInPage.getByRole('button', { name: 'Switch to visual editor' }).click();
		await loggedInPage.locator('button', { hasText: 'Hyponyms' }).click();
		const row2 = loggedInPage.locator('.ep-synonyms-list-table tr', { hasText: 'plugin' });
		await row2.getByRole('button', { name: 'Delete' }).click();
		await expect(
			loggedInPage.locator('.ep-synonyms-list-table tr', { hasText: 'plugin' }),
		).not.toBeVisible();

		await saveSynonyms(loggedInPage);

		/**
		 * Results should not longer reflect the deleted rule.
		 */
		await loggedInPage.goto('/?s=plugin');
		await expect(loggedInPage.locator('article h2', { hasText: 'Plugin' })).toBeVisible();
		await expect(
			loggedInPage.locator('article h2', { hasText: 'ElasticPress' }),
		).not.toBeVisible();
		await expect(
			loggedInPage.locator('article h2', { hasText: 'Safe Redirect Manager' }),
		).not.toBeVisible();
		await loggedInPage.goto('/?s=elasticpress');
		await expect(loggedInPage.locator('article h2', { hasText: 'Plugin' })).not.toBeVisible();
		await expect(loggedInPage.locator('article h2', { hasText: 'ElasticPress' })).toBeVisible();
		await expect(
			loggedInPage.locator('article h2', { hasText: 'Safe Redirect Manager' }),
		).not.toBeVisible();
		await loggedInPage.goto('/?s=redirect');
		await expect(loggedInPage.locator('article h2', { hasText: 'Plugin' })).not.toBeVisible();
		await expect(
			loggedInPage.locator('article h2', { hasText: 'ElasticPress' }),
		).not.toBeVisible();
		await expect(
			loggedInPage.locator('article h2', { hasText: 'Safe Redirect Manager' }),
		).toBeVisible();
	});

	test('Is possible to create, edit, and delete replacement rules', async ({ loggedInPage }) => {
		await activatePlugin(loggedInPage, 'disable-fuzziness', 'wpCli');

		/**
		 * Confirm that our replacements are not returned yet.
		 */
		await loggedInPage.goto('/?s=bandeirole');
		await expect(loggedInPage.locator('article h2', { hasText: 'Bandeirole' })).toBeVisible();
		await expect(loggedInPage.locator('article h2', { hasText: 'Flag' })).not.toBeVisible();
		await expect(loggedInPage.locator('article h2', { hasText: 'Banner' })).not.toBeVisible();

		/**
		 * Enter a term.
		 */
		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress-synonyms');
		await loggedInPage.locator('button', { hasText: 'Replacements' }).click();
		const panel = loggedInPage.locator('.ep-synonyms-edit-panel');
		await expect(panel.locator('h2', { hasText: 'Add Replacements' })).toBeVisible();
		await expect(panel.getByRole('button', { name: 'Add replacements' })).toBeVisible();
		await panel.locator('input[type="text"]').nth(0).fill('bandeirole,');

		/**
		 * Add button should be disabled when there's no replacements.
		 */
		await expect(panel.getByRole('button', { name: 'Add replacements' })).toBeDisabled();

		/**
		 * Enter a replacement and submit.
		 */
		await panel.locator('input[type="text"]').nth(1).fill('flag,');
		await panel.getByRole('button', { name: 'Add replacements' }).click();

		/**
		 * The replacements should appear in the list.
		 */
		await expect(
			loggedInPage.locator('.ep-synonyms-list-table tr', { hasText: 'bandeirole' }),
		).toBeVisible();

		await saveSynonyms(loggedInPage);

		/**
		 * Results should reflect the replacement rules.
		 */
		await loggedInPage.goto('/?s=bandeirole');
		await expect(
			loggedInPage.locator('article h2', { hasText: 'Bandeirole' }),
		).not.toBeVisible();
		await expect(loggedInPage.locator('article h2', { hasText: 'Flag' })).toBeVisible();
		await expect(loggedInPage.locator('article h2', { hasText: 'Banner' })).not.toBeVisible();

		/**
		 * It should be possible to edit replacement rules.
		 */
		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress-synonyms');
		await loggedInPage.locator('button', { hasText: 'Replacements' }).click();
		const row = loggedInPage.locator('.ep-synonyms-list-table tr', { hasText: 'bandeirole' });
		await row.getByRole('button', { name: 'Edit' }).click();
		await panel.getByText('Edit Replacements').isVisible();
		await panel.locator('input').nth(1).fill('banner,');
		await panel.getByRole('button', { name: 'Save changes' }).click();
		await expect(
			loggedInPage.locator('.ep-synonyms-list-table tr', { hasText: 'flag, banner' }),
		).toBeVisible();

		await saveSynonyms(loggedInPage);

		/**
		 * Results should reflect the new replacements.
		 */
		await loggedInPage.goto('/?s=bandeirole');
		await expect(
			loggedInPage.locator('article h2', { hasText: 'Bandeirole' }),
		).not.toBeVisible();
		await expect(loggedInPage.locator('article h2', { hasText: 'Flag' })).toBeVisible();
		await expect(loggedInPage.locator('article h2', { hasText: 'Banner' })).toBeVisible();

		/**
		 * In the advanced editor, replacements hould be represented as
		 * expected.
		 */
		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress-synonyms');
		await loggedInPage.getByRole('button', { name: 'Switch to advanced text editor' }).click();
		await expect(loggedInPage.locator('textarea')).toContainText('bandeirole => flag, banner');

		/**
		 * It should be possible to delete replacement rules.
		 */
		await loggedInPage.getByRole('button', { name: 'Switch to visual editor' }).click();
		await loggedInPage.locator('button', { hasText: 'Replacements' }).click();
		const row2 = loggedInPage.locator('.ep-synonyms-list-table tr', { hasText: 'bandeirole' });
		await row2.getByRole('button', { name: 'Delete' }).click();
		await expect(
			loggedInPage.locator('.ep-synonyms-list-table tr', { hasText: 'bandeirole' }),
		).not.toBeVisible();

		await saveSynonyms(loggedInPage);

		/**
		 * Results should not longer reflect the deleted rule.
		 */
		await loggedInPage.goto('/?s=bandeirole');
		await expect(loggedInPage.locator('article h2', { hasText: 'Bandeirole' })).toBeVisible();
		await expect(loggedInPage.locator('article h2', { hasText: 'Flag' })).not.toBeVisible();
		await expect(loggedInPage.locator('article h2', { hasText: 'Banner' })).not.toBeVisible();

		await deactivatePlugin(loggedInPage, 'disable-fuzziness', 'wpCli');
	});

	test('Is possible to edit rules using the text editor', async ({ loggedInPage }) => {
		/**
		 * Our rule should not be reflected in results yet.
		 */
		await loggedInPage.goto('/?s=red');
		await expect(loggedInPage.locator('article h2', { hasText: 'Red' })).toBeVisible();
		await expect(loggedInPage.locator('article h2', { hasText: 'Carmine' })).not.toBeVisible();
		await expect(loggedInPage.locator('article h2', { hasText: 'Cordovan' })).not.toBeVisible();
		await expect(loggedInPage.locator('article h2', { hasText: 'Crimson' })).not.toBeVisible();

		/**
		 * Add a hyponym rule to the text editor.
		 */
		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress-synonyms');
		await loggedInPage.getByRole('button', { name: 'Switch to advanced text editor' }).click();
		await loggedInPage.locator('textarea').fill('red => red, carmine, cordovan, crimson');

		await saveSynonyms(loggedInPage);

		/**
		 * Our rule should be reflected in results.
		 */
		await loggedInPage.goto('/?s=red');
		await expect(loggedInPage.locator('article h2', { hasText: 'Red' })).toBeVisible();
		await expect(loggedInPage.locator('article h2', { hasText: 'Carmine' })).toBeVisible();
		await expect(loggedInPage.locator('article h2', { hasText: 'Cordovan' })).toBeVisible();
		await expect(loggedInPage.locator('article h2', { hasText: 'Crimson' })).toBeVisible();
		await loggedInPage.goto('/?s=carmine');
		await expect(loggedInPage.locator('article h2', { hasText: 'Red' })).not.toBeVisible();
		await expect(loggedInPage.locator('article h2', { hasText: 'Carmine' })).toBeVisible();
		await expect(loggedInPage.locator('article h2', { hasText: 'Cordovan' })).not.toBeVisible();
		await expect(loggedInPage.locator('article h2', { hasText: 'Crimson' })).not.toBeVisible();

		/**
		 * The settings page should remember that we used the text editor.
		 */
		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress-synonyms');
		await expect(loggedInPage.locator('textarea')).toBeVisible();

		/**
		 * Our rule should be visible under Hyponyms when we switch to the
		 * visual editor.
		 */
		await loggedInPage.getByRole('button', { name: 'Switch to visual editor' }).click();
		await loggedInPage.locator('button', { hasText: 'Hyponyms' }).click();
		await expect(
			loggedInPage.locator('.ep-synonyms-list-table tr', {
				hasText: 'carmine, cordovan, crimson',
			}),
		).toBeVisible();
	});
});
