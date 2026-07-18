import { CartApi, type ApiResult } from './api';
import { dispatchCartEvent, SFCART_EVENTS } from './events';
import { OperationQueue } from './operation-queue';
import type {
	AppliedCoupon,
	CartItem,
	CartNotice,
	CartState,
	Recommendation,
	RecommendationVariation,
	SfcartConfig,
	SpecialAddonProduct,
} from './types';
import { clampQuantity, createElement, formatTemplate } from './utils';

const FOCUSABLE_SELECTOR = [
	'a[href]',
	'button:not([disabled])',
	'input:not([disabled])',
	'select:not([disabled])',
	'textarea:not([disabled])',
	'[tabindex]:not([tabindex="-1"])',
].join( ',' );

export class DrawerController {
	private readonly api: CartApi;
	private readonly panel: HTMLElement;
	private readonly items: HTMLUListElement;
	private readonly empty: HTMLElement;
	private readonly footer: HTMLElement;
	private readonly loading: HTMLElement;
	private readonly notices: HTMLElement;
	private readonly rewards: HTMLElement;
	private readonly recommendations: HTMLElement;
	private readonly specialAddon: HTMLElement;
	private activeBeforeOpen: HTMLElement | null = null;
	private state: CartState | null = null;
	private readonly operations = new OperationQueue();
	private lastAutoOpen = 0;
	private closeTimer: number | null = null;
	private impressionSignature = '';
	private recommendationCarouselSignature = '';
	private recommendationCarouselIndex = 0;
	private openEventPending = false;

	public constructor(
		private readonly root: HTMLElement,
		private readonly config: SfcartConfig
	) {
		this.api = new CartApi( config );
		this.panel = this.required< HTMLElement >( '.sfcart-drawer' );
		this.items = this.required< HTMLUListElement >( '[data-sfcart-items]' );
		this.empty = this.required< HTMLElement >( '[data-sfcart-empty]' );
		this.footer = this.required< HTMLElement >( '[data-sfcart-footer]' );
		this.loading = this.required< HTMLElement >( '[data-sfcart-loading]' );
		this.notices = this.required< HTMLElement >( '[data-sfcart-notices]' );
		this.rewards = this.required< HTMLElement >( '[data-sfcart-rewards]' );
		this.recommendations = this.required< HTMLElement >(
			'[data-sfcart-recommendations]'
		);
		this.specialAddon = this.required< HTMLElement >(
			'[data-sfcart-special-addon]'
		);
	}

	public connect(): void {
		document.addEventListener( 'click', this.handleDocumentClick );
		document.addEventListener(
			'wc-blocks_added_to_cart',
			this.handleExternalAdd
		);
		this.root.addEventListener( 'click', this.handleRootClick );
		this.root.addEventListener( 'change', this.handleQuantityChange );
		this.root.addEventListener( 'submit', this.handleSubmit );
		document.addEventListener( 'keydown', this.handleKeydown );

		if ( window.jQuery ) {
			window
				.jQuery( document.body )
				.on( 'added_to_cart', this.handleExternalAdd );
		}
	}

	public open = (): void => {
		if ( ! this.root.hidden && this.root.classList.contains( 'is-open' ) ) {
			void this.refresh();
			return;
		}

		if ( this.closeTimer !== null ) {
			window.clearTimeout( this.closeTimer );
			this.closeTimer = null;
		}

		const activeElement = this.root.ownerDocument.activeElement;
		this.activeBeforeOpen =
			activeElement instanceof HTMLElement ? activeElement : null;
		this.root.hidden = false;
		document.body.classList.add( 'sfcart-is-open' );

		window.requestAnimationFrame( () => {
			this.root.classList.add( 'is-open' );
			this.panel.focus();
		} );

		dispatchCartEvent( SFCART_EVENTS.opened );
		this.openEventPending = true;
		void this.refresh();
	};

	public close = (): void => {
		if ( this.root.hidden ) {
			return;
		}

		this.root.classList.remove( 'is-open' );
		document.body.classList.remove( 'sfcart-is-open' );
		this.closeTimer = window.setTimeout( () => {
			this.root.hidden = true;
			this.closeTimer = null;
		}, 240 );
		this.activeBeforeOpen?.focus();
		this.api.trackEvent(
			'cart_close',
			this.state?.is_empty ? 'empty' : 'non_empty'
		);
		dispatchCartEvent( SFCART_EVENTS.closed );
	};

	private readonly handleDocumentClick = ( event: MouseEvent ): void => {
		const target = event.target;

		if ( ! ( target instanceof Element ) ) {
			return;
		}

		const toggle = target.closest< HTMLElement >( '[data-sfcart-toggle]' );

		if ( toggle ) {
			event.preventDefault();
			this.open();
		}
	};

	private readonly handleRootClick = ( event: MouseEvent ): void => {
		const target = event.target;

		if ( ! ( target instanceof Element ) ) {
			return;
		}

		if ( target.closest( '[data-sfcart-close]' ) ) {
			this.close();
			return;
		}

		if ( target.closest( '[data-sfcart-continue-shopping]' ) ) {
			event.preventDefault();
			this.close();
			return;
		}

		if ( target.closest( '[data-sfcart-carousel-previous]' ) ) {
			event.preventDefault();
			this.recommendationCarouselIndex = Math.max(
				0,
				this.recommendationCarouselIndex - 1
			);
			this.updateRecommendationCarousel();
			return;
		}

		if ( target.closest( '[data-sfcart-carousel-next]' ) ) {
			event.preventDefault();
			const count = this.recommendations.querySelectorAll(
				'[data-sfcart-recommendation]'
			).length;
			this.recommendationCarouselIndex = Math.min(
				Math.max( 0, count - 1 ),
				this.recommendationCarouselIndex + 1
			);
			this.updateRecommendationCarousel();
			return;
		}

		if ( target.closest( '[data-sfcart-checkout]' ) ) {
			this.api.trackEvent(
				'checkout_click',
				this.state?.is_empty ? 'empty' : 'non_empty'
			);
			return;
		}

		const action = target.closest< HTMLButtonElement >(
			'[data-sfcart-action]'
		);

		if ( ! action ) {
			return;
		}

		const key = action.dataset.cartKey ?? '';
		const item = this.state?.items.find(
			( candidate ) => candidate.key === key
		);

		if ( action.dataset.sfcartAction === 'remove-coupon' ) {
			const code = action.dataset.couponCode ?? '';
			this.queueMutation(
				() => this.api.removeCoupon( code ),
				null,
				'coupon_remove'
			);
			return;
		}

		if ( action.dataset.sfcartAction === 'add-recommendation' ) {
			const productId = Number( action.dataset.productId ?? 0 );
			const card = action.closest< HTMLElement >(
				'[data-sfcart-recommendation]'
			);
			const recommendation = this.state?.recommendations.items.find(
				( candidate ) => candidate.id === productId
			);
			if ( ! card || ! recommendation ) {
				return;
			}

			const selection = this.selectedVariation( card, recommendation );
			if ( recommendation.type === 'variable' && ! selection ) {
				card.querySelector< HTMLSelectElement >( 'select' )?.focus();
				return;
			}

			this.queueMutation(
				() =>
					this.api.addRecommendation(
						productId,
						selection?.variation.id ?? 0,
						selection?.attributes ?? {}
					),
				'added',
				'recommendation_add'
			);
			return;
		}

		if ( ! item ) {
			return;
		}

		if ( action.dataset.sfcartAction === 'remove' ) {
			this.queueMutation(
				() => this.api.removeItem( key ),
				'removed',
				'item_remove'
			);
			return;
		}

		const direction = action.dataset.sfcartAction === 'increase' ? 1 : -1;
		const quantity = clampQuantity(
			item.quantity + item.quantity_step * direction,
			item.quantity_min,
			item.quantity_max
		);

		if ( quantity !== item.quantity ) {
			this.queueMutation(
				() => this.api.updateItem( key, quantity ),
				null,
				'quantity_update'
			);
		}
	};

	private readonly handleQuantityChange = ( event: Event ): void => {
		const input = event.target;
		if (
			input instanceof HTMLInputElement &&
			input.matches( '[data-sfcart-addon-control]' )
		) {
			const product = this.state?.special_addon.product;
			if ( ! product ) {
				return;
			}
			const selection = this.selectedAddonVariation( product );
			if ( input.checked && product.type === 'variable' && ! selection ) {
				input.checked = false;
				this.specialAddon
					.querySelector< HTMLSelectElement >(
						'[data-sfcart-addon-attribute]'
					)
					?.focus();
				return;
			}
			this.queueMutation(
				() =>
					this.api.setSpecialAddon(
						input.checked,
						selection?.variation.id ?? 0,
						selection?.attributes ?? {}
					),
				null,
				input.checked ? 'addon_select' : 'addon_remove'
			);
			return;
		}

		if (
			input instanceof HTMLSelectElement &&
			input.matches( '[data-sfcart-addon-attribute]' )
		) {
			const product = this.state?.special_addon.product;
			if ( ! product ) {
				return;
			}
			const selection = this.updateAddonVariationSelection( product );
			if ( selection && this.state?.special_addon.selected ) {
				this.queueMutation(
					() =>
						this.api.setSpecialAddon(
							true,
							selection.variation.id,
							selection.attributes
						),
					null,
					'addon_variation'
				);
			}
			return;
		}

		if (
			input instanceof HTMLSelectElement &&
			input.matches( '[data-sfcart-attribute]' )
		) {
			const card = input.closest< HTMLElement >(
				'[data-sfcart-recommendation]'
			);
			const productId = Number( card?.dataset.sfcartRecommendation ?? 0 );
			const recommendation = this.state?.recommendations.items.find(
				( candidate ) => candidate.id === productId
			);
			if ( card && recommendation ) {
				this.updateVariationSelection( card, recommendation );
			}
			return;
		}

		if (
			! ( input instanceof HTMLInputElement ) ||
			! input.matches( '[data-sfcart-quantity]' )
		) {
			return;
		}

		const key = input.dataset.cartKey ?? '';
		const item = this.state?.items.find(
			( candidate ) => candidate.key === key
		);
		const parsed = Number.parseFloat( input.value );

		if ( ! item || ! Number.isFinite( parsed ) ) {
			if ( item ) {
				input.value = String( item.quantity );
			}
			return;
		}

		this.queueMutation(
			() => this.api.updateItem( key, parsed ),
			null,
			'quantity_update'
		);
	};

	private readonly handleSubmit = ( event: SubmitEvent ): void => {
		const form = event.target;

		if (
			! ( form instanceof HTMLFormElement ) ||
			! form.matches( '[data-sfcart-coupon-form]' )
		) {
			return;
		}

		event.preventDefault();
		const input = form.elements.namedItem( 'coupon_code' );

		if ( ! ( input instanceof HTMLInputElement ) || ! input.value.trim() ) {
			if ( input instanceof HTMLInputElement ) {
				input.focus();
			}
			return;
		}

		const code = input.value.trim();
		this.queueMutation(
			async () => {
				const result = await this.api.applyCoupon( code );
				if ( result.success ) {
					form.reset();
				}
				return result;
			},
			null,
			'coupon_apply'
		);
	};

	private readonly handleKeydown = ( event: KeyboardEvent ): void => {
		if ( this.root.hidden ) {
			return;
		}

		if ( event.key === 'Escape' ) {
			event.preventDefault();
			this.close();
			return;
		}

		if ( event.key !== 'Tab' ) {
			return;
		}

		const focusable = Array.from(
			this.panel.querySelectorAll< HTMLElement >( FOCUSABLE_SELECTOR )
		).filter( ( element ) => element.offsetParent !== null );

		if ( focusable.length === 0 ) {
			event.preventDefault();
			this.panel.focus();
			return;
		}

		const first = focusable[ 0 ];
		const last = focusable[ focusable.length - 1 ];

		const activeElement = this.root.ownerDocument.activeElement;

		if ( event.shiftKey && activeElement === first ) {
			event.preventDefault();
			last.focus();
		} else if ( ! event.shiftKey && activeElement === last ) {
			event.preventDefault();
			first.focus();
		}
	};

	private readonly handleExternalAdd = (): void => {
		const now = Date.now();

		if ( now - this.lastAutoOpen < 150 ) {
			return;
		}

		this.lastAutoOpen = now;
		if ( this.config.behavior.autoOpen ) {
			this.open();
		} else if ( ! this.root.hidden ) {
			void this.refresh();
		}
	};

	private async refresh(): Promise< void > {
		const operation = this.operations.enqueueRefresh( async () => {
			this.setBusy( true );
			try {
				const result = await this.api.state();
				this.render( result.state );
			} catch {
				this.renderClientError( this.config.labels.requestFailed );
			} finally {
				this.setBusy( false );
			}
		} );

		if ( operation ) {
			await operation;
		}
	}

	private queueMutation(
		mutation: () => Promise< ApiResult >,
		notify: 'added' | 'removed' | null = null,
		analyticsAction = ''
	): void {
		const focusTarget = this.focusIdentity();

		void this.operations.enqueue( async () => {
			this.setBusy( true );
			try {
				const result = await mutation();
				this.render( result.state );
				this.restoreFocus( focusTarget );
				if ( analyticsAction ) {
					this.api.trackEvent(
						'cart_interaction',
						analyticsAction,
						result.success ? 'success' : 'failed'
					);
				}

				if ( notify === 'removed' && result.success ) {
					document.dispatchEvent(
						new CustomEvent( 'wc-blocks_removed_from_cart', {
							detail: { preserveCartData: false },
						} )
					);
				}
				if ( notify === 'added' && result.success ) {
					document.dispatchEvent(
						new CustomEvent( 'wc-blocks_added_to_cart', {
							detail: { preserveCartData: false },
						} )
					);
				}
			} catch {
				this.renderClientError( this.config.labels.requestFailed );
			} finally {
				this.setBusy( false );
			}
		} );
	}

	private render( state: CartState ): void {
		this.state = state;
		this.loading.hidden = true;
		this.renderNotices( state.notices );
		this.renderItems( state.items );
		this.renderRewards( state );
		this.renderSpecialAddon( state );
		this.renderRecommendations( state );
		this.renderCoupons( state.coupons );
		if ( this.openEventPending ) {
			this.openEventPending = false;
			this.api.trackEvent(
				'cart_open',
				state.is_empty ? 'empty' : 'non_empty'
			);
		}

		this.empty.hidden = ! state.is_empty;
		this.items.hidden = state.is_empty;
		this.footer.hidden = state.is_empty;
		this.required< HTMLFormElement >( '[data-sfcart-coupon-form]' ).hidden =
			! state.coupons_enabled || ! this.config.behavior.coupons;

		this.setText( '[data-sfcart-subtotal]', state.subtotal );
		document
			.querySelectorAll< HTMLElement >( '[data-sfcart-trigger-subtotal]' )
			.forEach( ( subtotal ) => {
				subtotal.textContent = state.subtotal;
			} );
		this.setText( '[data-sfcart-total]', state.total );
		this.setOptionalRow(
			'[data-sfcart-shipping-row]',
			'[data-sfcart-shipping]',
			this.config.behavior.showShipping ? state.shipping : ''
		);
		this.setOptionalRow(
			'[data-sfcart-tax-row]',
			'[data-sfcart-tax]',
			this.config.behavior.showTax ? state.tax : ''
		);

		this.required< HTMLAnchorElement >( '[data-sfcart-checkout]' ).href =
			state.urls.checkout;
		const cartLink = this.required< HTMLAnchorElement >(
			'[data-sfcart-cart-link]'
		);
		cartLink.href = state.urls.cart;
		cartLink.hidden = ! this.config.behavior.showCartLink;
		const shopLink = this.required< HTMLAnchorElement >(
			'[data-sfcart-shop-link]'
		);
		shopLink.href = state.urls.shop;
		shopLink.hidden = ! this.config.behavior.showContinueShopping;
		const continueShopping = this.required< HTMLAnchorElement >(
			'[data-sfcart-continue-shopping]'
		);
		continueShopping.href = state.urls.shop;
		continueShopping.hidden = ! this.config.behavior.showContinueShopping;

		const itemLabel =
			state.item_count === 1
				? this.config.labels.item
				: this.config.labels.items;
		this.setText(
			'[data-sfcart-header-count]',
			`${ state.item_count } ${ itemLabel }`
		);
		document
			.querySelectorAll< HTMLElement >( '[data-sfcart-count]' )
			.forEach( ( count ) => {
				count.textContent = String( state.item_count );
				count.setAttribute(
					'aria-label',
					`${ state.item_count } ${ itemLabel }`
				);
			} );

		dispatchCartEvent( SFCART_EVENTS.updated, {
			cartHash: state.cart_hash,
			itemCount: state.item_count,
		} );
	}

	private renderSpecialAddon( state: CartState ): void {
		const addon = state.special_addon;
		const product = addon.product;
		this.specialAddon.hidden = ! addon.enabled || ! product;
		this.specialAddon.replaceChildren();
		if ( ! addon.enabled || ! product ) {
			return;
		}

		this.specialAddon.classList.toggle(
			'sfcart-special-addon--toggle',
			addon.selection_type === 'toggle'
		);
		this.specialAddon.classList.toggle(
			'sfcart-special-addon--has-image',
			addon.image_enabled
		);
		this.specialAddon.style.setProperty(
			'--sfcart-addon-background',
			addon.background
		);
		this.specialAddon.style.setProperty(
			'--sfcart-addon-accent',
			addon.accent
		);
		this.specialAddon.style.setProperty(
			'--sfcart-addon-heading',
			addon.heading_color
		);
		this.specialAddon.style.setProperty(
			'--sfcart-addon-description',
			addon.description_color
		);
		this.specialAddon.style.setProperty(
			'--sfcart-addon-image-size',
			`${ addon.image_size }px`
		);

		if ( addon.image_enabled ) {
			const image = createElement( 'img', 'sfcart-special-addon__image' );
			image.src = product.image;
			image.alt = '';
			image.loading = 'lazy';
			image.dataset.sfcartAddonImage = '';
			this.specialAddon.append( image );
		}

		const content = createElement( 'div', 'sfcart-special-addon__content' );
		const title = product.url
			? createElement(
					'a',
					'sfcart-special-addon__heading',
					addon.heading
			  )
			: createElement(
					'strong',
					'sfcart-special-addon__heading',
					addon.heading
			  );
		if ( title instanceof HTMLAnchorElement ) {
			title.href = product.url;
		}
		content.append( title );
		if ( addon.description ) {
			content.append(
				createElement(
					'p',
					'sfcart-special-addon__description',
					addon.description
				)
			);
		}
		content.append(
			createElement(
				'span',
				'sfcart-special-addon__price',
				product.price
			)
		);

		if ( product.type === 'variable' ) {
			const choices = createElement(
				'div',
				'sfcart-special-addon__choices'
			);
			product.attributes.forEach( ( attribute ) => {
				const select = createElement(
					'select',
					'sfcart-special-addon__select'
				);
				select.dataset.sfcartAddonAttribute = attribute.key;
				select.setAttribute(
					'aria-label',
					formatTemplate( this.config.labels.chooseOption, [
						attribute.label,
					] )
				);
				const placeholder = createElement(
					'option',
					'',
					formatTemplate( this.config.labels.chooseOption, [
						attribute.label,
					] )
				);
				placeholder.value = '';
				select.append( placeholder );
				attribute.options.forEach( ( option ) => {
					const item = createElement( 'option', '', option.label );
					item.value = option.value;
					select.append( item );
				} );
				select.value =
					product.selected_attributes?.[ attribute.key ] ?? '';
				choices.append( select );
			} );
			content.append( choices );
		}
		this.specialAddon.append( content );

		const label = createElement( 'label', 'sfcart-special-addon__control' );
		const control = createElement( 'input', '' );
		control.type = 'checkbox';
		control.checked = addon.selected;
		control.dataset.sfcartAddonControl = '';
		control.setAttribute( 'aria-label', this.config.labels.selectAddon );
		label.append( control );
		if ( addon.selection_type === 'toggle' ) {
			label.append(
				createElement( 'span', 'sfcart-special-addon__switch' )
			);
		}
		this.specialAddon.append( label );
		this.updateAddonVariationSelection( product );
	}

	private selectedAddonVariation( product: SpecialAddonProduct ): {
		variation: RecommendationVariation;
		attributes: Record< string, string >;
	} | null {
		if ( product.type !== 'variable' ) {
			return null;
		}
		const attributes: Record< string, string > = {};
		for ( const select of this.specialAddon.querySelectorAll< HTMLSelectElement >(
			'[data-sfcart-addon-attribute]'
		) ) {
			const key = select.dataset.sfcartAddonAttribute ?? '';
			if ( ! key || ! select.value ) {
				return null;
			}
			attributes[ key ] = select.value;
		}
		const variation = product.variations.find( ( candidate ) =>
			Object.entries( candidate.attributes ).every(
				( [ key, value ] ) => ! value || attributes[ key ] === value
			)
		);
		return variation ? { attributes, variation } : null;
	}

	private updateAddonVariationSelection( product: SpecialAddonProduct ): {
		variation: RecommendationVariation;
		attributes: Record< string, string >;
	} | null {
		const selection = this.selectedAddonVariation( product );
		const control = this.specialAddon.querySelector< HTMLInputElement >(
			'[data-sfcart-addon-control]'
		);
		if (
			control &&
			product.type === 'variable' &&
			! this.state?.special_addon.selected
		) {
			control.disabled = ! selection;
		}
		if ( selection ) {
			const price = this.specialAddon.querySelector< HTMLElement >(
				'.sfcart-special-addon__price'
			);
			const image = this.specialAddon.querySelector< HTMLImageElement >(
				'[data-sfcart-addon-image]'
			);
			if ( price ) {
				price.textContent = selection.variation.price;
			}
			if (
				image &&
				selection.variation.image &&
				this.state?.special_addon.image_source === 'product'
			) {
				image.src = selection.variation.image;
			}
		}
		return selection;
	}

	private renderItems( items: CartItem[] ): void {
		this.items.replaceChildren(
			...items.map( ( item ) => this.renderItem( item ) )
		);
	}

	private renderRewards( state: CartState ): void {
		const rewards = state.rewards;
		const visible = rewards.enabled && ! state.is_empty;
		this.rewards.hidden = ! visible;
		this.rewards.classList.remove(
			'sfcart-rewards--bar',
			'sfcart-rewards--steps',
			'sfcart-rewards--compact',
			'is-complete'
		);

		const visual = this.required< HTMLElement >(
			'[data-sfcart-rewards-visual]'
		);
		if ( ! visible ) {
			visual.replaceChildren();
			return;
		}

		this.rewards.classList.add( `sfcart-rewards--${ rewards.design }` );
		this.rewards.classList.toggle( 'is-complete', rewards.complete );
		this.setText( '[data-sfcart-rewards-message]', rewards.message );

		if ( rewards.design === 'steps' ) {
			const steps = createElement( 'ol', 'sfcart-rewards__steps' );
			for ( const milestone of rewards.milestones ) {
				const step = createElement(
					'li',
					milestone.achieved ? 'is-achieved' : '',
					milestone.label
				);
				step.setAttribute( 'aria-label', milestone.message );
				steps.append( step );
			}
			visual.replaceChildren( steps );
			return;
		}

		const progress = createElement( 'div', 'sfcart-rewards__progress' );
		progress.setAttribute( 'role', 'progressbar' );
		progress.setAttribute( 'aria-valuemin', '0' );
		progress.setAttribute( 'aria-valuemax', '100' );
		progress.setAttribute( 'aria-valuenow', String( rewards.progress ) );
		progress.setAttribute( 'aria-label', rewards.message );
		const fill = createElement( 'span', 'sfcart-rewards__fill' );
		fill.style.width = `${ Math.max(
			0,
			Math.min( 100, rewards.progress )
		) }%`;
		progress.append( fill );

		if ( rewards.design === 'bar' ) {
			for ( const milestone of rewards.milestones ) {
				const marker = createElement(
					'span',
					`sfcart-rewards__marker${
						milestone.achieved ? ' is-achieved' : ''
					}`
				);
				marker.style.insetInlineStart = `${ Math.max(
					0,
					Math.min( 100, milestone.position )
				) }%`;
				marker.title = milestone.label;
				progress.append( marker );
			}
		}

		visual.replaceChildren( progress );
	}

	private renderRecommendations( state: CartState ): void {
		const recommendations = state.recommendations;
		const visible =
			recommendations.enabled && recommendations.items.length > 0;
		this.recommendations.hidden = ! visible;

		if ( ! visible ) {
			this.required< HTMLElement >(
				'[data-sfcart-recommendations-items]'
			).replaceChildren();
			return;
		}

		this.placeRecommendations( recommendations.placement );

		this.recommendations.classList.remove(
			'sfcart-recommendations--style1',
			'sfcart-recommendations--style2',
			'sfcart-recommendations--style3',
			'sfcart-recommendations--carousel',
			'sfcart-recommendations--before-items',
			'sfcart-recommendations--after-items',
			'sfcart-recommendations--before-totals',
			'sfcart-recommendations--after-checkout'
		);
		this.recommendations.classList.add(
			`sfcart-recommendations--${ recommendations.layout }`,
			`sfcart-recommendations--${ recommendations.placement.replaceAll(
				'_',
				'-'
			) }`
		);
		this.setText(
			'[data-sfcart-recommendations-heading]',
			recommendations.heading
		);
		const items = this.required< HTMLElement >(
			'[data-sfcart-recommendations-items]'
		);
		items.replaceChildren(
			...recommendations.items.map( ( item ) =>
				this.renderRecommendation( item )
			)
		);

		const navigation = this.required< HTMLElement >(
			'[data-sfcart-recommendations-navigation]'
		);
		const isCarousel = recommendations.layout === 'carousel';
		navigation.hidden = ! isCarousel || recommendations.items.length < 2;
		const previous = this.required< HTMLButtonElement >(
			'[data-sfcart-carousel-previous]'
		);
		const next = this.required< HTMLButtonElement >(
			'[data-sfcart-carousel-next]'
		);
		previous.setAttribute(
			'aria-label',
			this.config.labels.recommendationPrevious
		);
		next.setAttribute(
			'aria-label',
			this.config.labels.recommendationNext
		);

		const carouselSignature = `${
			recommendations.layout
		}:${ recommendations.items.map( ( item ) => item.id ).join( ',' ) }`;
		if ( carouselSignature !== this.recommendationCarouselSignature ) {
			this.recommendationCarouselSignature = carouselSignature;
			this.recommendationCarouselIndex = 0;
		}
		this.updateRecommendationCarousel();

		const signature = recommendations.items
			.map( ( item ) => item.id )
			.join( ',' );
		if ( signature && signature !== this.impressionSignature ) {
			this.impressionSignature = signature;
			void this.api
				.trackRecommendations(
					recommendations.items.map( ( item ) => item.id )
				)
				.catch( () => undefined );
		}
	}

	private updateRecommendationCarousel(): void {
		const isCarousel = this.recommendations.classList.contains(
			'sfcart-recommendations--carousel'
		);
		const cards = Array.from(
			this.recommendations.querySelectorAll< HTMLElement >(
				'[data-sfcart-recommendation]'
			)
		);
		cards.forEach( ( card, index ) => {
			card.hidden =
				isCarousel && index !== this.recommendationCarouselIndex;
		} );

		const previous =
			this.recommendations.querySelector< HTMLButtonElement >(
				'[data-sfcart-carousel-previous]'
			);
		const next = this.recommendations.querySelector< HTMLButtonElement >(
			'[data-sfcart-carousel-next]'
		);
		if ( previous ) {
			previous.disabled = this.recommendationCarouselIndex === 0;
		}
		if ( next ) {
			next.disabled =
				cards.length === 0 ||
				this.recommendationCarouselIndex >= cards.length - 1;
		}
	}

	private placeRecommendations(
		placement:
			| 'before_items'
			| 'after_items'
			| 'before_totals'
			| 'after_checkout'
	): void {
		if ( placement === 'before_items' ) {
			this.items.before( this.recommendations );
			return;
		}

		if ( placement === 'before_totals' ) {
			this.footer.prepend( this.recommendations );
			return;
		}

		if ( placement === 'after_checkout' ) {
			this.required< HTMLElement >( '[data-sfcart-footer-links]' ).before(
				this.recommendations
			);
			return;
		}

		this.specialAddon.after( this.recommendations );
	}

	private renderRecommendation( item: Recommendation ): HTMLElement {
		const card = createElement( 'article', 'sfcart-recommendation' );
		card.dataset.sfcartRecommendation = String( item.id );

		const image = createElement( 'img', 'sfcart-recommendation__image' );
		image.src = item.image;
		image.alt = '';
		image.loading = 'lazy';
		image.dataset.sfcartRecommendationImage = '';
		card.append( image );

		const content = createElement(
			'div',
			'sfcart-recommendation__content'
		);
		const name = createElement(
			'a',
			'sfcart-recommendation__name',
			item.name
		);
		name.href = item.url;
		content.append( name );
		content.append(
			createElement( 'span', 'sfcart-recommendation__price', item.price )
		);

		if ( item.type === 'variable' ) {
			const choices = createElement(
				'div',
				'sfcart-recommendation__choices'
			);
			item.attributes.forEach( ( attribute ) => {
				const label = createElement(
					'label',
					'sfcart-screen-reader-text',
					formatTemplate( this.config.labels.chooseOption, [
						attribute.label,
					] )
				);
				const select = createElement(
					'select',
					'sfcart-recommendation__select'
				);
				select.dataset.sfcartAttribute = attribute.key;
				select.setAttribute( 'aria-label', label.textContent ?? '' );
				const placeholder = createElement(
					'option',
					'',
					formatTemplate( this.config.labels.chooseOption, [
						attribute.label,
					] )
				);
				placeholder.value = '';
				select.append( placeholder );
				attribute.options.forEach( ( option ) => {
					const element = createElement( 'option', '', option.label );
					element.value = option.value;
					select.append( element );
				} );
				choices.append( label, select );
			} );
			content.append( choices );
		}

		const add = createElement(
			'button',
			'sfcart-secondary-button sfcart-recommendation__add',
			item.type === 'variable'
				? this.config.labels.chooseOptions
				: this.config.labels.addRecommendation
		);
		add.type = 'button';
		add.dataset.sfcartAction = 'add-recommendation';
		add.dataset.productId = String( item.id );
		add.disabled = item.type === 'variable';
		content.append( add );
		card.append( content );

		return card;
	}

	private selectedVariation(
		card: HTMLElement,
		item: Recommendation
	): {
		variation: RecommendationVariation;
		attributes: Record< string, string >;
	} | null {
		const attributes: Record< string, string > = {};
		for ( const select of card.querySelectorAll< HTMLSelectElement >(
			'[data-sfcart-attribute]'
		) ) {
			const key = select.dataset.sfcartAttribute ?? '';
			if ( ! key || ! select.value ) {
				return null;
			}
			attributes[ key ] = select.value;
		}

		const variation = item.variations.find( ( candidate ) =>
			Object.entries( candidate.attributes ).every(
				( [ key, value ] ) => ! value || attributes[ key ] === value
			)
		);

		return variation ? { attributes, variation } : null;
	}

	private updateVariationSelection(
		card: HTMLElement,
		item: Recommendation
	): void {
		const selection = this.selectedVariation( card, item );
		const button = card.querySelector< HTMLButtonElement >(
			'[data-sfcart-action="add-recommendation"]'
		);
		if ( button ) {
			button.disabled = ! selection;
			button.textContent = selection
				? this.config.labels.addRecommendation
				: this.config.labels.chooseOptions;
		}
		if ( selection ) {
			const price = card.querySelector< HTMLElement >(
				'.sfcart-recommendation__price'
			);
			const image = card.querySelector< HTMLImageElement >(
				'[data-sfcart-recommendation-image]'
			);
			if ( price ) {
				price.textContent = selection.variation.price;
			}
			if ( image && selection.variation.image ) {
				image.src = selection.variation.image;
			}
		}
	}

	private renderItem( item: CartItem ): HTMLLIElement {
		const row = createElement( 'li', 'sfcart-item' );
		row.dataset.cartKey = item.key;

		const image = createElement( 'img', 'sfcart-item__image' );
		image.src = item.image;
		image.alt = '';
		image.loading = 'lazy';
		row.append( image );

		const content = createElement( 'div', 'sfcart-item__content' );
		const heading = createElement( 'div', 'sfcart-item__heading' );
		const name = item.url
			? createElement( 'a', 'sfcart-item__name', item.name )
			: createElement( 'span', 'sfcart-item__name', item.name );
		if ( name instanceof HTMLAnchorElement ) {
			name.href = item.url;
		}
		heading.append( name );

		const remove = createElement(
			'button',
			'sfcart-icon-button sfcart-item__remove',
			'×'
		);
		remove.type = 'button';
		remove.dataset.sfcartAction = 'remove';
		remove.dataset.cartKey = item.key;
		remove.setAttribute(
			'aria-label',
			formatTemplate( this.config.labels.remove, [ item.name ] )
		);
		if ( item.removable ) {
			heading.append( remove );
		}
		content.append( heading );

		if ( item.meta ) {
			content.append(
				createElement( 'p', 'sfcart-item__meta', item.meta )
			);
		}
		if ( item.backorder ) {
			content.append(
				createElement( 'p', 'sfcart-item__backorder', item.backorder )
			);
		}

		const controls = createElement( 'div', 'sfcart-item__controls' );
		controls.append( this.renderQuantity( item ) );
		const prices = createElement( 'div', 'sfcart-item__prices' );
		prices.append(
			createElement( 'strong', 'sfcart-item__total', item.line_total )
		);
		prices.append(
			createElement( 'span', 'sfcart-item__unit', item.unit_price )
		);
		controls.append( prices );
		content.append( controls );

		if ( item.savings ) {
			content.append(
				createElement(
					'p',
					'sfcart-item__savings',
					formatTemplate( this.config.labels.savings, [
						item.savings.amount,
						item.savings.percentage,
					] )
				)
			);
		}

		row.append( content );
		return row;
	}

	private renderQuantity( item: CartItem ): HTMLElement {
		if ( ! item.editable ) {
			return createElement(
				'span',
				'sfcart-item__fixed-quantity',
				`× ${ item.quantity }`
			);
		}

		const selector = createElement( 'div', 'sfcart-quantity' );
		const decrease = createElement(
			'button',
			'sfcart-quantity__button',
			'−'
		);
		decrease.type = 'button';
		decrease.dataset.sfcartAction = 'decrease';
		decrease.dataset.cartKey = item.key;
		decrease.setAttribute(
			'aria-label',
			formatTemplate( this.config.labels.decrease, [ item.name ] )
		);
		decrease.disabled = item.quantity <= item.quantity_min;

		const input = createElement( 'input', 'sfcart-quantity__input' );
		input.type = 'number';
		input.value = String( item.quantity );
		input.min = String( item.quantity_min );
		input.step = String( item.quantity_step );
		input.inputMode = Number.isInteger( item.quantity_step )
			? 'numeric'
			: 'decimal';
		input.dataset.sfcartQuantity = '';
		input.dataset.cartKey = item.key;
		input.setAttribute(
			'aria-label',
			formatTemplate( this.config.labels.quantity, [ item.name ] )
		);
		if ( item.quantity_max !== null ) {
			input.max = String( item.quantity_max );
		}

		const increase = createElement(
			'button',
			'sfcart-quantity__button',
			'+'
		);
		increase.type = 'button';
		increase.dataset.sfcartAction = 'increase';
		increase.dataset.cartKey = item.key;
		increase.setAttribute(
			'aria-label',
			formatTemplate( this.config.labels.increase, [ item.name ] )
		);
		increase.disabled =
			item.quantity_max !== null && item.quantity >= item.quantity_max;

		selector.append( decrease, input, increase );
		return selector;
	}

	private renderCoupons( coupons: AppliedCoupon[] ): void {
		const container = this.required< HTMLElement >(
			'[data-sfcart-coupons]'
		);
		container.replaceChildren(
			...coupons.map( ( coupon ) => {
				const row = createElement( 'div', 'sfcart-applied-coupon' );
				const label = createElement( 'span', '', coupon.code );
				const amount = createElement(
					'span',
					'',
					`−${ coupon.discount }`
				);
				const remove = createElement(
					'button',
					'sfcart-icon-button',
					'×'
				);
				remove.type = 'button';
				remove.dataset.sfcartAction = 'remove-coupon';
				remove.dataset.couponCode = coupon.code;
				remove.setAttribute(
					'aria-label',
					formatTemplate( this.config.labels.removeCoupon, [
						coupon.code,
					] )
				);
				row.append( label, amount, remove );
				return row;
			} )
		);
	}

	private renderNotices( notices: CartNotice[] ): void {
		this.notices.replaceChildren(
			...notices.map( ( notice ) =>
				createElement(
					'p',
					`sfcart-notice sfcart-notice--${ notice.type }`,
					notice.message
				)
			)
		);
	}

	private renderClientError( message: string ): void {
		this.loading.hidden = true;
		this.renderNotices( [ { message, type: 'error' } ] );
	}

	private setBusy( busy: boolean ): void {
		this.root.setAttribute( 'aria-busy', String( busy ) );
		this.root.classList.toggle( 'is-busy', busy );
		if ( busy && ! this.state ) {
			this.loading.hidden = false;
		}
	}

	private setOptionalRow(
		rowSelector: string,
		valueSelector: string,
		value: string
	): void {
		const row = this.required< HTMLElement >( rowSelector );
		row.hidden = ! value;
		this.setText( valueSelector, value );
	}

	private setText( selector: string, value: string ): void {
		this.required< HTMLElement >( selector ).textContent = value;
	}

	private focusIdentity(): { action: string; key: string } | null {
		const active = this.root.ownerDocument.activeElement;

		if ( ! ( active instanceof HTMLElement ) ) {
			return null;
		}

		return {
			action:
				active.dataset.sfcartAction ??
				( active.dataset.sfcartQuantity !== undefined
					? 'quantity'
					: '' ),
			key: active.dataset.cartKey ?? '',
		};
	}

	private restoreFocus(
		identity: { action: string; key: string } | null
	): void {
		if ( ! identity?.key ) {
			return;
		}

		const selector =
			identity.action === 'quantity'
				? `[data-sfcart-quantity][data-cart-key="${ CSS.escape(
						identity.key
				  ) }"]`
				: `[data-sfcart-action="${ CSS.escape(
						identity.action
				  ) }"][data-cart-key="${ CSS.escape( identity.key ) }"]`;
		this.root.querySelector< HTMLElement >( selector )?.focus();
	}

	private required< T extends Element >( selector: string ): T {
		const element = this.root.querySelector< T >( selector );

		if ( ! element ) {
			throw new Error( `Missing Starfiniti Cart element: ${ selector }` );
		}

		return element;
	}
}
