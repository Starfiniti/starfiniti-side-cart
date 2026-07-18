import { expect, type Page, test } from '@playwright/test';

type AdminConfig = {
	nonce: string;
	root: string;
	settingsPath: string;
	productsPath: string;
};

async function adminConfig( page: Page ): Promise< AdminConfig > {
	await page.goto( '/wp-admin/admin.php?page=starfiniti-cart', {
		waitUntil: 'domcontentloaded',
	} );
	await expect(
		page.getByRole( 'heading', { level: 1, name: 'Starfiniti Cart' } )
	).toBeVisible( { timeout: 60_000 } );
	return page.evaluate( () => {
		if ( ! window.sfcartAdminConfig ) {
			throw new Error( 'Administration configuration is missing.' );
		}
		return window.sfcartAdminConfig;
	} );
}

const url = ( config: AdminConfig, path: string ) =>
	`${ config.root }${ path.replace( /^\//, '' ) }`;

async function productId(
	page: Page,
	config: AdminConfig,
	search: string
): Promise< number > {
	return page.evaluate(
		async ( args ) => {
			const response = await fetch(
				`${ args.path }?search=${ encodeURIComponent( args.search ) }`,
				{ headers: { 'X-WP-Nonce': args.nonce } }
			);
			const body = await response.json();
			return Number( body.items?.[ 0 ]?.id ?? 0 );
		},
		{
			nonce: config.nonce,
			path: url( config, config.productsPath ),
			search,
		}
	);
}

async function saveAddon(
	page: Page,
	config: AdminConfig,
	patch: Record< string, unknown >
): Promise< void > {
	const result = await page.evaluate(
		async ( args ) => {
			const currentResponse = await fetch( args.path, {
				headers: { 'X-WP-Nonce': args.nonce },
			} );
			const current = await currentResponse.json();
			Object.assign( current.settings.special_addon, args.patch );
			const response = await fetch( args.path, {
				body: JSON.stringify( { settings: current.settings } ),
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': args.nonce,
				},
				method: 'PUT',
			} );
			return { body: await response.json(), status: response.status };
		},
		{
			nonce: config.nonce,
			patch,
			path: url( config, config.settingsPath ),
		}
	);
	expect( result.status, JSON.stringify( result.body ) ).toBe( 200 );
}

async function clearCart( page: Page ): Promise< void > {
	await page.goto( '/sfcart-classic/', { waitUntil: 'domcontentloaded' } );
	await page.evaluate( async () => {
		const response = await fetch( '/wp-json/wc/store/v1/cart' );
		const cart = await response.json();
		let nonce = response.headers.get( 'Nonce' ) ?? '';
		for ( const item of cart.items ?? [] ) {
			const removed = await fetch(
				'/wp-json/wc/store/v1/cart/remove-item',
				{
					body: JSON.stringify( { key: item.key } ),
					headers: {
						'Content-Type': 'application/json',
						Nonce: nonce,
					},
					method: 'POST',
				}
			);
			nonce = removed.headers.get( 'Nonce' ) ?? nonce;
		}
	} );
}

async function addSourceProduct( page: Page ): Promise< void > {
	await page.goto( '/sfcart-classic/', { waitUntil: 'domcontentloaded' } );
	await page
		.locator( 'li.product' )
		.filter( { hasText: 'Batch 2 Simple Product' } )
		.locator( 'a.ajax_add_to_cart' )
		.click();
}

async function inspect( page: Page, config: AdminConfig ) {
	return page.evaluate( async ( args ) => {
		const response = await fetch(
			`${ args.root }sfcart-test/v1/special-addon`,
			{
				headers: { 'X-WP-Nonce': args.nonce },
				method: 'POST',
			}
		);
		return { body: await response.json(), status: response.status };
	}, config );
}

test( 'simple, preselected, and variable special add-ons remain cart-owned', async ( {
	page,
} ) => {
	test.setTimeout( 420_000 );
	const config = await adminConfig( page );
	const simpleAddonId = await productId(
		page,
		config,
		'Batch 6 Special Add-on'
	);
	const variableAddonId = await productId(
		page,
		config,
		'Batch 2 Variable Product'
	);
	expect( simpleAddonId ).toBeGreaterThan( 0 );
	expect( variableAddonId ).toBeGreaterThan( 0 );

	await clearCart( page );
	await saveAddon( page, config, {
		accent: '#7c3aed',
		background: '#f5f3ff',
		description: 'Optional protection for this shipment.',
		description_color: '#5b21b6',
		enabled: true,
		heading: 'Protect this order',
		heading_color: '#2e1065',
		image_enabled: true,
		image_size: 64,
		image_source: 'product',
		preselected: false,
		product_id: simpleAddonId,
		selection_type: 'checkbox',
	} );
	await addSourceProduct( page );

	const dialog = page.getByRole( 'dialog', { name: 'Your cart' } );
	await expect( dialog ).toBeVisible();
	let details = await inspect( page, config );
	expect(
		details.body.snapshot.enabled,
		JSON.stringify( details.body )
	).toBe( true );
	await expect(
		dialog.locator( '[data-sfcart-express-buttons]' )
	).toHaveCount( 0 );
	const offer = dialog.locator( '[data-sfcart-special-addon]' );
	await expect( offer ).toBeVisible();
	await expect( offer ).toContainText( 'Protect this order' );
	await expect( offer ).toContainText(
		'Optional protection for this shipment.'
	);
	const control = offer.getByLabel( 'Select add-on' );
	await expect( control ).not.toBeChecked();
	await control.check();
	await expect( control ).toBeChecked();

	const simpleLine = dialog
		.locator( '.sfcart-item' )
		.filter( { hasText: 'Batch 6 Special Add-on' } );
	await expect( simpleLine ).toContainText( 'Special add-on' );
	await expect(
		simpleLine.locator( '.sfcart-item__fixed-quantity' )
	).toContainText( '1' );
	details = await inspect( page, config );
	expect( details.status ).toBe( 200 );
	expect( details.body.addon_count ).toBe( 1 );
	expect( details.body.metadata ).toEqual( [
		{ addon: 'yes', product_id: simpleAddonId },
	] );

	await control.uncheck();
	await expect( simpleLine ).toHaveCount( 0 );
	details = await inspect( page, config );
	expect( details.body.addon_count ).toBe( 0 );

	await clearCart( page );
	await saveAddon( page, config, { preselected: true } );
	await addSourceProduct( page );
	await expect( offer.getByLabel( 'Select add-on' ) ).toBeChecked();
	await expect(
		dialog
			.locator( '.sfcart-item' )
			.filter( { hasText: 'Batch 6 Special Add-on' } )
	).toHaveCount( 1 );

	await clearCart( page );
	await saveAddon( page, config, {
		preselected: false,
		product_id: variableAddonId,
		selection_type: 'toggle',
	} );
	await addSourceProduct( page );
	await expect( offer ).toHaveClass( /sfcart-special-addon--toggle/ );
	const variableControl = offer.getByLabel( 'Select add-on' );
	await expect( variableControl ).toBeDisabled();
	await offer.getByLabel( 'Choose Size' ).selectOption( { label: 'Large' } );
	await expect( variableControl ).toBeEnabled();
	await offer.locator( 'label.sfcart-special-addon__control' ).click();
	await expect( variableControl ).toBeChecked();

	const variableLine = dialog
		.locator( '.sfcart-item' )
		.filter( { hasText: 'Batch 2 Variable Product' } );
	await expect( variableLine ).toContainText( 'Large' );
	await offer.getByLabel( 'Choose Size' ).selectOption( { label: 'Small' } );
	await expect( variableLine ).toContainText( 'Small' );
	await expect( variableLine ).toHaveCount( 1 );

	details = await inspect( page, config );
	expect( details.status ).toBe( 200 );
	expect( details.body.addon_count ).toBe( 1 );
	expect( details.body.metadata ).toEqual( [
		{ addon: 'yes', product_id: variableAddonId },
	] );
} );

test( 'special add-on test inspection rejects anonymous requests', async ( {
	request,
} ) => {
	const response = await request.post(
		'/wp-json/sfcart-test/v1/special-addon'
	);
	expect( [ 401, 403, 404 ] ).toContain( response.status() );
} );
