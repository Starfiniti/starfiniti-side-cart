import { expect, type Page, test } from '@playwright/test';

type AdminConfig = {
	nonce: string;
	root: string;
	settingsPath: string;
	productsPath: string;
	relationshipsPath: string;
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

async function apiUrl( config: AdminConfig, path: string ): Promise< string > {
	return `${ config.root }${ path.replace( /^\//, '' ) }`;
}

async function productId(
	page: Page,
	config: AdminConfig,
	search: string
): Promise< number > {
	return page.evaluate(
		async ( { apiConfig, query } ) => {
			const response = await fetch(
				`${ apiConfig.root }${ apiConfig.productsPath.replace(
					/^\//,
					''
				) }?search=${ encodeURIComponent( query ) }`,
				{ headers: { 'X-WP-Nonce': apiConfig.nonce } }
			);
			const body = await response.json();
			return Number( body.items?.[ 0 ]?.id ?? 0 );
		},
		{ apiConfig: config, query: search }
	);
}

async function setUpsells(
	page: Page,
	config: AdminConfig,
	patch: Record< string, unknown >
): Promise< void > {
	const settingsUrl = await apiUrl( config, config.settingsPath );
	const status = await page.evaluate(
		async ( { nonce, settingsPath, upsellPatch } ) => {
			const currentResponse = await fetch( settingsPath, {
				headers: { 'X-WP-Nonce': nonce },
			} );
			const current = await currentResponse.json();
			current.settings.upsells.enabled = true;
			current.settings.upsells.heading = 'Complete your cart';
			Object.assign( current.settings.upsells, upsellPatch );
			const response = await fetch( settingsPath, {
				body: JSON.stringify( { settings: current.settings } ),
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': nonce,
				},
				method: 'PUT',
			} );
			return response.status;
		},
		{ nonce: config.nonce, settingsPath: settingsUrl, upsellPatch: patch }
	);
	expect( status ).toBe( 200 );
}

async function setLayout(
	page: Page,
	config: AdminConfig,
	layout: 'style1' | 'style2' | 'style3' | 'carousel'
): Promise< void > {
	await setUpsells( page, config, { layout } );
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
	await page.reload( { waitUntil: 'domcontentloaded' } );
}

test( 'native relationships, three layouts, variation adds, and attribution pass', async ( {
	page,
} ) => {
	test.setTimeout( 420_000 );
	const config = await adminConfig( page );
	const sourceId = await productId( page, config, 'Batch 2 Simple Product' );
	const recommendationId = await productId(
		page,
		config,
		'Batch 4 Simple Recommendation'
	);
	const variableId = await productId(
		page,
		config,
		'Batch 2 Variable Product'
	);
	const crossSellId = await productId(
		page,
		config,
		'Batch 2 Sold Individually'
	);
	expect( sourceId ).toBeGreaterThan( 0 );
	expect( recommendationId ).toBeGreaterThan( 0 );
	expect( variableId ).toBeGreaterThan( 0 );
	expect( crossSellId ).toBeGreaterThan( 0 );

	const relationshipStatus = await page.evaluate(
		async ( { apiConfig, crossSells, source, upsells } ) => {
			const path = `${
				apiConfig.root
			}${ apiConfig.relationshipsPath.replace( /^\//, '' ) }/${ source }`;
			const response = await fetch( path, {
				body: JSON.stringify( {
					cross_sell_ids: crossSells,
					upsell_ids: upsells,
				} ),
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': apiConfig.nonce,
				},
				method: 'PUT',
			} );
			const body = await response.json();
			return {
				ids: body.upsells?.map(
					( product: { id: number } ) => product.id
				),
				status: response.status,
			};
		},
		{
			apiConfig: config,
			source: sourceId,
			upsells: [ recommendationId, variableId ],
			crossSells: [ crossSellId ],
		}
	);
	expect( relationshipStatus.status ).toBe( 200 );
	expect( relationshipStatus.ids ).toEqual( [
		recommendationId,
		variableId,
	] );

	await setLayout( page, config, 'style1' );
	await clearCart( page );
	const sourceProduct = page
		.locator( 'li.product' )
		.filter( { hasText: 'Batch 2 Simple Product' } );
	await sourceProduct.locator( 'a.ajax_add_to_cart' ).click();

	const dialog = page.getByRole( 'dialog', { name: 'Your cart' } );
	await expect( dialog ).toBeVisible();
	const recommendations = dialog.locator( '[data-sfcart-recommendations]' );
	await expect( recommendations ).toHaveClass(
		/sfcart-recommendations--style1/
	);
	await expect( recommendations ).toContainText( 'Complete your cart' );
	await expect( recommendations ).toContainText(
		'Batch 2 Sold Individually'
	);

	const refreshRecommendations = async () => {
		await dialog.getByRole( 'button', { name: 'Close cart' } ).click();
		await page.locator( '.sfcart-floating-toggle' ).click();
		await expect( dialog ).toBeVisible();
	};

	await setUpsells( page, config, {
		excluded_product_ids: [ recommendationId ],
	} );
	await refreshRecommendations();
	await expect( recommendations ).not.toContainText(
		'Batch 4 Simple Recommendation'
	);

	await setUpsells( page, config, {
		excluded_product_ids: [],
		ordering: 'price_desc',
	} );
	await refreshRecommendations();
	await expect(
		recommendations.locator( '[data-sfcart-recommendation]' ).first()
	).toContainText( 'Batch 2 Variable Product' );

	await setUpsells( page, config, {
		always_show_defaults: false,
		default_product_ids: [ crossSellId ],
		mode: 'upsells',
		ordering: 'relevance',
	} );
	await refreshRecommendations();
	await expect( recommendations ).not.toContainText(
		'Batch 2 Sold Individually'
	);
	await setUpsells( page, config, { always_show_defaults: true } );
	await refreshRecommendations();
	await expect( recommendations ).toContainText(
		'Batch 2 Sold Individually'
	);
	await setUpsells( page, config, {
		always_show_defaults: false,
		default_product_ids: [],
		mode: 'both',
	} );
	await refreshRecommendations();

	const simpleCard = recommendations
		.locator( '[data-sfcart-recommendation]' )
		.filter( { hasText: 'Batch 4 Simple Recommendation' } );
	await simpleCard.getByRole( 'button', { name: 'Add to cart' } ).click();
	const addedSimple = dialog
		.locator( '.sfcart-item' )
		.filter( { hasText: 'Batch 4 Simple Recommendation' } );
	await expect( addedSimple ).toContainText(
		'Batch 4 Simple Recommendation'
	);
	await addedSimple.locator( '[data-sfcart-action="remove"]' ).click();
	await expect( recommendations ).toContainText(
		'Batch 4 Simple Recommendation'
	);

	const variableCard = recommendations
		.locator( '[data-sfcart-recommendation]' )
		.filter( { hasText: 'Batch 2 Variable Product' } );
	const variableAdd = variableCard.getByRole( 'button', {
		name: 'Choose options',
	} );
	await expect( variableAdd ).toBeDisabled();
	await variableCard.getByLabel( 'Choose Size' ).selectOption( {
		label: 'Small',
	} );
	await expect(
		variableCard.getByRole( 'button', { name: 'Add to cart' } )
	).toBeEnabled();
	await variableCard.getByRole( 'button', { name: 'Add to cart' } ).click();
	const addedVariable = dialog
		.locator( '.sfcart-item' )
		.filter( { hasText: 'Batch 2 Variable Product' } );
	await expect( addedVariable ).toContainText( 'Small' );

	for ( const layout of [ 'style2', 'style3', 'carousel' ] as const ) {
		await setLayout( page, config, layout );
		await refreshRecommendations();
		await expect( recommendations ).toHaveClass(
			new RegExp( `sfcart-recommendations--${ layout }` )
		);
		if ( layout === 'carousel' ) {
			await expect(
				recommendations.getByRole( 'button', {
					name: 'Next recommendation',
				} )
			).toBeVisible();
		}
	}

	for ( const placement of [
		'before_items',
		'after_items',
		'before_totals',
		'after_checkout',
	] as const ) {
		await setUpsells( page, config, { placement } );
		await refreshRecommendations();
		await expect( recommendations ).toHaveClass(
			new RegExp(
				`sfcart-recommendations--${ placement.replace( '_', '-' ) }`
			)
		);
		const actualPlacement = await recommendations.evaluate( ( element ) => {
			if (
				element.nextElementSibling?.matches( '[data-sfcart-items]' )
			) {
				return 'before_items';
			}
			if (
				element.previousElementSibling?.matches(
					'[data-sfcart-special-addon]'
				)
			) {
				return 'after_items';
			}
			if ( element.parentElement?.firstElementChild === element ) {
				return 'before_totals';
			}
			if (
				element.nextElementSibling?.matches(
					'[data-sfcart-footer-links]'
				)
			) {
				return 'after_checkout';
			}
			return 'unknown';
		} );
		expect( actualPlacement ).toBe( placement );

		if ( placement === 'before_totals' ) {
			const doesNotOverlapCoupon = await recommendations.evaluate(
				( element ) => {
					const coupon = element.nextElementSibling;
					if ( ! coupon ) {
						return false;
					}
					const recommendationsBox = element.getBoundingClientRect();
					const couponBox = coupon.getBoundingClientRect();
					return recommendationsBox.bottom <= couponBox.top + 1;
				}
			);
			expect( doesNotOverlapCoupon ).toBe( true );
		}

		if ( placement === 'after_checkout' ) {
			const doesNotOverlapFooterLinks = await recommendations.evaluate(
				( element ) => {
					const footerLinks = element.nextElementSibling;
					if ( ! footerLinks ) {
						return false;
					}
					const recommendationsBox = element.getBoundingClientRect();
					const linksBox = footerLinks.getBoundingClientRect();
					return recommendationsBox.bottom <= linksBox.top + 1;
				}
			);
			expect( doesNotOverlapFooterLinks ).toBe( true );
		}
	}
	await setUpsells( page, config, { placement: 'after_items' } );
	await refreshRecommendations();

	await setUpsells( page, config, { mode: 'cross_sells' } );
	await refreshRecommendations();
	await expect( recommendations ).toContainText(
		'Batch 2 Sold Individually'
	);
	await expect( recommendations ).not.toContainText(
		'Batch 4 Simple Recommendation'
	);
	await setUpsells( page, config, { mode: 'both' } );
	await refreshRecommendations();

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
	expect( attribution.body.item_recommendation ).toBe( 'yes' );
	expect( attribution.body.item_source ).toBe( 'upsell' );
	expect( Number( attribution.body.impression_count ) ).toBeGreaterThan( 0 );
	expect( Number( attribution.body.revenue ) ).toBe( 18 );
	expect( Number( attribution.body.refunded ) ).toBe( 9 );
	expect( attribution.body.refund_attributed ).toBe( 'yes' );
} );

test( 'relationship management rejects anonymous requests', async ( {
	request,
} ) => {
	const response = await request.get(
		'/wp-json/starfiniti-cart/v1/relationships/1'
	);
	expect( [ 401, 403 ] ).toContain( response.status() );
} );
