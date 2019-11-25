<?php

/**
 * Plugin Name: 	Extra Add-on for WC Points and Rewards
 * Plugin URI: 		https://github.com/huraji/extra-for-points-rewards
 * Description: 	An add to add extra features to WC Points and Rewards
 * Author: 			huraji
 * Author URI: 		https://github.com/huraji/
 * Version: 		1.1
 * Text Domain: 	extra-points-rewards
 * Domain Path: 	/languages/
 * WC requires at least: 3.2.0
 * WC tested up to: 3.5.0
 *
 * @package		Extra-Receiptful
 * @author		huraji
 * @license		http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

if ( ! defined('ABSPATH') ) exit; // Exit if accessed directly

/**
 * Check if WooCommerce is active
 */
if ( ! in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins') ) ) ) {
    return;
}


/**
 * Class WC_Points_Rewards_Handler.
 *
 * Main class initializes the plugin.
 *
 * @class		WC_Points_Rewards_Handler
 * @version		1.0.0
 * @author		huraji
 */
class WC_Points_Rewards_Handler {

    /**
     * Instance of WC_Points_Rewards_Handler
     *
     * @since 1.0.0
     * @access protected
     * @var object $instance the instance of WC_Points_Rewards_Handler.
     */
    protected static $instance;

    /**
     * Conversio API
     *
     * @var
     */
    public $conversio_api;

    /**
     * Constructor.
     *
     * Initialize the class and plugin.
     *
     * @since 1.0.0
     */
    function __construct () {

        /** Wait for WC_Points_Rewards  */
        add_action( 'plugins_loaded', array( $this, 'init'), 21 );
    }

    /**
     * Instance.
     *
     * An global instance of the class. Used to retrieve the instance
     * to use on other files/plugins/themes.
     *
     * @since 1.0.0
     *
     * @return WC_Points_Rewards_Handler Instance of the class.
     */
    public static function instance() 
    {

		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;

    }

    /**
     * init.
     *
     * Initialize plugin parts.
     *
     * @since 1.0.0
     */
    public function init()
    {
        // Check if WooCommerce Points Rewards is active
        require_once(ABSPATH . '/wp-admin/includes/plugin.php');
        if ( !is_plugin_active('woocommerce-points-and-rewards/woocommerce-points-and-rewards.php') && !class_exists('WC_Points_Rewards') ) {
            deactivate_plugins( plugin_basename(__FILE__) );
            add_action('admin_notices', array( $this, 'points_required_notice' ) );
            return false;
        }
        $this->points_requires();
        $this->hooks();
        $this->load_textdomain();
    }

    /**
     * Initial plugin hooks.
     *
     * @since 1.1
     */
    public function hooks()
    {
        global $wc_points_rewards;
        
        /**
         * Removing actions
         */
        remove_action( 'woocommerce_before_add_to_cart_button', array( $wc_points_rewards->product, 'render_product_message' ), 15 );
        remove_action( 'woocommerce_before_cart', array( $wc_points_rewards->cart, 'render_earn_points_message' ), 15 );
		remove_action( 'woocommerce_before_cart', array( $wc_points_rewards->cart, 'render_redeem_points_message' ), 16 );
		remove_action( 'woocommerce_before_checkout_form', array( $wc_points_rewards->cart, 'render_earn_points_message' ), 5 );
        remove_action( 'woocommerce_before_checkout_form', array( $wc_points_rewards->cart, 'render_redeem_points_message' ), 6 );

        //add_action( 'woocommerce_add_to_cart', array( $this, 'points_cart_operations' ), 10, 6);
        add_action( 'woocommerce_review_order_before_shipping', array( $this, 'points_render_cart_block' ), 10, 0 );
        add_action( 'woocommerce_cart_totals_before_shipping', array( $this, 'points_render_cart_block' ), 10, 0 );
        add_action( 'woocommerce_order_status_processing', array( $this, 'points_set_used_points' ) );
		add_action( 'woocommerce_order_status_completed', array( $this, 'points_set_used_points' ) );
		add_action( 'woocommerce_order_status_on-hold', array( $this, 'points_set_used_points' ) );
        add_action( 'woocommerce_checkout_order_processed', array( $this, 'points_log_used_earned_points' ) );
        
        /**
         * When user logs
         */
        add_action( 'wp_login', array( $this, 'points_wp_login' ), 10, 2 );

        /** 
         * Handle points events 
         */
        add_action( 'wc_points_rewards_after_set_points_balance', array( $this, 'points_email_notifications'), 10, 2 );
        add_action( 'wc_points_rewards_after_increase_points', array( $this, 'points_email_notifications'), 10, 3 );
        add_action( 'wc_points_rewards_after_reduce_points', array( $this, 'points_email_notifications'), 10, 3 );

        /** 
         * When user is deleted
         */
        add_action( 'delete_user', array( $this, 'points_delete_from_notifications' ) );
        
        /**
         * Filters
         */
        add_filter( 'woocommerce_calculated_total', array( $this, 'points_after_calculate_totals' ), 10, 2 );
        add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'points_add_to_cart_validation' ), 20, 3 );
        add_filter( 'woocommerce_product_get_price', array( $this, 'points_get_filtered_price' ), 10, 2 );
        add_filter( 'woocommerce_product_variation_get_price', array( $this, 'points_get_filtered_price' ), 10, 2 );
        add_filter( 'woocommerce_product_get_regular_price', array( $this, 'points_get_filtered_price' ), 10, 2 );
        add_filter( 'woocommerce_product_get_sale_price', array( $this, 'points_get_filtered_price' ), 10, 2 );
        add_filter( 'woocommerce_loop_add_to_cart_link' , array( $this, 'points_add_to_cart_button' ), 10, 2);
        add_filter( 'wc_points_rewards_settings', array( $this, 'points_add_extra_settings' ), 20, 1 );
        add_filter( 'bones_wc_exclude_query_cats', array( $this, 'points_exclude_query_cats' ), 20, 1 );
        add_filter( 'bones_no_products_found', array( $this, 'points_no_products_found' ), 10, 2 );
        add_filter( 'woocommerce_cart_item_quantity', array( $this, 'points_change_quantity_validation' ), 10, 3);
        add_filter( 'woocommerce_order_subtotal_to_display', array( $this, 'points_order_subtotal_to_display' ), 10, 3 );
        add_filter( 'woocommerce_order_formatted_line_subtotal', array( $this, 'points_order_formatted_line_subtotal' ), 10, 3 );
        add_filter( 'woocommerce_points_earned_for_order_item', array( $this, 'points_earned_for_order_item' ), 10, 5 );
        add_filter( 'wc_points_rewards_my_account_points_events', array( $this, 'points_rewards_my_account_points_events' ), 10, 1);
        add_filter( 'woocommerce_currencies', array( $this, 'points_currencies' ), 10, 1 );
        add_filter( 'woocommerce_currency_symbol', array( $this, 'points_currency_symbol' ), 10, 2);
        add_filter( 'woocommerce_currency', array( $this, 'points_currency' ), 10, 1 );
        add_filter( 'pre_option_woocommerce_currency_pos', array( $this, 'points_currency_position') );
        add_filter( 'woocommerce_get_price_html', array( $this, 'points_get_price_html' ), 10, 2 );
        
        /**
         * Conversio filters
         */
        add_filter( 'receiptful_cart_items_args', array( $this, 'points_cart_items_args' ), 10, 1 );
        add_filter( 'receiptful_api_args_order_args', array( $this, 'points_api_args_order_args' ), 10, 5 );
        /**
         * Custom Filters
         */
        add_filter( 'bones_exclude_search_terms', array( $this, 'points_exclude_search_terms' ), 10, 1 );
        
        /**
         * Apply filter on WC Reports
         *
         * @return array
         */
        //add_filter( 'woocommerce_reports_get_order_report_data', array($this, 'points_reports_get_order_report_data'), 99, 2 );
    }

    /**
     * Textdomain.
     *
     * Load the textdomain based on WP language.
     *
     * @since 1.1
     */
    public function load_textdomain()
    {
        load_plugin_textdomain('extra-points-rewards', false, plugin_dir_path( __FILE__ ) . 'languages');
    }

    /**
	 * Show WC version requirement notice.
	 *
	 * @since 1.4.0
	 */
	public function points_required_notice() {
		?><div class="notice notice-error is-dismissible">
			<p><?php _e('WooCommerce Points and Rewards is not activated', 'extra-points-rewards' ); ?></p>
		</div><?php
	}

    /**
     * Requires
     *
     * @return void
     */
    public function points_requires()
    {
        if ( class_exists( 'Conversio_WooCommerce' ) ) {
            require_once( plugin_dir_path( __FILE__ ) . 'receiptful-api.php' );
            $this->conversio_api = new Ext_Conversio_Api();
        }
    }

    /**
     * Filter WC Reports
     * 
     * @return array $data
     */
    public function points_reports_get_order_report_data($query, $data)
    {
        error_log('hello');
        error_log('ciao');
        return $query;
    }
    /**
     * Add backwards compatibility for subscribers without points
     */
    public function points_wp_login( $user_login, $user )
    {

        if( $user->has_cap( 'administrator' ) ) {
            return;
        }

        /**
         * Convert subscribers into customers
         */
        if( !$user->has_cap( 'customer' ) ) {   
            $user->add_role( 'customer' );
        }
        
        /**
         * Update customers
         */
        if( empty( $this->points_balance( $user->ID ) ) || $this->points_balance( $user->ID ) === 0 ) {
            $points = get_option('wc_points_rewards_account_signup_points');
            if ( !empty( $points ) ) {
                WC_Points_Rewards_Manager::increase_points( $user->ID, $points, 'account-signup' );
            }

        }

    }

    /**
     * Hook into order data sent to Conversio
     *
     * @param array $order_args
     * @param int|WC_Order $order
     * @param array $items
     * @param array $subtotals
     * @param array $related_products
     * @return array
     */
    public function points_api_args_order_args( $order_args, $order, $items, $subtotals, $related_products )
    {
        foreach ( $items as $index => $data ) {
            if( $this->points_is_rewarding_product( $data['reference'] ) ) {
                $items[$index]['amount'] = 0;
                foreach ( $subtotals as $key => $value ) {
                    if( $value['description'] === 'Subtotal') {
                        $subtotals[$key]['amount'] -= $data['amount'];
                    }
                }
            }
        }
        $order_args['items'] = $items;
        $order_args['subtotals'] = $subtotals;
        return $order_args;
    }

    /**
     * Before sending cart items to Conversio Abandoned Cart
     *
     * @param object $cart_items_args
     * @return object $cart_items_args
     */
    public function points_cart_items_args( $cart_items_args )
    {
        foreach ( $cart_items_args as $key => $value ) {
            if( $this->points_is_rewarding_product( $value['reference'] ) ) {
                $cart_items_args[$key]['amount'] = 0;
            }
        }
        return $cart_items_args;
    }

    /**
     * Filter product price
     *
     * @param mixed $price
     * @param object $product
     * @return mixed
     */
    public function points_get_price_html( $price, $product )
    {
        if( $this->points_is_rewarding_product( $product ) ) {
            return sprintf( __( '%d %s', 'extra-points-rewards' ), $this->points_to_purchase( $product ), $this->points_label()  );
        }
        if( !has_term( 'bundles', 'product_cat', $product ) ) {
            $link = is_user_logged_in() ? get_permalink( wc_get_page_id( 'myaccount' ) ) : get_permalink(wc_get_page_id( 'myaccount' )) . '?redirect_to=' . get_permalink();
            return sprintf( __( '%s %s', 'extra-points-rewards' ), $price, '<span class="woocommerce-Points-amount"><span class="woocommerce-Points-amount_label"><a href="' . $link . '">' . $this->points_earned_label() . ' ' . $this->points_to_earn( $product ) . ' ' . $this->points_label() . ' ' ) . '</a></span></span>';
        }
        return $price;
    }

    /**
     * Points set currency position
     * @return mixed String position on truthy otherwise false
     */
    public function points_currency_position()
    {
        if( has_term( $this->points_get_rewarding_category(), 'product_cat' ) ) {
            return 'right';
        }
        return false;
    }

    /**
     * Points set currency
     *
     * @param mixed $currency
     * @return mixed filtered currency code
     */
    public function points_currency( $currency )
    {
        if( has_term( $this->points_get_rewarding_category(), 'product_cat' ) ) {
            $currency = 'Points';
        }
        return $currency;
    }

    /**
     * Set Currency Symbol
     *
     * @param mixed $currency_symbol
     * @param mixed $currency
     * @return mixed new custom currency symbol
     */
    public function points_currency_symbol( $currency_symbol, $currency )
    {
        switch ( $currency ) {
            case 'Points':
                $currency_symbol = $this->points_label();
                break;
        }
        return $currency_symbol;
    }

    /**
     * Insert Custom Currency for Points
     *
     * @param array $currencies
     * @return array containing all currencies
     */
    public function points_currencies( $currencies )
    {
        $currencies['Points'] = __('Points and rewards currency', 'woocommerce');
        return $currencies;
    }

    /**
     * Set nr of logs to displau in my account
     * 
     * @param int $posts_per_page 
     * @return int Number of posts per page to display
     */
    public function points_rewards_my_account_points_events( $posts_per_page )
    {
        $posts_per_page = '';
        return $posts_per_page;
    }

    /**
     * Exclude Search term category
     *
     * @param array $terms
     * @return array
     */
    public function points_exclude_search_terms( $terms )
    {
        $terms[] .= $this->points_get_rewarding_category();
        return $terms;
    }

    /**
     * Format single Order Line
     *
     * @param mixed $subtotal
     * @param mixed $item
     * @param object $order
     * @return mixed
     */
    public function points_order_formatted_line_subtotal( $subtotal, $item, $order )
    {
        $item_data = $item->get_data();
        if ( $this->points_is_rewarding_product( $item_data['product_id'] ) ) {
            $subtotal = '<span class="woocommerce-Price-amount amount">' . $item_data['total'] . ' ' . $this->points_label( $item_data['total'] ) . '</span>';
        }
        return $subtotal;
    }

    /**
     * Filter order item totals
     *
     * @param mixed $subtotal
     * @param void $compound
     * @param object $order
     * @return mixed
     */
    public function points_order_subtotal_to_display( $subtotal, $compound, $order )
    {
        $new_subtotal = 0;
        foreach ( $order->get_items() as $line_id => $line_data ) {
            if ( !$this->points_is_rewarding_product( $line_data['product_id'] ) ) {
                $new_subtotal += $line_data['total'] + $line_data['total_tax'];
            }
        }
        return '<span class="woocommerce-Price-amount amount">' . get_woocommerce_currency_symbol() . number_format( (float)$new_subtotal, 2, ',', '' ) . '</span>';
    }

    /**
     * Delete users from notifications 
     *
     * @param int $user_id
     * @return void
     */
    public function points_delete_from_notifications( $user_id )
    {
        if( empty( $this->conversio_api ) ) {
            return;
        }

        $user = get_userdata( $user_id );
        $lists_ids = $this->points_get_conversio_lists_ids();
        
        $args = array(
            'emails' => array( $user->user_email )
        );

        if( false !== $lists_ids ) {
            foreach( $lists_ids as $id ) {
                $this->conversio_api->delete_customer( $id, $args );
            }
        }

    }

    /**
     * Add points email notifications 
     *
     * @param int $user_id
     * @param int $points_balance
     * @return array|WP_Error $email_classes
     */
    public function points_email_notifications( $user_id, $points, $event_type = 'points-balance' )
    {
        if( empty( $this->conversio_api ) ) {
            return;
        }

        if( $points === 1 ) {
            return;
        }

        //set parameters
        $user = get_userdata( $user_id );
        $lists_ids = $this->points_get_conversio_lists_ids();
        $args = array(
            'email' => $user->user_email,
            'source' => get_bloginfo('url'),
            'properties' => array(
                'points_balance' => $points,
                'points_label' => $this->points_label( $points ),
                'points_expiration' => $this->points_expiration(),
                'event_type' => $event_type
            )
        );
        foreach( $lists_ids as $id ) {
            $this->conversio_api->update_customer_list( $id, $args );
        }
    }

    /**
     * Get points expiration date
     *
     * @return mixed timestamp of the expiration date or lifetime
     */
    public function points_expiration()
    {
        return !empty( wp_next_scheduled('wc_points_rewards_expire_points') ) ? wp_next_scheduled('wc_points_rewards_expire_points') : __( 'Lifetime', 'extra-points-rewards' );
    }

    /**
     * Set used poinrt
     *
     * @param int $order_id
     * @return void
     */
    public function points_set_used_points( $order_id )
    {
        $already_redeemed  = get_post_meta( $order_id, '_wc_points_redeemed', true );
        $logged_redemption = get_post_meta( $order_id, '_wc_points_logged_redemption', true );
        if (!empty($already_redeemed)) {
            return;
        }
        $order = wc_get_order($order_id);
        WC_Points_Rewards_Manager::decrease_points( $order->get_user_id(), $logged_redemption['points'], 'order-redeem', array('discount_code' => '', 'discount_amount' => 0), $order_id );
        update_post_meta( $order_id, '_wc_points_redeemed',  $logged_redemption['points'] );
    }

    /**
     * Function log used points
     *
     * @param int $order_id
     * @return void
     */
    public function points_log_used_earned_points( $order_id )
    {
        $points = $this->points_used_earned_in_cart();
        update_post_meta( $order_id, '_wc_points_logged_redemption', array( 'points' => $points['used'], 'amount' => '', 'discount_code' => '' ) );
        
        /**
         * Add meta to store general points informations about the order
         */
        update_post_meta( $order_id, '_wc_points_used_earned', $points);
    }

    /**
     * Return points earned per order
     *  
     * @param object $order
     * @return int
     */
    public function points_get_earned_per_order( $order_id )
    {
        return get_post_meta( $order_id, '_wc_points_earned', true ) ? get_post_meta( $order_id, '_wc_points_earned', true ) : __('None', 'extra-points-rewards');
    }

    /**
     * Adjust point to exclude club category
     *
     * @param int $points
     * @param int $user_id
     * @param string $event_type
     * @param int $order_id
     * @param mixed $data
     * @return int $points
     */
    public function points_earned_for_order_item( $points, $product, $item_key, $item, $order )
    {
        if( $this->points_is_rewarding_product( $product ) ) {
            $points = 0;
        }
        return $points;
    }

    /**
     * Check for quantity in cart
     *
     * @param string $product_quantity
     * @param $cartkey $cart_item_key
     * @param $cart $cart_item
     * @return $product_quantity
     */
    public function points_change_quantity_validation( $product_quantity, $cart_item_key, $cart_item )
    {
        if ( $this->points_is_rewarding_product( $cart_item['product_id'] ) ) {
            $points = $this->points_used_earned_in_cart();
            $balance = $this->points_balance();
            $future_balance = $points['used'];
            if ( false !== $balance - $future_balance < 0 ) {
                $product_quantity = woocommerce_quantity_input(array(
                    'input_name'  => "cart[{$cart_item_key}][qty]",
                    'input_value' => $cart_item['quantity'] - 1,
                    'max_value'   => $cart_item['data']->backorders_allowed() ? '' : $cart_item['data']->get_stock_quantity(),
                    'min_value'   => '0'
                ), $cart_item['data'], false);
                WC()->cart->set_quantity($cart_item_key, $cart_item['quantity'] - 1, true);
                printf(__('<div style="color:red">Not enough %s</div>', 'extra-points-rewards'), strtolower($this->points_label()));
            }
        }
        return $product_quantity;
    }

    /**
     * Check if club products can be added
     *
     * @param boolean $passed
     * @param int $product_id
     * @param int $quantity
     * @return boolean
     */
    public function points_add_to_cart_validation( $passed, $product_id, $quantity )
    {
        if( $this->points_is_rewarding_product( $product_id ) ) {
            $product = wc_get_product( $product_id );
            $points = $this->points_used_earned_in_cart();
            $balance = $this->points_balance();
            $future_balance = $product->get_price() + $points['used'];
            if( $balance - $future_balance < 0 ) {
                wc_add_notice(sprintf(__('You haven\'t enough %s', 'extra-points-rewards'), strtolower($this->points_label())), 'error');
                $passed = false;
            }
        }
        return $passed;
    }

    /**
     * Calculate totals without club products
     *  
     * @param object $cart_object
     * @return void
     */
    public function points_after_calculate_totals( $total, $cart )
    {
        $points = $this->points_used_earned_in_cart();
        return $total - $points['used'];
    }

    /**
     * Render cart block for points
     *
     * @param Type $var
     * @return void
     */
    public function points_render_cart_block()
    {
        require dirname( __FILE__ ) . '/includes/cart-points.php';
    }

    /**
     * Caculate number of points used / earned in cart
     *
     * @return array used|earned
     */
    public function points_used_earned_in_cart()
    {
        $points = array(
            'used' => 0,
            'earned' => 0
        );

        $coupon_applied = WC()->cart->get_coupon_discount_totals();

        foreach ( $coupon_applied as $code => $amount ) {
            $points['earned'] -= ceil(WC_Points_Rewards_Manager::calculate_points($amount + WC()->cart->get_coupon_discount_tax_amount( $code )));
        }

        foreach ( WC()->cart->get_cart() as $key => $values ) {
            if ( $this->points_is_rewarding_product( $values['data']->get_id() ) ) {
                $points['used'] += $values['data']->get_price() * $values['quantity'];
            } else {
                $points['earned'] += ceil(WC_Points_Rewards_Manager::calculate_points($values['data']->get_price() * $values['quantity']));
            }
        }
        return $points;
    }

    /**
     * Get points used earned in order
     *
     * @param int $order_id
     * @return mixed
     */
    public function points_user_earned_in_order( $order_id )
    {
        $login_text = get_option( 'wc_points_rewards_cart_login_text' );
        $points = !is_user_logged_in() ? array( 'used' => $login_text, 'earned' => $login_text ) : get_post_meta( $order_id, '_wc_points_used_earned', true );
        return $points;
    }

    /**
     * Get label to identiy club product cards
     *
     * @return mixed
     */
    public function points_get_club_product_label( $product )
    {
        return $this->points_is_rewarding_product( $product ) ? '<div class="product-grid-entry_club-wrapper"><div class="product-grid-entry_club-label">' . $this->points_get_club_program_name() . '</div></div>' : '';
    }

    /**
     * Customise string for product not found in Points and Rewards category
     *
     * @param string $string
     * @return string
     */
    public function points_no_products_found( $string, $term )
    {
        if( !is_user_logged_in() && $this->points_is_rewarding_category( $term ) ) {
            $string = sprintf(__( "<a href='%s'>Become a member to view these products.</a>", "extra-points-rewards" ), 
                esc_url(wc_get_account_endpoint_url('points-and-rewards'))
            );
        }
        return $string;
    }

    /**
     * Filter WooCommerce query in specific cases
     *
     * @return array
     */
    public function points_exclude_query_cats( $terms )
    {
        if( !is_user_logged_in() ) {
            $terms[] .= get_option( 'wc_points_rewards_product_cat' ) ? get_option('wc_points_rewards_product_cat') : 'club'; 
        }
        return $terms;
    }

    /**
     * Add extra options to admin dashboard
     *
     * @return array
     */
    public function points_add_extra_settings( $settings )
    {
        $extra = array(
            array(
                'title' => __('Other Settings', 'extra-points-rewards'),
                'type'  => 'title',
                'id'    => 'wc_points_rewards_points_extra_settings_start'
            ),
            array(
                'title'    => __('Main Club Program Name', 'extra-points-rewards'),
                'desc_tip' => __('General name to refer the club program name.', 'extra-points-rewards'),
                'id'       => 'wc_points_rewards_name',
                'default'  => false,
                'type'     => 'text'
            ),
            array(
                'title'    => __( 'Icon / Symbol', 'extra-points-rewards' ),
                'desc_tip' => __( 'Insert icon html code to be used as symbol. (Eg. <span class="my-icon"></span>).', 'extra-points-rewards' ),
                'id'       => 'wc_points_rewards_icon',
                'default'  => false,
                'type'     => 'textarea',
                'css'      => 'min-width: 400px;',
            ),
            array(
                'title'    => __('Add to cart text when redeemable', 'extra-points-rewards'),
                'desc_tip' => __('Text for add to cart button when users have points (Eg. Redeem Now)', 'extra-points-rewards'),
                'id'       => 'wc_points_rewards_add_to_cart_text',
                'default'  => false,
                'type'     => 'text'
            ),
            array(
                'title'    => __('Add to cart text when not redeemable', 'extra-points-rewards'),
                'desc_tip' => __('Text for add to cart button when users haven\'t points (Eg. Learn More / Earn more)', 'extra-points-rewards'),
                'id'       => 'wc_points_rewards_add_to_cart_text_info',
                'default'  => false,
                'type'     => 'text'
            ),
            array(
                'title'    => __('Add to cart link when not redeemable', 'extra-points-rewards'),
                'desc_tip' => __('Link for add to cart button when users haven\'t points', 'extra-points-rewards'),
                'id'       => 'wc_points_rewards_add_to_cart_link_info',
                'default'  => false,
                'type'     => 'text'
            ),
            array(
                'title'    => __('Label for points earned', 'extra-points-rewards'),
                'desc_tip' => __('Label for displaying earned points.', 'extra-points-rewards'),
                'id'       => 'wc_points_rewards_earned_label',
                'default'  => false,
                'type'     => 'text'
            ),
            array(
                'title'    => __('Label for points used', 'extra-points-rewards'),
                'desc_tip' => __('Label for displaying used points.', 'extra-points-rewards'),
                'id'       => 'wc_points_rewards_used_label',
                'default'  => false,
                'type'     => 'text'
            ),
            array(
                'title'    => __('WC Product Category Slug for club products', 'extra-points-rewards'),
                'desc_tip' => __('WC category slug containing products for ONLY memebers. Default: club.', 'extra-points-rewards'),
                'id'       => 'wc_points_rewards_product_cat',
                'default'  => 'club',
                'type'     => 'text'
            ),
            array(
                'title'    => __('WC Cart Login Text Button', 'extra-points-rewards'),
                'desc_tip' => __('WC login text visible in cart, in case of logged out. Default: Become Member.', 'extra-points-rewards'),
                'id'       => 'wc_points_rewards_login_text',
                'default'  => 'Become Member',
                'type'     => 'text'
            ),
            array(
                'title'    => __( 'Conversio Lists IDs', 'extra-points-rewards' ),
                'desc_tip' => __( 'Conversio Lists IDs to subscribe users', 'extra-points-rewards' ),
                'id'       => 'wc_points_rewards_conversio_lists_ids',
                'default'  => false,
                'type'     => 'text'
            ),
            array( 'type' => 'sectionend', 'id' => 'wc_points_rewards_points_extra_settings_end' ),
        );

        return array_merge( $settings, $extra );

    }

    /**
     * Return Conversio list id if present
     *
     * @return array $list_ids
     */
    public function points_get_conversio_lists_ids()
    {
        return explode(',', get_option('wc_points_rewards_conversio_lists_ids'));
    }

    /**
     * Function to get main club program name
     * 
     * @return mixed
     */
    public function points_get_club_program_name()
    {
        return get_option( 'wc_points_rewards_name' ) ? get_option( 'wc_points_rewards_name' ) : __('Club', 'extra-points-rewards');
    }

    /**
     * Function to customise add to cart button for points 
     * 
     * @return mixed
     */
    public function points_get_add_to_cart_text()
    {
        return get_option( 'wc_points_rewards_add_to_cart_text' ) ? get_option( 'wc_points_rewards_add_to_cart_text' ) : __('Redeem Now', 'extra-points-rewards');
    }

    /**
     * Function to customise add to cart button text when not enough points
     * 
     * @return mixed
     */
    public function points_get_add_to_cart_text_info()
    {
        return get_option('wc_points_rewards_add_to_cart_text_info') ? get_option('wc_points_rewards_add_to_cart_text_info') : __('Learn More', 'extra-points-rewards');
    }

    /**
     * Function to customise add to cart button link when not enough points
     * 
     * @return mixed
     */
    public function points_get_add_to_cart_link_info()
    {
        return get_option('wc_points_rewards_add_to_cart_link_info') ? get_option('wc_points_rewards_add_to_cart_link_info') : wc_get_account_endpoint_url( 'points-and-rewards' );
    }
    
    /**
     * Function to customise login text link / button in cart and checkout pages
     * 
     * @return mixed
     */
    public function points_get_cart_login_text()
    {
        return get_option( 'wc_points_rewards_login_text' ) ? get_option( 'wc_points_rewards_login_text' ) : __('Become Member', 'extra-points-rewards');
    }

    /**
     * Function to get label for earned products
     * 
     * @return mixed
     */
    public function points_earned_label()
    {
        return get_option( 'wc_points_rewards_earned_label' ) ? get_option('wc_points_rewards_earned_label' ) : __('Earned', 'extra-points-rewards');
    }

    /**
     * Function to get label for used products
     * 
     * @return mixed
     */
    public function points_used_label()
    {
        return get_option( 'wc_points_rewards_used_label' ) ? get_option('wc_points_rewards_used_label' ) : __('Used', 'extra-points-rewards');
    }

    /**
     * Get Points and rewards symbol / icon 
     * 
     * @return mixed
     */
    public function points_get_symbol() 
    {
        return get_option( 'wc_points_rewards_icon' ) ? trim(get_option( 'wc_points_rewards_icon' )) : '<span class="icon-club-bubbles"></span>&nbsp;';
    }   

    /**
     * Round club product price (=points) to the lower int
     * 
     *  @param float $price
     *  @param object $product
     *  @return int
     */
    public function points_get_filtered_price( $price, $product ) 
    { 
        if( !$this->points_is_rewarding_product( $product ) ) {
            return $price;
        }

        return floor( $price );
    }

    /**
     * Change Add to cart button in case of redeemable products
     *
     * @param string $original
     * @param object $product
     * @return string
     */
    public function points_add_to_cart_button( $original, $product ) 
    {
        if( !$this->points_is_rewarding_product( $product ) ) {
            return $original;
        }
        
        if( !is_user_logged_in() ) {
            return sprintf(
                '<a href="%s" rel="nofollow" class="button %s">%s</a>',
                esc_url(wc_get_account_endpoint_url('points-and-rewards')),
                'single_add_to_cart_button add_to_cart_button button alt go-button',
                esc_html(__("Become Member Now", "extra-points-rewards"))
            );
        }

        if( $this->points_can_redeem_product( $product ) ) {
            
            if( !is_product() ) {
                return sprintf(
                    '<a href="%s" rel="nofollow" data-product_id="%s" data-product_sku="%s" data-quantity="%s" class="button %s product_type_%s" data-action="show_cart" data-mode="side-mod_no-bottom" data-title="%s">%s %s</a>',
                    esc_url($product->add_to_cart_url()),
                    esc_attr($product->get_id()),
                    esc_attr($product->get_sku()),
                    esc_attr(isset($quantity) ? $quantity : 1),
                    $product->is_purchasable() && $product->is_in_stock() ? 'ajax_add_to_cart quick_add_to_cart_button add_to_cart_button' : 'no-add-to-cart',
                    esc_attr($product->get_type()),
                    esc_html(__("Shopping Cart", "extra-points-rewards")),
                    apply_filters('woocommerce_add_to_cart_icon', '<span class="add_to_cart_button_icon icon-add"></span>'),
                    apply_filters('woocommerce_add_html_price', $product)
                );
            }
            
            return sprintf(
                '<a href="%s" rel="nofollow" data-product_id="%s" data-product_sku="%s" data-quantity="%s" class="button %s product_type_%s" data-action="show_cart" data-mode="side-mod_no-bottom" data-title="%s">%s %s %s</a>',
                esc_url($product->add_to_cart_url()),
                esc_attr($product->get_id()),
                esc_attr($product->get_sku()),
                esc_attr(isset($quantity) ? $quantity : 1),
                $product->is_purchasable() && $product->is_in_stock() ? 'ajax_add_to_cart single_add_to_cart_button add_to_cart_button button alt' : '',
                esc_attr($product->get_type()),
                esc_html(__("Shopping Cart", "extra-points-rewards")),
                apply_filters('woocommerce_add_to_cart_icon', '<span class="add_to_cart_button_icon icon-add"></span>'),
                '<span class="add_to_cart_button_text">' . esc_html($this->points_get_add_to_cart_text()) . '</span>',
                apply_filters('woocommerce_add_html_price', $product)
            );
        }

        if(!is_product()) {
            return sprintf(
                '<a href="%s" rel="nofollow" class="button %s">%s %s</a>',
                esc_url( get_permalink( $product->get_id() ) ),
                'single_add_to_cart_button add_to_cart_button quick_add_to_cart_button button alt disabled',
                apply_filters('woocommerce_add_to_cart_icon', '<span class="add_to_cart_button_icon icon-add"></span>'),
                apply_filters('woocommerce_add_html_price', $product)
            );
        }

        return sprintf(
            '<a href="%s" rel="nofollow" class="button %s">%s %s %s</a>',
            esc_url( $this->points_get_add_to_cart_link_info() ),
            'single_add_to_cart_button add_to_cart_button button alt disabled',
            apply_filters('woocommerce_add_to_cart_icon', '<span class="add_to_cart_button_icon icon-add"></span>'),
            '<span class="add_to_cart_button_text">' . esc_html( $this->points_get_add_to_cart_text_info() ) . '</span>',
            apply_filters('woocommerce_add_html_price', $product )
        );
    }

    /**
     * Return rewarding category slug
     *
     * @return mixed
     */
    public function points_get_rewarding_category()
    {
        return get_option( 'wc_points_rewards_product_cat' );
    }

    /**
     * Check if is a rewarding category
     *
     * @param mixed $term
     * @return boolean
     */
    public function points_is_rewarding_category( $term )
    {
        return $term == $this->points_get_rewarding_category() ? true : false;
    }

    /**
     * Check if is a rewarding product
     *
     * @param object|int $product object or $product ID
     * @return boolean
     */
    public function points_is_rewarding_product( $product )
    {
        if( is_object( $product ) ) {
            $product = $product->get_id();
        }
        return has_term( get_option('wc_points_rewards_product_cat'), 'product_cat', $product );
    }

    /**
     * Get points label set in the admin
     *
     * @return string
     */
    public function points_label( $points = 2 )
    {
        global $wc_points_rewards;
        return $wc_points_rewards->get_points_label( $points );
    }

    /**
     * Get user points balance
     *
     * @return int 
     */
    public function points_balance( $user_id = '' ) 
    {
        $user_id = !empty( $user_id ) ? $user_id : get_current_user_id();
        return WC_Points_Rewards_Manager::get_users_points( $user_id );
    }

    /**
     * Get user points balance
     *
     * @return int 
     */
    public function points_balance_short()
    {
        $balance = $this->points_balance();
        return $balance >= 1000 ? '+' . round( $balance / 1000 ) . 'K' : $balance;
    }

    /**
     * Points earned for product purchase
     *
     * @param object $product
     * @return int
     */
    public function points_to_earn( $product ) 
    {
        global $wc_points_rewards;
        return ceil($wc_points_rewards->product->get_points_earned_for_product_purchase( $product ));
    }

    /**
     * Get points can be earned for a product purchase
     *
     * @param object $product
     * @return int
     */
    public function points_to_purchase( $product ) {
        return $product->get_price();
    }

    /**
     * Check if points are enough to redeem a prduct
     *
     * @param object $product
     * @return boolean
     */         
    public function points_can_redeem_product($product) {

        $points = $this->points_to_purchase( $product );
        $balance = $this->points_balance();
        return ( $balance - $points ) >= 0 ? true : false;
    }

    /**
     * Generate discount in Checkout if needed
     *
     * @return void
     */
    public function points_generate_discount()
    {
        $existing_discount = WC_Points_Rewards_Discount::get_discount_code();

        // bail if the discount has already been applied
        if (!empty($existing_discount) && WC()->cart->has_discount($existing_discount)) {
            return;
        }

        // Get discount amount if set and store in session
        // WC()->session->set( 'wc_points_rewards_discount_amount', ( ! empty( $_POST['wc_points_rewards_apply_discount_amount'] ) ? absint( $_POST['wc_points_rewards_apply_discount_amount'] ) : '' ) );

        // generate and set unique discount code
        $discount_code = WC_Points_Rewards_Discount::generate_discount_code();

        // apply the discount
        WC()->cart->add_discount($discount_code);
    }
}

if (!function_exists('Extra_Points')) {
    function Extra_Points()
    {
        $GLOBALS['wc_points_rewards_handler'] = WC_Points_Rewards_Handler::instance();
        return $GLOBALS['wc_points_rewards_handler'];
    }
}
Extra_Points();