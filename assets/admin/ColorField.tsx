import {
	Button,
	ColorPicker,
	Dropdown,
	TextControl,
} from '@wordpress/components';
import { sprintf, __ } from '@wordpress/i18n';

import { isHexColor } from './settings';

type Props = {
	label: string;
	value: string;
	onChange: ( value: string ) => void;
	enableAlpha?: boolean;
};

function transparentColor( value: string ): string {
	const opaque = isHexColor( value ) ? value.slice( 0, 7 ) : '#ffffff';

	return `${ opaque }00`;
}

export function ColorField( {
	label,
	value,
	onChange,
	enableAlpha = false,
}: Props ) {
	const valid = isHexColor( value );
	const pickerColor = valid ? value : '#000000';

	return (
		<div className={ `sfcart-color-field${ valid ? '' : ' has-error' }` }>
			<TextControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ label }
				value={ value }
				onChange={ onChange }
			/>
			<Dropdown
				className="sfcart-color-field__dropdown"
				contentClassName="sfcart-color-picker-popover"
				expandOnMobile
				headerTitle={ label }
				popoverProps={ { placement: 'bottom-end' } }
				renderToggle={ ( { isOpen, onToggle } ) => (
					<Button
						className="sfcart-color-field__swatch"
						onClick={ onToggle }
						aria-expanded={ isOpen }
						aria-label={ sprintf(
							/* translators: %s is a color field label. */
							__( 'Choose %s', 'starfiniti-cart' ),
							label
						) }
					>
						<span
							className="sfcart-color-field__swatch-sample"
							style={ {
								backgroundColor: valid ? value : 'transparent',
							} }
							aria-hidden="true"
						/>
					</Button>
				) }
				renderContent={ () => (
					<div className="sfcart-color-picker-popover__content">
						<ColorPicker
							color={ pickerColor }
							copyFormat="hex"
							enableAlpha={ enableAlpha }
							onChange={ onChange }
						/>
						{ enableAlpha && (
							<Button
								variant="secondary"
								onClick={ () =>
									onChange( transparentColor( value ) )
								}
							>
								{ __( 'Make transparent', 'starfiniti-cart' ) }
							</Button>
						) }
					</div>
				) }
			/>
		</div>
	);
}
