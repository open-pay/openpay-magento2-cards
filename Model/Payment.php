<?php

/**
 * Openpay_Cards payment method model
 *
 * @category    Openpay
 * @package     Openpay_Cards
 * @author      Federico Balderas
 * @copyright   Openpay (http://openpay.mx)
 * @license     http://www.apache.org/licenses/LICENSE-2.0  Apache License Version 2.0
 */

namespace Openpay\Cards\Model;

use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Payment\Helper\Data;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Payment\Model\Method\Cc;
use Magento\Payment\Model\Method\Logger;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Directory\Model\CountryFactory;
use Magento\Store\Model\StoreManagerInterface;
use Openpay\Cards\Model\Utils\AddressFormat;
use Openpay\Cards\Model\Utils\ProductFormat;
use Openpay\Cards\Model\Utils\OpenpayRequest;
use Openpay\Cards\Model\Utils\Currency;
use Magento\Framework\App\Request\Http;

use Magento\Customer\Model\Customer;
use Magento\Customer\Model\Session as CustomerSession;

use Openpay\Data\Openpay;
use Openpay\Data\OpenpayApiTransactionError;
use Openpay\Data\OpenpayApiConnectionError;

class Payment extends Cc
{

    const CODE = 'openpay_cards';

    protected $_code = self::CODE;
    protected $_isGateway = true;
    protected $_canOrder = true;
    protected $_canCapture = true;
    protected $_canCapturePartial = true;
    protected $_canRefund = true;
    protected $_canRefundInvoicePartial = true;
    protected $_canAuthorize = true;
    protected $_canVoid = true;
    protected $openpay = false;
    protected $is_sandbox;
    protected $country;
    protected $use_card_points;
    protected $merchant_id = null;
    protected $pk = null;
    protected $sk = null;
    protected $sandbox_merchant_id;
    protected $sandbox_sk;
    protected $sandbox_pk;
    protected $live_merchant_id;
    protected $live_sk;
    protected $live_pk;
    protected $country_factory;
    protected $scopeConfig;
    protected $supported_currency_codes = array('USD', 'MXN');
    protected $months_interest_free;
    protected $charge_type;
    protected $logger;
    protected $_storeManager;
    protected $save_cc;
    protected $iva = 0;
    protected $minimum_amounts = 0;
    protected $config_months;
    protected $processing_openpay = '';
    protected $pending_payment_openpay = '';
    protected $canceled_openpay = '';
    protected $isAvailableInstallments;
    protected $openpayRequest;
    protected $request;


    /**
     * @var Currency
     */
    protected $currencyUtils;

    /**
     * @var Customer
     */
    protected $customerModel;
    /**
     * @var CustomerSession
     */
    protected $customerSession;

    protected $openpayCustomerFactory;

    /**
    *  @var WriterInterface
    */
    protected $configWriter;

    protected $addressFormat;
    protected $productFormat;

    /**
     *
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory
     * @param \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory
     * @param \Magento\Payment\Helper\Data $paymentData
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Payment\Model\Method\Logger $logger
     * @param \Magento\Framework\Module\ModuleListInterface $moduleList
     * @param \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate
     * @param \Magento\Directory\Model\CountryFactory $countryFactory
     * @param Openpay $openpay
     * @param array $data
     * @param \Magento\Store\Model\StoreManagerInterface $data
     * @param WriterInterface $configWriter
     * @param AddressFormat $addressFormat
     * @param ProductFormat $productFormat
     * @param OpenpayRequest $openpayRequest
     * @param Http $request
     * @param Currency $currencyUtils
     */
    public function __construct(
            StoreManagerInterface $storeManager,
            Context $context,
            Registry $registry,
            ExtensionAttributesFactory $extensionFactory,
            AttributeValueFactory $customAttributeFactory,
            Data $paymentData,
            ScopeConfigInterface $scopeConfig,
            WriterInterface $configWriter,
            Logger $logger,
            ModuleListInterface $moduleList,
            TimezoneInterface $localeDate,
            CountryFactory $countryFactory,
            Openpay $openpay,
            \Openpay\Cards\Logger\Logger $logger_interface,
            Customer $customerModel,
            CustomerSession $customerSession,
            \Openpay\Cards\Model\OpenpayCustomerFactory $openpayCustomerFactory,
            AddressFormat $addressFormat,
            ProductFormat $productFormat,
            OpenpayRequest $openpayRequest,
            Currency $currencyUtils,
            Http $request,
            array $data = array()
    ) {

        parent::__construct(
                $context, $registry, $extensionFactory, $customAttributeFactory, $paymentData, $scopeConfig, $logger, $moduleList, $localeDate, null, null, $data
        );

        $this->customerModel = $customerModel;
        $this->customerSession = $customerSession;
        $this->openpayCustomerFactory = $openpayCustomerFactory;

        $this->_storeManager = $storeManager;
        $this->logger = $logger_interface;

        $this->scopeConfig = $scopeConfig;
        $this->configWriter = $configWriter;
        $this->country_factory = $countryFactory;

        $this->is_active = $this->getConfigData('active');
        $this->is_sandbox = $this->getConfigData('is_sandbox');
        $this->country = $this->getConfigData('country');

        // Se realiza mejora de if ternario
        $this->_canRefund = $this->country === 'MX' || $this->country === 'PE';
        $this->_canRefundInvoicePartial = $this->country === 'MX' || $this->country === 'PE';

        $this->sandbox_merchant_id = $this->getConfigData('sandbox_merchant_id');
        $this->sandbox_sk = $this->getConfigData('sandbox_sk');
        $this->sandbox_pk = $this->getConfigData('sandbox_pk');
        $this->live_merchant_id = $this->getConfigData('live_merchant_id');
        $this->live_sk = $this->getConfigData('live_sk');
        $this->live_pk = $this->getConfigData('live_pk');

        $this->merchant_id = $this->is_sandbox ? $this->sandbox_merchant_id : $this->live_merchant_id;
        $this->sk = $this->is_sandbox ? $this->sandbox_sk : $this->live_sk;
        $this->pk = $this->is_sandbox ? $this->sandbox_pk : $this->live_pk;
        $this->months_interest_free = $this->country === 'MX' ? $this->getConfigData('interest_free') : '1';
        $this->isAvailableInstallments = $this->country === 'PE' ?  $this->getConfigData('installments') : false;
        $this->use_card_points = $this->country === 'MX' ? $this->getConfigData('use_card_points') : '0';
        $this->iva = $this->country === 'CO' ? $this->getConfigData('iva') : '0';

        // Se valida por pais para COF
        if ($this->country === "MX") {
            $this->save_cc = $this->getConfigData('save_cc_mx');
        }

        if ($this->country === "CO") {
            $this->save_cc = $this->getConfigData('save_cc_co');
        }

        if ($this->country === "PE") {
            $this->save_cc = $this->getConfigData('save_cc_pe');
        }

        $this->minimum_amounts = $this->getConfigData('minimum_amounts');
        $this->config_months = $this->minimum_amounts ? array(
                                        "3" => $this->getConfigData('three_months'),
                                        "6" => $this->getConfigData('six_months'),
                                        "9" => $this->getConfigData('nine_months'),
                                        "12" => $this->getConfigData('twelve_months'),
                                        "18" => $this->getConfigData('eighteen_months')
        ) : null;

        $this->charge_type = $this->country === "MX" ? $this->getConfigData('charge_type_mx') : $this->getConfigData('charge_type_co_pe');

        $this->affiliation_bbva = $this->getConfigData('affiliation_bbva');
        $this->request = $request;

        $classification = 'Openpay';


        $this->addressFormat = $addressFormat;
        $this->productFormat = $productFormat;

        $this->processing_openpay = $this->getConfigData('processing_openpay');
        $this->pending_payment_openpay = $this->getConfigData('pending_payment_openpay');
        $this->canceled_openpay = $this->getConfigData('canceled_openpay');

        $this->openpay = $openpay;
        $this->openpayRequest = $openpayRequest;

        $this->currencyUtils = $currencyUtils;

    }

    /**
     * Get Ip of client
     */
    public function getIpClient(){
        // Recogemos la IP de la cabecera de la conexión
        if (!empty($_SERVER['HTTP_CLIENT_IP']))
        {
            $ipAdress = $_SERVER['HTTP_CLIENT_IP'];
            $this->logger->debug('#HTTP_CLIENT_IP', array('$IP' => $ipAdress));
        }
        // Caso en que la IP llega a través de un Proxy
        elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))
        {
            $ipAdress = $_SERVER['HTTP_X_FORWARDED_FOR'];
            $this->logger->debug('#HTTP_X_FORWARDED_FOR', array('$IP' => $ipAdress));
        }
        // Caso en que la IP lleva a través de la cabecera de conexión remota
        else
        {
            $ipAdress = $_SERVER['REMOTE_ADDR'];
            $this->logger->debug('#REMOTE_ADDR', array('$IP' => $ipAdress));
        }
        $ipAdress = explode(",", $ipAdress) [0];
        return $ipAdress;
    }

    /**
     * Validate payment method information object
     *
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function validate() {
        $info = $this->getInfoInstance();
        $openpay_cc = $info->getAdditionalInformation('openpay_cc');
        $errorMsg = false;

        switch ($this->country) {
            case "MX":
                $availableTypes = explode(',', $this->getConfigData('cctypes_mx'));
            break;
            case "CO":
                $availableTypes = explode(',', $this->getConfigData('cctypes_co'));
            break;
            case "PE":
                $availableTypes = explode(',', $this->getConfigData('cctypes_pe'));
            break;
        }

        $this->logger->debug('#validate', array('$openpay_cc' => $openpay_cc, 'getCcType' => $info->getCcType()));

        // Custom Validation

        /** CC_number validation is not done because it should not get into the server * */
        if ($info->getCcType() != null && !in_array($info->getCcType(), $availableTypes)) {
            $errorMsg = 'Credit card type is not allowed for this payment method.';
        }

        if ($errorMsg) {
            $this->logger->error('captureOpenpayTransaction', array('#ERROR validate() => ' => $errorMsg));
            throw new \Magento\Framework\Exception\LocalizedException(__($errorMsg));
        }

        return $this;
    }

    /**
     * Assign corresponding data
     *
     * @param \Magento\Framework\DataObject|mixed $data
     * @return $this
     * @throws LocalizedException
     */
    public function assignData(\Magento\Framework\DataObject $data) {
        parent::assignData($data);

        $infoInstance = $this->getInfoInstance();
        $additionalData = ($data->getData('additional_data') != null) ? $data->getData('additional_data') : $data->getData();

        $infoInstance->setAdditionalInformation('device_session_id',
            $additionalData['device_session_id'] ?? null
        );
        $infoInstance->setAdditionalInformation('openpay_token', $additionalData['openpay_token'] ?? null);
        $infoInstance->setAdditionalInformation('interest_free',
            isset($additionalData['interest_free']) ? $additionalData['interest_free'] : null
        );
        $infoInstance->setAdditionalInformation('use_card_points',
            isset($additionalData['use_card_points']) ? $additionalData['use_card_points'] : null
        );
        $infoInstance->setAdditionalInformation('installments',
            isset($additionalData['installments']) ? $additionalData['installments'] : null
        );

        $infoInstance->setAdditionalInformation('save_cc',
            isset($additionalData['save_cc']) ? $additionalData['save_cc'] : null
        );
        $infoInstance->setAdditionalInformation('openpay_cc',
            isset($additionalData['openpay_cc']) ? $additionalData['openpay_cc'] : null
        );
        $infoInstance->setAdditionalInformation('cc_cid',
            isset($additionalData['cc_cid']) ? $additionalData['cc_cid'] : null
        );

        $infoInstance->setAdditionalInformation('with_interest',
            isset($additionalData['with_interest']) ? $additionalData['with_interest'] : null
        );

        return $this;
    }

    /**
     * Refund capture
     *
     * @param \Magento\Framework\DataObject|\Magento\Payment\Model\InfoInterface|Payment $payment
     * @param float $amount
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount){
        $order = $payment->getOrder();
        $trx_id = $order->getExtOrderId();
        $customer_id = $order->getExtCustomerId();

        $this->logger->debug('#refund', array('$trx_id' => $trx_id, '$customer_id' => $customer_id, '$order_id' => $order->getIncrementId(), '$status' => $order->getStatus(), '$amount' => $amount));

        if ($amount <= 0) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Invalid amount for refund.'));
        }

        try {
            $refundData = array(
                'description' => 'Reembolso',
                'amount' => $amount
            );

//            $openpay = $this->getOpenpayInstance();
//            $charge = $openpay->charges->get($trx_id);
            $charge = $this->getOpenpayCharge($trx_id, $customer_id);
            $charge->refund($refundData);
        } catch (OpenpayApiConnectionError $e) {
            $this->_logger->error('#refund Exception (charge->refund)', array('message' => $e->getMessage()));
            throw new \Magento\Framework\Validator\Exception(__('Ocurrió un error interno. Intente más tarde.'));
        } catch (\Exception $e) {
            throw new \Magento\Framework\Exception\LocalizedException(__($e->getMessage()));
        }

        return $this;
    }


    /**
     * Send authorize request to gateway
     *
     * @param \Magento\Framework\DataObject|\Magento\Payment\Model\InfoInterface $payment
     * @param  float $amount
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function authorize(\Magento\Payment\Model\InfoInterface $payment, $amount) {
        $order = $payment->getOrder();
        $this->logger->debug('#authorize', array('$order_id' => $order->getIncrementId(), '$status' => $order->getStatus(), '$amount' => $amount));
        $payment->setAdditionalInformation('payment_type', $this->getConfigData('payment_action'));
        $payment->setIsTransactionClosed(false);
        $payment->setSkipOrderProcessing(true);
        $this->processCapture($payment, $amount);
        return $this;
    }

    /**
     * Send capture request to gateway
     *
     * @param \Magento\Framework\DataObject|\Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount) {
        $order = $payment->getOrder();
        $this->logger->debug('#capture', array('$order_id' => $order->getIncrementId(), '$trx_id' => $payment->getLastTransId(), '$status' => $order->getStatus(), '$amount' => $amount));

        if ($amount <= 0) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Invalid amount for capture.'));
        }

        $payment->setAmount($amount);
        if(!$payment->getLastTransId()){
            $this->processCapture($payment, $amount);
        } else {
            $this->captureOpenpayTransaction($payment, $amount);
        }

        return $this;
    }

    protected function captureOpenpayTransaction(\Magento\Payment\Model\InfoInterface $payment, $amount){
        $order = $payment->getOrder();
        $customer_id = $order->getExtCustomerId();
        $trx = str_replace('-capture', '', $payment->getLastTransId());

        $this->logger->debug('#captureOpenpayTransaction', array('$trx_id' => $trx, '$customer_id' => $customer_id, '$order_id' => $order->getIncrementId(), '$status' => $order->getStatus(), '$amount' => $amount));

        try {
            $order->addStatusHistoryComment("Pago recibido exitosamente")->setIsCustomerNotified(true);
            $charge = $this->getOpenpayCharge($trx, $customer_id);
            $captureData = array('amount' => $amount);
            $charge->capture($captureData);

            return $charge;
        } catch (\Exception $e) {
            $this->logger->error('captureOpenpayTransaction', array('message' => $e->getMessage(), 'code' => $e->getCode()));
            throw new \Magento\Framework\Validator\Exception(__($e->getMessage()));
        }
    }


    /**
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws \Magento\Framework\Validator\Exception
     */
    public function processCapture(\Magento\Payment\Model\InfoInterface $payment, $amount) {
        unset($_SESSION['pdf_url']);
        unset($_SESSION['show_map']);
        unset($_SESSION['openpay_3d_secure_url']);
        unset($_SESSION['openpay_pse_redirect_url']);

        $base_url = $this->_storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB);  // URL de la tienda

        /** @var \Magento\Sales\Model\Order $order */
        $order = $payment->getOrder();

        $this->logger->debug('#processCapture', array('charge_type' => $this->charge_type,'$order_id' => $order->getIncrementId(), '$status' => $order->getStatus(), '$amount' => $amount));

        /** @var \Magento\Sales\Model\Order\Address $billing */
        $billing = $order->getBillingAddress();

        /** @var \Magento\Sales\Model\Order\Address $billing */
        $shipping = $order->getShippingAddress();

        $origin_channel = "PLUGIN_MAGENTO";

        $capture = $this->getConfigData('payment_action') == 'authorize_capture' ? true : false;
        $token = $this->getInfoInstance()->getAdditionalInformation('openpay_token');
        $device_session_id = $this->getInfoInstance()->getAdditionalInformation('device_session_id');
        $use_card_points = $this->getInfoInstance()->getAdditionalInformation('use_card_points');
        // Valida si esta chekeado guardar tarjeta
        $save_cc = $this->getInfoInstance()->getAdditionalInformation('save_cc');
        $openpay_cc = $this->getInfoInstance()->getAdditionalInformation('openpay_cc');
        $cvv2 = $this->getInfoInstance()->getAdditionalInformation('cc_cid');
        $card_number = $payment->getData('cc_number');

        if (!$token && (!$openpay_cc || $openpay_cc == 'new')) {
            $msg = 'ERROR 100 Please specify card info';
            throw new \Magento\Framework\Validator\Exception(__($msg));
        }

        $this->logger->debug('#processCapture', array('$openpay_cc' => $openpay_cc, '$save_cc' => $save_cc, '$device_session_id' => $device_session_id));

        $customer_data = array(
            'requires_account' => false,
            'name' => $billing->getFirstname(),
            'last_name' => $billing->getLastname(),
            'phone_number' => $billing->getTelephone(),
            'email' => $order->getCustomerEmail()
        );

        if ($this->validateAddress($billing)) {
            $nameAddressField = $this->addressFormat::getNameAddressFieldByCountry($this->country);
            $customer_data[$nameAddressField] = $this->addressFormat::formatAddress($billing, $this->country);
        }

        $charge_request = array(
            'method' => 'card',
            'currency' => strtolower($order->getBaseCurrencyCode()),
            'amount' => $amount,
            'description' => sprintf('#%s, %s', $order->getIncrementId(), $order->getCustomerEmail()),
            'order_id' => $order->getIncrementId(),
            'source_id' => $token,
            'device_session_id' => $device_session_id,
            'customer' => $customer_data,
            'capture' => $capture,
            'origin_channel' => $origin_channel
        );

            $charge_request['use_card_points'] = $use_card_points;
            // 3D Secure

            $this->logger->debug("COMO PROCESAR EL CARGO (444d) ". $this->getConfigData('charge_type'));
            $this->logger->debug("3DS ACTIVO (444d) ". $this->charge_type);
            if ($this->charge_type == '3d') {
                $charge_request['use_3d_secure'] = true;
                $charge_request['redirect_url'] = $base_url.'openpay/payment/success';
            }

        if ($this->country === 'CO') {
            $charge_request['iva'] = $this->iva;
        }

        // Meses sin intereses (solo para MX)
        $interest_free = $this->getInfoInstance()->getAdditionalInformation('interest_free');
        if($interest_free > 1 && $this->country === 'MX'){
            $charge_request['payment_plan'] = array('payments' => (int)$interest_free);
        }

        // Pago en cuotas (solo para CO y PE)
        $installments = $this->getInfoInstance()->getAdditionalInformation('installments');
        $withInterest = $this->getInfoInstance()->getAdditionalInformation('with_interest');
        $this->logger->debug('#installments', array('$installments' => $installments));
        $this->logger->debug('#withInterest', array('$withInterest' => $withInterest));
        $isInstallmentsCO = $this->country === 'CO';
        $isInstallmentsPE = $this->country === 'PE' && $this->isAvailableInstallments;
        if($installments > 1 && ($isInstallmentsCO || $isInstallmentsPE)){
            $charge_request['payment_plan'] = array('payments' => (int)$installments);
                switch ($withInterest){
                    case "false":
                        $charge_request['payment_plan']['payments_type'] = 'WITHOUT_INTEREST';
                        break;
                    case "true":
                        $charge_request['payment_plan']['payments_type'] = 'WITH_INTEREST';
                        break;
                }
        }

        try {
            $openpayCustomerFactory = $this->customerSession->isLoggedIn() ? $this->hasOpenpayAccount($this->customerSession->getCustomer()->getId()) : null;
            $openpay_customer_id = $openpayCustomerFactory ? $openpayCustomerFactory->openpay_id : null;

            // Valida una nueva tarjeta para guardar sin cvv -- $save_cc es del checkbox 'guardar tarjeta'
            if ($save_cc == '1' && $openpay_cc == 'new') {
                $charge_request['source_id'] = $this->validateNewCard($customer_data, $charge_request, $token, $device_session_id, $card_number);
                $this->logger->debug("SAVE_CC GUARDAR ". $save_cc);
                $_SESSION['card_new'] = '1';
            }
            // valida una tarjeta guardada para actualizarla
            if ($this->save_cc == '1' && $openpay_cc != 'new') {
                $this->logger->debug('SAVE_CC UPDATE '.$save_cc. '$openpay_cc '.$openpay_cc);
                $path = sprintf('/%s/customers/%s/cards/%s', $this->merchant_id, $openpay_customer_id, $openpay_cc);
                $dataCVV = $this->openpayRequest->make($path, $this->country, $this->is_sandbox, 'PUT', [
                        'cvv2' => $cvv2
                    ],
                    [
                        'sk' => $this->sk
                    ]);
                if($dataCVV->http_code != 200) {
                    throw new \Magento\Framework\Validator\Exception(__('Error al intentar pagar con la tarjeta seleccionada, intente otra método de pago'));
                }
                $charge_request['source_id'] = $openpay_cc;
            }

            if ($this->save_cc == '2' && $openpay_cc != 'new'){
                $charge_request['source_id'] = $openpay_cc;
            }

            // Realiza la transacción en Openpay
            $charge = $this->makeOpenpayCharge($customer_data, $charge_request);

            $payment->setTransactionId($charge->id);
            $payment->setCcLast4(substr($charge->card->card_number, -4));
            $payment->setCcType($this->getCCBrandCode($charge->card->brand));
            $payment->setCcExpMonth($charge->card->expiration_month);
            $payment->setCcExpYear($charge->card->expiration_year);

            if ($this->charge_type == '3d' && $charge->payment_method) {
                $status = $this->getCustomStatus('pending_payment');
                $state = \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT;

                $order->setState($state)->setStatus($status);
                $order->setCanSendNewEmailFlag(false);
                $payment->setIsTransactionPending(true);
                $payment->setIsTransactionClosed(false);
                $_SESSION['openpay_3d_secure_url'] = $charge->payment_method->url;
                $this->logger->debug('3d_direct', array('redirect_url' => $charge->payment_method->url, 'openpay_id' => $charge->id, 'openpay_status' => $charge->status));
            }

            // Registra el ID de la transacción de Openpay
            $order->setExtOrderId($charge->id);

            // Registra (si existe), el ID de Customer de Openpay
            $order->setExtCustomerId($openpay_customer_id);
            $order->save();

            $this->logger->debug('#saveOrder');

        } catch (OpenpayApiTransactionError $e) {
            $this->logger->error('OpenpayApiTransactionError', array('message' => $e->getMessage(), 'code' => $e->getErrorCode(), '$status' => $order->getStatus()));

            // Si hubo riesgo de fraude y el usuario definió autenticación selectiva, se envía por 3D secure
            if ($this->charge_type == 'auth' && $e->getErrorCode() == '3005') {
                $charge_request['use_3d_secure'] = true;
                $charge_request['redirect_url'] = $base_url.'openpay/payment/success';

                $charge = $this->makeOpenpayCharge($customer_data, $charge_request);
                $openpayCustomerFactory = $this->customerSession->isLoggedIn() ? $this->hasOpenpayAccount($this->customerSession->getCustomer()->getId()) : null;
                $openpay_customer_id = $openpayCustomerFactory ? $openpayCustomerFactory->openpay_id : null;

                $order->setExtOrderId($charge->id);
                $order->setExtCustomerId($openpay_customer_id);
                $order->save();

                $payment->setTransactionId($charge->id);
                $payment->setCcLast4(substr($charge->card->card_number, -4));

                $payment->setCcType($this->getCCBrandCode($charge->card->brand));
                $payment->setCcExpMonth($charge->card->expiration_month);
                $payment->setCcExpYear($charge->card->expiration_year);
                $payment->setAdditionalInformation('openpay_3d_secure_url', $charge->payment_method->url);
                $payment->setSkipOrderProcessing(true);
                $payment->setIsTransactionPending(true);
                $payment->setIsTransactionClosed(false);
                $order->setCanSendNewEmailFlag(false);

                $_SESSION['openpay_3d_secure_url'] = $charge->payment_method->url;

                $this->logger->debug('3d_auth', array('redirect_url' => $charge->payment_method->url, 'openpay_id' => $charge->id, 'openpay_status' => $charge->status, '$status' => $order->getStatus()));
            } else {
                throw new \Magento\Framework\Validator\Exception(__($this->error($e)));
            }
        } catch (OpenpayApiConnectionError $e) {
            $this->_logger->error('#processCapture OpenpayApiConnectionError (makeOpenpayCharge)', array('message' => $e->getMessage()));
            throw new \Magento\Framework\Validator\Exception(__('Ocurrió un error interno. Intente más tarde.'));
        } catch (\Exception $e) {
            $this->_logger->error(__('Payment capturing error.'));
            $this->logger->error('ERROR', array('message' => $e->getMessage(), 'code' => $e->getCode()));
            if($e->getMessage()) {
                throw new \Magento\Framework\Validator\Exception(__($e->getMessage()));
            } else {
                throw new \Magento\Framework\Validator\Exception(__('Ocurrió un error interno en Magento. Intente más tarde.'));
            }
        }

        return $this;
    }

    private function getCCBrandCode($brand) {
        $code = null;
        switch ($brand) {
            case "mastercard":
                $code = "MC";
                break;

            case "visa":
                $code = "VI";
                break;

            case "american_express":
                $code = "AE";
                break;
            case "carnet":
                $code = "CN";
                break;
            case "diners":
                $code = "DN";
            break;
        }
        return $code;
    }

    private function validateNewCard($customer_data, $charge_request, $token, $device_session_id, $card_number) {
        $openpay_customer = $this->retrieveOpenpayCustomerAccount($customer_data);
        $customerId = $this->customerSession->getCustomer()->getId();
        $has_openpay_account = $this->hasOpenpayAccount($customerId);
        $cards = $this->getCreditCards($openpay_customer, $has_openpay_account->created_at);

        $card_number_bin = substr($card_number, 0, 6);
        $card_number_complement = substr($card_number, -4);
        foreach ($cards as $card) {
            if($card_number_bin == substr($card->card_number, 0, 6) && $card_number_complement == substr($card->card_number, -4)) {
                $errorMsg = "La tarjeta ya se encuentra registrada, seleccionala de la lista de tarjetas.";
                $this->logger->error('validateNewCard', array('#ERROR validateNewCard() => ' => $errorMsg));
                throw new \Magento\Framework\Exception\LocalizedException(__($errorMsg));
            }
        }

        $card_data = array(
            'token_id' => $token,
            'device_session_id' => $device_session_id
        );

        if ($this->save_cc === "2" && $this->country === "PE") {
            $this->logger->debug('GUARDA CON CVV => ', array('save_cc => ' => $this->save_cc, 'country => ' => $this->country));
            $card_data['register_frequent'] = true;
            $_SESSION['card_new'] = '2';
        }
        $this->logger->debug('CARD DATA => ', $card_data);

        $card = $this->createCreditCard($openpay_customer, $card_data);

        return $card->id;
    }


    private function makeOpenpayCharge($customer_data, $charge_request) {
        $openpay = $this->getOpenpayInstance();

        if (!$this->customerSession->isLoggedIn()) {
            // Cargo para usuarios "invitados"
            return $openpay->charges->create($charge_request);
        }

        // Se remueve el atributo de "customer" porque ya esta relacionado con una cuenta en Openpay
        unset($charge_request['customer']);

        $openpay_customer = $this->retrieveOpenpayCustomerAccount($customer_data);

        // Cargo para usuarios con cuenta
        return $openpay_customer->charges->create($charge_request);
    }

    public function getOpenpayCharge($charge_id, $customer_id = null) {
        try {
            if ($customer_id === null) {
                $openpay = $this->getOpenpayInstance();
                return $openpay->charges->get($charge_id);
            }

            $openpay_customer = $this->getOpenpayCustomer($customer_id);
            if($openpay_customer === false){
                $openpay = $this->getOpenpayInstance();
                return $openpay->charges->get($charge_id);
            }

            return $openpay_customer->charges->get($charge_id);
        } catch (OpenpayApiConnectionError $e) {
            $this->_logger->error('#getOpenpayCharge OpenpayApiConnectionError (openpay->charges->get)', array('message' => $e->getMessage()));
            throw new \Magento\Framework\Validator\Exception(__('Ocurrió un error interno. Intente más tarde.'));
        } catch (\Exception $e) {
            throw new \Magento\Framework\Validator\Exception(__($e->getMessage()));
        }
    }

    private function hasOpenpayAccount($customer_id) {
        try {
            $openpay_customer_local = $this->openpayCustomerFactory->create();
            $response = $openpay_customer_local->fetchOneBy('customer_id', $customer_id);
            return $response;
        } catch (\Exception $e) {
            throw new \Magento\Framework\Validator\Exception(__($e->getMessage()));
        }
    }

    private function retrieveOpenpayCustomerAccount($customer_data) {
        try {
            $customerId = $this->customerSession->getCustomer()->getId();
            //$customer = $this->customerModel->load($customerId);
            //$this->logger->debug('getFirstname => '.$customer->getFirstname());
            $has_openpay_account = $this->hasOpenpayAccount($customerId);
            if ($has_openpay_account === false) {
                $openpay_customer = $this->createOpenpayCustomer($customer_data);
                $this->logger->debug('$openpay_customer => '.$openpay_customer->id);

                $data = [
                    'customer_id' => $customerId,
                    'openpay_id' => $openpay_customer->id
                ];

                // Se guarda en BD la relación
                $openpay_customer_local = $this->openpayCustomerFactory->create();
                $openpay_customer_local->addData($data)->save();
            } else {
                $openpay_customer = $this->getOpenpayCustomer($has_openpay_account->openpay_id);
                if($openpay_customer === false){
                    $openpay_customer = $this->createOpenpayCustomer($customer_data);

                    $this->logger->debug('#update openpay_customer', array('$openpay_customer_old' => $has_openpay_account->openpay_id, '$openpay_customer_old_new' => $openpay_customer->id));

                    // Se actualiza en BD la relación
                    $openpay_customer_local = $this->openpayCustomerFactory->create();
                    $openpay_customer_local_update = $openpay_customer_local->load($has_openpay_account->openpay_customer_id);
                    $openpay_customer_local_update->setOpenpayId($openpay_customer->id);
                    $openpay_customer_local_update->save();
                }
            }

            return $openpay_customer;
        } catch (\Exception $e) {
            throw new \Magento\Framework\Validator\Exception(__($e->getMessage()));
        }
    }

    private function createOpenpayCustomer($data) {
        try {
            $openpay = $this->getOpenpayInstance();
            return $openpay->customers->add($data);
        } catch (OpenpayApiConnectionError $e) {
            $this->_logger->error('#createOpenpayCustomer OpenpayApiConnectionError (openpay->customers->add)', array('message' => $e->getMessage()));
            throw new \Magento\Framework\Validator\Exception(__('Ocurrió un error interno. Intente más tarde.'));
        } catch (\Exception $e) {
            throw new \Magento\Framework\Validator\Exception(__($e->getMessage()));
        }
    }

    public function getOpenpayCustomer($openpay_customer_id) {
        try {
            $openpay = $this->getOpenpayInstance();
            $customer = $openpay->customers->get($openpay_customer_id);
            if(isset($customer->balance)){
                return false;
            }
            return $customer;
        } catch (OpenpayApiConnectionError $e) {
            $this->_logger->error('#getOpenpayCustomer OpenpayApiConnectionError (openpay->customers->get)', array('message' => $e->getMessage()));
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function createCreditCard($customer, $data) {
        try {
            return $customer->cards->add($data);
        } catch (OpenpayApiConnectionError $e) {
            $this->_logger->error('#createCreditCard OpenpayApiConnectionError (customer->cards->add)', array('message' => $e->getMessage()));
            throw new \Magento\Framework\Validator\Exception(__('Ocurrió un error interno. Intente más tarde.'));
        } catch (\Exception $e) {
            throw new \Magento\Framework\Validator\Exception(__($e->getMessage()));
        }
    }

    private function getCreditCards($customer, $customer_created_at) {
        $from_date = date('Y-m-d', strtotime($customer_created_at."- 1 day"));
        $to_date = date('Y-m-d');

        try {
            return $customer->cards->getList(array(
                'creation[gte]' => $from_date,
                'creation[lte]' => $to_date,
                'offset' => 0,
                'limit' => 10
            ));

        } catch (OpenpayApiConnectionError $e) {
            $this->_logger->error('#getCreditCards OpenpayApiConnectionError (customer->cards->getList)', array('message' => $e->getMessage()));
            throw new \Magento\Framework\Validator\Exception(__('Ocurrió un error interno. Intente más tarde.'));
        } catch (\Exception $e) {
            throw new \Magento\Framework\Validator\Exception(__($e->getMessage()));
        }
    }

    public function getCreditCardList(): array
    {
        $message = 'Selecciona tarjeta';
        if (!$this->customerSession->isLoggedIn()) {
            return array(array('value' => 'new', 'name' => $message));
        }

        $customerId = $this->customerSession->getCustomer()->getId();
        $has_openpay_account = $this->hasOpenpayAccount($customerId);
        if ($has_openpay_account === false) {
            return array(array('value' => 'new', 'name' => $message));
        }

        $customer = $this->getOpenpayCustomer($has_openpay_account->openpay_id);
        if($customer == false){
            return array(array('value' => 'new', 'name' => $message));
        }

        try {
            $list = array(array('value' => 'new', 'name' => $message));
            $cards = $this->getCreditCards($customer, $has_openpay_account->created_at);

            foreach ($cards as $card) {
                /** This handle the way to get the bin and the last four digits of the credit cards in frontend*/
                array_push($list, array('value' => $card->id, 'name' => strtoupper($card->brand).' '.$card->card_number));
            }

            return $list;
        } catch (\Exception $e) {
            throw new \Magento\Framework\Validator\Exception(__($e->getMessage()));
        }
    }

    public function existsOneCreditCard(): bool
    {
        return count($this->getCreditCardList()) > 1;
    }

    public function getBaseUrlStore(): string
    {
        return $this->_storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_LINK);
    }

    public function isLoggedIn(): bool
    {
        return $this->customerSession->isLoggedIn();
    }

    /**
     * Determine method availability based on quote amount and config data
     *
     * @param \Magento\Quote\Api\Data\CartInterface|null $quote
     * @return bool
     */
    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null): bool
    {
        return parent::isAvailable($quote);
    }

    /**
     * @return string
     */
    public function getMerchantId(): ?string
    {
        return $this->merchant_id;
    }

    /**
     * @return string
     */
    public function getPublicKey(): ?string
    {
        return $this->pk;
    }

    public function getPrivateKey() {
        return $this->sk;
    }

    /**
     * @return boolean
     */
    public function isSandbox(): bool
    {
        return $this->is_sandbox;
    }

    public function getCountry() {
        return $this->country;
    }

    public function getCurrentSiteOpenpayInstance(){
        $website_id = (int) $this->request->getParam('website', 0);
        $current_is_sandbox = $this->scopeConfig->getValue("payment/openpay_cards/is_sandbox",\Magento\Store\Model\ScopeInterface::SCOPE_STORE,$website_id );
        $current_sandbox_merchant_id = $this->scopeConfig->getValue("payment/openpay_cards/sandbox_merchant_id",\Magento\Store\Model\ScopeInterface::SCOPE_STORE,$website_id );
        $current_live_merchant_id = $this->scopeConfig->getValue("payment/openpay_cards/live_merchant_id",\Magento\Store\Model\ScopeInterface::SCOPE_STORE,$website_id );
        $current_sandbox_sk = $this->scopeConfig->getValue("payment/openpay_cards/sandbox_sk",\Magento\Store\Model\ScopeInterface::SCOPE_STORE,$website_id );
        $current_live_sk = $this->scopeConfig->getValue("payment/openpay_cards/live_sk",\Magento\Store\Model\ScopeInterface::SCOPE_STORE,$website_id );
        $current_country = $this->scopeConfig->getValue("payment/openpay_cards/country",\Magento\Store\Model\ScopeInterface::SCOPE_STORE,$website_id );
        
        $current_merchant_id = $current_is_sandbox ? $current_sandbox_merchant_id : $current_live_merchant_id;
        $current_sk = $current_is_sandbox ? $current_sandbox_sk : $current_live_sk;

        $this->logger->debug( '#payment.getCurrentSiteOpenpayInstance', array( 'current_merchant_id' => $current_merchant_id ) );
        
        $openpay = $this->getOpenpayInstance($current_merchant_id,$current_sk,$current_country,$current_is_sandbox);
        return $openpay;
    }

    public function validateSettings() { 
        $website_id = (int) $this->request->getParam('website', 0);  
        $is_active = $this->scopeConfig->getValue("payment/openpay_cards/active",\Magento\Store\Model\ScopeInterface::SCOPE_STORE,$website_id );
        $this->logger->debug( '#payment.validateSettings', array( 'plugin_is_active' => $is_active ) );
        if($is_active){
            $current_country = $this->scopeConfig->getValue("payment/openpay_cards/country",\Magento\Store\Model\ScopeInterface::SCOPE_STORE,$website_id );
            $supportedCurrencies = $this->currencyUtils->getSupportedCurrenciesByCountryCode($current_country);
            if (!$this->currencyUtils->isSupportedCurrentCurrency($supportedCurrencies)) {
                $currenciesAsString = implode(', ', $supportedCurrencies);
                throw new \Magento\Framework\Validator\Exception(__('The '. $this->currencyUtils->getCurrentCurrency() .' currency is not suported, the supported currencies are: ' . $currenciesAsString));
            }
        }
    }

    public function getCode() {
        return $this->_code;
    }

    public function getOpenpayInstance($merchant_id = null, $sk = null, $country = null, $is_sandbox = null) {

        $ipClient = $this->getIpClient();

        if(is_null($merchant_id)){
            $merchant_id = $this->merchant_id;
        }

        if(is_null($sk)){
            $sk = $this->sk;
        }

        if(is_null($country)){
            $country = $this->country;
        }
        
        if(is_null($is_sandbox)){
            $is_sandbox = $this->is_sandbox;
        }

        $openpay = Openpay::getInstance($merchant_id,$sk,$country, $ipClient);
        Openpay::setSandboxMode($is_sandbox);
        $userAgent = "Openpay-MTO2".$country."/v2";
        Openpay::setUserAgent($userAgent);

        return $openpay;
    }

    public function getMonthsInterestFree() {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $cart = $objectManager->get('\Magento\Checkout\Model\Cart');
        $grandTotal = (int) $cart->getQuote()->getGrandTotal();

        if(version_compare(phpversion(), '8.1.0', '>=')){
            $months = explode(',', $this->months_interest_free ?? '');
        } else {
            $months = explode(',', $this->months_interest_free);
        }

        if($this->minimum_amounts && $this->country == 'MX'){
            foreach($months as $key => $value){
                $msi_minimum_amount = (int) $this->config_months[$value];
                if($grandTotal < $msi_minimum_amount){
                    unset($months[$key]);
                }
            }
        }

        if(!in_array('1', $months)) {
            array_unshift($months, '1');
        }
        return $months;
    }

    public function getInstallments() {
        $installments = array();
        for ($i=1; $i <= 36; $i++) {
            $installments[] = $i;
        }

        return $installments;
    }

    public function useCardPoints() {
        return $this->use_card_points;
    }

    /**
     *
     * Valida que los clientes pueda guardar sus TC
     *
     * @return boolean
     */
    public function canSaveCC() {
        return $this->save_cc;
    }

     /**
     *
     * Determina para Peru si se puede pagar a cuotas
     *
     * @return boolean
     */
    public function getIsAvailableInstallments() {
        return $this->isAvailableInstallments;
    }

    /**
     * @param Exception $e
     * @return string
     */
    public function error($e) {
        /* 6001 el webhook ya existe */
        switch ($e->getCode()) {
            case '1000':
            case '1004':
            case '1005':
                $msg = 'Servicio no disponible.';
                break;
            /* ERRORES TARJETA */
            case '3002':
                $msg = 'Tarjeta expirada. Por favor intenta con otra tarjeta o con otro método de pago.';
                break;
            case '3003':
                $msg = 'Fondos insuficientes. Por favor intenta con otra tarjeta o con otro método de pago';
                break;
            case '3001':
            case '3006':
            case '3008':
            case '3012':
                $msg = 'Por favor contacta a tu banco o intenta con otro método de pago.';
                break;
            case '3004':
            case '3005':
            case '3009':
            case '3010':
            case '3011':
                $msg = 'Por favor intenta con otro método de pago';
                break;
            default: /* Demás errores 400 */
                $msg = 'La petición no pudo ser procesada.';
                break;
        }

        return 'ERROR '.$e->getCode().'. '.$msg;
    }

    /**
     * @param Address $billing
     * @return boolean
     */
    public function validateAddress($address) {
        if($this->country == 'MX' || $this->country == 'PE') {
            return $address->getStreetLine(1) && $address->getCity() && $address->getPostcode() && $address->getRegion();
        } elseif ($this->country == 'CO') {
            return $address->getStreetLine(1) && $address->getCity() && $address->getRegion();
        }
        return false;
    }

    /**
     * Create webhook
     * @return mixed
     */
    public function createWebhook() {
        $website_id = (int) $this->request->getParam('website', 0);
        $is_active = $this->scopeConfig->getValue("payment/openpay_cards/active",\Magento\Store\Model\ScopeInterface::SCOPE_STORE,$website_id );
        $this->logger->debug('#payment.createWebhook', Array());
        if($is_active){
            $base_url = $this->_storeManager->getStore($website_id)->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB);
            $this->logger->debug('#payment.createWebhook', array( 'Website Url' => $base_url ) );
            $this->logger->debug( '#payment.createWebhook', array( 'Website ID' => $website_id ) );
            $openpay = $this->getCurrentSiteOpenpayInstance();
            $uri = $base_url."openpay/cards/webhook";
            $webhooks = $openpay->webhooks->getList([]);
            $webhookCreated = $this->isWebhookCreated($webhooks, $uri);
        }else{
            $webhookCreated = (object) [
                "url" => ""
            ];
        }

        if($webhookCreated){
            $this->logger->debug('#payment.createWebhook.isWebhookCreated', array('isWebhookCreated' => $webhookCreated->url) );
            return $webhookCreated;
        }

        $webhook_data = array(
            'url' => $uri,
            'event_types' => array(
                'verification',
                'charge.succeeded',
                'charge.created',
                'charge.cancelled',
                'charge.failed',
                'payout.created',
                'payout.succeeded',
                'payout.failed',
                'spei.received',
                'chargeback.created',
                'chargeback.rejected',
                'chargeback.accepted',
                'transaction.expired'
            )
        );

        try {
            $webhook = $openpay->webhooks->add($webhook_data);
            return $webhook;
        } catch (OpenpayApiConnectionError $e) {
            $this->_logger->error('#createWebhook OpenpayApiConnectionError (openpay->webhooks->add)', array('message' => $e->getMessage()));
            return "Ocurrió un error interno. Intente más tarde.";
        } catch (Exception $e) {
            return $this->error($e);
        }
    }

    private function isWebhookCreated($webhooks, $uri) {
        foreach ($webhooks as $webhook) {
            if ($webhook->url === $uri) {
                return $webhook;
            }
        }
        return null;
    }


    public function getCustomStatus($status) {
        switch($status){
            case 'processing':
                return ($this->processing_openpay != \Magento\Sales\Model\Order::STATE_PROCESSING ) ? $this->processing_openpay : \Magento\Sales\Model\Order::STATE_PROCESSING;
            case 'pending_payment':
                return ($this->pending_payment_openpay != \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT ) ? $this->pending_payment_openpay : \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT;
            case 'canceled':
                return ($this->canceled_openpay != \Magento\Sales\Model\Order::STATE_CANCELED ) ? $this->canceled_openpay : \Magento\Sales\Model\Order::STATE_CANCELED;
        }
    }

}
