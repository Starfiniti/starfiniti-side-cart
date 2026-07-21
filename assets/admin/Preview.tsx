import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import type { CSSProperties } from 'react';
import { ChevronLeft, ChevronRight } from 'lucide-react';

import { CartIcon } from './CartIcon';
import { languageValue, readableTextColor } from './settings';
import type { ProductSummary, Settings } from './types';
import { buildRewardPreviewState } from './reward-preview';
import { recommendationDisplayLimit } from '../recommendations';

type Props = {
	settings: Settings;
	addonProduct: ProductSummary | null;
	showAddonConfiguration: boolean;
};

type PreviewItem = {
	id: number;
	name: string;
	price: number;
	quantity: number;
};

const initialItems: PreviewItem[] = [
	{ id: 1, name: 'Aurora Ceramic Mug', price: 18, quantity: 1 },
	{ id: 2, name: 'Linen Tote Bag', price: 24, quantity: 1 },
];

const money = ( value: number ) => `€${ value.toFixed( 2 ) }`;

function UpsellPreview( { settings }: { settings: Settings } ) {
	const [ carouselIndex, setCarouselIndex ] = useState( 0 );
	const products = [
		[ 'Gift Wrap', '€3.50' ],
		[ 'Candle Care Kit', '€12.00' ],
		[ 'Star Coaster Set', '€9.00' ],
		[ 'Silk Ribbon', '€6.00' ],
		[ 'Greeting Card', '€4.50' ],
		[ 'Mini Vase', '€16.00' ],
		[ 'Scented Sachet', '€8.50' ],
		[ 'Gift Tag Set', '€5.00' ],
		[ 'Travel Candle', '€14.00' ],
		[ 'Ceramic Coaster', '€11.00' ],
		[ 'Keepsake Box', '€19.00' ],
		[ 'Hand Cream', '€13.50' ],
	].slice(
		0,
		recommendationDisplayLimit(
			settings.upsells.layout,
			settings.upsells.display_limit
		)
	);
	const activeCarouselIndex = Math.min(
		carouselIndex,
		Math.max( 0, products.length - 1 )
	);
	const visibleProducts =
		settings.upsells.layout === 'carousel'
			? products.slice( activeCarouselIndex, activeCarouselIndex + 1 )
			: products;

	if ( ! settings.upsells.enabled ) {
		return null;
	}

	return (
		<div
			className={ `sfcart-preview-upsells sfcart-preview-upsells--${
				settings.upsells.layout
			} sfcart-preview-upsells--${ settings.upsells.placement.replaceAll(
				'_',
				'-'
			) }` }
		>
			<div className="sfcart-preview-upsells__header">
				<strong>
					{ settings.upsells.heading ||
						__( 'You may also like', 'starfiniti-cart' ) }
				</strong>
				{ settings.upsells.layout === 'carousel' &&
					products.length > 1 && (
						<div className="sfcart-preview-upsells__navigation">
							<button
								type="button"
								aria-label={ __(
									'Previous recommendation',
									'starfiniti-cart'
								) }
								disabled={ activeCarouselIndex === 0 }
								onClick={ () =>
									setCarouselIndex( ( index ) =>
										Math.max( 0, index - 1 )
									)
								}
							>
								<ChevronLeft size={ 15 } aria-hidden="true" />
							</button>
							<button
								type="button"
								aria-label={ __(
									'Next recommendation',
									'starfiniti-cart'
								) }
								disabled={
									activeCarouselIndex >= products.length - 1
								}
								onClick={ () =>
									setCarouselIndex( ( index ) =>
										Math.min(
											products.length - 1,
											index + 1
										)
									)
								}
							>
								<ChevronRight size={ 15 } aria-hidden="true" />
							</button>
						</div>
					) }
			</div>
			<div>
				{ visibleProducts.map( ( product ) => (
					<article key={ product[ 0 ] }>
						<span
							className="dashicons dashicons-star-filled"
							aria-hidden="true"
						/>
						<span className="sfcart-preview-upsells__content">
							<strong>{ product[ 0 ] }</strong>
							<small>{ product[ 1 ] }</small>
						</span>
						<button
							type="button"
							aria-label={ __(
								'Add recommendation',
								'starfiniti-cart'
							) }
						>
							+
						</button>
					</article>
				) ) }
			</div>
		</div>
	);
}

export function Preview( {
	settings,
	addonProduct,
	showAddonConfiguration,
}: Props ) {
	const [ items, setItems ] = useState( initialItems );
	const count = items.reduce( ( total, item ) => total + item.quantity, 0 );
	const subtotal = items.reduce(
		( total, item ) => total + item.price * item.quantity,
		0
	);
	const tax = subtotal - subtotal / 1.22;
	const rewardPreview = buildRewardPreviewState(
		settings.rewards,
		subtotal,
		money,
		{
			free_shipping: __( 'Free shipping', 'starfiniti-cart' ),
			coupon: __( 'Discount', 'starfiniti-cart' ),
			gift: __( 'Free gift', 'starfiniti-cart' ),
		}
	);

	const style = {
		'--sfcart-preview-accent': settings.design.accent,
		'--sfcart-preview-accent-text': readableTextColor(
			settings.design.accent,
			settings.design.background
		),
		'--sfcart-preview-floating-background':
			settings.design.floating_background,
		'--sfcart-preview-floating-icon': settings.design.floating_icon_color,
		'--sfcart-preview-floating-badge':
			settings.design.floating_badge_background,
		'--sfcart-preview-floating-badge-color':
			settings.design.floating_badge_color,
		'--sfcart-preview-floating-size': `${ settings.design.floating_size }px`,
		'--sfcart-preview-floating-radius': `${ settings.design.floating_border_radius }%`,
		'--sfcart-preview-background': settings.design.background,
		'--sfcart-preview-border': settings.design.border,
		'--sfcart-preview-muted': settings.design.muted,
		'--sfcart-preview-radius': `${ settings.design.border_radius }px`,
		'--sfcart-preview-success': settings.design.success,
		'--sfcart-preview-text': settings.design.text,
		'--sfcart-preview-width': `${ Math.min( settings.cart.width, 440 ) }px`,
		'--sfcart-preview-overlay': `${
			settings.design.overlay_opacity / 100
		}`,
		'--sfcart-preview-addon-accent': settings.special_addon.accent,
		'--sfcart-preview-addon-background': settings.special_addon.background,
		'--sfcart-preview-addon-heading': settings.special_addon.heading_color,
		'--sfcart-preview-addon-description':
			settings.special_addon.description_color,
		'--sfcart-preview-addon-image-size': `${ settings.special_addon.image_size }px`,
	} as CSSProperties;
	const addonImage =
		settings.special_addon.image_source === 'custom'
			? settings.special_addon.image_url
			: addonProduct?.image ?? '';
	const showAddonPreview =
		items.length > 0 &&
		( settings.special_addon.enabled || showAddonConfiguration );

	const changeQuantity = ( id: number, amount: number ) => {
		setItems( ( current ) =>
			current
				.map( ( item ) =>
					item.id === id
						? { ...item, quantity: item.quantity + amount }
						: item
				)
				.filter( ( item ) => item.quantity > 0 )
		);
	};

	return (
		<aside
			className="sfcart-preview-panel"
			aria-label={ __( 'Live cart preview', 'starfiniti-cart' ) }
		>
			<div className="sfcart-preview-panel__heading">
				<span className="sfcart-preview-live-dot" aria-hidden="true" />
				<strong>{ __( 'Live preview', 'starfiniti-cart' ) }</strong>
				<span className="sfcart-preview-size">
					{ settings.cart.width } px ·{ ' ' }
					{ settings.cart.position === 'right'
						? __( 'Right', 'starfiniti-cart' )
						: __( 'Left', 'starfiniti-cart' ) }
				</span>
			</div>
			<div
				className={ `sfcart-preview-stage is-${ settings.cart.position }` }
				style={ style }
			>
				<div className="sfcart-preview-storefront" aria-hidden="true">
					<span />
					<div>
						<i />
						<i />
						<i />
					</div>
					<small />
					<small />
					<small />
				</div>
				<div className="sfcart-preview-overlay" aria-hidden="true" />

				{ settings.cart.floating_button && (
					<div className="sfcart-preview-floating">
						<CartIcon
							icon={ settings.design.floating_icon }
							customUrl={ settings.design.floating_icon_url }
							size={ 18 }
							className="sfcart-preview-cart-icon"
						/>
						<span className="sfcart-preview-floating__label">
							{ languageValue(
								settings.language,
								'open_cart',
								__( 'Open cart', 'starfiniti-cart' )
							) }
						</span>
						<b>{ count }</b>
					</div>
				) }

				<div className="sfcart-preview-drawer">
					<header>
						<div>
							<h3>
								{ languageValue(
									settings.language,
									'title',
									__( 'Your cart', 'starfiniti-cart' )
								) }
							</h3>
							<small className="sfcart-preview-count">
								{ count } { __( 'items', 'starfiniti-cart' ) }
							</small>
						</div>
						<button
							className="sfcart-preview-close"
							type="button"
							aria-label={ __(
								'Close preview cart',
								'starfiniti-cart'
							) }
						>
							×
						</button>
					</header>

					<div className="sfcart-preview-scroll">
						{ rewardPreview.enabled && items.length > 0 && (
							<div
								className={ `sfcart-preview-reward is-${
									rewardPreview.design
								}${
									rewardPreview.complete ? ' is-complete' : ''
								}` }
							>
								<strong>{ rewardPreview.message }</strong>
								{ rewardPreview.design === 'steps' ? (
									<ol>
										{ rewardPreview.milestones.map(
											( milestone ) => (
												<li
													className={
														milestone.achieved
															? 'is-achieved'
															: undefined
													}
													key={ milestone.id }
													title={ milestone.message }
												>
													{ milestone.label }
												</li>
											)
										) }
									</ol>
								) : (
									<div
										className="sfcart-preview-reward__progress"
										role="progressbar"
										aria-label={ rewardPreview.message }
										aria-valuemin={ 0 }
										aria-valuemax={ 100 }
										aria-valuenow={ Math.round(
											rewardPreview.progress
										) }
									>
										<span
											style={ {
												width: `${ rewardPreview.progress }%`,
											} }
										/>
										{ rewardPreview.design === 'bar' &&
											rewardPreview.milestones.map(
												( milestone ) => (
													<i
														className={
															milestone.achieved
																? 'is-achieved'
																: undefined
														}
														key={ milestone.id }
														style={ {
															insetInlineStart: `${ milestone.position }%`,
														} }
														title={
															milestone.message
														}
													/>
												)
											) }
									</div>
								) }
							</div>
						) }
						{ items.length === 0 ? (
							<div className="sfcart-preview-empty">
								<CartIcon
									icon={ settings.design.floating_icon }
									customUrl={
										settings.design.floating_icon_url
									}
									size={ 40 }
									className="sfcart-preview-cart-icon sfcart-preview-cart-icon--empty"
								/>
								<strong>
									{ languageValue(
										settings.language,
										'empty_title',
										__(
											'Your cart is empty',
											'starfiniti-cart'
										)
									) }
								</strong>
								<p>
									{ languageValue(
										settings.language,
										'empty_message',
										__(
											'Add something you love and it will appear here.',
											'starfiniti-cart'
										)
									) }
								</p>
								{ settings.cart.show_continue_shopping && (
									<button
										type="button"
										onClick={ () =>
											setItems( initialItems )
										}
									>
										{ languageValue(
											settings.language,
											'continue_shopping',
											__(
												'Continue shopping',
												'starfiniti-cart'
											)
										) }
									</button>
								) }
							</div>
						) : (
							<>
								{ settings.upsells.placement ===
									'before_items' && (
									<UpsellPreview settings={ settings } />
								) }
								<div className="sfcart-preview-products">
									{ items.map( ( item ) => (
										<div
											className="sfcart-preview-product"
											key={ item.id }
										>
											<span
												className="sfcart-preview-product__image"
												aria-hidden="true"
											>
												<span className="dashicons dashicons-star-filled" />
											</span>
											<div>
												<div className="sfcart-preview-product__title">
													<strong>
														{ item.name }
													</strong>
													<button
														type="button"
														onClick={ () =>
															changeQuantity(
																item.id,
																-item.quantity
															)
														}
														aria-label={ __(
															'Remove item',
															'starfiniti-cart'
														) }
													>
														×
													</button>
												</div>
												<div className="sfcart-preview-product__bottom">
													<div className="sfcart-preview-stepper">
														<button
															type="button"
															onClick={ () =>
																changeQuantity(
																	item.id,
																	-1
																)
															}
														>
															−
														</button>
														<span>
															{ item.quantity }
														</span>
														<button
															type="button"
															onClick={ () =>
																changeQuantity(
																	item.id,
																	1
																)
															}
														>
															+
														</button>
													</div>
													<b>
														{ money(
															item.price *
																item.quantity
														) }
													</b>
												</div>
											</div>
										</div>
									) ) }
								</div>

								{ showAddonPreview && (
									<div
										className={ `sfcart-preview-addon sfcart-preview-addon--${
											settings.special_addon
												.selection_type
										}${
											settings.special_addon
												.image_enabled && addonImage
												? ' sfcart-preview-addon--has-image'
												: ''
										}${
											settings.special_addon.enabled
												? ''
												: ' is-preview-disabled'
										}` }
									>
										{ settings.special_addon
											.image_enabled &&
											addonImage && (
												<img
													className="sfcart-preview-addon__image"
													src={ addonImage }
													alt=""
												/>
											) }
										<span className="sfcart-preview-addon__content">
											<strong>
												{ settings.special_addon
													.heading ||
													__(
														'Add this to your order',
														'starfiniti-cart'
													) }
											</strong>
											<small>
												{ settings.special_addon
													.description ||
													__(
														'Optional cart add-on',
														'starfiniti-cart'
													) }
											</small>
										</span>
										<span className="sfcart-preview-addon__price">
											{ addonProduct?.price || '€3.50' }
										</span>
										<span className="sfcart-preview-addon__control">
											<input
												type="checkbox"
												checked={
													settings.special_addon
														.preselected
												}
												readOnly
												aria-label={ __(
													'Preview add-on selection',
													'starfiniti-cart'
												) }
											/>
											{ settings.special_addon
												.selection_type ===
												'toggle' && (
												<span className="sfcart-preview-addon__switch" />
											) }
										</span>
									</div>
								) }

								{ settings.upsells.placement ===
									'after_items' && (
									<UpsellPreview settings={ settings } />
								) }
							</>
						) }
					</div>

					{ items.length > 0 && (
						<footer>
							{ settings.upsells.placement ===
								'before_totals' && (
								<UpsellPreview settings={ settings } />
							) }
							{ settings.cart.coupons && (
								<div className="sfcart-preview-coupon">
									<span>
										{ languageValue(
											settings.language,
											'coupon_code',
											__(
												'Coupon code',
												'starfiniti-cart'
											)
										) }
									</span>
									<button type="button">
										{ languageValue(
											settings.language,
											'apply_coupon',
											__( 'Apply', 'starfiniti-cart' )
										) }
									</button>
								</div>
							) }
							<div className="sfcart-preview-summary">
								<span>{ __( 'Subtotal', 'woocommerce' ) }</span>
								<b>{ money( subtotal ) }</b>
							</div>
							{ settings.cart.show_shipping && (
								<div className="sfcart-preview-summary">
									<span>
										{ __( 'Shipping', 'woocommerce' ) }
									</span>
									<b className="is-free">
										{ __( 'Free', 'starfiniti-cart' ) }
									</b>
								</div>
							) }
							{ settings.cart.show_tax && (
								<div className="sfcart-preview-summary">
									<span>
										{ __(
											'Tax (22% included)',
											'starfiniti-cart'
										) }
									</span>
									<b>{ money( tax ) }</b>
								</div>
							) }
							<div className="sfcart-preview-total">
								<strong>
									{ __( 'Total', 'woocommerce' ) }
								</strong>
								<strong>{ money( subtotal ) }</strong>
							</div>
							<a
								href="#sfcart-preview"
								onClick={ ( event ) => event.preventDefault() }
							>
								{ languageValue(
									settings.language,
									'checkout',
									__(
										'Proceed to checkout',
										'starfiniti-cart'
									)
								) }
							</a>
							{ settings.upsells.placement ===
								'after_checkout' && (
								<UpsellPreview settings={ settings } />
							) }
							{ ( settings.cart.show_cart_link ||
								settings.cart.show_continue_shopping ) && (
								<div className="sfcart-preview-footer-links">
									{ settings.cart.show_cart_link && (
										<button
											className="sfcart-preview-cart-link"
											type="button"
										>
											{ languageValue(
												settings.language,
												'view_cart',
												__(
													'View cart',
													'starfiniti-cart'
												)
											) }
										</button>
									) }
									{ settings.cart.show_continue_shopping && (
										<button
											className="sfcart-preview-continue-shopping"
											type="button"
										>
											{ languageValue(
												settings.language,
												'continue_shopping',
												__(
													'Continue shopping',
													'starfiniti-cart'
												)
											) }
										</button>
									) }
								</div>
							) }
							{ settings.language.calculation_note.trim() !==
								'' && (
								<small className="sfcart-preview-note">
									{ settings.language.calculation_note.trim() }
								</small>
							) }
						</footer>
					) }
				</div>
			</div>
			<div className="sfcart-preview-panel__footer">
				{ __(
					'Interactive sample — try the quantity steppers, or empty the cart to see the empty state.',
					'starfiniti-cart'
				) }
			</div>
		</aside>
	);
}
