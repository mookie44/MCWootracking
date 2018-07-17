<?php
/**
 * Plugin Name: MC Woocommerce Tracking
 * Plugin URI: 
 * Description: Order tracking code and courier fields 
 * Version: 1.0.0
 * Author: Matt Cook
 * Author URI: 
 * Tested up to: 4.9
 * WC requires at least: 2.6
 * WC tested up to: 3.4
 */

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

/**
 * Check if WooCommerce is active
 **/
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

	if ( ! class_exists( 'MCWootracking' ) ) :
		class MCWootracking {

			public function __construct(){
				add_action( 'admin_init', array($this, 'mc_register_plugin_settings' ));
				add_action( 'save_post',  array($this, 'mc_save_tracking' ), 10, 1 );
				add_action( 'woocommerce_view_order', array($this, 'mc_action_woocommerce_view_order'), 10, 1 ); 
				add_action( 'woocommerce_email_order_meta', array($this, 'mc_action_woocommerce_email_order_meta'), 10, 4 ); 
				// Set Plugin Path
				$this->pluginPath = dirname(__FILE__);
			}

			public function mc_register_plugin_settings() {
				//register our settings
				add_meta_box( 'mc_order_packaging', __('Order Tracking','woocommerce'), array($this,'mc_order_packaging'), 'shop_order', 'side', 'high' );
			}

			// add tracking input boxes to the admin order page
			public function mc_order_packaging(){
				global $post;

				$mc_tracking_code = get_post_meta( $post->ID, '_mc_tracking_code', true ) ? get_post_meta( $post->ID, '_mc_tracking_code', true ) : '';
				$mc_courier = get_post_meta( $post->ID, '_mc_courier', true ) ? get_post_meta( $post->ID, '_mc_courier', true ) : '';

				echo '<input type="hidden" name="mc_order_tracking_nonce" value="' . wp_create_nonce() . '">
				<p style="border-bottom:solid 1px #eee;padding-bottom:13px;">
				<label for="mc_tracking_code">Tracking code:</label>
				<input type="text" style="width:250px;" name="mc_tracking_code" placeholder="' . $mc_tracking_code . '" value="' . $mc_tracking_code . '">
				<label for="mc_courier">Courier:</label>
				<input type="text" style="width:250px;" name="mc_courier" placeholder="' . $mc_courier . '" value="' . $mc_courier . '">
				</p>';

			}

			public function mc_save_tracking( $post_id ) {
				// We need to verify this with the proper authorization (security stuff).

				// Check if our nonce is set.
				if ( ! isset( $_POST[ 'mc_order_tracking_nonce' ] ) ) {
					return $post_id;
				}
				$nonce = $_REQUEST[ 'mc_order_tracking_nonce' ];

				//Verify that the nonce is valid.
				if ( ! wp_verify_nonce( $nonce ) ) {
					return $post_id;
				}

				// If this is an autosave, our form has not been submitted, so we don't want to do anything.
				if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
					return $post_id;
				}

				// Check the user's permissions.
				if ( 'page' == $_POST[ 'post_type' ] ) {

					if ( ! current_user_can( 'edit_page', $post_id ) ) {
						return $post_id;
					}
				} else {

					if ( ! current_user_can( 'edit_post', $post_id ) ) {
						return $post_id;
					}
				}

				$order = wc_get_order( $post_id );
				if($order){
					// --- Its safe for us to save the data ! --- //
					$mc_tracking_code   = sanitize_text_field($_POST['mc_tracking_code']);
					$mc_courier     	= sanitize_text_field($_POST['mc_courier']);
					$order->update_meta_data( '_mc_tracking_code', $mc_tracking_code );
					$order->update_meta_data( '_mc_courier', $mc_courier );
					$order->save();
				}
			}

			// Add tracking info to myaccount order page
			public function mc_action_woocommerce_view_order($orderid) { 
				$order = wc_get_order( $orderid );
				$mc_tracking_code = $order->get_meta( '_mc_tracking_code');
				$mc_courier = $order->get_meta( '_mc_courier');
				if(!empty(trim($mc_tracking_code))){
					if (strpos($mc_courier, 'Royal Mail') !== false && !empty($mc_tracking_code)) {
						$link = "https://www.royalmail.com/track-your-item?trackNumber={$mc_tracking_code}";
					}elseif (strpos($mc_courier, 'DPD') !== false && !empty($mc_tracking_code)) {
						$link = "http://www.dpd.co.uk/service/tracking?consignment={$mc_tracking_code}";
					}elseif (strpos($mc_courier, 'UKmail') !== false && !empty($mc_tracking_code)) {
						$link = "https://www.ukmail.com/manage-my-delivery/manage-my-delivery";
					}elseif (strpos($mc_courier, 'DHL') !== false && !empty($mc_tracking_code)) {
						$link = "http://www.dhl.co.uk/en/express/tracking.html?AWB={$mc_tracking_code}&brand=DHL";
					}else{
						$link = "";
					}
					echo '<p>Your order tracking code is: <b>'.$mc_tracking_code.'</b> <br>Sent via: <b>'.$mc_courier.'</b><br><a href="'.$link.'" class="btn" target="_blank">TRACK ORDER</a></p>';
				}
			}

			// Add tracking info to order complete email
			public function mc_action_woocommerce_email_order_meta( $order, $sent_to_admin, $plain_text, $email ) { 
				if($order->get_status()=="completed"){
					$mc_tracking_code = $order->get_meta( '_mc_tracking_code');
					$mc_courier = $order->get_meta( '_mc_courier');
					if(!empty(trim($mc_tracking_code))){
						if (strpos($mc_courier, 'Royal Mail') !== false && !empty($mc_tracking_code)) {
							echo "<p>Your order was despatched via {$mc_courier}. Tracking Code: <a href='https://www.royalmail.com/track-your-item?trackNumber={$mc_tracking_code}'>{$mc_tracking_code}</a></p>";
						}elseif (strpos($mc_courier, 'DPD') !== false && !empty($mc_tracking_code)) {
							echo "<p>Your order was despatched via {$mc_courier}. Tracking Code: <a href='http://www.dpd.co.uk/service/tracking?consignment={$mc_tracking_code}'>{$mc_tracking_code}</a></p>";
						}elseif (strpos($mc_courier, 'DHL') !== false && !empty($mc_tracking_code)) {
							echo "<p>Your order was despatched via {$mc_courier}. Tracking Code: <a href='http://www.dhl.co.uk/en/express/tracking.html?AWB={$mc_tracking_code}&brand=DHL'>{$mc_tracking_code}</a></p>";
						}else{
							echo "<br><p>Your order was despatched via {$mc_courier}. Tracking Code: {$mc_tracking_code}</p>";
						}
					}
				}
			}

		}

	$MCWootracking = new MCWootracking();
	endif;
}
