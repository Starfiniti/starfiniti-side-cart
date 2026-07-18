import './style.scss';

import { DrawerController } from './drawer';

function initialize(): void {
	const root = document.querySelector< HTMLElement >( '[data-sfcart-root]' );
	const config = window.sfcartConfig;

	if ( ! root || ! config ) {
		return;
	}

	new DrawerController( root, config ).connect();
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', initialize, { once: true } );
} else {
	initialize();
}

export { dispatchCartEvent, SFCART_EVENTS } from './events';
