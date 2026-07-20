import { Button, RangeControl, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { CART_ICON_OPTIONS, CartIcon } from './CartIcon';
import { ColorField } from './ColorField';
import { MediaPicker } from './MediaPicker';
import { contrastRatio, readableTextColor } from './settings';
import type { CartIconName, CartSettings, DesignSettings } from './types';

type ColorKey =
	| 'floating_background'
	| 'floating_hover'
	| 'floating_icon_color'
	| 'floating_badge_background'
	| 'floating_badge_color'
	| 'shortcode_background'
	| 'shortcode_hover'
	| 'shortcode_icon_color'
	| 'shortcode_border'
	| 'shortcode_badge_background'
	| 'shortcode_badge_color';

type ColorFieldDefinition = {
	key: ColorKey;
	label: string;
	enableAlpha?: boolean;
};

const floatingFields: ColorFieldDefinition[] = [
	{
		key: 'floating_background',
		label: __( 'Button background', 'starfiniti-cart' ),
		enableAlpha: true,
	},
	{
		key: 'floating_hover',
		label: __( 'Hover background', 'starfiniti-cart' ),
		enableAlpha: true,
	},
	{
		key: 'floating_icon_color',
		label: __( 'Icon color', 'starfiniti-cart' ),
	},
	{
		key: 'floating_badge_background',
		label: __( 'Count background', 'starfiniti-cart' ),
	},
	{
		key: 'floating_badge_color',
		label: __( 'Count text color', 'starfiniti-cart' ),
	},
];

const shortcodeFields: ColorFieldDefinition[] = [
	{
		key: 'shortcode_background',
		label: __( 'Container background', 'starfiniti-cart' ),
		enableAlpha: true,
	},
	{
		key: 'shortcode_hover',
		label: __( 'Hover background', 'starfiniti-cart' ),
		enableAlpha: true,
	},
	{
		key: 'shortcode_icon_color',
		label: __( 'Icon and total color', 'starfiniti-cart' ),
	},
	{
		key: 'shortcode_border',
		label: __( 'Border color', 'starfiniti-cart' ),
		enableAlpha: true,
	},
	{
		key: 'shortcode_badge_background',
		label: __( 'Count background', 'starfiniti-cart' ),
	},
	{
		key: 'shortcode_badge_color',
		label: __( 'Count text color', 'starfiniti-cart' ),
	},
];

type Props = {
	cart: CartSettings;
	design: DesignSettings;
	onCartChange: ( patch: Partial< CartSettings > ) => void;
	onChange: ( patch: Partial< DesignSettings > ) => void;
};

type IconPickerProps = {
	icon: CartIconName;
	iconId: number;
	iconUrl: string;
	label: string;
	onChange: ( patch: {
		icon?: CartIconName;
		iconId?: number;
		iconUrl?: string;
	} ) => void;
};

function IconPicker( {
	icon,
	iconId,
	iconUrl,
	label,
	onChange,
}: IconPickerProps ) {
	return (
		<div className="sfcart-trigger-icon-field">
			<strong className="sfcart-field-label">{ label }</strong>
			<div
				className="sfcart-cart-icon-picker"
				role="group"
				aria-label={ label }
			>
				{ CART_ICON_OPTIONS.map( ( option ) => (
					<button
						key={ option.value }
						type="button"
						className={
							icon === option.value ? 'is-selected' : undefined
						}
						aria-pressed={ icon === option.value }
						onClick={ () => onChange( { icon: option.value } ) }
					>
						<span>
							<CartIcon
								icon={ option.value }
								customUrl=""
								size={ 26 }
							/>
						</span>
						<small>{ option.label }</small>
					</button>
				) ) }
				<button
					type="button"
					className={ icon === 'custom' ? 'is-selected' : undefined }
					aria-pressed={ icon === 'custom' }
					onClick={ () => onChange( { icon: 'custom' } ) }
				>
					<span className="sfcart-cart-icon-picker__custom">
						{ iconUrl ? (
							<CartIcon
								icon="custom"
								customUrl={ iconUrl }
								size={ 26 }
							/>
						) : (
							<span
								className="dashicons dashicons-upload"
								aria-hidden="true"
							/>
						) }
					</span>
					<small>{ __( 'Custom', 'starfiniti-cart' ) }</small>
				</button>
			</div>
			{ icon === 'custom' && (
				<div
					className={
						iconId > 0
							? 'sfcart-cart-icon-upload'
							: 'sfcart-cart-icon-upload has-error'
					}
				>
					<MediaPicker
						label={ __( 'Custom icon file', 'starfiniti-cart' ) }
						id={ iconId }
						url={ iconUrl }
						onChange={ ( media ) =>
							onChange( {
								iconId: media.id,
								iconUrl: media.url,
							} )
						}
					/>
					{ iconId === 0 && (
						<p className="sfcart-field-error">
							{ __(
								'Choose an image before saving the custom icon.',
								'starfiniti-cart'
							) }
						</p>
					) }
				</div>
			) }
		</div>
	);
}

function ColorControls( {
	design,
	fields,
	onChange,
}: Pick< Props, 'design' | 'onChange' > & {
	fields: ColorFieldDefinition[];
} ) {
	const updateColor = ( key: ColorKey, value: string ) => {
		const patch: Partial< DesignSettings > = { [ key ]: value };

		if (
			key === 'floating_badge_background' &&
			contrastRatio( design.floating_badge_color, value ) < 4.5
		) {
			patch.floating_badge_color = readableTextColor( value );
		}

		if (
			key === 'shortcode_badge_background' &&
			contrastRatio( design.shortcode_badge_color, value ) < 4.5
		) {
			patch.shortcode_badge_color = readableTextColor( value );
		}

		onChange( patch );
	};

	return (
		<div className="sfcart-field-grid sfcart-color-grid">
			{ fields.map( ( field ) => (
				<ColorField
					key={ field.key }
					label={ field.label }
					value={ design[ field.key ] }
					enableAlpha={ field.enableAlpha }
					onChange={ ( value ) => updateColor( field.key, value ) }
				/>
			) ) }
		</div>
	);
}

export function TriggerSettings( {
	cart,
	design,
	onCartChange,
	onChange,
}: Props ) {
	return (
		<div className="sfcart-trigger-settings-grid">
			<section className="sfcart-trigger-settings__panel">
				<div className="sfcart-trigger-settings__header">
					<div>
						<h3>{ __( 'Floating cart', 'starfiniti-cart' ) }</h3>
						<p>
							{ __(
								'Fixed storefront button. Its icon and design are completely independent.',
								'starfiniti-cart'
							) }
						</p>
					</div>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Enable floating cart',
							'starfiniti-cart'
						) }
						checked={ cart.floating_button }
						onChange={ ( value ) =>
							onCartChange( { floating_button: value } )
						}
					/>
				</div>

				<div className="sfcart-trigger-sample">
					<div
						className="sfcart-trigger-sample__floating"
						style={ {
							background: design.floating_background,
							borderRadius: `${ design.floating_border_radius }%`,
							color: design.floating_icon_color,
							height: design.floating_size,
							width: design.floating_size,
						} }
						role="img"
						aria-label={ __(
							'Floating cart preview',
							'starfiniti-cart'
						) }
					>
						<CartIcon
							className="sfcart-trigger-sample__icon"
							icon={ design.floating_icon }
							customUrl={ design.floating_icon_url }
							size={ 22 }
						/>
						<b
							className="sfcart-trigger-sample__badge"
							style={ {
								background: design.floating_badge_background,
								color: design.floating_badge_color,
							} }
						>
							3
						</b>
					</div>
				</div>

				<IconPicker
					label={ __( 'Floating cart icon', 'starfiniti-cart' ) }
					icon={ design.floating_icon }
					iconId={ design.floating_icon_id }
					iconUrl={ design.floating_icon_url }
					onChange={ ( patch ) =>
						onChange( {
							...( patch.icon && { floating_icon: patch.icon } ),
							...( patch.iconId !== undefined && {
								floating_icon_id: patch.iconId,
							} ),
							...( patch.iconUrl !== undefined && {
								floating_icon_url: patch.iconUrl,
							} ),
						} )
					}
				/>
				<ColorControls
					design={ design }
					fields={ floatingFields }
					onChange={ onChange }
				/>
				<div className="sfcart-field-grid">
					<RangeControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Button size (px)', 'starfiniti-cart' ) }
						min={ 44 }
						max={ 72 }
						value={ design.floating_size }
						onChange={ ( value ) =>
							onChange( { floating_size: value ?? 56 } )
						}
					/>
					<RangeControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Border radius (%)', 'starfiniti-cart' ) }
						min={ 0 }
						max={ 50 }
						value={ design.floating_border_radius }
						onChange={ ( value ) =>
							onChange( { floating_border_radius: value ?? 50 } )
						}
					/>
				</div>
			</section>

			<section className="sfcart-trigger-settings__panel">
				<div className="sfcart-trigger-settings__header">
					<div>
						<h3>
							{ __(
								'Header / shortcode cart',
								'starfiniti-cart'
							) }
						</h3>
						<p>
							{ __(
								'Compact icon for headers and menus. It shows no forced “Cart” title.',
								'starfiniti-cart'
							) }
						</p>
					</div>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Enable header cart', 'starfiniti-cart' ) }
						checked={ cart.header_cart }
						onChange={ ( value ) =>
							onCartChange( { header_cart: value } )
						}
					/>
				</div>

				<div className="sfcart-shortcode-copy">
					<code>[starfiniti_cart]</code>
					<Button
						variant="secondary"
						onClick={ () => {
							const copy =
								navigator.clipboard?.writeText(
									'[starfiniti_cart]'
								);
							void copy?.catch( () => undefined );
						} }
					>
						{ __( 'Copy shortcode', 'starfiniti-cart' ) }
					</Button>
				</div>

				<div className="sfcart-trigger-sample">
					<div
						className="sfcart-trigger-sample__shortcode"
						style={ {
							background: design.shortcode_background,
							borderColor: design.shortcode_border,
							borderWidth: design.shortcode_border_width,
							borderRadius: design.shortcode_border_radius,
							color: design.shortcode_icon_color,
							fontSize: design.shortcode_text_size,
						} }
						role="img"
						aria-label={ __(
							'Header cart preview',
							'starfiniti-cart'
						) }
					>
						<span className="sfcart-trigger-sample__icon-wrap">
							<CartIcon
								className="sfcart-trigger-sample__icon"
								icon={ design.shortcode_icon }
								customUrl={ design.shortcode_icon_url }
								size={ design.shortcode_icon_size }
							/>
							{ design.shortcode_show_count && (
								<b
									className="sfcart-trigger-sample__badge"
									style={ {
										background:
											design.shortcode_badge_background,
										color: design.shortcode_badge_color,
									} }
								>
									3
								</b>
							) }
						</span>
						{ design.shortcode_show_total && (
							<strong>€49.90</strong>
						) }
					</div>
				</div>

				<IconPicker
					label={ __( 'Header cart icon', 'starfiniti-cart' ) }
					icon={ design.shortcode_icon }
					iconId={ design.shortcode_icon_id }
					iconUrl={ design.shortcode_icon_url }
					onChange={ ( patch ) =>
						onChange( {
							...( patch.icon && { shortcode_icon: patch.icon } ),
							...( patch.iconId !== undefined && {
								shortcode_icon_id: patch.iconId,
							} ),
							...( patch.iconUrl !== undefined && {
								shortcode_icon_url: patch.iconUrl,
							} ),
						} )
					}
				/>
				<div className="sfcart-trigger-toggles">
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Show product count', 'starfiniti-cart' ) }
						checked={ design.shortcode_show_count }
						onChange={ ( value ) =>
							onChange( { shortcode_show_count: value } )
						}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Show cart total', 'starfiniti-cart' ) }
						checked={ design.shortcode_show_total }
						onChange={ ( value ) =>
							onChange( { shortcode_show_total: value } )
						}
					/>
				</div>
				<ColorControls
					design={ design }
					fields={ shortcodeFields }
					onChange={ onChange }
				/>
				<div className="sfcart-field-grid">
					<RangeControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __(
							'Container border width (px)',
							'starfiniti-cart'
						) }
						help={ __(
							'Set to 0 to remove the outline completely.',
							'starfiniti-cart'
						) }
						min={ 0 }
						max={ 4 }
						value={ design.shortcode_border_width }
						onChange={ ( value ) =>
							onChange( { shortcode_border_width: value ?? 1 } )
						}
					/>
					<RangeControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Icon size (px)', 'starfiniti-cart' ) }
						min={ 16 }
						max={ 64 }
						value={ design.shortcode_icon_size }
						onChange={ ( value ) =>
							onChange( { shortcode_icon_size: value ?? 24 } )
						}
					/>
					<RangeControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __(
							'Total text size (px)',
							'starfiniti-cart'
						) }
						min={ 10 }
						max={ 32 }
						value={ design.shortcode_text_size }
						onChange={ ( value ) =>
							onChange( { shortcode_text_size: value ?? 14 } )
						}
					/>
					<RangeControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Corner radius (px)', 'starfiniti-cart' ) }
						min={ 0 }
						max={ 32 }
						value={ design.shortcode_border_radius }
						onChange={ ( value ) =>
							onChange( { shortcode_border_radius: value ?? 8 } )
						}
					/>
				</div>
			</section>
		</div>
	);
}
