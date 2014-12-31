<?php

/**
 * Class Nicklasmoeller_Billysbilling_Model_Shipment
 *
 * @author Nicklas Møller <hello@nicklasmoeller.com>
 * @version 0.2.0
 */
class Nicklasmoeller_Billysbilling_Model_Shipment extends Nicklasmoeller_Billysbilling_Model_Abstract
{
    /**
     * @param $orderData
     *
     * @return bool|mixed
     */
    public function getShipping($orderData)
    {
        $shipping = $this->buildShipping($orderData);

        $res = $this->client->request("GET", "/products?productNo=" . $shipping->productNo);

        if ($res->body->meta->paging->total > 0) {
            return $this->buildShippingLine($res->body->products[0], $orderData);
        }

        $res = $this->client->request("POST", "/products", array(
            'product' => $shipping
        ));

        if ($res->status !== 200) {
            return false;
        }

        $product = $res->body->products[0];

        return $this->buildShippingLine($product, $orderData);
    }

    /**
     * @param $orderData
     *
     * @return mixed
     */
    public function buildShipping($orderData)
    {
        $shipping = new stdClass();

        $shipping->organizationId = Mage::getSingleton('billysbilling/organization')->getOrganizationId();
        $shipping->name           = 'Fragt';
        $shipping->description    = $orderData->getShippingDescription();
        $shipping->productNo      = $orderData->getShippingMethod();
        $shipping->prices         = array(
            Mage::getSingleton('billysbilling/product')->buildPrice($orderData->getShippingInvoiced())
        );

        return $shipping;
    }

    /**
     * @param $shipping
     * @param $orderData
     *
     * @return mixed
     */
    public function buildShippingLine($shipping, $orderData)
    {
        $newLine = new stdClass();
        $newLine->productId = $shipping->id;
        $newLine->description = $shipping->description;
        $newLine->quantity = 1;
        $newLine->unitPrice = $orderData->getShippingInvoiced();

        return $newLine;
    }
}