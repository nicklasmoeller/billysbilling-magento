<?php

/**
 * Abstract class Nicklasmoeller_Billysbilling_Model_Abstract
 *
 * @author Nicklas Møller <hello@nicklasmoeller.com>
 */
abstract class Nicklasmoeller_Billysbilling_Model_Abstract extends Mage_Core_Model_Abstract
{
    /**
     * @var $client
     */
    public $client;

    /**
     * Instantiate the client
     */
    public function __construct()
    {
        $this->client = Mage::getModel('billysbilling/client');
    }
}