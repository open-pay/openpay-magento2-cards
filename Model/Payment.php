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

namespace OpenpayMagento\Cards\Model;

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
    protected $supported_currency_codes = array('USD', 'MXN');
    

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
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \Magento\Framework\Module\ModuleListInterface $moduleList,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate,
        \Magento\Directory\Model\CountryFactory $countryFactory,
        \Openpay $openpay,
        array $data = array()
    ) {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $moduleList,
            $localeDate,
            null,
            null,
            $data
        );

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
        $infoInstance->setAdditionalInformation('device_session_id', $data->getData('device_session_id'));                
        $infoInstance->setAdditionalInformation('openpay_token', $data->getData('openpay_token'));        
        return $this;
    }

    /**
     * Payment capturing
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws \Magento\Framework\Validator\Exception
     */
    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        
        /** @var \Magento\Sales\Model\Order $order */
        $order = $payment->getOrder();

        /** @var \Magento\Sales\Model\Order\Address $billing */
        $billing = $order->getBillingAddress();
        
        if(!$this->getInfoInstance()->getAdditionalInformation('openpay_token')){
            $msg = 'ERROR X100 Please specify card info';
            throw new \Magento\Framework\Validator\Exception(__($msg));
        }

        try {
            
            $customer_data = array(
                'name' => $billing->getFirstname(),
                'last_name' => $billing->getLastname(),
                'phone_number' => $billing->getTelephone(),
                'email' => $order->getCustomerEmail()
            );
            
            if($this->validateAddress($billing)){
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
                'source_id' => $this->getInfoInstance()->getAdditionalInformation('openpay_token'),
                'device_session_id' => $this->getInfoInstance()->getAdditionalInformation('device_session_id'),
                'customer' => $customer_data
            );
            
            $openpay = \Openpay::getInstance($this->merchant_id, $this->sk);
            \Openpay::setSandboxMode($this->is_sandbox);
            $charge = $openpay->charges->create($charge_request);                        
            $payment->setTransactionId($charge->id)->setIsTransactionClosed(0);

        } catch (\Exception $e) {
            $this->debugData(['request' => $charge_request, 'exception' => $e->getMessage()]);
            $this->_logger->error(__('Payment capturing error.'));            
            throw new \Magento\Framework\Validator\Exception(__($this->error($e)));
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
    public function canUseForCurrency($currencyCode)
    {
        if (!in_array($currencyCode, $this->supported_currency_codes)) {
            return false;
        }
        return true;
    }
    
    /**     
     * @return string
     */
    public function getMerchantId(){
        return $this->merchant_id;
    }
    
    /**     
     * @return string
     */
    public function getPublicKey(){
        return $this->pk;
    }
    
    /**     
     * @param Exception $e
     * @return string
     */
    public function error($e)
    {
        /* 6001 el webhook ya existe */
        switch ($e->getErrorCode()) {
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

        return 'ERROR '.$e->getErrorCode().'. '.$msg;        
    }
    
    /**     
     * @param Address $billing
     * @return boolean
     */
    public function validateAddress($billing){        
        if($billing->getStreetLine(1) && $billing->getCity() && $billing->getPostcode() && $billing->getRegion() && $billing->getCountryId()){
            return true;
        }
        return false;
    }
        
}