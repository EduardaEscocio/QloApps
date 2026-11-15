<?php
/**
 * Temporary diagnostic probe: compares legacy vs decorated pipeline with the
 * EXACT argument patterns used by the cart display paths
 * (HotelCartBookingData::getCartDataByCart and getCartDataByGuestId-style
 * aggregation). Delete after diagnosis.
 */
if (!defined('_PS_MODE_DEV_')) {
    define('_PS_MODE_DEV_', true);
}
require_once dirname(__FILE__).'/../config/config.inc.php';

$context = Context::getContext();
if (!isset($context->employee) || !Validate::isLoadedObject($context->employee)) {
    $context->employee = new Employee(1);
}

$legacy = new ReflectionMethod('HotelRoomTypeFeaturePricing', 'getRoomTypeTotalPriceLegacy');
$legacy->setAccessible(true);

// cart rows in DB: id_cart=1, id_product=1, guest=3, rooms 1 and 2
$occupancy = array(array('adults' => 2, 'children' => 0, 'child_ages' => array()));

$cases = array(
    // label => args
    'Path B room 1 (services=1, tax-incl)' => array(1, '2026-11-29 00:00:00', '2026-12-02 00:00:00', $occupancy, 0, 1, 3, 1, 1, 1, false),
    'Path B room 2 (services=1, tax-incl)' => array(1, '2026-11-29 00:00:00', '2026-12-02 00:00:00', $occupancy, 0, 1, 3, 2, 1, 1, false),
    'Path A room 1 (services=0, tax-incl)' => array(1, '2026-11-29 00:00:00', '2026-12-02 00:00:00', $occupancy, 0, 1, 3, 1, 0, 1, false),
    'Path A room 2 (services=0, tax-incl)' => array(1, '2026-11-29 00:00:00', '2026-12-02 00:00:00', $occupancy, 0, 1, 3, 2, 0, 1, false),
    'No-cart ctx occupancy (harness style)' => array(1, '2026-11-29 00:00:00', '2026-12-02 00:00:00', $occupancy, 0, 0, 0, 0, 1, 1, false),
);

foreach ($cases as $label => $args) {
    $old = $legacy->invokeArgs(null, $args);
    $new = forward_static_call_array(array('HotelRoomTypeFeaturePricing', 'getRoomTypeTotalPrice'), $args);
    $dTI = abs($old['total_price_tax_incl'] - $new['total_price_tax_incl']);
    $dTE = abs($old['total_price_tax_excl'] - $new['total_price_tax_excl']);
    printf(
        "%-40s legacy TI=%10.2f new TI=%10.2f (d=%.2f) legacy TE=%10.2f new TE=%10.2f (d=%.2f) %s\n",
        $label,
        $old['total_price_tax_incl'], $new['total_price_tax_incl'], $dTI,
        $old['total_price_tax_excl'], $new['total_price_tax_excl'], $dTE,
        ($dTI < 0.0001 && $dTE < 0.0001) ? 'PASS' : '** DIVERGE **'
    );
}
