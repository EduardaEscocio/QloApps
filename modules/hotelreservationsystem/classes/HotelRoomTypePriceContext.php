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
 * Carries the inputs and the shared state of a room type price calculation.
 *
 * It acts as the accumulator passed along the price component chain
 * (Decorator pattern), holding both the calculation inputs and the running
 * totals every component adds to.
 */
class HotelRoomTypePriceContext
{
    /** @var int Room type product id */
    public $id_product;

    /** @var string Stay start date */
    public $date_from;

    /** @var string Stay end date, normalized to at least one night of stay */
    public $date_to;

    /** @var mixed Null, rooms quantity or array of per room occupancy data */
    public $occupancy;

    /** @var int Customer group id */
    public $id_group;

    /** @var int Cart id */
    public $id_cart;

    /** @var int Guest id */
    public $id_guest;

    /** @var int Physical room id */
    public $id_room;

    /** @var bool Whether auto added room services are included in the total */
    public $with_auto_room_services;

    /** @var bool Whether specific price reductions and feature pricing plans apply */
    public $use_reduc;

    /** @var bool Whether the tourism tax is included in the tax included total */
    public $includeTourismTax;

    /** @var float Base price of the room type per night, tax included */
    public $productPriceTI = 0;

    /** @var float Base price of the room type per night, tax excluded */
    public $productPriceTE = 0;

    /** @var int Hotel address id of the room type */
    public $id_address = 0;

    /** @var Address|false Hotel address object of the room type */
    public $objAddress = false;

    /** @var int Tax rules group id of the room type */
    public $id_tax_rule_group = 0;

    /** @var TaxCalculator|false Tax calculator of the room type */
    public $tax_calculator = false;

    /** @var float Total tax rate of the room type */
    public $taxRate = 0;

    /** @var int Number of rooms the stay totals are scaled by */
    public $quantity = 0;

    /** @var int Currency id used to convert fixed price impacts */
    public $id_currency = 0;

    /** @var array Running totals accumulator of the calculation */
    public $totalPrice = array(
        'total_price_tax_incl' => 0,
        'total_price_tax_excl' => 0,
    );

    /** @var bool Whether the shared base price data was already loaded */
    protected $basePriceLoaded = false;

    /**
     * @param int $id_product Room type product id
     * @param string $date_from Stay start date
     * @param string $date_to Stay end date
     * @param mixed $occupancy Null, rooms quantity or array of per room occupancy data
     * @param int $id_group Customer group id
     * @param int $id_cart Cart id
     * @param int $id_guest Guest id
     * @param int $id_room Physical room id
     * @param bool $with_auto_room_services Whether auto added room services are included
     * @param bool $use_reduc Whether specific price reductions and feature pricing plans apply
     * @param bool $includeTourismTax Whether the tourism tax is included in the tax included total
     */
    public function __construct(
        $id_product,
        $date_from,
        $date_to,
        $occupancy = null,
        $id_group = 0,
        $id_cart = 0,
        $id_guest = 0,
        $id_room = 0,
        $with_auto_room_services = 1,
        $use_reduc = 1,
        $includeTourismTax = false
    ) {
        $this->id_product = $id_product;
        $this->date_from = $date_from;
        $this->date_to = $date_to;
        $this->occupancy = $occupancy;
        $this->id_group = $id_group;
        $this->id_cart = $id_cart;
        $this->id_guest = $id_guest;
        $this->id_room = $id_room;
        $this->with_auto_room_services = $with_auto_room_services;
        $this->use_reduc = $use_reduc;
        $this->includeTourismTax = $includeTourismTax;

        // if date_from and date_to are same then date_to will be the next date of date_from
        if (strtotime($this->date_from) == strtotime($this->date_to)) {
            $this->date_to = date('Y-m-d H:i:s', strtotime('+1 day', strtotime($this->date_from)));
        }
    }

    /**
     * Loads the shared base price data used by the whole component chain:
     * per night base prices, hotel address, tax rate, rooms quantity,
     * customer group and currency. Safe to call multiple times, the data
     * is loaded only once.
     */
    public function initBasePriceData()
    {
        if ($this->basePriceLoaded) {
            return;
        }
        $this->basePriceLoaded = true;

        $nothing = null;
        $this->productPriceTI = Product::getPriceStatic((int) $this->id_product, 1, 0, 6, null, 0, $this->use_reduc, 1, 0, null, null, null, $nothing, 1, 1, null, 1, 0, 0, $this->id_group);
        $this->productPriceTE = Product::getPriceStatic((int) $this->id_product, 0, 0, 6, null, 0, $this->use_reduc, 1, 0, null, null, null, $nothing, 1, 1, null, 1, 0, 0, $this->id_group);
        $this->id_address = HotelRoomType::getHotelIdAddressByIdProduct($this->id_product);
        $this->objAddress = new Address($this->id_address);
        $this->id_tax_rule_group = Product::getIdTaxRulesGroupByIdProduct((int) $this->id_product);
        $this->tax_calculator = TaxManagerFactory::getManager($this->objAddress, $this->id_tax_rule_group)->getTaxCalculator();
        $this->taxRate = $this->tax_calculator->getTotalRate();

        if (is_array($this->occupancy) && count($this->occupancy)) {
            $this->quantity = count($this->occupancy);
        } else {
            $this->quantity = $this->occupancy;
        }

        // Initializations
        if (!$this->id_group) {
            $this->id_group = (int) Group::getCurrent()->id;
        }

        $context = Context::getContext();
        $this->id_currency = Validate::isLoadedObject($context->currency) ? (int) $context->currency->id : (int) Configuration::get('PS_CURRENCY_DEFAULT');
    }

    /**
     * Returns the stay dates the price is accumulated over.
     * The checkout night is excluded.
     *
     * @return Generator Yields each stay date as Y-m-d
     */
    public function getStayDates()
    {
        for (
            $currentDate = date('Y-m-d', strtotime($this->date_from));
            $currentDate < date('Y-m-d', strtotime($this->date_to));
            $currentDate = date('Y-m-d', strtotime('+1 day', strtotime($currentDate)))
        ) {
            yield $currentDate;
        }
    }

    /**
     * Adds an amount to the tax included running total.
     *
     * @param float $amount Amount to add
     */
    public function addTaxIncl($amount)
    {
        $this->totalPrice['total_price_tax_incl'] += $amount;
    }

    /**
     * Adds an amount to the tax excluded running total.
     *
     * @param float $amount Amount to add
     */
    public function addTaxExcl($amount)
    {
        $this->totalPrice['total_price_tax_excl'] += $amount;
    }

    /**
     * Returns the running totals accumulator.
     *
     * @return array Keys: total_price_tax_incl, total_price_tax_excl
     */
    public function getTotalPrice()
    {
        return $this->totalPrice;
    }
}
