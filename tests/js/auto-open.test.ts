import { consumeAutoOpenFlag } from '../../assets/frontend/auto-open';

const cookie = { name: 'sfcart_auto_open', path: '/', domain: '' };

describe( 'classic add-to-cart auto-open flag', () => {
	afterEach( () => {
		document.cookie = 'sfcart_auto_open=; Max-Age=0; path=/';
	} );

	it( 'reports the flag once and deletes it immediately', () => {
		document.cookie = 'sfcart_auto_open=1; path=/';

		expect( consumeAutoOpenFlag( document, cookie ) ).toBe( true );
		expect( document.cookie ).not.toContain( 'sfcart_auto_open' );
		expect( consumeAutoOpenFlag( document, cookie ) ).toBe( false );
	} );

	it( 'ignores missing flags, unrelated cookies, and older configurations', () => {
		document.cookie = 'other_cookie=1; path=/';

		expect( consumeAutoOpenFlag( document, cookie ) ).toBe( false );
		expect( consumeAutoOpenFlag( document, undefined ) ).toBe( false );
		expect( document.cookie ).toContain( 'other_cookie=1' );

		document.cookie = 'other_cookie=; Max-Age=0; path=/';
	} );
} );
