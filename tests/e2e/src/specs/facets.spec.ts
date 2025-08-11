import { test, expect, Locator, Page } from '../fixtures';
import {
	activatePlugin,
	deactivatePlugin,
	goToAdminPage,
	wpCliEval,
	updateWeighting,
	emptyWidgets,
	createClassicWidget,
	wpCli,
	setCustomPostTypes,
} from '../utils';
import {
	openBlockInserter,
	getBlocksList,
	insertBlock,
	openBlockSettingsSidebar,
	supportsBlockColors,
	supportsBlockTypography,
	supportsBlockDimensions,
} from '../block-editor';

test.describe('Facets Feature', { tag: '@group2' }, () => {
	const setSlider = async (page: Page, block: Locator, min: number, max: number) => {
		const firstSliderThumb = block.locator('.ep-range-slider__thumb').first();
		const currentMin = parseInt(
			(await firstSliderThumb.getAttribute('aria-valuenow')) || '1',
			10,
		);
		const minDirection = currentMin < min ? 'ArrowRight' : 'ArrowLeft';
		const promisesMin: Promise<void>[] = [];
		await firstSliderThumb.focus();
		for (let i = 0; i < Math.abs(currentMin - min); i++) {
			promisesMin.push(page.keyboard.press(minDirection));
		}
		await Promise.all(promisesMin);

		const secondSliderThumb = block.locator('.ep-range-slider__thumb').last();
		const currentMax = parseInt(
			(await secondSliderThumb.getAttribute('aria-valuenow')) || '20',
			10,
		);
		const maxDirection = currentMax > max ? 'ArrowLeft' : 'ArrowRight';
		const promisesMax: Promise<void>[] = [];
		await secondSliderThumb.focus();
		for (let i = 0; i < Math.abs(currentMax - max); i++) {
			promisesMax.push(page.keyboard.press(maxDirection));
		}
		await Promise.all(promisesMax);
	};

	test.beforeAll(async () => {
		// Ensure the feature is active, perform a sync, and remove test posts
		await wpCliEval(`
			\\ElasticPress\\Features::factory()->activate_feature('facets' );
			WP_CLI::runcommand( 'elasticpress sync --setup --yes' );
			$posts = new \\WP_Query(
				[
					's'            => 'A new',
					'ep_integrate' => false,
					'fields'       => 'ids',
				]
			);
			foreach ( $posts->posts as $post ) {
				wp_delete_post( $post, true );
			}
			activate_plugin( 'add-meta-fields.php' );
		`);

		await updateWeighting();
	});

	test.beforeEach(async () => {
		// Delete all widgets and ensure Classic Widgets is deactivated before each test
		await emptyWidgets();
		await wpCli('plugin deactivate classic-widgets');
	});

	test('Can insert, configure, and use the Filter by Taxonomy block', async ({
		loggedInPage,
	}) => {
		// Insert two Filter blocks
		await goToAdminPage(loggedInPage, 'widgets.php');
		await openBlockInserter(loggedInPage);
		await expect(await getBlocksList(loggedInPage)).toContainText('Filter by Taxonomy');
		await insertBlock(loggedInPage, 'Filter by Taxonomy');
		await insertBlock(loggedInPage, 'Filter by Taxonomy');

		const firstBlock = loggedInPage.locator('.wp-block.wp-block-elasticpress-facet').first();
		const secondBlock = loggedInPage.locator('.wp-block.wp-block-elasticpress-facet').last();

		// Verify that the blocks are inserted into the editor, and contain the expected content
		await expect(firstBlock.locator('select')).toContainText('Select taxonomy');
		await expect(secondBlock.locator('select')).toContainText('Select taxonomy');

		let facetResponse;

		// Set the first block to use Categories
		await firstBlock.click();
		await openBlockSettingsSidebar(loggedInPage);
		facetResponse = loggedInPage.waitForResponse(
			'/wp-json/wp/v2/block-renderer/elasticpress/facet*',
		);
		await loggedInPage
			.locator('.block-editor-block-inspector select')
			.first()
			.selectOption('category');
		await facetResponse;

		// Set the last block to use Tags and sort by name in ascending order
		await secondBlock.click();
		await loggedInPage
			.locator('.block-editor-block-inspector select')
			.first()
			.selectOption('post_tag');
		facetResponse = loggedInPage.waitForResponse(
			'/wp-json/wp/v2/block-renderer/elasticpress/facet*',
		);
		await loggedInPage
			.locator('.block-editor-block-inspector select')
			.last()
			.selectOption('name/asc');
		await facetResponse;

		// Verify the blocks have the expected output in the editor based on the block's settings
		await expect(firstBlock.locator('input')).toHaveAttribute(
			'placeholder',
			'Search Categories',
		);
		await expect(secondBlock.locator('input')).toHaveAttribute('placeholder', 'Search Tags');

		// Verify the display count setting on the editor
		await expect(secondBlock.locator('.term', { hasText: /\(\d+\)$/ })).not.toBeVisible();
		facetResponse = loggedInPage.waitForResponse(
			'/wp-json/wp/v2/block-renderer/elasticpress/facet*',
		);
		await loggedInPage
			.locator('.block-editor-block-inspector .components-form-toggle__input')
			.click();
		await facetResponse;
		await expect(secondBlock.locator('.term', { hasText: /\(\d+\)$/ }).last()).toBeVisible();

		// Test that the block supports changing styles
		await supportsBlockColors(loggedInPage, secondBlock, true);
		await supportsBlockTypography(loggedInPage, secondBlock, true);
		await supportsBlockDimensions(loggedInPage, secondBlock, true);

		// Save widgets and visit the front page
		const sidebarResponse = loggedInPage.waitForResponse('/wp-json/wp/v2/sidebars/*');
		await loggedInPage
			.locator('.edit-widgets-header__actions button:has-text("Update")')
			.click();
		await sidebarResponse;
		await loggedInPage.goto('/');

		// Verify the blocks have the expected output on the front-end based on their settings
		const firstBlockFrontend = loggedInPage.locator('.wp-block-elasticpress-facet').first();
		const secondBlockFrontend = loggedInPage.locator('.wp-block-elasticpress-facet').last();
		await expect(firstBlockFrontend.locator('input')).toHaveAttribute(
			'placeholder',
			'Search Categories',
		);
		await expect(
			firstBlockFrontend.locator('.term', { hasText: /\(\d+\)$/ }),
		).not.toBeVisible();
		await expect(secondBlockFrontend.locator('input')).toHaveAttribute(
			'placeholder',
			'Search Tags',
		);
		await expect(
			secondBlockFrontend.locator('.term', { hasText: /\(\d+\)$/ }).last(),
		).toBeVisible();

		// Verify that the block supports changing styles
		await supportsBlockColors(loggedInPage, secondBlockFrontend);
		await supportsBlockTypography(loggedInPage, secondBlockFrontend);
		await supportsBlockDimensions(loggedInPage, secondBlockFrontend);

		// Typing in the input should filter the list of terms for that block without affecting other blocks
		const firstBlockSearch = firstBlockFrontend.locator('input');
		await firstBlockSearch.pressSequentially('Parent C');
		await expect(
			firstBlockFrontend.locator('.term', { hasText: 'Parent Category' }),
		).not.toContainClass('hide');

		const childrenTerms = await firstBlockFrontend
			.locator('.term', { hasText: 'Child Category' })
			.all();
		for await (const childTerm of childrenTerms) {
			await expect(childTerm).toContainClass('hide');
		}
		expect(secondBlockFrontend.locator('.term.hide')).not.toBeVisible();

		// Clearing the input should restore previously hidden terms and allow them to be selected
		await firstBlockSearch.clear();
		await firstBlockFrontend.locator('.term', { hasText: 'Classic' }).click();

		// Selecting that term should lead to the correct URL, mark the correct item as checked, and all articles being displayed should have the selected category
		await expect(loggedInPage).toHaveURL(/ep_filter_category=classic/);
		await expect(
			firstBlockFrontend.locator('.term', { hasText: 'Classic' }).locator('.ep-checkbox'),
		).toContainClass('checked');

		const articlesCount = await loggedInPage.locator('article').count();
		const classicTermsCount = await loggedInPage
			.locator('.cat-links a', { hasText: 'Classic' })
			.count();
		expect(articlesCount).toBe(classicTermsCount);

		// Facets should continue to apply across pagination
		await loggedInPage.locator('.page-numbers.next').click();
		await expect(loggedInPage).toHaveURL(/page\/2/);
		await expect(loggedInPage).toHaveURL(/ep_filter_category=classic/);

		const articlesCount2 = await loggedInPage.locator('article').count();
		const classicTermsCount2 = await loggedInPage
			.locator('.cat-links a', { hasText: 'Classic' })
			.count();
		expect(articlesCount2).toBe(classicTermsCount2);

		// When another facet is selected pagination should reset and results should be filtered by both selections
		await secondBlockFrontend.locator('.term:has-text("template")').click();
		await expect(loggedInPage).toHaveURL(/ep_filter_category=classic/);
		await expect(loggedInPage).toHaveURL(/ep_filter_post_tag=template/);
		await expect(loggedInPage).not.toHaveURL(/page\/2/);
		await expect(
			firstBlockFrontend.locator('.term:has-text("Classic") .ep-checkbox'),
		).toContainClass('checked');
		await expect(
			secondBlockFrontend.locator('.term:has-text("template") .ep-checkbox'),
		).toContainClass('checked');

		const filteredArticlesCount = await loggedInPage.locator('article').count();
		const classicTermsCount3 = await loggedInPage
			.locator('.cat-links a', { hasText: 'Classic' })
			.count();
		const templateTermsCount = await loggedInPage
			.locator('.cat-links a', { hasText: 'Classic' })
			.count();
		expect(filteredArticlesCount).toBe(classicTermsCount3);
		expect(filteredArticlesCount).toBe(templateTermsCount);

		// Clicking selected facet should remove it while keeping any other facets active
		await secondBlockFrontend.locator('.term:has-text("template")').click();
		await expect(loggedInPage).not.toHaveURL(/ep_filter_post_tag=template/);
		await expect(loggedInPage).toHaveURL(/ep_filter_category=classic/);
	});

	test('Can insert, configure, use, and transform the legacy Facet widget', async ({
		loggedInPage,
	}) => {
		// Add the legacy widget
		await activatePlugin(loggedInPage, 'classic-widgets', 'wpCli');
		await createClassicWidget(loggedInPage, 'ep-facet', [
			{
				name: 'title',
				value: 'My facet',
				type: 'text',
			},
			{
				name: 'facet',
				value: 'post_tag',
				type: 'select',
			},
			{
				name: 'orderby',
				value: 'name',
				type: 'select',
			},
			{
				name: 'order',
				value: 'asc',
				type: 'select',
			},
		]);

		// Verify the widget has the expected output on the front-end based on the widget's settings
		await loggedInPage.goto('/');
		const widget = loggedInPage.locator('.widget_ep-facet').first();
		await expect(widget.locator('input')).toHaveAttribute('placeholder', 'Search Tags');

		// Visit the block-based widgets screen
		await deactivatePlugin(loggedInPage, 'classic-widgets', 'wpCli');
		await goToAdminPage(loggedInPage, 'widgets.php');

		// Check that the widget is inserted in to the editor as a Legacy Widget block
		const legacyWidget = loggedInPage
			.locator('.wp-block-legacy-widget:has-text("ElasticPress - Filter by Taxonomy")')
			.first();
		await legacyWidget.click();

		// Transform the legacy widget into the block
		await loggedInPage.locator('.block-editor-block-switcher button').click();
		await loggedInPage
			.locator(
				'.block-editor-block-switcher__popover .editor-block-list-item-elasticpress-facet',
			)
			.click();

		// Check that the widget has been transformed into the correct blocks
		await expect(loggedInPage.locator('.wp-block-heading:has-text("My facet")')).toBeVisible();
		const block = loggedInPage.locator('.wp-block-elasticpress-facet').first();
		await expect(block).toBeVisible();

		// Check that the block's settings match the widget's
		await block.click();
		await expect(loggedInPage.getByRole('combobox', { name: 'Filter By' })).toHaveValue(
			'post_tag',
		);
		await expect(loggedInPage.getByRole('combobox', { name: 'Order By' })).toHaveValue(
			'name/asc',
		);
	});

	test('Does not change post types being displayed', async ({ loggedInPage }) => {
		await setCustomPostTypes();

		// Give Elasticsearch some time to process the post
		await loggedInPage.waitForTimeout(2000);

		// Blog page
		await loggedInPage.goto('/');
		await expect(
			loggedInPage.locator('.site-content article h2:has-text("A new page")'),
		).not.toBeVisible();
		await expect(
			loggedInPage.locator('.site-content article h2:has-text("A new post")'),
		).toBeVisible();
		await expect(
			loggedInPage.locator('.site-content article h2:has-text("A new movie")'),
		).not.toBeVisible();

		// Specific taxonomy archive
		await loggedInPage.goto('/blog/genre/action/');
		await expect(
			loggedInPage.locator('.site-content article h2:has-text("A new page")'),
		).not.toBeVisible();
		await expect(
			loggedInPage.locator('.site-content article h2:has-text("A new post")'),
		).not.toBeVisible();
		await expect(
			loggedInPage.locator('.site-content article h2:has-text("A new movie")'),
		).toBeVisible();

		// Search
		await loggedInPage.goto('/?s=new');
		await expect(
			loggedInPage.locator('.site-content article h2:has-text("A new page")'),
		).toBeVisible();
		await expect(
			loggedInPage.locator('.site-content article h2:has-text("A new post")'),
		).toBeVisible();
		await expect(
			loggedInPage.locator('.site-content article h2:has-text("A new movie")'),
		).toBeVisible();
	});

	test.describe('Filter by Metadata block', () => {
		test.beforeAll(async () => {
			await wpCliEval(`
				$facet_meta_query = new WP_Query(
					[
						'post_type'      => 'post',
						'post_status'    => 'publish',
						'posts_per_page' => -1,
						'meta_query'     => [
							[
								'key'     => 'facet_by_meta_tests',
								'value'   => '1',
								'compare' => '=',
							],
						],
						'orderby'        => 'title',
						'order'          => 'ASC',
					]
				);

				$posts = $facet_meta_query->posts;
				foreach ( $posts as $post ) {
					wp_delete_post( $post->ID, true );
				}

				for ( $i = 1; $i <= 20; $i++ ) {
					wp_insert_post(
						[
							'post_title'  => "Facet By Meta Post {$i}",
							'post_status' => 'publish',
							'meta_input'  => [
								'facet_by_meta_tests' => 1,
								'meta_field_1'        => "Meta Value (1) - {$i}",
								'meta_field_2'        => "Meta Value (2) - {$i}",
							],
						]
					);
				}
			`);
		});

		test('Can insert, configure, and use the Filter by Metadata block', async ({
			loggedInPage,
		}) => {
			// Insert a Filter by Metadata block
			await goToAdminPage(loggedInPage, 'widgets.php');
			await openBlockInserter(loggedInPage);
			await expect(await getBlocksList(loggedInPage)).toContainText('Filter by Metadata');
			await insertBlock(loggedInPage, 'Filter by Metadata');
			const firstBlock = loggedInPage
				.locator('.wp-block.wp-block-elasticpress-facet-meta')
				.last();

			// Configure the block
			await firstBlock.click();
			await openBlockSettingsSidebar(loggedInPage);
			await loggedInPage
				.locator('.block-editor-block-inspector input[type="text"]')
				.fill('Search Meta 1');

			let facetResponse;
			facetResponse = loggedInPage.waitForResponse(
				'/wp-json/wp/v2/block-renderer/elasticpress/facet-meta*',
			);
			await loggedInPage
				.getByRole('combobox', { name: 'Filter By' })
				.first()
				.selectOption('meta_field_1');
			await facetResponse;

			// Verify that the blocks are inserted into the editor, and contain the expected content
			await expect(firstBlock.locator('input')).toHaveAttribute(
				'placeholder',
				'Search Meta 1',
			);

			// Verify the display count setting on the editor
			await expect(firstBlock.locator('.term', { hasText: /\(\d+\)$/ })).not.toBeVisible();
			facetResponse = loggedInPage.waitForResponse(
				'/wp-json/wp/v2/block-renderer/elasticpress/facet-meta*',
			);
			await loggedInPage
				.locator('.block-editor-block-inspector .components-form-toggle__input')
				.click();
			await facetResponse;
			await expect(await firstBlock.locator('.term', { hasText: /\(\d+\)$/ }).count()).toBe(
				20,
			);

			// Insert a second block
			await openBlockInserter(loggedInPage);
			await expect(await getBlocksList(loggedInPage)).toContainText('Filter by Metadata');
			await insertBlock(loggedInPage, 'Filter by Metadata');
			const secondBlock = loggedInPage
				.locator('.wp-block.wp-block-elasticpress-facet-meta')
				.last();

			// Configure the block
			await secondBlock.click();
			await openBlockSettingsSidebar(loggedInPage);
			await loggedInPage
				.locator('.block-editor-block-inspector input[type="text"]')
				.fill('Search Meta 2');
			await loggedInPage
				.getByRole('combobox', { name: 'Filter By' })
				.first()
				.selectOption('meta_field_2');
			facetResponse = loggedInPage.waitForResponse(
				'/wp-json/wp/v2/block-renderer/elasticpress/facet-meta*',
			);
			await loggedInPage
				.getByRole('combobox', { name: 'Order By' })
				.first()
				.selectOption('name/asc');
			await facetResponse;

			// Verify the block has the expected output in the editor based on the block's settings
			await expect(secondBlock.locator('input')).toHaveAttribute(
				'placeholder',
				'Search Meta 2',
			);

			// Test that the block supports changing styles
			await supportsBlockColors(loggedInPage, secondBlock, true);
			await supportsBlockTypography(loggedInPage, secondBlock, true);
			await supportsBlockDimensions(loggedInPage, secondBlock, true);

			// Save widgets and visit the front page
			const sidebarResponse = loggedInPage.waitForResponse('/wp-json/wp/v2/sidebars/*');
			await loggedInPage
				.locator('.edit-widgets-header__actions button:has-text("Update")')
				.click();
			await sidebarResponse;
			await loggedInPage.goto('/');

			// Verify the blocks have the expected output on the front-end based on their settings
			const firstBlockFrontend = loggedInPage.locator('.wp-block-elasticpress-facet').first();
			const secondBlockFrontend = loggedInPage.locator('.wp-block-elasticpress-facet').last();
			await expect(firstBlockFrontend.locator('input')).toHaveAttribute(
				'placeholder',
				'Search Meta 1',
			);
			await expect(
				await firstBlockFrontend.locator('.term', { hasText: /\(\d+\)$/ }).count(),
			).toBeGreaterThan(0);

			await expect(secondBlockFrontend.locator('input')).toHaveAttribute(
				'placeholder',
				'Search Meta 2',
			);
			await expect(
				secondBlockFrontend.locator('.term', { hasText: /\(\d+\)$/ }),
			).not.toBeVisible();

			// Verify that the block supports changing styles
			await supportsBlockColors(loggedInPage, secondBlockFrontend);
			await supportsBlockTypography(loggedInPage, secondBlockFrontend);
			await supportsBlockDimensions(loggedInPage, secondBlockFrontend);

			// Typing in the input should filter the list of terms for that block without affecting other blocks
			const firstBlockSearch = firstBlockFrontend.locator('input');
			await firstBlockSearch.fill('12');
			await expect(
				firstBlockFrontend.locator('.term:has-text("Meta Value (1) - 12")'),
			).toBeVisible();
			await firstBlockSearch.fill('Meta Value (1) - 13');
			await expect(
				firstBlockFrontend.locator('.term:has-text("Meta Value (1) - 13")'),
			).toBeVisible();

			// Clearing the input should restore previously hidden terms and allow them to be selected
			await firstBlockSearch.clear();
			await firstBlockFrontend.locator('.term:has-text("Meta Value (1) - 20")').click();

			// Selecting that term should lead to the correct URL, mark the correct item as checked, and all articles being displayed should have the selected category
			await expect(loggedInPage).toHaveURL(
				/ep_meta_filter_meta_field_1=Meta%20Value%20\(1\)%20-%2020/,
			);
			await expect(
				firstBlockFrontend.locator('.term:has-text("Meta Value (1) - 20") .ep-checkbox'),
			).toContainClass('checked');
			await expect(
				loggedInPage.locator(
					'.site-content article:nth-of-type(1) h2:has-text("Facet By Meta Post 20")',
				),
			).toBeVisible();

			// When another facet is selected pagination should reset and results should be filtered by both selections
			await secondBlockFrontend.locator('.term:has-text("Meta Value (2) - 20")').click();
			await expect(loggedInPage).toHaveURL(
				/ep_meta_filter_meta_field_1=Meta%20Value%20\(1\)%20-%2020/,
			);
			await expect(loggedInPage).toHaveURL(
				/ep_meta_filter_meta_field_2=Meta%20Value%20\(2\)%20-%2020/,
			);
			await expect(loggedInPage).not.toHaveURL(/page\/2/);
			await expect(
				firstBlockFrontend.locator('.term:has-text("Meta Value (1) - 20") .ep-checkbox'),
			).toContainClass('checked');
			await expect(
				secondBlockFrontend.locator('.term:has-text("Meta Value (2) - 20") .ep-checkbox'),
			).toContainClass('checked');
			await expect(
				loggedInPage.locator(
					'.site-content article:nth-of-type(1) h2:has-text("Facet By Meta Post 20")',
				),
			).toBeVisible();

			// Clicking selected facet should remove it while keeping any other facets active
			await secondBlockFrontend.locator('.term:has-text("Meta Value (2) - 20")').click();
			await expect(loggedInPage).not.toHaveURL(
				/ep_meta_filter_meta_field_2=Meta%20Value%20\(2\)%20-%2020/,
			);
			await expect(loggedInPage).toHaveURL(
				/ep_meta_filter_meta_field_1=Meta%20Value%20\(1\)%20-%2020/,
			);
			await expect(
				secondBlockFrontend.locator(
					'a[aria-disabled="true"]:has-text("Meta Value (2) - 19")',
				),
			).toBeVisible();

			// When Match Type is "any", all options need to be clickable
			await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress');
			const featuresResponse = loggedInPage.waitForResponse(
				'/wp-json/elasticpress/v1/features*',
			);

			await loggedInPage.locator('button:has-text("Core Search")').click();
			await loggedInPage.locator('button:has-text("Filters")').click();
			await loggedInPage
				.locator('label:has-text("Show results that match any selected filter")')
				.click();
			await loggedInPage.locator('button:has-text("Save changes")').click();
			await featuresResponse;

			await loggedInPage.goto('/');
			await secondBlockFrontend
				.locator('.term', { hasText: /^Meta Value \(2\) - 20$/ })
				.click();
			await secondBlockFrontend
				.locator('.term', { hasText: /^Meta Value \(2\) - 1$/ })
				.click();
			await expect(
				loggedInPage.locator('.wp-block-elasticpress-facet a[aria-disabled="true"]'),
			).not.toBeVisible();
			await expect(
				loggedInPage.locator('.site-content article h2:has-text("Facet By Meta Post 20")'),
			).toBeVisible();
			await expect(
				loggedInPage.locator('.site-content article h2:has-text("Facet By Meta Post 1")'),
			).toBeVisible();
		});
	});

	test.describe('Filter by Metadata Range block', () => {
		test.beforeAll(async () => {
			// Clean up sample posts
			await wpCliEval(`
				$facet_meta_query = new WP_Query(
					[
						'post_type'      => 'post',
						'post_status'    => 'publish',
						'posts_per_page' => -1,
						'meta_query'     => [
							[
								'key'     => '_facet_by_meta_range_tests',
								'value'   => '1',
								'compare' => '=',
							],
						],
						'orderby'        => 'title',
						'order'          => 'ASC',
					]
				);

				$posts = $facet_meta_query->posts;
				foreach ( $posts as $post ) {
					wp_delete_post( $post->ID, true );
				}

				for ( $i = 1; $i <= 20; $i++ ) {
					wp_insert_post(
						[
							'post_date_gmt' => "-20 days + {$i} days",
							'post_title'    => "Facet By Meta Range Post {$i}",
							'post_status'   => 'publish',
							'meta_input'    => [
								'_facet_by_meta_range_tests' => 1,
								'numeric_meta_field'        => $i,
								'non_numeric_meta_field'    => "Non-numeric value {$i}",
							],
						]
					);
				}
			`);
		});

		test('Can insert, configure, and use the Filter by Metadata Range block', async ({
			loggedInPage,
		}) => {
			// Insert a Filter by Metadata Range block
			await goToAdminPage(loggedInPage, 'widgets.php');
			await openBlockInserter(loggedInPage);
			await expect(await getBlocksList(loggedInPage)).toContainText(
				'Filter by Metadata Range - Beta',
			);
			await insertBlock(loggedInPage, 'Filter by Metadata Range - Beta');
			const block = loggedInPage
				.locator('.wp-block.wp-block-elasticpress-facet-meta-range')
				.last();

			// The block should prompt to select a field
			await expect(block).toContainText('Filter by Metadata Range');
			await expect(block.locator('select')).toBeVisible();

			// After selecting a field a preview should display
			await loggedInPage.waitForResponse('/wp-json/elasticpress/v1/meta-keys*');
			await block.locator('select').selectOption('numeric_meta_field');
			await loggedInPage.waitForResponse('/wp-json/elasticpress/v1/meta-range*');
			await expect(block.locator('.ep-range-facet')).toBeVisible();
			await expect(block.locator('.ep-range-facet__values')).toContainText('1 — 20');

			// Changes to the prefix and suffix should be reflected in the preview
			await block.click();
			await openBlockSettingsSidebar(loggedInPage);
			await loggedInPage
				.locator('.block-editor-block-inspector input[type="text"]')
				.nth(0)
				.fill('$');
			await loggedInPage
				.locator('.block-editor-block-inspector input[type="text"]')
				.nth(1)
				.fill('/day');
			await expect(block.locator('.ep-range-facet__values')).toContainText(
				'$1/day — $20/day',
			);

			// It should be possible to change the field from the block inspector
			await loggedInPage
				.locator('.block-editor-block-inspector select')
				.first()
				.selectOption('non_numeric_meta_field');

			// A non-numeric field should show a warning
			await expect(block).toContainText('Preview unavailable.');

			// Changing the field back should restore a preview
			await loggedInPage
				.locator('.block-editor-block-inspector select')
				.first()
				.selectOption('numeric_meta_field');
			await expect(block.locator('.ep-range-facet')).toBeVisible();

			// Test that the block supports changing styles
			await supportsBlockColors(loggedInPage, block, true);
			await supportsBlockTypography(loggedInPage, block, true);
			await supportsBlockDimensions(loggedInPage, block, true);

			// Insert a regular Filter by Metadata block
			await openBlockInserter(loggedInPage);
			await expect(await getBlocksList(loggedInPage)).toContainText('Filter by Metadata');
			await insertBlock(loggedInPage, 'Filter by Metadata');
			await loggedInPage.locator('.wp-block-elasticpress-facet-meta').last().click();
			await openBlockSettingsSidebar(loggedInPage);
			await loggedInPage
				.locator('.block-editor-block-inspector select')
				.first()
				.selectOption('non_numeric_meta_field');

			// Save widgets and visit the front page
			const sidebarResponse = loggedInPage.waitForResponse('/wp-json/wp/v2/sidebars*');
			await loggedInPage
				.locator('.edit-widgets-header__actions button:has-text("Update")')
				.click();
			await sidebarResponse;
			await loggedInPage.goto('/');

			// The block should be rendered on the front end and display the prefix and suffix
			const blockFrontend = loggedInPage.locator('.wp-block-elasticpress-facet').first();
			await expect(blockFrontend.locator('.ep-range-facet')).toBeVisible();
			await expect(await blockFrontend.locator('.ep-range-slider__thumb').count()).toBe(2);
			await expect(blockFrontend).toContainText('$1/day — $20/day');
			await expect(blockFrontend.locator('.ep-range-facet__action a')).not.toBeVisible();

			// Verify that the block supports changing styles
			await supportsBlockColors(loggedInPage, blockFrontend);
			await supportsBlockTypography(loggedInPage, blockFrontend);
			await supportsBlockDimensions(loggedInPage, blockFrontend);

			// Selecting a range and pressing Filter should filter the results
			await setSlider(loggedInPage, blockFrontend, 9, 12);

			await expect(blockFrontend).toContainText('$9/day — $12/day');
			await blockFrontend.locator('button').first().click();
			await expect(loggedInPage.locator('.post')).toHaveCount(4);
			await expect(loggedInPage).toHaveURL(/ep_meta_range_filter_numeric_meta_field_min=9/);
			await expect(loggedInPage).toHaveURL(/ep_meta_range_filter_numeric_meta_field_max=12/);
			await expect(blockFrontend.locator('.ep-range-facet__action a')).toBeVisible();
			await expect(
				loggedInPage.locator('.post:has-text("Facet By Meta Range Post 9")'),
			).toBeVisible();
			await expect(
				loggedInPage.locator('.post:has-text("Facet By Meta Range Post 10")'),
			).toBeVisible();
			await expect(
				loggedInPage.locator('.post:has-text("Facet By Meta Range Post 11")'),
			).toBeVisible();
			await expect(
				loggedInPage.locator('.post:has-text("Facet By Meta Range Post 12")'),
			).toBeVisible();
			await expect(
				loggedInPage.locator('.post:has-text("Facet By Meta Range Post 14")'),
			).not.toBeVisible();
			await expect(
				loggedInPage.locator('.post:has-text("Facet By Meta Range Post 20")'),
			).not.toBeVisible();

			// After selecting a narrow range of values it should be possible to adjust the filter to a wider range
			await setSlider(loggedInPage, blockFrontend, 7, 14);
			await expect(blockFrontend).toContainText('$7/day — $14/day');
			await blockFrontend.locator('button').first().click();
			await expect(loggedInPage).toHaveURL(/ep_meta_range_filter_numeric_meta_field_min=7/);
			await expect(loggedInPage).toHaveURL(/ep_meta_range_filter_numeric_meta_field_max=14/);
			await expect(
				loggedInPage.locator('.post:has-text("Facet By Meta Range Post 14")'),
			).toBeVisible();

			// Clicking clear should clear the range parameters but not any other facet parameters
			await loggedInPage.locator('.wp-block-elasticpress-facet .term a').first().click();
			await expect(loggedInPage).toHaveURL(/ep_meta_range_filter_numeric_meta_field_min=7/);
			await expect(loggedInPage).toHaveURL(/ep_meta_range_filter_numeric_meta_field_max=14/);
			await expect(loggedInPage).toHaveURL(
				/ep_meta_filter_non_numeric_meta_field=Non-numeric/,
			);
			await blockFrontend.locator('.ep-range-facet__action a').click();
			await expect(loggedInPage).not.toHaveURL(
				/ep_meta_range_filter_numeric_meta_field_min=7/,
			);
			await expect(loggedInPage).not.toHaveURL(
				/ep_meta_range_filter_numeric_meta_field_max=14/,
			);
			await expect(loggedInPage).toHaveURL(
				/ep_meta_filter_non_numeric_meta_field=Non-numeric/,
			);
		});
	});

	test.describe('Facet by Post Type', () => {
		test('Can insert, configure, and use the Facet by Post Type block', async ({
			loggedInPage,
		}) => {
			// Insert a Facet block
			await goToAdminPage(loggedInPage, 'widgets.php');
			await openBlockInserter(loggedInPage);
			await expect(await getBlocksList(loggedInPage)).toContainText('Filter by Post Type');
			await insertBlock(loggedInPage, 'Filter by Post Type');
			const block = loggedInPage
				.locator('.wp-block.wp-block-elasticpress-facet-post-type')
				.last();

			// Configure the block
			await block.click();
			await openBlockSettingsSidebar(loggedInPage);
			await loggedInPage
				.locator('.block-editor-block-inspector input[type="text"]')
				.fill('Search Post Type');

			// Verify the display count setting on the editor
			let facetResponse = loggedInPage.waitForResponse(
				'/wp-json/wp/v2/block-renderer/elasticpress/facet-post-type*',
			);
			await expect(block.locator('.term', { hasText: /\(\d+\)$/ })).not.toBeVisible();
			await loggedInPage
				.locator('.block-editor-block-inspector .components-form-toggle__input')
				.click();
			await facetResponse;
			facetResponse = loggedInPage.waitForResponse(
				'/wp-json/wp/v2/block-renderer/elasticpress/facet-post-type*',
			);
			await expect(
				await block.locator('.term', { hasText: /\(\d+\)$/ }).count(),
			).toBeGreaterThan(0);
			await loggedInPage
				.locator('.block-editor-block-inspector .components-select-control__input')
				.selectOption('name/asc');
			await facetResponse;

			// Test that the block supports changing styles
			await supportsBlockColors(loggedInPage, block, true);
			await supportsBlockTypography(loggedInPage, block, true);
			await supportsBlockDimensions(loggedInPage, block, true);

			// Save widgets and visit the front page
			const sidebarResponse = loggedInPage.waitForResponse('/wp-json/wp/v2/sidebars/*');
			await loggedInPage
				.locator('.edit-widgets-header__actions button:has-text("Update")')
				.click();
			await sidebarResponse;
			await loggedInPage.goto('/');

			// Verify the blocks have the expected output on the front-end based on their settings
			const firstBlockFrontend = loggedInPage.locator('.wp-block-elasticpress-facet').first();
			const termsCount = await firstBlockFrontend.locator('.term').count();
			const termsCounterCount = await firstBlockFrontend
				.locator('.term', { hasText: /\(\d+\)$/ })
				.count();
			await expect(termsCount).toBe(termsCounterCount);

			await firstBlockFrontend.locator('.term:has-text("Post")').click();

			// Verify that the block supports changing styles
			await supportsBlockColors(loggedInPage, firstBlockFrontend);
			await supportsBlockTypography(loggedInPage, firstBlockFrontend);
			await supportsBlockDimensions(loggedInPage, firstBlockFrontend);

			// Selecting that term should lead to the correct URL, mark the correct item as checked, and all articles being displayed should have the selected category
			await expect(loggedInPage).toHaveURL(/ep_post_type_filter=post/);
			await expect(
				firstBlockFrontend.locator('.term:has-text("Post") .ep-checkbox'),
			).toContainClass('checked');

			// Clicking selected facet should remove it while keeping any other facets active
			await firstBlockFrontend.locator('.term:has-text("Post")').click();
			await expect(loggedInPage).not.toHaveURL(/ep_post_type_filter=post/);
		});
	});

	test.describe('Facet by Date', () => {
		test('Can insert, configure, and use the Facet by Date block', async ({ loggedInPage }) => {
			// Insert a Facet block
			await goToAdminPage(loggedInPage, 'widgets.php');
			await openBlockInserter(loggedInPage);
			await expect(await getBlocksList(loggedInPage)).toContainText('Filter by Post Date');
			await insertBlock(loggedInPage, 'Filter by Post Date');
			const block = loggedInPage.locator('.wp-block.wp-block-elasticpress-facet-date').last();

			// Verify that there are 4 options
			await expect(block.locator('.ep-facet-date-form .ep-facet-date-option')).toHaveCount(4);

			await block.click();
			await openBlockSettingsSidebar(loggedInPage);

			// Test that the block supports changing styles
			await supportsBlockColors(loggedInPage, block, true);
			await supportsBlockTypography(loggedInPage, block, true);
			await supportsBlockDimensions(loggedInPage, block, true);

			// Save widgets and visit the front page
			const sidebarResponse = loggedInPage.waitForResponse('/wp-json/wp/v2/sidebars/*');
			await loggedInPage
				.locator('.edit-widgets-header__actions button:has-text("Update")')
				.click();
			await sidebarResponse;
			await loggedInPage.goto('/');

			// Verify the blocks have the expected output on the front-end
			const blockFrontend = loggedInPage.locator('.wp-block-elasticpress-facet-date').first();

			await expect(
				blockFrontend.locator('.ep-facet-date-form .ep-facet-date-option'),
			).toHaveCount(4);

			await expect(
				blockFrontend.locator('.ep-facet-date-form__action-submit:has-text("Filter")'),
			).toBeVisible();

			// Verify that the block supports changing styles
			await supportsBlockColors(loggedInPage, blockFrontend);
			await supportsBlockTypography(loggedInPage, blockFrontend);
			await supportsBlockDimensions(loggedInPage, blockFrontend);

			// Selecting the last 3 months option should lead to the correct URL, mark the correct
			await blockFrontend.locator('.ep-facet-date-option label').first().click();
			await blockFrontend.locator('.wp-element-button').click();

			await expect(loggedInPage).toHaveURL(/ep_date_filter=last-3-months/);
			await expect(
				blockFrontend.locator('.ep-facet-date-option').first().locator('input'),
			).toBeChecked();

			// Verify the custom date range
			await blockFrontend.locator('.ep-facet-date-option').last().locator('label').click();

			await blockFrontend.locator('[name="ep_date_filter_from"]').fill('2023-01-01');
			await blockFrontend.locator('[name="ep_date_filter_to"]').fill('2023-12-31');
			await blockFrontend.locator('.wp-element-button').click();
			await expect(loggedInPage).toHaveURL(/ep_date_filter=2023-01-01,2023-12-31/);

			// Clear filter
			await blockFrontend.locator('.ep-facet-date-form__action-clear').click();
			await expect(loggedInPage).not.toHaveURL(/ep_date_filter/);

			await goToAdminPage(loggedInPage, 'widgets.php');
			await openBlockInserter(loggedInPage);

			// Unselect the Custom Date option
			await block.click();
			await openBlockSettingsSidebar(loggedInPage);
			const facetResponse = loggedInPage.waitForResponse(
				'/wp-json/wp/v2/block-renderer/elasticpress/facet-date*',
			);
			await loggedInPage
				.locator('.block-editor-block-inspector input[type="checkbox"]')
				.uncheck();
			await facetResponse;

			await expect(block.locator('.ep-facet-date-form .ep-facet-date-option')).toHaveCount(3);

			// Save widgets and visit the front page
			const sidebarResponse2 = loggedInPage.waitForResponse('/wp-json/wp/v2/widgets*');
			await loggedInPage
				.locator('.edit-widgets-header__actions button:has-text("Update")')
				.click();
			await sidebarResponse2;
			await loggedInPage.goto('/');

			// Click on the last option and check its last-12-months
			await blockFrontend.locator('.ep-facet-date-option label').last().click();
			await blockFrontend.locator('.wp-element-button').click();

			await expect(loggedInPage).toHaveURL(/ep_date_filter=last-12-months/);
			await expect(
				blockFrontend.locator('.ep-facet-date-option').last().locator('input'),
			).toBeChecked();

			// It should go back to page 1 when selecting a filter in page 2
			await loggedInPage.goto('/page/2');
			await blockFrontend.locator('.ep-facet-date-option label').first().click();
			await blockFrontend.locator('.wp-element-button').click();

			await expect(loggedInPage).toHaveURL(/ep_date_filter=last-3-months/);
			await expect(loggedInPage).not.toHaveURL(/page\/2/);
		});
	});
});
