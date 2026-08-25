import { test, expect, Page } from '../fixtures.js';
import {
	logout,
	goToAdminPage,
	wpCli,
	activatePlugin,
	maybeEnableFeature,
	maybeDisableFeature,
	updateFeatures,
	updateWeighting,
	createUser,
	setPerIndexCycle,
	refreshIndex,
	isEpIo,
	deactivatePlugin,
	openSyncLog,
	wpCliEval,
	getSyncTimeout,
} from '../utils.js';

/**
 * Test suite for WooCommerce feature functionality in ElasticPress.
 *
 * @module WooCommerce
 */
test.describe('WooCommerce Feature', { tag: '@group2' }, () => {
	const userData = {
		username: 'testuser',
		email: 'testuser@example.com',
		firstName: 'John',
		lastName: 'Doe',
		address: '123 Main St',
		city: 'Culver City',
		postCode: '90230',
		phoneNumber: '1234567890',
	};

	const isWcVersionAtLeast = async (minVersion: string) => {
		const wcVersion = (await wpCli('plugin get woocommerce --field=version')).toString();
		return (
			wcVersion.localeCompare(minVersion, undefined, {
				numeric: true,
				sensitivity: 'base',
			}) >= 0
		);
	};

	const checkMainEsQuery = async (loggedInPage: Page) => {
		await expect(
			loggedInPage.locator('.ep-query-debug').filter({
				has: loggedInPage.locator('.ep-query-type', { hasText: 'Main query' }),
			}),
		).toContainText('HTTP 200');
	};

	test.afterAll(async () => {
		await wpCli('plugin deactivate woocommerce');
	});

	test('Can auto-activate the feature', async ({ loggedInPage }) => {
		await deactivatePlugin(loggedInPage, 'woocommerce', 'wpCli');
		await maybeDisableFeature('woocommerce');
		await activatePlugin(loggedInPage, 'woocommerce', 'wpCli');

		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress');
		await loggedInPage
			.locator('.ep-dashboard-outer-tabs .ep-dashboard-tab:has-text("WooCommerce")')
			.click();
		await loggedInPage
			.locator('.group-content .ep-dashboard-tab:has-text("WooCommerce")')
			.click();
		await expect(loggedInPage.locator('.components-form-toggle__input')).toBeChecked();
	});

	test('Can automatically start a sync if activate the feature', async ({ loggedInPage }) => {
		await maybeDisableFeature('woocommerce');

		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress');

		// Intercept API requests
		const apiRequestPromise = loggedInPage.waitForResponse(
			'/wp-json/elasticpress/v1/features*',
		);

		await loggedInPage
			.locator('.ep-dashboard-outer-tabs .ep-dashboard-tab:has-text("WooCommerce")')
			.click();
		await loggedInPage
			.locator('.group-content .ep-dashboard-tab:has-text("WooCommerce")')
			.click();
		await loggedInPage.getByRole('checkbox', { name: 'Enable' }).setChecked(true);
		loggedInPage.on('dialog', (dialog) => dialog.accept());
		await loggedInPage.getByRole('button', { name: 'Save and sync now' }).click();
		await apiRequestPromise;

		await openSyncLog(loggedInPage);
		const syncMessages = loggedInPage.locator('.ep-sync-messages');
		await expect(syncMessages).toContainText('Mapping sent', { timeout: getSyncTimeout() });
		await expect(syncMessages).toContainText('Sync complete', { timeout: getSyncTimeout() });

		const result = await wpCli('elasticpress list-features');
		expect(result.toString()).toContain('woocommerce');
	});

	test('Can fetch products from Elasticsearch in product rivers and category archives', async ({
		loggedInPage,
	}) => {
		await maybeEnableFeature('woocommerce');
		await activatePlugin(loggedInPage, 'enable-debug-bar', 'wpCli');
		await wpCli('option update woocommerce_coming_soon off', true);

		await loggedInPage.goto('/shop/?filter_size=small');
		await checkMainEsQuery(loggedInPage);

		await loggedInPage.goto('/product-category/uncategorized');
		await checkMainEsQuery(loggedInPage);
	});

	test('Can Search Product by Variation SKU', async ({ loggedInPage }) => {
		await activatePlugin(loggedInPage, 'woocommerce', 'wpCli');
		await maybeEnableFeature('woocommerce');

		await updateFeatures('search', {
			active: 1,
			highlight_enabled: '1',
			highlight_excerpt: '1',
			highlight_tag: 'mark',
			highlight_color: '#157d84',
			decaying_enabled: 'disabled_includes_products',
		});

		await updateWeighting({
			product: {
				'meta._variations_skus.value': {
					weight: 1,
					enabled: true,
				},
			},
		});

		await wpCli('elasticpress sync --setup --yes');

		// Give Elasticsearch some time to process
		await loggedInPage.waitForTimeout(2000);

		await loggedInPage.goto('/?s=awesome-aluminum-shoes-variation-sku');
		await expect(loggedInPage.locator('.site-content article:nth-of-type(1) h2')).toContainText(
			'Awesome Aluminum Shoes',
		);
	});

	test.describe('Dashboard', () => {
		test.beforeAll(async () => {
			await wpCli('plugin activate woocommerce');
			// Latest WooCommerce may enable HPOS on this later activation.
			// ElasticPress does not integrate the HPOS orders list.
			await wpCli('wc hpos disable', true);
			await maybeEnableFeature('protected_content');
			await maybeEnableFeature('woocommerce');

			// The tests below place an order as this user and assert on the
			// exact order count, so drop the user and any orders left behind
			// by a previous run.
			await wpCliEval(`
				// Orders are authored by the admin and lose their customer link
				// once the account is deleted, so the billing email entered at
				// checkout is what identifies them.
				$stale_orders = get_posts(
					[
						'post_type'   => 'shop_order',
						'post_status' => 'any',
						'numberposts' => 999,
						'fields'      => 'ids',
						'meta_key'    => '_billing_email',
						'meta_value'  => '${userData.email}',
					]
				);
				foreach ( $stale_orders as $stale_order ) {
					wp_delete_post( $stale_order, true );
				}

				$stale_user = get_user_by( 'login', '${userData.username}' );
				if ( $stale_user ) {
					require_once ABSPATH . 'wp-admin/includes/user.php';
					wp_delete_user( $stale_user->ID );
				}
			`);
		});

		test('Can fetch orders and products from Elasticsearch', async ({ loggedInPage }) => {
			// Sync orders to Elasticsearch
			await wpCli('elasticpress sync --setup --yes');

			// Test orders
			await goToAdminPage(loggedInPage, 'edit.php?post_type=shop_order');
			await checkMainEsQuery(loggedInPage);

			// Test products
			await goToAdminPage(loggedInPage, 'edit.php?post_type=product');
			await checkMainEsQuery(loggedInPage);
		});

		test('Can not display other users orders on the My Account Order page', async ({
			loggedInPage,
		}) => {
			await activatePlugin(loggedInPage, 'enable-debug-bar');

			// Enable payment gateway
			await wpCliEval(`
				$settings = get_option( 'woocommerce_cod_settings', [] );
				if ( ! is_array( $settings ) ) {
					$settings = [];
				}
				$settings['enabled'] = 'yes';
				update_option( 'woocommerce_cod_settings', $settings );
				if ( function_exists( 'WC' ) && WC()->payment_gateways() ) {
					WC()->payment_gateways()->init();
				}
			`);

			// Disable coming soon option
			await wpCli('option update woocommerce_coming_soon off');

			await logout(loggedInPage);

			// Create new user
			await createUser(loggedInPage, {
				username: userData.username,
				email: userData.email,
				login: true,
			});

			// Add product to cart
			await loggedInPage.goto('product/fantastic-silk-knife');
			await loggedInPage.locator('.single_add_to_cart_button').click();

			// Checkout and place order
			await loggedInPage.goto('checkout');
			await loggedInPage
				.locator('#billing-first_name, #billing_first_name')
				.fill(userData.firstName);
			await loggedInPage
				.locator('#billing-last_name, #billing_last_name')
				.fill(userData.lastName);
			await loggedInPage
				.locator('#billing-address_1, #billing_address_1')
				.fill(userData.address);
			await loggedInPage.locator('#billing-city, #billing_city').fill(userData.city);
			await loggedInPage
				.locator('#billing-postcode, #billing_postcode')
				.fill(userData.postCode);
			await loggedInPage
				.locator('#billing-phone, #billing_phone')
				.fill(userData.phoneNumber, { force: true });
			await loggedInPage.locator('#email, #billing_email').clear();
			await loggedInPage.locator('#email, #billing_email').fill(userData.email);

			// WooCommerce hides the radio and checks it for us when cash on
			// delivery is the only gateway available, so it can only be selected
			// when a second gateway makes it visible.
			const codMethod = loggedInPage.locator('#payment_method_cod');
			if ((await codMethod.count()) > 0 && !(await codMethod.isChecked())) {
				await codMethod.check();
			}

			// WooCommerce 8.3 made the checkout block the default for new
			// installs; older versions still render the shortcode checkout.
			if (await isWcVersionAtLeast('8.3.0')) {
				await loggedInPage.waitForTimeout(1000);
				await loggedInPage
					.locator('.wc-block-components-checkout-place-order-button')
					.click();
			} else {
				await loggedInPage.locator('#place_order').click();
			}

			// Ensure order is placed
			await expect(loggedInPage).toHaveURL(/.*\/checkout\/order-received/);

			// Give Elasticsearch time to process
			await loggedInPage.waitForTimeout(2000);

			// Ensure order is visible to user
			await loggedInPage.goto('my-account/orders');
			await expect(loggedInPage.locator('.woocommerce-orders-table tbody tr')).toHaveCount(1);

			// Test orderby parameter
			await expect(
				loggedInPage
					.locator('.ep-query-debug')
					.filter({
						has: loggedInPage.locator('.ep-query-type', { hasText: 'post' }),
					})
					.first(),
			).toContainText('shop_order');
			await expect(
				loggedInPage
					.locator('.ep-query-debug')
					.filter({
						has: loggedInPage.locator('.ep-query-type', { hasText: 'post' }),
					})
					.first(),
			).toContainText("'orderby' => 'date'");

			await logout(loggedInPage);

			await createUser(loggedInPage, {
				username: 'buyer',
				email: 'buyer@example.com',
				login: true,
			});

			// Ensure no order is shown for different user
			await loggedInPage.goto('my-account/orders');
			await expect(loggedInPage.locator('.woocommerce-orders-table tbody tr')).toHaveCount(0);

			await expect(
				loggedInPage
					.locator('.ep-query-debug')
					.filter({
						has: loggedInPage.locator('.ep-query-type', { hasText: 'post' }),
					})
					.first(),
			).toContainText('shop_order');
			await expect(
				loggedInPage
					.locator('.ep-query-debug')
					.filter({
						has: loggedInPage.locator('.ep-query-type', { hasText: 'post' }),
					})
					.first(),
			).toContainText('HTTP 200');
		});

		test('Can search orders from ElasticPress in WP Dashboard', async ({ loggedInPage }) => {
			await goToAdminPage(loggedInPage, 'edit.php?post_type=shop_order');

			const checkAllOrders = async () => {
				const allOrders = await loggedInPage.locator('.order_number .order-view').all();
				for await (const order of allOrders) {
					await expect(order).toContainText(`${userData.firstName} ${userData.lastName}`);
				}
			};

			// Search order by user's name
			await loggedInPage.locator('#post-search-input').clear();
			await loggedInPage
				.locator('#post-search-input')
				.fill(`${userData.firstName} ${userData.lastName}`);
			await loggedInPage.locator('#post-search-input').press('Enter');

			await checkMainEsQuery(loggedInPage);
			await checkAllOrders();

			// Search order by user's address
			await loggedInPage.locator('#post-search-input').clear();
			await loggedInPage.locator('#post-search-input').fill(userData.address);
			await loggedInPage.locator('#post-search-input').press('Enter');
			await checkMainEsQuery(loggedInPage);
			await checkAllOrders();

			// Search order by product
			await loggedInPage.locator('#post-search-input').clear();
			await loggedInPage.locator('#post-search-input').fill('fantastic-silk-knife');
			await loggedInPage.locator('#post-search-input').press('Enter');
			await checkMainEsQuery(loggedInPage);
			await checkAllOrders();
		});

		test('Can show the correct order of products after custom sort order', async ({
			loggedInPage,
		}) => {
			// Content Items per Index Cycle is greater than number of products
			await setPerIndexCycle();
			await goToAdminPage(
				loggedInPage,
				'edit.php?post_type=product&orderby=menu_order+title&order=ASC',
			);
			await expect(
				loggedInPage.locator('div[data-ep-notice="woocommerce_custom_sort"]'),
			).not.toBeVisible();

			// Get third product ID
			const thirdProductRow = loggedInPage.locator('#the-list tr').nth(2);
			const thirdProductId = await thirdProductRow.getAttribute('id');

			// Drag third product to first position
			const ajaxRequestPromise = loggedInPage.waitForResponse('**/wp-admin/admin-ajax.php*');
			await thirdProductRow.dragTo(loggedInPage.locator('#the-list tr').first(), {
				force: true,
			});
			await ajaxRequestPromise;
			await expect(loggedInPage.locator('#the-list tr').first()).toHaveAttribute(
				'id',
				thirdProductId || '',
			);

			await refreshIndex('post');
			await loggedInPage.reload();
			await expect(
				loggedInPage.locator(
					'#debug-menu-target-EP_Debug_Bar_ElasticPress .ep-query-debug',
				),
			).toContainText('Query Response Code: HTTP 200');
			await expect(loggedInPage.locator('#the-list tr').first()).toHaveAttribute(
				'id',
				thirdProductId || '',
			);

			// Content Items per Index Cycle is lower than number of products
			await setPerIndexCycle(20);
			await goToAdminPage(
				loggedInPage,
				'edit.php?post_type=product&orderby=menu_order+title&order=ASC',
			);
			await expect(
				loggedInPage.locator('div[data-ep-notice="woocommerce_custom_sort"]'),
			).toBeVisible();

			// Get third product ID again
			const thirdProductRow2 = loggedInPage.locator('#the-list tr').nth(2);
			const thirdProductId2 = await thirdProductRow2.getAttribute('id');

			// Drag third product to first position
			const ajaxRequestPromise2 = loggedInPage.waitForResponse('**/wp-admin/admin-ajax.php*');
			await thirdProductRow2.dragTo(loggedInPage.locator('#the-list tr').first(), {
				force: true,
			});
			await ajaxRequestPromise2;
			await expect(loggedInPage.locator('#the-list tr').first()).toHaveAttribute(
				'id',
				thirdProductId2 || '',
			);

			await refreshIndex('post');
			await loggedInPage.reload();
			await expect(
				loggedInPage.locator(
					'#debug-menu-target-EP_Debug_Bar_ElasticPress .ep-query-debug',
				),
			).toContainText('Query Response Code: HTTP 200');
			await expect(loggedInPage.locator('#the-list tr').first()).not.toHaveAttribute(
				'id',
				thirdProductId2 || '',
			);

			await setPerIndexCycle();
		});
	});

	test.describe('Orders Autosuggest', () => {
		test.beforeAll(async () => {
			await wpCli('plugin activate woocommerce');
			await wpCli('wc hpos disable', true);
			await maybeEnableFeature('woocommerce');
			await maybeDisableFeature('protected_content');
		});

		test('Will require a sync when enabling Orders Autosuggest', async ({ loggedInPage }) => {
			await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress');

			// Enable the feature
			await loggedInPage
				.locator('.ep-dashboard-outer-tabs .ep-dashboard-tab:has-text("WooCommerce")')
				.click();
			await loggedInPage
				.locator('.group-content .ep-dashboard-tab:has-text("WooCommerce")')
				.click();

			const showSuggestionsCheck = loggedInPage
				.locator('label:has-text("Show suggestions")')
				.locator('..')
				.locator('input');

			if (!isEpIo()) {
				await expect(showSuggestionsCheck).toBeDisabled();
				return;
			}

			const apiRequestPromise = loggedInPage.waitForResponse(
				'/wp-json/elasticpress/v1/features*',
			);

			await showSuggestionsCheck.check();
			loggedInPage.on('dialog', (dialog) => dialog.accept());
			await loggedInPage.getByRole('button', { name: 'Save and sync now' }).click();

			await apiRequestPromise;

			// Syncing should complete
			await openSyncLog(loggedInPage);
			const syncMessages = loggedInPage.locator('.ep-sync-messages');
			await expect(syncMessages).toContainText('Mapping sent', {
				timeout: getSyncTimeout(),
			});
			await expect(syncMessages).toContainText('Sync complete', {
				timeout: getSyncTimeout(),
			});
		});

		test('Will show a navigable list of suggested results when searching orders', async ({
			loggedInPage,
		}) => {
			await goToAdminPage(loggedInPage, 'edit.php?post_type=shop_order');

			// The combobox will not render if not using ElasticPress.io
			if (!isEpIo()) {
				await expect(
					loggedInPage.locator('#posts-filter .ep-combobox__input'),
				).not.toBeVisible();
				return;
			}

			// Prepare aliases
			const apiRequestPromise = loggedInPage.waitForResponse('**/api/v1/search/orders/*');
			const input = loggedInPage.locator('#posts-filter .ep-combobox__input');
			const description = loggedInPage.locator(
				'#posts-filter .ep-combobox > .screen-reader-text',
			);
			const listbox = loggedInPage.locator('#posts-filter .ep-combobox__list');
			const submit = loggedInPage.locator('#posts-filter .search-box .button');

			// Search for "Antwon". 3 suggestions should appear
			await input.fill('Antwon');
			await apiRequestPromise;
			await expect(input).toHaveAttribute('aria-expanded', 'true');
			await expect(description).toContainText('4 suggestions available');
			await expect(listbox).toBeVisible();
			await expect(listbox.locator('> *')).toHaveCount(4);

			// Test arrow key navigation
			await input.press('ArrowDown');
			await expect(listbox.locator('> *').first()).toHaveAttribute('aria-selected', 'true');

			await input.press('ArrowDown');
			await input.press('ArrowDown');
			await input.press('ArrowDown');
			await expect(listbox.locator('> *').nth(3)).toHaveAttribute('aria-selected', 'true');
			await expect(listbox.locator('> *').first()).not.toHaveAttribute(
				'aria-selected',
				'true',
			);

			await input.press('ArrowDown');
			await expect(listbox.locator('> *').first()).toHaveAttribute('aria-selected', 'true');
			await expect(listbox.locator('> *').nth(3)).not.toHaveAttribute(
				'aria-selected',
				'true',
			);

			await input.press('ArrowUp');
			await expect(listbox.locator('> *').nth(3)).toHaveAttribute('aria-selected', 'true');
			await expect(listbox.locator('> *').first()).not.toHaveAttribute(
				'aria-selected',
				'true',
			);

			await input.press('ArrowUp');
			await expect(listbox.locator('> *').nth(2)).toHaveAttribute('aria-selected', 'true');
			await expect(listbox.locator('> *').nth(3)).not.toHaveAttribute(
				'aria-selected',
				'true',
			);

			// Test escape key
			await input.press('Escape');
			await expect(listbox).not.toBeVisible();
			await input.press('ArrowDown');
			await expect(listbox).toBeVisible();
			await expect(listbox.locator('> *').first()).toHaveAttribute('aria-selected', 'true');

			// Test focus management
			await submit.focus();
			await expect(listbox).not.toBeVisible();
			await input.focus();
			await expect(listbox).toBeVisible();

			// Test partial name searches
			await input.press('Backspace');
			await input.press('Backspace');
			await loggedInPage.waitForResponse('**/api/v1/search/orders/*');
			await expect(listbox.locator('> *')).toHaveCount(4);

			// Test enter key navigation
			await input.press('ArrowDown');
			await input.press('ArrowDown');
			await input.press('Enter');
			await expect(loggedInPage).toHaveURL(/.*post\.php\?post=/);

			// Test clicking suggestions
			await goToAdminPage(loggedInPage, 'edit.php?post_type=shop_order');
			await input.fill('Antwon');
			await loggedInPage.waitForResponse('**/api/v1/search/orders/*');
			await listbox.locator('> *').nth(1).click();
			await expect(loggedInPage).toHaveURL(/.*post\.php\?post=/);
		});
	});

	test.describe('Orders (HPOS)', () => {
		test.beforeAll(async () => {
			test.skip(
				!(await isWcVersionAtLeast('9.8.0')),
				'HPOS integration requires WooCommerce 9.8.0 or greater',
			);

			await wpCli('plugin activate woocommerce');

			await wpCli('wc hpos compatibility-mode enable');
			await wpCli('wc hpos sync');
			await wpCli('wc hpos enable --ignore-plugin-compatibility');
			const status = (await wpCli('wc hpos status')).toString();
			expect(status).toMatch(/HPOS.*enabled|Authoritative.*orders/i);

			await maybeEnableFeature('woocommerce');
			await maybeEnableFeature('protected_content');
			await wpCli('elasticpress sync --setup --yes');
		});

		test('Can fetch orders from Elasticsearch', async ({ loggedInPage }) => {
			await goToAdminPage(loggedInPage, 'admin.php?page=wc-orders');
			await expect(
				loggedInPage
					.locator('#debug-menu-target-EP_Debug_Bar_ElasticPress .ep-query-debug')
					.filter({ hasText: '_search' })
					.first(),
			).toContainText('Query Response Code: HTTP 200');
		});

		test('Can show and dismiss HPOS query integration disabled notice', async ({
			loggedInPage,
		}) => {
			await wpCli(
				'option delete ep_hide_wc_orders_hpos_query_integration_disabled_notice',
				true,
			);

			await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress');
			await loggedInPage
				.locator('.ep-dashboard-outer-tabs .ep-dashboard-tab:has-text("WooCommerce")')
				.click();
			await loggedInPage
				.locator('.group-content .ep-dashboard-tab:has-text("WooCommerce")')
				.click();

			const disableHposCheckbox = loggedInPage.getByRole('checkbox', {
				name: 'Disable query integration with WooCommerce Orders using HPOS',
			});
			const enableApiRequestPromise = loggedInPage.waitForResponse(
				'/wp-json/elasticpress/v1/features*',
			);
			await disableHposCheckbox.setChecked(true);
			await loggedInPage.getByRole('button', { name: 'Save changes' }).click();
			await enableApiRequestPromise;

			await goToAdminPage(loggedInPage, 'admin.php?page=wc-orders');
			const notice = loggedInPage.locator(
				'div[data-ep-notice="wc_orders_hpos_query_integration_disabled"]',
			);
			await expect(notice).toBeVisible();
			await expect(notice).toContainText('not being retrieved from Elasticsearch');

			const dismissResponse = loggedInPage.waitForResponse((response) => {
				const request = response.request();
				return (
					request.url().includes('admin-ajax.php') &&
					request.method() === 'POST' &&
					(request.postData() || '').includes('ep_notice_dismiss')
				);
			});
			await notice.locator('.notice-dismiss').click();
			await dismissResponse;
			await expect(notice).not.toBeVisible();

			await goToAdminPage(loggedInPage, 'admin.php?page=wc-orders');
			await expect(
				loggedInPage.locator(
					'div[data-ep-notice="wc_orders_hpos_query_integration_disabled"]',
				),
			).not.toBeVisible();

			await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress');
			await loggedInPage
				.locator('.ep-dashboard-outer-tabs .ep-dashboard-tab:has-text("WooCommerce")')
				.click();
			await loggedInPage
				.locator('.group-content .ep-dashboard-tab:has-text("WooCommerce")')
				.click();

			const disableApiRequestPromise = loggedInPage.waitForResponse(
				'/wp-json/elasticpress/v1/features*',
			);
			await loggedInPage
				.getByRole('checkbox', {
					name: 'Disable query integration with WooCommerce Orders using HPOS',
				})
				.setChecked(false);
			await loggedInPage.getByRole('button', { name: 'Save changes' }).click();
			await disableApiRequestPromise;

			await wpCli(
				'option delete ep_hide_wc_orders_hpos_query_integration_disabled_notice',
				true,
			);
		});

		test('Can fetch orders from Elasticsearch when HPOS query integration is enabled', async ({
			loggedInPage,
		}) => {
			await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress');
			await loggedInPage
				.locator('.ep-dashboard-outer-tabs .ep-dashboard-tab:has-text("WooCommerce")')
				.click();
			await loggedInPage
				.locator('.group-content .ep-dashboard-tab:has-text("WooCommerce")')
				.click();

			const disableHposCheckbox = loggedInPage.getByRole('checkbox', {
				name: 'Disable query integration with WooCommerce Orders using HPOS',
			});

			if (await disableHposCheckbox.isChecked()) {
				const apiRequestPromise = loggedInPage.waitForResponse(
					'/wp-json/elasticpress/v1/features*',
				);
				await disableHposCheckbox.setChecked(false);
				await loggedInPage.getByRole('button', { name: 'Save changes' }).click();
				await apiRequestPromise;
			}

			await goToAdminPage(loggedInPage, 'admin.php?page=wc-orders');
			await expect(
				loggedInPage
					.locator('#debug-menu-target-EP_Debug_Bar_ElasticPress .ep-query-debug')
					.filter({ hasText: '_search' })
					.first(),
			).toContainText('Query Response Code: HTTP 200');
		});

		test('Can search orders from ElasticPress in WP Dashboard', async ({ loggedInPage }) => {
			// Disable Orders Autosuggest.
			if (isEpIo()) {
				await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress');
				await loggedInPage
					.locator('.ep-dashboard-outer-tabs .ep-dashboard-tab:has-text("WooCommerce")')
					.click();
				await loggedInPage
					.locator('.group-content .ep-dashboard-tab:has-text("WooCommerce")')
					.click();

				const showSuggestionsCheck = loggedInPage
					.locator('label:has-text("Show suggestions")')
					.locator('..')
					.locator('input');

				if (await showSuggestionsCheck.isChecked()) {
					const apiRequestPromise = loggedInPage.waitForResponse(
						'/wp-json/elasticpress/v1/features*',
					);

					await showSuggestionsCheck.uncheck();
					await loggedInPage.getByRole('button', { name: 'Save changes' }).click();
					await apiRequestPromise;
				}
			}

			await goToAdminPage(loggedInPage, 'admin.php?page=wc-orders');
			await expect(loggedInPage.locator('#orders-search-input-search-input')).toBeVisible();

			const checkAllOrders = async () => {
				const allOrders = await loggedInPage.locator('.order_number .order-view').all();
				for await (const order of allOrders) {
					await expect(order).toContainText(`${userData.firstName} ${userData.lastName}`);
				}
			};

			// Search order by user's name
			await loggedInPage.locator('#orders-search-input-search-input').clear();
			await loggedInPage
				.locator('#orders-search-input-search-input')
				.fill(`${userData.firstName} ${userData.lastName}`);
			await loggedInPage.locator('#order-search-filter').selectOption('customers');
			await loggedInPage.locator('#orders-search-input-search-input').press('Enter');

			await expect(
				loggedInPage
					.locator('#debug-menu-target-EP_Debug_Bar_ElasticPress .ep-query-debug')
					.filter({ hasText: '_search' })
					.first(),
			).toContainText('Query Response Code: HTTP 200');
			await checkAllOrders();

			// Search order by user's email
			await loggedInPage.locator('#orders-search-input-search-input').clear();
			await loggedInPage.locator('#orders-search-input-search-input').fill(userData.email);
			await loggedInPage.locator('#order-search-filter').selectOption('customer_email');
			await loggedInPage.locator('#orders-search-input-search-input').press('Enter');
			await expect(
				loggedInPage
					.locator('#debug-menu-target-EP_Debug_Bar_ElasticPress .ep-query-debug')
					.filter({ hasText: '_search' })
					.first(),
			).toContainText('Query Response Code: HTTP 200');
			await checkAllOrders();

			// Search order by product
			await loggedInPage.locator('#orders-search-input-search-input').clear();
			await loggedInPage
				.locator('#orders-search-input-search-input')
				.fill('fantastic-silk-knife');
			await loggedInPage.locator('#order-search-filter').selectOption('products');
			await loggedInPage.locator('#orders-search-input-search-input').press('Enter');
			await expect(
				loggedInPage
					.locator('#debug-menu-target-EP_Debug_Bar_ElasticPress .ep-query-debug')
					.filter({ hasText: '_search' })
					.first(),
			).toContainText('Query Response Code: HTTP 200');
			await checkAllOrders();

			// Search order by order ID
			await loggedInPage.locator('#orders-search-input-search-input').clear();
			await loggedInPage.locator('#orders-search-input-search-input').fill('1988');
			await loggedInPage.locator('#order-search-filter').selectOption('order_id');
			await loggedInPage.locator('#orders-search-input-search-input').press('Enter');
			await expect(
				loggedInPage
					.locator('#debug-menu-target-EP_Debug_Bar_ElasticPress .ep-query-debug')
					.filter({ hasText: '_search' })
					.first(),
			).toContainText('Query Response Code: HTTP 200');

			const allOrders = await loggedInPage.locator('.order_number .order-view').all();
			for await (const order of allOrders) {
				await expect(order).toContainText('1988');
			}
		});

		test('Can fetch orders from Elasticsearch when Date filter is applied', async ({
			loggedInPage,
		}) => {
			await goToAdminPage(loggedInPage, 'admin.php?page=wc-orders');

			await loggedInPage.locator('#filter-by-date').selectOption({ index: 1 });
			await loggedInPage.locator('#filter-by-date').press('Enter');

			await expect(
				loggedInPage
					.locator('#debug-menu-target-EP_Debug_Bar_ElasticPress .ep-query-debug')
					.filter({ hasText: '_search' })
					.first(),
			).toContainText('Query Response Code: HTTP 200');

			await expect(
				loggedInPage
					.locator('.order_number .order-view')
					.filter({
						hasText: `${userData.firstName} ${userData.lastName}`,
					})
					.first(),
			).toBeVisible();
		});

		test('Can fetch orders from Elasticsearch when Sales Channel filter is applied', async ({
			loggedInPage,
		}) => {
			await goToAdminPage(loggedInPage, 'admin.php?page=wc-orders&action=new');
			await loggedInPage
				.locator('.order_data_column_container  .order_data_column > h3 > .edit_address')
				.first()
				.click();
			await loggedInPage.locator('#_billing_first_name').fill(userData.firstName);
			await loggedInPage.locator('#_billing_last_name').fill(userData.lastName);
			await loggedInPage.locator('#_billing_address_1').fill(userData.address);
			await loggedInPage.locator('#_billing_city').fill(userData.city);
			await loggedInPage.locator('#_billing_postcode').fill(userData.postCode);
			await loggedInPage.locator('#_billing_phone').fill(userData.phoneNumber);
			await loggedInPage.locator('#_billing_email').fill(userData.email);
			await loggedInPage.locator('.order_actions.submitbox .save_order').click();
			await loggedInPage.waitForURL(/[?&]id=\d+/);

			// Grab the id query string from the url
			const url = new URL(loggedInPage.url());
			const id = url.searchParams.get('id');
			if (!id) {
				throw new Error('Order ID missing from URL after saving order');
			}

			// Allow the new order to be indexed.
			await loggedInPage.waitForTimeout(2000);

			await goToAdminPage(loggedInPage, 'admin.php?page=wc-orders');

			await loggedInPage.locator('#filter-by-created-via').selectOption('admin');
			await loggedInPage.locator('#filter-by-created-via').press('Enter');

			await expect(
				loggedInPage
					.locator('#debug-menu-target-EP_Debug_Bar_ElasticPress .ep-query-debug')
					.filter({ hasText: '_search' })
					.first(),
			).toContainText('Query Response Code: HTTP 200');

			await expect(
				loggedInPage
					.locator('.order_number .order-view')
					.filter({ hasText: `#${id}` })
					.first(),
			).toBeVisible();
		});

		test('Can not display other users orders on the My Account Order page', async ({
			loggedInPage,
		}) => {
			await activatePlugin(loggedInPage, 'enable-debug-bar');

			// Enable payment gateway
			await goToAdminPage(
				loggedInPage,
				'admin.php?page=wc-settings&tab=checkout&section=cod',
			);
			const checkboxLabel = 'Enable cash on delivery payments';

			await loggedInPage.getByLabel(checkboxLabel).setChecked(false);
			await loggedInPage.getByLabel(checkboxLabel).setChecked(true);
			await loggedInPage.getByRole('button', { name: 'Save changes' }).click();

			// Disable coming soon option
			await wpCli('option update woocommerce_coming_soon off');

			await logout(loggedInPage);

			// Create new user
			await createUser(loggedInPage, {
				username: userData.username,
				email: userData.email,
				login: true,
			});

			// Add product to cart
			await loggedInPage.goto('product/fantastic-silk-knife');
			await loggedInPage.locator('.single_add_to_cart_button').click();

			// Checkout and place order
			await loggedInPage.goto('checkout');
			await loggedInPage
				.locator('#billing-first_name, #billing_first_name')
				.fill(userData.firstName);
			await loggedInPage
				.locator('#billing-last_name, #billing_last_name')
				.fill(userData.lastName);
			await loggedInPage
				.locator('#billing-address_1, #billing_address_1')
				.fill(userData.address);
			await loggedInPage.locator('#billing-city, #billing_city').fill(userData.city);
			await loggedInPage
				.locator('#billing-postcode, #billing_postcode')
				.fill(userData.postCode);
			await loggedInPage
				.locator('#billing-phone, #billing_phone')
				.fill(userData.phoneNumber, { force: true });
			await loggedInPage.locator('#email, #billing_email').clear();
			await loggedInPage.locator('#email, #billing_email').fill(userData.email);

			// Check WooCommerce version and place order accordingly
			await loggedInPage.waitForTimeout(1000);
			await loggedInPage.locator('.wc-block-components-checkout-place-order-button').click();

			// Ensure order is placed
			await expect(loggedInPage).toHaveURL(/.*\/checkout\/order-received/);

			// Give Elasticsearch time to process
			await loggedInPage.waitForTimeout(2000);

			// Ensure order is visible to user
			await loggedInPage.goto('my-account/orders');
			await expect(loggedInPage.locator('.woocommerce-orders-table tbody tr')).toHaveCount(1);

			// Test orderby parameter
			await expect(
				loggedInPage
					.locator('.ep-query-debug')
					.filter({
						has: loggedInPage.locator('.ep-query-type', { hasText: 'post' }),
					})
					.first(),
			).toContainText('shop_order');
			await expect(
				loggedInPage
					.locator('.ep-query-debug')
					.filter({
						has: loggedInPage.locator('.ep-query-type', { hasText: 'post' }),
					})
					.first(),
			).toContainText("'orderby' => 'date'");

			await logout(loggedInPage);

			await createUser(loggedInPage, {
				username: 'buyer',
				email: 'buyer@example.com',
				login: true,
			});

			// Ensure no order is shown for different user
			await loggedInPage.goto('my-account/orders');
			await expect(loggedInPage.locator('.woocommerce-orders-table tbody tr')).toHaveCount(0);

			await expect(
				loggedInPage
					.locator('.ep-query-debug')
					.filter({
						has: loggedInPage.locator('.ep-query-type', { hasText: 'post' }),
					})
					.first(),
			).toContainText('shop_order');
			await expect(
				loggedInPage
					.locator('.ep-query-debug')
					.filter({
						has: loggedInPage.locator('.ep-query-type', { hasText: 'post' }),
					})
					.first(),
			).toContainText('HTTP 200');
		});
	});

	test.describe('Orders Autosuggest (HPOS)', () => {
		test.beforeAll(async () => {
			test.skip(
				!(await isWcVersionAtLeast('9.8.0')),
				'HPOS integration requires WooCommerce 9.8.0 or greater',
			);

			await wpCli('plugin activate woocommerce');

			await wpCli('wc hpos compatibility-mode enable');
			await wpCli('wc hpos sync');
			await wpCli('wc hpos enable --ignore-plugin-compatibility');
			const status = (await wpCli('wc hpos status')).toString();
			expect(status).toMatch(/HPOS.*enabled|Authoritative.*orders/i);

			await maybeEnableFeature('woocommerce');
			await maybeEnableFeature('protected_content');
			await wpCli('elasticpress sync --setup --yes');
		});

		test('Will require a sync when enabling Orders Autosuggest', async ({ loggedInPage }) => {
			await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress');

			// Enable the feature
			await loggedInPage
				.locator('.ep-dashboard-outer-tabs .ep-dashboard-tab:has-text("WooCommerce")')
				.click();
			await loggedInPage
				.locator('.group-content .ep-dashboard-tab:has-text("WooCommerce")')
				.click();

			const showSuggestionsCheck = loggedInPage
				.locator('label:has-text("Show suggestions")')
				.locator('..')
				.locator('input');

			if (!isEpIo()) {
				await expect(showSuggestionsCheck).toBeDisabled();
				return;
			}

			const apiRequestPromise = loggedInPage.waitForResponse(
				'/wp-json/elasticpress/v1/features*',
			);

			await showSuggestionsCheck.check();
			loggedInPage.on('dialog', (dialog) => dialog.accept());
			await loggedInPage.getByRole('button', { name: 'Save and sync now' }).click();

			await apiRequestPromise;

			// Syncing should complete
			await loggedInPage.getByRole('button', { name: 'Log' }).click();
			const syncMessages = loggedInPage.locator('.ep-sync-messages');
			await expect(syncMessages).toContainText('Mapping sent');
			await expect(syncMessages).toContainText('Sync complete');
		});

		test('Will show a navigable list of suggested results when searching orders', async ({
			loggedInPage,
		}) => {
			await goToAdminPage(loggedInPage, 'admin.php?page=wc-orders');

			// The combobox will not render if not using ElasticPress.io
			if (!isEpIo()) {
				await expect(
					loggedInPage.locator('#wc-orders-filter .ep-combobox__input'),
				).not.toBeVisible();
				return;
			}

			// Prepare aliases
			const apiRequestPromise = loggedInPage.waitForResponse('**/api/v1/search/orders/*');
			const input = loggedInPage.locator('#wc-orders-filter .ep-combobox__input');
			const description = loggedInPage.locator(
				'#wc-orders-filter .ep-combobox > .screen-reader-text',
			);
			const listbox = loggedInPage.locator('#wc-orders-filter .ep-combobox__list');
			const submit = loggedInPage.locator('#wc-orders-filter .search-box .button');

			// Search for "Antwon". 3 suggestions should appear
			await input.fill('Antwon');
			await apiRequestPromise;
			await expect(input).toHaveAttribute('aria-expanded', 'true');
			await expect(description).toContainText('4 suggestions available');
			await expect(listbox).toBeVisible();
			await expect(listbox.locator('> *')).toHaveCount(4);

			// Test arrow key navigation
			await input.press('ArrowDown');
			await expect(listbox.locator('> *').first()).toHaveAttribute('aria-selected', 'true');

			await input.press('ArrowDown');
			await input.press('ArrowDown');
			await input.press('ArrowDown');
			await expect(listbox.locator('> *').nth(3)).toHaveAttribute('aria-selected', 'true');
			await expect(listbox.locator('> *').first()).not.toHaveAttribute(
				'aria-selected',
				'true',
			);

			await input.press('ArrowDown');
			await expect(listbox.locator('> *').first()).toHaveAttribute('aria-selected', 'true');
			await expect(listbox.locator('> *').nth(3)).not.toHaveAttribute(
				'aria-selected',
				'true',
			);

			await input.press('ArrowUp');
			await expect(listbox.locator('> *').nth(3)).toHaveAttribute('aria-selected', 'true');
			await expect(listbox.locator('> *').first()).not.toHaveAttribute(
				'aria-selected',
				'true',
			);

			await input.press('ArrowUp');
			await expect(listbox.locator('> *').nth(2)).toHaveAttribute('aria-selected', 'true');
			await expect(listbox.locator('> *').nth(3)).not.toHaveAttribute(
				'aria-selected',
				'true',
			);

			// Test escape key
			await input.press('Escape');
			await expect(listbox).not.toBeVisible();
			await input.press('ArrowDown');
			await expect(listbox).toBeVisible();
			await expect(listbox.locator('> *').first()).toHaveAttribute('aria-selected', 'true');

			// Test focus management
			await submit.focus();
			await expect(listbox).not.toBeVisible();
			await input.focus();
			await expect(listbox).toBeVisible();

			// Test partial name searches
			await input.press('Backspace');
			await input.press('Backspace');
			await loggedInPage.waitForResponse('**/api/v1/search/orders/*');
			await expect(listbox.locator('> *')).toHaveCount(4);

			// Test enter key navigation
			await input.press('ArrowDown');
			await input.press('ArrowDown');
			await input.press('Enter');
			await expect(loggedInPage).toHaveURL(/page=wc-orders&action=edit&id=\d+/);

			// Test clicking suggestions
			await goToAdminPage(loggedInPage, 'admin.php?page=wc-orders');
			await input.fill('Antwon');
			await loggedInPage.waitForResponse('**/api/v1/search/orders/*');
			await listbox.locator('> *').nth(1).click();
			await expect(loggedInPage).toHaveURL(/page=wc-orders&action=edit&id=\d+/);
		});
	});
});
