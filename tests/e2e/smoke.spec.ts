import { expect, type Page, test } from '@playwright/test';

test.describe.configure( { timeout: 180_000 } );

async function openClassicPage( page: Page ): Promise< void > {
	const response = await page.goto( '/sfcart-classic/', {
		waitUntil: 'domcontentloaded',
	} );

	expect( response?.status() ).toBeLessThan( 500 );
	const removedItems = await page.evaluate( async () => {
		const cartResponse = await fetch( '/wp-json/wc/store/v1/cart' );
		const cart = await cartResponse.json();
		let nonce = cartResponse.headers.get( 'Nonce' ) ?? '';

		for ( const item of cart.items ?? [] ) {
			const removeResponse = await fetch(
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
			nonce = removeResponse.headers.get( 'Nonce' ) ?? nonce;
		}

		return ( cart.items ?? [] ).length;
	} );

	if ( removedItems > 0 ) {
		await page.reload( { waitUntil: 'domcontentloaded' } );
	}

	await expect(
		page.getByRole( 'heading', { name: 'Batch 2 Classic Cart Test' } )
	).toBeVisible();
}

async function addClassicProduct(
	page: Page,
	productName: string
): Promise< void > {
	const product = page
		.locator( 'li.product' )
		.filter( { hasText: productName } );
	await product.locator( 'a.ajax_add_to_cart' ).click();
	await expect(
		page.getByRole( 'dialog', { name: 'Your cart' } )
	).toBeVisible();
}

test( 'activates with WooCommerce and renders all core entry points', async ( {
	page,
	request,
} ) => {
	const readiness = await request.get(
		'/wp-includes/css/sfcart-playground-ready.css'
	);

	expect( readiness.ok() ).toBeTruthy();
	expect( await readiness.text() ).toContain(
		'WooCommerce and Starfiniti Cart are active.'
	);

	await openClassicPage( page );
	await expect( page.locator( '.sfcart-shortcode-toggle' ) ).toBeVisible();
	await expect( page.locator( 'a[data-sfcart-toggle]' ) ).toHaveAttribute(
		'aria-controls',
		'sfcart-drawer'
	);
	await expect( page.locator( '.sfcart-floating-toggle' ) ).toBeVisible();
	await expect( page.locator( 'body' ) ).not.toContainText(
		'There has been a critical error on this website'
	);
} );

test( 'empty drawer supports keyboard focus, Escape, and mobile layout', async ( {
	page,
} ) => {
	await page.setViewportSize( { height: 844, width: 390 } );
	await openClassicPage( page );

	const toggle = page.locator( '.sfcart-floating-toggle' );
	await toggle.focus();
	await page.keyboard.press( 'Enter' );

	const dialog = page.getByRole( 'dialog', { name: 'Your cart' } );
	await expect( dialog ).toBeVisible();
	await expect( dialog.getByText( 'Your cart is empty' ) ).toBeVisible();

	const box = await dialog.boundingBox();
	expect( box?.width ).toBeGreaterThan( 380 );

	await page.keyboard.press( 'Escape' );
	await expect( page.locator( '[data-sfcart-root]' ) ).toBeHidden();
	await expect( toggle ).toBeFocused();
} );

test( 'classic AJAX add, metadata, stock limits, coupons, totals, and removal work', async ( {
	page,
} ) => {
	await openClassicPage( page );
	await addClassicProduct( page, 'Batch 2 Simple Product' );

	const dialog = page.getByRole( 'dialog', { name: 'Your cart' } );
	await expect( dialog ).toContainText( 'Batch 2 Simple Product' );
	await expect( dialog ).toContainText( 'Engraving' );
	await expect( dialog ).toContainText( 'Starfiniti' );
	await expect( dialog.locator( '.sfcart-item__savings' ) ).toContainText(
		'25%'
	);
	await expect( dialog.locator( '.sfcart-calculation-note' ) ).toHaveCount(
		0
	);
	await expect( dialog.locator( '[data-sfcart-checkout]' ) ).toHaveAttribute(
		'href',
		/checkout/
	);

	const quantity = dialog.locator( '[data-sfcart-quantity]' );
	const increase = dialog.locator( '[data-sfcart-action="increase"]' );
	await expect( quantity ).toHaveValue( '1' );
	await increase.click();
	await expect( quantity ).toHaveValue( '2' );
	await increase.click();
	await expect( quantity ).toHaveValue( '3' );
	await expect( increase ).toBeDisabled();

	await dialog.locator( '#sfcart-coupon-code' ).fill( 'SAVE5' );
	await dialog.getByRole( 'button', { name: 'Apply' } ).click();
	await expect( dialog.locator( '.sfcart-applied-coupon' ) ).toContainText(
		'save5',
		{ ignoreCase: true }
	);
	await dialog.locator( '[data-sfcart-action="remove-coupon"]' ).click();
	await expect( dialog.locator( '.sfcart-applied-coupon' ) ).toHaveCount( 0 );

	await dialog.locator( '[data-sfcart-action="remove"]' ).click();
	await expect( dialog.getByText( 'Your cart is empty' ) ).toBeVisible();
	await expect( page.locator( '[data-sfcart-count]' ).first() ).toHaveText(
		'0'
	);
} );

test( 'sold-individually and variable products retain WooCommerce behavior', async ( {
	page,
} ) => {
	await openClassicPage( page );
	await addClassicProduct( page, 'Batch 2 Sold Individually' );

	let dialog = page.getByRole( 'dialog', { name: 'Your cart' } );
	const soldItem = dialog
		.locator( '.sfcart-item' )
		.filter( { hasText: 'Batch 2 Sold Individually' } );
	await expect( soldItem.locator( '[data-sfcart-quantity]' ) ).toHaveCount(
		0
	);
	await expect(
		soldItem.locator( '.sfcart-item__fixed-quantity' )
	).toHaveText( '× 1' );

	await soldItem.locator( '[data-sfcart-action="remove"]' ).click();
	await expect( dialog.getByText( 'Your cart is empty' ) ).toBeVisible();
	await page.keyboard.press( 'Escape' );
	await page.goto( '/product/batch-2-variable-product/', {
		waitUntil: 'domcontentloaded',
	} );

	const variation = page.locator( 'select[name^="attribute_"]' ).first();
	await variation.selectOption( { index: 1 } );
	const variationIdInput = page.locator( 'input.variation_id' );
	await expect( variationIdInput ).not.toHaveValue( '0' );
	const variationId = Number( await variationIdInput.inputValue() );
	const addStatus = await page.evaluate( async ( id ) => {
		const cartResponse = await fetch( '/wp-json/wc/store/v1/cart' );
		const nonce = cartResponse.headers.get( 'Nonce' ) ?? '';
		const addResponse = await fetch( '/wp-json/wc/store/v1/cart/add-item', {
			body: JSON.stringify( { id, quantity: 1 } ),
			headers: {
				'Content-Type': 'application/json',
				Nonce: nonce,
			},
			method: 'POST',
		} );

		return addResponse.status;
	}, variationId );
	expect( addStatus ).toBe( 201 );
	await page.locator( '.sfcart-floating-toggle' ).click();

	dialog = page.getByRole( 'dialog', { name: 'Your cart' } );
	await expect( dialog ).toContainText( 'Batch 2 Variable Product' );
	await expect( dialog ).toContainText( 'Small' );
} );

test( 'Store API extensions and native Blocks event refresh the drawer', async ( {
	page,
} ) => {
	await openClassicPage( page );
	const productId = Number(
		await page
			.locator( 'li.product' )
			.filter( { hasText: 'Batch 2 Simple Product' } )
			.locator( '[data-product_id]' )
			.getAttribute( 'data-product_id' )
	);

	await page.goto( '/sfcart-blocks/', { waitUntil: 'domcontentloaded' } );
	await expect(
		page.locator( '.sfcart-block-toggle [data-sfcart-toggle]' )
	).toBeVisible();

	const result = await page.evaluate( async ( id ) => {
		const cartResponse = await fetch( '/wp-json/wc/store/v1/cart' );
		const cart = await cartResponse.json();
		const nonce = cartResponse.headers.get( 'Nonce' ) ?? '';
		const addResponse = await fetch( '/wp-json/wc/store/v1/cart/add-item', {
			body: JSON.stringify( { id, quantity: 1 } ),
			headers: {
				'Content-Type': 'application/json',
				Nonce: nonce,
			},
			method: 'POST',
		} );
		const addedCart = await addResponse.json();

		document.dispatchEvent(
			new CustomEvent( 'wc-blocks_added_to_cart', {
				detail: { preserveCartData: true },
			} )
		);

		return {
			addStatus: addResponse.status,
			cartExtension: cart.extensions?.[ 'starfiniti-cart' ],
			itemExtension:
				addedCart.items?.[ 0 ]?.extensions?.[ 'starfiniti-cart' ],
		};
	}, productId );

	expect( result.addStatus ).toBe( 201 );
	expect( result.cartExtension?.available ).toBe( true );
	expect( result.itemExtension?.editable ).toBe( true );
	expect( result.itemExtension?.quantity_max ).toBe( 3 );

	const dialog = page.getByRole( 'dialog', { name: 'Your cart' } );
	await expect( dialog ).toBeVisible();
	await expect( dialog ).toContainText( 'Batch 2 Simple Product' );
} );

test( 'checkout remains owned by WooCommerce and loads no drawer assets', async ( {
	page,
} ) => {
	await openClassicPage( page );
	await addClassicProduct( page, 'Batch 2 Simple Product' );
	await page.keyboard.press( 'Escape' );
	const response = await page.goto( '/checkout/', {
		waitUntil: 'domcontentloaded',
	} );

	expect( response?.status() ).toBeLessThan( 500 );
	await expect( page.locator( '[data-sfcart-root]' ) ).toHaveCount( 0 );
	await expect( page.locator( '.sfcart-floating-toggle' ) ).toHaveCount( 0 );
	await expect(
		page.locator( 'script[src*="starfiniti-cart/build/frontend"]' )
	).toHaveCount( 0 );
} );
