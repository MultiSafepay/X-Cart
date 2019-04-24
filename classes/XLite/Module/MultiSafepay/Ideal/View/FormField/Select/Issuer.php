<?php

namespace XLite\Module\MultiSafepay\Ideal\View\FormField\Select;

class Issuer extends \XLite\View\FormField\Select\Regular
{

    protected function getDefaultOptions()
    {
        $list = array();

        $processor = new \XLite\Module\MultiSafepay\Ideal\Model\Payment\Processor\Ideal();
        $issuers = $processor->getIdealIssuers();

        $list[null] = "Select a bank";
        foreach ($issuers as $issuer) {
            $list[$issuer->code] = $issuer->description;
        }

        return $list;
    }

}
