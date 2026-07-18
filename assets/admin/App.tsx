import {
	Button,
	Card,
	CardBody,
	Notice,
	RangeControl,
	SelectControl,
	Spinner,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useCallback, useEffect, useState } from '@wordpress/element';

import {
	configureApi,
	apiErrorDetails,
	errorMessage,
	loadSettings,
	saveSettings,
	searchCoupons,
	searchProducts,
} from './api';
import { AnalyticsDashboard } from './AnalyticsDashboard';
import { EntityPicker } from './EntityPicker';
import { MediaPicker } from './MediaPicker';
import { MigrationTool } from './MigrationTool';
import { Preview } from './Preview';
import { RelationshipManager } from './RelationshipManager';
import { RewardMilestones } from './RewardMilestones';
import { TriggerSettings } from './TriggerSettings';
import {
	settingsValidationIssues,
	validationFieldLabel,
	validationSection,
} from './settings';
import { ColorField } from './ColorField';
import type {
	AdminConfig,
	CartSettings,
	DesignSettings,
	LanguageSettings,
	ProductSummary,
	RewardSettings,
	Settings,
	SpecialAddonSettings,
	UpsellSettings,
} from './types';

type Section =
	| 'cart'
	| 'design'
	| 'upsells'
	| 'rewards'
	| 'special-addon'
	| 'analytics'
	| 'tools';

const sections: Array< { id: Section; label: string; icon: string } > = [
	{
		id: 'cart',
		label: __( 'Cart', 'starfiniti-cart' ),
		icon: 'dashicons-cart',
	},
	{
		id: 'design',
		label: __( 'Design', 'starfiniti-cart' ),
		icon: 'dashicons-art',
	},
	{
		id: 'upsells',
		label: __( 'Upsells', 'starfiniti-cart' ),
		icon: 'dashicons-chart-line',
	},
	{
		id: 'rewards',
		label: __( 'Rewards', 'starfiniti-cart' ),
		icon: 'dashicons-awards',
	},
	{
		id: 'special-addon',
		label: __( 'Special Add-on', 'starfiniti-cart' ),
		icon: 'dashicons-plus-alt2',
	},
	{
		id: 'analytics',
		label: __( 'Analytics', 'starfiniti-cart' ),
		icon: 'dashicons-chart-bar',
	},
	{
		id: 'tools',
		label: __( 'Tools', 'starfiniti-cart' ),
		icon: 'dashicons-admin-tools',
	},
];

const colorFields: Array< {
	key: keyof Pick<
		DesignSettings,
		| 'accent'
		| 'accent_hover'
		| 'background'
		| 'text'
		| 'muted'
		| 'border'
		| 'success'
		| 'danger'
	>;
	label: string;
	enableAlpha?: boolean;
} > = [
	{ key: 'accent', label: __( 'Accent color', 'starfiniti-cart' ) },
	{
		key: 'accent_hover',
		label: __( 'Accent hover color', 'starfiniti-cart' ),
	},
	{
		key: 'background',
		label: __( 'Background color', 'starfiniti-cart' ),
		enableAlpha: true,
	},
	{ key: 'text', label: __( 'Text color', 'starfiniti-cart' ) },
	{ key: 'muted', label: __( 'Muted text color', 'starfiniti-cart' ) },
	{
		key: 'border',
		label: __( 'Border color', 'starfiniti-cart' ),
		enableAlpha: true,
	},
	{ key: 'success', label: __( 'Success color', 'starfiniti-cart' ) },
	{ key: 'danger', label: __( 'Error color', 'starfiniti-cart' ) },
];

const addonColorFields: Array< {
	key: keyof Pick<
		SpecialAddonSettings,
		'background' | 'accent' | 'heading_color' | 'description_color'
	>;
	label: string;
	enableAlpha?: boolean;
} > = [
	{
		key: 'background',
		label: __( 'Offer background', 'starfiniti-cart' ),
		enableAlpha: true,
	},
	{ key: 'accent', label: __( 'Control color', 'starfiniti-cart' ) },
	{ key: 'heading_color', label: __( 'Heading color', 'starfiniti-cart' ) },
	{
		key: 'description_color',
		label: __( 'Description color', 'starfiniti-cart' ),
	},
];

const languageFields: Array< {
	key: keyof LanguageSettings;
	label: string;
	placeholder: string;
} > = [
	{
		key: 'title',
		label: __( 'Drawer title', 'starfiniti-cart' ),
		placeholder: __( 'Your cart', 'starfiniti-cart' ),
	},
	{
		key: 'close',
		label: __( 'Close button label', 'starfiniti-cart' ),
		placeholder: __( 'Close cart', 'starfiniti-cart' ),
	},
	{
		key: 'loading',
		label: __( 'Loading message', 'starfiniti-cart' ),
		placeholder: __( 'Loading your cart…', 'starfiniti-cart' ),
	},
	{
		key: 'empty_title',
		label: __( 'Empty cart title', 'starfiniti-cart' ),
		placeholder: __( 'Your cart is empty', 'starfiniti-cart' ),
	},
	{
		key: 'empty_message',
		label: __( 'Empty cart message', 'starfiniti-cart' ),
		placeholder: __(
			'Add something you love and it will appear here.',
			'starfiniti-cart'
		),
	},
	{
		key: 'continue_shopping',
		label: __( 'Continue shopping label', 'starfiniti-cart' ),
		placeholder: __( 'Continue shopping', 'starfiniti-cart' ),
	},
	{
		key: 'coupon_code',
		label: __( 'Coupon field label', 'starfiniti-cart' ),
		placeholder: __( 'Coupon code', 'starfiniti-cart' ),
	},
	{
		key: 'apply_coupon',
		label: __( 'Apply coupon label', 'starfiniti-cart' ),
		placeholder: __( 'Apply', 'starfiniti-cart' ),
	},
	{
		key: 'checkout',
		label: __( 'Checkout button label', 'starfiniti-cart' ),
		placeholder: __( 'Proceed to checkout', 'starfiniti-cart' ),
	},
	{
		key: 'view_cart',
		label: __( 'View cart label', 'starfiniti-cart' ),
		placeholder: __( 'View cart', 'starfiniti-cart' ),
	},
	{
		key: 'calculation_note',
		label: __( 'Calculation note', 'starfiniti-cart' ),
		placeholder: __( 'Hidden when blank', 'starfiniti-cart' ),
	},
	{
		key: 'open_cart',
		label: __( 'Accessible cart button label', 'starfiniti-cart' ),
		placeholder: __( 'Open cart', 'starfiniti-cart' ),
	},
];

const upsellPlacements: Array< {
	value: UpsellSettings[ 'placement' ];
	label: string;
	description: string;
	recommended?: boolean;
} > = [
	{
		value: 'after_items',
		label: __( 'After cart items', 'starfiniti-cart' ),
		description: __(
			'Natural discovery inside the scrollable cart.',
			'starfiniti-cart'
		),
		recommended: true,
	},
	{
		value: 'before_items',
		label: __( 'Before cart items', 'starfiniti-cart' ),
		description: __(
			'High visibility directly below rewards.',
			'starfiniti-cart'
		),
	},
	{
		value: 'before_totals',
		label: __( 'Above totals', 'starfiniti-cart' ),
		description: __(
			'Persistent offer at the top of the sticky footer.',
			'starfiniti-cart'
		),
	},
	{
		value: 'after_checkout',
		label: __( 'Below checkout', 'starfiniti-cart' ),
		description: __(
			'A low-pressure offer below the primary action.',
			'starfiniti-cart'
		),
	},
];

const upsellLayouts: Array< {
	value: UpsellSettings[ 'layout' ];
	label: string;
	description: string;
} > = [
	{
		value: 'style1',
		label: __( 'Feature card', 'starfiniti-cart' ),
		description: __(
			'Wide product cards stacked vertically with maximum clarity.',
			'starfiniti-cart'
		),
	},
	{
		value: 'style2',
		label: __( 'Compact cards', 'starfiniti-cart' ),
		description: __(
			'Three products per row for fast visual scanning.',
			'starfiniti-cart'
		),
	},
	{
		value: 'style3',
		label: __( 'Product list', 'starfiniti-cart' ),
		description: __(
			'Dense full-width rows for larger assortments.',
			'starfiniti-cart'
		),
	},
	{
		value: 'carousel',
		label: __( 'Slider', 'starfiniti-cart' ),
		description: __(
			'One focused product at a time with accessible previous and next controls.',
			'starfiniti-cart'
		),
	},
];

type Props = {
	config: AdminConfig;
};

export function App( { config }: Props ) {
	const [ active, setActive ] = useState< Section >( 'cart' );
	const [ settings, setSettings ] = useState< Settings | null >( null );
	const [ addonProduct, setAddonProduct ] = useState< ProductSummary | null >(
		null
	);
	const [ busy, setBusy ] = useState( true );
	const [ notice, setNotice ] = useState< {
		status: 'error' | 'success';
		message: string;
		details?: string[];
	} | null >( null );

	useEffect( () => configureApi( config ), [ config ] );

	const reload = useCallback( async () => {
		setBusy( true );
		setNotice( null );
		try {
			const response = await loadSettings( config );
			setSettings( response.settings );
		} catch ( error ) {
			setNotice( { status: 'error', message: errorMessage( error ) } );
		} finally {
			setBusy( false );
		}
	}, [ config ] );

	useEffect( () => {
		void reload();
	}, [ reload ] );

	const addonProductId = settings?.special_addon.product_id ?? 0;
	useEffect( () => {
		let cancelled = false;
		if ( addonProductId === 0 ) {
			setAddonProduct( null );
			return () => {
				cancelled = true;
			};
		}

		void searchProducts( config, '', [ addonProductId ] )
			.then( ( products ) => {
				if ( ! cancelled ) {
					setAddonProduct( products[ 0 ] ?? null );
				}
			} )
			.catch( () => {
				if ( ! cancelled ) {
					setAddonProduct( null );
				}
			} );

		return () => {
			cancelled = true;
		};
	}, [ addonProductId, config ] );

	const save = async () => {
		if ( ! settings ) {
			return;
		}

		const localIssues = settingsValidationIssues( settings );
		if ( localIssues.length > 0 ) {
			setActive( localIssues[ 0 ].section );
			setNotice( {
				status: 'error',
				message: __(
					'Please correct these settings before saving:',
					'starfiniti-cart'
				),
				details: localIssues.map(
					( issue ) => `${ issue.label }: ${ issue.message }`
				),
			} );
			return;
		}

		setBusy( true );
		setNotice( null );
		try {
			const response = await saveSettings( config, settings );
			setSettings( response.settings );
			setNotice( {
				status: 'success',
				message: __( 'Settings saved.', 'starfiniti-cart' ),
			} );
		} catch ( error ) {
			const details = apiErrorDetails( error );
			const fieldEntries = Object.entries( details.fields );
			if ( fieldEntries.length > 0 ) {
				setActive( validationSection( fieldEntries[ 0 ][ 0 ] ) );
			}
			setNotice( {
				status: 'error',
				message: details.message,
				details: fieldEntries.map(
					( [ path, message ] ) =>
						`${ validationFieldLabel( path ) }: ${ message }`
				),
			} );
		} finally {
			setBusy( false );
		}
	};

	if ( ! settings ) {
		return (
			<div className="sfcart-admin-loading">
				{ busy && <Spinner /> }
				{ notice && (
					<Notice status="error" isDismissible={ false }>
						{ notice.message }
					</Notice>
				) }
			</div>
		);
	}

	const updateCart = ( patch: Partial< CartSettings > ) =>
		setSettings( { ...settings, cart: { ...settings.cart, ...patch } } );
	const updateDesign = ( patch: Partial< DesignSettings > ) =>
		setSettings( {
			...settings,
			design: { ...settings.design, ...patch },
		} );
	const updateLanguage = ( patch: Partial< LanguageSettings > ) =>
		setSettings( {
			...settings,
			language: { ...settings.language, ...patch },
		} );
	const updateUpsells = ( patch: Partial< UpsellSettings > ) =>
		setSettings( {
			...settings,
			upsells: { ...settings.upsells, ...patch },
		} );
	const updateRewards = ( patch: Partial< RewardSettings > ) =>
		setSettings( {
			...settings,
			rewards: { ...settings.rewards, ...patch },
		} );
	const updateAddon = ( patch: Partial< SpecialAddonSettings > ) =>
		setSettings( {
			...settings,
			special_addon: { ...settings.special_addon, ...patch },
		} );

	const productSearch = ( query: string, include: number[] = [] ) =>
		searchProducts( config, query, include );
	const couponSearch = ( query: string, include: number[] = [] ) =>
		searchCoupons( config, query, include );

	return (
		<div className="sfcart-admin-app">
			<div className="sfcart-admin-accent-line" aria-hidden="true" />
			<header className="sfcart-admin-header">
				<div className="sfcart-admin-brand">
					<img src={ config.iconUrl } alt="" />
					<div>
						<div className="sfcart-admin-title-row">
							<h1>
								{ __( 'Starfiniti Cart', 'starfiniti-cart' ) }
							</h1>
							<span>v{ config.version }</span>
						</div>
						<p>
							{ __(
								'Side-cart settings owned by your store.',
								'starfiniti-cart'
							) }
						</p>
					</div>
				</div>
				<div className="sfcart-admin-header__actions">
					<Button
						variant="secondary"
						disabled={ busy }
						onClick={ reload }
					>
						{ __( 'Reload', 'starfiniti-cart' ) }
					</Button>
					<Button
						variant="primary"
						isBusy={ busy }
						disabled={ busy }
						onClick={ save }
					>
						{ __( 'Save settings', 'starfiniti-cart' ) }
					</Button>
				</div>
			</header>

			{ notice && (
				<Notice
					status={ notice.status }
					onRemove={ () => setNotice( null ) }
				>
					<div className="sfcart-admin-notice-content">
						<strong>{ notice.message }</strong>
						{ notice.details && notice.details.length > 0 && (
							<ul>
								{ notice.details.map( ( detail ) => (
									<li key={ detail }>{ detail }</li>
								) ) }
							</ul>
						) }
					</div>
				</Notice>
			) }

			<div className="sfcart-admin-shell">
				<nav
					className="sfcart-admin-nav"
					aria-label={ __( 'Settings sections', 'starfiniti-cart' ) }
				>
					{ sections.map( ( section ) => (
						<Button
							key={ section.id }
							aria-current={
								active === section.id ? 'page' : undefined
							}
							className={
								active === section.id ? 'is-active' : ''
							}
							onClick={ () => setActive( section.id ) }
						>
							<span
								className={ `dashicons ${ section.icon }` }
								aria-hidden="true"
							/>
							{ section.label }
						</Button>
					) ) }
				</nav>

				<main className="sfcart-admin-content">
					<section
						className="sfcart-settings-section"
						aria-labelledby={ `sfcart-section-${ active }` }
					>
						<h2 id={ `sfcart-section-${ active }` }>
							{
								sections.find(
									( section ) => section.id === active
								)?.label
							}
						</h2>

						{ active === 'cart' && (
							<>
								<Card>
									<CardBody>
										<h3>
											{ __(
												'Drawer behavior',
												'starfiniti-cart'
											) }
										</h3>
										<p>
											{ __(
												'How and where the side cart opens.',
												'starfiniti-cart'
											) }
										</p>
										<div className="sfcart-drawer-grid">
											<SelectControl
												__next40pxDefaultSize
												__nextHasNoMarginBottom
												label={ __(
													'Drawer position',
													'starfiniti-cart'
												) }
												value={ settings.cart.position }
												options={ [
													{
														label: __(
															'Right',
															'starfiniti-cart'
														),
														value: 'right',
													},
													{
														label: __(
															'Left',
															'starfiniti-cart'
														),
														value: 'left',
													},
												] }
												onChange={ ( value ) =>
													updateCart( {
														position:
															value as CartSettings[ 'position' ],
													} )
												}
											/>
											<RangeControl
												__next40pxDefaultSize
												__nextHasNoMarginBottom
												label={ __(
													'Drawer width (px)',
													'starfiniti-cart'
												) }
												min={ 320 }
												max={ 640 }
												value={ settings.cart.width }
												onChange={ ( value ) =>
													updateCart( {
														width: value ?? 440,
													} )
												}
											/>
										</div>
										<ToggleControl
											__nextHasNoMarginBottom
											label={ __(
												'Open automatically after add to cart',
												'starfiniti-cart'
											) }
											checked={ settings.cart.auto_open }
											onChange={ ( value ) =>
												updateCart( {
													auto_open: value,
												} )
											}
										/>
										<ToggleControl
											__nextHasNoMarginBottom
											label={ __(
												'Show Continue shopping link',
												'starfiniti-cart'
											) }
											help={ __(
												'Shows a working link in the drawer footer and empty-cart state.',
												'starfiniti-cart'
											) }
											checked={
												settings.cart
													.show_continue_shopping
											}
											onChange={ ( value ) =>
												updateCart( {
													show_continue_shopping:
														value,
												} )
											}
										/>
										<ToggleControl
											__nextHasNoMarginBottom
											label={ __(
												'Allow coupons in the drawer',
												'starfiniti-cart'
											) }
											checked={ settings.cart.coupons }
											onChange={ ( value ) =>
												updateCart( { coupons: value } )
											}
										/>
										<ToggleControl
											__nextHasNoMarginBottom
											label={ __(
												'Show shipping total',
												'starfiniti-cart'
											) }
											checked={
												settings.cart.show_shipping
											}
											onChange={ ( value ) =>
												updateCart( {
													show_shipping: value,
												} )
											}
										/>
										<ToggleControl
											__nextHasNoMarginBottom
											label={ __(
												'Show tax total',
												'starfiniti-cart'
											) }
											checked={ settings.cart.show_tax }
											onChange={ ( value ) =>
												updateCart( {
													show_tax: value,
												} )
											}
										/>
										<ToggleControl
											__nextHasNoMarginBottom
											label={ __(
												'Show View cart link',
												'starfiniti-cart'
											) }
											help={ __(
												'Shows the normal WooCommerce cart link beside Continue shopping at the bottom.',
												'starfiniti-cart'
											) }
											checked={
												settings.cart.show_cart_link
											}
											onChange={ ( value ) =>
												updateCart( {
													show_cart_link: value,
												} )
											}
										/>
									</CardBody>
								</Card>
								<Card>
									<CardBody>
										<h3>
											{ __(
												'Language overrides',
												'starfiniti-cart'
											) }
										</h3>
										<p>
											{ __(
												'Leave fields blank to use normal WordPress translations. The two footer links use their visibility toggles above; only Calculation note stays hidden when blank.',
												'starfiniti-cart'
											) }
										</p>
										<div className="sfcart-field-grid">
											{ languageFields.map( ( field ) => (
												<TextControl
													key={ field.key }
													__next40pxDefaultSize
													__nextHasNoMarginBottom
													label={ field.label }
													placeholder={
														field.placeholder
													}
													value={
														settings.language[
															field.key
														]
													}
													onChange={ ( value ) =>
														updateLanguage( {
															[ field.key ]:
																value,
														} )
													}
												/>
											) ) }
										</div>
									</CardBody>
								</Card>
							</>
						) }

						{ active === 'design' && (
							<>
								<Card>
									<CardBody>
										<TriggerSettings
											cart={ settings.cart }
											design={ settings.design }
											onCartChange={ updateCart }
											onChange={ updateDesign }
										/>
									</CardBody>
								</Card>
								<Card>
									<CardBody>
										<h3>
											{ __(
												'Colors and shape',
												'starfiniti-cart'
											) }
										</h3>
										<div className="sfcart-field-grid sfcart-color-grid">
											{ colorFields.map( ( field ) => (
												<ColorField
													key={ field.key }
													label={ field.label }
													value={
														settings.design[
															field.key
														]
													}
													enableAlpha={
														field.enableAlpha
													}
													onChange={ ( value ) =>
														updateDesign( {
															[ field.key ]:
																value,
														} )
													}
												/>
											) ) }
										</div>
										<RangeControl
											__next40pxDefaultSize
											__nextHasNoMarginBottom
											label={ __(
												'Corner radius (px)',
												'starfiniti-cart'
											) }
											min={ 0 }
											max={ 32 }
											value={
												settings.design.border_radius
											}
											onChange={ ( value ) =>
												updateDesign( {
													border_radius: value ?? 0,
												} )
											}
										/>
										<RangeControl
											__next40pxDefaultSize
											__nextHasNoMarginBottom
											label={ __(
												'Overlay opacity (%)',
												'starfiniti-cart'
											) }
											min={ 0 }
											max={ 90 }
											value={
												settings.design.overlay_opacity
											}
											onChange={ ( value ) =>
												updateDesign( {
													overlay_opacity:
														value ?? 58,
												} )
											}
										/>
									</CardBody>
								</Card>
								<Card>
									<CardBody>
										<MediaPicker
											label={ __(
												'Empty-cart image',
												'starfiniti-cart'
											) }
											id={
												settings.design.empty_image_id
											}
											url={
												settings.design.empty_image_url
											}
											onChange={ ( media ) =>
												updateDesign( {
													empty_image_id: media.id,
													empty_image_url: media.url,
												} )
											}
										/>
									</CardBody>
								</Card>
							</>
						) }

						{ active === 'upsells' && (
							<>
								<Card>
									<CardBody>
										<ToggleControl
											__nextHasNoMarginBottom
											label={ __(
												'Enable cart recommendations',
												'starfiniti-cart'
											) }
											checked={ settings.upsells.enabled }
											onChange={ ( value ) =>
												updateUpsells( {
													enabled: value,
												} )
											}
										/>
										<TextControl
											__next40pxDefaultSize
											__nextHasNoMarginBottom
											label={ __(
												'Recommendation heading',
												'starfiniti-cart'
											) }
											value={ settings.upsells.heading }
											onChange={ ( heading ) =>
												updateUpsells( { heading } )
											}
										/>
										<fieldset className="sfcart-choice-fieldset">
											<legend>
												{ __(
													'Position in cart',
													'starfiniti-cart'
												) }
											</legend>
											<p>
												{ __(
													'Choose the conversion moment independently from the visual layout.',
													'starfiniti-cart'
												) }
											</p>
											<div className="sfcart-choice-grid sfcart-choice-grid--four">
												{ upsellPlacements.map(
													( option ) => (
														<label
															htmlFor={ `sfcart-upsell-placement-${ option.value }` }
															className={
																settings.upsells
																	.placement ===
																option.value
																	? 'is-selected'
																	: undefined
															}
															key={ option.value }
														>
															<input
																id={ `sfcart-upsell-placement-${ option.value }` }
																type="radio"
																name="sfcart-upsell-placement"
																value={
																	option.value
																}
																checked={
																	settings
																		.upsells
																		.placement ===
																	option.value
																}
																onChange={ () =>
																	updateUpsells(
																		{
																			placement:
																				option.value,
																		}
																	)
																}
															/>
															<span>
																<strong>
																	{
																		option.label
																	}
																</strong>
																{ option.recommended && (
																	<em>
																		{ __(
																			'Recommended',
																			'starfiniti-cart'
																		) }
																	</em>
																) }
															</span>
															<small>
																{
																	option.description
																}
															</small>
														</label>
													)
												) }
											</div>
										</fieldset>
										<fieldset className="sfcart-choice-fieldset">
											<legend>
												{ __(
													'Product layout',
													'starfiniti-cart'
												) }
											</legend>
											<div className="sfcart-choice-grid sfcart-choice-grid--four">
												{ upsellLayouts.map(
													( option ) => (
														<label
															htmlFor={ `sfcart-upsell-layout-${ option.value }` }
															className={
																settings.upsells
																	.layout ===
																option.value
																	? 'is-selected'
																	: undefined
															}
															key={ option.value }
														>
															<input
																id={ `sfcart-upsell-layout-${ option.value }` }
																type="radio"
																name="sfcart-upsell-layout"
																value={
																	option.value
																}
																checked={
																	settings
																		.upsells
																		.layout ===
																	option.value
																}
																onChange={ () =>
																	updateUpsells(
																		{
																			layout: option.value,
																		}
																	)
																}
															/>
															<span>
																<strong>
																	{
																		option.label
																	}
																</strong>
															</span>
															<small>
																{
																	option.description
																}
															</small>
														</label>
													)
												) }
											</div>
										</fieldset>
										<SelectControl
											__next40pxDefaultSize
											__nextHasNoMarginBottom
											label={ __(
												'Product ordering',
												'starfiniti-cart'
											) }
											value={ settings.upsells.ordering }
											options={ [
												{
													label: __(
														'Relationship relevance',
														'starfiniti-cart'
													),
													value: 'relevance',
												},
												{
													label: __(
														'Product name',
														'starfiniti-cart'
													),
													value: 'name',
												},
												{
													label: __(
														'Price: low to high',
														'starfiniti-cart'
													),
													value: 'price_asc',
												},
												{
													label: __(
														'Price: high to low',
														'starfiniti-cart'
													),
													value: 'price_desc',
												},
											] }
											onChange={ ( ordering ) =>
												updateUpsells( {
													ordering:
														ordering as UpsellSettings[ 'ordering' ],
												} )
											}
										/>
										<SelectControl
											__next40pxDefaultSize
											__nextHasNoMarginBottom
											label={ __(
												'Recommendation source',
												'starfiniti-cart'
											) }
											value={ settings.upsells.mode }
											options={ [
												{
													label: __(
														'Upsells and cross-sells',
														'starfiniti-cart'
													),
													value: 'both',
												},
												{
													label: __(
														'Upsells only',
														'starfiniti-cart'
													),
													value: 'upsells',
												},
												{
													label: __(
														'Cross-sells only',
														'starfiniti-cart'
													),
													value: 'cross_sells',
												},
											] }
											onChange={ ( value ) =>
												updateUpsells( {
													mode: value as UpsellSettings[ 'mode' ],
												} )
											}
										/>
										<RangeControl
											__next40pxDefaultSize
											__nextHasNoMarginBottom
											label={ __(
												'Display limit',
												'starfiniti-cart'
											) }
											min={ 1 }
											max={ 12 }
											value={
												settings.upsells.display_limit
											}
											onChange={ ( value ) =>
												updateUpsells( {
													display_limit: value ?? 3,
												} )
											}
										/>
										<ToggleControl
											__nextHasNoMarginBottom
											label={ __(
												'Always show default recommendations',
												'starfiniti-cart'
											) }
											checked={
												settings.upsells
													.always_show_defaults
											}
											onChange={ ( value ) =>
												updateUpsells( {
													always_show_defaults: value,
												} )
											}
										/>
										<EntityPicker< ProductSummary >
											label={ __(
												'Search default products',
												'starfiniti-cart'
											) }
											selected={
												settings.upsells
													.default_product_ids
											}
											search={ productSearch }
											display={ ( product ) =>
												product.name
											}
											meta={ ( product ) =>
												[ product.sku, product.price ]
													.filter( Boolean )
													.join( ' · ' )
											}
											onChange={ ( identifiers ) =>
												updateUpsells( {
													default_product_ids:
														identifiers,
												} )
											}
										/>
										<EntityPicker< ProductSummary >
											label={ __(
												'Search excluded products',
												'starfiniti-cart'
											) }
											selected={
												settings.upsells
													.excluded_product_ids
											}
											search={ productSearch }
											display={ ( product ) =>
												product.name
											}
											meta={ ( product ) =>
												[ product.sku, product.price ]
													.filter( Boolean )
													.join( ' · ' )
											}
											onChange={ ( identifiers ) =>
												updateUpsells( {
													excluded_product_ids:
														identifiers,
												} )
											}
											limit={ 50 }
										/>
									</CardBody>
								</Card>
								<Card>
									<CardBody>
										<RelationshipManager
											config={ config }
											search={ productSearch }
										/>
									</CardBody>
								</Card>
							</>
						) }

						{ active === 'rewards' && (
							<Card>
								<CardBody>
									<ToggleControl
										__nextHasNoMarginBottom
										label={ __(
											'Enable cart rewards',
											'starfiniti-cart'
										) }
										checked={ settings.rewards.enabled }
										onChange={ ( value ) =>
											updateRewards( { enabled: value } )
										}
									/>
									<ToggleControl
										__nextHasNoMarginBottom
										label={ __(
											'Allow customers to remove free gifts',
											'starfiniti-cart'
										) }
										checked={
											settings.rewards.allow_gift_removal
										}
										onChange={ ( value ) =>
											updateRewards( {
												allow_gift_removal: value,
											} )
										}
									/>
									<SelectControl
										__next40pxDefaultSize
										__nextHasNoMarginBottom
										label={ __(
											'Calculation amount',
											'starfiniti-cart'
										) }
										value={
											settings.rewards.calculation_mode
										}
										options={ [
											{
												label: __(
													'Subtotal',
													'starfiniti-cart'
												),
												value: 'subtotal',
											},
											{
												label: __(
													'Total',
													'starfiniti-cart'
												),
												value: 'total',
											},
										] }
										onChange={ ( value ) =>
											updateRewards( {
												calculation_mode:
													value as RewardSettings[ 'calculation_mode' ],
											} )
										}
									/>
									<SelectControl
										__next40pxDefaultSize
										__nextHasNoMarginBottom
										label={ __(
											'Progress design',
											'starfiniti-cart'
										) }
										value={
											settings.rewards.progress_design
										}
										options={ [
											{
												label: __(
													'Bar',
													'starfiniti-cart'
												),
												value: 'bar',
											},
											{
												label: __(
													'Steps',
													'starfiniti-cart'
												),
												value: 'steps',
											},
											{
												label: __(
													'Compact',
													'starfiniti-cart'
												),
												value: 'compact',
											},
										] }
										onChange={ ( value ) =>
											updateRewards( {
												progress_design:
													value as RewardSettings[ 'progress_design' ],
											} )
										}
									/>
									<TextControl
										__next40pxDefaultSize
										__nextHasNoMarginBottom
										label={ __(
											'Completion message',
											'starfiniti-cart'
										) }
										value={
											settings.rewards.complete_message
										}
										onChange={ ( completeMessage ) =>
											updateRewards( {
												complete_message:
													completeMessage,
											} )
										}
									/>
									<RewardMilestones
										milestones={
											settings.rewards.milestones
										}
										searchCoupons={ couponSearch }
										searchProducts={ productSearch }
										onChange={ ( milestones ) =>
											updateRewards( { milestones } )
										}
									/>
								</CardBody>
							</Card>
						) }

						{ active === 'special-addon' && (
							<Card>
								<CardBody>
									<ToggleControl
										__nextHasNoMarginBottom
										label={ __(
											'Enable special add-on',
											'starfiniti-cart'
										) }
										checked={
											settings.special_addon.enabled
										}
										onChange={ ( value ) =>
											updateAddon( { enabled: value } )
										}
									/>
									<ToggleControl
										__nextHasNoMarginBottom
										label={ __(
											'Preselect the add-on',
											'starfiniti-cart'
										) }
										checked={
											settings.special_addon.preselected
										}
										onChange={ ( value ) =>
											updateAddon( {
												preselected: value,
											} )
										}
									/>
									<SelectControl
										__next40pxDefaultSize
										__nextHasNoMarginBottom
										label={ __(
											'Selection control',
											'starfiniti-cart'
										) }
										value={
											settings.special_addon
												.selection_type
										}
										options={ [
											{
												label: __(
													'Checkbox',
													'starfiniti-cart'
												),
												value: 'checkbox',
											},
											{
												label: __(
													'Toggle',
													'starfiniti-cart'
												),
												value: 'toggle',
											},
										] }
										onChange={ ( selectionType ) =>
											updateAddon( {
												selection_type:
													selectionType as SpecialAddonSettings[ 'selection_type' ],
											} )
										}
									/>
									<TextControl
										__next40pxDefaultSize
										__nextHasNoMarginBottom
										label={ __(
											'Offer heading',
											'starfiniti-cart'
										) }
										value={ settings.special_addon.heading }
										onChange={ ( heading ) =>
											updateAddon( { heading } )
										}
									/>
									<TextControl
										__next40pxDefaultSize
										__nextHasNoMarginBottom
										label={ __(
											'Offer description',
											'starfiniti-cart'
										) }
										value={
											settings.special_addon.description
										}
										onChange={ ( description ) =>
											updateAddon( { description } )
										}
									/>
									<EntityPicker< ProductSummary >
										label={ __(
											'Search add-on product',
											'starfiniti-cart'
										) }
										selected={
											settings.special_addon.product_id
												? [
														settings.special_addon
															.product_id,
												  ]
												: []
										}
										search={ productSearch }
										display={ ( product ) => product.name }
										meta={ ( product ) =>
											[ product.type, product.price ]
												.filter( Boolean )
												.join( ' · ' )
										}
										single
										onChange={ ( identifiers ) =>
											updateAddon( {
												product_id:
													identifiers[ 0 ] ?? 0,
											} )
										}
									/>
									<p
										className={
											settings.special_addon.enabled &&
											settings.special_addon
												.product_id === 0
												? 'sfcart-field-error'
												: 'sfcart-field-help'
										}
									>
										{ settings.special_addon.enabled &&
										settings.special_addon.product_id === 0
											? __(
													'Choose an add-on product before saving. The live preview uses a sample offer until then.',
													'starfiniti-cart'
											  )
											: __(
													'Enable the add-on to publish it. While this section is open, the preview remains visible for styling.',
													'starfiniti-cart'
											  ) }
									</p>
									<ToggleControl
										__nextHasNoMarginBottom
										label={ __(
											'Show offer image',
											'starfiniti-cart'
										) }
										checked={
											settings.special_addon.image_enabled
										}
										onChange={ ( value ) =>
											updateAddon( {
												image_enabled: value,
											} )
										}
									/>
									{ settings.special_addon.image_enabled && (
										<>
											<SelectControl
												__next40pxDefaultSize
												__nextHasNoMarginBottom
												label={ __(
													'Image source',
													'starfiniti-cart'
												) }
												value={
													settings.special_addon
														.image_source
												}
												options={ [
													{
														label: __(
															'Product image',
															'starfiniti-cart'
														),
														value: 'product',
													},
													{
														label: __(
															'Custom image',
															'starfiniti-cart'
														),
														value: 'custom',
													},
												] }
												onChange={ ( imageSource ) =>
													updateAddon( {
														image_source:
															imageSource as SpecialAddonSettings[ 'image_source' ],
													} )
												}
											/>
											{ settings.special_addon
												.image_source === 'custom' && (
												<MediaPicker
													label={ __(
														'Custom add-on image',
														'starfiniti-cart'
													) }
													id={
														settings.special_addon
															.image_id
													}
													url={
														settings.special_addon
															.image_url
													}
													onChange={ ( media ) =>
														updateAddon( {
															image_id: media.id,
															image_url:
																media.url,
														} )
													}
												/>
											) }
											<RangeControl
												__next40pxDefaultSize
												__nextHasNoMarginBottom
												label={ __(
													'Image size (px)',
													'starfiniti-cart'
												) }
												min={ 32 }
												max={ 96 }
												value={
													settings.special_addon
														.image_size
												}
												onChange={ ( imageSize ) =>
													updateAddon( {
														image_size:
															imageSize ?? 56,
													} )
												}
											/>
										</>
									) }
									<div className="sfcart-field-grid sfcart-color-grid">
										{ addonColorFields.map( ( field ) => (
											<ColorField
												key={ field.key }
												label={ field.label }
												value={
													settings.special_addon[
														field.key
													]
												}
												enableAlpha={
													field.enableAlpha
												}
												onChange={ ( value ) =>
													updateAddon( {
														[ field.key ]: value,
													} )
												}
											/>
										) ) }
									</div>
								</CardBody>
							</Card>
						) }

						{ active === 'analytics' && (
							<AnalyticsDashboard config={ config } />
						) }

						{ active === 'tools' && (
							<>
								<MigrationTool
									config={ config }
									onSettingsChanged={ reload }
								/>
								<Card>
									<CardBody>
										<h3>
											{ __(
												'Data retention',
												'starfiniti-cart'
											) }
										</h3>
										<ToggleControl
											__nextHasNoMarginBottom
											label={ __(
												'Delete Starfiniti Cart data when the plugin is uninstalled',
												'starfiniti-cart'
											) }
											help={ __(
												'Off by default. Deactivation never deletes data.',
												'starfiniti-cart'
											) }
											checked={
												settings.delete_data_on_uninstall
											}
											onChange={ ( value ) =>
												setSettings( {
													...settings,
													delete_data_on_uninstall:
														value,
												} )
											}
										/>
									</CardBody>
								</Card>
							</>
						) }
					</section>
				</main>

				<Preview
					settings={ settings }
					addonProduct={ addonProduct }
					showAddonConfiguration={ active === 'special-addon' }
				/>
			</div>
		</div>
	);
}
