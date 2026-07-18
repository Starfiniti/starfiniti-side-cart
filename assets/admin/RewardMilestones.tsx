import {
	Button,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

import { EntityPicker } from './EntityPicker';
import type { CouponSummary, ProductSummary, RewardMilestone } from './types';

type Props = {
	milestones: RewardMilestone[];
	onChange: ( milestones: RewardMilestone[] ) => void;
	searchCoupons: (
		query: string,
		include?: number[]
	) => Promise< CouponSummary[] >;
	searchProducts: (
		query: string,
		include?: number[]
	) => Promise< ProductSummary[] >;
};

const createMilestone = ( index: number ): RewardMilestone => ( {
	id: `milestone-${ Date.now() }-${ index + 1 }`,
	enabled: true,
	type: 'free_shipping',
	threshold: 0,
	label: '',
	pending_message: 'Spend {{remaining_amount}} more to unlock {{reward}}.',
	achieved_message: '{{reward}} unlocked.',
	coupon_id: 0,
	gift_product_id: 0,
	gift_variation_id: 0,
} );

export function RewardMilestones( {
	milestones,
	onChange,
	searchCoupons,
	searchProducts,
}: Props ) {
	const update = ( index: number, patch: Partial< RewardMilestone > ) =>
		onChange(
			milestones.map( ( milestone, current ) =>
				current === index ? { ...milestone, ...patch } : milestone
			)
		);

	return (
		<div className="sfcart-reward-editor">
			<div className="sfcart-reward-editor__heading">
				<div>
					<h3 className="sfcart-reward-editor__title">
						{ __( 'Reward milestones', 'starfiniti-cart' ) }
					</h3>
					<p className="sfcart-reward-editor__description">
						{ __(
							'Customers unlock every enabled milestone whose threshold they reach.',
							'starfiniti-cart'
						) }
					</p>
				</div>
				<Button
					variant="secondary"
					disabled={ milestones.length >= 10 }
					onClick={ () =>
						onChange( [
							...milestones,
							createMilestone( milestones.length ),
						] )
					}
				>
					{ __( 'Add milestone', 'starfiniti-cart' ) }
				</Button>
			</div>

			{ milestones.length === 0 && (
				<p className="sfcart-reward-editor__empty">
					{ __(
						'Add a milestone to configure free shipping, a coupon, or a free gift.',
						'starfiniti-cart'
					) }
				</p>
			) }

			{ milestones.map( ( milestone, index ) => (
				<section
					className="sfcart-reward-milestone"
					key={ milestone.id }
				>
					<header>
						<strong>
							{ sprintf(
								/* translators: %d: milestone number. */
								__( 'Milestone %d', 'starfiniti-cart' ),
								index + 1
							) }
						</strong>
						<Button
							variant="link"
							isDestructive
							onClick={ () =>
								onChange(
									milestones.filter(
										( _, current ) => current !== index
									)
								)
							}
						>
							{ __( 'Remove', 'starfiniti-cart' ) }
						</Button>
					</header>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Enabled', 'starfiniti-cart' ) }
						checked={ milestone.enabled }
						onChange={ ( enabled ) => update( index, { enabled } ) }
					/>
					<div className="sfcart-field-grid">
						<SelectControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Reward type', 'starfiniti-cart' ) }
							value={ milestone.type }
							options={ [
								{
									label: __(
										'Free shipping',
										'starfiniti-cart'
									),
									value: 'free_shipping',
								},
								{
									label: __( 'Coupon', 'starfiniti-cart' ),
									value: 'coupon',
								},
								{
									label: __( 'Free gift', 'starfiniti-cart' ),
									value: 'gift',
								},
							] }
							onChange={ ( type ) =>
								update( index, {
									type: type as RewardMilestone[ 'type' ],
								} )
							}
						/>
						<TextControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							type="number"
							min={ 0 }
							label={ __(
								'Threshold amount',
								'starfiniti-cart'
							) }
							value={ milestone.threshold }
							onChange={ ( threshold ) =>
								update( index, {
									threshold: Math.max(
										0,
										Number( threshold ) || 0
									),
								} )
							}
						/>
					</div>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Reward label', 'starfiniti-cart' ) }
						help={ __(
							'Leave blank to use the translated reward type.',
							'starfiniti-cart'
						) }
						value={ milestone.label }
						onChange={ ( label ) => update( index, { label } ) }
					/>

					{ milestone.type === 'coupon' && (
						<EntityPicker< CouponSummary >
							label={ __(
								'Search reward coupon',
								'starfiniti-cart'
							) }
							selected={
								milestone.coupon_id
									? [ milestone.coupon_id ]
									: []
							}
							search={ searchCoupons }
							display={ ( coupon ) => coupon.code }
							meta={ ( coupon ) =>
								`${ coupon.type } · ${ coupon.amount }`
							}
							single
							onChange={ ( identifiers ) =>
								update( index, {
									coupon_id: identifiers[ 0 ] ?? 0,
								} )
							}
						/>
					) }

					{ milestone.type === 'gift' && (
						<>
							<EntityPicker< ProductSummary >
								label={ __(
									'Search gift product',
									'starfiniti-cart'
								) }
								selected={
									milestone.gift_product_id
										? [ milestone.gift_product_id ]
										: []
								}
								search={ searchProducts }
								display={ ( product ) => product.name }
								meta={ ( product ) =>
									[ product.sku, product.price ]
										.filter( Boolean )
										.join( ' · ' )
								}
								single
								onChange={ ( identifiers ) =>
									update( index, {
										gift_product_id: identifiers[ 0 ] ?? 0,
									} )
								}
							/>
							<TextControl
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								type="number"
								min={ 0 }
								label={ __(
									'Variation ID (optional)',
									'starfiniti-cart'
								) }
								help={ __(
									'Leave blank to use the first available variation.',
									'starfiniti-cart'
								) }
								value={ milestone.gift_variation_id || '' }
								onChange={ ( value ) =>
									update( index, {
										gift_variation_id: Math.max(
											0,
											Number( value ) || 0
										),
									} )
								}
							/>
						</>
					) }

					<div className="sfcart-field-grid">
						<TextControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Pending message', 'starfiniti-cart' ) }
							help="{{remaining_amount}}, {{reward}}"
							value={ milestone.pending_message }
							onChange={ ( value ) =>
								update( index, { pending_message: value } )
							}
						/>
						<TextControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __(
								'Achieved message',
								'starfiniti-cart'
							) }
							help="{{reward}}"
							value={ milestone.achieved_message }
							onChange={ ( value ) =>
								update( index, { achieved_message: value } )
							}
						/>
					</div>
				</section>
			) ) }
		</div>
	);
}
