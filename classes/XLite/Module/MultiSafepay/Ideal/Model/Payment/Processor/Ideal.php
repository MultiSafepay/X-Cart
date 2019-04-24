<?php

/**
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade the MultiSafepay plugin
 * to newer versions in the future. If you wish to customize the plugin for your
 * needs please document your changes and make backups before you update.
 *
 * @category    MultiSafepay
 * @package     Connect
 * @author      TechSupport <techsupport@multisafepay.com>
 * @copyright   Copyright (c) 2017 MultiSafepay, Inc. (http://www.multisafepay.com)
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED,
 * INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR
 * PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN
 * ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
 * WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */

namespace XLite\Module\MultiSafepay\Ideal\Model\Payment\Processor;

class Ideal extends \XLite\Model\Payment\Base\WebBased
{

    public $settings = 'MultiSafepay Connect';
    public $icon = 'msp_ideal.png';
    public $gateway = 'Ideal';
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
     * @return string Status code
     */
    public function getAllowedTransactions()
    {
        return array(
            \XLite\Model\Payment\BackendTransaction::TRAN_TYPE_REFUND
        );
    }

    /**
     * Get settings widget or template
     *
     * @return string Widget class name or template path
     */
    public function getSettingsWidget()
    {
        return 'modules/MultiSafepay/Ideal/config.twig';
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
        parent::processReturn($transaction);
        $processor = new \XLite\Module\MultiSafepay\Connect\Model\Payment\Processor\Connect();
        $processor->processReturn($transaction);
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
        return $method->getSetting('prefix');
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
     * @param string  $issuerId Issuer ID selected by customer
     * @param integer $transid  Current transaction ID
     *
     * @return void
     */
    public function doTransactionRequest($issuerId, $transid)
    {
        $processor = new \XLite\Module\MultiSafepay\Connect\Model\Payment\Processor\Connect();
        $processor->startTransaction($issuerId, $transid, 'MultiSafepay Connect', 'IDEAL');
    }

    /**
     * Get list of issuers from iDEAL
     *
     * @return array
     */
    public function getIdealIssuers()
    {
        require_once LC_DIR_MODULES . 'MultiSafepay' . LC_DS . 'API' . LC_DS . 'Autoloader.php';

        $msp = new \MultiSafepayAPI\Client();
        $msp->setApiKey($this->getSetting('api_key'));
        $msp->setApiUrl($this->getEnvironment());

        $issuers = $msp->issuers->get();

        return $issuers;
    }

    /**
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
     * Get array of payment settings
     *
     * @return array
     */
    public function getPaymentSettings($settings)
    {
        $result = array();
        $this->settings = $settings;

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
    protected function getSetting($name)
    {
        $settings = $this->settings;
        $result = parent::getSetting($name);

        if (is_null($result)) {
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
        return \XLite\Core\Converter::buildURL('ideal', 'transaction');
    }

    /**
     * Get redirect form fields list
     *
     * @return array
     */
    protected function getFormFields()
    {
        $data = \XLite\Core\Request::getInstance()->getData();

        return array(
            'iid' => $data['payment']['iid'],
            'transid' => $this->transaction->getPublicTxnId(),
            'returnURL' => $this->getReturnURL(null, true),
        );
    }

    /**
     * 
     * @return string
     */
    public function getInputTemplate()
    {
        if ($this->getSetting('transaction_type') == '1') {
            return 'modules/MultiSafepay/Ideal/checkout/ideal.twig';
        }
    }

    /**
     * 
     * @param \XLite\Model\Payment\Method $method
     * @return string
     */
    public function getCheckoutTemplate(\XLite\Model\Payment\Method $method)
    {
        return 'modules/MultiSafepay/Connect/checkout/gateway.twig';
    }

    /**
     * Process input errors
     *
     * @param array $data Input data
     *
     * @return array
     */
    public function getInputErrors(array $data)
    {
        if ($this->getSetting('transaction_type') == '1') {
            $errors = parent::getInputErrors($data);

            foreach ($this->getInputDataLabels() as $k => $t) {
                if (!isset($data[$k]) || !$data[$k]) {
                    $errors[] = \XLite\Core\Translation::lbl('X field is required', array('field' => $t));
                }
            }

            return $errors;
        }
    }

    /**
     * Get input data labels list
     *
     * @return array
     */
    protected function getInputDataLabels()
    {
        return array(
            'iid' => 'Select an bank',
        );
    }

    /**
     * Get input data access levels list
     *
     * @return array
     */
    protected function getInputDataAccessLevels()
    {
        return array(
            'iid' => \XLite\Model\Payment\TransactionData::ACCESS_CUSTOMER,
        );
    }

    /**
     * 
     * @param type $order
     * @param type $method
     * @return type
     */
    public function getIconPath(\XLite\Model\Order $order = null, \XLite\Model\Payment\Method $method = null)
    {
        $processor = new \XLite\Module\MultiSafepay\Connect\Model\Payment\Processor\Connect();
        $processor->gateway = 'Ideal';
        $processor->icon = 'msp_ideal.png';
        return $processor->getIconPath($order, $method);
    }

}
