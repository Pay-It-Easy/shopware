<?php

use DoctrineExtensions\Query\Mysql\StrToDate;

require_once(dirname(dirname(dirname(__FILE__))).'/core/PayItEasyCore.php');
require_once(dirname(dirname(dirname(__FILE__))).'/core/simpleLogger.php');

class Shopware_Controllers_Frontend_PaymentPayItEasy extends Shopware_Controllers_Frontend_Payment
{

	private $prefix;
	private $paymentOption;
	private $paymentMethod;
	private $transaction_id;
	private $userinfo;
	private $logger;
	private $orderid;

	function getLogger(){
		if($this->logger)
		return $this->logger;
		else
		$this->logger=new simpleLogger($this->getLoggerFileName(),$this->getLoggerLevel());
		return $this->logger;
	}


	public function Config()
	{
		return Shopware()->Plugins()->Frontend()->PaymentPayItEasy()->Config();
	}


	public function indexAction ()
	{
		$client = strtolower('PayItEasy').'_';
		$payment=$this->getPaymentShortName();

		switch ($payment)
		{
			case $client.'cc' :
			case $client.'dd' :
			case $client.'gp' :
			case $client.'pp' :
				if (preg_match('/'.strtolower('PayItEasy').'_(.+)/',$payment, $matches))
				$payment_methods = strtoupper($matches[1]);
				return $this->redirect(array('action' => 'gateway',
						'payment' => $payment_methods,
						'forceSecure' => true));
				break;
			default :
				return $this->redirect(array('controller' => 'checkout'));
		}
	}

	public function notifyAction()
	{
		$helper=new PayItEasyCore();
		$dStatus=$helper->processPaymentGatewayNotification($this->Request()->getParams(), $this);
		$this->View()->nUrl  =$dStatus['redirecturl'];
	}

	public function gatewayAction()
	{
		$config = $this->Config();
		$router = $this->Front()->Router();
		$this->prefix=strtoupper($this->Request()->payment);

		switch ($this->prefix)
		{
			case 'CC' :
				$this->paymentMethod='creditcard';
				break;
			case 'DD' :
				$this->paymentMethod='directdebit';
				break;
			case 'GP' :
				$this->paymentMethod='banktransfer';
				break;
			case 'PP':
				$this->paymentMethod='paypal';
				break;
		}

		$userinfo = $this->getUser();
		if (!$userinfo) // Redirect to payment failed page
		$this->forward('cancel');

		$uniquePaymentID = $this->createPaymentUniqueId();
		$oldvlue=Shopware()->Config()->DeleteCacheAfterOrder;
		Shopware()->Config()->DeleteCacheAfterOrder='';
		$this->orderid=$this->saveOrder($this->getBasketid(), $uniquePaymentID,17);
		Shopware()->Config()->DeleteCacheAfterOrder=$oldvlue;
		$this->View()->addTemplateDir(dirname(__FILE__) . '/Views/frontend/payment_'.strtolower('PayItEasy').'/');


		$helper=new PayItEasyCore();
		$this->View()->htmlCode = $helper->getTransactionRedirect($this);


	}

	public function failAction ()
	{
		$this->forward('cancel');
	}

	public function cancelAction ()
	{
		return $this->redirect(array('controller' => 'checkout'));
	}




	////////////////////////////////////////////NOTOFICATION////////////////////////////////////////////////////

	function processOnError($order_id,$msg){
		$this->updatePaymentStatus($order_id,21,$msg);
		$this->logDebug('processOnError()->order_id:'.$order_id.' msg:'.$msg);
		return $this->Front()->Router()->assemble(array('action' => 'fail'));
	}

	function processOnCancel($order_id){
		$this->updatePaymentStatus($order_id,35);
		$this->logDebug('processOnCancel()->order_id:'.$order_id);
		Shopware()->Cache()->clean(Zend_Cache::CLEANING_MODE_MATCHING_TAG, array('Shopware_Adodb'));
		return $this->Front()->Router()->assemble(array('controller' => 'checkout'));
	}

	function processOnOk($order_id,$amount,$currency){
		$vmsg=$this->validateOrderAmount($order_id,$amount,$currency);
		if(''!=$vmsg)
			return $this->processOnError($order_id,$vmsg);

		$this->updatePaymentStatus($order_id, 12,true);
		$this->logDebug('processOnOk()->order_id:'.$order_id);
		Shopware()->Cache()->clean(Zend_Cache::CLEANING_MODE_MATCHING_TAG, array('Shopware_Adodb'));
		return $this->Front()->Router()->assemble(array('controller' => 'checkout',
				'action' => 'finish',
				'sAGB'=> '1',
				'sUniqueID' => $uniqueId));
	}

	function validateOrderAmount($order_id,$amount,$currency){
		$this->logDebug("validate()->start() amount:".$amount.' currency:'.$currency);

		$orderCurrency = Shopware()->Db()->fetchOne('select currency from s_order WHERE ordernumber=?', array($order_id));

		$orderAmount = Shopware()->Db()->fetchOne('select invoice_amount from s_order WHERE ordernumber=?', array($order_id));
		$orderAmount=$this->formatAmount($orderAmount);


		$this->logDebug("validate()->start() orderAmount:".$orderAmount." orderCurrency:".$orderCurrency);

		if(!is_null($currency) && $orderCurrency!=$currency){
			$this->logTransaction('validate()->invalid currency order currency:'.$orderCurrency.' gateway currency:'.$currency);
			return 'invalid currency order currency:'.$orderCurrency.' gateway currency:'.$currency;
		}

		if($orderAmount!= $amount){
			$this->logTransaction('validate()->invalid amount order amount:'.$orderAmount.' gateway amount:'.$amount);
			return 'invalid amount order amount:'.$orderAmount.' gateway amount:'.$amount;
		}
		$this->logTransaction('validate()->ok');
		return '';

	}

	/**
	 * Saves the payment status an sends and possibly sends a status email.
	 *
	 * @param string $transactionId
	 * @param string $paymentUniqueId
	 * @param int $paymentStatusId
	 * @param bool $sendStatusMail
	 * @return void
	 */
	public function updatePaymentStatus($orderNumber, $paymentStatusId,$comment=null, $sendStatusMail = false)
	{

		$sql = '
				SELECT id FROM s_order
				WHERE ordernumber=? AND status!=-1
				';
		$orderId = Shopware()->Db()->fetchOne($sql, array($orderNumber));
		$order = Shopware()->Modules()->Order();
		$order->setPaymentStatus($orderId, $paymentStatusId, $sendStatusMail,$comment);
	}


	function getSSLMerchant(){
		return $this->Config()->sslmerchant;
	}

	function getTransactiontype(){
		if('CC'==$this->prefix)
			return $this->Config()->transactiontype_CC;
		else if('DD'==$this->prefix)
			return $this->Config()->transactiontype_DD;
		else
			return '';
	}

	function getCssurl(){
		return $this->Config()->cssurl;

	}

	function isLiveMode(){
		if($this->prefix=='CC' && $this->Config()->test_mode_CC==1)
		return false;
		if($this->prefix=='DD' && $this->Config()->test_mode_DD==1)
		return false;
		if($this->prefix=='GP' && $this->Config()->test_mode_GP==1)
		return false;
		if($this->prefix=='PP' && $this->Config()->test_mode_PP==1)
		return false;

		return true;

	}

	function getSecret(){
		if($this->Config()->secret=='')
		return '';
		return $this->Config()->secret;
	}

	function getPrefix(){
		if($this->prefix==''){
			$this->prefix=$this->Request()->payment;
		}
		return $this->prefix;
	}

	function getLoggerLevel(){
		if($this->Config()->debug=='')
		return 'NONE';
		else if($this->Config()->debug=='Transaction')
		return 'INFO';
		else
		return 'DEBUG';
		//return strtoupper($this->Config()->debug);
	}


	/**
	 * Enter description here ...
	 */
	function getOrderid(){
		return $this->orderid;
	}

	function getPaymentMethod(){
		return $this->paymentMethod;
	}

	/**
	 * Enter description here ...
	 */
	function getLocale(){
		$str=shopware()->System()->sLanguageData[Shopware()->System()->sLanguage]["isocode"];
		$str = strtolower($str);
		if(strlen($str)<2)
		return "de";
		else
		return substr($str,0,2);
	}

	/**
	 * Enter description here ...
	 */
	function getBasketid(){
		if($this->transaction_id==''){
			mt_srand(time());
			$this->transaction_id = mt_rand();
		}
		return $this->transaction_id;
	}

	private function formatAmount($amount){
		// set the amount
		$tstr = number_format($amount, 2, ',', '');
		//$tstr = str_replace('.',',',$amount);
		$tstr = substr( $tstr, 0,strpos($tstr,',')+3);
		//$this->logDebug(sprintf('setAmount: %d',$tstr));
		return $tstr;
	}

	function getAmount(){
		return $this->formatAmount(parent::getAmount());
	}

	function getAcceptcountries(){
		return $this->Config()->acceptcountries;
	}

	function getRejectcountries(){
		return $this->Config()->rejectcountries;
	}

	function getCustomer_addr_city(){
		$this->userinfo = $this->getUser();
		if (!$this->userinfo) // Redirect to payment failed page
		return '';
		$address=$this->userinfo['shippingaddress'];
		return $address['city'];
	}

	function getCustomer_addr_street(){
		$this->userinfo = $this->getUser();
		if (!$this->userinfo) // Redirect to payment failed page
		return '';
		$address=$this->userinfo['shippingaddress'];
		return $address['street'];
	}
	function getCustomer_addr_zip(){
		$this->userinfo = $this->getUser();
		if (!$this->userinfo) // Redirect to payment failed page
		return '';
		$address=$this->userinfo['shippingaddress'];
		return $address['zipcode'];
	}
	function getCustomer_addr_number(){
		$this->userinfo = $this->getUser();
		if (!$this->userinfo) // Redirect to payment failed page
		return '';
		$address=$this->userinfo['shippingaddress'];
		return  $address['streetnumber'];
	}

	function getDeliverycountry(){
		$this->userinfo = $this->getUser();
		if (!$this->userinfo) // Redirect to payment failed page
		return '';
		$country=$this->userinfo['additional']['country'];
		return  $country['countryiso'];

	}



	/**
	 * Enter description here ...
	 */
	function getCurrency(){
		return $this->getCurrencyShortName();

	}
	/**
	 * Enter description here ...
	 */
	function getSessionid(){
		return Shopware()->SessionID();
	}

	/**
	 * Enter description here ...
	 */
	function getNotifyurl(){
		$router = $this->Front()->Router();
		return '';// $router->assemble(array('action' => 'notify','forceSecure' => true)) .'?orderId=' . $this->getOrderid();
	}
	/**
	 * Enter description here ...
	 */
	function getNotificationfailedurl(){
		$router = $this->Front()->Router();
		return '';//$router->assemble(array('action' => 'notify','forceSecure' => true)) .'?orderId=' . $this->getOrderid();
	}



	function getUnitId(){
		$sessionid=$this->Request()->getParam('sessionid');
		if (preg_match('/.*_(.+)/',$sessionid, $matches))
		return $matches[1];
		else
		return '';
	}

	function getLoggerFileName(){
		if($this->Config()->logger_file_name)
		$this->Config()->logger_file_name;
		else
		return '';
	}

	/**
	 * @param unknown $param
	 */
	function logDebug($param){
		$this->getLogger()->debug("".$param);
	}

	/**
	 * @param unknown $param
	 */
	function logTransaction($param){
		$this->getLogger()->info("".$param);
	}

	/**
	 * @param unknown $param
	 */
	function logError($param){
		$this->getLogger()->error("".$param);

	}


	// 			'firstname'		=> $userinfo["billingaddress"]["firstname"],
	// 			'lastname'		=> $userinfo["billingaddress"]["lastname"],
	// 			'address'		=> $userinfo["billingaddress"]["street"] . ' ' .
	// 			$userinfo["billingaddress"]["streetnumber"],
	// 			'city'		=> $userinfo["billingaddress"]["city"],
	// 			'phone_number'	=> $userinfo["billingaddress"]["phone"],
	// 			'postal_code'	=> $userinfo["billingaddress"]["zipcode"],
	// 			'country'		=> $userinfo["additional"]["country"]["iso3"],


	function getPaymentGatewayURL(){
		return '';
	}

	/**
	 * @return Ambigous <mixed, NULL>
	 */
	function getForm_label_submit(){
		$value=PayItEasyCore::translateKey('FORM_LABEL_SUBMIT',$this->getLocale());
		$value=trim($value);

		if(PayItEasyCore::notEmpty($value))
		return $value;
		return '';
	}

	/**
	 * Enter description here ...
	 * @return Ambigous <NULL, mixed>
	 */
	function getDeliverycountryrejectmessage(){
		$value=PayItEasyCore::translateKey('DELIVERYCOUNTRY_REJECT_MESSAGE',$this->getLocale());
		$value=trim($value);

		if(PayItEasyCore::notEmpty($value))
		return $value;
		return '';
	}

	/**
	 * Enter description here ...
	 * @return Ambigous <NULL, mixed>
	 */
	function getForm_merchantref(){
		return $this->Config()->form_merchantref;
	}

	/**
	 * Enter description here ...
	 * @return Ambigous <NULL, mixed>
	 */
	function getForm_label_cancel(){
		$value=PayItEasyCore::translateKey('FORM_LABEL_CANCEL',$this->getLocale());
		$value=trim($value);

		if(PayItEasyCore::notEmpty($value))
		return $value;
		return '';
	}

	/**
	 * Enter description here ...
	 * @return Ambigous <NULL, mixed>
	 */
	function  getDeliverycountryaction(){
		return $this->Config()->deliverycountry_action_CC;
	}


	/**
	 * Enter description here ...
	 * @return Ambigous <NULL, mixed>
	 */
	function getAutocapture(){
		if('CC'==$this->prefix)
		return $this->Config()->autocapture_CC;
		if('DD'==$this->prefix)
		return $this->Config()->autocapture_DD;
		else
		return '';
	}


	/**
	 * Enter description here ...
	 * @return Ambigous <NULL, mixed>
	 */
	function getPayment_options(){
		if('CC'==$this->prefix)
		return $this->Config()->paymentoptions_CC;
		else if('DD'==$this->prefix)
		return $this->Config()->paymentoptions_DD;
		else if('GP'==$this->prefix)
		{
			if($this->Config()->ageverification)
			return 'avsopen';
		}else
		return '';
	}

	/**
	 * Enter description here ...
	 * @return Ambigous <NULL, mixed>
	 */
	function getCountryrejectmessage(){
		return PayItEasyCore::translateKey('COUNTRYREJECTMESSAGE');
	}

	/**
	 * Enter description here ...
	 * @return Ambigous <NULL, mixed>
	 */
	function getForm_merchantname(){
		return $this->Config()->form_merchantname;
	}


	public function getAccountnumber(){
		'';
	}

	public function getBankcode(){
		if($this->isLiveMode())
		return '';
		else{
			return '12345679';
		}
	}

	function getBic(){
		if($this->isLiveMode())
		return '';
		else
		return 'TESTDETT421';
	}

	function getIban(){
		return '';
	}

	function getMandateid(){
		if($this->Config()->mandateprefix_DD)
		return $this->Config()->mandateprefix_DD.'-'.$this->getOrderid();
		else
		return '-'.$this->getOrderid();
	}

	function getMandatename(){
		if($this->Config()->mandatename_DD)
		return $this->Config()->mandatename_DD;
		else
		return '';
	}

	function getSequencetype(){
		if($this->Config()->sequencetype_DD)
		return $this->Config()->sequencetype_DD;
		else
		return '';
	}

	public function getLabel0(){
		if($this->Config()->label0)
		return $this->Config()->label0;
		else
		return '';
	}
	public function getLabel1(){
		if($this->Config()->label1)
		return $this->Config()->label1;
		else
		return '';
	}
	public function getLabel2(){
		if($this->Config()->label2)
		return $this->Config()->label2;
		else
		return '';
	}
	public function getLabel3(){
		if($this->Config()->label3)
		return $this->Config()->label3;
		else
		return '';
	}

	public function getLabel4(){
		if($this->Config()->label4)
		return $this->Config()->label4;
		else
		return '';
	}

	public function getText0(){
		if($this->Config()->text0)
		return $this->Config()->text0;
		else
		return '';
	}
	public function getText1(){
		if($this->Config()->text1)
		return $this->Config()->text1;
		else
		return '';
	}
	public function getText2(){
		if($this->Config()->text2)
		return $this->Config()->text2;
		else
		return '';
	}
	public function getText3(){
		if($this->Config()->text3)
		return $this->Config()->text3;
		else
		return '';
	}
	public function getText4(){
		if($this->Config()->text4)
		return $this->Config()->text4;
		else
		return '';
	}


	function setAdditionalParamsforPayPal(array &$params){
		$basket=$this->getBasket();

		$line_item_no = 0;

		if($this->Config()->payment_options_PP){
			$params['payment_options']=$this->Config()->payment_options;
		}

		$params['basket_shipping_costs']=$basket['sShippingcosts'];

		foreach ($basket["content"] as $key => $basketRow){

			$basketRow["articlename"] = str_replace("<br />","\n",$basketRow["articlename"]);
			$basketRow["articlename"] = html_entity_decode($basketRow["articlename"]);
			$basketRow["articlename"] = strip_tags($basketRow["articlename"]);
			if (!$basketRow["price"])
			$basketRow["price"] = "0,00";

			$params['basketitem_amount' . $line_item_no] = $this->formatAmount($basketRow["price"]);
			$params['basketitem_name' . $line_item_no] =substr($basketRow["articlename"],0,32);
			$params['basketitem_number' . $line_item_no] = urlencode(substr($basketRow["articleID"],0,32));
			$params['basketitem_qty' . $line_item_no] = $basketRow["quantity"];
			$line_item_no++;
		}
		return $params;
	}






}