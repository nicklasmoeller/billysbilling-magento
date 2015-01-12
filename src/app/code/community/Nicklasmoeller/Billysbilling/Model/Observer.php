<?php

/**
 * Class Nicklasmoeller_Billysbilling_Model_Observer
 *
 * @author Nicklas Møller <hello@nicklasmoeller.com>
 */
class Nicklasmoeller_Billysbilling_Model_Observer extends Nicklasmoeller_Billysbilling_Model_Abstract
{
    /**
     * @var
     */
    private $orderData;
    /**
     * @var
     */
    private $billingAddress;

    /**
     * @var
     */
    private $event;

    /**
     * @param Varien_Event_Observer $observer
     *
     * @return bool
     */
    public function billysObserver(Varien_Event_Observer $observer)
    {
        if (!Mage::helper('billysbilling')->isApiKeySet() || !Mage::helper('billysbilling')->isEnabled()) {
            return false;
        }

        $this->event = $observer->getEvent();

        if ($this->event->getName() == 'sales_order_creditmemo_refund') {
            $this->orderData = $observer->getCreditmemo()->getOrder();
        } else {
            $this->orderData = $observer->getOrder();
        }

        $this->billingAddress = $this->orderData->getBillingAddress();

        $contact = Mage::getModel('billysbilling/contact')->getCustomer($this->billingAddress);

        $invoice = Mage::getModel('billysbilling/invoice')->getInvoice($this->orderData, $contact, $this->event->getName());
    }
}
