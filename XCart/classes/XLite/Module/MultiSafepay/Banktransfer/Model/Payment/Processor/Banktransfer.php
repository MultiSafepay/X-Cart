<?php

// vim: set ts=4 sw=4 sts=4 et:

namespace XLite\Module\MultiSafepay\Banktransfer\Model\Payment\Processor;

class Banktransfer extends \XLite\Model\Payment\Base\WebBased {

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
        return 'modules/MultiSafepay/Banktransfer/config.tpl';
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
        $processor = new \XLite\Module\MultiSafepay\Ideal\Model\Payment\Processor\Ideal();
        $processor->processReturn($transaction);
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
        $processor = new \XLite\Module\MultiSafepay\Ideal\Model\Payment\Processor\Ideal();
        $processor->doTransactionRequest('', $transid, 'MultiSafepay Banktransfer', 'BANKTRANS');
    }


    /**
     * Get redirect form URL
     *
     * @return string
     */
    protected function getFormURL() {
        return \XLite\Core\Converter::buildURL('banktransfer', 'transaction');
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
        $processor->gateway = 'Banktransfer';
        $processor->icon = 'msp_banktransfer.png';
        return $processor->getIconPath($order, $method);
    }

    public function getCheckoutTemplate(\XLite\Model\Payment\Method $method) {
        return 'modules/MultiSafepay/Ideal/checkout/gateway.tpl';
    }

    // }}}
}
