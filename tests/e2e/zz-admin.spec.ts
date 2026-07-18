import { expect, test } from '@playwright/test';

test( 'administration REST routes reject anonymous requests', async ( {
	request,
} ) => {
	const response = await request.get(
		'/wp-json/starfiniti-cart/v1/settings'
	);

	expect( [ 401, 403 ] ).toContain( response.status() );
} );

test( 'owned administration saves, reloads, previews, searches, and updates the drawer', async ( {
	page,
} ) => {
	test.setTimeout( 900_000 );
	await page.goto( '/wp-admin/admin.php?page=starfiniti-cart', {
		waitUntil: 'domcontentloaded',
	} );
	await expect(
		page.getByRole( 'heading', { level: 1, name: 'Starfiniti Cart' } )
	).toBeVisible( { timeout: 60_000 } );

	const invalidWrite = await page.evaluate( async () => {
		const config = window.sfcartAdminConfig;
		if ( ! config ) {
			throw new Error( 'Administration configuration is missing.' );
		}

		const currentResponse = await fetch(
			`${ config.root }${ config.settingsPath.replace( /^\//, '' ) }`,
			{ headers: { 'X-WP-Nonce': config.nonce } }
		);
		const current = await currentResponse.json();
		current.settings.cart.width = 100;

		const response = await fetch(
			`${ config.root }${ config.settingsPath.replace( /^\//, '' ) }`,
			{
				body: JSON.stringify( { settings: current.settings } ),
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': config.nonce,
				},
				method: 'PUT',
			}
		);
		const body = await response.json();

		return { fields: body.data?.fields, status: response.status };
	} );

	expect( invalidWrite.status ).toBe( 400 );
	expect( invalidWrite.fields?.[ 'cart.width' ] ).toBeTruthy();

	const navigation = page.getByRole( 'navigation', {
		name: 'Settings sections',
	} );
	for ( const section of [
		'Cart',
		'Design',
		'Upsells',
		'Rewards',
		'Special Add-on',
		'Analytics',
		'Tools',
	] ) {
		await expect(
			navigation.getByRole( 'button', { name: section, exact: true } )
		).toBeVisible();
	}

	await page.getByLabel( 'Drawer position' ).selectOption( 'left' );
	await page
		.getByRole( 'spinbutton', { name: 'Drawer width (px)' } )
		.fill( '512' );
	await page.getByLabel( 'Open automatically after add to cart' ).uncheck();
	await page.getByLabel( 'Allow coupons in the drawer' ).uncheck();
	await page.getByLabel( 'Show Continue shopping link' ).check();
	await page.getByLabel( 'Show View cart link' ).check();
	await page.getByLabel( 'View cart label' ).fill( '' );
	await page
		.getByRole( 'textbox', { name: 'Drawer title', exact: true } )
		.fill( 'Basket' );
	await page.getByLabel( 'Checkout button label' ).fill( 'Finish order' );

	const preview = page.getByRole( 'complementary', {
		name: 'Live cart preview',
	} );
	await expect(
		preview.getByRole( 'heading', { name: 'Basket' } )
	).toBeVisible();
	await expect( preview ).toContainText( '512 px' );
	await expect( preview ).toContainText( 'Finish order' );
	await expect(
		preview.getByRole( 'button', { name: 'View cart', exact: true } )
	).toBeVisible();
	await expect(
		preview.getByRole( 'button', {
			name: 'Continue shopping',
			exact: true,
		} )
	).toBeVisible();

	await navigation
		.getByRole( 'button', { name: 'Design', exact: true } )
		.click();
	await page
		.getByRole( 'textbox', { name: 'Accent color', exact: true } )
		.fill( '#7c3aed' );
	expect( await page.evaluate( () => typeof window.wp?.media ) ).toBe(
		'function'
	);
	await page.evaluate( () => {
		let select: () => void = () => undefined;
		const attachment = {
			id: 321,
			sizes: {
				medium: { url: 'https://example.test/sfcart-empty-medium.png' },
			},
			url: 'https://example.test/sfcart-empty.png',
		};
		const frame = {
			on: ( _event: 'select', callback: () => void ) => {
				select = callback;
				return frame;
			},
			open: () => select(),
			state: () => ( {
				get: () => ( {
					first: () => ( {
						toJSON: () => attachment,
					} ),
				} ),
			} ),
		};

		if ( window.wp ) {
			window.wp.media = () => frame;
		}
	} );
	await page.getByRole( 'button', { name: 'Choose image' } ).click();
	await expect(
		page.getByRole( 'button', { name: 'Replace image' } )
	).toBeVisible();
	await expect( page.locator( '.sfcart-media-picker img' ) ).toBeVisible();

	await navigation
		.getByRole( 'button', { name: 'Upsells', exact: true } )
		.click();
	const productPicker = page.locator( '.sfcart-entity-picker' ).filter( {
		has: page.getByLabel( 'Search default products' ),
	} );
	await productPicker
		.getByLabel( 'Search default products' )
		.fill( 'Batch 2 Simple' );
	await productPicker.getByRole( 'button', { name: 'Search' } ).click();
	await expect(
		productPicker.getByText( 'Batch 2 Simple Product' )
	).toBeVisible();
	await productPicker.getByRole( 'button', { name: 'Add' } ).click();
	await expect( productPicker.getByText( 'Selected (1)' ) ).toBeVisible();

	const relationshipManager = page.locator( '.sfcart-relationship-manager' );
	await relationshipManager
		.getByLabel( 'Search product to manage' )
		.fill( 'Batch 2 Simple Product' );
	const managerProductPicker = relationshipManager
		.locator( '.sfcart-entity-picker' )
		.filter( { has: page.getByLabel( 'Search product to manage' ) } );
	await managerProductPicker
		.getByRole( 'button', { name: 'Search' } )
		.click();
	await managerProductPicker.getByRole( 'button', { name: 'Add' } ).click();
	await expect(
		relationshipManager.getByLabel( 'Search upsells' )
	).toBeVisible();
	await relationshipManager
		.getByRole( 'button', { name: 'Save product relationships' } )
		.click();
	await expect(
		relationshipManager.getByText( 'Product relationships saved.' )
	).toBeVisible();

	await navigation
		.getByRole( 'button', { name: 'Rewards', exact: true } )
		.click();
	await page.getByRole( 'button', { name: 'Add milestone' } ).click();
	const rewardMilestone = page.locator( '.sfcart-reward-milestone' ).last();
	await rewardMilestone.getByLabel( 'Reward type' ).selectOption( 'coupon' );
	await rewardMilestone.getByLabel( 'Threshold amount' ).fill( '25' );
	const couponPicker = rewardMilestone.locator( '.sfcart-entity-picker' );
	await couponPicker.getByLabel( 'Search reward coupon' ).fill( 'SAVE5' );
	await couponPicker.getByRole( 'button', { name: 'Search' } ).click();
	await expect(
		couponPicker.getByText( 'save5', { exact: true } )
	).toBeVisible();
	await couponPicker.getByRole( 'button', { name: 'Add' } ).click();

	await navigation
		.getByRole( 'button', { name: 'Special Add-on', exact: true } )
		.click();
	await expect( page.getByLabel( 'Search add-on product' ) ).toBeVisible();
	await expect( page.getByLabel( 'Show offer image' ) ).toBeVisible();

	await navigation
		.getByRole( 'button', { name: 'Analytics', exact: true } )
		.click();
	await expect( page.getByText( 'Daily cart activity' ) ).toBeVisible();
	await expect(
		page.getByRole( 'button', { name: 'Export CSV' } )
	).toBeVisible();

	await navigation
		.getByRole( 'button', { name: 'Tools', exact: true } )
		.click();
	await expect(
		page.getByLabel(
			'Delete Starfiniti Cart data when the plugin is uninstalled'
		)
	).not.toBeChecked();

	await page.getByRole( 'button', { name: 'Save settings' } ).click();
	await expect(
		page.locator( '#sfcart-admin-root' ).getByText( 'Settings saved.' )
	).toBeVisible();

	await page.getByRole( 'button', { name: 'Reload' } ).click();
	await navigation
		.getByRole( 'button', { name: 'Cart', exact: true } )
		.click();
	await expect(
		page.getByRole( 'textbox', { name: 'Drawer title', exact: true } )
	).toHaveValue( 'Basket' );
	await expect( page.getByLabel( 'Drawer position' ) ).toHaveValue( 'left' );

	await page.goto( '/sfcart-classic/', {
		waitUntil: 'domcontentloaded',
	} );
	const product = page
		.locator( 'li.product' )
		.filter( { hasText: 'Batch 2 Simple Product' } );
	const addResponsePromise = page.waitForResponse( ( response ) =>
		response.url().includes( 'wc-ajax=add_to_cart' )
	);
	await product.locator( 'a.ajax_add_to_cart' ).click();
	const addResponse = await addResponsePromise;
	expect( addResponse.ok() ).toBe( true );
	await expect( page.locator( '[data-sfcart-root]' ) ).toBeHidden();
	await page.locator( '.sfcart-floating-toggle' ).click();

	const drawerRoot = page.locator( '[data-sfcart-root]' );
	await expect( drawerRoot ).toHaveClass( /sfcart-root--left/ );
	await expect( drawerRoot ).toHaveAttribute(
		'style',
		/--sfcart-accent:#7c3aed;.*--sfcart-width:512px/
	);
	const dialog = page.getByRole( 'dialog', { name: 'Basket' } );
	await expect( dialog ).toBeVisible();
	await expect( dialog.getByText( 'Finish order' ) ).toBeVisible();
	await expect( dialog.locator( '[data-sfcart-coupon-form]' ) ).toBeHidden();
	await expect( dialog.locator( '[data-sfcart-cart-link]' ) ).toBeVisible();
	await expect(
		dialog.locator( '[data-sfcart-continue-shopping]' )
	).toBeVisible();
} );
