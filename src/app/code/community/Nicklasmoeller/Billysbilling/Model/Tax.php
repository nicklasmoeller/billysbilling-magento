<?php

/**
 * Class Nicklasmoeller_Billysbilling_Model_Tax
 *
 * @author Nicklas Møller <hello@nicklasmoeller.com>
 * @version 0.2.0
 */
class Nicklasmoeller_Billysbilling_Model_Tax extends Nicklasmoeller_Billysbilling_Model_Abstract
{
    /**
     * @param $orderData
     *
     * @return bool|mixed
     */
    public function getTaxRate($orderData)
    {
        $rate = $this->buildTaxRate($orderData->getFullTaxInfo());

        $res = $this->client->request("GET", "/taxRates");

        foreach ($res->body->taxRates as $singleRate) {
            if ($singleRate->abbreviation == $rate->abbreviation) {
                Mage::log($singleRate, null, 'singlerate.log');
                return $singleRate;
            }
        }

        $res = $this->client->request("POST", "/taxRates", array(
            'taxRate' => $rate
        ));

        if ($res->status !== 200) {
            return false;
        }

        return $res->body->taxRates[0];
    }

    /**
     * @param $taxRate
     *
     * @return mixed
     */
    public function buildTaxRate($taxRate)
    {
        $rate = new stdClass();

        $rate->organizationId   = Mage::getSingleton('billysbilling/organization')->getOrganizationId();
        $rate->name             = $taxRate[0]['id'];
        $rate->abbreviation     = "tax_" . str_replace(' ', '_', strtolower($taxRate[0]['id']));
        $rate->description      = $taxRate[0]['id'];
        Mage::log($taxRate[0]['percent'], null, 'percent.log');
        Mage::log($taxRate, null, 'taxrate.log');
        $rate->rate             = "0." . $taxRate[0]['percent'];
        $rate->appliesToSales   = true;
        $rate->isActive         = true;

        return $rate;
    }
}