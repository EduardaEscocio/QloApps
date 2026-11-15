-- Fixture temporário para o teste de caracterização do Decorator
-- (tests/pricing_characterization.php): cria 3 planos de feature pricing
-- para o room type 1 em janelas de datas não sobrepostas, exercitando a
-- matriz de impacto completa (decrease %, increase fixo, fix price).
--
-- Aplicar:   mysql -u root qloapps < tests/fixtures_feature_pricing.sql
-- Remover:   mysql -u root qloapps < tests/fixtures_feature_pricing.sql  (seção CLEANUP)

-- ===== FIXTURE =====

INSERT INTO qlo_htl_room_type_feature_pricing
    (id_product, id_cart, id_guest, id_room, impact_way, impact_type, impact_value, active, date_add, date_upd)
VALUES
    (1, 0, 0, 0, 1, 1, 20,  1, NOW(), NOW()),  -- decrease 20%
    (1, 0, 0, 0, 2, 2, 100, 1, NOW(), NOW()),  -- increase fixed 100
    (1, 0, 0, 0, 3, 2, 500, 1, NOW(), NOW());  -- fix price 500

SET @p3 = (SELECT MAX(id_feature_price) FROM qlo_htl_room_type_feature_pricing WHERE id_product = 1);
SET @p2 = @p3 - 1;
SET @p1 = @p3 - 2;

INSERT INTO qlo_htl_room_type_feature_pricing_lang (id_feature_price, id_lang, feature_price_name)
VALUES
    (@p1, 1, 'Test Decrease 20%'),
    (@p2, 1, 'Test Increase Fixed 100'),
    (@p3, 1, 'Test Fix 500');

INSERT INTO qlo_htl_room_type_feature_pricing_restriction
    (id_feature_price, is_special_days_exists, date_selection_type, special_days, date_from, date_to, date_add, date_upd)
VALUES
    (@p1, 0, 1, NULL, CURDATE() + INTERVAL 14 DAY, CURDATE() + INTERVAL 16 DAY, NOW(), NOW()),
    (@p2, 0, 1, NULL, CURDATE() + INTERVAL 31 DAY, CURDATE() + INTERVAL 32 DAY, NOW(), NOW()),
    (@p3, 0, 1, NULL, CURDATE() + INTERVAL 40 DAY, CURDATE() + INTERVAL 41 DAY, NOW(), NOW());

INSERT INTO qlo_htl_room_type_feature_pricing_group (id_feature_price, id_group)
SELECT fp.id_feature_price, g.id_group
FROM qlo_htl_room_type_feature_pricing fp
CROSS JOIN qlo_group g
WHERE fp.id_feature_price IN (@p1, @p2, @p3);

-- ===== CLEANUP (executar depois da validação final) =====

-- DELETE fp, fpl, fpr, fpg
-- FROM qlo_htl_room_type_feature_pricing fp
-- LEFT JOIN qlo_htl_room_type_feature_pricing_lang fpl ON fpl.id_feature_price = fp.id_feature_price
-- LEFT JOIN qlo_htl_room_type_feature_pricing_restriction fpr ON fpr.id_feature_price = fp.id_feature_price
-- LEFT JOIN qlo_htl_room_type_feature_pricing_group fpg ON fpg.id_feature_price = fp.id_feature_price
-- WHERE fp.id_product = 1 AND fp.feature_price_name IS NULL AND fp.impact_value IN (20, 100, 500)
--   AND fp.id_feature_price > (SELECT MAX(id_feature_price) - 3 FROM qlo_htl_room_type_feature_pricing);
