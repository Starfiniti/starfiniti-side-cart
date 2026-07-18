import { createElement, render } from '@wordpress/element';

import { App } from './App';
import './style.scss';

const mount = document.getElementById( 'sfcart-admin-root' );
const config = window.sfcartAdminConfig;

if ( mount && config ) {
	render( createElement( App, { config } ), mount );
}
