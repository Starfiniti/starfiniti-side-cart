import { expect, type Page, test } from '@playwright/test';

type AdminConfig = {
	nonce: string;
	root: string;
	settingsPath: string;
	productsPath: string;
	couponsPath: string;
};

type Milestone = {
	id: string;
	enabled: boolean;
	type: 'coupon' | 'gift' | 'free_shipping';
	threshold: number;
	label: string;
	pending_message: string;
	achieved_message: string;
	coupon_id: number;
	gift_product_id: number;
	gift_variation_id: number;
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

async function searchId(
	page: Page,
	config: AdminConfig,
	path: string,
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
		{ nonce: config.nonce, path: url( config, path ), search }
	);
}

async function saveRewards(
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
			Object.assign( current.settings.rewards, args.patch );
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

async function inspect( page: Page, config: AdminConfig, createOrder = false ) {
	return page.evaluate(
		async ( args ) => {
			const response = await fetch(
				`${ args.root }sfcart-test/v1/rewards`,
				{
					body: JSON.stringify( { create_order: args.createOrder } ),
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': args.nonce,
					},
					method: 'POST',
				}
			);
			return { body: await response.json(), status: response.status };
		},
		{ createOrder, nonce: config.nonce, root: config.root }
	);
}

test( 'threshold rewards cross and reverse without duplicate effects', async ( {
	page,
} ) => {
	test.setTimeout( 600_000 );
	const config = await adminConfig( page );
	await clearCart( page );
	const couponId = await searchId(
		page,
		config,
		config.couponsPath,
		'REWARD5'
	);
	const giftId = await searchId(
		page,
		config,
		config.productsPath,
		'Batch 2 Variable Product'
	);
	expect( couponId ).toBeGreaterThan( 0 );
	expect( giftId ).toBeGreaterThan( 0 );

	const milestones: Milestone[] = [
		{
			id: 'coupon-15',
			enabled: true,
			type: 'coupon',
			threshold: 15,
			label: 'Five off',
			pending_message:
				'Spend {{remaining_amount}} more to unlock {{reward}}.',
			achieved_message: '{{reward}} unlocked.',
			coupon_id: couponId,
			gift_product_id: 0,
			gift_variation_id: 0,
		},
		{
			id: 'gift-30',
			enabled: true,
			type: 'gift',
			threshold: 30,
			label: 'Variable gift',
			pending_message:
				'Spend {{remaining_amount}} more to unlock {{reward}}.',
			achieved_message: '{{reward}} unlocked.',
			coupon_id: 0,
			gift_product_id: giftId,
			gift_variation_id: 0,
		},
		{
			id: 'shipping-40',
			enabled: true,
			type: 'free_shipping',
			threshold: 40,
			label: 'Free delivery',
			pending_message:
				'Spend {{remaining_amount}} more to unlock {{reward}}.',
			achieved_message: '{{reward}} unlocked.',
			coupon_id: 0,
			gift_product_id: 0,
			gift_variation_id: 0,
		},
	];

	try {
		await saveRewards( page, config, {
			allow_gift_removal: false,
			calculation_mode: 'subtotal',
			complete_message: 'Every reward is unlocked!',
			enabled: true,
			milestones,
			progress_design: 'steps',
		} );
		await page.goto( '/sfcart-classic/', {
			waitUntil: 'domcontentloaded',
		} );
		const product = page
			.locator( 'li.product' )
			.filter( { hasText: 'Batch 2 Simple Product' } );
		await product.locator( 'a.ajax_add_to_cart' ).click();

		const dialog = page.getByRole( 'dialog', { name: 'Your cart' } );
		await expect( dialog ).toBeVisible();
		const rewards = dialog.locator( '[data-sfcart-rewards]' );
		await expect( rewards ).toHaveClass( /sfcart-rewards--steps/ );
		await expect(
			rewards.locator( '.sfcart-rewards__steps li' ).first()
		).toHaveAttribute( 'aria-label', 'Five off unlocked.' );
		await expect( dialog.locator( '[data-sfcart-coupons]' ) ).toContainText(
			'reward5'
		);

		const source = dialog
			.locator( '.sfcart-item' )
			.filter( { hasText: 'Batch 2 Simple Product' } );
		await source.locator( '[data-sfcart-action="increase"]' ).click();
		const gift = dialog
			.locator( '.sfcart-item' )
			.filter( { hasText: 'Batch 2 Variable Product' } );
		await expect( gift ).toContainText( 'Free gift' );
		await expect(
			gift.locator( '[data-sfcart-action="remove"]' )
		).toHaveCount( 0 );
		await expect(
			gift.locator( '.sfcart-item__fixed-quantity' )
		).toContainText( '1' );
		expect( ( await inspect( page, config ) ).body.gift_count ).toBe( 1 );

		await source.locator( '[data-sfcart-action="increase"]' ).click();
		await expect( rewards ).toContainText( 'Every reward is unlocked!' );
		let details = await inspect( page, config );
		expect( details.status ).toBe( 200 );
		expect( details.body.gift_count ).toBe( 1 );
		expect( details.body.rates ).toContain( 'sfcart_reward_free_shipping' );

		await source.locator( '[data-sfcart-action="decrease"]' ).click();
		await expect( source.locator( '[data-sfcart-quantity]' ) ).toHaveValue(
			'2'
		);
		details = await inspect( page, config, true );
		expect( details.status, JSON.stringify( details.body ) ).toBe( 200 );
		expect( details.body.order_meta ).toEqual( [ 'coupon-15', 'gift-30' ] );
		expect( details.body.order_item_meta ).toEqual( [
			{ gift: 'yes', milestone_id: 'gift-30', type: 'gift' },
		] );

		details = await inspect( page, config );
		expect( details.body.rates ).not.toContain(
			'sfcart_reward_free_shipping'
		);
		await source.locator( '[data-sfcart-action="decrease"]' ).click();
		await expect( gift ).toHaveCount( 0 );
		expect( ( await inspect( page, config ) ).body.gift_count ).toBe( 0 );

		await saveRewards( page, config, {
			calculation_mode: 'total',
			progress_design: 'bar',
		} );
		await dialog.getByRole( 'button', { name: 'Close cart' } ).click();
		await page.locator( '.sfcart-floating-toggle' ).click();
		await expect( rewards ).toHaveClass( /sfcart-rewards--bar/ );
		await expect( dialog.locator( '[data-sfcart-coupons]' ) ).toContainText(
			'reward5'
		);

		const couponInput = dialog.getByLabel( 'Coupon code' );
		await couponInput.fill( 'SAVE5' );
		await dialog.getByRole( 'button', { name: 'Apply' } ).click();
		await expect(
			dialog.locator( '[data-sfcart-coupons]' )
		).not.toContainText( 'reward5' );
		const saveCoupon = dialog
			.locator( '.sfcart-applied-coupon' )
			.filter( { hasText: 'save5' } );
		await saveCoupon.getByRole( 'button' ).click();
		await expect( dialog.locator( '[data-sfcart-coupons]' ) ).toContainText(
			'reward5'
		);

		await source.locator( '[data-sfcart-action="increase"]' ).click();
		await saveRewards( page, config, {
			allow_gift_removal: true,
			progress_design: 'compact',
		} );
		await dialog.getByRole( 'button', { name: 'Close cart' } ).click();
		await page.locator( '.sfcart-floating-toggle' ).click();
		await expect( rewards ).toHaveClass( /sfcart-rewards--compact/ );
		const removableGift = dialog
			.locator( '.sfcart-item' )
			.filter( { hasText: 'Batch 2 Variable Product' } );
		await removableGift.locator( '[data-sfcart-action="remove"]' ).click();
		await expect( removableGift ).toHaveCount( 0 );
		expect( ( await inspect( page, config ) ).body.gift_count ).toBe( 0 );

		await source.locator( '[data-sfcart-action="decrease"]' ).click();
		await source.locator( '[data-sfcart-action="increase"]' ).click();
		await expect(
			dialog
				.locator( '.sfcart-item' )
				.filter( { hasText: 'Batch 2 Variable Product' } )
		).toHaveCount( 1 );
		expect( ( await inspect( page, config ) ).body.gift_count ).toBe( 1 );
	} finally {
		await saveRewards( page, config, { enabled: false } );
		await inspect( page, config );
	}
} );

test( 'reward fixture rejects anonymous requests', async ( { request } ) => {
	const response = await request.post( '/wp-json/sfcart-test/v1/rewards' );
	// Test-only routes may be hidden entirely from logged-out clients.
	expect( [ 401, 403, 404 ] ).toContain( response.status() );
} );
