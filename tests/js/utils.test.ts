import { clampQuantity, formatTemplate } from '../../assets/frontend/utils';

describe( 'side-cart frontend utilities', () => {
	it( 'clamps quantities to product limits', () => {
		expect( clampQuantity( 0, 1, 5 ) ).toBe( 1 );
		expect( clampQuantity( 7, 1, 5 ) ).toBe( 5 );
		expect( clampQuantity( 7, 1, null ) ).toBe( 7 );
	} );

	it( 'formats translated positional and sequential placeholders', () => {
		expect( formatTemplate( 'Save %1$s (%2$d%%)', [ '$5', 20 ] ) ).toBe(
			'Save $5 (20%)'
		);
		expect( formatTemplate( 'Remove %s', [ 'Product' ] ) ).toBe(
			'Remove Product'
		);
	} );
} );
