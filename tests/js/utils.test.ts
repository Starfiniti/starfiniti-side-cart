import {
	clampQuantity,
	formatTemplate,
	itemCountLabel,
} from '../../assets/frontend/utils';

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

	it( 'prefers the server-pluralized item count label', () => {
		const labels = { item: 'item', items: 'items' };

		expect( itemCountLabel( 2, '2 izdelka', labels ) ).toBe( '2 izdelka' );
		expect( itemCountLabel( 5, '5 izdelkov', labels ) ).toBe(
			'5 izdelkov'
		);
		expect( itemCountLabel( 1, undefined, labels ) ).toBe( '1 item' );
		expect( itemCountLabel( 3, '', labels ) ).toBe( '3 items' );
	} );
} );
