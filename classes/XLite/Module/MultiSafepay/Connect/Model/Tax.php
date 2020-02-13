<?php


namespace XLite\Module\MultiSafepay\Connect\Model;


use XLite\Core\Database;
use XLite\Model\Order;
use XLite\Model\OrderItem;
use Xlite\Model\Shipping\Rate;
use XLite\Model\TaxClass;

class Tax
{
    public static function getTaxNameForOrderItems(OrderItem $orderItem)
    {
        return self::getTaxName($orderItem->getProduct()->getTaxClass());
    }

    /**
     * @param Rate $shipping
     * @return string
     */
    public static function getTaxNameForShipping(Order $order)
    {
        $taxClass = $order->getModifier(\XLite\Model\Base\Surcharge::TYPE_SHIPPING, 'SHIPPING')
            ->getSelectedRate()
            ->getMethod()
            ->getTaxClass();
        
        if ($taxClass) {
            return $taxClass->getTranslation()->getName();
        }
        return 'sales_tax';
    }

    /**
     * @param TaxClass|null $taxClass
     * @return string
     */
    protected static function getTaxName(TaxClass $taxClass = null)
    {
        $availableTax = self::getActiveTaxClass();

        if ((int)$availableTax->getRates()->count() === 0) {
            return 'no-tax';
        }

        if ($taxClass === null) {
            return 'sales_tax';
        }

        return $taxClass->getTranslation()->getName();
    }

    /**
     * @return mixed
     */
    public static function getActiveTaxClass()
    {
        $availableTax = null;
        $taxRepos = [
            'XLite\Module\CDev\SalesTax\Model\Tax',
            'XLite\Module\CDev\VAT\Model\Tax',
        ];

        foreach ($taxRepos as $repo) {
            $repo = Database::getRepo($repo);
            if ($repo !== null) {
                $availableTax = $repo->getTax();
            }
        }

        return $availableTax;
    }

    /**
     * @return array
     */
    public static function getCheckoutOptions()
    {
        $checkoutOptions = [];
        $availableTax = self::getActiveTaxClass();

        $checkoutOptions['tax_tables']['default'] = [
            'shipping_taxed' => false,
            'rate' => null
        ];

        $checkoutOptions['tax_tables']['alternate'][] = [
            'standalone' => false,
            'name' => 'BTW0',
            'rules' => [[
                'rate' => 0,
                'country' => null
            ]]
        ];

        $checkoutOptions['tax_tables']['alternate'][] = [
            'standalone' => false,
            'name' => 'no-tax',
            'rules' => [[
                'rate' => 0,
                'country' => null
            ]]
        ];

        foreach ($availableTax->getRates() as $tax) {
            $taxTable = [
                'standalone' => false,
                'name' => $tax->getNoTaxClass() ? 'sales_tax' : self::getTaxName($tax->getTaxClass())
            ];

            if (empty($tax->getZone()->getZoneCountries())) {
                $taxTable['rules'][] = [
                    'rate' => $tax->getValue() / 100,
                ];
                $checkoutOptions['tax_tables']['alternate'][] = $taxTable;
                continue;
            }

            foreach ($tax->getZone()->getZoneCountries() as $supportedTaxCountry) {
                $taxTable['rules'][] = [
                    'rate' => $tax->getValue() / 100,
                    'country' => $supportedTaxCountry->getCode()
                ];
            }

            $checkoutOptions['tax_tables']['alternate'][] = $taxTable;
        }

        return $checkoutOptions;
    }
}
