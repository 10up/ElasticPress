import { test, expect } from '../fixtures.js';
import {
	goToAdminPage,
	wpCli,
	maybeEnableFeature,
	maybeDisableFeature,
	isEpIo,
} from '../utils.js';

test.describe('Semantic Search Feature', { tag: '@group2' }, () => {
	test.afterAll(async () => {
		await maybeDisableFeature('semantic_search');
		await maybeDisableFeature('vector_embeddings');
	});

	test('Can not turn the feature on if vector embeddings is not enabled', async ({
		loggedInPage,
	}) => {
		await maybeDisableFeature('vector_embeddings');
		await maybeDisableFeature('semantic_search');
		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress');

		await loggedInPage.getByRole('button', { name: 'AI', exact: true }).click();
		await loggedInPage.getByRole('button', { name: 'Semantic Search' }).click();

		await expect(
			loggedInPage.locator('.components-notice.is-error').filter({
				hasText: 'The Vector Embeddings feature must be enabled to use this feature.',
			}),
		).toBeVisible();
	});

	test('Can turn the feature on', async ({ loggedInPage }) => {
		await maybeEnableFeature('vector_embeddings');
		await maybeDisableFeature('semantic_search');
		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress');

		await loggedInPage.getByRole('button', { name: 'Other', exact: true }).click();
		await loggedInPage.getByRole('button', { name: 'Search Algorithm Version' }).click();

		await expect(loggedInPage.locator('#search_algorithm-view').getByRole('radio')).toHaveCount(
			3,
		);

		// Wait for API request
		const apiRequestPromise = loggedInPage.waitForResponse(
			'/wp-json/elasticpress/v1/features*',
		);

		await loggedInPage.getByRole('button', { name: 'AI', exact: true }).click();
		await loggedInPage.getByRole('button', { name: 'Semantic Search' }).click();
		await loggedInPage.getByRole('checkbox', { name: 'Enable' }).click();
		await loggedInPage.getByRole('button', { name: 'Save' }).click();

		const apiRequestResponse = await apiRequestPromise;
		const jsonResponse = await apiRequestResponse.json();
		expect(JSON.stringify(jsonResponse)).toContain('"success":true');

		const result = await wpCli('elasticpress list-features');
		expect(result.toString()).toContain('semantic_search');

		await loggedInPage.reload();

		await loggedInPage.getByRole('button', { name: 'Other', exact: true }).click();
		await loggedInPage.getByRole('button', { name: 'Search Algorithm Version' }).click();

		if (process.env.ES_VERSION === '7.10.1') {
			await expect(loggedInPage.getByLabel('kNN')).toHaveCount(1);
			await expect(loggedInPage.getByLabel('kNN Cosine')).toBeVisible();
			await expect(loggedInPage.getByLabel('kNN', { exact: true })).not.toBeVisible();
			await expect(loggedInPage.getByLabel('Hybrid (kNN + Regular ES)')).not.toBeVisible();
		} else {
			await expect(loggedInPage.getByLabel('kNN')).toHaveCount(3);
			await expect(loggedInPage.getByLabel('kNN Cosine')).toBeVisible();
			await expect(loggedInPage.getByLabel('Hybrid (kNN + Regular ES)')).toBeVisible();
		}
	});

	test('Search algorithms disable Autosuggest and Instant Results', async ({ loggedInPage }) => {
		const saveFeatures = async () => {
			const apiResponsePromise = loggedInPage.waitForResponse(
				'**/wp-json/elasticpress/v1/features*',
			);
			await loggedInPage.getByRole('button', { name: 'Save changes' }).click();
			await apiResponsePromise;
		};

		// Check if Autosuggest and Instant Results are enabled
		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress');
		await loggedInPage.getByRole('button', { name: 'Live Search' }).click();

		await loggedInPage.getByRole('button', { name: 'Autosuggest' }).click();
		await expect(
			loggedInPage.locator('#autosuggest-view').getByRole('checkbox', { name: 'Enable' }),
		).toBeEnabled();
		await expect(
			loggedInPage.locator('#autosuggest-view').getByRole('checkbox', { name: 'Enable' }),
		).toBeChecked();

		await loggedInPage.getByRole('button', { name: 'Instant Results' }).click();
		if (isEpIo()) {
			await expect(
				loggedInPage
					.locator('#instant-results-view')
					.getByRole('checkbox', { name: 'Enable' }),
			).toBeEnabled();
		}

		// Select a semantic search algorithm
		await maybeEnableFeature('vector_embeddings');
		await maybeEnableFeature('semantic_search');
		await maybeEnableFeature('search_algorithm');

		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress');
		await loggedInPage.getByRole('button', { name: 'Other' }).click();
		await loggedInPage.getByRole('button', { name: 'Search Algorithm Version' }).click();
		await loggedInPage.getByLabel('kNN Cosine').check();
		await saveFeatures();

		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress');
		await loggedInPage.getByRole('button', { name: 'Live Search' }).click();
		await loggedInPage.getByRole('button', { name: 'Autosuggest' }).click();
		await expect(
			loggedInPage.locator('#autosuggest-view').getByRole('checkbox', { name: 'Enable' }),
		).toBeDisabled();
		await expect(
			loggedInPage.locator('#autosuggest-view').getByRole('checkbox', { name: 'Enable' }),
		).not.toBeChecked();
		await expect(
			loggedInPage.locator('#autosuggest-view').getByText('This feature is temporarily'),
		).toBeVisible();

		await loggedInPage.getByRole('button', { name: 'Instant Results' }).click();
		await expect(
			loggedInPage.locator('#instant-results-view').getByRole('checkbox', { name: 'Enable' }),
		).toBeDisabled();
		await expect(
			loggedInPage.locator('#instant-results-view').getByText('This feature is temporarily'),
		).toBeVisible();

		// If another algorithm is selected, Autosuggest and Instant Results should be enabled again
		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress');
		await loggedInPage.getByRole('button', { name: 'Other' }).click();
		await loggedInPage.getByRole('button', { name: 'Search Algorithm Version' }).click();
		await loggedInPage.getByLabel('Version 4.0').check();
		await saveFeatures();

		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress');
		await loggedInPage.getByRole('button', { name: 'Live Search' }).click();
		await loggedInPage.getByRole('button', { name: 'Autosuggest' }).click();
		await expect(
			loggedInPage.locator('#autosuggest-view').getByRole('checkbox', { name: 'Enable' }),
		).toBeEnabled();
		await expect(
			loggedInPage.locator('#autosuggest-view').getByRole('checkbox', { name: 'Enable' }),
		).toBeChecked();

		await loggedInPage.getByRole('button', { name: 'Instant Results' }).click();
		if (isEpIo()) {
			await expect(
				loggedInPage
					.locator('#instant-results-view')
					.getByRole('checkbox', { name: 'Enable' }),
			).toBeEnabled();
		}
	});
});
