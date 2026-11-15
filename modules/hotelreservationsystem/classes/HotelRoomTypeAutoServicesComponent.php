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
 * Stay price decorator adding the room services priced with the room to the
 * totals: the services stored in the cart for the booked room when there is
 * a cart context, or the auto added services of the room type otherwise.
 */
class HotelRoomTypeAutoServicesComponent extends HotelRoomTypePriceComponent
{
    /**
     * {@inheritdoc}
     *
     * @param HotelRoomTypePriceContext $context Price calculation context
     */
    protected function doApply(HotelRoomTypePriceContext $context)
    {
        if (!$context->with_auto_room_services) {
            return;
        }
        if ($context->id_cart && $context->id_room) {
            $objHotelCartBookingData = new HotelCartBookingData();
            if ($roomHtlCartInfo = $objHotelCartBookingData->getRoomRowByIdProductIdRoomInDateRange(
                $context->id_cart,
                $context->id_product,
                $context->date_from,
                $context->date_to,
                $context->id_room
            )) {
                $objServiceProductCartDetail = new ServiceProductCartDetail();
                if ($roomServicesServices = $objServiceProductCartDetail->getServiceProductsInCart(
                    $context->id_cart,
                    [],
                    null,
                    $roomHtlCartInfo['id'],
                    null,
                    null,
                    null,
                    null,
                    0,
                    1,
                    Product::PRICE_ADDITION_TYPE_WITH_ROOM
                )) {
                    foreach ($roomServicesServices as $selectedService) {
                        $context->addTaxIncl($selectedService['total_price_tax_incl']);
                        $context->addTaxExcl($selectedService['total_price_tax_excl']);
                        if ($context->includeTourismTax && isset($selectedService['tourism_tax'])) {
                            $context->addTaxIncl($selectedService['tourism_tax']);
                        }
                    }
                }
            }
        } else {
            if ($servicesWithTax = RoomTypeServiceProduct::getAutoAddServices(
                $context->id_product,
                $context->date_from,
                $context->date_to,
                Product::PRICE_ADDITION_TYPE_WITH_ROOM,
                true,
                $context->use_reduc,
                $context->includeTourismTax
            )) {
                foreach ($servicesWithTax as $service) {
                    $context->addTaxIncl($service['price']);
                }
            }
            if ($servicesWithoutTax = RoomTypeServiceProduct::getAutoAddServices(
                $context->id_product,
                $context->date_from,
                $context->date_to,
                Product::PRICE_ADDITION_TYPE_WITH_ROOM,
                false,
                $context->use_reduc
            )) {
                foreach ($servicesWithoutTax as $service) {
                    $context->addTaxExcl($service['price']);
                }
            }
        }
    }
}
