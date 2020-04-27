<?php

/**
 * --------------------------------------------------------------------------------
 * Payment Plugin - Cardinity
 * --------------------------------------------------------------------------------
 * @package     Joomla 2.5 -  3.x
 * @subpackage  J2Store
 * @author      Cardinity
 * @copyright   Copyright (c) 2020 Cardinity . All rights reserved.
 * @license     GNU/GPL license: http://www.gnu.org/licenses/gpl-2.0.html
 * --------------------------------------------------------------------------------
 *
 * */

// No direct access

defined('_JEXEC') or die('Restricted access');

require_once JPATH_ADMINISTRATOR . '/components/com_j2store/library/plugins/payment.php';
require_once JPATH_ADMINISTRATOR . '/components/com_j2store/helpers/j2store.php';

class plgJ2StorePayment_cardinity extends J2StorePaymentPlugin
{
  /**
   * @var $_element  string  Should always correspond with the plugin's filename,
   *                         forcing it to be unique
   */
  var $_element    = 'payment_cardinity';
  var $project_key = '';
  var $project_secret = '';


  /**
   * Constructor
   *
   * @param object $subject The object to observe
   * @param 	array  $config  An array that holds the plugin configuration
   * @since 1.5
   */
  function __construct(&$subject, $config)
  {
    parent::__construct($subject, $config);
    $this->loadLanguage('', JPATH_ADMINISTRATOR);

    $this->project_key = $this->params->get('project_key');
    $this->project_secret = $this->params->get('project_secret');
  }


  /**
   * Prepares variables for the payment form. 
   * Displayed when customer selects the method in Shipping and Payment step of Checkout
   *
   * @return unknown_type
   */
  function _renderForm($data)
  {
    $user = JFactory::getUser();
    $vars = new JObject();
    $vars->onselection_text = 'You have selected to pay with Cardinity. You will be redirected.';
    //if this is a direct integration, the form layout should have the credit card form fields.
    $html = $this->_getLayout('form', $vars);

    return $html;
  }

  /**
   * Method to display a Place order button either to redirect the customer or process the credit card information.
   * @param $data     array       form post data
   * @return string   HTML to display
   */
  function _prePayment($data)
  {
    // get component params
    $amount = number_format($data['orderpayment_amount'], 2, '.', '');
    $cancel_url = JURI::root() . "index.php?option=com_j2store&view=checkout&task=confirmPayment&orderpayment_type=" . $this->_element . "&paction=cancel&tmpl=component";
    $description = $data['orderpayment_id'];
    $order_id = $data['order_id'];
    $return_url = JURI::root() . "index.php?option=com_j2store&view=checkout&task=confirmPayment&orderpayment_type=" . $this->_element . "&paction=callback&tmpl=component";

    $project_id = $this->project_key;
    $project_secret = $this->project_secret;
    // prepare the payment form

    $vars = new JObject();
    $vars->order_id = $data['order_id'];
    $vars->orderpayment_id = $data['orderpayment_id'];

    F0FTable::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_j2store/tables');
    $order = F0FTable::getInstance('Order', 'J2StoreTable')->getClone();
    $order->load(array('order_id' => $data['order_id']));
    $orderinfo = $order->getOrderInformation();

    $attributes = [
      "amount" => $amount,
      "currency" => $this->getCurrency($order)['currency_code'],
      "country" => $this->getCountryById($orderinfo->billing_country_id)->country_isocode_2,
      "order_id" => $order_id,
      "description" => $description,
      "project_id" => $project_id,
      "cancel_url" => $cancel_url,
      "return_url" => $return_url,
    ];

    ksort($attributes);

    $message = '';
    foreach ($attributes as $key => $value) {
      $message .= $key . $value;
    }

    $vars->signature = hash_hmac('sha256', $message, $project_secret);

    $vars->invoice = $order->getInvoiceNumber();

    $vars->attributes = $attributes;

    $html = $this->_getLayout('prepayment', $vars);
    return $html;
  }

  /**
   * Processes the payment form
   * and returns HTML to be displayed to the user
   * generally with a success/failed message
   *
   * @param $data     array       form post data
   * @return string   HTML to display
   */
  function _postPayment($data)
  {
    // Process the payment
    $app = JFactory::getApplication();
    $paction = $app->input->getString('paction');

    $vars = new JObject();

    switch ($_POST['status']) {
      case "approved":
        //Its a call back. You can update the order based on the response from the payment gateway
        //process the response from the gateway
        $this->_processSale();
        $html = $this->_getLayout('message', $vars);
        echo $html;
        break;
      case "declined":
        //cancel is called. 
        $vars->message = 'Sorry, your order has been declined.';
        $html = $this->_getLayout('message', $vars);
        break;
      default:
        $vars->message = 'Sorry, there seems to be an unknown problem. Contact our support.';
        $html = $this->_getLayout('message', $vars);
        break;
    }

    return $html;
  }


  /**
   * Processes the sale payment
   *	 
   */
  private function _processSale()
  {

    $app = JFactory::getApplication();
    $data = $app->input->getArray($_POST);

    //get the order id sent by the gateway. This may differ based on the API of your payment gateway
    $order_id = $_POST['order_id'];

    // load the orderpayment record and set some values
    $order = F0FTable::getInstance('Order', 'J2StoreTable')->getClone();

    if ($order->load(array('order_id' => $order_id))) {

      $order->add_history(JText::_('J2STORE_CALLBACK_RESPONSE_RECEIVED'));

      //run any checks you want here.
      //if payment successful, call : $order->payment_complete ();

      $message = '';
      ksort($_POST);

      foreach ($_POST as $key => $value) {
        if ($key == 'signature') continue;
        $message .= $key . $value;
      }

      $signature = hash_hmac('sha256', $message, $this->project_secret);

      if ($signature == $_POST['signature']) {
        $order->payment_complete();
      } else { }



      // save the data
      if (!$order->store()) {
        $errors[] = $order->getError();
      }
      //clear cart
      $order->empty_cart();
    }

    return count($errors) ? implode("\n", $errors) : '';
  }

  /**
   * Simple logger
   *
   * @param string $text
   * @param string $type
   * @return void
   */
  function _log($text, $type = 'message')
  {
    if ($this->_isLog) {
      $file = JPATH_ROOT . "/cache/{$this->_element}.log";
      $date = JFactory::getDate();

      $f = fopen($file, 'a');
      fwrite($f, "\n\n" . $date->format('Y-m-d H:i:s'));
      fwrite($f, "\n" . $type . ': ' . $text);
      fclose($f);
    }
  }
}
