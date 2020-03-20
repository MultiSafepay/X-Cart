<?php

namespace XLite\Module\MultiSafepay\Connect\Model;

use XLite\Core\Config;
use XLite\Model\Base\Surcharge;
use XLite\Model\Order;
use XLite\Model\OrderItem;
use XLite\Module\CDev\Coupons\Model\UsedCoupon;

class Cart
{
    public static function getShoppingCart(Order $order)
    {
        $shoppingCart = [];

        //Add items
        foreach ($order->getItems() as $product) {
            $shoppingCart['items'][] = Cart::addProductToCart($product);
        }

        $shoppingCart['items'][] = Cart::addShippingToCart($order);

        foreach ($order->getUsedCoupons() as $coupon) {
            $shoppingCart['items'][] = Cart::addCouponToCart($coupon);
        }

        return $shoppingCart;
    }

    /**
     * @param OrderItem $orderItem
     * @return array
     */
    public static function addProductToCart(OrderItem $orderItem)
    {
        return [
            'name' => $orderItem->getName(),
            'description' => $orderItem->getDescription(),
            'unit_price' => $orderItem->getNetPrice(),
            'quantity' => $orderItem->getAmount(),
            'merchant_item_id' => $orderItem->getSku(),
            'tax_table_selector' => Tax::getTaxNameForOrderItems($orderItem),
            'weight' => [
                'unit' => strtoupper(Config::getInstance()->Units->weight_symbol),
                'value' => $orderItem->getWeight()
            ]
        ];
    }

    /**
     * @param Order $order
     * @return array
     */
    public static function addShippingToCart(Order $order)
    {
        $taxAmount = 0;
        if ($taxActive = self::isShippingTaxActive($order)) {
            $taxAmount = $order->getModifier(Surcharge::TYPE_SHIPPING, 'SHIPPING')
                ->getSelectedRate()
                ->getIncludedVatRate();
        }
        return [
            'name' => $order->getShippingMethodName(),
            'description' => 'Shipping',
            'unit_price' => $order->getSurchargeSumByType(\XLite\Model\Base\Surcharge::TYPE_SHIPPING) - $taxAmount,
            'quantity' => '1',
            'merchant_item_id' => 'msp-shipping',
            'tax_table_selector' => $taxActive ? Tax::getTaxNameForShipping($order) : 'no-tax',
        ];
    }


    /**
     * @param UsedCoupon $coupon
     * @return array
     */
    public static function addCouponToCart(UsedCoupon $coupon)
    {
        return [
            'name' => 'Discount/Coupon ' . $coupon->getPublicName(),
            'description' => 'DISCOUNT',
            'unit_price' => -$coupon->getValue(),
            'quantity' => 1,
            'merchant_item_id' => 'discount-' . $coupon->getId(),
            'tax_table_selector' => 'no-tax',
        ];
    }

    /**
     * @param Order $order
     * @return bool
     */
    protected static function isShippingTaxActive(Order $order)
    {
        $modifier = $order->getModifier(Surcharge::TYPE_SHIPPING, 'SHIPPING');

        if (!$modifier->getSelectedRate()) {
            return false;
        }
        return $modifier->getSelectedRate()->getIncludedVatRate() > 0;
    }
}
