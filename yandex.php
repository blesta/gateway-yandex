<?php

/**
 * Yandex
 *
 * @package blesta
 * @subpackage blesta.components.gateways.yandex
 * @copyright Copyright (c) 2017, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class yandex extends NonmerchantGateway
{
    /**
     * @var string The version of this gateway
     */
    private static $version = '1.0.0';
    /**
     * @var string The authors of this gateway
     */
    private static $authors = [['name' => 'Phillips Data, Inc.', 'url' => 'http://www.blesta.com']];
    /**
     * @var array An array of meta data for this gateway
     */
    private $meta;
    /**
     * @var string The URL to post payments to
     */
    private $yandex_url = 'https://money.yandex.ru/quickpay/confirm.xml';
    /**
     * @var string The URL to post payments to
     */
    private $demo_yandex_url = 'https://demomoney.yandex.ru/quickpay/confirm.xml';

    /**
     * Construct a new merchant gateway
     */
    public function __construct()
    {

        // Load components required by this gateway
        Loader::loadComponents($this, ['Input', 'Json']);

        // Load the language required by this gateway
        Language::loadLang('yandex', null, dirname(__FILE__) . DS . 'language' . DS);
    }

    /**
     * Returns the name of this gateway
     *
     * @return string The common name of this gateway
     */
    public function getName()
    {
        return Language::_('yandex.name', true);
    }

    /**
     * Returns the version of this gateway
     *
     * @return string The current version of this gateway
     */
    public function getVersion()
    {
        return self::$version;
    }

    /**
     * Returns the name and URL for the authors of this gateway
     *
     * @return array The name and URL of the authors of this gateway
     */
    public function getAuthors()
    {
        return self::$authors;
    }

    /**
     * Return all currencies supported by this gateway
     *
     * @return array A numerically indexed array containing all currency codes (ISO 4217 format) this gateway supports
     */
    public function getCurrencies()
    {
        // Always 643 (Russian ruble according to ISO 4217).
        return ['RUB'];
    }

    /**
     * Sets the currency code to be used for all subsequent payments
     *
     * @param string $currency The ISO 4217 currency code to be used for subsequent payments
     */
    public function setCurrency($currency)
    {
        $this->currency = $currency;
    }

    /**
     * Create and return the view content required to modify the settings of this gateway
     *
     * @param array $meta An array of meta (settings) data belonging to this gateway
     * @return string HTML content containing the fields to update the meta data for this gateway
     */
    public function getSettings(array $meta = null)
    {
        $this->view = $this->makeView('settings', 'default', str_replace(ROOTWEBDIR, '', dirname(__FILE__) . DS));

        // Load the helpers required for this view
        Loader::loadHelpers($this, ['Form', 'Html']);

        $this->view->set('meta', $meta);

        return $this->view->fetch();
    }

    /**
     * Validates the given meta (settings) data to be updated for this gateway
     *
     * @param array $meta An array of meta (settings) data to be updated for this gateway
     * @return array The meta data to be updated in the database for this gateway, or reset into the form on failure
     */
    public function editSettings(array $meta)
    {
        // Verify meta data is valid
        $rules = [
            'receiver_number' => [
                'empty' => [
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'message' => Language::_('yandex.!error.receiver_number.empty', true)
                ]
            ],
            'test_mode' => [
                'valid' => [
                    'if_set' => true,
                    'rule' => ['in_array', ['true', 'false']],
                    'message' => Language::_('yandex.!error.test_mode.valid', true)
                ]
            ]
        ];


        // Set checkbox if not set
        if (!isset($meta['test_mode'])) {
            $meta['test_mode'] = 'false';
        }

        $this->Input->setRules($rules);

        // Validate the given meta data to ensure it meets the requirements
        $this->Input->validates($meta);

        // Return the meta data, no changes required regardless of success or failure for this gateway
        return $meta;
    }

    /**
     * Returns an array of all fields to encrypt when storing in the database
     *
     * @return array An array of the field names to encrypt when storing in the database
     */
    public function encryptableFields()
    {
        return ['receiver_number'];
    }

    /**
     * Sets the meta data for this particular gateway
     *
     * @param array $meta An array of meta data to set for this gateway
     */
    public function setMeta(array $meta = null)
    {
        $this->meta = $meta;
    }

    /**
     * Returns all HTML markup required to render an authorization and capture payment form
     *
     * @param array $contact_info An array of contact info including:
     *  - id The contact ID
     *  - client_id The ID of the client this contact belongs to
     *  - user_id The user ID this contact belongs to (if any)
     *  - contact_type The type of contact
     *  - contact_type_id The ID of the contact type
     *  - first_name The first name on the contact
     *  - last_name The last name on the contact
     *  - title The title of the contact
     *  - company The company name of the contact
     *  - address1 The address 1 line of the contact
     *  - address2 The address 2 line of the contact
     *  - city The city of the contact
     *  - state An array of state info including:
     *      - code The 2 or 3-character state code
     *      - name The local name of the country
     *  - country An array of country info including:
     *      - alpha2 The 2-character country code
     *      - alpha3 The 3-cahracter country code
     *      - name The english name of the country
     *      - alt_name The local name of the country
     *  - zip The zip/postal code of the contact
     * @param float $amount The amount to charge this contact
     * @param array $invoice_amounts An array of invoices, each containing:
     *  - id The ID of the invoice being processed
     *  - amount The amount being processed for this invoice (which is included in $amount)
     * @param array $options An array of options including:
     *  - description The Description of the charge
     *  - return_url The URL to redirect users to after a successful payment
     *  - recur An array of recurring info including:
     *      - amount The amount to recur
     *      - term The term to recur
     *      - period The recurring period (day, week, month, year, onetime) used in conjunction
     *          with term in order to determine the next recurring payment
     * @return string HTML markup required to render an authorization and capture payment form
     */
    public function buildProcess(array $contact_info, $amount, array $invoice_amounts = null, array $options = null)
    {
        // Force 2-decimal places only
        $amount = round($amount, 2);

        $this->view = $this->makeView('process', 'default', str_replace(ROOTWEBDIR, '', dirname(__FILE__) . DS));

        // Load the helpers required for this view
        Loader::loadHelpers($this, ['Form', 'Html']);

        // A list of key/value hidden fields to set for the payment form
        $fields = [
            // Set account/invoice info to use later
            'receiver' => $this->ifSet($this->meta['receiver_number']),
            'quickpay-form' => 'shop',
            'targets' => $this->ifSet($options['description']),
            'sum' => $amount,
            'successURL' => $this->ifSet($options['return_url']),
            'label' => base64_encode(
                $this->serializeInvoices($this->ifSet($contact_info['client_id']), $invoice_amounts)
            ),
            'need-fio' => 'true',
            'need-email' => 'true',
        ];

        $this->view->set(
            'post_to',
            ($this->ifSet($this->meta['test_mode']) == 'true' ? $this->demo_yandex_url : $this->yandex_url)
        );
        $this->view->set('fields', $fields);

        return $this->view->fetch();
    }


    /**
     * Validates the incoming POST/GET response from the gateway to ensure it is
     * legitimate and can be trusted.
     *
     * @param array $get The GET data for this request
     * @param array $post The POST data for this request
     * @return array An array of transaction data, sets any errors using Input if the data fails to validate
     *  - client_id The ID of the client that attempted the payment
     *  - amount The amount of the payment
     *  - currency The currency of the payment
     *  - invoices An array of invoices and the amount the payment should be applied to (if any) including:
     *      - id The ID of the invoice to apply to
     *      - amount The amount to apply to the invoice
     *  - status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
     *  - reference_id The reference ID for gateway-only use with this transaction (optional)
     *  - transaction_id The ID returned by the gateway to identify this transaction
     *  - parent_transaction_id The ID returned by the gateway to identify this
     *      transaction's original transaction (in the case of refunds)
     */
    public function validate(array $get, array $post)
    {

        $status = 'declined';
        if (isset($post['unaccepted']) && $post['unaccepted'] == false) {
            $status = 'approved';
        }

        $this->log(
            $this->ifSet($_SERVER['REQUEST_URI']),
            serialize($post),
            'output',
            $this->ifSet($post['unaccepted'], false)
        );

        if (empty($post)) {
            $this->Input->setErrors($this->getCommonError('invalid'));
            return;
        }

        $invoices = $this->unserializeInvoices(base64_decode($post['label']));
        $client_id = $invoices['client_id'];
        unset($invoices['client_id']);


        return [
            'client_id' => $client_id,
            'amount' => $this->ifSet($post['amount']),
            'currency' => 'RUB',
            'invoices' => $invoices,
            'status' => $status,
            'transaction_id' => $this->ifSet($post['operation_id']),
            'parent_transaction_id' => null
        ];
    }

    /**
     * Returns data regarding a success transaction. This method is invoked when
     * a client returns from the non-merchant gateway's web site back to Blesta.
     *
     * @param array $get The GET data for this request
     * @param array $post The POST data for this request
     * @return array An array of transaction data, may set errors using Input if the data appears invalid
     *  - client_id The ID of the client that attempted the payment
     *  - amount The amount of the payment
     *  - currency The currency of the payment
     *  - invoices An array of invoices and the amount the payment should be applied to (if any) including:
     *      - id The ID of the invoice to apply to
     *      - amount The amount to apply to the invoice
     *  - status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
     *  - transaction_id The ID returned by the gateway to identify this transaction
     *  - parent_transaction_id The ID returned by the gateway to identify this transaction's original transaction
     */
    public function success(array $get, array $post)
    {
        // Yandex does not return significant data; return nothing
        return null;
    }

    /**
     * Serializes an array of invoice info into a string
     *
     * @param mixed the client id
     * @param array A numerically indexed array invoices info including:
     *  - id The ID of the invoice
     *  - amount The amount relating to the invoice
     * @return string A serialized string of invoice info in the format of key1=value1|key2=value2
     */
    private function serializeInvoices($client_id, array $invoices)
    {
        $str = 'client_id=' . $client_id;
        foreach ($invoices as $i => $invoice) {
            $str .= '|' . $invoice['id'] . '=' . $invoice['amount'];
        }

        return $str;
    }

    /**
     * Unserializes a string of invoice info into an array
     *
     * @param string A serialized string of invoice info in the format of key1=value1|key2=value2
     * @return array A numerically indexed array invoices info including:
     *  - client_id The client id
     *     - id The ID of the invoice
     *     - amount The amount relating to the invoice
     */
    private function unserializeInvoices($str)
    {
        $invoices = [];
        $temp = explode('|', $str);
        foreach ($temp as $pair) {
            $pairs = explode('=', $pair, 2);
            if (count($pairs) != 2) {
                continue;
            }

            if ($pairs[0] == 'client_id') {
                $invoices['client_id'] = $pairs[1];
            } else {
                $invoices[] = ['id' => $pairs[0], 'amount' => $pairs[1]];
            }
        }

        return $invoices;
    }
}
