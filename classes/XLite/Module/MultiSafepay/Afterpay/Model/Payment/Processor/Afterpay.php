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

namespace XLite\Module\MultiSafepay\Afterpay\Model\Payment\Processor;

use XLite\Core\Converter;
use XLite\Model\Order;
use XLite\Model\Payment\Method;
use XLite\Module\MultiSafepay\Connect\Model\Payment\Processor\Connect;
use XLite\Module\MultiSafepay\Connect\Model\Payment\Refund;

class Afterpay extends Connect
{
    /**
     * {@inheritDoc}
     */
    public function getAllowedTransactions()
    {
        return [];
    }

    /**
     * {@inheritDoc}
     */
    public function getSettingsWidget()
    {
        return 'modules/MultiSafepay/Afterpay/config.twig';
    }

    /**
     * {@inheritDoc}
     */
    public function isConfigured(Method $method)
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    protected function getFormURL()
    {
        return Converter::buildURL('afterpay', 'transaction');
    }

    /**
     * {@inheritDoc}
     */
    public function getIconPath(Order $order = null, Method $method = null)
    {
        $processor = new Connect();
        $processor->gateway = 'Afterpay';
        $processor->icon = 'msp_afterpay.png';
        return $processor->getIconPath($order, $method);
    }

    /**
     * @param \XLite\Model\Payment\BackendTransaction $transaction
     * @return bool
     */
    protected function doRefund(\XLite\Model\Payment\BackendTransaction $transaction)
    {
        return Refund::complexRefund($transaction);
    }
}
