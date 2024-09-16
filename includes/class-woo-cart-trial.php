<?php

if (!defined('ABSPATH')) {
    exit;
}

class WooCartTrial
{
    /**
     * Add cart fee for sign-up
     */
    public static function add_cart_fee_for_signup($cart)
    {
        // Check WooCommerce session
        if (!WC()->session) {
            WC()->session = new WC_Session_Handler();
            WC()->session->init();
        }

        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        $add_a_product = WC()->session->get('add_a_product');

        if ($add_a_product === 'yes' && 'none' == WC_Subscriptions_Cart::get_calculation_type()) {
            $cart->add_fee(__('Trial Fee', 'woo-cart-oop'), 1.00);
        }
    }

    /**
     * Display trial checkbox on checkout
     */
    public static function add_trial_checkbox_to_checkout()
    {
        $value = sanitize_text_field(WC()->session->get('add_a_product'));

        woocommerce_form_field('cb_add_product', array(
            'type' => 'checkbox',
            'label' => '&nbsp;&nbsp;' . esc_html__('An additional $1 will be added to the trial fee.', 'woo-cart-oop'),
            'class' => array('form-row-wide'),
        ), $value === 'yes');
    }

    /**
     * Load custom jQuery script for checkout
     */
    public static function checkout_custom_jquery_script()
    {
        if (is_checkout() && !is_wc_endpoint_url()) {
            wp_localize_script('custom-checkout-js', 'wc_checkout_params', array(
                'ajax_url' => admin_url('admin-ajax.php')
            ));
            ?>
            <script>
                jQuery(function ($) {
                    if (typeof wc_checkout_params === "undefined") {
                        return false;
                    }

                    $("form.checkout").on("change", "#cb_add_product", function () {
                        var value = $(this).prop("checked") === true ? "yes" : "no";
                        $.ajax({
                            type: "POST",
                            url: wc_checkout_params.ajax_url,
                            data: {
                                action: "add_a_product",
                                add_a_product: value,
                            },
                            success: function (result) {
                                $("body").trigger("update_checkout");
                                console.log(result);
                            },
                        });
                    });
                });
            </script>
            <?php
        }
    }

    /**
     * Handle AJAX request for adding product status to session
     */
    public static function checkout_ajax_add_a_product()
    {
        if (!WC()->session) {
            WC()->session = new WC_Session_Handler();
            WC()->session->init();
        }

        if (isset($_POST['add_a_product'])) {
            $product_status = sanitize_text_field($_POST['add_a_product']);
            WC()->session->set('add_a_product', $product_status);
            WC()->cart->calculate_totals();
            wp_send_json_success($product_status);
        } else {
            wp_send_json_error('No product status provided.');
        }
        wp_die();
    }
}
