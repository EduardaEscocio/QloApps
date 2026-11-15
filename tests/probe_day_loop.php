<?php
/**
 * Temporary diagnostic probe: reproduces the legacy day loop inline and prints
 * every intermediate value for occupancy=null vs occupancy=[[2 adults]],
 * exposing where the two occupancy shapes diverge. Delete after diagnosis.
 */
if (!defined('_PS_MODE_DEV_')) {
    define('_PS_MODE_DEV_', true);
}
require_once dirname(__FILE__).'/../config/config.inc.php';

$context = Context::getContext();
if (!isset($context->employee) || !Validate::isLoadedObject($context->employee)) {
    $context->employee = new Employee(1);
}

function runLegacyInline($id_product, $date_from, $date_to, $occupancy, $label)
{
    $id_group = 0;
    $id_cart = 0;
    $id_guest = 0;
    $id_room = 0;
    $use_reduc = 1;
    $includeTourismTax = false;
    $with_auto_room_services = 0;

    $totalPrice = array();
    $totalPrice['total_price_tax_incl'] = 0;
    $totalPrice['total_price_tax_excl'] = 0;
    $featureImpactPriceTE = 0;
    $featureImpactPriceTI = 0;

    $productPriceTI = Product::getPriceStatic((int) $id_product, 1, 0, 6, null, 0, $use_reduc, 1, 0, null, null, null, $nothing, 1, 1, null, 1, 0, 0, $id_group);
    $productPriceTE = Product::getPriceStatic((int) $id_product, 0, 0, 6, null, 0, $use_reduc, 1, 0, null, null, null, $nothing, 1, 1, null, 1, 0, 0, $id_group);
    echo "[$label] base TI=$productPriceTI TE=$productPriceTE\n";

    $id_address = HotelRoomType::getHotelIdAddressByIdProduct($id_product);
    $objAddress = new Address($id_address);
    $id_tax_rule_group = Product::getIdTaxRulesGroupByIdProduct((int)$id_product);
    $tax_calculator = TaxManagerFactory::getManager($objAddress, $id_tax_rule_group)->getTaxCalculator();
    $taxRate = $tax_calculator->getTotalRate();
    echo "[$label] taxRate=$taxRate\n";

    if (is_array($occupancy) && count($occupancy)) {
        $quantity = count($occupancy);
    } else {
        $quantity = $occupancy;
    }
    if (!$id_group) {
        $id_group = (int)Group::getCurrent()->id;
    }
    echo "[$label] id_group=$id_group quantity=" . var_export($quantity, true) . "\n";

    if (strtotime($date_from) == strtotime($date_to)) {
        $date_to = date('Y-m-d H:i:s', strtotime('+1 day', strtotime($date_from)));
    }
    $context = Context::getContext();
    $id_currency = Validate::isLoadedObject($context->currency) ? (int)$context->currency->id : (int)Configuration::get('PS_CURRENCY_DEFAULT');

    for ($currentDate = date('Y-m-d', strtotime($date_from)); $currentDate < date('Y-m-d', strtotime($date_to)); $currentDate = date('Y-m-d', strtotime('+1 day', strtotime($currentDate)))) {
        if ($use_reduc && ($featurePrice = HotelCartBookingData::getProductFeaturePricePlanByDateByPriority(
            $id_product,
            $currentDate,
            $id_group,
            $id_cart,
            $id_guest,
            $id_room
        ))) {
            $planInfo = sprintf('plan id=%s way=%s type=%s value=%s id_cart=%s', $featurePrice['id_feature_price'], $featurePrice['impact_way'], $featurePrice['impact_type'], $featurePrice['impact_value'], $featurePrice['id_cart']);

            if ($featurePrice['impact_type'] == HotelRoomTypeFeaturePricing::IMPACT_TYPE_PERCENTAGE) {
                $featureImpactPriceTE = $productPriceTE * ($featurePrice['impact_value'] / 100);
                $featureImpactPriceTI = $productPriceTI * ($featurePrice['impact_value'] / 100);
            } else {
                $taxPrice = ($featurePrice['impact_value'] * $taxRate) / 100;
                $featureImpactPriceTE = Tools::convertPrice($featurePrice['impact_value'], $id_currency);
                $featureImpactPriceTI = Tools::convertPrice($featurePrice['impact_value'] + $taxPrice, $id_currency);
            }
            if ($featurePrice['impact_way'] == HotelRoomTypeFeaturePricing::IMPACT_WAY_DECREASE) {
                $priceWithFeatureTE = ($productPriceTE - $featureImpactPriceTE);
                $priceWithFeatureTI = ($productPriceTI - $featureImpactPriceTI);
            } elseif ($featurePrice['impact_way'] == HotelRoomTypeFeaturePricing::IMPACT_WAY_INCREASE) {
                $priceWithFeatureTE = ($productPriceTE + $featureImpactPriceTE);
                $priceWithFeatureTI = ($productPriceTI + $featureImpactPriceTI);
            } else {
                $priceWithFeatureTE = $featureImpactPriceTE;
                $priceWithFeatureTI = $featureImpactPriceTI;
            }
            if ($priceWithFeatureTI < 0) {
                $priceWithFeatureTI = 0;
                $priceWithFeatureTE = 0;
            }
            if ((int)$featurePrice['id_cart'] == 0) {
                $priceWithFeatureTI = Product::applyGroupDiscount($priceWithFeatureTI, $id_product, $id_group);
                $priceWithFeatureTE = Product::applyGroupDiscount($priceWithFeatureTE, $id_product, $id_group);
                $planInfo .= ' +groupDiscount';
            }
            echo "[$label] $currentDate $planInfo => TI=$priceWithFeatureTI TE=$priceWithFeatureTE\n";
            $totalPrice['total_price_tax_incl'] += $priceWithFeatureTI;
            $totalPrice['total_price_tax_excl'] += $priceWithFeatureTE;
        } else {
            $dayPriceTI = Product::applyGroupDiscount($productPriceTI, $id_product, $id_group);
            $dayPriceTE = Product::applyGroupDiscount($productPriceTE, $id_product, $id_group);
            echo "[$label] $currentDate no-plan => TI=$dayPriceTI TE=$dayPriceTE\n";
            $totalPrice['total_price_tax_incl'] += $dayPriceTI;
            $totalPrice['total_price_tax_excl'] += $dayPriceTE;
        }
    }
    echo "[$label] FINAL (qty multiply) TI={$totalPrice['total_price_tax_incl']} TE={$totalPrice['total_price_tax_excl']}\n\n";
    return $totalPrice;
}

runLegacyInline(1, '2026-11-29 00:00:00', '2026-12-02 00:00:00', null, 'null');
runLegacyInline(1, '2026-11-29 00:00:00', '2026-12-02 00:00:00', array(array('adults' => 2, 'children' => 0, 'child_ages' => array())), 'occ[[2,0]]');
