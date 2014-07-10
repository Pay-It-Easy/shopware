<?php
require_once(dirname(__FILE__).'/core/PayItEasyCore.php');
class Shopware_Plugins_Frontend_PaymentPayItEasy_Bootstrap extends Shopware_Components_Plugin_Bootstrap
{
	public static $paymentMethods = array('CC' ,'DD' ,'GP' ,'PP' );

	public static function onSaveForm(Enlight_Hook_HookArgs $args)
	{
		$class = $args->getSubject();
		$request = $class->Request();
		$pluginId = (int)$request->id;
		$elements = $request->getPost('elements');

		$locale=substr(Shopware()->Locale(),0,2);

		if($locale=='')
			$locale='de';
		Shopware()->Loader()->registerNamespace('Shopware_Components_PaymentPayItEasy', dirname(__FILE__) . '/Components/PayItEasy/');
		
		foreach ($elements as $element_id => $element_data) {
			foreach (self::$paymentMethods as $pAbbrMethod) {
				if ($element_data['name'] != strtolower ('PayItEasy_') . strtolower($pAbbrMethod)) {
					continue;
				}

				$pMethodElement = new Shopware_Components_PaymentPayItEasy_Checkbox(strtolower ('PayItEasy_') . strtolower($pAbbrMethod), $pluginId);
				$pMethodElement->setValue($element_data['values'][0]['value']);
				$pMethodElement->description = PayItEasyCore::translateKey('PUBLIC_TITLE_'.$pAbbrMethod,$locale);
				/*
				 if (self::$logos[$pAbbrMethod]) {
				$pMethodElement->logoName = self::$logos[$pAbbrMethod];
				}
				*/
				$pMethodElement->save();
			}
		}
	}

	protected function createPayments()
	{
		$locale=substr(Shopware()->Locale(),0,2);
		if($locale=='')
			$locale='de';

		$payment = Shopware()->Payments()->fetchRow(array('name=?' => strtolower ('PayItEasy')));

		if (!$payment) {
			Shopware()->Payments()->createRow(
			array('name' => strtolower ('PayItEasy'),
			'description' =>  PayItEasyCore::translateKey('PUBLIC_TITLE_BASE'.$prefix,$locale),
			'action' => 'payment_'.strtolower ('PayItEasy'),
			'active' => 1,
			'pluginID' => $this->getId(),
			'additionaldescription' => '<img src="engine/Shopware/Plugins/Local/Frontend/PaymentPayItEasy/images/logo_small.png"/><br/>'))->save();
		}
	}

	public function install()
	{
		Shopware()->Loader()->registerNamespace('Shopware_Components_PaymentPayItEasy', dirname(__FILE__) . '/Components/PayItEasy/');

		Shopware()->Template()->addTemplateDir(dirname(__FILE__) . '/Views/');

		$event = $this->createEvent('Enlight_Controller_Action_PostDispatch', 'onPostDispatch');
		$this->subscribeEvent($event);

		$event = $this->createEvent('Enlight_Controller_Dispatcher_ControllerPath_Frontend_PaymentPayItEasy', 'onGetControllerPath');
		$this->subscribeEvent($event);

//		$event = $this->createEvent( 'Shopware_Modules_Order_SendMail_Send','onSendMail_Send');
//		$this->subscribeEvent($event);


		$this->createPayments();
		$this->createForm();

		return true;
	}


public function update($oldVersion)
{
    $form = $this->Form();

    $local=substr(Shopware()->Locale(),0,2);

	if($local=='')
		$local='de';

    //Der Parameter $oldVersion gibt die Plugin-Version an, die vor dem Update
    //auf dem System installiert ist. Somit kann unterschieden werden, welche
    //Aktionen noch ausgeführt werden müssen.
    // Die neue Version des Plugins kann wie gewohnt über $this->getVersion() abgefragt werden.

    //Als Kontrollstruktur bietet sich hier eine Switch an.
    //Durch das Weglassen von breaks der switch können Sie
    //den Einstiegspunkt optimal definieren. Fangen Sie hierbei
    //mit der kleinsten Version an, in unserem Beispiel die Version 1.0.0
    switch ($oldVersion) {
        case '1.0.0':
        case '1.0.1':
        case '1.0.2':

        $form->setElement('checkbox', 'ageverification', array(
				'label' => PayItEasyCore::translateKey('AGEVERIFICATION_TITLE',$local),
				'scope' => \Shopware\Models\Config\Element::SCOPE_SHOP,
				'value' => false)
		);

		$form->setElement('text', 'mandateprefix_DD', array('label' => PayItEasyCore::translateKey('MANDATEPREFIX_TITLE',$local), 'required' => true));
		$form->setElement('text', 'mandatename_DD', array('label' => PayItEasyCore::translateKey('MANDATENAME_TITLE',$local), 'required' => false));
		$form->setElement('select', 'sequencetype_DD', array(
				'label' => PayItEasyCore::translateKey('SEQUENCETYPE_TITLE_DD',$local),
				'scope' => \Shopware\Models\Config\Element::SCOPE_SHOP,
				'value' => 'oneoff',
				'store' => array(
						array("oneoff", "oneoff"),
						array("first","first"),
						array("recurring","recurring"),
						array("final","final")
				)
		));

		break;
        default:
            //Die installierte Version entspricht weder 1.0.0 noch 1.0.1
            //Aus diesem Grund wird dem Plugin-Manaager mitgeteilt,
            //dass das Update fehlgeschlagen ist.
            return true;
    }

    //Nachdem das Update durchgelaufen ist muss true zurückgegeben
    //werden, um das Update abzuschließen
    return true;
}

	public function uninstall()
	{
		
		Shopware()->Loader()->registerNamespace('Shopware_Components_PaymentPayItEasy', dirname(__FILE__) . '/Components/PayItEasy/');
		
		if ($payment = $this->Payment()) {
			$payment->delete();
		}

		$form = $this->Form();

		foreach (self::$paymentMethods as $pAbbrMethod) {
			$pMethodElement = $form->getElement(strtolower ('PayItEasy').'_' . strtolower($pAbbrMethod));
			if (!$pMethodElement) {
				continue;
			}

			$pMethodNew = new Shopware_Components_PaymentPayItEasy_Checkbox(strtolower ('PayItEasy').'_' . strtolower($pAbbrMethod), $this->getId());
			$pMethodNew->deletePayment();
		}

		return parent::uninstall();
	}

	public function enable()
	{
		$payment = $this->Payment();
		if ($payment !== null) {
			$payment->active = 1;
		}

		return true;
	}

	public function disable()
	{
		$payment = $this->Payment();
		if ($payment !== null) {
			$payment->active = 0;
		}
		return true;
	}

	public function Payment()
	{
		return Shopware()->Payments()->fetchRow(array('name=?' => strtolower ('PayItEasy')));
	}

	public static function onGetControllerPath(Enlight_Event_EventArgs $args)
	{
		Shopware()->Template()->addTemplateDir(dirname(__FILE__) . '/Views/');
		return dirname(__FILE__) . '/Controllers/frontend/PayItEasy.php';
	}


/*
 * 	public static function onSendMail_Send(Enlight_Event_EventArgs $args)
	{

		$paymentId=(int)$args['variables']['additional']["user"]["paymentID"];
		$sql = 'select p.name from s_core_plugins p join s_core_paymentmeans m on m.pluginID =p.id where m.id=?';
		$name = Shopware()->Db()->fetchOne($sql, array($paymentId));

		if($name<>'PaymentPayItEasy')
			return null;
		else
			return false;
	}
*/


	public static function onPostDispatch(Enlight_Event_EventArgs $args)
	{
		$request = $args->getSubject()->Request();
		$response = $args->getSubject()->Response();
		$view = $args->getSubject()->View();

		if ($request->getActionName() == 'saveForm' && $request->getModuleName() == 'backend' && $request->getControllerName() == 'config') {
			self::onSaveForm($args);
			return;
		}

		Shopware()->Template()->addTemplateDir(dirname(__FILE__) . '/Views/');

		if (!$request->isDispatched() || $response->isException() || $request->getModuleName() != 'frontend' || !$view->hasTemplate()) {
			return;
		}
	}

	function createPaymentElement(&$form,$prefix,$local='de'){

		$form->setElement('button', 'button_'.$prefix, array(
				'label' => '<b style="width: 800px;">'
				. PayItEasyCore::translateKey('TITLE_'.$prefix,$local).'</b>',
				'value' => ''
		));

		$pMethodElement = new Shopware_Components_PaymentPayItEasy_Checkbox(strtolower ('PayItEasy').'_' . $prefix, $this->getId());
		$pMethodElement->setLabel(PayItEasyCore::translateKey('PUBLIC_TITLE_'.$prefix,$local));
		$pMethodElement->description = PayItEasyCore::translateKey('PUBLIC_TITLE_'.$prefix,$local);
		/*
		 if (self::$logos[$pAbbrMethod]) {
		$pMethodElement->logoName = self::$logos[$pAbbrMethod];
		}
		*/
		$pMethodElement->setValue(false);
		$pMethodElement->save();

		$form->setElement('checkbox', $pMethodElement->name, array('label' => $pMethodElement->description, 'value' => false));
		$form->setElement('checkbox', 'test_mode_'.$prefix, array('label' => PayItEasyCore::translateKey('TEST_MODE_TITLE',$local), 'value' => true));


	}

	public function createForm()
	{
		$form = $this->Form();
		$local=substr(Shopware()->Locale(),0,2);

		if($local=='')
			$local='de';



		$form->setElement('button', 'button_1', array(
				'label' => '<b style="width: 800px;">'.	PayItEasyCore::translateKey('TITLE_BASE',$local).'</b>',
				'value' => ''
		));


		$form->setElement('text', 'sslmerchant', array('label' => PayItEasyCore::translateKey('SSLMERCHANT_TITLE',$local), 'required' => true));
		$form->setElement('text', 'secret', array('label' => PayItEasyCore::translateKey('SECRET_TITLE',$local), 'value' => '', 'required' => true));
		$form->setElement('text', 'form_merchantname', array('label' => PayItEasyCore::translateKey('FORM_MERCHANTNAME_TITLE',$local)));


		$form->setElement('text', 'cssurl', array('label' => PayItEasyCore::translateKey('CSSURL_TITLE',$local)));

		$form->setElement('select', 'debug', array(
				'label' => PayItEasyCore::translateKey('DEBUG_TITLE',$local),
				'scope' => \Shopware\Models\Config\Element::SCOPE_SHOP,
				'value' => 'off',
				'store' => array(
						array("Off", "off"),
						array("Transaction","Transaction"),
						array("debug","Entwicklung")
				)
		));

		$form->setElement('text', 'debug_file_path', array('label' => PayItEasyCore::translateKey('DEBUG_FILE_PATH_TITLE',$local)));

		self::createPaymentElement($form,'CC');

		$form->setElement('text', 'acceptcountries', array('label' => PayItEasyCore::translateKey('ACCEPTCOUNTRIES_TITLE',$local)));
		$form->setElement('text', 'rejectcountries', array('label' => PayItEasyCore::translateKey('REJECTCOUNTRIES_TITLE',$local)));

		$form->setElement('select', 'transactiontype_CC', array(
				'label' =>  PayItEasyCore::translateKey('TRANSACTIONTYPE_TITLE',$local),
				'scope' => \Shopware\Models\Config\Element::SCOPE_SHOP,
				'value' => 'authorization',
				'store' => array(
						array("authorization", "authorization"),
						array("preauthorization","preauthorization")
				)
		));

		$form->setElement('select', 'deliverycountry_action_CC', array(
				'label' =>  PayItEasyCore::translateKey('DELIVERYCOUNTRY_ACTION_TITLE',$local),
				'scope' => \Shopware\Models\Config\Element::SCOPE_SHOP,
				'value' => 'notify',
				'store' => array(
						array("notify","notify"),
						array("reject", "reject")
				)
		));

		$form->setElement('number', 'autocapture_CC', array('minValue'=>'0','maxValue'=>'168','label' => PayItEasyCore::translateKey('AUTOCAPTURE_TITLE',$local)));

		$form->setElement('text', 'form_merchantref', array('label' => PayItEasyCore::translateKey('FORM_MERCHANTREF_TITLE',$local)));

		$form->setElement('text', 'paymentoptions_CC', array('label' => PayItEasyCore::translateKey('PAYMENT_OPTIONS_TITLE',$local)));

		self::createPaymentElement($form,'DD');

		$form->setElement('select', 'transactiontype_DD', array(
				'label' =>  PayItEasyCore::translateKey('TRANSACTIONTYPE_TITLE',$local),
				'scope' => \Shopware\Models\Config\Element::SCOPE_SHOP,
				'value' => 'authorization',
				'store' => array(
						array("authorization", "authorization"),
						array("preauthorization","preauthorization")
				)
		));

		$form->setElement('number', 'autocapture_DD', array('minValue'=>'0','maxValue'=>'720','label'=> PayItEasyCore::translateKey('AUTOCAPTURE_TITLE',$local)));
		$form->setElement('text', 'paymentoptions_DD', array('label' => PayItEasyCore::translateKey('PAYMENT_OPTIONS_TITLE',$local)));

		$form->setElement('text', 'mandateprefix_DD', array('label' => PayItEasyCore::translateKey('MANDATEPREFIX_TITLE',$local), 'required' => false));
		$form->setElement('text', 'mandatename_DD', array('label' => PayItEasyCore::translateKey('MANDATENAME_TITLE',$local), 'required' => true));

		$form->setElement('select', 'sequencetype_DD', array(
				'label' => PayItEasyCore::translateKey('SEQUENCETYPE_TITLE',$local),
				'scope' => \Shopware\Models\Config\Element::SCOPE_SHOP,
				'value' => 'oneoff',
				'store' => array(
						array("oneoff", "oneoff"),
						array("first","first"),
						array("recurring","recurring"),
						array("final","final")
				)
		));


		// 		const NOTIFICATION_FAILED_URL='notificationfailedurl';
		// 		const NOTIFYURL='notifyurl';

		// 		const COUNTRYREJECTMESSAGE='countryrejectmessage';

		// 		const DELIVERYCOUNTRY_ACTION='deliverycountry_action';
		// 		const FORM_LABEL_SUBMIT='form_label_submit';
		// 		const FORM_LABEL_CANCEL='form_label_cancel';
		// 		const DELIVERYCOUNTRY_REJECT_MESSAGE='deliverycountry_reject_message';
		// 		const PAYMENTOPTIONS='payment_options';

		self::createPaymentElement($form,'GP');

/*		$form->setElement(
		'select', 'paymentoptions_GP', array(
				'label' => PayItEasyCore::translateKey('PAYMENT_OPTIONS_GP_TITLE',$local),
				'scope' => \Shopware\Models\Config\Element::SCOPE_SHOP,
				'value' => '',
				'store' => array(
						array("avsopen", PayItEasyCore::translateKey('YES',$local)),
						array("",PayItEasyCore::translateKey('NO',$local))
				)
		));

*/

 		$form->setElement('checkbox', 'ageverification', array(
				'label' => PayItEasyCore::translateKey('AGEVERIFICATION_TITLE',$local),
				'scope' => \Shopware\Models\Config\Element::SCOPE_SHOP,
				'value' => false)
		);





		for ($i = 0; $i <= 4; $i++) {
			$form->setElement('text', 'label'.$i, array('label' => PayItEasyCore::translateKey('LABEL_TITLE',$local).$i));
			$form->setElement('text', 'text'.$i, array('label' => PayItEasyCore::translateKey('TEXT_TITLE',$local).$i));
		}

		self::createPaymentElement($form,'PP');


	}

	public function getVersion()
	{
		return '1.2.0';
	}

	public function getInfo()
	{
		return array('version'	=> $this->getVersion(),
				'autor'	=> '',
				'label'	=>  'Pay It Easy',
				'source'	=> $this->getSource(),
				'description'=> '<img src="data:image/png;base64,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 AABJRU5ErkJggg=="/>',
				'license'	=> '',
				'support'	=> '',
				'link'	=> '',
				'changes'	=> '[changelog]',
				'copyright'=>'',
				'revision'	=> '[revision]');
	}

}