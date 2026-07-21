export type CartSettings = {
	position: 'left' | 'right';
	width: number;
	auto_open: boolean;
	floating_button: boolean;
	header_cart: boolean;
	coupons: boolean;
	totals_display: 'full' | 'subtotal' | 'custom';
	show_subtotal: boolean;
	show_shipping: boolean;
	show_tax: boolean;
	show_total: boolean;
	show_cart_link: boolean;
	show_continue_shopping: boolean;
};

export type CartIconName =
	| 'shopping-cart'
	| 'shopping-bag'
	| 'shopping-basket'
	| 'baggage-claim'
	| 'custom';

export type DesignSettings = {
	floating_icon: CartIconName;
	floating_icon_id: number;
	floating_icon_url: string;
	floating_background: string;
	floating_hover: string;
	floating_icon_color: string;
	floating_badge_background: string;
	floating_badge_color: string;
	floating_size: number;
	floating_border_radius: number;
	shortcode_icon: CartIconName;
	shortcode_icon_id: number;
	shortcode_icon_url: string;
	shortcode_show_count: boolean;
	shortcode_show_total: boolean;
	shortcode_icon_size: number;
	shortcode_text_size: number;
	shortcode_background: string;
	shortcode_hover: string;
	shortcode_icon_color: string;
	shortcode_border: string;
	shortcode_border_width: number;
	shortcode_badge_background: string;
	shortcode_badge_color: string;
	shortcode_border_radius: number;
	accent: string;
	accent_hover: string;
	background: string;
	text: string;
	muted: string;
	border: string;
	success: string;
	danger: string;
	border_radius: number;
	overlay_opacity: number;
	empty_image_id: number;
	empty_image_url: string;
};

export type LanguageSettings = {
	title: string;
	close: string;
	loading: string;
	empty_title: string;
	empty_message: string;
	continue_shopping: string;
	coupon_code: string;
	apply_coupon: string;
	checkout: string;
	view_cart: string;
	calculation_note: string;
	open_cart: string;
};

export type UpsellSettings = {
	enabled: boolean;
	mode: 'upsells' | 'cross_sells' | 'both';
	layout: 'style1' | 'style2' | 'style3' | 'carousel';
	placement:
		| 'before_items'
		| 'after_items'
		| 'before_totals'
		| 'after_checkout';
	heading: string;
	ordering: 'relevance' | 'name' | 'price_asc' | 'price_desc';
	default_product_ids: number[];
	excluded_product_ids: number[];
	display_limit: number;
	always_show_defaults: boolean;
};

export type RewardSettings = {
	enabled: boolean;
	calculation_mode: 'subtotal' | 'total';
	progress_design: 'bar' | 'steps' | 'compact';
	complete_message: string;
	allow_gift_removal: boolean;
	milestones: RewardMilestone[];
};

export type RewardMilestone = {
	id: string;
	enabled: boolean;
	type: 'free_shipping' | 'coupon' | 'gift';
	threshold: number;
	label: string;
	pending_message: string;
	achieved_message: string;
	coupon_id: number;
	gift_product_id: number;
	gift_variation_id: number;
};

export type SpecialAddonSettings = {
	enabled: boolean;
	product_id: number;
	preselected: boolean;
	selection_type: 'checkbox' | 'toggle';
	heading: string;
	description: string;
	image_enabled: boolean;
	image_source: 'product' | 'custom';
	image_id: number;
	image_url: string;
	image_size: number;
	background: string;
	accent: string;
	heading_color: string;
	description_color: string;
};

export type Settings = {
	settings_version: number;
	cart: CartSettings;
	design: DesignSettings;
	language: LanguageSettings;
	upsells: UpsellSettings;
	rewards: RewardSettings;
	special_addon: SpecialAddonSettings;
	analytics_retention_days: number;
	delete_data_on_uninstall: boolean;
};

export type SettingsResponse = {
	settings: Settings;
	version: number;
};

export type ProductSummary = {
	id: number;
	name: string;
	sku: string;
	type: string;
	price: string;
	image: string;
	has_options: boolean;
	in_stock: boolean;
};

export type CouponSummary = {
	id: number;
	code: string;
	type: string;
	amount: string;
	description: string;
};

export type SearchItem = ProductSummary | CouponSummary;

export type SearchResponse< T extends SearchItem > = {
	items: T[];
};

export type AdminConfig = {
	nonce: string;
	root: string;
	version: string;
	iconUrl: string;
	settingsPath: string;
	productsPath: string;
	couponsPath: string;
	relationshipsPath: string;
	analyticsPath: string;
	migrationPath: string;
};

export type RelationshipsResponse = {
	product: ProductSummary;
	upsells: ProductSummary[];
	cross_sells: ProductSummary[];
};

export type MediaSelection = {
	id: number;
	url: string;
};

export type AnalyticsFilters = {
	from: string;
	to: string;
	type: string;
	product_id: number;
	coupon: string;
	currency: string;
};

export type AnalyticsMetric = {
	events: number;
	revenue: number;
	refunded: number;
};

export type AnalyticsOverview = {
	cart_opens: number;
	cart_sessions: number;
	interaction_sessions: number;
	checkout_clicks: number;
	checkout_sessions: number;
	converted_sessions: number;
	abandoned_carts: number;
	checkout_rate: number;
	cart_conversion_rate: number;
	checkout_conversion_rate: number;
	orders: number;
	order_total: number;
	attributed_revenue: number;
	refunded: number;
	net_revenue: number;
	by_type: Record< string, AnalyticsMetric >;
	by_action: Array< {
		action: string;
		events: number;
		sessions: number;
	} >;
};

export type AnalyticsConversion = {
	id: number;
	date: string;
	type: string;
	status: string;
	order_id: number;
	refund_id: number;
	currency: string;
	total: number;
	revenue: number;
	refunded: number;
	coupon: string;
};

export type AnalyticsItem = {
	product_id: number;
	variation_id: number;
	name: string;
	type: string;
	currency: string;
	events: number;
	quantity: number;
	revenue: number;
	refunded: number;
	net: number;
};

export type AnalyticsDay = {
	date: string;
	opens: number;
	interactions: number;
	checkout_clicks: number;
	orders: number;
	revenue: number;
	refunded: number;
	net: number;
};

export type AnalyticsOptions = {
	currencies: string[];
	types: string[];
};

export type MigrationState = {
	status?: string;
	started_at?: string;
	updated_at?: string;
	source_hash?: string;
	steps?: Record< string, string >;
	progress?: MigrationProgress;
};

export type MigrationProgress = {
	cart_offset: number;
	cart_total: number;
	conversions_imported: number;
	items_imported: number;
	settings_changed?: boolean;
};

export type MigrationAuditEntry = {
	id?: string;
	started_at: string;
	completed_at?: string;
	status: string;
	source_hash: string;
	settings_changed?: boolean;
	conversions_imported?: number;
	items_imported?: number;
	warnings?: string[];
	message?: string;
	progress?: MigrationProgress;
};

export type MigrationPreview = {
	available: boolean;
	source_hash: string;
	legacy_settings_keys: string[];
	table_counts: {
		cart_rows: number;
		product_rows: number;
	};
	transformed_settings: Partial< Settings >;
	warnings: string[];
	status: {
		state: MigrationState;
		audit: MigrationAuditEntry[];
	};
};

export type MigrationRunResponse = {
	preview: MigrationPreview;
	result: MigrationAuditEntry;
};
