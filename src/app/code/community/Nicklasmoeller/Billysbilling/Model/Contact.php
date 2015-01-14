<?php

/**
 * Class Nicklasmoeller_Billysbilling_Model_Contact
 *
 * @author Nicklas Møller <hello@nicklasmoeller.com>
 */
class Nicklasmoeller_Billysbilling_Model_Contact extends Nicklasmoeller_Billysbilling_Model_Abstract
{
    public $id;

    protected $customer;
    protected $prefix = "m_";
    protected $country;

    /**
     * @param $billingAddress
     *
     * @return bool|mixed
     */
    public function getCustomer($billingAddress)
    {
        if ($this->customer) {
            return $this->customer;
        }

        $this->country = $billingAddress->getCountryId();

        if (Mage::helper('billysbilling')->isSingleCustomer() || !$billingAddress->getCustomerId()) {
            $this->id = $this->prefix . $this->country;

            if ($billingAddress->getCompany()) {
                $this->id .= '_c';
            }
        } else {
            $this->id = $this->prefix . $billingAddress->getCustomerId();
        }

        $res = $this->client->request("GET", "/contacts?contactNo=" . $this->id);

        if ($res->body->meta->paging->total > 0) {
            $this->customer = $res->body->contacts[0];

            return $this->customer;
        }

        $contact = $this->buildCustomer($billingAddress);

        $res = $this->client->request("POST", "/contacts", array(
            'contact' => $contact
        ));

        if ($res->status !== 200) {
            return false;
        }

        $this->customer = $res->body->contacts[0];

        return $this->customer;
    }

    /**
     * @param $billingAddress
     *
     * @return mixed
     */
    public function buildCustomer($billingAddress)
    {
        $contact = new stdClass();

        $contact->organizationId        = Mage::getSingleton('billysbilling/organization')->getOrganizationId();
        $contact->contactNo             = $this->id;

        if (Mage::helper('billysbilling')->isSingleCustomer() || !$billingAddress->getCustomerId()) {
            $contact->type              = 'person';
            $contact->name              = 'Magento Sales';

            if ($this->country == "DK" || $this->country == "US") {
                $contact->countryId     = Mage::getSingleton('billysbilling/country')->getCountry($this->country);
                $contact->name .= ' ' . $this->country;
            } else {
                $contact->countryId     = Mage::getSingleton('billysbilling/country')->getCountry('DE');
                $contact->name .= ' EU';
            }

            if ($billingAddress->getCompany()) {
                $contact->type = 'company';
                $contact->name .= ' company';
            }

        } else {
            $contact->countryId         = Mage::getSingleton('billysbilling/country')->getCountry($this->country);
            $contact->zipcodeText       = $billingAddress->getPostcode();
            $contact->stateText         = $billingAddress->getRegion();
            $contact->cityText          = $billingAddress->getCity();
            $contact->street            = $billingAddress->getStreetFull();
            $contact->registrationNo    = $billingAddress->getVatId();
            $contact->phone             = $billingAddress->getTelephone();
            $contact->isCustomer        = true;

            if ($billingAddress->getCompany()) {
                $contact->type = 'company';
                $contact->name = $billingAddress->getCompany();
            } else {
                $contact->type = 'person';
                $contact->name = $billingAddress->getName();
            }
        }

        return $contact;
    }
}
