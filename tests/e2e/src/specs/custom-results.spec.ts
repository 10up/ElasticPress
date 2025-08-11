import { test, expect } from '../fixtures.js';
import { goToAdminPage, publishPost, wpCliEval } from '../utils.js';

test.describe('Custom Results', { tag: '@group2' }, () => {
	const testPost = 'test-post';

	test.beforeEach(async () => {
		await wpCliEval(`
			$ep_pointers = get_posts(
				[
					'post_type'   => 'ep-pointer',
					'numberposts' => 999,
				]
			);
			foreach( $ep_pointers as $pointer ) {
				wp_delete_post( $pointer->ID, true );
			}

			$posts = new \\WP_Query(
				[
					's'            => '${testPost}',
					'ep_integrate' => false,
					'fields'       => 'ids',
					'numberposts'  => 999,
				]
			);
			foreach( $posts->posts as $post ) {
				wp_delete_post( $post, true );
			}
		`);
	});

	test('Can change post position and verify the result on search', async ({ loggedInPage }) => {
		const searchResult: string[] = [];
		const searchTerm = 'Feature';

		await goToAdminPage(loggedInPage, 'post-new.php?post_type=ep-pointer');

		// Set up API intercept
		const apiResponsePromise = loggedInPage.waitForResponse(
			'**/wp-json/elasticpress/v1/pointer_preview*',
		);

		await loggedInPage.locator('#titlewrap input').pressSequentially(searchTerm);
		const apiResponse = await apiResponsePromise;
		await expect(apiResponse.status()).toBe(200);

		// Get initial order of posts
		const initialPostTitles = await loggedInPage
			.locator('.pointers .pointer .title')
			.allTextContents();

		expect(initialPostTitles.length).toBeGreaterThan(0);

		// Perform drag and drop operation, but using keyboard instead of mouse.
		await loggedInPage.locator('.pointers > div').first().locator('.dashicons-menu').focus();
		await loggedInPage.keyboard.press('Space');
		await loggedInPage.keyboard.press('ArrowDown');
		await loggedInPage.keyboard.press('Space');

		const postTitles = await loggedInPage
			.locator('.pointers .pointer .title')
			.allTextContents();
		expect(initialPostTitles).not.toEqual(postTitles); // Make sure the order has changed
		searchResult.push(...postTitles);

		// Get the updated order after drag and drop
		const newPostTitles = await loggedInPage
			.locator('.pointers .pointer .title')
			.allTextContents();
		searchResult.splice(0, searchResult.length, ...newPostTitles);

		// Publish the pointer
		await loggedInPage.click('#publish');
		await loggedInPage.waitForLoadState('networkidle');

		// Verify the search results match the new order
		await loggedInPage.goto(`/?s=${searchTerm}`);

		const searchResultTitlesPromise = await loggedInPage
			.locator('article .entry-title')
			.allTextContents();
		const searchResultTitles = await searchResultTitlesPromise.slice(0, searchResult.length);

		for (let index = 0; index < searchResult.length; index++) {
			expect(searchResultTitles[index]).toBe(searchResult[index]);
		}
	});

	test('Can include the new post in search result and verify the result on search', async ({
		loggedInPage,
	}) => {
		const searchResult: string[] = [];
		const searchTerm = 'Custom Page';

		// Create test post
		await publishPost(loggedInPage, {
			title: testPost,
		});

		await goToAdminPage(loggedInPage, 'post-new.php?post_type=ep-pointer');

		// Set up API intercept for pointer preview
		const previewResponsePromise = loggedInPage.waitForResponse(
			'**/wp-json/elasticpress/v1/pointer_preview*',
		);

		await loggedInPage.locator('#titlewrap input').pressSequentially(searchTerm);
		const previewResponse = await previewResponsePromise;
		await expect(previewResponse.status()).toBe(200);

		// Set up API intercept for pointer search
		const searchResponsePromise = loggedInPage.waitForResponse(
			'**/wp-json/elasticpress/v1/pointer_search*',
		);

		// Search for the post
		await loggedInPage.fill('.search-pointers', testPost);
		const searchResponse = await searchResponsePromise;
		await expect(searchResponse.status()).toBe(200);

		// Add the post to the search results
		await loggedInPage.click('.pointer-result:first-of-type .dashicons-plus.add-pointer');

		// Get the updated list of posts (limiting to first 5 as mentioned in original test)
		const postTitles = await loggedInPage
			.locator('.pointers .pointer:nth-child(-n+5) .title')
			.allTextContents();
		searchResult.push(...postTitles);

		expect(searchResult.length).toBeGreaterThan(0);

		// Publish the pointer
		await loggedInPage.click('#publish');
		await loggedInPage.waitForLoadState('networkidle');

		// Give Elasticsearch some time to update the posts in custom results
		await loggedInPage.waitForTimeout(1000);

		// Verify the search results match the expected order
		await loggedInPage.goto(`/?s=${searchTerm}`);

		const searchResultTitlesPromise = await loggedInPage
			.locator(`article .entry-title`)
			.allTextContents();
		const searchResultTitles = searchResultTitlesPromise.slice(0, searchResult.length);

		for (let index = 0; index < searchResult.length; index++) {
			expect(searchResultTitles[index]).toBe(searchResult[index]);
		}
	});
});
