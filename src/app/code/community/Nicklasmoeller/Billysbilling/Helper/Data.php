<?php

/**
 * Class Nicklasmoeller_Billysbilling_Helper_Data
 *
 * @author Nicklas Møller <hello@nicklasmoeller.com>
 */
class Nicklasmoeller_Billysbilling_Helper_Data extends Mage_Core_Helper_Abstract {
    /**
     * @return bool
     */
    public function isEnabled()
    {
        return (boolean) Mage::getStoreConfig('billysbilling/settings/enabled');
    }

    /**
     * @return bool
     */
    public function isApiKeySet()
    {
        return (boolean) Mage::getStoreConfig('billysbilling/api/key');
    }

    /**
     * @return bool
     */
    public function isSingleCustomer()
    {
        return (boolean) Mage::getStoreConfig('billysbilling/settings/singleuser');
    }
}
