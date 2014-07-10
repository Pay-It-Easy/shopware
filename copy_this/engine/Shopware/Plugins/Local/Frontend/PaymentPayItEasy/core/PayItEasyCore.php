<?php


/**
 * Enter description here ...
 * @author younes
 *
 */
class PayItEasyCore {



	const  PREFIX_CC='CC';
	const  PREFIX_PP='PP';
	const  PREFIX_GP='GP';
	const  PREFIX_DD='DD';
	const  PREFIX_SE='SE';
	const  PREFIX_BASE='BASE';

	const DEBUG_FILE_PATH='debug_file_path';
	const DEBUG='debug';
	const TITLE='title';
	const PUBLIC_TITLE='public_title';
	const SORT_ORDER='sort_order';
	const STATUS='status';
	const DESCRIPTION='description';

	const ORDER_STATUS_ID='ORDER_STATUS_ID';
	const ZONE='ZONE';

	const SECRET='secret';
	const SSLMERCHANT='sslmerchant';
	const TEST_MODE='test_mode';
	const ACCEPTCOUNTRIES='acceptcountries';
	const REJECTCOUNTRIES='rejectcountries';
	const TRANSACTIONTYPE='transactiontype';
	const NOTIFICATION_FAILED_URL='notificationfailedurl';
	const NOTIFYURL='notifyurl';
	const CSS_URL='cssurl';
	const AUTOCAPTURE='autocapture';
	const COUNTRYREJECTMESSAGE='countryrejectmessage';
	const FORM_MERCHANTNAME='form_merchantname';
	const DELIVERYCOUNTRY_ACTION='deliverycountry_action';
	const FORM_LABEL_SUBMIT='form_label_submit';
	const FORM_LABEL_CANCEL='form_label_cancel';
	const DELIVERYCOUNTRY_REJECT_MESSAGE='deliverycountry_reject_message';
	const FORM_MERCHANTREF='form_merchantref';
	const PAYMENT_GATEWAY_URL='payment_gateway_url';
	const PAYMENTOPTIONS='payment_options';
	const MANDATEID='mandateid';
	const MANDATEPREFIX='mandateprefix';
	const MANDATENAME='mandatename';
	const SEQUENCETYPE='sequencetype';
	const LABEL0='label0';
	const LABEL1='label1';
	const LABEL2='label2';
	const LABEL3='label3';
	const LABEL4='label4';
	const TEXT0='text0';
	const TEXT1='text1';
	const TEXT2='text2';
	const TEXT3='text3';
	const TEXT4='text4';




	private $logLevel;

	private static $TRANSACTION_TYPES = array('preauthorization','authorization');

	private static  $PAYMENT_METHODS =array('amexavs','sslifvisaenrolled','checklist','accountholder','cardholder','optionalcardholder');

	public static $API_VERSION='1.6';

	const TEST_URL='https://apistg.pay-it-easy.de/web-api/SSLPayment.po';
	const LIVE_URL='https://api.pay-it-easy.de/web-api/SSLPayment.po';

	var $apiVersion;
	var $command;

	function __construct(){
		$this->command='sslform';
		$this->apiVersion='1.6';
	}

	function getAPIVersion(){
		return $this->apiVersion;
	}



	/**
	 * Enter description here ...
	 * @return string
	 */
	function getDate() {
		return date("Ymd_H:i:s");
	}

	//qqq MY check the parameter command

	/**
	 * the method is used by the getTransactionParams, it sets the common mandatory params requerd by the payment gateway.
	 * @param array $params the array of params to be extanded
	 * @return unknown the array of params included the common mandatory params.
	 */
	function setCommonMandatoryParams(&$paymentModule){
		$params= array();
		$params['amount']=$paymentModule->getAmount();
		$params['basketid']=$paymentModule->getBasketid();
		$params['command']=$this->command;
		$params['currency']=$paymentModule->getCurrency();
		//$params['orderid']= $paymentModule->getOrderid();
		$params['orderid']= $this->getOrderid($paymentModule);
		$params['paymentmethod']=$paymentModule->getPaymentmethod();
		$params['sslmerchant']=$paymentModule->getSSLmerchant();
		$params['sessionid']=$paymentModule->getSessionid();
		$params['version']=$this->apiVersion;
		return $params;
	}

	function getOrderId(&$paymentModule){
		$d = new DateTime();
		$orderid=$paymentModule->getOrderid();
		$orderid=$orderid.'/'.$d->getTimestamp();
		return substr($orderid, 0,17);
	}
	function splitOrderId($orderid){
		$splitedOrder=explode("/", $orderid);
		return $splitedOrder[0];
	}


	/**
	 * the method is used by the <code>getTransactionParams</code>, it sets the additional parameters required by the payment gateway.
	 * the parameters will be validate. will be validated. if the parameter contains wrong value this. will be logged and ignored.
	 * @see<code>setOptionalParam</code>
	 * @param array $params the array contains the paraemters to be send to the payment gateway
	 * @return the new params
	 */
	function setAdditionalParams(array &$params, &$paymentModule){
		$alpha      = 'a-zA-ZäAöÖüÜß';
		$numeric    = '0-9';
		$punct      = '+\-_,.!?';
		$whitespace = '\s';
		$label      = "$alpha$whitespace";
		$text       = "$alpha$numeric$punct$whitespace";

		$paymentModule->logDebug("setAdditionalParams()->prefix".$paymentModule->getPrefix());

		$this->setOptionalParam('notificationfailedurl',$paymentModule,$params);

		if($paymentModule->getPrefix()==self::PREFIX_CC){


			$params['date']=$this->getDate();
			$params['locale']=$paymentModule->getLocale();

			//$params['transactiontype']=$this->transactionType;


			$this->setOptionalParam('payment_options',$paymentModule,$params);
			$this->setOptionalParam('cssurl',$paymentModule,$params,"/^.(?!.{256})$/");
			$this->setOptionalParam('acceptcountries',$paymentModule,$params,"/^(?!.{256})[A-Z]{2}(,[A-Z]{2})*$/x");

			$this->setOptionalParam('transactiontype',$paymentModule,$params,"/^(preauthorization)|(authorization)$/x");

			if($paymentModule->getTransactiontype()=='preauthorization'){
				$this->setOptionalParam('autocapture',$paymentModule,$params,"/^[$numeric]{0,3}$/");
			}

			$this->setOptionalParam('rejectcountries',$paymentModule,$params,"/^(?!.{256})[A-Z]{2}(,[A-Z]{2})*$/x");


			if(self::notEmpty($params['rejectcountries'])){
				$this->setOptionalParam('countryrejectmessage',$paymentModule,$params,"/^[$text]{0,255}$/");
			}

			if(self::notEmpty($params['deliverycountryrejectmessage'])){
				$this->setOptionalParam('deliverycountryrejectmessage',$paymentModule,$params,"/^[$text]{0,255}$/");
			}

			$this->setOptionalParam('deliverycountry',$paymentModule,$params,"/^[A-Z]{2}$/");

			if(self::notEmpty($params['deliverycountry'])){
				$this->setOptionalParam('deliverycountryaction',$paymentModule,$params,"/^[$text]{0,255}$/");
			}

			$this->setOptionalParam('form_merchantname',$paymentModule,$params,"/^[$text]{0,32}$/");
			$this->setOptionalParam('form_merchantref',$paymentModule,$params,"/^[$alpha$numeric$punct]{0,32}$/");

			$this->setOptionalParam('customer_addr_city',$paymentModule,$params);
			$this->setOptionalParam('customer_addr_number',$paymentModule,$params);
			$this->setOptionalParam('customer_addr_street',$paymentModule,$params);
			$this->setOptionalParam('customer_addr_zip',$paymentModule,$params);

			$this->setOptionalParam('form_label_cancel',$paymentModule,$params,"/^[$label]{0,30}$/");
			$this->setOptionalParam('form_label_submit',$paymentModule,$params,"/^[$label]{0,30}$/");
		}
		else if($paymentModule->getPrefix()==self::PREFIX_PP){
			$params['paymentmethod']=$paymentModule->getPaymentMethod();
			$this->setOptionalParam('notifyurl',$paymentModule,$params);
			$this->setOptionalParam('notificationfailedurl',$paymentModule,$params);
			$paymentModule->setAdditionalParamsforPayPal($params);

		}
		else if($paymentModule->getPrefix()==self::PREFIX_GP){

			$this->setOptionalParam('bic',$paymentModule,$params);
			$this->setOptionalParam('iban',$paymentModule,$params);
			$this->setOptionalParam('payment_options',$paymentModule,$params);
			$this->setOptionalParam('label0',$paymentModule,$params);
			$this->setOptionalParam('label1',$paymentModule,$params);
			$this->setOptionalParam('label2',$paymentModule,$params);
			$this->setOptionalParam('label3',$paymentModule,$params);
			$this->setOptionalParam('label4',$paymentModule,$params);

			$this->setOptionalParam('text0',$paymentModule,$params);
			$this->setOptionalParam('text1',$paymentModule,$params);
			$this->setOptionalParam('text2',$paymentModule,$params);
			$this->setOptionalParam('text3',$paymentModule,$params);
			$this->setOptionalParam('text4',$paymentModule,$params);
		}
		else if($paymentModule->getPrefix()==self::PREFIX_DD){
			$params['locale']=$paymentModule->getLocale();
			$params['date']=$this->getDate();
			$params['mandatesigned']=date('Ymd');

			$this->setOptionalParam('mandateid',$paymentModule,$params);
			$this->setOptionalParam('mandatename',$paymentModule,$params);
			//$this->setOptionalParam('mandatesigned',$paymentModule,$params);
			$this->setOptionalParam('sequencetype',$paymentModule,$params);

			$this->setOptionalParam('payment_options',$paymentModule,$params);
			$this->setOptionalParam('transactiontype',$paymentModule,$params,"/^(preauthorization)|(authorization)$/x");
			if($paymentModule->getTransactiontype()=='preauthorization'){
				$this->setOptionalParam('autocapture',$paymentModule,$params,"/^[$numeric]{0,3}$/");
			}

			$this->setOptionalParam('cssurl',$paymentModule,$params,"/^.(?!.{256})$/");
			$this->setOptionalParam('form_merchantname',$paymentModule,$params,"/^[$text]{0,32}$/");
			$this->setOptionalParam('form_label_cancel',$paymentModule,$params,"/^[$label]{0,30}$/");
			$this->setOptionalParam('form_label_submit',$paymentModule,$params,"/^[$label]{0,30}$/");
			//$this->pg_notificationfailedurl=$this->getPaymentFaildURL();
		}
		return $params;
	}

	/**
	 * this methed is used to set the transaction parameters and calculate the secret mac. for the included parameters.
	 * @return the array contains the params for the current type of transaction:
	 */
	function getTransactionParams(&$paymentModule){
		$params= $this->setCommonMandatoryParams($paymentModule);
		$params=$this->setAdditionalParams($params,$paymentModule);
		$params=$this->setMAC($params,$paymentModule);
		$paymentModule->logTransaction($this->prepareLogStringPaymentGatewayNotificationRequest($params));
		return $params;
	}


	/**
	 * This method is used to create the transaction redirect. its generate a html form element
	 * with the required hidden fields to be send to the payment gateway.
	 * @param  $paymentModule the payment module.
	 * @param  $redirectText the text to be displayed for the user.
	 * @return the generated HTML-Form element.
	 */
	function getTransactionRedirect(&$paymentModule,$redirectText=''){
		$html='<div style="width: 700px; margin-left: auto ; margin-right: auto" >';
		$html.='<form name="dpos" action="'.$this->getPaymentGatewayURL($paymentModule).'">';
		$params=$this->getTransactionParams($paymentModule);
		reset($params);
		uksort($params, 'strcasecmp');
		while (list($key, $value) = each($params)) {
			if(!is_null($value)&& $value!='')
			$html.='<input type="hidden" name="'.$key.'" value="'.$value.'">';
		}

		if(is_null($redirectText)|| $redirectText=='')
		{
			$html.='<input type="submit" value="'.$this->translateKey('REDIRECT',$paymentModule->getLocale()).'">';
		}
		else
		{
			$html.='<input type="submit" value="'.$redirectText.'">';
		}

		$html.='</form><script language="JavaScript">document.dpos.submit();</script>';
		$html.='</div>';
		$paymentModule->logDebug($html);
		return $html;
	}



	/**
	 * calculates and sets the mac parameter value for the transaction params
	 * @param array $params array of params
	 * @returnthe $paramas inclues mac
	 */
	private function setMAC(array &$params, $paymentModule){
		$secret='';
		uksort($params,'strcasecmp');

		foreach ($params as $value) {
			if(!is_null($value)&&$value!='')
				$secret.=$value;
		}

		$hmac=$this->_hmac($paymentModule->getSecret(),$secret);
		//$paymentModule->logDebug( "secret:".$paymentModule->getSecret()." hmac:".$hmac);
		$params['mac']=$hmac;
		return $params;
	}


	/**
	 * @return boolean
	 */
	public static function isTestMode(){
		if($_COOKIE['testmode']==true) {
			return  true;
		}else if($_REQUEST['testmode']=='True'){
			setcookie('testmode',true);
			return true;
		} return false;
	}




	/**
	 * @param string $key the key used to create the sha1 hash.
	 * @param string $data the string contains the data to be hashed
	 * @return string the sha1 hash
	 **/

	public static function	_hmac( $key, $data) {
		$b = 64;
		$key = str_pad($key, $b, chr(0x00));
		$ipad = str_pad('', $b, chr(0x36));
		$opad = str_pad('', $b, chr(0x5c));
		$k_ipad = $key ^ $ipad ;
		$k_opad = $key ^ $opad;
		return sha1($k_opad .pack("H*",sha1($k_ipad . $data)));
	}



	private static $NOTIFY_KEYS = array('aid','amount','basketid','currency','deliverycountry','directPosErrorCode','directPosErrorMessage','mac','orderid','rc','retrefnum','sessionid','trefnum');
	
	
		
	/**
	 * @param unknown $request
	 * @param  $paymentModule
	 * @return boolean
	 */
	static function checkMACforPaymentResponse($request,&$paymentModule){
	$mac=$request['mac']; $macstr=''; if(!is_null($request['aid']))
			$macstr.=$request['aid']; if(!is_null($request['amount']))
			$macstr.=$request['amount']; if(!is_null($request['basketid']))
			$macstr.=$request['basketid']; if(!is_null($request['currency']))
			$macstr.=$request['currency']; if(!is_null($request['deliverycountry']))
			$macstr.=$request['deliverycountry'];

		if(!is_null($request['directPosErrorCode']))
			$macstr.=$request['directPosErrorCode'];

		if(!is_null($request['directPosErrorMessage']))
			$macstr.=$request['directPosErrorMessage'];

		if(!is_null($request['orderid'])) $macstr.=$request['orderid'];

		if(!is_null($request['ppan'])) $macstr.=$request['ppan'];

		if(!is_null($request['rc'])) $macstr.=$request['rc'];

		if(!is_null($request['rcavsamex'])) $macstr.=$request['rcavsamex'];

		if(!is_null($request['rc_score'])) $macstr.=$request['rc_score'];

		if(!is_null($request['retrefnum'])) $macstr.=$request['retrefnum'];

		if(!is_null($request['sessionid'])) $macstr.=$request['sessionid'];

		if(!is_null($request['trefnum'])) $macstr.=$request['trefnum'];

		$hmac=self::_hmac($paymentModule->getSecret(), $macstr);

		if($hmac==$mac){
			return true;
		} else{
			$paymentModule->logError('checkMACforPaymentResponse()->notification-Params:'.self::prepareLogStringPaymentGatewayNotificationRequest($request));
			$paymentModule->logError('checkMACforPaymentResponse()->returns false calculated MAC:['.$hmac.'] request-MAC:'. $mac);
			return false;
		}

	}


	/**
	 * @param unknown $request
	 * @return string
	 */
	static function prepareLogStringPaymentGatewayNotificationRequest($request){
		uksort($request, 'strcasecmp'); $str='[';
		foreach($request as $key=> $value){
			$str .= $key . '='.$value.',';
		}

		if(strrpos($str, ',')==strlen($str)){
			$str=substr($str,0,strlen($str)-1);
		}

		$str.="]";
		return $str;
	}

	/**
	 * @param  $paymentModule
	 * @return string
	 */
	function preparePaymentGatewayRequest(&$paymentModule){
		$url=$this->getPaymentGatewayURL($paymentModule).'?';
		$params=$this->getTransactionParams($paymentModule);
		uksort($params,'strcasecmp');
		$str='';
		foreach ($params as $key => $value) {
			if(!is_null($value)){
				$str.=$key.'='.$value.'&';
			}
		} if(strrpos($str,'&')==strlen($str)-1){
			$str=substr($str,0,strlen($str)-1);
		}

		return $url.$str;
	}

	/**
	 * validate the request parameters by calculating the MAC for the current params and perform appropriate actions to update the
	 * status of the order
	 * @param unknown_type $request
	 * @return multitype:boolean string Ambigous <string , mixed> |multitype:boolean string
	 */
	function processPaymentGatewayNotification($request,&$paymentModule){
		$paymentModule->logDebug('processPaymentGatewayNotification()->start() ');
		$paymentModule->logDebug('processPaymentGatewayNotification()->request: '.self::prepareLogStringPaymentGatewayNotificationRequest($request));
		$directPosErrorCode=$request['directPosErrorCode'];
		$directPosErrorMessage=$request['directPosErrorMessage'];

		$rc=$this->valueOf('rc',$request);
		$amount=$this->valueOf('amount',$request);
		$currency=$this->valueOf('currency',$request);
		$url='';

		if($this->checkMACforPaymentResponse($request,$paymentModule)){
			if(!isset($request['orderid'])){
				$url= 'redirecturlf='.$this->processOnError($request['orderid'],$this->translateRCCode($rc,$directPosErrorCode,$directPosErrorMessage));
				return array('status'=>false,'msg'=>$this->translateRCCode($rc,$directPosErrorCode,$directPosErrorMessage),'redirecturl'=>$url);
			}
			else{

				$orderId=$this->splitOrderId($request['orderid']);

				if ($directPosErrorCode=='0'){
					$paymentModule->logTransaction('processPaymentGatewayNotification()-> ok');
					$url='redirecturls='.$paymentModule->processOnOk($orderId,$amount,$currency);
					$paymentModule->logTransaction('success:'.$url);
					return array('status'=>true,'msg'=>'','redirecturl'=>$url);
				} else if($directPosErrorCode=='347') {
					$paymentModule->logTransaction('processPaymentGatewayNotification()-> cancel:, directPosErrorMessage: '.$directPosErrorMessage);
					$url='redirecturlf='.$paymentModule->processOnCancel($orderId);
					$paymentModule->logTransaction('cancel:'.$url);
					return array('status'=>false,'msg'=>$this->translateRCCode($rc,$directPosErrorCode,$directPosErrorMessage),'redirecturl'=>$url);
				} else {
					$paymentModule->logTransaction('processPaymentGatewayNotification()-> error:' .$this->prepareLogStringPaymentGatewayNotificationRequest($request));
					$url='redirecturlf='.$paymentModule->processOnError($orderId,$directPosErrorMessage);
					$paymentModule->logTransaction('error:'.$url);
					return array('status'=>false,'msg'=>$this->translateRCCode($rc,$directPosErrorCode,$directPosErrorMessage),'redirecturl'=>$url);
				}
			}
		} else{
			$paymentModule->logTransaction('processPaymentGatewayNotification()-> error:' .$this->prepareLogStringPaymentGatewayNotificationRequest($request));
			$url='redirecturlf='.$paymentModule->processOnError($request['orderid'],$directPosErrorMessage);
			return array('status'=>false,'invalid mac calculatedmac','redirecturl'=>$url);
		}
	}


	/**
	 * @param unknown $rc
	 * @param unknown $directPosErrorCode
	 * @param unknown $directPosError
	 * @return Ambigous <string, unknown, mixed>
	 */
	function translateRCCode($rc,$directPosErrorCode,$directPosError){
		$error_msg='';
		if ($rc and defined('MODULE_PAYMENT_DPOS_ERROR_RC_'.$rc) === true) {
			$error_msg = constant('MODULE_PAYMENT_DPOS_ERROR_RC_'.$rc);
		} elseif
		(defined('MODULE_PAYMENT_DPOS_ERROR_'.$directPosErrorCode) === true) {
			$error_msg = constant('MODULE_PAYMENT_DPOS_ERROR_'.$directPosErrorCode);
		}else if($directPosError!=''){
			$error_msg = $directPosError ;
		} else {
			$error_msg = MODULE_PAYMENT_DPOS_ERROR_DEFAULT ;
		} return $error_msg;
	}


	/**
	 * @param unknown $options
	 * @return string
	 */
	public function validatePaymentOptions($options){
		$paymentmethods=explode('
				,;|', $options); $ok=true; foreach ($paymentmethods as $key) {
				if(!in_array($key, self::$PAYMENT_METHODS)) {
					$ok=false;
					//$this->logWarn("validatePaymentOptions: invalid PaymentOptions:".$key." allowed paymentoptions: [".implode(" | ", self::$PAYMENT_METHODS)."]!");
				}
		} return ok;
	}

	/**
	 * @param unknown $txType
	 */
	function validateTransactiontype($txType){
		if(in_array($txType, self::$TRANSACTION_TYPES)) {
			return true;
		} else{
			return false;
		}
	}

	/**
	 * @param unknown $key
	 * @param  $paymentModule
	 * @param array $params
	 * @param string $regexp
	 * @throws Exception
	 */
	function setOptionalParam($key,&$paymentModule, array &$params, $regexp=''){
		$method='get'.ucfirst($key);
		if(method_exists($paymentModule, $method)){
			$value= call_user_func(array($paymentModule, $method));
			$paymentModule->logDebug('$key '.$key.'='.$value);

			if($value!='' && strlen($value)>0){
				if($regexp!=''){
					if (preg_match($regexp, $value,$matches)) {
						$params[$key]= $matches[0];
					} else {
						$paymentModule->logDebug("parameter ".$key ." has invalid value [".$value."]!");
					}
				}else{
					$params[$key]=$value;
				}
			}
		} else{
			$paymentModule->logError('method '.$method.' is not implemented for paymenttype:');
			throw new Exception('method '.$method.' is not implemented for paymenttype:');
		}
	}


	/**
	 * @param unknown_type $params
	 * @return unknown
	 */
	private function gethmac($params){
		$secret='';
		uksort($params, 'strcasecmp');
		foreach ($params as $value) {
			if(isset($value)) $secret.=$value;
		}

		$hmac=$this->_hmac($this->sslpwd,$secret);
		return $hmac;
	}



	/**
	 * check if the param $value is not empty
	 * @param unknown $value to be ckecked
	 * @return boolean
	 */
	static function notEmpty($value){
		if
		(is_array($value)) {
			if (sizeof($value) > 0) {
				return true;
			} else {
				return false;
			}
		} else if (($value != '') && (strtolower($value) !=
				'null') && (strlen(trim($value)) > 0)) {
				return true;
		} else { return
		false;
		}
	}



	/**
	 * returns the translation for the key
	 * @param unknown $key the key to be translated (it must be set in the language files)
	 * @param string $locale  the locale 'de|en';
	 * @return Ambigous <multitype:>|string returns the translation for the key or empty if not translation was found
	 */
	static function translateKey($key,$locale='de'){
		if(self::notEmpty($key))
		{
			$translations=self::getTranslationsForLoacle($locale);
			if(!empty($translations[$key])){
				return $translations[$key];
			}
			else{
				foreach ($translations as $code=>$value){
					$code = str_replace('_{prefix}', '', $code);
					$code = str_replace('{counter}', '', $code);
					if($code===$key){
						return str_replace('{counter}', '', $value);
					}
				}
				return null;
			}
		}
	}

	/**
	 *
	 * @param unknown_type $locale
	 * @return multitype:
	 */
	static function getTranslationsForLoacle($locale='en'){
		$filename=dirname(__FILE__);
		if(strtolower($locale)=='de'){
			$filename.='/languages/de.ini';
		}
		else
		{
			$filename.='/languages/en.ini';
		}

		$translations=array();
		$content = explode("\n", file_get_contents($filename));


		foreach ($content as $line) {
			$parts = explode('=', $line);
			$translations[$parts[0]] = (!empty($parts[1]) ? $parts[1] : '');
		}

		return $translations;
	}

	/**
	 * Enter description here ...
	 * @param unknown_type $locale
	 */
	static function defineTranslations($locale='en', $paymentmodule){
		$translations=self::getTranslationsForLoacle($locale);
		if(self::notEmpty($translations))
		{
			foreach ($translations as $key=>$value){
				$key = str_replace('{prefix}', $paymentmodule->getPrefix(), $key);
				$key=$paymentmodule->getModulePrefix().$key;
				$pos=strpos($key,'{counter}');
				if($pos===false)
				{
					define($key,$value);

				}
				else{
					for($i = 0; $i <= 4; $i++)
					{
						$key2 = str_replace('{counter}', $i, $key);
						define($key2,str_replace('{counter}', $i, $value));
					}
				}
			}
		}
	}


	/**
	 * Enter description here ...
	 * @param unknown_type $locale
	 */
	static function getTranslation($code,$locale='en', $paymentmodule,$prefix=''){
		$translations=self::getTranslationsForLoacle($locale);
		$_prefix=$prefix==''?$paymentmodule->getPrefix():$prefix;

		if(self::notEmpty($translations))
		{
			foreach ($translations as $key=>$value){
				$key1 = str_replace('{prefix}', $_prefix, $key);
				$key1=$paymentmodule->getModulePrefix().$key1;
				if(strpos($key1,'{counter}'))
				{
					for($i = 0; $i <= 4; $i++)
					{
						$key2 = str_replace('{counter}', ''.$i, $key1);
						if($code===$key2)
							return str_replace('{counter}', ''.$i, $value);
					}
				}
				else{
					if($code==$key1)
						return $value;
				}
			}
		}
		return '';
	}

	/**
	 * returns the payment gateway for the current payment gateway
	 * @param  $paymentModule
	 * @return string the url to the payment gateway
	 */
	function getPaymentGatewayURL(&$paymentModule) {
		if ($paymentModule->isLiveMode()) {
			return self::LIVE_URL;
		}else{
			return self::TEST_URL;
		}
	}


	function valueOf($key,$array){
		$value='';
		if(array_key_exists($key,$array))
			$value=$array[$key];
		return $value;

	}

}

?>