<?php
// vim: set ts=4 sw=4 sts=4 et:

namespace XLite\Module\Multisafepay\Multisafepay\Model\Payment\Processor;

/**
 * MultiSafepay processor
 *
 */
class MultisafepayForm extends \XLite\Model\Payment\Base\WebBased
{
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
     * Get settings widget or template
     *
     * @return string Widget class name or template path
     */
    public function getSettingsWidget()
    {
        return 'modules/Multisafepay/Multisafepay/config.tpl';
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

        $request = \XLite\Core\Request::getInstance();
        $requestBody = $this->decode($request->crypt);

        $status = $transaction::STATUS_FAILED;

        \XLite\Module\XC\SagePay\Main::addLog(
            'processReturnRawResult',
            $request->getData()
        );

        \XLite\Module\Multisafepay\Multisafepay\Main::addLog(
            'processReturn',
            $requestBody
        );

        if (isset($requestBody['Status'])) {

            if ('OK' === $requestBody['Status']) {
                // Success status
                $this->setDetail('TxAuthNo', $requestBody['TxAuthNo'], 'Authorisation code of the transaction');
                $status = $transaction::STATUS_SUCCESS;
            } else {
                // Some error occuried
                $status = $transaction::STATUS_FAILED;
            }

            $this->setDetail('StatusDetail', $requestBody['StatusDetail'], 'Status details');
        } else {
            // Invalid response
            $this->setDetail('StatusDetail', 'Invalid response was received', 'Status details');
        }

        if (isset($requestBody['VPSTxId'])) {
            $this->setDetail('VPSTxId', $requestBody['VPSTxId'], 'The unique Sage Pay ID of the transaction');
        }

        if (isset($requestBody['AVSCV2'])) {
            $this->setDetail('AVSCV2', $requestBody['AVSCV2'], 'AVSCV2 Status');
        }

        if (isset($requestBody['AddressResult'])) {
            $this->setDetail('AddressResult', $requestBody['AddressResult'], 'Cardholder address checking status');
        }

        if (isset($requestBody['PostCodeResult'])) {
            $this->setDetail('PostCodeResult', $requestBody['PostCodeResult'], 'Cardholder postcode checking status');
        }

        if (isset($requestBody['CV2Result'])) {
            $this->setDetail('CV2Result', $requestBody['CV2Result'], 'CV2 code checking result');
        }

        if (isset($requestBody['3DSecureStatus'])) {
            $this->setDetail('3DSecureStatus', $requestBody['3DSecureStatus'], '3DSecure checking status');
        }

        if (!$this->checkTotal($requestBody['Amount'])) {
            $this->setDetail('StatusDetail', 'Invalid amount value was received', 'Status details');
            $status = $transaction::STATUS_FAILED;
        }

        $this->transaction->setStatus($status);
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
        return parent::isConfigured($method)
            && $method->getSetting('vendorName')
            && $method->getSetting('password')
            && $method->getSetting('currency')
            && function_exists('mcrypt_decrypt');
    }

    /**
     * Get return type
     *
     * @return string
     */
    public function getReturnType()
    {
        return self::RETURN_TYPE_HTML_REDIRECT;
    }

    /**
     * Returns the list of settings available for this payment processor
     *
     * @return array
     */
    public function getAvailableSettings()
    {
        return array(
            'vendorName',
            'password',
            'test',
            'currency',
            'prefix',
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
        return (bool)$method->getSetting('test');
    }

    /**
     * Get redirect form URL
     *
     * @return string
     */
    protected function getFormURL()
    {
        return $this->getSetting('test')
            ? 'https://test.sagepay.com/gateway/service/vspform-register.vsp'
            : 'https://live.sagepay.com/gateway/service/vspform-register.vsp';
    }

    /**
     * Get redirect form fields list
     *
     * @return array
     */
    protected function getFormFields()
    {
        return array(
            'VPSProtocol'   => '3.00',
            'TxType'        => 'PAYMENT',
            'Vendor'        => $this->getSetting('vendorName'),
            'Crypt'         => $this->getCrypt(),
        );
    }

    /**
     * Returns the crypted ordering information
     *
     * @return string
     */
    protected function getCrypt()
    {
        $fields = array(
            'VendorTxCode'      => $this->getSetting('prefix') . $this->transaction->getTransactionId(),
            'ReferrerID'      => '653E8C42-AD93-4654-BB91-C645678FA97B',
            'Amount'            => round($this->transaction->getValue(), 2),
            'Currency'          => strtoupper($this->getSetting('currency')),
            'Description'       => 'Your Cart',

            'SuccessURL'        => $this->getReturnURL(null, true),
            'FailureURL'        => $this->getReturnURL(null, true, true),

            'CustomerName'      => $this->getProfile()->getBillingAddress()->getFirstname()
                . ' '
                . $this->getProfile()->getBillingAddress()->getLastname(),
            'CustomerEMail'     => $this->getProfile()->getLogin(),
            'VendorEMail'       => \XLite\Core\Config::getInstance()->Company->orders_department,
            'SendEMail'         => 1,

            'BillingSurname'    => $this->getProfile()->getBillingAddress()->getLastname(),
            'BillingFirstnames' => $this->getProfile()->getBillingAddress()->getFirstname(),
            'BillingAddress1'   => $this->getProfile()->getBillingAddress()->getStreet(),
            'BillingCity'       => $this->getProfile()->getBillingAddress()->getCity(),
            'BillingPostCode'   => $this->getProfile()->getBillingAddress()->getZipcode(),
            'BillingCountry'    => strtoupper($this->getProfile()->getBillingAddress()->getCountry()->getCode()),

            'DeliverySurname'    => $this->getProfile()->getShippingAddress()->getLastname(),
            'DeliveryFirstnames' => $this->getProfile()->getShippingAddress()->getFirstname(),
            'DeliveryAddress1'   => $this->getProfile()->getShippingAddress()->getStreet(),
            'DeliveryCity'       => $this->getProfile()->getShippingAddress()->getCity(),
            'DeliveryPostCode'   => $this->getProfile()->getShippingAddress()->getZipcode(),
            'DeliveryCountry'    => strtoupper($this->getProfile()->getShippingAddress()->getCountry()->getCode()),

            'Basket'             => $this->getBasket(),
            'AllowGiftAid'       => 0,
            'ApplyAVSCV2'        => 0,
            'Apply3DSecure'      => 0,
        );

        if  ('US' === $fields['BillingCountry']) {
            $fields['BillingState'] = $this->getProfile()->getBillingAddress()->getState()->getCode();
        }

        if ('US' === $fields['DeliveryCountry']) {
            $fields['DeliveryState'] = $this->getProfile()->getShippingAddress()->getState()->getCode();
        }

        $cryptedFields = array();
        foreach ($fields as $key => $value) {
            $cryptedFields[] = $key . '=' . $value;
        }

        return $this->encryptAndEncode(implode('&', $cryptedFields));
    }

    /**
     * Returns the basket information
     *
     * @return string
     */
    protected function getBasket()
    {
        return '';
    }

    /**
     * Decode the crypted response text
     *
     * @param string $strIn Crypted response text
     *
     * @return array
     */
	protected function decode($strIn)
    {
        $sagePayResponse = array();
		$decodedString =  $this->decodeAndDecrypt($strIn);
		parse_str($decodedString, $sagePayResponse);

        return $sagePayResponse;
	}

    /**
     * Encryption of the text
     *
     * @param string $strIn Text for encryption
     *
     * @return string
     */
	protected function encryptAndEncode($strIn)
    {
        return '@' . bin2hex(
            mcrypt_encrypt(
                MCRYPT_RIJNDAEL_128,
                $this->getSetting('password'),
                $this->pkcs5Pad($strIn, 16),
                MCRYPT_MODE_CBC,
                $this->getSetting('password')
            )
        );
	}

    /**
     * Decode the text
     *
     * @param string $strIn Text to decode
     *
     * @return string
     */
	protected function decodeAndDecrypt($strIn)
    {
		return mcrypt_decrypt(
            MCRYPT_RIJNDAEL_128,
            $this->getSetting('password'),
            pack('H*', substr($strIn, 1)),
            MCRYPT_MODE_CBC,
            $this->getSetting('password')
        );
	}

    /**
     * Padding of the text with the provided block sizing
     *
     * @param string  $text
     * @param integer $blocksize
     *
     * @return string
     */
	protected function pkcs5Pad($text, $blocksize)
    {
		$pad = $blocksize - (strlen($text) % $blocksize);

        return $text . str_repeat(chr($pad), $pad);
	}
}
