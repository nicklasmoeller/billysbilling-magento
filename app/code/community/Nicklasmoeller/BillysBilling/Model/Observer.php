<?php

/**
 * Class Nicklasmoeller_BillysBilling_Model_Observer
 *
 * @author Nicklas Møller <nicklasmoeller@outlook.com>
 * @version 0.0.1
 */
class Nicklasmoeller_BillysBilling_Model_Observer
{
    /**
     * @var string
     */
    protected $apiKey;
    /**
     * @var string
     */
    protected $apiUri;

    /**
     * @var
     */
    private $organizationId;
    /**
     * @var
     */
    private $countries;
    /**
     * @var
     */
    private $currency;

    /**
     * @var
     */
    private $customer;
    /**
     * @var
     */
    private $country;
    /**
     * @var
     */
    private $invoice;

    /**
     * @var
     */
    private $orderData;
    /**
     * @var
     */
    private $billingAddress;

    /**
     * @var
     */
    private $event;

    /**
     *
     */
    public function __construct()
    {
        $this->apiKey = Mage::getStoreConfig('billysbilling/api/key');
        $this->apiUri = Mage::getStoreConfig('billysbilling/api/uri');
    }

    /**
     * @param Varien_Event_Observer $observer
     *
     * @return bool
     */
    public function billysObserver(Varien_Event_Observer $observer)
    {
        if (!$this->apiKey || !$this->apiUri) {
            return false;
        }

        $this->event = $observer->getEvent();

        if ($this->event->getName() == 'sales_order_creditmemo_refund') {
            $this->orderData = $observer->getCreditmemo()->getOrder();
        } else {
            $this->orderData = $observer->getOrder();
        }

        $this->billingAddress = $this->orderData->getBillingAddress();

        $contact = $this->getCustomer($this->billingAddress);

        $this->getInvoice($this->orderData, $contact);
    }

    /**
     * @param      $method
     * @param      $endpoint
     * @param null $body
     *
     * @return object
     */
    private function request($method, $endpoint, $body = null)
    {
        $headers = ["X-Access-Token: " . $this->apiKey];
        $ch = curl_init($this->apiUri . $endpoint);
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

        // We're in testing phases. Don't delete these lines, for debugging purposes
        Mage::log($method . ": " . $endpoint . ": ", null, 'curl.log');
        Mage::log($info, null, 'curl.log');
        Mage::log($body, null, 'curl.log');

        return (object) [
            'status' => $info['http_code'],
            'body'   => $body
        ];
    }

    /**
     * @return bool
     */
    public function getOrganizationId()
    {
        if ($this->organizationId) {
            return $this->organizationId;
        }

        $res = $this->request("GET", "/organization");

        if ($res->status !== 200) {
            return false;
        }

        $this->organizationId = $res->body->organization->id;

        return $this->organizationId;
    }

    /**
     * @param $countryId
     *
     * @return bool
     */
    public function getCountry($countryId)
    {
        if (!$this->countries) {
            $res = $this->request("GET", "/countries");

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

    /**
     * @param $currencyId
     *
     * @return bool
     */
    public function getCurrency($currencyId)
    {
        if ($this->currency) {
            return $this->currency;
        }

        $res = $this->request("GET", "/currencies/" . $currencyId);

        if ($res->status !== 200) {
            return false;
        }

        $this->currency = $res->body->currency;

        return $this->currency;
    }

    /**
     * @param $billingAddress
     *
     * @return bool
     */
    public function getCustomer($billingAddress)
    {
        if ($this->customer) {
            return $this->customer;
        }

        $res = $this->request("GET", "/contacts?contactNo=" . $billingAddress->getCustomerId());

        if ($res->body->meta->paging->total > 0) {
            $this->customer = $res->body->contacts[0];

            return $this->customer;
        }

        $contact = $this->buildCustomer($billingAddress);

        $res = $this->request("POST", "/contacts", [
            'contact' => $contact
        ]);

        if ($res->status !== 200) {
            return false;
        }

        $this->customer = $res->body->contacts[0];

        return $this->customer;
    }

    /**
     * @param $billingAddress
     *
     * @return stdClass
     */
    public function buildCustomer($billingAddress)
    {
        $contact = new stdClass();

        $contact->organizationId = $this->getOrganizationId();
        $contact->contactNo      = $billingAddress->getCustomerId();
        $contact->countryId      = $this->getCountry($billingAddress->getCountryId());
        $contact->zipcodeText    = $billingAddress->getPostcode();
        $contact->stateText      = $billingAddress->getRegion();
        $contact->cityText       = $billingAddress->getCity();
        $contact->street         = $billingAddress->getStreetFull();
        $contact->registrationNo = $billingAddress->getVatId();
        $contact->phone          = $billingAddress->getTelephone();
        $contact->isCustomer     = true;

        if ($billingAddress->getCompany()) {
            $contact->type = 'company';
            $contact->name = $billingAddress->getCompany();
        } else {
            $contact->type = 'person';
            $contact->name = $billingAddress->getName();
        }

        return $contact;
    }

    /**
     * @param $orderData
     * @param $contact
     *
     * @return bool
     */
    public function getInvoice($orderData, $contact)
    {
        $invoice = $this->buildInvoice($orderData, $contact);

        return $invoice;
    }

    /**
     * @param $orderData
     * @param $contact
     *
     * @return bool
     */
    public function buildInvoice($orderData, $contact)
    {
        if ($this->event->getName() == 'sales_order_creditmemo_refund') {
            $type = 'creditNote';
            $paymentTermsDays = null;

            $invoicePrefix = "c";
        } elseif ($this->event->getName() == 'sales_order_invoice_register') {
            $type = 'invoice';
            $paymentTermsDays = 8;

            $invoicePrefix = "";
        } else {
            Mage::log($this->event->getName(), null, 'billys_debugging.log');
        }

        $entryDate = date('Y-m-d');

        $invoice = new stdClass();

        $invoice->organizationId   = $this->getOrganizationId();
        $invoice->contactId        = $contact->id;
        $invoice->type             = $type;
        $invoice->state            = 'approved';
        $invoice->invoiceNo        = $invoicePrefix . $orderData->getId();
        $invoice->currencyId       = $this->getCurrency($orderData->getOrderCurrencyCode())->id;
        $invoice->entryDate        = $entryDate;
        $invoice->paymentTermsDays = $paymentTermsDays;
        $invoice->taxMode          = 'incl';

        $invoice->lines = $this->buildInvoiceLines($orderData);

        $res = $this->request("POST", "/invoices", [
            'invoice' => $invoice
        ]);

        if ($res->status !== 200) {
            return false;
        }

        $this->invoice = $res->body->invoices[0];

        return $this->invoice;
    }

    /**
     * @param $orderData
     *
     * @return array
     */
    public function buildInvoiceLines($orderData)
    {
        $lines = [];

        $products = $orderData->getAllItems();

        $this->request("GET", "/invoices");

        $i = 0;

        foreach ($products as $product) {
            $tempProduct = $this->getProduct($product);

            $lines[$i]                = new stdClass();
            $lines[$i]->productId     = $tempProduct->id;
            $lines[$i]->description   = '';
            $lines[$i]->quantity      = $product->getQtyOrdered();
            $lines[$i]->unitPrice     = $product->getPrice() + ($product->getPrice() * $this->getTaxRate()->rate);

            if ($product->getDiscountAmount() > 0) {
                $lines[$i]->discountText  = 'Discounted';
                $lines[$i]->discountMode  = 'cash';
                $lines[$i]->discountValue = $product->getDiscountAmount();
            }

            $lines[$i]->taxRateId   = $this->getTaxRate()->id;

            $i++;
        }

        $lines[$i] = $this->getShipping($orderData);

        return $lines;
    }

    /**
     * @param $product
     *
     * @return bool
     */
    public function getProduct($product)
    {
        $tempProduct = $this->buildProduct($product);

        $res = $this->request("GET", "/products?productNo=" . $tempProduct->productNo);

        if ($res->body->meta->paging->total > 0) {
            return $res->body->products[0];
        }

        $res = $this->request("POST", "/products", [
            'product' => $tempProduct
        ]);

        if ($res->status !== 200) {
            return false;
        }

        return $res->body->products[0];
    }

    /**
     * @param $productData
     *
     * @return stdClass
     */
    public function buildProduct($productData)
    {
        $product = new stdClass();

        $product->organizationId = $this->getOrganizationId();
        $product->name           = $productData->getName();
        $product->description    = $productData->getDescription();
        $product->productNo      = $productData->getSku();
        $product->prices         = [
            $this->buildPrice($productData->getPrice())
        ];

        return $product;
    }

    /**
     * @param $productDataPrice
     *
     * @return stdClass
     */
    public function buildPrice($productDataPrice)
    {
        $price = new stdClass();

        $price->unitPrice = $productDataPrice;
        $price->currencyId = $this->currency->id;

        return $price;
    }

    /**
     * @param $orderData
     *
     * @return bool|stdClass
     */
    public function getShipping($orderData)
    {
        $shipping = $this->buildShipping($orderData);

        $res = $this->request("GET", "/products?productNo=" . $shipping->productNo);

        if ($res->body->meta->paging->total > 0) {
            return $this->buildShippingLine($res->body->products[0]);
        }

        $res = $this->request("POST", "/products", [
            'product' => $shipping
        ]);

        if ($res->status !== 200) {
            return false;
        }

        $product = $res->body->products[0];

        return $this->buildShippingLine($product);
    }

    /**
     * @param $orderData
     *
     * @return stdClass
     */
    public function buildShipping($orderData)
    {
        $shipping = new stdClass();

        $shipping->organizationId = $this->getOrganizationId();
        $shipping->name           = 'Fragt';
        $shipping->description    = $orderData->getShippingDescription();
        $shipping->productNo      = $orderData->getShippingMethod();
        $shipping->prices         = [
            $this->buildPrice($orderData->getShippingInvoiced())
        ];

        return $shipping;
    }

    /**
     * @param $shipping
     *
     * @return stdClass
     */
    public function buildShippingLine($shipping)
    {
        $newLine = new stdClass();
        $newLine->productId = $shipping->id;
        $newLine->description = $shipping->description;
        $newLine->quantity = 1;
        $newLine->unitPrice = $this->orderData->getShippingInvoiced();

        return $newLine;
    }

    /**
     * @return bool
     */
    public function getTaxRate()
    {
        $rate = $this->buildTaxRate($this->orderData->getFullTaxInfo());

        $res = $this->request("GET", "/taxRates");

        foreach ($res->body->taxRates as $singleRate) {
            if ($singleRate->abbreviation == $rate->abbreviation) {
                return $singleRate;
            }
        }

        $res = $this->request("POST", "/taxRates", [
            'taxRate' => $rate
        ]);

        if ($res->status !== 200) {
            return false;
        }

        return $res->body->taxRates[0];
    }

    /**
     * @param $taxRate
     *
     * @return stdClass
     */
    public function buildTaxRate($taxRate)
    {
        $rate = new stdClass();
        
        $rate->organizationId   = $this->getOrganizationId();
        $rate->name             = $taxRate[0]['id'];
        $rate->abbreviation     = "tax_" . str_replace(' ', '_', strtolower($taxRate[0]['id']));
        $rate->description      = $taxRate[0]['id'];
        $rate->rate             = (int)("0." . $taxRate[0]['percent']);
        $rate->appliesToSales   = true;
        $rate->isActive         = true;

        return $rate;
    }
}
