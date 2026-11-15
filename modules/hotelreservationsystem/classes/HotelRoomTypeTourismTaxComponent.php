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
 * Stay price decorator adding the tourism tax to the tax included total.
 *
 * The tax is computed per occupied room (adults and child ages) using the
 * tourism tax rules of the room type and the collection type of the hotel.
 * It is divided by the number of rooms because the occupancy multiplier
 * applied afterwards scales the totals back up.
 */
class HotelRoomTypeTourismTaxComponent extends HotelRoomTypePriceComponent
{
    /**
     * {@inheritdoc}
     *
     * @param HotelRoomTypePriceContext $context Price calculation context
     */
    protected function doApply(HotelRoomTypePriceContext $context)
    {
        if (!$context->includeTourismTax
            || !Configuration::get('QLO_USE_TOURISM_TAX')
            || !is_array($context->occupancy)
            || empty($context->occupancy)
            || !isset($context->occupancy[0]['adults'])
        ) {
            return;
        }
        if (!$idTourismTaxRulesGroup = Product::getIdTourismTaxRulesGroupByIdProduct((int) $context->id_product)) {
            return;
        }
        $context->initBasePriceData();

        $hotelBranch = new HotelBranchInformation((int) $context->objAddress->id_hotel);
        $collectionType = (int) $hotelBranch->tourism_tax_collection_type;
        $numNights = max(1, (int) HotelHelper::getNumberOfDays($context->date_from, $context->date_to));
        $unitPriceTe = (float) $context->totalPrice['total_price_tax_excl'] / $numNights;
        $taxCalculator = TaxManagerFactory::getManager($context->objAddress, $idTourismTaxRulesGroup)->getTaxCalculator();
        $roomsTourismTax = 0;
        foreach ($context->occupancy as $roomOccupancy) {
            $roomsTourismTax += $taxCalculator->getTaxesTotalAmount(
                $unitPriceTe,
                $context->date_from,
                $numNights,
                isset($roomOccupancy['adults']) ? (int) $roomOccupancy['adults'] : 0,
                !empty($roomOccupancy['child_ages']) ? (array) $roomOccupancy['child_ages'] : array(),
                $collectionType,
                1,
                $context->id_currency
            );
        }
        $context->addTaxIncl($roomsTourismTax / count($context->occupancy));
    }
}
