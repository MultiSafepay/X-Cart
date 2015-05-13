<?php
// vim: set ts=4 sw=4 sts=4 et:

namespace XLite\Module\MultiSafepay\Banktransfer;

/**
 * Banktransfer module
 */
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
        return 'Banktransfer';
    }

    /**
     * Get module major version
     *
     * @return string
     */
    public static function getMajorVersion()
    {
        return '5.2';
    }

    /**
     * Module version
     *
     * @return string
     */
    public static function getMinorVersion()
    {
        return '5';
    }

    /**
     * Module description
     *
     * @return string
     */
    public static function getDescription()
    {
        return 'Enables MultiSafepay Banktransfer transactions';
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
                'data'    => $data,
            );

        } else {
            $msg = ($message ?: ($data ?: null));
        }

        if (!is_string($msg)) {
            $msg = var_export($msg, true);
        }

        \XLite\Logger::logCustom(
            self::getModuleName(),
            $msg
        );
    }

    /**
     * Get path of SDK classes file
     *
     * @return string
     */
    public static function getLibClassesFile()
    {
        return LC_DIR_MODULES . 'MultiSafepay' . LC_DS . 'Ideal' . LC_DS . 'lib' . LC_DS . 'MultiSafepay.combined.php';
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
