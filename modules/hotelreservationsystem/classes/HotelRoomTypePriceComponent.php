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
 * Abstract component of the room type price calculation chain.
 *
 * Implements the Decorator pattern: every component can wrap another one
 * (its "inner" component). Applying the chain applies the inner component
 * first, and this component then decorates its effect on the shared
 * HotelRoomTypePriceContext accumulator. New price behaviour can be added
 * by wrapping the chain in a new component instead of editing existing ones.
 */
abstract class HotelRoomTypePriceComponent
{
    /** @var HotelRoomTypePriceComponent|null Wrapped component, null for the chain root */
    protected $innerComponent;

    /**
     * @param HotelRoomTypePriceComponent|null $innerComponent Component decorated by this one
     */
    public function __construct($innerComponent = null)
    {
        $this->innerComponent = $innerComponent;
    }

    /**
     * Applies the wrapped component first, when there is one, and then this
     * component's own effect on the price context accumulator.
     *
     * @param HotelRoomTypePriceContext $context Price calculation context
     */
    public function apply(HotelRoomTypePriceContext $context)
    {
        if ($this->innerComponent !== null) {
            $this->innerComponent->apply($context);
        }
        $this->doApply($context);
    }

    /**
     * This component's own effect on the price context accumulator.
     *
     * @param HotelRoomTypePriceContext $context Price calculation context
     */
    abstract protected function doApply(HotelRoomTypePriceContext $context);

    /**
     * Resolves the price of a single stay day. Decorators of the daily price
     * override this method and delegate to the inner component for the days
     * they do not affect.
     *
     * @param HotelRoomTypePriceContext $context Price calculation context
     * @param string $currentDate Stay date as Y-m-d
     *
     * @return array Keys: total_price_tax_incl, total_price_tax_excl
     */
    public function getDayPrice(HotelRoomTypePriceContext $context, $currentDate)
    {
        if ($this->innerComponent !== null) {
            return $this->innerComponent->getDayPrice($context, $currentDate);
        }

        return array(
            'total_price_tax_incl' => 0,
            'total_price_tax_excl' => 0,
        );
    }
}
