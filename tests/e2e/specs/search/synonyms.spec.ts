import { test, expect } from '../../fixtures';
import { wpCli, goToAdminPage, activatePlugin, deactivatePlugin } from '../../utils';

test.describe('Post Search Feature - Synonyms Functionality', () => {
	async function saveSynonyms(page) {
		await page.route('/wp-json/elasticpress/v1/synonyms*', (route) => route.continue());
		await page.getByRole('button', { name: 'Save changes' }).click();
		await expect(page.locator('text=Synonym settings saved.')).toBeVisible();
	}

	test.beforeAll(async () => {
		await wpCli(`eval "
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
		"`);
	});

	test.beforeEach(async ({ loggedInPage }) => {
		await wpCli(`eval "
			$ep_synonyms = get_posts([
				'post_type'   => 'ep-synonym',
				'post_status' => 'any',
				'numberposts' => 999,
			]);
			foreach( $ep_synonyms as $synonym ) {
				wp_delete_post( $synonym->ID, true );
			}
		"`);
		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress-synonyms');
		await saveSynonyms(loggedInPage);
	});

	test('Is possible to create, edit, and delete synonym rules', async ({ loggedInPage }) => {
		await loggedInPage.goto('/?s=plugin');
		await expect(loggedInPage.locator('article h2', { hasText: 'Plugin' })).toBeVisible();
		await expect(
			loggedInPage.locator('article h2', { hasText: 'Extension' }),
		).not.toBeVisible();
		await expect(loggedInPage.locator('article h2', { hasText: 'Module' })).not.toBeVisible();

		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress-synonyms');
		const panel = loggedInPage.locator('.ep-synonyms-edit-panel');
		await expect(panel.getByText('Add Synonyms')).toBeVisible();
		await panel.locator('input[type="text"]').type('plugin,');
		await expect(panel.getByRole('button', { name: 'Add synonyms' })).toBeDisabled();
		await panel.locator('input[type="text"]').type('extension,');
		await panel.getByRole('button', { name: 'Add synonyms' }).click();
		await expect(
			loggedInPage.locator('.ep-synonyms-list-table tr', { hasText: 'plugin, extension' }),
		).toBeVisible();
		await saveSynonyms(loggedInPage);

		await loggedInPage.goto('/?s=plugin');
		await expect(loggedInPage.locator('article h2', { hasText: 'Plugin' })).toBeVisible();
		await expect(loggedInPage.locator('article h2', { hasText: 'Extension' })).toBeVisible();
		await expect(loggedInPage.locator('article h2', { hasText: 'Module' })).not.toBeVisible();

		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress-synonyms');
		const row = loggedInPage.locator('.ep-synonyms-list-table tr', {
			hasText: 'plugin, extension',
		});
		await row.getByRole('button', { name: 'Edit' }).click();
		await panel.getByText('Edit Synonyms').isVisible();
		await panel.locator('input').type('{Backspace}module,');
		await panel.getByRole('button', { name: 'Save changes' }).click();
		await expect(
			loggedInPage.locator('.ep-synonyms-list-table tr', { hasText: 'plugin, module' }),
		).toBeVisible();
		await saveSynonyms(loggedInPage);

		await loggedInPage.goto('/?s=plugin');
		await expect(loggedInPage.locator('article h2', { hasText: 'Plugin' })).toBeVisible();
		await expect(
			loggedInPage.locator('article h2', { hasText: 'Extension' }),
		).not.toBeVisible();
		await expect(loggedInPage.locator('article h2', { hasText: 'Module' })).toBeVisible();

		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress-synonyms');
		await loggedInPage.getByRole('button', { name: 'Switch to advanced text editor' }).click();
		await expect(loggedInPage.locator('textarea')).toContainText('plugin, module');
		await loggedInPage.getByRole('button', { name: 'Switch to visual editor' }).click();
		const row2 = loggedInPage.locator('.ep-synonyms-list-table tr', {
			hasText: 'plugin, module',
		});
		await row2.getByRole('button', { name: 'Delete' }).click();
		await expect(
			loggedInPage.locator('.ep-synonyms-list-table tr', { hasText: 'plugin' }),
		).not.toBeVisible();
		await saveSynonyms(loggedInPage);

		await loggedInPage.goto('/?s=plugin');
		await expect(loggedInPage.locator('article h2', { hasText: 'Plugin' })).toBeVisible();
		await expect(
			loggedInPage.locator('article h2', { hasText: 'Extension' }),
		).not.toBeVisible();
		await expect(loggedInPage.locator('article h2', { hasText: 'Module' })).not.toBeVisible();
	});

	test('Is possible to create, edit, and delete hyponym rules', async ({ loggedInPage }) => {
		await loggedInPage.goto('/?s=plugin');
		await expect(loggedInPage.locator('article h2', { hasText: 'Plugin' })).toBeVisible();
		await expect(
			loggedInPage.locator('article h2', { hasText: 'ElasticPress' }),
		).not.toBeVisible();
		await expect(
			loggedInPage.locator('article h2', { hasText: 'Safe Redirect Manager' }),
		).not.toBeVisible();

		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress-synonyms');
		await loggedInPage.getByRole('button', { name: 'Hyponyms' }).click();
		const panel = loggedInPage.locator('.ep-synonyms-edit-panel');
		await expect(panel.getByText('Add Hyponyms')).toBeVisible();
		await panel.locator('input[type="text"]').nth(0).type('plugin');
		await expect(panel.getByRole('button', { name: 'Add hyponyms' })).toBeDisabled();
		await panel.locator('input[type="text"]').nth(1).type('ElasticPress,');
		await panel.getByRole('button', { name: 'Add hyponyms' }).click();
		await expect(
			loggedInPage.locator('.ep-synonyms-list-table tr', { hasText: 'plugin' }),
		).toBeVisible();
		await saveSynonyms(loggedInPage);

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

		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress-synonyms');
		await loggedInPage.getByRole('button', { name: 'Hyponyms' }).click();
		const row = loggedInPage.locator('.ep-synonyms-list-table tr', { hasText: 'plugin' });
		await row.getByRole('button', { name: 'Edit' }).click();
		await panel.getByText('Edit Hyponyms').isVisible();
		await panel.locator('input').nth(1).type('Safe Redirect Manager,');
		await panel.getByRole('button', { name: 'Save changes' }).click();
		await expect(row.locator('td')).toContainText('ElasticPress, Safe Redirect Manager');
		await saveSynonyms(loggedInPage);

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

		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress-synonyms');
		await loggedInPage.getByRole('button', { name: 'Switch to advanced text editor' }).click();
		await expect(loggedInPage.locator('textarea')).toContainText(
			'plugin => plugin, ElasticPress, Safe Redirect Manager',
		);
		await loggedInPage.getByRole('button', { name: 'Switch to visual editor' }).click();
		await loggedInPage.getByRole('button', { name: 'Hyponyms' }).click();
		const row2 = loggedInPage.locator('.ep-synonyms-list-table tr', { hasText: 'plugin' });
		await row2.getByRole('button', { name: 'Delete' }).click();
		await expect(
			loggedInPage.locator('.ep-synonyms-list-table tr', { hasText: 'plugin' }),
		).not.toBeVisible();
		await saveSynonyms(loggedInPage);

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
		(await activatePlugin) &&
			(await activatePlugin(loggedInPage, 'disable-fuzziness', 'wpCli'));
		await loggedInPage.goto('/?s=bandeirole');
		await expect(loggedInPage.locator('article h2', { hasText: 'Bandeirole' })).toBeVisible();
		await expect(loggedInPage.locator('article h2', { hasText: 'Flag' })).not.toBeVisible();
		await expect(loggedInPage.locator('article h2', { hasText: 'Banner' })).not.toBeVisible();

		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress-synonyms');
		await loggedInPage.getByRole('button', { name: 'Replacements' }).click();
		const panel = loggedInPage.locator('.ep-synonyms-edit-panel');
		await expect(panel.getByText('Add Replacements')).toBeVisible();
		await panel.locator('input[type="text"]').nth(0).type('bandeirole,');
		await expect(panel.getByRole('button', { name: 'Add replacements' })).toBeDisabled();
		await panel.locator('input[type="text"]').nth(1).type('flag,');
		await panel.getByRole('button', { name: 'Add replacements' }).click();
		await expect(
			loggedInPage.locator('.ep-synonyms-list-table tr', { hasText: 'bandeirole' }),
		).toBeVisible();
		await saveSynonyms(loggedInPage);

		await loggedInPage.goto('/?s=bandeirole');
		await expect(
			loggedInPage.locator('article h2', { hasText: 'Bandeirole' }),
		).not.toBeVisible();
		await expect(loggedInPage.locator('article h2', { hasText: 'Flag' })).toBeVisible();
		await expect(loggedInPage.locator('article h2', { hasText: 'Banner' })).not.toBeVisible();

		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress-synonyms');
		await loggedInPage.getByRole('button', { name: 'Replacements' }).click();
		const row = loggedInPage.locator('.ep-synonyms-list-table tr', { hasText: 'bandeirole' });
		await row.getByRole('button', { name: 'Edit' }).click();
		await panel.getByText('Edit Replacements').isVisible();
		await panel.locator('input').nth(1).type('banner,');
		await panel.getByRole('button', { name: 'Save changes' }).click();
		await expect(
			loggedInPage.locator('.ep-synonyms-list-table tr', { hasText: 'flag, banner' }),
		).toBeVisible();
		await saveSynonyms(loggedInPage);

		await loggedInPage.goto('/?s=bandeirole');
		await expect(
			loggedInPage.locator('article h2', { hasText: 'Bandeirole' }),
		).not.toBeVisible();
		await expect(loggedInPage.locator('article h2', { hasText: 'Flag' })).toBeVisible();
		await expect(loggedInPage.locator('article h2', { hasText: 'Banner' })).toBeVisible();

		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress-synonyms');
		await loggedInPage.getByRole('button', { name: 'Switch to advanced text editor' }).click();
		await expect(loggedInPage.locator('textarea')).toContainText('bandeirole => flag, banner');
		await loggedInPage.getByRole('button', { name: 'Switch to visual editor' }).click();
		await loggedInPage.getByRole('button', { name: 'Replacements' }).click();
		const row2 = loggedInPage.locator('.ep-synonyms-list-table tr', { hasText: 'bandeirole' });
		await row2.getByRole('button', { name: 'Delete' }).click();
		await expect(
			loggedInPage.locator('.ep-synonyms-list-table tr', { hasText: 'bandeirole' }),
		).not.toBeVisible();
		await saveSynonyms(loggedInPage);

		await loggedInPage.goto('/?s=bandeirole');
		await expect(loggedInPage.locator('article h2', { hasText: 'Bandeirole' })).toBeVisible();
		await expect(loggedInPage.locator('article h2', { hasText: 'Flag' })).not.toBeVisible();
		await expect(loggedInPage.locator('article h2', { hasText: 'Banner' })).not.toBeVisible();
		(await deactivatePlugin) &&
			(await deactivatePlugin(loggedInPage, 'disable-fuzziness', 'wpCli'));
	});

	test('Is possible to edit rules using the text editor', async ({ loggedInPage }) => {
		await loggedInPage.goto('/?s=red');
		await expect(loggedInPage.locator('article h2', { hasText: 'Red' })).toBeVisible();
		await expect(loggedInPage.locator('article h2', { hasText: 'Carmine' })).not.toBeVisible();
		await expect(loggedInPage.locator('article h2', { hasText: 'Cordovan' })).not.toBeVisible();
		await expect(loggedInPage.locator('article h2', { hasText: 'Crimson' })).not.toBeVisible();

		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress-synonyms');
		await loggedInPage.getByRole('button', { name: 'Switch to advanced text editor' }).click();
		await loggedInPage.locator('textarea').type('red => red, carmine, cordovan, crimson');
		await saveSynonyms(loggedInPage);

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

		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress-synonyms');
		await expect(loggedInPage.locator('textarea')).toBeVisible();
		await loggedInPage.getByRole('button', { name: 'Switch to visual editor' }).click();
		await loggedInPage.getByRole('button', { name: 'Hyponyms' }).click();
		await expect(
			loggedInPage.locator('.ep-synonyms-list-table tr', {
				hasText: 'carmine, cordovan, crimson',
			}),
		).toBeVisible();
	});
});
