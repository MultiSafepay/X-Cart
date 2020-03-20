<?php

namespace XLite\Module\MultiSafepay\Connect\Model\Payment;


class Refund
{
    /**
     * @param \XLite\Model\Payment\BackendTransaction $transaction
     * @return bool
     */
    public static function simpleRefund(\XLite\Model\Payment\BackendTransaction $transaction)
    {
        require_once LC_DIR_MODULES . 'MultiSafepay' . LC_DS . 'API' . LC_DS . 'Autoloader.php';
        $orderId = $transaction->getPaymentTransaction()->getPublicTxnId();
        $msp = new \MultiSafepayAPI\Client();
        $msp->setApiKey(self::getSetting('api_key'));
        $msp->setApiUrl(self::getEnvironment());

        $refundData = [
            'currency' => $transaction->getPaymentTransaction()->getCurrency()->getCode(),
            'amount' => (float)$transaction->getValue() * 100
        ];

        try {
            $msp->orders->post($refundData, 'orders/' . $orderId . '/refunds');
        } catch (\Exception $exception) {
            $transaction->setStatus(\XLite\Model\Payment\BackendTransaction::STATUS_FAILED);
            \XLite\Core\TopMessage::getInstance()->addError('MultiSafepay error: ' . $exception->getMessage());
            return false;
        }

        $transaction->setStatus(\XLite\Model\Payment\BackendTransaction::STATUS_SUCCESS);
        \XLite\Core\TopMessage::getInstance()->addInfo('Payment successfully refunded at MultiSafepay');
        return true;
    }

    /**
     * @param $name
     * @return mixed|null
     */
    protected static function getSetting($name)
    {
        $serviceName = 'MultiSafepay Connect';
        $method = \XLite\Core\Database::getRepo('XLite\Model\Payment\Method')->findOneBy(array('service_name' => $serviceName));
        return $method ? $method->getSetting($name) : null;
    }

    /**
     * @return string
     */
    protected static function getEnvironment()
    {
        if (self::getSetting('account_type') == '1') {
            return "https://api.multisafepay.com/v1/json/";
        }
        return "https://testapi.multisafepay.com/v1/json/";
    }

    /**
     * @param \XLite\Model\Payment\BackendTransaction $transaction
     * @return bool
     */
    public static function complexRefund(\XLite\Model\Payment\BackendTransaction $transaction)
    {
        require_once LC_DIR_MODULES . 'MultiSafepay' . LC_DS . 'API' . LC_DS . 'Autoloader.php';
        $orderId = $transaction->getPaymentTransaction()->getPublicTxnId();
        $msp = new \MultiSafepayAPI\Client();
        $msp->setApiKey(self::getSetting('api_key'));
        $msp->setApiUrl(self::getEnvironment());
        $orderData = $msp->orders->get('orders', $orderId);
        $originalCart = $orderData->shopping_cart;

        foreach ($originalCart->items as $item) {
            // Go to next item if previous is empty
            $refundData['checkout_data']['items'][] = $item;

            if ($item->unit_price < 1) {
                continue;
            }


            $refundData['checkout_data']['items'][] = [
                'name' => $item->name,
                'description' => $item->description,
                'unit_price' => (float)-$item->unit_price,
                'quantity' => $item->quantity,
                'merchant_item_id' => $item->merchant_item_id,
                'tax_table_selector' => $item->tax_table_selector
            ];
        }

        try {
            $msp->orders->post($refundData, 'orders/' . $orderId . '/refunds');
        } catch (\Exception $exception) {
            $transaction->setStatus(\XLite\Model\Payment\BackendTransaction::STATUS_FAILED);
            \XLite\Core\TopMessage::getInstance()->addError('MultiSafepay error: ' . $exception->getMessage());
            return false;
        }

        $transaction->setStatus(\XLite\Model\Payment\BackendTransaction::STATUS_SUCCESS);
        \XLite\Core\TopMessage::getInstance()->addInfo('Payment successfully refunded at MultiSafepay');
        return true;
    }
}
