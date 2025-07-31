import { test, expect, Page } from '../fixtures';
import {
	wpCli,
	publishPost,
	goToAdminPage,
	activatePlugin,
	deactivatePlugin,
	maybeEnableFeature,
	isEpIo,
	wpCliEval,
	setCustomPostTypes,
	maybeDisableFeature,
} from '../utils';
import { openBlockInserter, insertBlock } from '../block-editor';

/**
 * Test suite for the Instant Results Feature in ElasticPress.
 *
 * @module InstantResults
 */
test.describe('Instant Results Feature', () => {
	const instantResultRequestPromise = (page: Page, urlPart: string) => {
		return page.waitForResponse((response) => {
			const requestId = response.request().headers()['x-elasticpress-request-id'];
			return response.url().includes(urlPart) && !!requestId?.match(/[0-9a-f]{32}$/);
		});
	};

	const addInstantResultFilter = async (page: Page, filterName: string) => {
		await page.locator('.components-form-token-field__input').focus();
		await page.keyboard.press('Backspace');
		await page.keyboard.press('Backspace');
		await page.keyboard.press('Backspace');
		await page.keyboard.press('Backspace');
		await page.locator('.components-form-token-field__input').fill(filterName);
		await page.keyboard.press('ArrowDown');
		await page.keyboard.press('Enter');
		await page.keyboard.press('Escape');
	};

	const searchFor = async (page: Page, searchTerm: string) => {
		const searchBlock = page.locator('form.search-form').last();
		await searchBlock.locator('input[type="search"]').fill(searchTerm);
		await searchBlock.locator('input[type="submit"]').click();
	};

	/**
	 * Create a Product Search widget.
	 *
	 * @param {Page} page - Playwright Page object
	 */
	const createProductSearchWidget = async (page: any) => {
		await goToAdminPage(page, 'widgets.php');
		await openBlockInserter(page);
		await expect(page.locator('.block-editor-inserter__block-list')).toContainText(
			'Product Search',
		);
		await insertBlock(page, 'Product Search');
		const responsePromise = page.waitForResponse('**/wp-json/wp/v2/sidebars/*');
		await page.getByRole('button', { name: 'Update' }).click();
		await responsePromise;
	};

	test.beforeAll(async () => {
		const output = await wpCliEval(
			`
			WP_CLI::runcommand( 'plugin deactivate classic-widgets woocommerce', [ 'return' => 'all', 'exit_error' => false ] );

			$widgets_obj = WP_CLI::runcommand( 'widget list sidebar-1 --fields=id', [ 'return' => 'all', 'exit_error' => false ] );
			if ( $widgets_obj->return_code !== 0 ) {
				print_r( "\nError listing widgets\n" );
				print_r( $widgets_obj );
			}

			if ( $widgets_obj->stdout && $widgets_obj->return_code === 0 ) {
				$widgets = explode( "\n", $widgets_obj->stdout );
				$widgets = array_filter( $widgets, function( $widget ) {
					return str_starts_with( $widget, 'search-' );
				} );
				$widgets = implode( ' ', $widgets );

				$deleted = WP_CLI::runcommand( "widget delete {$widgets}", [ 'return' => 'all', 'exit_error' => false ] );
				if ( $deleted->return_code !== 0 ) {
					print_r( "\nError deleting widgets\n" );
					print_r( $deleted );
				}
			}

			$added = WP_CLI::runcommand( 'widget add search sidebar-1', [ 'return' => 'all', 'exit_error' => false ] );
			if ( $added->return_code !== 0 ) {
				print_r( "\nError adding widget\n" );
				print_r( $added );
			}

			wp_insert_post(
				[
					'post_title'   => 'Blog post',
					'post_content' => 'This is a sample Blog post.',
					'post_status'  => 'publish',
				]
			);
			wp_insert_post(
				[
					'post_title'   => 'Test Post',
					'post_content' => 'This is a sample test post.',
					'post_status'  => 'publish',
				]
			);
			`,
		);
		console.log(output.toString());
	});

	test.beforeEach(async ({ loggedInPage }) => {
		await deactivatePlugin(
			loggedInPage,
			'custom-instant-results-template open-instant-results-with-buttons filter-instant-results-per-page filter-instant-results-args-schema',
			'wpCli',
		);
	});

	/**
	 * Test that the feature cannot be activated when not in ElasticPress.io nor using a custom PHP proxy.
	 */
	test("Can't activate the feature if not in ElasticPress.io nor using a custom PHP proxy", async ({
		loggedInPage,
	}) => {
		// Skip if running on ElasticPress.io
		if (isEpIo()) {
			test.skip();
		}

		// Make sure the proxy is deactivated.
		await deactivatePlugin(loggedInPage, 'elasticpress-proxy', 'wpCli');

		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress');

		await loggedInPage.getByRole('button', { name: 'Live Search' }).click();
		await loggedInPage.getByRole('button', { name: 'Instant Results' }).click();
		await expect(loggedInPage.locator('.components-notice.is-error')).toContainText(
			'To use this feature you need',
		);
		await expect(loggedInPage.locator('.components-form-toggle__input')).toBeDisabled();
	});

	test.describe('Instant Results Available', () => {
		test.beforeAll(async () => {
			await wpCli('plugin activate woocommerce', true);

			if (!isEpIo()) {
				await wpCli('plugin activate elasticpress-proxy', true);
			}
		});

		/**
		 * Test that the feature can be activated and it can sync automatically.
		 * Also, it can show a warning when using a custom PHP proxy
		 */
		test('Can activate the feature and sync automatically', async ({ loggedInPage }) => {
			await maybeDisableFeature('instant-results');

			// Can see the warning if using custom proxy
			await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress');
			await loggedInPage.getByRole('button', { name: 'Live Search' }).click();
			await loggedInPage.getByRole('button', { name: 'Instant Results' }).click();

			const noticeShould = isEpIo() ? 'not.contain.text' : 'contain.text';

			await expect(loggedInPage.locator('.components-form-toggle__input')).not.toBeDisabled();
			await expect(loggedInPage.locator('.components-notice').first()).toHaveText(
				noticeShould === 'contain.text'
					? /You are using a custom proxy/
					: /^(?!.*You are using a custom proxy)/,
			);

			await loggedInPage.getByRole('checkbox', { name: 'Enable' }).click();
			loggedInPage.on('dialog', (dialog) => dialog.accept());
			await loggedInPage.getByRole('button', { name: 'Save and sync now' }).click();

			// Wait for sync messages
			await loggedInPage.getByRole('button', { name: 'Log' }).click();
			const syncMessages = loggedInPage.locator('.ep-sync-messages');
			await expect(syncMessages).toContainText('Mapping sent');
			await expect(syncMessages).toContainText('Sync complete');

			const result = await wpCli('elasticpress list-features');
			expect(result.toString()).toContain('instant-results');
		});

		test.describe('Instant Results activated', () => {
			test.beforeAll(async () => {
				await setCustomPostTypes();
			});

			/**
			 * Test that the instant results list is visible
			 * It can display the number of test results
			 * It can show the modal in the same state after a reload
			 * Can change the URL when search term is changed
			 */
			test('Can see instant results elements, URL changes, reload, and update after changing search term', async ({
				loggedInPage,
			}) => {
				await maybeEnableFeature('instant-results');
				await wpCli('elasticpress put-search-template', true);

				/**
				 * Add product category facet to test the labelling of facets
				 * with the same name.
				 */
				await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress');
				const apiResponsePromise = loggedInPage.waitForResponse(
					'**/wp-json/elasticpress/v1/features*',
				);

				await loggedInPage.getByRole('button', { name: 'Live Search' }).click();
				await loggedInPage.getByRole('button', { name: 'Instant Results' }).click();
				await addInstantResultFilter(loggedInPage, 'post type');
				await loggedInPage.getByRole('button', { name: 'Save changes' }).click();

				await apiResponsePromise;

				/**
				 * Perform a search.
				 */
				await loggedInPage.goto('/');

				const responsePromise = instantResultRequestPromise(loggedInPage, 'search=new');

				await searchFor(loggedInPage, 'new');

				const searchModal = loggedInPage.locator('.ep-search-modal');
				await expect(searchModal).toBeVisible(); // Should be visible immediately
				// await expect(searchModal.locator('.ep-search-results__title')).toContainText(
				// 	'Loading results',
				// );
				await expect(loggedInPage).toHaveURL(/.*search=new/);

				await responsePromise;

				await expect(searchModal).toContainText('new');
				// Show the number of results
				await expect(searchModal.locator('.ep-search-results__title')).toContainText(/\d+/);

				/**
				 * The Category facet should specify its searchable post types.
				 */
				const postTypeFacet = loggedInPage.locator('.ep-search-panel').filter({
					has: loggedInPage
						.locator('.ep-search-panel__button')
						.filter({ hasText: 'Type' }),
				});
				await expect(postTypeFacet).toContainText('Post');
				await expect(postTypeFacet).toContainText('Movie');
				await expect(postTypeFacet).not.toContainText('Album');

				await loggedInPage.locator('#ep-search-post-type-post').click();
				await expect(loggedInPage).toHaveURL(/.*ep-post_type=post/);

				// Show the modal in the same state after a reload
				await loggedInPage.reload();
				await expect(searchModal.locator('.ep-search-results__title')).toContainText(
					'Loading results',
				);
				await expect(loggedInPage).toHaveURL(/.*search=new/);
				await responsePromise;
				await expect(searchModal).toBeVisible();
				await expect(searchModal).toContainText('new');

				// Update the results when search term is changed
				await searchModal.locator('.ep-search-input').fill('test');
				await responsePromise;
				await expect(searchModal).toBeVisible();
				await expect(searchModal).toContainText('test');
				await expect(loggedInPage).toHaveURL(/.*search=test/);

				await loggedInPage.locator('#wp-admin-bar-debug-bar').click();
				await expect(loggedInPage.locator('#querylist')).toBeVisible();
			});

			test('Is possible to filter the number of results', async ({ loggedInPage }) => {
				/**
				 * The number of results should match the posts per page
				 * setting by default.
				 */
				await maybeEnableFeature('instant-results');
				await goToAdminPage(loggedInPage, 'options-reading.php');
				const perPageInput = loggedInPage.locator('input[name="posts_per_page"]');
				const perPage = await perPageInput.inputValue();

				await loggedInPage.goto('/');
				await searchFor(loggedInPage, 'block');
				await expect(loggedInPage).toHaveURL(new RegExp(`per_page=${perPage}`));

				/**
				 * Activate test plugin with filter.
				 */
				await activatePlugin(loggedInPage, 'filter-instant-results-per-page', 'wpCli');

				/**
				 * On searching the per_page parameter should reflect the
				 * filtered value.
				 */
				await loggedInPage.goto('/');
				await searchFor(loggedInPage, 'block');
				await expect(loggedInPage).toHaveURL(/.*per_page=3/);
			});

			test('Can filter results by price', async ({ loggedInPage }) => {
				/**
				 * Add price range facet.
				 */
				await maybeEnableFeature('instant-results');
				await maybeEnableFeature('woocommerce');
				await wpCli('wp elasticpress sync');

				await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress');
				const apiResponsePromise = loggedInPage.waitForResponse(
					'**/wp-json/elasticpress/v1/features*',
				);

				await loggedInPage.getByRole('button', { name: 'Live Search' }).click();
				await loggedInPage.getByRole('button', { name: 'Instant Results' }).click();
				await addInstantResultFilter(loggedInPage, 'price');
				await loggedInPage.getByRole('button', { name: 'Save changes' }).click();

				await apiResponsePromise;

				/**
				 * Perform a search.
				 */
				await loggedInPage.goto('/');
				const responsePromise = instantResultRequestPromise(
					loggedInPage,
					'search=ergonomic',
				);
				await searchFor(loggedInPage, 'ergonomic');
				await expect(loggedInPage.locator('.ep-search-modal')).toBeVisible();
				await responsePromise;
				await expect(loggedInPage.locator('.ep-search-result')).toHaveCount(3);

				/**
				 * Adjusting the price range facet should filter results by price.
				 */
				await loggedInPage.locator('.ep-search-range-slider__thumb-0').focus();
				await loggedInPage.keyboard.press('ArrowRight');

				const priceValues = await loggedInPage
					.locator('.ep-search-price-facet__values')
					.textContent();
				const matches = priceValues?.match(/\$([0-9]*) /);
				const value = matches?.[1];
				expect(value).not.toBe('419');

				await responsePromise;
				await expect(loggedInPage).toHaveURL(new RegExp(`min_price=${value}`));
				await expect(loggedInPage.locator('.ep-search-result')).toHaveCount(2);

				/**
				 * Clearing the filter should return the unfiltered results.
				 */
				await loggedInPage
					.locator('.ep-search-tokens button')
					.filter({ hasText: value })
					.click();
				await responsePromise;
				await expect(loggedInPage.locator('.ep-search-result')).toHaveCount(3);
			});

			test('Is possible to manually open Instant Results with a plugin', async ({
				loggedInPage,
			}) => {
				/**
				 * Activate test plugin with JavaScript.
				 */
				await maybeEnableFeature('instant-results');
				await activatePlugin(loggedInPage, 'open-instant-results-with-buttons', 'wpCli');

				/**
				 * Create a post with a Buttons block.
				 */
				await publishPost(
					loggedInPage,
					{
						title: `Test openModal()`,
						content: `Testing openModal()
					<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#">Search "Block"</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons -->`,
					},
					true,
					true,
				);

				/**
				 * Click the button.
				 */
				const responsePromise = instantResultRequestPromise(loggedInPage, 'search=block');
				await loggedInPage.locator('.wp-block-button__link').click();

				/**
				 * Instant Results should be open and populated with our search term.
				 */
				await responsePromise;
				const searchModal = loggedInPage.locator('.ep-search-modal');
				await expect(searchModal).toBeVisible();
				await expect(searchModal.locator('.ep-search-input')).toHaveValue('block');
				await expect(searchModal.locator('.ep-search-results__title')).toContainText(
					'block',
				);
			});

			test('Can filter the result template', async ({ loggedInPage }) => {
				/**
				 * Activate test plugin with filter.
				 */
				await maybeEnableFeature('instant-results');
				await activatePlugin(loggedInPage, 'custom-instant-results-template', 'wpCli');

				/**
				 * Perform a search.
				 */
				const responsePromise = instantResultRequestPromise(loggedInPage, 'search=blog');
				await loggedInPage.goto('/');
				await searchFor(loggedInPage, 'blog');
				await expect(loggedInPage.locator('.ep-search-modal')).toBeVisible();
				await responsePromise;

				/**
				 * Results should use the filtered template with a custom class.
				 */
				await expect(loggedInPage.locator('.my-custom-result').first()).toBeVisible();
				await expect(loggedInPage.locator('.ep-search-result')).not.toBeVisible();
			});

			test('Can display a suggestion', async ({ loggedInPage }) => {
				await maybeEnableFeature('instant-results');
				await maybeEnableFeature('did-you-mean');

				await wpCli('wp elasticpress sync --setup --yes');

				/**
				 * Perform a search.
				 */
				const responsePromise = instantResultRequestPromise(
					loggedInPage,
					'search=wordpless',
				);
				await loggedInPage.goto('/');
				await searchFor(loggedInPage, 'wordpless');
				await expect(loggedInPage.locator('.ep-search-modal')).toBeVisible();
				await responsePromise;
				await expect(loggedInPage.locator('.ep-search-suggestion a')).toHaveText(
					'wordpress',
				);
			});

			test('Is possible to set the default post type from a search form', async ({
				loggedInPage,
			}) => {
				await maybeEnableFeature('instant-results');

				await createProductSearchWidget(loggedInPage);

				/**
				 * If the Post Type filter is in use, entering a new search
				 * term should reset post type the filter.
				 */
				await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress');
				const apiResponsePromise = loggedInPage.waitForResponse(
					'**/wp-json/elasticpress/v1/features*',
				);
				await loggedInPage.getByRole('button', { name: 'Live Search' }).click();
				await loggedInPage.getByRole('button', { name: 'Instant Results' }).click();
				await addInstantResultFilter(loggedInPage, 'post type');
				await loggedInPage.getByRole('button', { name: 'Save changes' }).click();
				await apiResponsePromise;

				await loggedInPage.goto('/');
				const responsePromise = instantResultRequestPromise(loggedInPage, 'search=heavy');
				const productSearchBlock = loggedInPage
					.locator('.wc-block-product-search,.wp-block-search')
					.last();
				await productSearchBlock.locator('input[type="search"]').fill('heavy');
				await loggedInPage.keyboard.press('Enter');
				await expect(loggedInPage.locator('.ep-search-modal')).toBeVisible();
				await responsePromise;
				await expect(loggedInPage).toHaveURL(/.*post_type=product/);
				await loggedInPage.locator('.ep-search-input').fill(' duty');
				await loggedInPage.waitForTimeout(300);
				await responsePromise;
				await expect(loggedInPage).not.toHaveURL(/.*post_type=product/);

				/**
				 * If the Post Type filter is not in use, entering a new search
				 * term should not reset the post type filter.
				 */
				await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress');
				const apiResponsePromise2 = loggedInPage.waitForResponse(
					'**/wp-json/elasticpress/v1/features*',
				);
				await loggedInPage.getByRole('button', { name: 'Live Search' }).click();
				await loggedInPage.getByRole('button', { name: 'Instant Results' }).click();
				await loggedInPage
					.locator('.components-form-token-field__token')
					.filter({ hasText: 'Post type' })
					.locator('button')
					.click();
				await loggedInPage.getByRole('button', { name: 'Save changes' }).click();
				await apiResponsePromise2;

				await loggedInPage.goto('/');
				const responsePromise2 = instantResultRequestPromise(loggedInPage, 'search=ergo');
				const productSearchBlock2 = loggedInPage
					.locator('.wc-block-product-search,.wp-block-search')
					.last();
				await productSearchBlock2.locator('input[type="search"]').fill('ergo');
				await loggedInPage.keyboard.press('Enter');
				await expect(loggedInPage.locator('.ep-search-modal')).toBeVisible();
				await responsePromise2;
				await expect(loggedInPage).toHaveURL(/.*post_type=product/);
				await loggedInPage.locator('.ep-search-input').fill(' duty');
				await loggedInPage.waitForTimeout(300);
				await responsePromise2;
				await expect(loggedInPage).toHaveURL(/.*post_type=product/);
			});

			test('Is possible to filter the taxonomy terms', async ({ loggedInPage }) => {
				/**
				 * Activate test plugin.
				 */
				await maybeEnableFeature('instant-results');
				await setCustomPostTypes();
				await activatePlugin(
					loggedInPage,
					'filter-instant-results-category-terms',
					'wpCli',
				);
				await wpCli('elasticpress put-search-template', true);

				await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress');
				const apiResponsePromise = loggedInPage.waitForResponse(
					'**/wp-json/elasticpress/v1/features*',
				);

				await loggedInPage.getByRole('button', { name: 'Live Search' }).click();
				await loggedInPage.getByRole('button', { name: 'Instant Results' }).click();
				await addInstantResultFilter(loggedInPage, '(category)');
				await loggedInPage.getByRole('button', { name: 'Save changes' }).click();

				await apiResponsePromise;

				/**
				 * Perform a search.
				 */
				const responsePromise = instantResultRequestPromise(loggedInPage, 'search=block');
				await loggedInPage.goto('/');
				await searchFor(loggedInPage, 'block');
				const response = await responsePromise;
				console.log(await response.json());

				const output = await wpCliEval(
					`
					$output = WP_CLI::runcommand( 'elasticpress list-features', [ 'return' => 'all', 'exit_error' => false ] );
					print_r( $output );

					$output = WP_CLI::runcommand( 'plugin list', [ 'return' => 'all', 'exit_error' => false ] );
					print_r( $output );

					$posts = new \\WP_Query(
						[
							'post_type' => 'post',
							'posts_per_page' => -1,
							's' => 'markup html',
						]
					);
					print_r( $posts );
					`,
				);
				console.log(output.toString());

				/**
				 * The number of terms displayed in the filter should be one.
				 */
				await expect(loggedInPage.locator('[id^="ep-search-tax-category-"]')).toHaveCount(
					1,
				);

				await deactivatePlugin(
					loggedInPage,
					'filter-instant-results-category-terms',
					'wpCli',
				);
			});
		});

		test('Is possible to filter the arguments schema', async ({ loggedInPage }) => {
			/**
			 * The number of results should match the posts per page
			 * setting by default.
			 */
			await maybeEnableFeature('instant-results');
			const responsePromise = instantResultRequestPromise(loggedInPage, 'search=block');

			/**
			 * Activate test plugin.
			 */
			await activatePlugin(loggedInPage, 'filter-instant-results-args-schema', 'wpCli');

			/**
			 * Perform a search.
			 */
			await loggedInPage.goto('/');
			await searchFor(loggedInPage, 'block');
			await responsePromise;

			/**
			 * Results should be sorted by date.
			 */
			await expect(loggedInPage.locator('#ep-sort').first()).toHaveValue('date_desc');
		});
	});
});
