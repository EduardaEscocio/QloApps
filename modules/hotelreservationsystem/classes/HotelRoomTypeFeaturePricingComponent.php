<?php
/**
* NOTICE OF LICENSE
*
* This source file is subject to the Open Software License version 3.0
* that is bundled with this package in the file LICENSE.md
* It is also available through the world-wide-web at this URL:
* https://opensource.org/license/osl-3-0-php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to support@qloapps.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade this module to a newer
* versions in the future. If you wish to customize this module for your needs
* please refer to https://store.webkul.com/customisation-guidelines for more information.
*
* @author Webkul IN
* @copyright Since 2010 Webkul
* @license https://opensource.org/license/osl-3-0-php Open Software License version 3.0
*/

/**
 * Daily price decorator applying the feature pricing plan of a stay day.
 *
 * When a feature pricing plan matches the stay date, the plan impact
 * (percentage or fixed price, decreasing, increasing or fixing the price)
 * is applied on the raw base price, the result is clamped to zero and the
 * customer group discount is applied unless the plan is cart scoped.
 * Days without a matching plan keep the wrapped component price.
 */
class HotelRoomTypeFeaturePricingComponent extends HotelRoomTypePriceComponent
{
    /**
     * {@inheritdoc}
     *
     * Daily prices are resolved by getDayPrice() during the stay loop,
     * so there is nothing stay level to add.
     *
     * @param HotelRoomTypePriceContext $context Price calculation context
     */
    protected function doApply(HotelRoomTypePriceContext $context)
    {
    }

    /**
     * {@inheritdoc}
     *
     * @param HotelRoomTypePriceContext $context Price calculation context
     * @param string $currentDate Stay date as Y-m-d
     *
     * @return array Keys: total_price_tax_incl, total_price_tax_excl
     */
    public function getDayPrice(HotelRoomTypePriceContext $context, $currentDate)
    {
        if ($context->use_reduc && ($featurePrice = HotelCartBookingData::getProductFeaturePricePlanByDateByPriority(
            $context->id_product,
            $currentDate,
            $context->id_group,
            $context->id_cart,
            $context->id_guest,
            $context->id_room
        ))) {
            $context->initBasePriceData();

            if ($featurePrice['impact_type'] == HotelRoomTypeFeaturePricing::IMPACT_TYPE_PERCENTAGE) {
                //percentage
                $featureImpactPriceTE = $context->productPriceTE * ($featurePrice['impact_value'] / 100);
                $featureImpactPriceTI = $context->productPriceTI * ($featurePrice['impact_value'] / 100);
            } else {
                //Fixed Price
                $taxPrice = ($featurePrice['impact_value'] * $context->taxRate) / 100;
                $featureImpactPriceTE = Tools::convertPrice($featurePrice['impact_value'], $context->id_currency);
                $featureImpactPriceTI = Tools::convertPrice($featurePrice['impact_value'] + $taxPrice, $context->id_currency);
            }
            if ($featurePrice['impact_way'] == HotelRoomTypeFeaturePricing::IMPACT_WAY_DECREASE) {
                // Decrease
                $priceWithFeatureTE = ($context->productPriceTE - $featureImpactPriceTE);
                $priceWithFeatureTI = ($context->productPriceTI - $featureImpactPriceTI);
            } elseif ($featurePrice['impact_way'] == HotelRoomTypeFeaturePricing::IMPACT_WAY_INCREASE) {
                // Increase
                $priceWithFeatureTE = ($context->productPriceTE + $featureImpactPriceTE);
                $priceWithFeatureTI = ($context->productPriceTI + $featureImpactPriceTI);
            } else {
                // Fix
                $priceWithFeatureTE = $featureImpactPriceTE;
                $priceWithFeatureTI = $featureImpactPriceTI;
            }
            if ($priceWithFeatureTI < 0) {
                $priceWithFeatureTI = 0;
                $priceWithFeatureTE = 0;
            }
            if ((int) $featurePrice['id_cart'] == 0) {
                $priceWithFeatureTI = Product::applyGroupDiscount($priceWithFeatureTI, $context->id_product, $context->id_group);
                $priceWithFeatureTE = Product::applyGroupDiscount($priceWithFeatureTE, $context->id_product, $context->id_group);
            }

            return array(
                'total_price_tax_incl' => $priceWithFeatureTI,
                'total_price_tax_excl' => $priceWithFeatureTE,
            );
        }

        return $this->innerComponent !== null
            ? $this->innerComponent->getDayPrice($context, $currentDate)
            : parent::getDayPrice($context, $currentDate);
    }
}
