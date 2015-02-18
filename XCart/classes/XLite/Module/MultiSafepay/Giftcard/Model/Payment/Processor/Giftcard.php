<?php

// vim: set ts=4 sw=4 sts=4 et:

namespace XLite\Module\MultiSafepay\Giftcard\Model\Payment\Processor;

class Giftcard extends \XLite\Model\Payment\Base\WebBased {

    /**
     * Get operation types
     *
     * @return array
     */
    public function getOperationTypes() {
        return array(
            self::OPERATION_SALE,
        );
    }
    
    
    protected $allowedCurrencies = array(
        'EUR', 'USD', 'GBP'
    );

    /**
     * Get settings widget or template
     *
     * @return string Widget class name or template path
     */
    public function getSettingsWidget() {
        return 'modules/MultiSafepay/Giftcard/config.tpl';
    }

    /**
     * Process return
     *
     * @param \XLite\Model\Payment\Transaction $transaction Return-owner transaction
     *
     * @return void
     */
    public function processReturn(\XLite\Model\Payment\Transaction $transaction) {
        parent::processReturn($transaction);

        $message = '';
        $data = array();

        if (\XLite\Core\Request::getInstance()->transactionid) {
            $status = $transaction::STATUS_FAILED;

            require_once LC_DIR_MODULES . 'MultiSafepay' . LC_DS . 'Ideal' . LC_DS . 'lib' . LC_DS . 'MultiSafepay.combined.php';

            $settings = $this->getGiftcardPaymentSettings();

            $msp = new \MultiSafepay();
            $msp->test = $settings['environment'] != 'Y' ? false : true;
            $msp->merchant['account_id'] = $settings['accountid'];
            $msp->merchant['site_id'] = $settings['siteid'];
            $msp->merchant['site_code'] = $settings['sitesecurecode'];
            $msp->transaction['id'] = \XLite\Core\Request::getInstance()->transactionid;
            $status = $msp->getStatus();
            $details = $msp->details;


            if ($msp->error) {
                $message .= "Error " . $msp->error_code . ": " . $msp->error . PHP_EOL;
            } else {
                switch ($status) {
                    case "initialized":
                        $order_status = $transaction::STATUS_PENDING;
                        break;
                    case "completed":
                        $order_status = $transaction::STATUS_SUCCESS;
                        break;
                    case "uncleared":
                        $order_status = \XLite\Model\Order\Status\Payment::STATUS_QUEUED;
                        break;
                    case "void":
                        $order_status = $transaction::STATUS_VOID;
                        break;
                    case "declined":
                        $order_status = \XLite\Model\Order\Status\Payment::STATUS_DECLINED;
                        break;
                    case "refunded":
                        $order_status = \XLite\Model\Order\Status\Payment::STATUS_REFUNDED;
                        break;
                    case "partial_refunded":
                        $this->setDetail('status', 'Transaction is partially refunded', 'Status');
                        $this->transaction->setNote('Transaction is partially refunded');
                        $order_status = \XLite\Model\Order\Status\Payment::STATUS_REFUNDED;
                        break;
                    case "expired":
                        $order_status = $transaction::STATUS_CANCELED;
                        break;
                    case "cancelled":
                        //$this->setDetail('status', 'Customer has canceled checkout before completing their payments', 'Status');
                        //$this->transaction->setNote('Customer has canceled checkout before completing their payments');
                        $order_status = $transaction::STATUS_CANCELED;
                        break;
                    case "shipped":
                        //don't do anything for status shipped.
                        //$order_status = $transaction::STATUS_SUCCESS;
                        break;
                }
            }
        } else {
            $message = static::t('Transacion ID is missing, update aborted');
        }

        // Save data in order history

        if ($message) {
            $data['message'] = $message;
        }
        
        // Set transaction status
        $this->transaction->setStatus($order_status);
        
        if(\XLite\Core\Request::getInstance()->redirect != 'true')
        {
            if(\XLite\Core\Request::getInstance()->type == 'initial'){
                echo '<a href="'.$this->getReturnURL(null, true).'&redirect=true&transactionid='.\XLite\Core\Request::getInstance()->transactionid.'">Terug naar de webwinkel</a>';exit;
            }elseif(\XLite\Core\Request::getInstance()->cancel == '1'){
                $order_status = $transaction::STATUS_CANCELED;
                $this->transaction->setStatus($order_status);
            }else{
                echo 'OK';exit;
            }
        }
    }

    /**
     * Get initial transaction type (used when customer places order)
     *
     * @param \XLite\Model\Payment\Method $method Payment method object OPTIONAL
     *
     * @return string
     */
    public function getInitialTransactionType($method = null) {
        return \XLite\Model\Payment\BackendTransaction::TRAN_TYPE_SALE;
    }

    /**
     * Check - payment method is configured or not
     *
     * @param \XLite\Model\Payment\Method $method Payment method
     *
     * @return boolean
     */
    public function isConfigured(\XLite\Model\Payment\Method $method) {
        return parent::isConfigured($method) && $this->isAllSettingsProvided($method);
    }

    /**
     * Check - payment method is configured or not
     *
     * @param \XLite\Model\Payment\Method $method Payment method
     *
     * @return boolean
     */
    public function isAllSettingsProvided(\XLite\Model\Payment\Method $method) {
        return $method->getSetting('accountid') && $method->getSetting('siteid') && $method->getSetting('sitesecurecode') && $method->getSetting('currency');
    }

    /**
     * Get return type
     *
     * @return string
     */
    public function getReturnType() {
        return self::RETURN_TYPE_HTTP_REDIRECT;
    }

    /**
     * Returns the list of settings available for this payment processor
     *
     * @return array
     */
    public function getAvailableSettings() {
        return array(
            'accountid',
            'siteid',
            'sitesecurecode',
            'environment',
            'daysactive',
            'currency',
            'prefix',
            'test',
        );
    }

    /**
     * Get payment method admin zone icon URL
     *
     * @param \XLite\Model\Payment\Method $method Payment method
     *
     * @return string
     */
    public function getAdminIconURL(\XLite\Model\Payment\Method $method) {
        return true;
    }

    /**
     * Check - payment method has enabled test mode or not
     *
     * @param \XLite\Model\Payment\Method $method Payment method
     *
     * @return boolean
     */
    public function isTestMode(\XLite\Model\Payment\Method $method) {
        return 'Y' == $this->getSetting('test');
    }

    /**
     * Start payment transaction
     *
     * @param string  $issuerId Issuer ID selected by customer
     * @param integer $transid  Current transaction ID
     *
     * @return void
     */
    public function doTransactionRequest($issuerId, $transid) {
        //if ($issuerId) {

            if (!$this->transaction && $transid) {
                $this->transaction = \XLite\Core\Database::getRepo('XLite\Model\Payment\Transaction')
                        ->findOneByPublicTxnId($transid);
            }

            if ($this->transaction) {
                $orderId = $this->getSetting('prefix') . $this->transaction->getPublicTxnId();

                require_once LC_DIR_MODULES . 'MultiSafepay' . LC_DS . 'Ideal' . LC_DS . 'lib' . LC_DS . 'MultiSafepay.combined.php';

                $settings = $this->getGiftcardPaymentSettings();

                $msp = new \MultiSafepay();
                $msp->test = $settings['environment'] != 'Y' ? false : true;
                $msp->merchant['account_id'] = $settings['accountid'];
                $msp->merchant['site_id'] = $settings['siteid'];
                $msp->merchant['site_code'] = $settings['sitesecurecode'];
                $msp->merchant['notification_url'] = $this->getReturnURL(null, true) . "&type=initial";
                $msp->merchant['cancel_url'] = $this->getReturnURL(null, true, true);
                $msp->merchant['redirect_url'] = $this->getReturnURL(null, true)."&redirect=true";
                $msp->customer['locale'] = strtoupper(\XLite\Core\Session::getInstance()->getLanguage()->getCode());
                $msp->customer['firstname'] = $this->getProfile()->getBillingAddress()->getFirstname();
                $msp->customer['lastname'] = $this->getProfile()->getBillingAddress()->getLastname();
                $msp->customer['zipcode'] = $this->getProfile()->getBillingAddress()->getZipcode();
                $msp->customer['state'] = '';
                $msp->customer['city'] = $this->getProfile()->getBillingAddress()->getCity();
                $msp->customer['country'] = strtoupper($this->getProfile()->getBillingAddress()->getCountry()->getCode());
                $msp->customer['phone'] = $this->getProfile()->getBillingAddress()->getPhone();
                $msp->customer['email'] = $this->getProfile()->getLogin();
                $msp->customer['ipaddress'] = $_SERVER['REMOTE_ADDR'];
                $msp->customer['forwardedip'] = $_SERVER['HTTP_X_FORWARDED_FOR'];
                $msp->parseCustomerAddress($this->getProfile()->getBillingAddress()->getStreet()); 
                
                $msp->transaction['id'] = $orderId;
                $msp->transaction['currency'] = strtoupper($this->getOrder()->getCurrency()->getCode());
                $msp->transaction['amount'] = $this->getOrder()->getCurrency()->roundValue($this->transaction->getValue())*100;
                $msp->transaction['description'] = 'Order #' . $this->getOrder()->getOrderNumber();
                // $msp->transaction['items'] = $items;
                $msp->transaction['gateway'] = 'WEBSHOPGIFTCARD';
                $msp->transaction['daysactive'] = $settings['daysactive'];
                $msp->plugin_name = 'X-CART';
                $msp->version = '1.0.0';
                $msp->plugin['shop'] = 'X-Cart';
                $msp->plugin['shop_version'] = 'VM_VERSION';
                $msp->plugin['plugin_version'] = '1.0.0';
                $msp->plugin['partner'] = '';
                $msp->plugin['shop_root_url'] = '';
                $url = $msp->startTransaction();

                if ($msp->error) {
                    \XLite\Core\TopMessage::addError("Error " . $msp->error_code . ": " . $msp->error);
                } else {
                    header('Location: ' . $url);
                    exit;
                }
            } else {
                \XLite\Core\TopMessage::addError('Unknown payment transaction');
            }
        //}
    }

  

    /**
     * Get array of payment settings
     *
     * @return array
     */
    public function getGiftcardPaymentSettings() {
        $result = array();

        $fields = $this->getAvailableSettings();
        foreach ($fields as $field) {
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
    protected function getSetting($name) {
        $result = parent::getSetting($name);

        if (is_null($result)) {
            $method = \XLite\Core\Database::getRepo('XLite\Model\Payment\Method')->findOneBy(array('service_name' => 'MultiSafepay Webshop Giftcard'));
            $result = $method ? $method->getSetting($name) : null;
        }

        return $result;
    }

    /**
     * Get redirect form URL
     *
     * @return string
     */
    protected function getFormURL() {
        return \XLite\Core\Converter::buildURL('giftcard', 'transaction');
    }

    /**
     * Get redirect form fields list
     *
     * @return array
     */
    protected function getFormFields() {
        $data = \XLite\Core\Request::getInstance()->getData();

        return array(
            'iid' => $data['payment']['iid'],
            'transid' => $this->transaction->getPublicTxnId(),
            'returnURL' => $this->getReturnURL(null, true),
        );
    }


    // {{{ Checkout



    /**
     * Process input errors
     *
     * @param array $data Input data
     *
     * @return array
     */
    public function getInputErrors(array $data) {
        $errors = parent::getInputErrors($data);

        foreach ($this->getInputDataLabels() as $k => $t) {
            if (!isset($data[$k]) || !$data[$k]) {
                $errors[] = \XLite\Core\Translation::lbl('X field is required', array('field' => $t));
            }
        }

        return $errors;
    }


    /**
     * Get input data access levels list
     *
     * @return array
     */
    protected function getInputDataAccessLevels() {
        return array(
            'iid' => \XLite\Model\Payment\TransactionData::ACCESS_CUSTOMER,
        );
    }

    // }}}
}
