<?php
// vim: set ts=4 sw=4 sts=4 et:

namespace XLite\Module\MultiSafepay\Ideal\Controller\Customer;

/**
 * Ideal Professional page controller
 * This page is only used to redirect customer to iDEAL side
 */
class Ideal extends \XLite\Controller\Customer\ACustomer
{
    /**
     * Do redirect customer to iDEAL server for payment
     *
     * @return void
     */
    protected function doActionTransaction()
    {
        try {

            $processor = new \XLite\Module\MultiSafepay\Ideal\Model\Payment\Processor\Ideal();

            $processor->doTransactionRequest(
                \XLite\Core\Request::getInstance()->iid,
                \XLite\Core\Request::getInstance()->transid
            );

        } catch (\Exception $e) {

            \XLite\Core\TopMessage::addError(
                static::t('An error occured processing your transaction request, please try again using another payment method.')
            );

            $this->setReturnURL('checkout');
        }
    }
}
