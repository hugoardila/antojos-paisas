<?php
require_once __DIR__ . '/config.php';

$products = [
    ['Empanada Paisa de Papa', 'Empanadas y pastelitos', 1000, 'bi-circle-fill', 10],
    ['Empanada Paisa de Arroz', 'Empanadas y pastelitos', 1000, 'bi-circle-fill', 20],
    ['Pastelito de Pollo', 'Empanadas y pastelitos', 3000, 'bi-circle-fill', 30],
    ['Chorizo Paisa', 'Chorizos', 8000, 'bi-fire', 40],
    ['Chorizo Paisa Crudo', 'Chorizos', 6000, 'bi-fire', 50],
    ['Arepa Paisa Tradicional', 'Arepas', 10000, 'bi-circle-fill', 60],
    ['Arepa Paisa Especial', 'Arepas', 15000, 'bi-circle-fill', 70],
    ['Arepa con Queso Personal', 'Arepas', 3000, 'bi-circle-fill', 80],
    ['Arepa con Queso Grande', 'Arepas', 5000, 'bi-circle-fill', 90],
    ['Salchipapa Mini', 'Salchipapas', 2000, 'bi-cup-hot', 100],
    ['Salchipapa Personal', 'Salchipapas', 5000, 'bi-cup-hot', 110],
    ['Salchipapa Especial', 'Salchipapas', 10000, 'bi-cup-hot', 120],
    ['Choripapa Venga Pues', 'Montaneras', 15000, 'bi-fire', 130],
    ['Salchipapa Montanera', 'Montaneras', 20000, 'bi-fire', 140],
    ['Patacon Tradicional', 'Patacones', 8000, 'bi-disc', 150],
    ['Patacon Supremo', 'Patacones', 15000, 'bi-disc', 160],
    ['Patacon Venga Pues', 'Patacones', 25000, 'bi-disc', 170],
    ['Hamburguesa Clasica Venga Pues', 'Hamburguesas', 20000, 'bi-basket', 180],
    ['Hamburguesa Campesina', 'Hamburguesas', 25000, 'bi-basket', 190],
    ['Hamburguesa La Parcera', 'Hamburguesas', 28000, 'bi-basket', 200],
    ['Gaseosa Mini', 'Bebidas', 2500, 'bi-cup-straw', 210],
    ['Gaseosa Personal', 'Bebidas', 4000, 'bi-cup-straw', 220],
    ['Jugos Hit', 'Bebidas', 3500, 'bi-cup-straw', 230],
    ['Agua', 'Bebidas', 2000, 'bi-droplet', 240],
];

$addons = [
    ['Porcion de papa a la francesa', 'Adicionales', 4000, 10],
    ['Porcion de papa criolla', 'Adicionales', 5000, 20],
    ['Proteina adicional', 'Adicionales', 5000, 30],
    ['Huevo frito adicional', 'Adicionales', 1000, 40],
    ['Queso adicional', 'Adicionales', 1000, 50],
    ['Porcion de huevos de codorniz', 'Adicionales', 2000, 60],
];

$pdo = db();
$pdo->beginTransaction();
try {
    $menuNames = array_map(fn($p) => $p[0], $products);
    $placeholders = implode(',', array_fill(0, count($menuNames), '?'));
    $pdo->prepare("UPDATE products SET active=0 WHERE name NOT IN ($placeholders)")->execute($menuNames);

    $find = $pdo->prepare('SELECT id FROM products WHERE name=? ORDER BY id LIMIT 1');
    $insert = $pdo->prepare('INSERT INTO products (name, category, price, cost, icon, image_path, active, sort_order) VALUES (?,?,?,?,?,?,1,?)');
    $update = $pdo->prepare('UPDATE products SET category=?, price=?, icon=?, active=1, sort_order=? WHERE id=?');
    foreach ($products as [$name, $category, $price, $icon, $sort]) {
        $find->execute([$name]);
        $id = (int)$find->fetchColumn();
        if ($id > 0) {
            $update->execute([$category, $price, $icon, $sort, $id]);
        } else {
            $insert->execute([$name, $category, $price, 0, $icon, null, $sort]);
        }
    }

    $addonNames = array_map(fn($a) => $a[0], $addons);
    $addonPlaceholders = implode(',', array_fill(0, count($addonNames), '?'));
    $pdo->prepare("UPDATE product_addons SET active=0 WHERE name NOT IN ($addonPlaceholders)")->execute($addonNames);
    $stmt = $pdo->prepare("INSERT INTO product_addons (name, category, price, sort_order, active) VALUES (?,?,?,?,1) ON DUPLICATE KEY UPDATE category=VALUES(category), price=VALUES(price), sort_order=VALUES(sort_order), active=1");
    foreach ($addons as $a) {
        $stmt->execute($a);
    }

    $settings = [
        ['business_name', 'Antojos Paisas'],
        ['ticket_footer', 'Sabor paisa, casero y delicioso'],
        ['delivery_fee', '0'],
    ];
    $settingStmt = $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
    foreach ($settings as $s) {
        $settingStmt->execute($s);
    }

    $pdo->commit();
    echo "OK products=" . count($products) . " addons=" . count($addons) . "\n";
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}
