<?php

// vim: set ts=4 sw=4 sts=4 et:

namespace XLite\Module\MultiSafepay\Payafter\Model\Payment\Processor;

class Payafter extends \XLite\Model\Payment\Base\WebBased {

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
        'EUR'
    );

    /**
     * Get settings widget or template
     *
     * @return string Widget class name or template path
     */
    public function getSettingsWidget() {
        return 'modules/MultiSafepay/Payafter/config.tpl';
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

            $settings = $this->getPayafterPaymentSettings();

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

            // Set transaction status
            $this->transaction->setStatus($order_status);
            $this->transaction->update();

            if (\XLite\Core\Request::getInstance()->redirect != 'true') {
                if (\XLite\Core\Request::getInstance()->type == 'initial') {
                    echo '<a href="' . $this->getReturnURL(null, true) . '&redirect=true&transactionid=' . \XLite\Core\Request::getInstance()->transactionid . '">Terug naar de webwinkel</a>';
                    exit;
                } elseif (\XLite\Core\Request::getInstance()->cancel == '1') {
                    $order_status = $transaction::STATUS_CANCELED;
                    $this->transaction->setStatus($order_status);
                } else {
                    echo 'OK';
                    exit;
                }
            }
        }
        if (\XLite\Core\Request::getInstance()->error) {

            $order_status = $transaction::STATUS_FAILED;
            $this->setDetail('status', 'Order canceled because of transaction request error:' . "Error " . \XLite\Core\Request::getInstance()->error_code . ": " . \XLite\Core\Request::getInstance()->error, 'Status');
            $this->transaction->setNote('Order canceled because of transaction request error:' . "Error " . \XLite\Core\Request::getInstance()->error_code . ": " . \XLite\Core\Request::getInstance()->error);
            $this->transaction->setStatus($order_status);
            //\XLite\Core\TopMessage::addWarning("Error " . \XLite\Core\Request::getInstance()->error_code . ": " . \XLite\Core\Request::getInstance()->error);
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

            $settings = $this->getPayafterPaymentSettings();

            $items = '<ul>';
            foreach ($this->getOrder()->getItems() as $item) {
                $product = $item->getProduct();
                $items .= '<li>' . $item->getAmount() . ' x ' . $product->getName();
            }
            $items.= '</ul>';

            $msp = new \MultiSafepay();
            $msp->test = $settings['environment'] != 'Y' ? false : true;
            $msp->merchant['account_id'] = $settings['accountid'];
            $msp->merchant['site_id'] = $settings['siteid'];
            $msp->merchant['site_code'] = $settings['sitesecurecode'];
            $msp->merchant['notification_url'] = $this->getReturnURL(null, true) . "&type=initial";
            $msp->merchant['cancel_url'] = $this->getReturnURL(null, true, true);
            $msp->merchant['redirect_url'] = $this->getReturnURL(null, true) . "&redirect=true";
            $msp->customer['locale'] = strtolower(\XLite\Core\Session::getInstance()->getLanguage()->getCode()).'-'.strtoupper(\XLite\Core\Session::getInstance()->getLanguage()->getCode());
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
            $msp->customer['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
            $msp->customer['referrer'] = $_SERVER['HTTP_REFERER'];
            $msp->parseCustomerAddress($this->getProfile()->getBillingAddress()->getStreet());

            $msp->transaction['id'] = $orderId;
            $msp->transaction['currency'] = strtoupper($this->getOrder()->getCurrency()->getCode());
            $msp->transaction['amount'] = $this->getOrder()->getCurrency()->roundValue($this->transaction->getValue()) * 100;
             $msp->transaction['description'] = $this->getInvoiceDescription();
            $msp->transaction['items'] = $items;
            $msp->transaction['gateway'] = 'PAYAFTER';
            $msp->transaction['daysactive'] = $settings['daysactive'];
            $msp->plugin_name = 'X-CART ('.\Doctrine\Common\Version::VERSION.')';
            $msp->version = '1.0.0';
            $msp->plugin['shop'] = 'X-Cart';
            $msp->plugin['shop_version'] = \Doctrine\Common\Version::VERSION;
            $msp->plugin['plugin_version'] = '1.0.0';
            $msp->plugin['partner'] = '';
            $msp->plugin['shop_root_url'] = '';

            $taxes_array = array();
            $taxes_array['BTW0'] = '0.00';

            $currency = $this->getOrder()->getCurrency();

            
            //Start adding product/discount/shipping/surcharge lines to the transaction request
            $taxes = \XLite\Core\Database::getRepo('XLite\Module\CDev\SalesTax\Model\Tax')->findActive();
            $result = array();

            foreach ($taxes as $tax) {
                $previousItems = array();
                $previousClasses = array();
                $cost = 0;
                $ratesExists = false;

                $zones = \XLite\Core\Database::getRepo('XLite\Model\Zone')->findAllZones();
                $rates = $tax->getFilteredRates($zones);

                if ($rates) {
                    $ratesExists = true;
                    foreach ($rates as $rate) {
                        if (!in_array($rate->getValue(), $taxes_array)) {
                            $taxes_array[$rate->getId()] = $rate->getValue();
                        }

                        $taxClass = $rate->getTaxClass() ? : null;
                        // Get tax cost for products in the cart with specified product class
                        $list = array();

                        foreach ($this->getOrder()->getItems() as $item) {
                            if ($item->getProduct()->getTaxable() && !in_array($item->getProduct()->getProductId(), $previousItems) && $rate->isAppliedToObject($item->getProduct())) {
                                $product = $item->getProduct();
                                $c_item = new \MspItem($product->getName(), '', $item->getAmount(), $currency->roundValue($item->getItemNetPrice()), 'KG', $item->getWeight());
                                $c_item->SetMerchantItemId($product->getSku());
                                $c_item->SetTaxTableSelector($rate->getId());
                                $msp->cart->AddItem($c_item);
                            }
                        }
                    }
                } else {
                    foreach ($this->getOrder()->getItems() as $item) {
                        $product = $item->getProduct();
                        $c_item = new \MspItem($product->getName(), '', $item->getAmount(), $currency->roundValue($item->getItemNetPrice()), 'KG', $item->getWeight());
                        $c_item->SetMerchantItemId($product->getSku());
                        $c_item->SetTaxTableSelector('BTW0');
                        $msp->cart->AddItem($c_item);
                    }
                }

                $rates = $tax->getFilteredShippingRates($zones, $membership);
                $modifier = $this->getOrder()->getModifier(\XLite\Model\Base\Surcharge::TYPE_SHIPPING, 'SHIPPING');

                if ($rates) {
                    foreach ($rates as $rate) {
                        if ($modifier && $modifier->getSelectedRate() && $modifier->getSelectedRate()->getMethod()) {
                            $shippingRate = $modifier->getSelectedRate();
                            if ($rate->isAppliedToObject($shippingRate->getMethod())) {
                                $taxes_array['msp-shipping'] = $rate->getValue();
                            }
                        }
                    }
                }
            }

            //add shipping cost to transaction request
            $shipping_name = $this->getOrder()->shipping_method_name;
            $shipping_cost = $this->getOrder()->getSurchargeSumByType(\XLite\Model\Base\Surcharge::TYPE_SHIPPING);
            
            if ($shipping_cost > 0) {
                $c_item = new \MspItem($shipping_name, '', '1', $ShipCost, 'KG', '0');
                $c_item->SetMerchantItemId('msp-shipping');
                $c_item->SetTaxTableSelector('msp-shipping');
                $msp->cart->AddItem($c_item);
            }

            //add discount to transaction request
            $discount = $currency->roundValue($this->getOrder()->getSurchargeSumByType(\XLite\Model\Base\Surcharge::TYPE_DISCOUNT));
            if ($discount < 0) {
                $c_item = new \MspItem('Discount', '', '1', $discount, 'KG', '0');
                $c_item->SetMerchantItemId('msp-discount');
                $c_item->SetTaxTableSelector('BTW0');
                $msp->cart->AddItem($c_item);
            }

            //Add payment/handling fee to the transaction request    
            $handling = $currency->roundValue($this->getOrder()->getSurchargeSumByType(\XLite\Model\Base\Surcharge::TYPE_HANDLING));
            if ($handling > 0) {
                $c_item = new \MspItem('Payment Cost', '', '1', $handling, 'KG', '0');
                $c_item->SetMerchantItemId('payment-fee');
                $c_item->SetTaxTableSelector('BTW0');
                $msp->cart->AddItem($c_item);
            }


            foreach ($taxes_array as $key => $value) {
                $table = new \MspAlternateTaxTable();
                $table->name = $key;
                $rule = new \MspAlternateTaxRule($value / 100);
                $table->AddAlternateTaxRules($rule);
                $msp->cart->AddAlternateTaxTables($table);
            }

            $url = $msp->startCheckout();
            
            if ($msp->error) {
                //\XLite\Core\TopMessage::addError("Error " . $msp->error_code . ": " . $msp->error);
                $url = $this->getReturnURL(null, true) . '&error=' . $msp->error . '&error_code=' . $msp->error_code;
                header('Location: ' . $url);
                exit;
            } else {
                header('Location: ' . $url);
                exit;
            }
        } else {
            \XLite\Core\TopMessage::addError('Unknown payment transaction');
        }
    }

    /**
     * Get array of payment settings
     *
     * @return array
     */
    public function getPayafterPaymentSettings() {
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
            $method = \XLite\Core\Database::getRepo('XLite\Model\Payment\Method')->findOneBy(array('service_name' => 'MultiSafepay Pay After Delivery'));
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
        return \XLite\Core\Converter::buildURL('payafter', 'transaction');
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

    public function getIconPath(\XLite\Model\Order $order, \XLite\Model\Payment\Method $method) {
        $processor = new \XLite\Module\MultiSafepay\Ideal\Model\Payment\Processor\Ideal();
        $processor->gateway = 'Payafter';
        $processor->icon = 'msp_payafter.png';
        return $processor->getIconPath($order, $method);
    }

    public function getCheckoutTemplate(\XLite\Model\Payment\Method $method) {
        return 'modules/MultiSafepay/Ideal/checkout/gateway.tpl';
    }

    // }}}
}
