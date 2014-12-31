<?php

/**
 * Class Nicklasmoeller_Billysbilling_Model_Organization
 *
 * @author Nicklas Møller <hello@nicklasmoeller.com>
 * @version 0.2.0
 */
class Nicklasmoeller_Billysbilling_Model_Organization extends Nicklasmoeller_Billysbilling_Model_Abstract
{
    protected $organizationId;

    /**
     * @return bool|int
     */
    public function getOrganizationId()
    {
        if ($this->organizationId) {
            return $this->organizationId;
        }

        $res = $this->client->request("GET", "/organization");

        if ($res->status !== 200) {
            return false;
        }

        $this->organizationId = $res->body->organization->id;

        return $this->organizationId;
    }
}