const path = require( 'node:path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	entry: {
		admin: path.resolve( process.cwd(), 'assets/admin/index.tsx' ),
		'cart-toggle': path.resolve(
			process.cwd(),
			'assets/blocks/cart-toggle/index.tsx'
		),
		frontend: path.resolve( process.cwd(), 'assets/frontend/index.ts' ),
	},
	output: {
		...defaultConfig.output,
		clean: true,
		filename: '[name].js',
		path: path.resolve( process.cwd(), 'build' ),
	},
};
