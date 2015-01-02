<?php

/**
 * Class Nicklasmoeller_Billysbilling_Model_Product
 *
 * @author Nicklas Møller <hello@nicklasmoeller.com>
 * @version 0.2.0
 */
class Nicklasmoeller_Billysbilling_Model_Product extends Nicklasmoeller_Billysbilling_Model_Abstract
{
    /**
     * @param $product
     *
     * @return bool|mixed
     */
    public function getProduct($product)
    {
        $tempProduct = $this->buildProduct($product);

        $res = $this->client->request("GET", "/products?productNo=" . $tempProduct->productNo);

        if ($res->body->meta->paging->total > 0) {
            return $res->body->products[0];
        }

        $res = $this->client->request("POST", "/products", array(
            'product' => $tempProduct
        ));

        if ($res->status !== 200) {
            return false;
        }

        return $res->body->products[0];
    }

    /**
     * @param $productData
     *
     * @return mixed
     */
    public function buildProduct($productData)
    {
        $product = new stdClass();

        $product->organizationId = Mage::getSingleton('billysbilling/organization')->getOrganizationId();
        $product->name           = $productData->getName();
        $product->description    = $productData->getDescription();
        $product->productNo      = $productData->getSku();
        $product->prices         = array(
            $this->buildPrice($productData->getPrice())
        );

        return $product;
    }

    /**
     * @param $productDataPrice
     *
     * @return mixed
     */
    public function buildPrice($productDataPrice)
    {
        $price = new stdClass();

        $price->unitPrice = $productDataPrice;
        $price->currencyId = Mage::getSingleton('billysbilling/currency')->currency->id;

        return $price;
    }
}
