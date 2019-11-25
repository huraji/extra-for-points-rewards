<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * API to communicate with Conversio and update lists
 * 
 */
class Ext_Conversio_Api extends Conversio_Api {

	/**
	 * Set email defaults
	 *
	 * @since 0.1
	 */
	public function __construct() {

		// Get methods from parent class
		parent::__construct();
	}


	/**
	 * Update Customer List
	 *
	 * @since 0.1
	 * @param int $list_id Customer list id to update
	 * @param array $args args to pass as body to the API call
	 * @return array|WP_Error Api call response
	 */
	public function update_customer_list( $list_id, $args ) {

		$customer_list = '/customer-lists/' . trim( $list_id ) . '/subscriptions';
		$response = $this->api_call( $customer_list, $args, array('method' => 'PUT'));
		$this->monitor( $response );
		return $response;

	}

	/**
	 * Delete Customer from list
	 *
	 * @since 0.1
	 * @param int $list_id Customer list id to update
	 * @param array $args args to pass as body to the API call
	 * @return array|WP_Error Api call response
	 */
	public function delete_customer( $list_id, $args )
	{
		$customer_list = '/customer-lists/' . trim( $list_id ) . '/subscribers';
		$response = $this->api_call( $customer_list, $args, array( 'method' => 'DELETE' ) );
		$this->monitor( $response );
		return $response;
	}

	/**
	 * Monitor response if above 400
	 *
	 * @param int $response
	 * @return void
	 */
	public function monitor( $response )
	{
		if( !is_array( $response ) ) {
			return;
		}

		if ( $response['response']['code'] >= 400 ) {
			wp_mail(get_field('options_dev_email', 'option'), "Error in " . __FUNCTION__, "You have issues with " . __FUNCTION__ . ". Respons: " . var_dump($response['response']['code']));
		}

	}

}