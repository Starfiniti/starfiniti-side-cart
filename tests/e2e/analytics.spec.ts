import { expect, type Page, test } from '@playwright/test';

type AdminConfig = {
	nonce: string;
	root: string;
	analyticsPath: string;
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

test( 'analytics reconcile paid attributed orders, refunds, dashboard, and CSV', async ( {
	page,
} ) => {
	test.setTimeout( 180_000 );
	await page.goto( '/sfcart-classic/', { waitUntil: 'domcontentloaded' } );
	const cartOpenRecorded = page.waitForResponse(
		( response ) =>
			response.url().includes( 'sfcart_track_event' ) &&
			( response.request().postData() ?? '' ).includes(
				'event_type=cart_open'
			)
	);
	await page.locator( '.sfcart-floating-toggle' ).click();
	await cartOpenRecorded;

	const config = await adminConfig( page );

	const attribution = await page.evaluate( async ( admin ) => {
		const response = await fetch(
			`${ admin.root }sfcart-test/v1/attribution`,
			{
				headers: { 'X-WP-Nonce': admin.nonce },
				method: 'POST',
			}
		);
		return { body: await response.json(), status: response.status };
	}, config );
	expect( attribution.status ).toBe( 200 );

	const query = '?from=1980-01-01&to=2099-12-31';
	const overview = await page.evaluate(
		async ( { admin, suffix } ) => {
			const response = await fetch(
				`${ admin.root }${ admin.analyticsPath.replace(
					/^\//,
					''
				) }/overview${ suffix }`,
				{ headers: { 'X-WP-Nonce': admin.nonce } }
			);
			return response.json();
		},
		{ admin: config, suffix: query }
	);
	expect( overview.overview.orders ).toBeGreaterThanOrEqual( 1 );
	expect( overview.overview.cart_opens ).toBeGreaterThanOrEqual( 1 );
	expect(
		Number( overview.overview.attributed_revenue )
	).toBeGreaterThanOrEqual( 18 );
	expect( Number( overview.overview.refunded ) ).toBeGreaterThanOrEqual( 9 );

	const popular = await page.evaluate(
		async ( { admin, suffix } ) => {
			const response = await fetch(
				`${ admin.root }${ admin.analyticsPath.replace(
					/^\//,
					''
				) }/popular${ suffix }`,
				{ headers: { 'X-WP-Nonce': admin.nonce } }
			);
			return response.json();
		},
		{ admin: config, suffix: query }
	);
	expect(
		popular.items.some(
			( item: { type: string; revenue: number } ) =>
				item.type === 'recommendation' && Number( item.revenue ) >= 18
		)
	).toBe( true );

	await page
		.getByRole( 'navigation', { name: 'Settings sections' } )
		.getByRole( 'button', { name: 'Analytics', exact: true } )
		.click();
	await expect( page.getByText( 'Cart opens' ).first() ).toBeVisible( {
		timeout: 60_000,
	} );
	await expect( page.getByText( 'Cart funnel' ) ).toBeVisible();
	await expect( page.getByText( 'Daily cart activity' ) ).toBeVisible();
	await expect( page.getByText( 'Order outcomes' ) ).toBeVisible();
	await expect( page.locator( '.sfcart-preview-panel' ) ).toBeVisible();
	await expect(
		page.getByText( 'Batch 4 Simple Recommendation' )
	).toBeVisible();

	const csv = await page.evaluate(
		async ( { admin, suffix } ) => {
			const response = await fetch(
				`${ admin.root }${ admin.analyticsPath.replace(
					/^\//,
					''
				) }/export${ suffix }`,
				{ headers: { 'X-WP-Nonce': admin.nonce } }
			);
			return {
				body: await response.text(),
				type: response.headers.get( 'content-type' ),
			};
		},
		{ admin: config, suffix: query }
	);
	expect( csv.type ).toContain( 'text/csv' );
	expect( csv.body ).toContain( 'Date,"Cart event",Action' );
	expect( csv.body ).toContain( ',order,' );
	expect( csv.body ).toContain( ',refund,' );
} );

test( 'analytics REST routes reject anonymous requests', async ( {
	request,
} ) => {
	const response = await request.get(
		'/wp-json/starfiniti-cart/v1/analytics/overview'
	);
	expect( [ 401, 403 ] ).toContain( response.status() );
} );
