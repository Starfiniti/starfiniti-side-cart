export type CartNotice = {
	type: 'error' | 'notice' | 'success' | string;
	message: string;
};

export type CartSavings = {
	amount: string;
	percentage: number;
};

export type CartItem = {
	key: string;
	product_id: number;
	variation_id: number;
	name: string;
	url: string;
	image: string;
	meta: string;
	quantity: number;
	quantity_min: number;
	quantity_max: number | null;
	quantity_step: number;
	editable: boolean;
	removable: boolean;
	is_reward_gift: boolean;
	is_special_addon: boolean;
	unit_price: string;
	line_total: string;
	savings: CartSavings | null;
	backorder: string;
};

export type AppliedCoupon = {
	code: string;
	discount: string;
};

export type RecommendationAttribute = {
	key: string;
	label: string;
	options: Array< { value: string; label: string } >;
};

export type RecommendationVariation = {
	id: number;
	attributes: Record< string, string >;
	price: string;
	image: string;
};

export type Recommendation = {
	id: number;
	name: string;
	url: string;
	image: string;
	price: string;
	price_value: number;
	type: 'simple' | 'variable';
	source: 'upsell' | 'cross_sell' | 'default';
	source_product_id: number;
	attributes: RecommendationAttribute[];
	variations: RecommendationVariation[];
};

export type RecommendationsState = {
	enabled: boolean;
	heading: string;
	layout: 'style1' | 'style2' | 'style3' | 'carousel';
	placement:
		| 'before_items'
		| 'after_items'
		| 'before_totals'
		| 'after_checkout';
	items: Recommendation[];
};

export type RewardMilestone = {
	id: string;
	type: 'free_shipping' | 'coupon' | 'gift';
	label: string;
	threshold: number;
	position: number;
	achieved: boolean;
	remaining: number;
	message: string;
};

export type RewardsState = {
	enabled: boolean;
	design: 'bar' | 'steps' | 'compact';
	amount: number;
	progress: number;
	complete: boolean;
	message: string;
	milestones: RewardMilestone[];
};

export type SpecialAddonProduct = {
	id: number;
	name: string;
	url: string;
	image: string;
	price: string;
	price_value: number;
	type: 'simple' | 'variable';
	attributes: RecommendationAttribute[];
	variations: RecommendationVariation[];
	selected_variation_id?: number;
	selected_attributes?: Record< string, string >;
};

export type SpecialAddonState = {
	enabled: boolean;
	selected: boolean;
	cart_key: string;
	selection_type: 'checkbox' | 'toggle';
	heading: string;
	description: string;
	image_enabled: boolean;
	image_source: 'product' | 'custom';
	image_size: number;
	background: string;
	accent: string;
	heading_color: string;
	description_color: string;
	product: SpecialAddonProduct | null;
};

export type CartState = {
	cart_hash: string;
	item_count: number;
	is_empty: boolean;
	items: CartItem[];
	coupons: AppliedCoupon[];
	coupons_enabled: boolean;
	subtotal: string;
	total: string;
	shipping: string;
	tax: string;
	notices: CartNotice[];
	recommendations: RecommendationsState;
	rewards: RewardsState;
	special_addon: SpecialAddonState;
	nonce: string;
	token: string;
	urls: {
		cart: string;
		checkout: string;
		shop: string;
	};
};

export type SfcartConfig = {
	behavior: {
		autoOpen: boolean;
		coupons: boolean;
		showShipping: boolean;
		showTax: boolean;
		showCartLink: boolean;
		showContinueShopping: boolean;
	};
	endpoints: {
		state: string;
		updateItem: string;
		removeItem: string;
		applyCoupon: string;
		removeCoupon: string;
		addRecommendation: string;
		trackRecommendations: string;
		trackEvent: string;
		setSpecialAddon: string;
	};
	nonce: string;
	labels: {
		item: string;
		items: string;
		decrease: string;
		increase: string;
		quantity: string;
		remove: string;
		removeCoupon: string;
		savings: string;
		backorder: string;
		requestFailed: string;
		sessionExpired: string;
		addRecommendation: string;
		chooseOptions: string;
		chooseOption: string;
		selectAddon: string;
		recommendationPrevious: string;
		recommendationNext: string;
	};
};

type JQueryBody = {
	on: ( eventName: string, handler: () => void ) => void;
};

declare global {
	interface Window {
		sfcartConfig?: SfcartConfig;
		jQuery?: ( element: HTMLElement ) => JQueryBody;
	}
}
