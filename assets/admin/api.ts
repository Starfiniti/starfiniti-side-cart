import apiFetch from '@wordpress/api-fetch';

import type {
	AdminConfig,
	AnalyticsConversion,
	AnalyticsDay,
	AnalyticsFilters,
	AnalyticsItem,
	AnalyticsOptions,
	AnalyticsOverview,
	CouponSummary,
	MigrationPreview,
	MigrationRunResponse,
	ProductSummary,
	RelationshipsResponse,
	SearchResponse,
	Settings,
	SettingsResponse,
} from './types';

let configuredNonce = '';

export function configureApi( config: AdminConfig ): void {
	if ( config.nonce !== configuredNonce ) {
		apiFetch.use( apiFetch.createNonceMiddleware( config.nonce ) );
		configuredNonce = config.nonce;
	}
}

export async function loadSettings(
	config: AdminConfig
): Promise< SettingsResponse > {
	return apiFetch< SettingsResponse >( { path: config.settingsPath } );
}

export async function saveSettings(
	config: AdminConfig,
	settings: Settings
): Promise< SettingsResponse > {
	return apiFetch< SettingsResponse >( {
		data: { settings },
		method: 'PUT',
		path: config.settingsPath,
	} );
}

export async function searchProducts(
	config: AdminConfig,
	search: string,
	include: number[] = []
): Promise< ProductSummary[] > {
	const query = buildSearchQuery( search, include );
	const response = await apiFetch< SearchResponse< ProductSummary > >( {
		path: `${ config.productsPath }${ query }`,
	} );

	return response.items;
}

export async function searchCoupons(
	config: AdminConfig,
	search: string,
	include: number[] = []
): Promise< CouponSummary[] > {
	const query = buildSearchQuery( search, include );
	const response = await apiFetch< SearchResponse< CouponSummary > >( {
		path: `${ config.couponsPath }${ query }`,
	} );

	return response.items;
}

export async function loadRelationships(
	config: AdminConfig,
	productId: number
): Promise< RelationshipsResponse > {
	return apiFetch< RelationshipsResponse >( {
		path: `${ config.relationshipsPath }/${ productId }`,
	} );
}

export async function saveRelationships(
	config: AdminConfig,
	productId: number,
	upsellIds: number[],
	crossSellIds: number[]
): Promise< RelationshipsResponse > {
	return apiFetch< RelationshipsResponse >( {
		data: {
			cross_sell_ids: crossSellIds,
			upsell_ids: upsellIds,
		},
		method: 'PUT',
		path: `${ config.relationshipsPath }/${ productId }`,
	} );
}

export async function loadAnalytics(
	config: AdminConfig,
	filters: AnalyticsFilters
): Promise< {
	overview: AnalyticsOverview;
	conversions: AnalyticsConversion[];
	items: AnalyticsItem[];
	series: AnalyticsDay[];
	options: AnalyticsOptions;
} > {
	const query = buildAnalyticsQuery( filters );
	const [ overview, conversions, popular, performance, options ] =
		await Promise.all( [
			apiFetch< { overview: AnalyticsOverview } >( {
				path: `${ config.analyticsPath }/overview${ query }`,
			} ),
			apiFetch< { conversions: AnalyticsConversion[] } >( {
				path: `${ config.analyticsPath }/conversions${ query }`,
			} ),
			apiFetch< { items: AnalyticsItem[] } >( {
				path: `${ config.analyticsPath }/popular${ query }`,
			} ),
			apiFetch< { series: AnalyticsDay[] } >( {
				path: `${ config.analyticsPath }/performance${ query }`,
			} ),
			apiFetch< AnalyticsOptions >( {
				path: `${ config.analyticsPath }/filters`,
			} ),
		] );

	return {
		overview: overview.overview,
		conversions: conversions.conversions,
		items: popular.items,
		series: performance.series,
		options,
	};
}

export function analyticsExportUrl(
	config: AdminConfig,
	filters: AnalyticsFilters
): string {
	const root = config.root.replace( /\/$/, '' );
	return `${ root }${ config.analyticsPath }/export${ buildAnalyticsQuery(
		filters
	) }`;
}

export async function loadMigrationPreview(
	config: AdminConfig
): Promise< MigrationPreview > {
	return apiFetch< MigrationPreview >( {
		path: `${ config.migrationPath }/preview`,
	} );
}

export async function runMigration(
	config: AdminConfig
): Promise< MigrationRunResponse > {
	return apiFetch< MigrationRunResponse >( {
		data: { confirmed: true },
		method: 'POST',
		path: `${ config.migrationPath }/run`,
	} );
}

export function errorMessage( error: unknown ): string {
	if (
		typeof error === 'object' &&
		error !== null &&
		'message' in error &&
		typeof error.message === 'string'
	) {
		return error.message;
	}

	return 'The request could not be completed.';
}

export type ApiErrorDetails = {
	message: string;
	fields: Record< string, string >;
};

/**
 * Preserve WordPress REST field-level validation details for actionable UI errors.
 * @param error
 */
export function apiErrorDetails( error: unknown ): ApiErrorDetails {
	const details: ApiErrorDetails = {
		message: errorMessage( error ),
		fields: {},
	};

	if (
		typeof error !== 'object' ||
		error === null ||
		! ( 'data' in error )
	) {
		return details;
	}

	const data = error.data;
	if ( typeof data !== 'object' || data === null || ! ( 'fields' in data ) ) {
		return details;
	}

	const fields = data.fields;
	if (
		typeof fields !== 'object' ||
		fields === null ||
		Array.isArray( fields )
	) {
		return details;
	}

	for ( const [ path, message ] of Object.entries( fields ) ) {
		if ( typeof message === 'string' ) {
			details.fields[ path ] = message;
		}
	}

	return details;
}

export function buildSearchQuery(
	search: string,
	include: number[] = []
): string {
	const parameters = new URLSearchParams();

	if ( search.trim() ) {
		parameters.set( 'search', search.trim() );
	}
	if ( include.length > 0 ) {
		parameters.set( 'include', include.join( ',' ) );
	}

	const query = parameters.toString();

	return query ? `?${ query }` : '';
}

export function buildAnalyticsQuery( filters: AnalyticsFilters ): string {
	const parameters = new URLSearchParams();

	for ( const [ key, value ] of Object.entries( filters ) ) {
		if ( value !== '' && value !== 0 ) {
			parameters.set( key, String( value ) );
		}
	}

	const query = parameters.toString();
	return query ? `?${ query }` : '';
}
