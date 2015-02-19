<?php
// vim: set ts=4 sw=4 sts=4 et:

namespace XLite\Module\MultiSafepay\Ideal\View\FormField\Select;

/**
 * Issuer selector widget
 */
class Issuer extends \XLite\View\FormField\Select\Regular
{
    /**
     * getDefaultOptions
     *
     * @return array
     */
    protected function getDefaultOptions()
    {
        $list = array();

        $processor = new \XLite\Module\MultiSafepay\Ideal\Model\Payment\Processor\Ideal();

        $issuers  = $processor->getIdealIssuers();
        $settings = $processor->getPaymentSettings('MultiSafepay iDEAL');
        
        $sandbox = $settings['environment'] != 'Y' ? false : true;
        
        
        if ($sandbox) {
            foreach ($issuers['issuers'] as $key => $relatedBank) {
                $list[$relatedBank['code']['VALUE']] = $relatedBank['description']['VALUE'];
            }
        } else {
            foreach ($issuers['issuers']['issuer'] as $key => $relatedBank) {
                $list[$relatedBank['code']['VALUE']] = $relatedBank['description']['VALUE'];
            }
        }

        return $list;
    }
}
