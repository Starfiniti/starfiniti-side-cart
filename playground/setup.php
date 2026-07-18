<?php
/**
 * Create deterministic Batch 2 products and storefront pages in Playground.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

wp_mkdir_p( WPMU_PLUGIN_DIR );
copy(
	'/wordpress/wp-content/plugins/starfiniti-cart/playground/test-fixture.php',
	WPMU_PLUGIN_DIR . '/sfcart-test-fixture.php'
);

update_option( 'permalink_structure', '/%postname%/' );
delete_transient( '_wc_activation_redirect' );
update_option(
	'woocommerce_onboarding_profile',
	array(
		'completed' => true,
		'skipped'   => true,
	),
	false
);
update_option( 'woocommerce_task_list_hidden', 'yes', false );

$simple_id = wc_get_product_id_by_sku( 'sfcart-batch2-simple' );
if ( 0 === $simple_id ) {
	$simple = new WC_Product_Simple();
	$simple->set_name( 'Batch 2 Simple Product' );
	$simple->set_slug( 'batch-2-simple-product' );
	$simple->set_sku( 'sfcart-batch2-simple' );
	$simple->set_status( 'publish' );
	$simple->set_regular_price( '20' );
	$simple->set_sale_price( '15' );
	$simple->set_manage_stock( true );
	$simple->set_stock_quantity( 3 );
	$simple->set_stock_status( 'instock' );
	$simple->set_tax_status( 'none' );
	$simple->set_virtual( false );
	$simple->set_weight( '1' );
	$simple_id = $simple->save();
}

$recommendation_id = wc_get_product_id_by_sku( 'sfcart-batch4-recommendation' );
if ( 0 === $recommendation_id ) {
	$recommendation = new WC_Product_Simple();
	$recommendation->set_name( 'Batch 4 Simple Recommendation' );
	$recommendation->set_slug( 'batch-4-simple-recommendation' );
	$recommendation->set_sku( 'sfcart-batch4-recommendation' );
	$recommendation->set_status( 'publish' );
	$recommendation->set_regular_price( '9' );
	$recommendation->set_stock_status( 'instock' );
	$recommendation->set_tax_status( 'none' );
	$recommendation->set_virtual( true );
	$recommendation_id = $recommendation->save();
}

$special_addon_id = wc_get_product_id_by_sku( 'sfcart-batch6-special-addon' );
if ( 0 === $special_addon_id ) {
	$special_addon = new WC_Product_Simple();
	$special_addon->set_name( 'Batch 6 Special Add-on' );
	$special_addon->set_slug( 'batch-6-special-addon' );
	$special_addon->set_sku( 'sfcart-batch6-special-addon' );
	$special_addon->set_status( 'publish' );
	$special_addon->set_regular_price( '4' );
	$special_addon->set_stock_status( 'instock' );
	$special_addon->set_tax_status( 'none' );
	$special_addon->set_virtual( true );
	$special_addon_id = $special_addon->save();
}

$sold_id = wc_get_product_id_by_sku( 'sfcart-batch2-sold' );
if ( 0 === $sold_id ) {
	$sold = new WC_Product_Simple();
	$sold->set_name( 'Batch 2 Sold Individually' );
	$sold->set_slug( 'batch-2-sold-individually' );
	$sold->set_sku( 'sfcart-batch2-sold' );
	$sold->set_status( 'publish' );
	$sold->set_regular_price( '12' );
	$sold->set_sold_individually( true );
	$sold->set_stock_status( 'instock' );
	$sold->set_tax_status( 'none' );
	$sold->set_virtual( false );
	$sold_id = $sold->save();
}

$variable_id = wc_get_product_id_by_sku( 'sfcart-batch2-variable' );
if ( 0 === $variable_id ) {
	$variable = new WC_Product_Variable();
	$variable->set_name( 'Batch 2 Variable Product' );
	$variable->set_slug( 'batch-2-variable-product' );
	$variable->set_sku( 'sfcart-batch2-variable' );
	$variable->set_status( 'publish' );
	$variable->set_tax_status( 'none' );

	$size = new WC_Product_Attribute();
	$size->set_id( 0 );
	$size->set_name( 'Size' );
	$size->set_options( array( 'Small', 'Large' ) );
	$size->set_position( 0 );
	$size->set_visible( true );
	$size->set_variation( true );
	$variable->set_attributes( array( $size ) );
	$variable_id = $variable->save();

	foreach ( array( 'Small' => '25', 'Large' => '30' ) as $option => $price ) {
		$variation = new WC_Product_Variation();
		$variation->set_parent_id( $variable_id );
		$variation->set_attributes( array( 'size' => $option ) );
		$variation->set_regular_price( $price );
		$variation->set_status( 'publish' );
		$variation->set_stock_status( 'instock' );
		$variation->set_tax_status( 'none' );
		$variation->save();
	}

	WC_Product_Variable::sync( $variable_id );
}

update_option( 'sfcart_test_simple_product_id', $simple_id, false );
update_option( 'sfcart_test_sold_product_id', $sold_id, false );
update_option( 'sfcart_test_variable_product_id', $variable_id, false );
update_option( 'sfcart_test_recommendation_product_id', $recommendation_id, false );
update_option( 'sfcart_test_special_addon_product_id', $special_addon_id, false );

$simple = wc_get_product( $simple_id );
if ( $simple instanceof WC_Product ) {
	$simple->set_upsell_ids( array( $recommendation_id, $variable_id ) );
	$simple->set_cross_sell_ids( array( $sold_id ) );
	$simple->save();
}

$sfcart_settings = \Starfiniti\Cart\Settings::get();
$sfcart_settings['upsells'] = array_merge(
	$sfcart_settings['upsells'],
	array(
		'enabled'              => true,
		'layout'               => 'style1',
		'heading'              => 'Complete your cart',
		'mode'                 => 'both',
		'display_limit'        => 5,
		'default_product_ids'  => array(),
		'excluded_product_ids' => array(),
	)
);
\Starfiniti\Cart\Settings::update( $sfcart_settings );

if ( 0 === wc_get_coupon_id_by_code( 'SAVE5' ) ) {
	$coupon = new WC_Coupon();
	$coupon->set_code( 'SAVE5' );
	$coupon->set_discount_type( 'fixed_cart' );
	$coupon->set_amount( '5' );
	$coupon->set_status( 'publish' );
	$coupon->save();
}

$reward_coupon_id = wc_get_coupon_id_by_code( 'REWARD5' );
if ( 0 === $reward_coupon_id ) {
	$reward_coupon = new WC_Coupon();
	$reward_coupon->set_code( 'REWARD5' );
	$reward_coupon->set_discount_type( 'fixed_cart' );
	$reward_coupon->set_amount( '5' );
	$reward_coupon->set_status( 'publish' );
	$reward_coupon_id = $reward_coupon->save();
}
update_option( 'sfcart_test_reward_coupon_id', $reward_coupon_id, false );

$menu = wp_get_nav_menu_object( 'sfcart-test' );
$menu_id = $menu instanceof WP_Term ? $menu->term_id : wp_create_nav_menu( 'sfcart-test' );
if ( is_int( $menu_id ) && 0 === count( wp_get_nav_menu_items( $menu_id ) ?: array() ) ) {
	wp_update_nav_menu_item(
		$menu_id,
		0,
		array(
			'menu-item-status' => 'publish',
			'menu-item-title'  => 'Menu cart',
			'menu-item-type'   => 'custom',
			'menu-item-url'    => '#starfiniti-cart',
		)
	);
}

$classic_page = get_page_by_path( 'sfcart-classic' );
if ( ! $classic_page instanceof WP_Post ) {
	$classic_page_id = wp_insert_post(
		array(
			'post_content' => sprintf(
				'<h1>Batch 2 Classic Cart Test</h1>[sfcart_test_menu][starfiniti_cart label="Inline cart"][products ids="%d,%d,%d" columns="3" orderby="include"]',
				$simple_id,
				$sold_id,
				$variable_id
			),
			'post_name'    => 'sfcart-classic',
			'post_status'  => 'publish',
			'post_title'   => 'Starfiniti Cart Classic Test',
			'post_type'    => 'page',
		)
	);
} else {
	$classic_page_id = $classic_page->ID;
}

$blocks_page = get_page_by_path( 'sfcart-blocks' );
if ( ! $blocks_page instanceof WP_Post ) {
	wp_insert_post(
		array(
			'post_content' => '<h1>Batch 2 Blocks Cart Test</h1><!-- wp:starfiniti/cart-toggle {"label":"Block cart"} /--><!-- wp:woocommerce/all-products {"columns":3,"rows":1,"alignButtons":true} /-->',
			'post_name'    => 'sfcart-blocks',
			'post_status'  => 'publish',
			'post_title'   => 'Starfiniti Cart Blocks Test',
			'post_type'    => 'page',
		)
	);
}

update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $classic_page_id );
flush_rewrite_rules();
