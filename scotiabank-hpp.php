<?php
/**
 * Plugin Name: ScotiaBank HPP by Hexakode Agency
 * Plugin URI: https://hexakodeagency.com
 * Author Name: Kalem Mentore
 * Author URI: https://hexakodeagency.com
 * Description: This plugin allows for credit/debit card payments via ScotiaBank HPP.
 * Version: 0.1.0
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: scotia-hpp-woo
 * WC requires at least: 6.0
 * WC tested up to: 8.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) return;

add_action('before_woocommerce_init', function() {
    if ( class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil') ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

add_action('plugins_loaded', function() {
    if (class_exists('WC_Payment_Gateway')) {
        require_once plugin_dir_path(__FILE__) . 'includes/class-wc-gateway-scotiahpp.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-scotiahpp-blocks.php';
    }
}, 11);

add_filter('woocommerce_payment_gateways', function($gateways) {
    $gateways[] = 'WC_Gateway_ScotiaHPP';
    return $gateways;
});

add_action('woocommerce_blocks_loaded', function () {
    if (! class_exists('WC_ScotiaHPP_Blocks')) {
        require_once plugin_dir_path(__FILE__) . 'includes/class-scotiahpp-blocks.php';
    }

    add_action('woocommerce_blocks_payment_method_type_registration', function ($registry) {
        if (
            class_exists('\\Automattic\\WooCommerce\\Blocks\\Payments\\PaymentMethodRegistry') &&
            class_exists('\\Automattic\\WooCommerce\\Blocks\\Payments\\Integrations\\AbstractPaymentMethodType')
        ) {
            $registry->register(new WC_ScotiaHPP_Blocks());
        }
    });
});

// Register custom rewrite rule
add_action('init', function () {
    add_rewrite_rule('^scotiahpp-redirect/([0-9]+)/?', 'index.php?scotiahpp_redirect=$matches[1]', 'top');
    add_rewrite_tag('%scotiahpp_redirect%', '([0-9]+)');
});

// Handle the redirect to the form
add_action('template_redirect', function () {
    $order_id = get_query_var('scotiahpp_redirect');
    if ($order_id) {
        $gateway = new WC_Gateway_Scotiahpp();
        $order = wc_get_order($order_id);
        echo $gateway->generate_scotiahpp_form($order);
        exit;
    }
});

add_action('woocommerce_admin_order_data_after_order_details', function($order) {
    $txn_id = $order->get_meta('_scotiahpp_oid');
    if ($txn_id) {
        echo '<p><strong>Scotia Order Id ID:</strong> ' . esc_html($txn_id) . '</p>';
    }
});