<?php
/**
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade the MultiSafepay plugin
 * to newer versions in the future. If you wish to customize the plugin for your
 * needs please document your changes and make backups before you update.
 *
 * @category    MultiSafepay
 * @package     Connect
 * @author      TechSupport <integration@multisafepay.com>
 * @copyright   Copyright (c) 2020 MultiSafepay, Inc. (https://www.multisafepay.com)
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED,
 * INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR
 * PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN
 * ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
 * WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */
namespace XLite\Module\MultiSafepay\Connect\Listeners;

class OrderListener extends \XLite\Model\Order implements \XLite\Base\IDecorator
{
    /**
     * Set the transaction on MultiSafepay dashboard on shipped
     * when order has been set on shipped in X-Cart backend
     */
    protected function processShip()
    {
        parent::processShip();

        $transaction = $this->getPaymentTransactions()->first();
        if (!$this->isValidPaymentMethod($transaction->getMethodName())) {
            return;
        }

        $trackTraceCode = null;
        if ($this->getTrackingNumbers()->count() > 0) {
            $trackTraceCode = $this->getTrackingNumbers()->first()->getValue();
        }

        require_once LC_DIR_MODULES . 'MultiSafepay' . LC_DS . 'API' . LC_DS . 'Autoloader.php';
        $msp = new \MultiSafepayAPI\Client();
        $msp->setApiKey($this->getSetting('api_key'));
        $msp->setApiUrl($this->getEnvironment());

        $msp->orders->patch(
            [
                'tracktrace_code' => $trackTraceCode,
                'carrier' => '',
                'ship_date' => date('Y-m-d H:i:s'),
                'reason' => 'Shipped'
            ],
            'orders/' . $transaction->getPublicTxnId()
        );
    }

    /**
     * @param $name
     * @return mixed|null
     */
    protected function getSetting($name)
    {
        $settings = 'MultiSafepay Connect';
        $method = \XLite\Core\Database::getRepo('XLite\Model\Payment\Method')->findOneBy(array('service_name' => $settings));
        $result = $method ? $method->getSetting($name) : null;

        return $result;
    }

    /**
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
     * @param $paymentMethod
     * @return bool
     */
    public function isValidPaymentMethod($paymentMethod)
    {
        //String contain MultiSafepay
        if (strpos($paymentMethod, 'MultiSafepay') !== false) {
            return true;
        }
        return false;
    }
}
