<?php

/**
 * @category    Payments
 * @package     Openpay_Cards
 * @author      Federico Balderas
 * @copyright   Openpay (http://openpay.mx)
 * @license     http://www.apache.org/licenses/LICENSE-2.0  Apache License Version 2.0
 */

namespace Openpay\Cards\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Openpay\Cards\Model\Payment as Config;
use Magento\Framework\DataObject;

class AfterPlaceOrder implements ObserverInterface {

    protected $config;
    protected $order;
    protected $logger;
    protected $_actionFlag;
    protected $_response;
    protected $_redirect;
    protected $openpayCustomerFactory;

    public function __construct(
    Config $config,
    \Magento\Sales\Model\Order $order,
    \Magento\Framework\App\Response\RedirectInterface $redirect,
    \Magento\Framework\App\ActionFlag $actionFlag,
    \Openpay\Cards\Logger\Logger $logger_interface,
    \Magento\Framework\App\ResponseInterface $response
    ) {
        $this->config = $config;
        $this->order = $order;
        $this->logger = $logger_interface;

        $this->_redirect = $redirect;
        $this->_response = $response;

        $this->_actionFlag = $actionFlag;
    }

    public function execute(Observer $observer) {
        $orderId = $observer->getEvent()->getOrderIds();
        $order = $this->order->load($orderId[0]);
        $status = $this->config->getCustomStatus('processing');

        $this->logger->debug('#AfterPlaceOrder openpay_cards');

        if ($order->getPayment()->getMethod() == 'openpay_cards') {
            $charge = $this->config->getOpenpayCharge($order->getExtOrderId(), $order->getExtCustomerId());
            $this->logger->debug('#AfterPlaceOrder.openpay_cards.ln:55', array('order_id' => $orderId[0], 'order_status' => $order->getStatus(), 'charge_id' => $charge->id, 'ext_order_id' => $order->getExtOrderId(), 'openpay_status' => $charge->status));

            if ($charge->status == 'charge_pending' && isset($_SESSION['openpay_3d_secure_url'])) {
                $this->logger->debug('#AfterPlaceOrder.openpay_cards.ln:64', array('ext_order_id' => $order->getExtOrderId(), 'redirect_url' => $_SESSION['openpay_3d_secure_url']));
                $order->setStatus($this->config->getCustomStatus('pending_payment'));
                $order->addStatusHistoryComment("Pago pendiente, evaluando 3DSecure");
                $order->save();
                $this->_actionFlag->set('', \Magento\Framework\App\Action\Action::FLAG_NO_DISPATCH, true);
                $this->_redirect->redirect($this->_response, $_SESSION['openpay_3d_secure_url']);
            }
            if ($charge->status == 'in_progress' && ($charge->id != $charge->authorization)) {
                $this->logger->debug('#AfterPlaceOrder.openpay_cards.ln:72', array('$charge->status' => 'in_progress'));
                $order->setState($status)->setStatus($status);
                $order->addStatusHistoryComment("Preautorización realizada exitosamente");
                $order->save();
            }
        } elseif ($order->getPayment()->getMethod() == 'openpay_banks') {
            $this->logger->debug('#AfterPlaceOrder.openpay_banks.ln:77', array('order_id' => $orderId[0], 'order_status' => $order->getStatus(), 'ext_order_id' => $order->getExtOrderId()));

            if ($order->getStatus() == 'pending' && isset($_SESSION['openpay_pse_redirect_url'])) {
                $this->logger->debug('#AfterPlaceOrder.openpay_banks.ln:80', array('ext_order_id' => $order->getExtOrderId(), 'redirect_url' => $_SESSION['openpay_pse_redirect_url']));
                $this->_actionFlag->set('', \Magento\Framework\App\Action\Action::FLAG_NO_DISPATCH, true);
                $this->_redirect->redirect($this->_response, $_SESSION['openpay_pse_redirect_url']);
            }
        }
        return $this;
    }
}