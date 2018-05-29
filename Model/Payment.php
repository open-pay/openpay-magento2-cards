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
use Magento\Payment\Model\Method\Logger;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Directory\Model\CountryFactory;
use Magento\Store\Model\StoreManagerInterface;

class Payment extends \Magento\Payment\Model\Method\Cc
{

    const CODE = 'openpay_cards';

    protected $_code = self::CODE;
    protected $_isGateway = true;
    protected $_canCapture = true;
    protected $_canCapturePartial = true;
    protected $_canRefund = false;
    protected $_canRefundInvoicePartial = false;
    protected $openpay = false;
    protected $is_sandbox;
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
    protected $minimum_amount;
    protected $months_interest_free;
    protected $charge_type;
    protected $logger;
    protected $_storeManager;


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
     * @param \Openpay $openpay
     * @param array $data
     * @param \Magento\Store\Model\StoreManagerInterface $data
     */
    public function __construct(
            StoreManagerInterface $storeManager,
            Context $context, 
            Registry $registry, 
            ExtensionAttributesFactory $extensionFactory, 
            AttributeValueFactory $customAttributeFactory, 
            Data $paymentData, 
            ScopeConfigInterface $scopeConfig, 
            Logger $logger,
            ModuleListInterface $moduleList, 
            TimezoneInterface $localeDate, 
            CountryFactory $countryFactory, 
            \Openpay $openpay,             
            \Psr\Log\LoggerInterface $logger_interface,
            array $data = array()            
    ) {
        
        parent::__construct(
                $context, $registry, $extensionFactory, $customAttributeFactory, $paymentData, $scopeConfig, $logger, $moduleList, $localeDate, null, null, $data
        );
        
        $this->_storeManager = $storeManager;
        $this->logger = $logger_interface;

        $this->scopeConfig = $scopeConfig;
        $this->country_factory = $countryFactory;

        $this->is_active = $this->getConfigData('active');
        $this->is_sandbox = $this->getConfigData('is_sandbox');
        $this->sandbox_merchant_id = $this->getConfigData('sandbox_merchant_id');
        $this->sandbox_sk = $this->getConfigData('sandbox_sk');
        $this->sandbox_pk = $this->getConfigData('sandbox_pk');
        $this->live_merchant_id = $this->getConfigData('live_merchant_id');
        $this->live_sk = $this->getConfigData('live_sk');
        $this->live_pk = $this->getConfigData('live_pk');

        $this->merchant_id = $this->is_sandbox ? $this->sandbox_merchant_id : $this->live_merchant_id;
        $this->sk = $this->is_sandbox ? $this->sandbox_sk : $this->live_sk;
        $this->pk = $this->is_sandbox ? $this->sandbox_pk : $this->live_pk;
        $this->months_interest_free = $this->getConfigData('interest_free');
        $this->charge_type = $this->getConfigData('charge_type');
        $this->minimum_amount = $this->getConfigData('minimum_amount');

        $this->openpay = $openpay;
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
            isset($additionalData['device_session_id']) ? $additionalData['device_session_id'] :  null
        );
        $infoInstance->setAdditionalInformation('openpay_token',     
            isset($additionalData['openpay_token']) ? $additionalData['openpay_token'] : null
        );
        $infoInstance->setAdditionalInformation('interest_free',
            isset($additionalData['interest_free']) ? $additionalData['interest_free'] : null
        );
        
        return $this;
    }

    /**     
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws \Magento\Framework\Validator\Exception
     */
    public function order(\Magento\Payment\Model\InfoInterface $payment, $amount) {
        unset($_SESSION['pdf_url']);
        $base_url = $this->_storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB);  // URL de la tienda
        $this->logger->debug('Openpay capture');        
        
        /** @var \Magento\Sales\Model\Order $order */
        $order = $payment->getOrder();

        /** @var \Magento\Sales\Model\Order\Address $billing */
        $billing = $order->getBillingAddress();

        if (!$this->getInfoInstance()->getAdditionalInformation('openpay_token')) {
            $msg = 'ERROR X100 Please specify card info';
            throw new \Magento\Framework\Validator\Exception(__($msg));
        }
        
        $customer_data = array(
            'name' => $billing->getFirstname(),
            'last_name' => $billing->getLastname(),
            'phone_number' => $billing->getTelephone(),
            'email' => $order->getCustomerEmail()
        );

        if ($this->validateAddress($billing)) {
            $customer_data['address'] = array(
                'line1' => $billing->getStreetLine(1),
                'line2' => $billing->getStreetLine(2),
                'postal_code' => $billing->getPostcode(),
                'city' => $billing->getCity(),
                'state' => $billing->getRegion(),
                'country_code' => $billing->getCountryId()
            );
        }

        $charge_request = array(
            'method' => 'card',
            'currency' => strtolower($order->getBaseCurrencyCode()),
            'amount' => $amount,
            'description' => sprintf('#%s, %s', $order->getIncrementId(), $order->getCustomerEmail()),                
            'order_id' => $order->getIncrementId(),
            'source_id' => $this->getInfoInstance()->getAdditionalInformation('openpay_token'),
            'device_session_id' => $this->getInfoInstance()->getAdditionalInformation('device_session_id'),
            'customer' => $customer_data                
        );

        // Meses sin intereses
        $interest_free = $this->getInfoInstance()->getAdditionalInformation('interest_free');
        if($interest_free > 1){
            $charge_request['payment_plan'] = array('payments' => (int)$interest_free);
        }  

        if ($this->charge_type == '3d') {
            $charge_request['use_3d_secure'] = true;
            $charge_request['redirect_url'] = $base_url.'openpay/payment/success';;
        }
        
        $openpay = $this->getOpenpayInstance();

        try {                   
            $charge = $openpay->charges->create($charge_request);
            $payment->setTransactionId($charge->id);                
            
            // Solo si es cargo directo se deja abierta la transacción
            if ($this->charge_type == 'direct') {                
                $status = \Magento\Sales\Model\Order::STATE_PROCESSING;
                $order->setState($status)->setStatus($status);                                
            }
            
            if ($this->charge_type == '3d') {                
                $status = \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT;
                $order->setState($status)->setStatus($status);                    
                                
                $payment->setSkipOrderProcessing(true);                                     
                $payment->setAdditionalInformation('openpay_3d_secure_url', $charge->payment_method->url);                                                
                
                $this->logger->debug('3d_direct', array('redirect_url' => $charge->payment_method->url, 'openpay_id' => $charge->id, 'status' => $charge->status));
            }
            
            $order->setExtOrderId($charge->id);   
            $order->save();
            
        } catch (\Exception $e) {
            //$this->debugData(['exception' => $e->getMessage()]);            
            $this->_logger->error(__('Payment capturing error.'));            
            $this->logger->error('ERROR', array('message' => $e->getMessage(), 'code' => $e->getCode()));
            
            // Si hubo riesgo de fraude y el usuario definió autenticación selectiva, se envía por 3D secure
            if ($this->charge_type == 'auth' && $e->getCode() == '3005') {                                                
                $charge_request['use_3d_secure'] = true;
                $charge_request['redirect_url'] = $base_url.'openpay/payment/success';
                
                $charge = $openpay->charges->create($charge_request);
                                
                $status = \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT;
                $order->setState($status)->setStatus($status);                                
                $order->setExtOrderId($charge->id);
                $order->save();
                
                $payment->setTransactionId($charge->id);
                $payment->setSkipOrderProcessing(true);      
                $payment->setAdditionalInformation('openpay_3d_secure_url', $charge->payment_method->url);                                                
                
                $this->logger->debug('3d_auth', array('redirect_url' => $charge->payment_method->url, 'openpay_id' => $charge->id, 'status' => $charge->status));
                
            } else {
                throw new \Magento\Framework\Validator\Exception(__($this->error($e)));
            }
        }
        
        return $this;
    }

    /**
     * Determine method availability based on quote amount and config data
     *
     * @param \Magento\Quote\Api\Data\CartInterface|null $quote
     * @return bool
     */
    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null) {
        return parent::isAvailable($quote);
    }

    /**
     * Availability for currency
     *
     * @param string $currencyCode
     * @return bool
     */
    public function canUseForCurrency($currencyCode) {
        if (!in_array($currencyCode, $this->supported_currency_codes)) {
            return false;
        }
        return true;
    }

    /**
     * @return string
     */
    public function getMerchantId() {
        return $this->merchant_id;
    }

    /**
     * @return string
     */
    public function getPublicKey() {
        return $this->pk;
    }
    
    public function getPrivateKey() {
        return $this->sk;
    }

    /**
     * @return boolean
     */
    public function isSandbox() {
        return $this->is_sandbox;
    }
    
    public function getMinimumAmount() {
        return $this->minimum_amount;
    }
    
    public function getCode() {
        return $this->_code;
    }
    
    public function getOpenpayInstance() {
        $openpay = \Openpay::getInstance($this->merchant_id, $this->sk);
        \Openpay::setSandboxMode($this->is_sandbox);        
        return $openpay;
    }
    
    public function getMonthsInterestFree() {
        $months = explode(',', $this->months_interest_free);                  
        if(!in_array('1', $months)) {            
            array_unshift($months, '1');
        }        
        return $months;
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
            case '3001':
            case '3004':
            case '3005':
            case '3007':
                $msg = 'La tarjeta fue rechazada.';
                break;
            case '3002':
                $msg = 'La tarjeta ha expirado.';
                break;
            case '3003':
                $msg = 'La tarjeta no tiene fondos suficientes.';
                break;
            case '3006':
                $msg = 'La operación no esta permitida para este cliente o esta transacción.';
                break;
            case '3008':
                $msg = 'La tarjeta no es soportada en transacciones en línea.';
                break;
            case '3009':
                $msg = 'La tarjeta fue reportada como perdida.';
                break;
            case '3010':
                $msg = 'El banco ha restringido la tarjeta.';
                break;
            case '3011':
                $msg = 'El banco ha solicitado que la tarjeta sea retenida. Contacte al banco.';
                break;
            case '3012':
                $msg = 'Se requiere solicitar al banco autorización para realizar este pago.';
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
    public function validateAddress($billing) {
        if ($billing->getStreetLine(1) && $billing->getCity() && $billing->getPostcode() && $billing->getRegion() && $billing->getCountryId()) {
            return true;
        }
        return false;
    }

}
