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
 * Concrete component of the room type price chain: the base price of one
 * stay day, i.e. the room type per night price with the customer group
 * discount applied. It is the chain root other components decorate upon.
 */
class HotelRoomTypeBasePriceComponent extends HotelRoomTypePriceComponent
{
    /**
     * {@inheritdoc}
     *
     * The base price contributes to the stay total only through the per-day
     * prices resolved by getDayPrice(), so there is nothing stay level to add.
     *
     * @param HotelRoomTypePriceContext $context Price calculation context
     */
    protected function doApply(HotelRoomTypePriceContext $context)
    {
    }

    /**
     * {@inheritdoc}
     *
     * Returns the base per night price with the customer group discount
     * applied, loading the shared base price data on first use.
     *
     * @param HotelRoomTypePriceContext $context Price calculation context
     * @param string $currentDate Stay date as Y-m-d
     *
     * @return array Keys: total_price_tax_incl, total_price_tax_excl
     */
    public function getDayPrice(HotelRoomTypePriceContext $context, $currentDate)
    {
        $context->initBasePriceData();

        return array(
            'total_price_tax_incl' => Product::applyGroupDiscount($context->productPriceTI, $context->id_product, $context->id_group),
            'total_price_tax_excl' => Product::applyGroupDiscount($context->productPriceTE, $context->id_product, $context->id_group),
        );
    }
}
