<?php

/**
 * Class Nicklasmoeller_Billysbilling_Model_Currency
 *
 * @author Nicklas Møller <hello@nicklasmoeller.com>
 * @version 0.2.0
 */
class Nicklasmoeller_Billysbilling_Model_Currency extends Nicklasmoeller_Billysbilling_Model_Abstract
{
    public $currency;

    /**
     * @param $currencyId
     *
     * @return bool|mixed
     */
    public function getCurrency($currencyId)
    {
        if ($this->currency) {
            return $this->currency;
        }

        $res = $this->client->request("GET", "/currencies/" . $currencyId);

        if ($res->status !== 200) {
            return false;
        }

        $this->currency = $res->body->currency;

        return $this->currency;
    }
}