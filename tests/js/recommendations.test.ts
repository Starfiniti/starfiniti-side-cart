import { recommendationDisplayLimit } from '../../assets/recommendations';

describe( 'recommendationDisplayLimit', () => {
	it( 'limits feature cards to one product', () => {
		expect( recommendationDisplayLimit( 'style1', 12 ) ).toBe( 1 );
	} );

	it( 'limits compact cards to three products while honoring lower limits', () => {
		expect( recommendationDisplayLimit( 'style2', 12 ) ).toBe( 3 );
		expect( recommendationDisplayLimit( 'style2', 2 ) ).toBe( 2 );
	} );

	it( 'leaves list and carousel limits configurable', () => {
		expect( recommendationDisplayLimit( 'style3', 12 ) ).toBe( 12 );
		expect( recommendationDisplayLimit( 'carousel', 8 ) ).toBe( 8 );
	} );

	it( 'normalizes invalid configured limits', () => {
		expect( recommendationDisplayLimit( 'style3', 0 ) ).toBe( 1 );
	} );
} );
