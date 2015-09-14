<?php
/**
 * SparkPay & Signifyd Integration
 * ====
 * Upon a successful checkout, the order ID is posted
 * to this script, which grabs relevent order details
 * thru the SparkPay API, and submits it to the Signifyd 
 * API for fraud analysis. 
 * ====
 * @author Robert Mullins <mullinsr@live.com>
 * @copyright Copyright Robert Mullins 2015, All Rights Reserved
 * No warranty implied. 
 * Free when used alongside Signifyd Bronze Plan.
 * Must purchase license to use with any other plan/guaranteed plan.
 *
 * @version 1.2
 */

class SparkPay_Signifyd {

	# API creds:
	private $sig_key   = 'your_signifyd_api_key';                       //your signifyd key
	private $sig_url   = 'https://api.signifyd.com/v2/cases';           //do not edit -- signifyd api url
	private $spark_key = 'your_sparkpay_key';                           //your sparkpay api key
	private $spark_url = 'https://www.your_site.com/api/v1/';           //your store api url

  # Common:
  private $order_id;
  private $order_shipping_id;
  private $order_billing_id;
  private $customer_id;
  private $customer;

   # Config:
  private $filterPaypal = false;  //Change to true to filter Paypal orders. 

	/**
	 * POSTs specified data to Signifyd API in order
	 * to create a new case for fraud analysis. 
	 * @param mixed - The order data to post to signifyd.
	 * @return mixed - The Signifyd response.
	 */
	private function postSig($data) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->sig_url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($ch, CURLOPT_USERPWD, $this->sig_key .':');
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

		$data = curl_exec($ch);
		return $data;
	}

	/**
	 * GETs specified SparkPay API resource
	 * @param string - The SparkPay API endpoint
	 * @return mixed - The JSON SparkPay API response.
	 */
	private function getSpark($url) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30); 
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('X-AC-Auth-Token: ' .$this->spark_key));
		curl_setopt($ch, CURLINFO_HEADER_OUT, true);
		$data = curl_exec($ch);
        return json_decode($data);
	}


    /**
     * Get's all data related to the Signifyd 'purchase' array.
     * @return mixed - Signifyd formatted 'purchase' data.
     */
    private function getPurchase() {
        $order = $this->getSpark($this->spark_url ."orders/" .$this->order_id);
        $order_payments = $this->getSpark($this->spark_url .'orders/' .$this->order_id .'/payments')->payments[0];
	        
        /* Make sure the order payment was approved */
        if ($order->order_status_id != 2) {
            die("order declined");
        }
        
        $this->customer_id = $order->customer_id;

        $this->order_billing_id  = $order->order_shipping_address_id;
        $this->order_shipping_id = $order->order_billing_address_id;
        
        $createdAt = substr($order->created_at, 0, strpos($order->created_at, "."));
        $createdAt .= '-05:00';
        $purchase = array(
              'browserIpAddress' => $order->ip_address,
              'orderId'          => $order->id,
              'createdAt'        => $createdAt,
              'paymentGateway'   => 'cc',
              'currency'         => 'USD',
              'avsResponseCode'  => $order_payments->avs_code[0],
              'cvvResponseCode'  => $order_payments->cvv_response_code[0],
              'orderChannel'     => 'WEB',
              'totalPrice'       => $order->grand_total,
              'products'         => $this->getOrderItems(),
              'shipments'        => $this->getOrderShipments()
        );
        return $purchase;
    }

    /**
     * Gets all products related to a single order. 
     * @return mixed - Array holding order related products.
     */
    private function getOrderItems() {
        $products = $this->getSpark($this->spark_url .'orders/' .$this->order_id .'/items');
        $return = array(); //array to be returned
        foreach ($products->items as $product) {
            array_push($return, array(
                'itemId'        => $product->id,
                'itemName'      => $product->item_name,
                'itemQuantity'  => $product->quantity,
                'itemWeight'    => $product->weight,
                'itemPrice'     => $product->price,
            ));
        }
        return $return;
    }

    /**
     * Gets the shipments related to a single order.
     * @return mixed - Shipments related to a single order.
     */
    private function getOrderShipments() {
        $shipments = $this->getSpark($this->spark_url .'orders/' .$this->order_id .'/shipments');
        $return = array(); //array to be returned
        foreach ($shipments->shipments as $shipment) {
            array_push($return, array(
                'shipper'        => $shipment->shipment_name,
                'shippingMethod' => $shipment->shipping_method,
                'shippingPrice'  => $shipment->provider_total_shipping_cost,
                'trackingNumber' => $shipment->tracking_numbers
            ));
        }
        return $return;
    }

    /**
     * Gets all order data related to the Signifyd 'recipient' section.
     * @return mixed - Signifyd formatted 'recipient' data.
     */
    private function getRecipient() {
        $delivery = $this->getSpark($this->spark_url .'order_addresses/' .$this->order_shipping_id);
        $customer = $this->getSpark($this->spark_url .'customers/' .$delivery->customer_id);
        $return = array(
            'fullName'          => $delivery->first_name .' ' .$delivery->last_name,
            'confirmationEmail' => $customer->email,
            'confirmationPhone' => $delivery->phone,
            'organization'      => $delivery->company,
            'deliveryAddress'   => array(
                'streetAddress'     => $delivery->address_line_1,
                'unit'              => $delivery->address_line_2,
                'city'              => $delivery->city,
                'postalCode'        => $delivery->postal_code,
                'provinceCode'      => $delivery->state,
                'countryCode'       => 'US'
            )
        );
        return $return;
    }

    /**
     * Gets all order data related to the Signifyd 'card' requirement.
     * @return mixed - Signifyd formatted 'card' data.
     */
    private function getCard() {
        $cc = $this->getSpark($this->spark_url .'orders/' .$this->order_id .'/payments')->payments[0];
        $billing = $this->getSpark($this->spark_url .'order_addresses/' .$this->order_billing_id);
        $return = array(
            'cardHolderName' => $cc->cardholder_name,
            'expiryMonth'    => $cc->card_expiration_month,
            'expiryYear'     => $cc->card_expiration_year,
            //'last4'          => $cc->last_four,
            'billingAddress' => array(
                'streetAddress' => $billing->address_line_1,
                'unit'          => $billing->address_line_2,
                'city'          => $billing->city,
                'postalCode'    => $billing->postal_code,
                'provinceCode'  => $billing->state,
                'countryCode'   => 'US'
            )
        );
        return $return;
    }

    /**
     * Get all order data related to the Signifyd 'userAccount' requirement.
     * @return mixed - Signifyd formatted 'userAccount' data.
     */
    private function getUserAccount() {
        $customerOrders = $this->getSpark($this->spark_url .'orders/?customer_id=' .$this->customer_id);
        $customerData   = $this->getSpark($this->spark_url .'customers/' .$this->customer_id);
        $this->customer = $customerData; //cache customer results
        
        # determine order aggregate statistics:
        $aggregateOrderCount = $customerOrders->total_count;
        $aggregateOrderDollars = 0;
        foreach ($customerOrders->orders as $order) {
            $aggregateOrderDollars += $order->grand_total;
        }

        # format time dates:
        $createdAt = substr($customerData->created_at, 0, strpos($order->created_at, "."));
        $createdAt .= '-05:00';
        $updatedAt = substr($customerData->updated_at, 0, strpos($order->updated_at, "."));
        $updatedAt .= '-05:00';

        # format signifyd data:
        $return = array(
            'emailAddress'          =>  $customerData->email,
            'createdDate'           =>  $createdAt,
            'aggregateOrderCount'   =>  $aggregateOrderCount,
            'aggregateOrderDollars' =>  $aggregateOrderDollars,
            'lastUpdateDate'        =>  $updatedAt
        );

        return $return;
    }

    /**
     * Determines if the given order is a Paypal Express order. 
     * @return bool - True if order is Paypal Express, false otherwise.
     */
    public function isPaypalExpress() {
        $order = $this->getSpark($this->spark_url .'order_payments/?order_id=' .$this->order_id);
        if ($order->payments[0]->payment_method_name == "PayPalExpress") {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Object initializer
     * @param int - The order ID to sent to Signifyd
     */
    public function main($id) {
        $this->order_id = $id;

        # if order is paypal express, end script:
        if ($this->filterPaypal) {
            if ($this->isPaypalExpress()) {
                die("Exiting\n");
            }
        }
       
        /* Prepare & Format Signifyd POST data */
        $sigData = array(
            'purchase'  => $this->getPurchase(),
            'card'      => $this->getCard(),
            'recipient' => $this->getRecipient(),
            'userAccount' => $this->getUserAccount()
        );

        $this->postSig(json_encode($sigData));
    }
} //end class

//INIT:
date_default_timezone_set('America/New_York'); //Set timezone to eastern
$id = filter_var($_GET['id'], FILTER_SANITIZE_NUMBER_INT);
$init = new SparkPay_Signifyd;
$init->main($id);
