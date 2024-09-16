<?php
/**
 * Plugin Name: Woo Cart
 * Description: A custom WooCommerce cart plugin for trial product functionality.
 * Version: 1.0
 * Author: Tarikul Islam
 * Author URI: mailto:tarikul47@gmail.com
 * Text Domain: woo-cart-oop
 * License: GPL-2.0+
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define the main class for the plugin
if (!class_exists('WooCartOOP')) {

    class WooCartOOP
    {
        // Constructor
        public function __construct()
        {
            // Check if WooCommerce and WooCommerce Subscriptions are active
            add_action('admin_init', array($this, 'check_required_plugins'));

            // Hook into WordPress actions
            add_action('init', array($this, 'init_plugin'));
        }

        // Check if WooCommerce and WooCommerce Subscriptions are active
        public function check_required_plugins()
        {
            // Check if WooCommerce is active
            if (!class_exists('WooCommerce')) {
                add_action('admin_notices', array($this, 'admin_notice_woocommerce_required'));
                deactivate_plugins(plugin_basename(__FILE__)); // Deactivate the plugin
            }

            // Check if WooCommerce Subscriptions is active
            if (!class_exists('WC_Subscriptions')) {
                add_action('admin_notices', array($this, 'admin_notice_subscriptions_required'));
                deactivate_plugins(plugin_basename(__FILE__)); // Deactivate the plugin
            }
        }

        // Admin notice if WooCommerce is not installed
        public function admin_notice_woocommerce_required()
        {
            ?>
            <div class="error notice">
                <p><?php esc_html_e('Woo Cart requires WooCommerce to be installed and activated. The plugin has been deactivated.', 'woo-cart-oop'); ?>
                </p>
            </div>
            <?php
        }

        // Admin notice if WooCommerce Subscriptions is not installed
        public function admin_notice_subscriptions_required()
        {
            ?>
            <div class="error notice">
                <p><?php esc_html_e('Woo Cart requires WooCommerce Subscriptions to be installed and activated. The plugin has been deactivated.', 'woo-cart-oop'); ?>
                </p>
            </div>
            <?php
        }

        // Initialize plugin functionality
        public function init_plugin()
        {
            // 1. Remove default subscription price filters
            if (class_exists('WC_Subscriptions_Cart')) {
                remove_action('woocommerce_before_calculate_totals', array('WC_Subscriptions_Cart', 'add_calculation_price_filter'), 10);
                remove_action('woocommerce_calculate_totals', array('WC_Subscriptions_Cart', 'remove_calculation_price_filter'), 10);
            }

            // 2. Add custom price calculation filters
            add_filter('woocommerce_product_get_price', [$this, 'custom_set_subscription_prices_for_calculation'], 100, 2);
            add_filter('woocommerce_product_variation_get_price', [$this, 'custom_set_subscription_prices_for_calculation'], 100, 2);

            // 3. Remove custom calculation filters after totals are calculated
            add_action('woocommerce_calculate_totals', [$this, 'custom_remove_calculation_price_filter'], 10);


            // All required file loading here 
            $this->load_dependencies();

            // Checkbox added and Ajax Processing 
            $this->add_actions();
        }

        // Load required files and classes
        private function load_dependencies()
        {
            // For example, include any necessary class files here
            require_once plugin_dir_path(__FILE__) . 'includes/class-woo-cart-trial.php';
        }

        // Custom price calculation logic
        function custom_set_subscription_prices_for_calculation($price, $product)
        {
            // Check if this is the shop or cart page or another page
            if (is_product() || is_shop()) {
                return $price; // Return the price as is for shop and cart pages
            }

            if (WC_Subscriptions_Product::is_subscription($product)) {
                if ('none' === WC_Subscriptions_Cart::get_calculation_type()) {
                    $add_a_product = WC()->session->get('add_a_product');
                    $sign_up_fee = WC_Subscriptions_Product::get_sign_up_fee($product);
                    $sign_up_fee = is_numeric($sign_up_fee) ? (float) $sign_up_fee : 0;

                    error_log(print_r($add_a_product === 'no', true));

                    if ($add_a_product === 'no' || empty($add_a_product)) {
                        $price = (float) $price + $sign_up_fee;
                    } elseif ($add_a_product === 'yes') {
                        $price = (float) $sign_up_fee;
                    }
                } elseif ('recurring_total' === WC_Subscriptions_Cart::get_calculation_type()) {
                    $price = 0;
                }
            }
            return $price;
        }

        // Remove custom calculation price filter
        function custom_remove_calculation_price_filter()
        {
            remove_filter('woocommerce_product_get_price', [$this, 'custom_set_subscription_prices_for_calculation'], 100);
            remove_filter('woocommerce_product_variation_get_price', [$this, 'custom_set_subscription_prices_for_calculation'], 100);
        }

        // Register actions and filters
        private function add_actions()
        {
            add_action('woocommerce_cart_calculate_fees', array('WooCartTrial', 'add_cart_fee_for_signup'), 10);
            add_action('woocommerce_checkout_before_terms_and_conditions', array('WooCartTrial', 'add_trial_checkbox_to_checkout'), 10);
            add_action('wp_footer', array('WooCartTrial', 'checkout_custom_jquery_script'));
            add_action('wp_ajax_add_a_product', array('WooCartTrial', 'checkout_ajax_add_a_product'));
            add_action('wp_ajax_nopriv_add_a_product', array('WooCartTrial', 'checkout_ajax_add_a_product'));
        }

    }

    // Initialize the plugin
    new WooCartOOP();
}
