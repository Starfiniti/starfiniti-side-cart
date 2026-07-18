import {
	BaggageClaim,
	ShoppingBag,
	ShoppingBasket,
	ShoppingCart,
	type LucideIcon,
} from 'lucide-react';
import { __ } from '@wordpress/i18n';

import type { CartIconName } from './types';

export type PresetCartIcon = Exclude< CartIconName, 'custom' >;

export const CART_ICON_OPTIONS: Array< {
	value: PresetCartIcon;
	label: string;
	Icon: LucideIcon;
} > = [
	{
		value: 'shopping-cart',
		label: __( 'Shopping cart', 'starfiniti-cart' ),
		Icon: ShoppingCart,
	},
	{
		value: 'shopping-bag',
		label: __( 'Shopping bag', 'starfiniti-cart' ),
		Icon: ShoppingBag,
	},
	{
		value: 'shopping-basket',
		label: __( 'Shopping basket', 'starfiniti-cart' ),
		Icon: ShoppingBasket,
	},
	{
		value: 'baggage-claim',
		label: __( 'Shopping trolley', 'starfiniti-cart' ),
		Icon: BaggageClaim,
	},
];

type Props = {
	icon: CartIconName;
	customUrl: string;
	size?: number;
	className?: string;
};

export function CartIcon( {
	icon,
	customUrl,
	size = 24,
	className = '',
}: Props ) {
	if ( icon === 'custom' && customUrl ) {
		return (
			<img
				className={ className }
				src={ customUrl }
				alt=""
				width={ size }
				height={ size }
			/>
		);
	}

	const option =
		CART_ICON_OPTIONS.find( ( current ) => current.value === icon ) ??
		CART_ICON_OPTIONS[ 0 ];
	const Icon = option.Icon;

	return (
		<Icon
			className={ className }
			size={ size }
			strokeWidth={ 1.8 }
			aria-hidden="true"
		/>
	);
}
