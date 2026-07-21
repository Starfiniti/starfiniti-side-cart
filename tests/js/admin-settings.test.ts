import {
	isHexColor,
	languageValue,
	readableTextColor,
	toggleIdentifier,
} from '../../assets/admin/settings';
import type { LanguageSettings } from '../../assets/admin/types';
import { apiErrorDetails } from '../../assets/admin/api';

const language = {
	title: '',
	close: '',
	loading: '',
	empty_title: '',
	empty_message: '',
	continue_shopping: '',
	coupon_code: '',
	apply_coupon: '',
	checkout: '',
	view_cart: '',
	calculation_note: '',
	open_cart: '',
} satisfies LanguageSettings;

describe( 'administration settings helpers', () => {
	it( 'uses translated fallbacks only for blank language overrides', () => {
		expect( languageValue( language, 'title', 'Your cart' ) ).toBe(
			'Your cart'
		);
		expect(
			languageValue(
				{ ...language, title: 'Basket' },
				'title',
				'Your cart'
			)
		).toBe( 'Basket' );
		expect( languageValue( language, 'view_cart', 'View cart' ) ).toBe(
			'View cart'
		);
	} );

	it( 'validates strict six- and eight-digit color formats', () => {
		expect( isHexColor( '#1d4ed8' ) ).toBe( true );
		expect( isHexColor( '#1d4ed800' ) ).toBe( true );
		expect( isHexColor( '#fff' ) ).toBe( false );
		expect( isHexColor( 'red' ) ).toBe( false );
	} );

	it( 'selects readable primary-button text for custom accent colors', () => {
		expect( readableTextColor( '#1d4ed8' ) ).toBe( '#ffffff' );
		expect( readableTextColor( '#f3658f' ) ).toBe( '#000000' );
		expect( readableTextColor( '#ffffff00', '#ffffff' ) ).toBe( '#000000' );
	} );

	it( 'adds, deduplicates, limits, and removes selected identifiers', () => {
		expect( toggleIdentifier( [ 2 ], 2, true ) ).toEqual( [ 2 ] );
		expect( toggleIdentifier( [ 1, 2 ], 3, true, 2 ) ).toEqual( [ 1, 2 ] );
		expect( toggleIdentifier( [ 1, 2 ], 1, false ) ).toEqual( [ 2 ] );
	} );

	it( 'preserves field-level REST validation details', () => {
		expect(
			apiErrorDetails( {
				message: 'Some settings are invalid.',
				data: {
					fields: {
						'special_addon.product_id': 'Select a product.',
					},
				},
			} )
		).toEqual( {
			message: 'Some settings are invalid.',
			fields: {
				'special_addon.product_id': 'Select a product.',
			},
		} );
	} );
} );
