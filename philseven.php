<?php
/*
Plugin Name: 7 connect Payment Gateway

Plugin URI: www.vutuz.com/philseven.zip

Description: 7 connect Payment gateway for woocommerce

Version: 1.0

Author: vutuz

Author URI: www.vutuz.com

*/
add_action('plugins_loaded', 'WooCommercen_philseven_init', 0);

add_action('plugins_loaded', 'check_philseven_response', 1);

function WooCommercen_philseven_init(){

  if(!class_exists('WC_Payment_Gateway')) return;



  class WC_Philseven extends WC_Payment_Gateway{

    public function __construct(){

      $this->icon = plugins_url().'/philseven/image/icon.png';

      $this -> id = 'philseven';

      $this -> medthod_title = '7-Connect';

      $this -> has_fields = false;



      $this -> init_form_fields();

      $this -> init_settings();



      $this -> title = $this -> settings['title'];

      $this -> description = $this -> settings['description'];

      $this -> merchant_id = $this -> settings['merchant_id'];

      $this -> transaction_key = $this -> settings['transactionkey'];

      $this -> redirect_page_id = $this -> settings['redirect_page_id'];

      $Testmode = $this->settings['testmode'];      

      $this -> liveurl = (($Testmode == 'yes') ? 'https://testpay.cliqq.net/transact' : 'https://pay.7-eleven.com.ph/transact' );

      $this -> msg['message'] = "";

      $this -> msg['class'] = "";

      if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {

                add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );

             } else {

                add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );

            }

      add_action('woocommerce_receipt_philseven', array(&$this, 'receipt_page'));

   }

    function init_form_fields(){

       $this -> form_fields = array(

                'enabled' => array(

                    'title' => __('Enable/Disable', 'philseven'),

                    'type' => 'checkbox',

                    'label' => __('Enable 7-Connect Payment Module.', 'philseven'),

                    'default' => 'no'),

                'title' => array(

                    'title' => __('Title:', 'philseven'),

                    'type'=> 'text',

                    'description' => __('This controls the title which the user sees during checkout.', 'philseven'),

                    'default' => __('7-Connect', 'philseven')),

                'description' => array(

                    'title' => __('Description:', 'philseven'),

                    'type' => 'textarea',

                    'description' => __('This controls the description which the user sees during checkout.', 'philseven'),

                    'default' => __('Use 7-Connect in order to pay at any 7-Eleven store', 'philseven')),

                'testmode' => array(

                    'title' => __('Using Test mode:', 'philseven'),

                    'type' => 'checkbox',

                    'description' => __('uncheck to Enable live mode ', 'philseven'),

                    'default' => __('yes', 'philseven')),     

           

                'merchant_id' => array(

                    'title' => __('Merchant ID', 'philseven'),

                    'type' => 'text',

                    'description' => __('merchant id of 7 connect account')),

                'transactionkey' => array(

                    'title' => __('Transaction Key ', 'philseven'),

                    'type' => 'text',

                    'description' => __('Transaction key of 7 connect account')),

        

                'redirect_page_id' => array(

                    'title' => __('Return Page'),

                    'type' => 'select',

                    'options' => $this -> get_pages('Select Page'),

                    'description' => "URL of success page"

                )

            );

    }



       public function admin_options(){

        echo '<h3>'.__('7-Connect Payment Gateway', 'philseven').'</h3>';

        echo '<p>'.__('7-Connect is most popular payment gateway for online shopping in phillipine').'</p>';

        echo '<table class="form-table">';

        // Generate the HTML For the settings form.

        $this -> generate_settings_html();

        echo '</table>';



    }



    /**

     *  There are no payment fields for philseven, but we want to show the description if set.

     **/

    function payment_fields(){

        if($this -> description) echo wpautop(wptexturize($this -> description));

    }

    /**

     * Receipt Page

     **/

    function receipt_page($order_id){

        echo '<p>'.__('Thank you for your order, please click the button below to pay with philseven.', 'philseven').'</p>';

        echo $this -> generate_philseven_form($order_id);



    }

    /**

     * Generate philseven button link

     **/

    public function generate_philseven_form($order_id){



       global $woocommerce;

       $order = wc_get_order( $order_id );

         

//             var_dump($order);

       $merchantRef = $order_id.'_'.date("ymds");

       $merchantID = $this->merchant_id;

       $transactionKey = $this->transaction_key;

       $token = sha1($merchantID . $merchantRef . '{' . $transactionKey . '}');

       $amount = $order->order_total;



        $redirect_url = ($this -> redirect_page_id=="" || $this -> redirect_page_id==0)?get_site_url() . "/":get_permalink($this -> redirect_page_id);

//      $redirect_url = $this->get_return_url( $order ).'?key='; 

      $fields = array(

      'merchantID'=>$merchantID,

      'merchantRef'=>$merchantRef,

      'amount'=>$amount,

      'successURL'=>$redirect_url,

      'failURL'=>$redirect_url,

      'token'=>$token,

      'email'=>$order->billing_email,

      );

//      var_dump($fields);



//       $philseven_args_array = array();

//        foreach($fields as $key => $value){

//          $philseven_args_array[] = "<input type='hidden' name='$key' value='$value'/>";

//        }

//        $order->update_status('processing', 'payed at 7eleven');

        $order->reduce_order_stock();

        WC()->cart->empty_cart();

        $params = http_build_query($fields);

        header("Location: $this->liveurl?$params");       

//        return '<form action="'.$this -> liveurl.'" method="post" id="philseven_payment_form">

//            ' . implode('', $philseven_args_array) . '

//            <input type="submit" class="button-alt" id="submit_philseven_payment_form" value="'.__('Pay via 7 connect', 'philseven').'" /> <a class="button cancel" href="'.$order->get_cancel_order_url().'">'.__('Cancel order &amp; restore cart', 'philseven').'</a>

//            

//</form>';





    }



    

    /**

     * Process the payment and return the result

     **/

    function process_payment($order_id){

        global $woocommerce;

      $order = new WC_Order( $order_id );    

        return array('result' => 'success', 'redirect' => add_query_arg('order',

            $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id( 'pay'))))

        );

      }



            

    function showMessage($content){

            return '<div class="box '.$this -> msg['class'].'-box">'.$this -> msg['message'].'</div>'.$content;

        }

     // get all pages

    function get_pages($title = false, $indent = true) {

        $wp_pages = get_pages('sort_column=menu_order');

        $page_list = array();

        if ($title) $page_list[] = $title;

        foreach ($wp_pages as $page) {

            $prefix = '';

            // show indented child pages?

            if ($indent) {

                $has_parent = $page->post_parent;

                while($has_parent) {

                    $prefix .=  ' - ';

                    $next_page = get_page($has_parent);

                    $has_parent = $next_page->post_parent;

                }

            }

            // add to page list array array

            $page_list[$page->ID] = $prefix . $page->post_title;

        }

        return $page_list;

    }

}


  function check_philseven_response(){

         global $woocommerce;    

       

         if (isset($_REQUEST['type']) && isset($_REQUEST['token'])) {

           

            $transactiontype = $_REQUEST['type'];

            $merchantRef = $_REQUEST['merchantRef'];

            $amount = $_REQUEST['amount'];

            $token = $_REQUEST['token'];

            $order_id = explode('_',$merchantRef);

            $order_id = (int)$order_id[0];



            $philseven = new WC_Philseven();

            $transactionKey = $philseven->transaction_key;

            $merchantID = $philseven->merchant_id;

            $validtoken = sha1($transactiontype . $merchantID . $merchantRef . '{' . $transactionKey . '}');



             if ($token != $validtoken) {

             $authCode = "";

             $responseCode = "DECLINED";

             $responseDesc = "Invalid token";

                  $order = new WC_Order($order_id);

                  $order->update_status('cancelled','Invalid Transaction ');

            } else {

             $authCode = '1111';

             $responseCode = "SUCCESS";

             $responseDesc = "";

             switch($transactiontype) {

              case "VALIDATE":

               // Check if merchantRef is still valid

               break;

              case "CONFIRM":

               // Update the paid status of the table

              { 

                $order = new WC_Order($order_id);

                if($order->status == 'processing'){

                $authCode = "";

                $responseCode = "DECLINED";

                $responseDesc = "Order has been processed"; 

                }
                else

                {
                  
                  $note = 'Payed At 7 eleven store';
              

                // $order->update_status( 'wc-processing', 'Payed At 7 eleven store' );
                   wp_update_post( array( 'ID' => $order_id, 'post_status' => 'wc-' . 'processing' ) );
                   $order->add_order_note( trim( $note . ' ' . sprintf( __( 'Order status changed from %s to %s.', 'woocommerce' ), wc_get_order_status_name( 'pending' ), wc_get_order_status_name('processing' ) ) ), 0, false );
                   $order->record_product_sales();

                    // Increase coupon usage counts
                   $order->increase_coupon_usage_counts();

                    // Update reports
                    wc_delete_shop_order_transients( $order_id);
                      }

              }

                

               break;

              default:

               $responseCode = "DECLINED";

               $responseDesc = "Unknown transaction type";

             }

            }



            $token = sha1($transactiontype . $merchantID . $merchantRef . $authCode . $responseCode . '{' . $transactionKey . '}');



            //set GET variables

            $fields = array(

               'merchantID'=>$merchantID,

               'merchantRef'=>$merchantRef,

               'amount'=>$amount,

               'authCode'=>$authCode,

               'responseCode'=>$responseCode,

               'responseDesc'=>$responseDesc,

               'token'=>$token

              );



            $params = http_build_query($fields);

            echo "?$params";

            $myFile = ABSPATH . 'wp-content/plugins/philseven/tmp/7-CONNECT.log';

         

            $fh = fopen($myFile, 'a') or exit();

            fwrite($fh, date('Y-m-d H:i ') . $params . "\n");

            fclose($fh);

            exit();

           }



         

    }

   /**

     * Add the Gateway to WooCommerce

     **/

    function woocommerce_add_philseven_philseven_gateway($methods) {

        $methods[] = 'WC_Philseven';

        return $methods;

    }



    add_filter('woocommerce_payment_gateways', 'woocommerce_add_philseven_philseven_gateway' );

}
