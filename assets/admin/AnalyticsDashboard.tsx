import {
	Button,
	Card,
	CardBody,
	Notice,
	Spinner,
	TextControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';

import { analyticsExportUrl, errorMessage, loadAnalytics } from './api';
import type {
	AdminConfig,
	AnalyticsConversion,
	AnalyticsDay,
	AnalyticsFilters,
	AnalyticsItem,
	AnalyticsOverview,
} from './types';

type Props = {
	config: AdminConfig;
};

const today = new Date().toISOString().slice( 0, 10 );
const monthAgo = new Date( Date.now() - 29 * 24 * 60 * 60 * 1000 )
	.toISOString()
	.slice( 0, 10 );

const defaultFilters: AnalyticsFilters = {
	from: monthAgo,
	to: today,
	type: '',
	product_id: 0,
	coupon: '',
	currency: '',
};

export function AnalyticsDashboard( { config }: Props ) {
	const [ filters, setFilters ] =
		useState< AnalyticsFilters >( defaultFilters );
	const [ overview, setOverview ] = useState< AnalyticsOverview | null >(
		null
	);
	const [ activity, setActivity ] = useState< AnalyticsConversion[] >( [] );
	const [ items, setItems ] = useState< AnalyticsItem[] >( [] );
	const [ series, setSeries ] = useState< AnalyticsDay[] >( [] );
	const [ busy, setBusy ] = useState( true );
	const [ notice, setNotice ] = useState< string | null >( null );

	const reload = useCallback( async () => {
		setBusy( true );
		setNotice( null );
		try {
			const response = await loadAnalytics( config, filters );
			setOverview( response.overview );
			setActivity( response.conversions );
			setItems( response.items );
			setSeries( response.series );
		} catch ( error ) {
			setNotice( errorMessage( error ) );
		} finally {
			setBusy( false );
		}
	}, [ config, filters ] );

	useEffect( () => {
		void reload();
	}, [ reload ] );

	const maxActivity = useMemo(
		() =>
			Math.max(
				1,
				...series.flatMap( ( day ) => [
					day.opens,
					day.interactions,
					day.checkout_clicks,
				] )
			),
		[ series ]
	);

	const exportCsv = async () => {
		const response = await window.fetch(
			analyticsExportUrl( config, filters ),
			{
				headers: { 'X-WP-Nonce': config.nonce },
			}
		);
		if ( ! response.ok ) {
			setNotice(
				__(
					'The CSV export could not be generated.',
					'starfiniti-cart'
				)
			);
			return;
		}
		const blob = await response.blob();
		const url = window.URL.createObjectURL( blob );
		const link = document.createElement( 'a' );
		link.href = url;
		link.download = 'starfiniti-cart-analytics.csv';
		link.click();
		window.URL.revokeObjectURL( url );
	};

	return (
		<div className="sfcart-analytics">
			<Card>
				<CardBody>
					<div className="sfcart-analytics__toolbar">
						<TextControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'From', 'starfiniti-cart' ) }
							type="date"
							value={ filters.from }
							onChange={ ( from ) =>
								setFilters( { ...filters, from } )
							}
						/>
						<TextControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'To', 'starfiniti-cart' ) }
							type="date"
							value={ filters.to }
							onChange={ ( to ) =>
								setFilters( { ...filters, to } )
							}
						/>
						<div className="sfcart-analytics__actions">
							<Button
								variant="secondary"
								isBusy={ busy }
								onClick={ reload }
							>
								{ __( 'Refresh', 'starfiniti-cart' ) }
							</Button>
							<Button variant="primary" onClick={ exportCsv }>
								{ __( 'Export CSV', 'starfiniti-cart' ) }
							</Button>
						</div>
					</div>
					{ notice && (
						<Notice
							status="error"
							onRemove={ () => setNotice( null ) }
						>
							{ notice }
						</Notice>
					) }
				</CardBody>
			</Card>

			{ busy && ! overview ? (
				<Card>
					<CardBody>
						<Spinner />
					</CardBody>
				</Card>
			) : (
				<>
					<div className="sfcart-analytics__metrics sfcart-analytics__metrics--primary">
						<Metric
							label={ __( 'Cart opens', 'starfiniti-cart' ) }
							value={ overview?.cart_opens ?? 0 }
						/>
						<Metric
							label={ __( 'Checkout clicks', 'starfiniti-cart' ) }
							value={ overview?.checkout_clicks ?? 0 }
						/>
						<Metric
							label={ __( 'Cart conversion', 'starfiniti-cart' ) }
							value={ percent(
								overview?.cart_conversion_rate ?? 0
							) }
						/>
						<Metric
							label={ __( 'Abandoned carts', 'starfiniti-cart' ) }
							value={ overview?.abandoned_carts ?? 0 }
							help={ __(
								'Non-empty cart sessions without a paid order in this date range.',
								'starfiniti-cart'
							) }
						/>
						<Metric
							label={ __( 'Added revenue', 'starfiniti-cart' ) }
							value={ money( overview?.net_revenue ?? 0 ) }
							help={ __(
								'Net revenue attributed to cart offers.',
								'starfiniti-cart'
							) }
						/>
					</div>

					<Card>
						<CardBody>
							<h3>{ __( 'Cart funnel', 'starfiniti-cart' ) }</h3>
							<div className="sfcart-cart-funnel">
								<FunnelStage
									label={ __(
										'Cart opened',
										'starfiniti-cart'
									) }
									value={ overview?.cart_sessions ?? 0 }
									rate={ 100 }
								/>
								<FunnelStage
									label={ __(
										'Cart interacted',
										'starfiniti-cart'
									) }
									value={
										overview?.interaction_sessions ?? 0
									}
									rate={ stageRate(
										overview?.interaction_sessions ?? 0,
										overview?.cart_sessions ?? 0
									) }
								/>
								<FunnelStage
									label={ __(
										'Checkout clicked',
										'starfiniti-cart'
									) }
									value={ overview?.checkout_sessions ?? 0 }
									rate={ overview?.checkout_rate ?? 0 }
								/>
								<FunnelStage
									label={ __(
										'Order paid',
										'starfiniti-cart'
									) }
									value={ overview?.converted_sessions ?? 0 }
									rate={ overview?.cart_conversion_rate ?? 0 }
								/>
							</div>
						</CardBody>
					</Card>

					<Card>
						<CardBody>
							<div className="sfcart-analytics__heading-row">
								<h3>
									{ __(
										'Daily cart activity',
										'starfiniti-cart'
									) }
								</h3>
								<div className="sfcart-analytics__legend">
									<span className="is-opens">
										{ __( 'Opens', 'starfiniti-cart' ) }
									</span>
									<span className="is-interactions">
										{ __(
											'Interactions',
											'starfiniti-cart'
										) }
									</span>
									<span className="is-checkout">
										{ __( 'Checkout', 'starfiniti-cart' ) }
									</span>
								</div>
							</div>
							<div
								className="sfcart-analytics-chart"
								role="img"
								aria-label={ __(
									'Daily side-cart activity chart',
									'starfiniti-cart'
								) }
							>
								{ series.length === 0 && (
									<p>
										{ __(
											'No cart activity has been recorded for this range yet.',
											'starfiniti-cart'
										) }
									</p>
								) }
								{ series.map( ( day ) => (
									<div key={ day.date }>
										<span>{ day.date.slice( 5 ) }</span>
										<div className="sfcart-analytics-chart__bars">
											<ActivityBar
												value={ day.opens }
												maximum={ maxActivity }
												className="is-opens"
												label={ __(
													'Cart opens',
													'starfiniti-cart'
												) }
											/>
											<ActivityBar
												value={ day.interactions }
												maximum={ maxActivity }
												className="is-interactions"
												label={ __(
													'Cart interactions',
													'starfiniti-cart'
												) }
											/>
											<ActivityBar
												value={ day.checkout_clicks }
												maximum={ maxActivity }
												className="is-checkout"
												label={ __(
													'Checkout clicks',
													'starfiniti-cart'
												) }
											/>
										</div>
										<small>{ `${ day.opens } / ${ day.checkout_clicks }` }</small>
									</div>
								) ) }
							</div>
						</CardBody>
					</Card>

					<div className="sfcart-analytics__split">
						<Card>
							<CardBody>
								<h3>
									{ __(
										'Cart interactions',
										'starfiniti-cart'
									) }
								</h3>
								<AnalyticsTable
									rows={ overview?.by_action ?? [] }
									columns={ [
										[
											'action',
											__( 'Action', 'starfiniti-cart' ),
										],
										[
											'events',
											__( 'Events', 'starfiniti-cart' ),
										],
										[
											'sessions',
											__( 'Sessions', 'starfiniti-cart' ),
										],
									] }
								/>
							</CardBody>
						</Card>
						<Card>
							<CardBody>
								<h3>
									{ __(
										'Cart offer performance',
										'starfiniti-cart'
									) }
								</h3>
								<AnalyticsTable
									rows={ items }
									columns={ [
										[
											'name',
											__( 'Product', 'starfiniti-cart' ),
										],
										[
											'type',
											__( 'Offer', 'starfiniti-cart' ),
										],
										[
											'events',
											__( 'Events', 'starfiniti-cart' ),
										],
										[
											'net',
											__( 'Net', 'starfiniti-cart' ),
										],
									] }
								/>
							</CardBody>
						</Card>
					</div>

					<Card>
						<CardBody>
							<h3>
								{ __(
									'Recent cart activity',
									'starfiniti-cart'
								) }
							</h3>
							<AnalyticsTable
								rows={ activity }
								columns={ [
									[ 'date', __( 'Date', 'starfiniti-cart' ) ],
									[
										'type',
										__( 'Event', 'starfiniti-cart' ),
									],
									[
										'status',
										__( 'Action', 'starfiniti-cart' ),
									],
									[
										'order_id',
										__( 'Order', 'starfiniti-cart' ),
									],
									[
										'revenue',
										__(
											'Added revenue',
											'starfiniti-cart'
										),
									],
								] }
							/>
						</CardBody>
					</Card>

					<div className="sfcart-analytics__outcomes">
						<h3>{ __( 'Order outcomes', 'starfiniti-cart' ) }</h3>
						<p>
							{ __(
								'Secondary financial results attributed to the side cart.',
								'starfiniti-cart'
							) }
						</p>
						<div className="sfcart-analytics__metrics">
							<Metric
								label={ __( 'Paid orders', 'starfiniti-cart' ) }
								value={ overview?.orders ?? 0 }
							/>
							<Metric
								label={ __(
									'Attributed revenue',
									'starfiniti-cart'
								) }
								value={ money(
									overview?.attributed_revenue ?? 0
								) }
							/>
							<Metric
								label={ __( 'Refunded', 'starfiniti-cart' ) }
								value={ money( overview?.refunded ?? 0 ) }
							/>
							<Metric
								label={ __( 'Net revenue', 'starfiniti-cart' ) }
								value={ money( overview?.net_revenue ?? 0 ) }
							/>
						</div>
					</div>
				</>
			) }
		</div>
	);
}

function Metric( {
	label,
	value,
	help,
}: {
	label: string;
	value: string | number;
	help?: string;
} ) {
	return (
		<Card>
			<CardBody>
				<span>{ label }</span>
				<strong>{ value }</strong>
				{ help && <small>{ help }</small> }
			</CardBody>
		</Card>
	);
}

function FunnelStage( {
	label,
	value,
	rate,
}: {
	label: string;
	value: number;
	rate: number;
} ) {
	return (
		<div className="sfcart-cart-funnel__stage">
			<div>
				<strong className="sfcart-cart-funnel__value">{ value }</strong>
				<span>{ label }</span>
				<small className="sfcart-cart-funnel__rate">
					{ percent( rate ) }
				</small>
			</div>
			<i
				style={ {
					width: `${ Math.max( 2, Math.min( 100, rate ) ) }%`,
				} }
			/>
		</div>
	);
}

function ActivityBar( {
	value,
	maximum,
	className,
	label,
}: {
	value: number;
	maximum: number;
	className: string;
	label: string;
} ) {
	return (
		<i
			className={ className }
			style={ {
				height: `${
					value > 0 ? Math.max( 4, ( value / maximum ) * 100 ) : 0
				}%`,
			} }
			title={ `${ label }: ${ value }` }
		/>
	);
}

function AnalyticsTable< T extends Record< string, unknown > >( {
	rows,
	columns,
}: {
	rows: T[];
	columns: Array< [ keyof T, string ] >;
} ) {
	return (
		<div className="sfcart-analytics-table">
			<table>
				<thead>
					<tr>
						{ columns.map( ( [ key, label ] ) => (
							<th key={ String( key ) }>{ label }</th>
						) ) }
					</tr>
				</thead>
				<tbody>
					{ rows.length === 0 && (
						<tr>
							<td colSpan={ columns.length }>
								{ __(
									'No activity matches the current filters.',
									'starfiniti-cart'
								) }
							</td>
						</tr>
					) }
					{ rows.map( ( row, index ) => (
						<tr key={ index }>
							{ columns.map( ( [ key ] ) => (
								<td key={ String( key ) }>
									{ formatCell( key as string, row[ key ] ) }
								</td>
							) ) }
						</tr>
					) ) }
				</tbody>
			</table>
		</div>
	);
}

function formatCell( key: string, value: unknown ): string | number {
	if ( key === 'type' && typeof value === 'string' ) {
		return labelForType( value );
	}
	if ( [ 'action', 'status' ].includes( key ) && typeof value === 'string' ) {
		return labelForAction( value );
	}
	if (
		[ 'revenue', 'refunded', 'net', 'total', 'order_total' ].includes(
			key
		) &&
		typeof value === 'number'
	) {
		return money( value );
	}
	return typeof value === 'number' || typeof value === 'string' ? value : '';
}

function money( value: number ): string {
	return value.toLocaleString( undefined, {
		maximumFractionDigits: 2,
		minimumFractionDigits: 2,
	} );
}

function percent( value: number ): string {
	return `${ value.toLocaleString( undefined, {
		maximumFractionDigits: 1,
	} ) }%`;
}

function stageRate( value: number, total: number ): number {
	return total > 0 ? Math.min( 100, ( value / total ) * 100 ) : 0;
}

function labelForAction( value: string ): string {
	const labels: Record< string, string > = {
		addon_remove: __( 'Add-on removed', 'starfiniti-cart' ),
		addon_select: __( 'Add-on selected', 'starfiniti-cart' ),
		addon_variation: __( 'Add-on variation changed', 'starfiniti-cart' ),
		coupon_apply: __( 'Coupon applied', 'starfiniti-cart' ),
		coupon_remove: __( 'Coupon removed', 'starfiniti-cart' ),
		empty: __( 'Empty cart', 'starfiniti-cart' ),
		item_remove: __( 'Item removed', 'starfiniti-cart' ),
		non_empty: __( 'Cart with items', 'starfiniti-cart' ),
		quantity_update: __( 'Quantity updated', 'starfiniti-cart' ),
		recommendation_add: __( 'Recommendation added', 'starfiniti-cart' ),
	};
	return labels[ value ] ?? value.replaceAll( '_', ' ' );
}

function labelForType( value: string ): string {
	const labels: Record< string, string > = {
		accepted: __( 'Recommendation accepted', 'starfiniti-cart' ),
		cart_close: __( 'Cart closed', 'starfiniti-cart' ),
		cart_interaction: __( 'Cart interaction', 'starfiniti-cart' ),
		cart_open: __( 'Cart opened', 'starfiniti-cart' ),
		checkout_click: __( 'Checkout clicked', 'starfiniti-cart' ),
		impression: __( 'Recommendation viewed', 'starfiniti-cart' ),
		order: __( 'Order paid', 'starfiniti-cart' ),
		product: __( 'Product', 'starfiniti-cart' ),
		recommendation: __( 'Recommendation', 'starfiniti-cart' ),
		refund: __( 'Refund', 'starfiniti-cart' ),
		reward: __( 'Reward', 'starfiniti-cart' ),
		reward_gift: __( 'Reward gift', 'starfiniti-cart' ),
		special_addon: __( 'Special add-on', 'starfiniti-cart' ),
	};
	return labels[ value ] ?? value;
}
