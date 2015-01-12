<?php

/**
 * Class Nicklasmoeller_Billysbilling_Model_Client
 *
 * @author Nicklas Møller <hello@nicklasmoeller.com>
 */
class Nicklasmoeller_Billysbilling_Model_Client
{
    /**
     * @var
     */
    private $key;

    /**
     * @var string
     */
    private $uri = "https://api.billysbilling.com/v2";

    /**
     * Instantiate the api key
     */
    public function __construct()
    {
        $this->key = Mage::getStoreConfig('billysbilling/api/key');
    }

	/**
     * @param               $method
     * @param               $endpoint
     * @param null|array    $body
     *
     * @return object
     */
    public function request($method, $endpoint, $body = null)
    {
        $headers = array("X-Access-Token: " . $this->key);
        $ch = curl_init($this->uri . $endpoint);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        if ($body) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
            $headers[] = "Content-Type: application/json";
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $body = json_decode($response);
        $info = curl_getinfo($ch);

        return (object) array(
            'status' => $info['http_code'],
            'body'   => $body
        );
    }
}
