import type { CartState, SfcartConfig } from './types';

type ApiEnvelope = {
	success: boolean;
	data: CartState;
};

export type ApiResult = {
	success: boolean;
	state: CartState;
	status: number;
};

export class CartApi {
	private nonce: string;

	public constructor( private readonly config: SfcartConfig ) {
		this.nonce = config.nonce;
	}

	public state(): Promise< ApiResult > {
		return this.request( this.config.endpoints.state );
	}

	public updateItem( key: string, quantity: number ): Promise< ApiResult > {
		return this.request( this.config.endpoints.updateItem, {
			cart_key: key,
			quantity: String( quantity ),
		} );
	}

	public removeItem( key: string ): Promise< ApiResult > {
		return this.request( this.config.endpoints.removeItem, {
			cart_key: key,
		} );
	}

	public applyCoupon( code: string ): Promise< ApiResult > {
		return this.request( this.config.endpoints.applyCoupon, {
			coupon_code: code,
		} );
	}

	public removeCoupon( code: string ): Promise< ApiResult > {
		return this.request( this.config.endpoints.removeCoupon, {
			coupon_code: code,
		} );
	}

	public addRecommendation(
		productId: number,
		variationId = 0,
		attributes: Record< string, string > = {}
	): Promise< ApiResult > {
		return this.request( this.config.endpoints.addRecommendation, {
			attributes: JSON.stringify( attributes ),
			product_id: String( productId ),
			variation_id: String( variationId ),
		} );
	}

	public trackRecommendations( productIds: number[] ): Promise< ApiResult > {
		return this.request( this.config.endpoints.trackRecommendations, {
			product_ids: productIds.join( ',' ),
		} );
	}

	public trackEvent(
		eventType:
			| 'cart_open'
			| 'cart_close'
			| 'cart_interaction'
			| 'checkout_click',
		eventAction: string,
		outcome = 'success'
	): void {
		const body = new URLSearchParams( {
			event_action: eventAction,
			event_type: eventType,
			nonce: this.nonce,
			outcome,
		} );

		void fetch( this.config.endpoints.trackEvent, {
			body,
			credentials: 'same-origin',
			headers: {
				'Content-Type':
					'application/x-www-form-urlencoded; charset=UTF-8',
			},
			keepalive: true,
			method: 'POST',
		} ).catch( () => undefined );
	}

	public setSpecialAddon(
		selected: boolean,
		variationId = 0,
		attributes: Record< string, string > = {}
	): Promise< ApiResult > {
		return this.request( this.config.endpoints.setSpecialAddon, {
			attributes: JSON.stringify( attributes ),
			selected: selected ? '1' : '0',
			variation_id: String( variationId ),
		} );
	}

	private async request(
		endpoint: string,
		payload: Record< string, string > = {},
		allowNonceRetry = true
	): Promise< ApiResult > {
		const body = new URLSearchParams( {
			...payload,
			nonce: this.nonce,
		} );
		const response = await fetch( endpoint, {
			body,
			credentials: 'same-origin',
			headers: {
				'Content-Type':
					'application/x-www-form-urlencoded; charset=UTF-8',
			},
			method: 'POST',
		} );
		const envelope = ( await response.json() ) as ApiEnvelope;

		if ( ! envelope.data || ! Array.isArray( envelope.data.items ) ) {
			throw new Error( 'Invalid Starfiniti Cart response.' );
		}

		const previousNonce = this.nonce;
		this.nonce = envelope.data.nonce || this.nonce;

		if (
			response.status === 403 &&
			allowNonceRetry &&
			this.nonce !== previousNonce
		) {
			return this.request( endpoint, payload, false );
		}

		return {
			state: envelope.data,
			status: response.status,
			success: response.ok && envelope.success,
		};
	}
}
