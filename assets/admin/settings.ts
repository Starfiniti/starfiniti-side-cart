import { __ } from '@wordpress/i18n';

import type { LanguageSettings, Settings } from './types';

export type ValidationSection =
	| 'cart'
	| 'design'
	| 'upsells'
	| 'rewards'
	| 'special-addon'
	| 'tools';

export type ValidationIssue = {
	path: string;
	label: string;
	message: string;
	section: ValidationSection;
};

export function languageValue(
	language: LanguageSettings,
	key: keyof LanguageSettings,
	fallback: string
): string {
	return language[ key ].trim() || fallback;
}

export function isHexColor( value: string ): boolean {
	return /^#[0-9a-f]{6}(?:[0-9a-f]{2})?$/i.test( value );
}

type Rgba = [ number, number, number, number ];

function colorChannels( color: string ): Rgba {
	const hex = color.slice( 1 );
	const alpha =
		hex.length === 8 ? Number.parseInt( hex.slice( 6, 8 ), 16 ) / 255 : 1;

	return [
		Number.parseInt( hex.slice( 0, 2 ), 16 ) / 255,
		Number.parseInt( hex.slice( 2, 4 ), 16 ) / 255,
		Number.parseInt( hex.slice( 4, 6 ), 16 ) / 255,
		alpha,
	];
}

function compositeColor( color: Rgba, backdrop: Rgba ): Rgba {
	const alpha = color[ 3 ] + backdrop[ 3 ] * ( 1 - color[ 3 ] );

	if ( alpha <= 0 ) {
		return [ 1, 1, 1, 1 ];
	}

	return [
		( color[ 0 ] * color[ 3 ] +
			backdrop[ 0 ] * backdrop[ 3 ] * ( 1 - color[ 3 ] ) ) /
			alpha,
		( color[ 1 ] * color[ 3 ] +
			backdrop[ 1 ] * backdrop[ 3 ] * ( 1 - color[ 3 ] ) ) /
			alpha,
		( color[ 2 ] * color[ 3 ] +
			backdrop[ 2 ] * backdrop[ 3 ] * ( 1 - color[ 3 ] ) ) /
			alpha,
		alpha,
	];
}

function channelLuminance( channel: number ): number {
	return channel <= 0.03928
		? channel / 12.92
		: Math.pow( ( channel + 0.055 ) / 1.055, 2.4 );
}

function relativeLuminance( color: Rgba ): number {
	return (
		0.2126 * channelLuminance( color[ 0 ] ) +
		0.7152 * channelLuminance( color[ 1 ] ) +
		0.0722 * channelLuminance( color[ 2 ] )
	);
}

export function contrastRatio(
	foreground: string,
	background: string,
	canvas = '#ffffff'
): number {
	if (
		! isHexColor( foreground ) ||
		! isHexColor( background ) ||
		! isHexColor( canvas )
	) {
		return Number.POSITIVE_INFINITY;
	}

	const white: Rgba = [ 1, 1, 1, 1 ];
	const canvasColor = compositeColor( colorChannels( canvas ), white );
	const backgroundColor = compositeColor(
		colorChannels( background ),
		canvasColor
	);
	const foregroundColor = compositeColor(
		colorChannels( foreground ),
		backgroundColor
	);
	const lighter = Math.max(
		relativeLuminance( foregroundColor ),
		relativeLuminance( backgroundColor )
	);
	const darker = Math.min(
		relativeLuminance( foregroundColor ),
		relativeLuminance( backgroundColor )
	);

	return ( lighter + 0.05 ) / ( darker + 0.05 );
}

export function readableTextColor(
	background: string,
	canvas = '#ffffff'
): '#000000' | '#ffffff' {
	return contrastRatio( '#000000', background, canvas ) >=
		contrastRatio( '#ffffff', background, canvas )
		? '#000000'
		: '#ffffff';
}

const fieldLabels: Record< string, string > = {
	'cart.position': __( 'Drawer position', 'starfiniti-cart' ),
	'cart.width': __( 'Drawer width', 'starfiniti-cart' ),
	'upsells.layout': __( 'Recommendation layout', 'starfiniti-cart' ),
	'upsells.placement': __( 'Recommendation position', 'starfiniti-cart' ),
	'upsells.mode': __( 'Recommendation source', 'starfiniti-cart' ),
	'upsells.ordering': __( 'Product ordering', 'starfiniti-cart' ),
	'upsells.display_limit': __( 'Display limit', 'starfiniti-cart' ),
	analytics_retention_days: __( 'Analytics retention', 'starfiniti-cart' ),
	'special_addon.product_id': __(
		'Special add-on product',
		'starfiniti-cart'
	),
	'special_addon.image_size': __( 'Add-on image size', 'starfiniti-cart' ),
	'design.floating_icon_id': __(
		'Floating cart custom icon',
		'starfiniti-cart'
	),
	'design.shortcode_icon_id': __(
		'Header cart custom icon',
		'starfiniti-cart'
	),
	'design.floating_badge_color': __(
		'Floating count text color',
		'starfiniti-cart'
	),
	'design.shortcode_badge_color': __(
		'Header count text color',
		'starfiniti-cart'
	),
};

export function validationSection( path: string ): ValidationSection {
	if ( path.startsWith( 'design.' ) ) {
		return 'design';
	}
	if ( path.startsWith( 'upsells.' ) ) {
		return 'upsells';
	}
	if ( path.startsWith( 'rewards.' ) ) {
		return 'rewards';
	}
	if ( path.startsWith( 'special_addon.' ) ) {
		return 'special-addon';
	}
	if ( path.startsWith( 'cart.' ) || path.startsWith( 'language.' ) ) {
		return 'cart';
	}
	return 'tools';
}

export function validationFieldLabel( path: string ): string {
	if ( fieldLabels[ path ] ) {
		return fieldLabels[ path ];
	}
	if ( path.startsWith( 'rewards.milestones.' ) ) {
		return __( 'Reward milestone', 'starfiniti-cart' );
	}
	return path
		.split( '.' )
		.at( -1 )!
		.replaceAll( '_', ' ' )
		.replace( /^./, ( character ) => character.toUpperCase() );
}

export function settingsValidationIssues(
	settings: Settings
): ValidationIssue[] {
	const issues: ValidationIssue[] = [];
	const add = ( path: string, message: string ) =>
		issues.push( {
			path,
			label: validationFieldLabel( path ),
			message,
			section: validationSection( path ),
		} );
	const range = (
		path: string,
		value: number,
		min: number,
		max: number,
		message: string
	) => {
		if ( ! Number.isFinite( value ) || value < min || value > max ) {
			add( path, message );
		}
	};

	range(
		'cart.width',
		settings.cart.width,
		320,
		640,
		__( 'Use a value from 320 to 640 pixels.', 'starfiniti-cart' )
	);
	range(
		'design.border_radius',
		settings.design.border_radius,
		0,
		32,
		__( 'Use a value from 0 to 32 pixels.', 'starfiniti-cart' )
	);
	range(
		'design.overlay_opacity',
		settings.design.overlay_opacity,
		0,
		90,
		__( 'Use a value from 0 to 90 percent.', 'starfiniti-cart' )
	);
	range(
		'design.floating_size',
		settings.design.floating_size,
		44,
		72,
		__( 'Use a value from 44 to 72 pixels.', 'starfiniti-cart' )
	);
	range(
		'design.floating_border_radius',
		settings.design.floating_border_radius,
		0,
		50,
		__( 'Use a value from 0 to 50 percent.', 'starfiniti-cart' )
	);
	range(
		'design.shortcode_icon_size',
		settings.design.shortcode_icon_size,
		16,
		64,
		__( 'Use a value from 16 to 64 pixels.', 'starfiniti-cart' )
	);
	range(
		'design.shortcode_text_size',
		settings.design.shortcode_text_size,
		10,
		32,
		__( 'Use a value from 10 to 32 pixels.', 'starfiniti-cart' )
	);
	range(
		'design.shortcode_border_width',
		settings.design.shortcode_border_width,
		0,
		4,
		__( 'Use a value from 0 to 4 pixels.', 'starfiniti-cart' )
	);
	range(
		'design.shortcode_border_radius',
		settings.design.shortcode_border_radius,
		0,
		32,
		__( 'Use a value from 0 to 32 pixels.', 'starfiniti-cart' )
	);
	range(
		'upsells.display_limit',
		settings.upsells.display_limit,
		1,
		12,
		__( 'Show between 1 and 12 products.', 'starfiniti-cart' )
	);
	range(
		'special_addon.image_size',
		settings.special_addon.image_size,
		32,
		96,
		__( 'Use a value from 32 to 96 pixels.', 'starfiniti-cart' )
	);
	range(
		'analytics_retention_days',
		settings.analytics_retention_days,
		30,
		3650,
		__( 'Use a value from 30 to 3650 days.', 'starfiniti-cart' )
	);

	if (
		settings.design.floating_icon === 'custom' &&
		settings.design.floating_icon_id <= 0
	) {
		add(
			'design.floating_icon_id',
			__( 'Upload or select an image.', 'starfiniti-cart' )
		);
	}
	if (
		settings.design.shortcode_icon === 'custom' &&
		settings.design.shortcode_icon_id <= 0
	) {
		add(
			'design.shortcode_icon_id',
			__( 'Upload or select an image.', 'starfiniti-cart' )
		);
	}
	if (
		settings.special_addon.enabled &&
		settings.special_addon.product_id <= 0
	) {
		add(
			'special_addon.product_id',
			__(
				'Select a product before enabling the add-on.',
				'starfiniti-cart'
			)
		);
	}

	const colors: Array< [ string, string ] > = [
		...Object.entries( settings.design )
			.filter( ( [ key ] ) =>
				[
					'accent',
					'accent_hover',
					'background',
					'text',
					'muted',
					'border',
					'success',
					'danger',
					'floating_background',
					'floating_hover',
					'floating_icon_color',
					'floating_badge_background',
					'floating_badge_color',
					'shortcode_background',
					'shortcode_hover',
					'shortcode_icon_color',
					'shortcode_border',
					'shortcode_badge_background',
					'shortcode_badge_color',
				].includes( key )
			)
			.map(
				( [ key, value ] ) =>
					[ `design.${ key }`, String( value ) ] as [ string, string ]
			),
		...Object.entries( settings.special_addon )
			.filter( ( [ key ] ) =>
				[
					'background',
					'accent',
					'heading_color',
					'description_color',
				].includes( key )
			)
			.map(
				( [ key, value ] ) =>
					[ `special_addon.${ key }`, String( value ) ] as [
						string,
						string,
					]
			),
	];
	for ( const [ path, value ] of colors ) {
		if ( ! isHexColor( value ) ) {
			add(
				path,
				__(
					'Enter a valid 6- or 8-digit hexadecimal color.',
					'starfiniti-cart'
				)
			);
		}
	}

	settings.rewards.milestones.forEach( ( milestone, index ) => {
		if ( milestone.threshold < 0 ) {
			add(
				`rewards.milestones.${ index }.threshold`,
				__( 'Threshold must be zero or greater.', 'starfiniti-cart' )
			);
		}
		if ( milestone.type === 'coupon' && milestone.coupon_id <= 0 ) {
			add(
				`rewards.milestones.${ index }.coupon_id`,
				__( 'Select a coupon.', 'starfiniti-cart' )
			);
		}
		if ( milestone.type === 'gift' && milestone.gift_product_id <= 0 ) {
			add(
				`rewards.milestones.${ index }.gift_product_id`,
				__( 'Select a gift product.', 'starfiniti-cart' )
			);
		}
	} );

	return issues;
}

export function settingsAreValid( settings: Settings ): boolean {
	return settingsValidationIssues( settings ).length === 0;
}

export function toggleIdentifier(
	identifiers: number[],
	identifier: number,
	selected: boolean,
	limit = 20
): number[] {
	if ( selected ) {
		return Array.from( new Set( [ ...identifiers, identifier ] ) ).slice(
			0,
			limit
		);
	}

	return identifiers.filter( ( current ) => current !== identifier );
}
