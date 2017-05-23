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

namespace XLite\Module\MultiSafepay\Dgsw;

abstract class Main extends \XLite\Module\AModule
{

    /**
     * Author name
     *
     * @return string
     */
    public static function getAuthorName()
    {
        return 'MultiSafepay';
    }

    /**
     * Module name
     *
     * @return string
     */
    public static function getModuleName()
    {
        return 'MultiSafepay De Grote Speelgoedwinkel';
    }

    /**
     * Get module major version
     *
     * @return string
     */
    public static function getMajorVersion()
    {
        return '5.3';
    }

    /**
     * Module version
     *
     * @return string
     */
    public static function getMinorVersion()
    {
        return '1';
    }

    /**
     * Module description
     *
     * @return string
     */
    public static function getDescription()
    {
        return 'Enables MultiSafepay De Grote Speelgoedwinkel transactions';
    }

    /**
     * Add record to the module log file
     *
     * @param string $message Text message OPTIONAL
     * @param mixed  $data    Data (can be any type) OPTIONAL
     *
     * @return void
     */
    public static function addLog($message = null, $data = null)
    {
        if ($message && $data) {
            $msg = array(
                'message' => $message,
                'data' => $data,
            );
        } else {
            $msg = ($message ? : ($data ? : null));
        }

        if (!is_string($msg)) {
            $msg = var_export($msg, true);
        }

        \XLite\Logger::logCustom(
                self::getModuleName(), $msg
        );
    }

    /**
     * Get path of SDK classes file
     *
     * @return string
     */
    public static function getLibClassesFile()
    {
        require_once LC_DIR_MODULES . 'MultiSafepay' . LC_DS . 'API' . LC_DS . 'Autoloader.php';
    }

    /**
     * The module is defined as the payment module
     *
     * @return integer|null
     */
    public static function getModuleType()
    {
        return static::MODULE_TYPE_PAYMENT;
    }

}
