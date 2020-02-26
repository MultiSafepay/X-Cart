<?php

namespace XLite\Module\MultiSafepay\Connect\Model\Payment\Processor;

use XLite\Module\MultiSafepay\Connect\Model\Cart;
use XLite\Module\MultiSafepay\Connect\Model\Payment\Refund;
use XLite\Module\MultiSafepay\Connect\Model\Tax;

class Connect extends \XLite\Model\Payment\Base\WebBased {

    public $settings = 'MultiSafepay Connect';
    public $icon = 'msp_connect.png';
    public $gateway = 'Connect';
    public $transactionid = '';

    /**
     * Get operation types
     *
     * @return array
     */
    public function getOperationTypes()
    {
        return array(
            self::OPERATION_SALE,
        );
    }

    /**
     * Get allowed backend transactions
     *
     * @return string[] Status code
     */
    public function getAllowedTransactions()
    {
        return [
            \XLite\Model\Payment\BackendTransaction::TRAN_TYPE_REFUND,
            \XLite\Model\Payment\BackendTransaction::TRAN_TYPE_REFUND_PART,
            \XLite\Model\Payment\BackendTransaction::TRAN_TYPE_REFUND_MULTI,
        ];
    }

    /**
     * Get settings widget or template
     *
     * @return string Widget class name or template path
     */
    public function getSettingsWidget()
    {
        return 'modules/MultiSafepay/Connect/config.twig';
    }

    /**
     * Process return
     *
     * @param \XLite\Model\Payment\Transaction $transaction Return-owner transaction
     *
     * @return void
     */
    public function processReturn(\XLite\Model\Payment\Transaction $transaction)
    {
        require_once LC_DIR_MODULES . 'MultiSafepay' . LC_DS . 'API' . LC_DS . 'Autoloader.php';
        parent::processReturn($transaction);

        try{
            if (\XLite\Core\Request::getInstance()->transactionid) {

                $settings = $this->getPaymentSettings($this->settings);
                if($this->getSetting('transaction_type') == '1')
                {
                    $order_id   =   \XLite\Core\Request::getInstance()->txnId;
                } else {
                    $order_id   =   \XLite\Core\Request::getInstance()->transactionid;
                }

                $msp = new \MultiSafepayAPI\Client();
                $msp->setApiKey($this->getSetting('api_key'));
                $msp->setApiUrl($this->getEnvironment());

                $response = $msp->orders->get('orders', $order_id);

                switch ($response->status)
                {
                    case "initialized":
                        $order_status = $transaction::STATUS_PENDING;
                        break;
                    case "completed":
                        $order_status = $transaction::STATUS_SUCCESS;
                        break;
                    case "uncleared":
                        $order_status = $transaction::STATUS_PENDING;
                        break;
                    case "void":
                        $order_status = $transaction::STATUS_VOID;
                        break;
                    case "declined":
                        $order_status = \XLite\Model\Order\Status\Payment::STATUS_DECLINED;
                        break;
                    case "refunded":
                        $this->getOrder()->setPaymentStatus(\XLite\Model\Order\Status\Payment::STATUS_REFUNDED);
                        $this->getOrder()->updateOrder();
                        $order_status = $transaction::STATUS_CANCELED;
                        break;
                    case "partial_refunded":
                        $order_status = $transaction::STATUS_PENDING;
                        $this->getOrder()->setPaymentStatus(\XLite\Model\Order\Status\Payment::STATUS_PART_PAID);
                        $this->getOrder()->updateOrder();
                        break;
                    case "expired":
                        $order_status = $transaction::STATUS_CANCELED;
                        break;
                    case "cancelled":
                        $order_status = $transaction::STATUS_CANCELED;
                        break;
                    case "shipped":
                        break;
                }

                $this->transaction->setStatus($order_status);
                $this->transaction->update();

                if (\XLite\Core\Request::getInstance()->redirect == 'true') {
                    if (\XLite\Core\Request::getInstance()->type == 'initial') {
                        echo '<a href="' . $this->getReturnURL(null, true) . '&redirect=true&transactionid=' . \XLite\Core\Request::getInstance()->transactionid . '">Return to the webshop</a>';
                        exit;
                    } elseif (\XLite\Core\Request::getInstance()->cancel == '1') {
                        $order_status   =   $transaction::STATUS_CANCELED;
                        $this->transaction->setStatus($order_status);
                    } else {
                        header('Location:' . $this->getReturnURL(null, true) . '&redirect=true');
                        echo 'OK';
                        exit;
                    }
                } else {
                    echo 'OK';
                    exit;
                }
            }
        } catch (Exception $e) {

        }
    }

    /**
     * Get initial transaction type (used when customer places order)
     *
     * @param \XLite\Model\Payment\Method $method Payment method object OPTIONAL
     *
     * @return string
     */
    public function getInitialTransactionType($method = null)
    {
        return \XLite\Model\Payment\BackendTransaction::TRAN_TYPE_SALE;
    }

    /**
     * Check - payment method is configured or not
     *
     * @param \XLite\Model\Payment\Method $method Payment method
     *
     * @return boolean
     */
    public function isConfigured(\XLite\Model\Payment\Method $method)
    {
        return parent::isConfigured($method) && $this->isAllSettingsProvided($method);
    }

    /**
     * Check - payment method is configured or not
     *
     * @param \XLite\Model\Payment\Method $method Payment method
     *
     * @return boolean
     */
    public function isAllSettingsProvided(\XLite\Model\Payment\Method $method)
    {
        return $method->getSetting('api_key');
    }

    /**
     * Get return type
     *
     * @return string
     */
    public function getReturnType()
    {
        return self::RETURN_TYPE_HTTP_REDIRECT;
    }

    /**
     * Returns the list of settings available for this payment processor
     *
     * @return array
     */
    public function getAvailableSettings()
    {
        return array(
            'account_type',
            'api_key',
            'days_active',
            'ga_accountid',
            'prefix'
        );
    }

    /**
     * Get payment method admin zone icon URL
     *
     * @param \XLite\Model\Payment\Method $method Payment method
     *
     * @return string
     */
    public function getAdminIconURL(\XLite\Model\Payment\Method $method)
    {
        return true;
    }

    /**
     * Check - payment method has enabled test mode or not
     *
     * @param \XLite\Model\Payment\Method $method Payment method
     *
     * @return boolean
     */
    public function isTestMode(\XLite\Model\Payment\Method $method)
    {
        return '0' == $this->getSetting('Test');
    }

    /**
     * Start payment transaction
     *
     * @param type $issuerId
     * @param type $transid
     * @param type $settings
     * @param string $gateway
     */

    public function startTransaction($issuerId = '', $transid, $settings = '', $gateway = '')
    {
        require_once LC_DIR_MODULES . 'MultiSafepay' . LC_DS . 'API' . LC_DS . 'Autoloader.php';

        if (!$this->transaction && $transid) {
            $this->transaction = \XLite\Core\Database::getRepo('XLite\Model\Payment\Transaction')->findOneByPublicTxnId($transid);
        }

        if ($settings != '') {
            $this->settings = $settings;
        }

        $trans_type =   "redirect";

        if($issuerId != '' && $gateway == 'IDEAL')
        {
            $trans_type   =   "direct";
        }

        if(in_array($gateway,["BANKTRANS", "TRUSTLY"]) && $this->getSetting('transaction_type') == '1')
        {
            $trans_type =   "direct";
        }

        if ($this->transaction) {
            $orderId    =   $this->transaction->getPublicTxnId();
            $settings   =   $this->getPaymentSettings($this->settings);

            $items_list =   '<ul>';
            foreach ($this->getOrder()->getItems() as $item)
            {
                $product    =   $item->getProduct();
                $items_list.= '<li>' . $item->getAmount() . ' x ' . $product->getName() . '</li>';
            }
            $items_list .= '</ul>';

            try {
                $msp = new \MultiSafepayAPI\Client();
                $msp->setApiKey($this->getSetting('api_key'));
                $msp->setApiUrl($this->getEnvironment());
                list($billing_street, $billing_housenumber) = $this->parseAddress($this->getProfile()->getBillingAddress()->getStreet());
                list($shipping_street, $shipping_housenumber) = $this->parseAddress($this->getProfile()->getShippingAddress()->getStreet());

                $postData = array(
                    "type"      =>  $trans_type,
                    "order_id"  =>  $orderId,
                    "currency"  =>  strtoupper($this->getOrder()->getCurrency()->getCode()),
                    "amount"    =>  $this->getOrder()->getCurrency()->roundValue($this->transaction->getValue()) * 100,
                    "gateway"   =>  $gateway,
                    "description"=> $this->getInvoiceDescription(),
                    "items"     =>  $items_list,
                    "days_active"=> $this->getSetting('days_active'),
                    "payment_options"=> array(
                        "notification_url"  =>  $this->getReturnURL(null, true) . "&type=initial",
                        "redirect_url"      =>  $this->getReturnURL(null, true) . '&redirect=true',
                        "cancel_url"        =>  \XLite::getInstance()->getShopURL(\XLite\Core\Converter::buildURL('checkout'),
                            \XLite\Core\Config::getInstance()->Security->customer_security
                        ),
                        "close_window"      =>  false
                    ),
                    "customer"  => array(
                        "locale"        =>  $this->getLocaleFromLanguageCode(strtolower(\XLite\Core\Session::getInstance()->getLanguage()->getCode())),
                        "ip_address"    =>  $this->getClientIP(),
                        "forwarded_ip"  =>  $_SERVER['HTTP_X_FORWARDED_FOR'],
                        "first_name"    =>  $this->getProfile()->getBillingAddress()->getFirstname(),
                        "last_name"     =>  $this->getProfile()->getBillingAddress()->getLastname(),
                        "address1"      =>  $billing_street,
                        "house_number"  =>  $billing_housenumber,
                        "zip_code"      =>  $this->getProfile()->getBillingAddress()->getZipcode(),
                        "city"          =>  $this->getProfile()->getBillingAddress()->getCity(),
                        "country"       =>  strtoupper($this->getProfile()->getBillingAddress()->getCountry()->getCode()),
                        "phone"         =>  $this->getProfile()->getBillingAddress()->getPhone(),
                        "email"         =>  $this->getProfile()->getLogin(),
                        "disable_send_email"=> false,
                        "user_agent"    =>  $_SERVER['HTTP_USER_AGENT'],
                        "referrer"      =>  $_SERVER['HTTP_REFERER']
                    ),
                    "delivery"  =>  array(
                        "first_name"    =>  $this->getProfile()->getShippingAddress()->getFirstname(),
                        "last_name"     =>  $this->getProfile()->getShippingAddress()->getLastname(),
                        "address1"      =>  $shipping_street,
                        "house_number"  =>  $shipping_housenumber,
                        "zip_code"      =>  $this->getProfile()->getShippingAddress()->getZipcode(),
                        "city"          =>  $this->getProfile()->getShippingAddress()->getCity(),
                        "country"       =>  strtoupper($this->getProfile()->getShippingAddress()->getCountry()->getCode()),
                        "phone"         =>  $this->getProfile()->getShippingAddress()->getPhone(),
                        "email"         =>  $this->getProfile()->getLogin()
                    ),
                    "shopping_cart" => Cart::getShoppingCart($this->getorder()),
                    "checkout_options" => Tax::getCheckoutOptions(),
                    "gateway_info"=>    array(
                        "issuer_id"     => $issuerId,
                        "phone"         => $this->getProfile()->getShippingAddress()->getPhone(),
                        "email"         => $this->getProfile()->getLogin() ,
                        "referrer"      => $_SERVER['HTTP_REFERER'],
                        "user_agent"    => $_SERVER['HTTP_USER_AGENT']
                    ),
                    "google_analytics"  =>  array(
                        "account"   =>  $this->getSetting('ga_accountid')
                    ),
                    "plugin" => array(
                        "shop" => "X-Cart",
                        "plugin_version" => self::getPluginVersion(),
                        "shop_version" => \XLite\Core\Config::getInstance()->Version->version,
                        "partner" => null,
                        "shop_root_url" => null
                    )
                );

                $msp->orders->post($postData);

                if($trans_type  ==  'direct' && in_array($gateway, $this->directGateways()))
                {
                    $url    =   \XLite\Core\Request::getInstance()->returnURL . '&redirect=true&transactionid=' . \XLite\Core\Request::getInstance()->transid;
                } else {
                    $url    =   $msp->orders->getPaymentLink();
                }

                header('Location: ' . $url);
                exit;
            } catch (\Exception $e) {
                \XLite\Core\TopMessage::addError("Error " .$e->getMessage());
                return  false;
            }
        }
    }

    /**
     * Convert language_code to locale
     *
     * @param type $language_code
     * @return type
     */

    public function getLocaleFromLanguageCode($language_code)
    {
        $locale_array = array
        (
            'nl' => 'nl_NL',
            'en' => 'en_GB',
            'fr' => 'fr_FR',
            'es' => 'es_ES',
            'de' => 'de_DE',
            'it' => 'it_IT',
            'sv' => 'sv_SE',
            'tr' => 'tr_TR',
            'cs' => 'cs_CZ',
            'pl' => 'pl_PL',
            'pt' => 'pt_PT',
            'he' => 'he_IL',
            'ru' => 'ru_RU',
            'ar' => 'ar_AR',
            'cn' => 'zh_CN',
            'ro' => 'ro_RO',
            'da' => 'da_DK',
            'fi' => 'fi_FI',
            'no' => 'no_NO'
        );

        if (array_key_exists($language_code, $locale_array)) {
            return $locale_array[$language_code];
        } else {
            return null;
        }
    }

    /**
     * Return the API Url based on the account type specified
     *
     * @return string
     */

    protected function getEnvironment()
    {
        if ($this->getSetting('account_type') == '1') {
            return "https://api.multisafepay.com/v1/json/";
        } else {
            return "https://testapi.multisafepay.com/v1/json/";
        }
    }

    /**
     * Array of type direct supported gateways
     *
     * @return type array
     */

    protected function directGateways()
    {
        return array(
            "BANKTRANS",
            "DIRDEB",
            "PAYPAL"
        );
    }

    /**
     * Get array of payment settings
     *
     * @return array
     */

    public function getPaymentSettings($settings)
    {
        $result = array();
        $this->settings = $settings;

        $fields = $this->getAvailableSettings();
        foreach ($fields as $field)
        {
            $result[$field] = $this->getSetting($field);
        }

        return $result;
    }

    /**
     * Get payment method setting by name
     *
     * @param string $name Setting name
     *
     * @result string
     */

    protected function getSetting($name)
    {
        $settings   =   $this->settings;
        $result     =   parent::getSetting($name);

        if (empty($result)) {
            $method = \XLite\Core\Database::getRepo('XLite\Model\Payment\Method')->findOneBy(array('service_name' => $this->settings));
            $result = $method ? $method->getSetting($name) : null;
        }

        return $result;
    }

    /**
     * Get redirect form URL
     *
     * @return string
     */

    protected function getFormURL()
    {
        return \XLite\Core\Converter::buildURL('connect', 'transaction');
    }

    /**
     * Get redirect form fields list
     *
     * @return array
     */

    protected function getFormFields()
    {
        return array(
            'transid' => $this->transaction->getPublicTxnId(),
            'returnURL' => $this->getReturnURL(null, true),
        );
    }

    /**
     * Get input template
     *
     * @return string
     */

    public function getCheckoutTemplate(\XLite\Model\Payment\Method $method)
    {
        return 'modules/MultiSafepay/Connect/checkout/gateway.twig';
    }

    /**
     * Get payment method icon path
     *
     * @param type $order
     * @param type $method
     * @return type string
     */

    public function getIconPath(\XLite\Model\Order $order = null, \XLite\Model\Payment\Method $method = null)
    {
        return 'modules/MultiSafepay/' . $this->gateway . '/checkout/' . $this->icon;
    }

    /**
     * Split the housenumber, suffix and street by provided address line
     *
     * @param type $street_address
     * @return type
     */

    public function parseAddress($street_address)
    {
        $address = $street_address;
        $apartment = "";

        $offset = strlen($street_address);

        while (($offset = $this->rstrpos($street_address, ' ', $offset)) !== false) {
            if ($offset < strlen($street_address) - 1 && is_numeric($street_address[$offset + 1])) {
                $address = trim(substr($street_address, 0, $offset));
                $apartment = trim(substr($street_address, $offset + 1));
                break;
            }
        }

        if (empty($apartment) && strlen($street_address) > 0 && is_numeric($street_address[0])) {
            $pos = strpos($street_address, ' ');

            if ($pos !== false) {
                $apartment = trim(substr($street_address, 0, $pos), ", \t\n\r\0\x0B");
                $address = trim(substr($street_address, $pos + 1));
            }
        }

        return array($address, $apartment);
    }

    /**
     *
     * @param type $haystack
     * @param type $needle
     * @param type $offset
     * @return boolean
     */

    public function rstrpos($haystack, $needle, $offset = null)
    {
        $size = strlen($haystack);

        if (is_null($offset)) {
            $offset = $size;
        }

        $pos = strpos(strrev($haystack), strrev($needle), $size - $offset);

        if ($pos === false) {
            return false;
        }

        return $size - $pos - strlen($needle);
    }

    public static function getPluginVersion()
    {
        return '2.3.0-RC1';
    }

    /**
     * @param \XLite\Model\Payment\BackendTransaction $transaction
     * @return bool
     */
    protected function doRefund(\XLite\Model\Payment\BackendTransaction $transaction)
    {
        return Refund::simpleRefund($transaction);
    }

    /**
     * @param \XLite\Model\Payment\BackendTransaction $transaction
     * @return bool
     */
    protected function doRefundPart(\XLite\Model\Payment\BackendTransaction $transaction)
    {
        return $this->doRefund($transaction);
    }

    /**
     * @param \XLite\Model\Payment\BackendTransaction $transaction
     * @return bool
     */
    protected function doRefundMulti(\XLite\Model\Payment\BackendTransaction $transaction)
    {
        return $this->doRefund($transaction);
    }
}
