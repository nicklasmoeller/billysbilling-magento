<?php

/**
 * Class Nicklasmoeller_Billysbilling_Model_Country
 *
 * @author Nicklas Møller <hello@nicklasmoeller.com>
 */
class Nicklasmoeller_Billysbilling_Model_Country extends Nicklasmoeller_Billysbilling_Model_Abstract
{
    /**
     * @var
     */
    private $countries;

    /**
     * @var
     */
    private $country;

    /**
     * @param $countryId
     *
     * @return bool|mixed
     */
    public function getCountry($countryId)
    {
        if (!$this->countries) {
            $res = $this->client->request("GET", "/countries");

            if ($res->status !== 200) {
                return false;
            }

            $this->countries = $res->body->countries;
        }

        foreach ($this->countries as $country) {
            if ($country->id == $countryId) {
                $this->country = $country->id;
                break;
            }
        }

        return $this->country;
    }

}