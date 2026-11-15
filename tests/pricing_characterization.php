<?php
/**
 * Characterization test of the Decorator refactoring of
 * HotelRoomTypeFeaturePricing::getRoomTypeTotalPrice().
 *
 * Compares the new decorated price pipeline against the kept legacy copy of
 * the original monolithic implementation (getRoomTypeTotalPriceLegacy) across
 * a scenario matrix. Every scenario must produce identical tax included and
 * tax excluded totals (tolerance 0.0001).
 *
 * Usage (from the QloApps root directory, after installation):
 *   php tests/pricing_characterization.php [id_room_type_product]
 *
 * When no room type id is given, the first room type found is used.
 */

if (!defined('_PS_MODE_DEV_')) {
    define('_PS_MODE_DEV_', true);
}

require_once dirname(__FILE__).'/../config/config.inc.php';

if (!class_exists('HotelRoomTypeFeaturePricing')) {
    die("HotelRoomTypeFeaturePricing not loaded. Check the module class loading (define.php).\n");
}

// Price calculation is normally reached with a cart or an employee in the
// context (front office or back office). On the CLI there is none, so set the
// install admin employee to emulate a back office price calculation context.
$context = Context::getContext();
if (!isset($context->employee) || !Validate::isLoadedObject($context->employee)) {
    $context->employee = new Employee(1);
}

$tolerance = 0.0001;
$dateFrom = date('Y-m-d H:i:s', strtotime('+14 days'));
$dateTo = date('Y-m-d H:i:s', strtotime('+17 days'));
$longDateFrom = date('Y-m-d H:i:s', strtotime('+21 days'));
$longDateTo = date('Y-m-d H:i:s', strtotime('+28 days'));
$sameDay = date('Y-m-d H:i:s', strtotime('+14 days'));
$occupancyTwoRooms = array(
    array('adults' => 2, 'children' => 0, 'child_ages' => array()),
    array('adults' => 1, 'children' => 1, 'child_ages' => array(8)),
);

// Room type under test
$idProduct = isset($argv[1]) ? (int) $argv[1] : 0;
if (!$idProduct) {
    // getRow()/getValue() append "LIMIT 1" themselves, so no explicit LIMIT here
    $idProduct = (int) Db::getInstance()->getValue(
        'SELECT `id_product` FROM `'._DB_PREFIX_.'htl_room_type`'
    );
}
if (!$idProduct) {
    die("No room type found. Install QloApps with demo data or pass a room type product id as argument.\n");
}
echo "Room type under test: $idProduct\n";
echo str_repeat('=', 100)."\n";

// Feature pricing plan windows (see tests/fixtures_feature_pricing.sql):
// decrease 20% on nights +14..+16, increase fixed 100 on +31..+32,
// fix price 500 on +40..+41 (all relative to today).
$decreaseDateFrom = date('Y-m-d H:i:s', strtotime('+14 days'));
$decreaseDateTo = date('Y-m-d H:i:s', strtotime('+17 days'));
$increaseDateFrom = date('Y-m-d H:i:s', strtotime('+31 days'));
$increaseDateTo = date('Y-m-d H:i:s', strtotime('+33 days'));
$fixDateFrom = date('Y-m-d H:i:s', strtotime('+40 days'));
$fixDateTo = date('Y-m-d H:i:s', strtotime('+42 days'));

// Scenario matrix: label => arguments of getRoomTypeTotalPrice
$scenarios = array(
    'Basic stay (3 nights, no occupancy)' => array($idProduct, $dateFrom, $dateTo),
    'Same day from/to (normalized to 1 night)' => array($idProduct, $sameDay, $sameDay),
    'Long stay (7 nights)' => array($idProduct, $longDateFrom, $longDateTo),
    'Plan: decrease 20% (3 nights)' => array($idProduct, $decreaseDateFrom, $decreaseDateTo),
    'Plan: increase fixed 100 (2 nights)' => array($idProduct, $increaseDateFrom, $increaseDateTo),
    'Plan: fix price 500 (2 nights)' => array($idProduct, $fixDateFrom, $fixDateTo),
    'Plan decrease + occupancy 2 rooms' => array($idProduct, $decreaseDateFrom, $decreaseDateTo, $occupancyTwoRooms),
    'Plan decrease + occupancy + tourism tax' => array($idProduct, $decreaseDateFrom, $decreaseDateTo, $occupancyTwoRooms, 0, 0, 0, 0, 1, 1, true),
    'Occupancy of 2 rooms' => array($idProduct, $dateFrom, $dateTo, $occupancyTwoRooms),
    'Occupancy + tourism tax' => array($idProduct, $dateFrom, $dateTo, $occupancyTwoRooms, 0, 0, 0, 0, 1, 1, true),
    'Without auto room services' => array($idProduct, $dateFrom, $dateTo, null, 0, 0, 0, 0, 0),
    'Without reductions (use_reduc=0)' => array($idProduct, $dateFrom, $dateTo, null, 0, 0, 0, 0, 1, 0),
    'Occupancy without reductions' => array($idProduct, $dateFrom, $dateTo, $occupancyTwoRooms, 0, 0, 0, 0, 1, 0, true),
);

$legacyMethod = new ReflectionMethod('HotelRoomTypeFeaturePricing', 'getRoomTypeTotalPriceLegacy');
$legacyMethod->setAccessible(true);

$failures = 0;
printf("%-42s %15s %15s %8s %8s\n", 'Scenario', 'Legacy TI', 'New TI', 'd TI', 'd TE');
echo str_repeat('-', 100)."\n";

foreach ($scenarios as $label => $args) {
    $legacy = $legacyMethod->invokeArgs(null, $args);
    $new = forward_static_call_array(
        array('HotelRoomTypeFeaturePricing', 'getRoomTypeTotalPrice'),
        $args
    );

    $diffTI = abs($legacy['total_price_tax_incl'] - $new['total_price_tax_incl']);
    $diffTE = abs($legacy['total_price_tax_excl'] - $new['total_price_tax_excl']);
    $passed = $diffTI < $tolerance && $diffTE < $tolerance;
    if (!$passed) {
        $failures++;
    }

    printf(
        "%-42s %15.4f %15.4f %8.4f %8.4f  %s\n",
        $label,
        $legacy['total_price_tax_incl'],
        $new['total_price_tax_incl'],
        $diffTI,
        $diffTE,
        $passed ? 'PASS' : 'FAIL'
    );
    if (!$passed) {
        printf("  legacy: %s\n  new:    %s\n", var_export($legacy, true), var_export($new, true));
    }
}

echo str_repeat('=', 100)."\n";
echo $failures ? "RESULT: $failures FAILURE(S) - the decorated pipeline diverged from the legacy implementation.\n" : "RESULT: All scenarios identical - the Decorator pipeline reproduces the legacy behaviour.\n";
exit($failures ? 1 : 0);
