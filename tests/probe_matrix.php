<?php
/**
 * Temporary diagnostic probe: full matrix legacy vs new across occupancy
 * shapes and with_auto_room_services, printing TE and TI. Delete after diagnosis.
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

$shapes = array(
    'null'      => null,
    '[[2,0]]'   => array(array('adults' => 2, 'children' => 0, 'child_ages' => array())),
);

printf("%-28s %-9s %-10s %10s %10s %10s %10s\n", 'call', 'occupancy', 'services', 'legacy TI', 'new TI', 'legacy TE', 'new TE');
echo str_repeat('-', 95)."\n";
foreach ($shapes as $shapeLabel => $occ) {
    foreach (array(0, 1) as $services) {
        $args = array(1, '2026-11-29 00:00:00', '2026-12-02 00:00:00', $occ, 0, 0, 0, 0, $services, 1, false);
        $old = $legacy->invokeArgs(null, $args);
        $new = forward_static_call_array(array('HotelRoomTypeFeaturePricing', 'getRoomTypeTotalPrice'), $args);
        printf(
            "%-28s %-9s %-10s %10.2f %10.2f %10.2f %10.2f\n",
            'getRoomTypeTotalPrice', $shapeLabel, $services,
            $old['total_price_tax_incl'], $new['total_price_tax_incl'],
            $old['total_price_tax_excl'], $new['total_price_tax_excl']
        );
    }
}
