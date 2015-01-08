<?php

/**
 * Class Nicklasmoeller_Billysbilling_Model_Invoice
 *
 * @author Nicklas Møller <hello@nicklasmoeller.com>
 * @version 0.2.0
 */
class Nicklasmoeller_Billysbilling_Model_Invoice extends Nicklasmoeller_Billysbilling_Model_Abstract
{
    /**
     * @var
     */
    protected $invoice;

    /**
     * @param $orderData
     * @param $contact
     * @param $observerType
     *
     * @return mixed
     */
    public function getInvoice($orderData, $contact, $observerType)
    {
        $invoice = $this->buildInvoice($orderData, $contact, $observerType);

        return $invoice;
    }

    /**
     * @param $orderData
     * @param $contact
     * @param $observerType
     *
     * @return bool|mixed
     */
    public function buildInvoice($orderData, $contact, $observerType)
    {
        if ($observerType == 'sales_order_creditmemo_refund') {
            $type = 'creditNote';
            $paymentTermsDays = null;

            $invoicePrefix = "c";
        } elseif ($observerType == 'sales_order_invoice_register') {
            $type = 'invoice';
            $paymentTermsDays = 8;

            $invoicePrefix = "";
        }

        $entryDate = date('Y-m-d');

        $invoice = new stdClass();

        $invoice->organizationId   = Mage::getSingleton('billysbilling/organization')->getOrganizationId();
        $invoice->contactId        = $contact->id;
        $invoice->type             = $type;
        $invoice->state            = 'approved';
        $invoice->invoiceNo        = $invoicePrefix . $orderData->getId();
        $invoice->currencyId       = Mage::getSingleton('billysbilling/currency')->getCurrency($orderData->getOrderCurrencyCode())->id;
        $invoice->entryDate        = $entryDate;
        $invoice->paymentTermsDays = $paymentTermsDays;
        $invoice->taxMode          = 'excl';

        $invoice->lines = $this->buildInvoiceLines($orderData);

        $res = $this->client->request("POST", "/invoices", array(
            'invoice' => $invoice
        ));

        if ($res->status !== 200) {
            return false;
        }

        $this->invoice = $res->body->invoices[0];

        return $this->invoice;
    }

    /**
     * @param $orderData
     *
     * @return array
     */
    public function buildInvoiceLines($orderData)
    {
        $lines = array();

        $products = $orderData->getAllItems();

        $this->client->request("GET", "/invoices");

        $i = 0;

        foreach ($products as $product) {
            $tempProduct = Mage::getSingleton('billysbilling/product')->getProduct($product);
            
            $lines[$i]                = new stdClass();
            $lines[$i]->productId     = $tempProduct->id;
            $lines[$i]->description   = '';
            $lines[$i]->quantity      = $product->getQtyOrdered();
            $lines[$i]->unitPrice     = $product->getPrice();

            if ($product->getDiscountAmount() > 0) {
                $lines[$i]->discountText  = 'Discounted';
                $lines[$i]->discountMode  = 'cash';
                $lines[$i]->discountValue = $product->getDiscountAmount();
            }

            $i++;
        }

        $lines[$i] = Mage::getSingleton('billysbilling/shipment')->getShipping($orderData);

        return $lines;
    }
}