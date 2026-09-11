<?php
require_once __DIR__ . '/config.php';

function column_exists(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function add_column(PDO $pdo, string $table, string $column, string $definition): void {
    if (!column_exists($pdo, $table, $column)) {
        $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    }
}

$pdo = db();
$pdo->exec("
CREATE TABLE IF NOT EXISTS customers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  phone VARCHAR(40) NOT NULL,
  email VARCHAR(160) NULL,
  address VARCHAR(255) NULL,
  neighborhood VARCHAR(120) NULL,
  notes TEXT NULL,
  orders_count INT NOT NULL DEFAULT 0,
  total_spent DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  last_order_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_customers_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$pdo->exec("
CREATE TABLE IF NOT EXISTS product_addons (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  category VARCHAR(90) NOT NULL DEFAULT 'Adiciones',
  price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 100,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_addon_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$pdo->exec("
CREATE TABLE IF NOT EXISTS order_item_addons (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_item_id INT NOT NULL,
  addon_id INT NULL,
  addon_name VARCHAR(120) NOT NULL,
  quantity DECIMAL(10,2) NOT NULL DEFAULT 1.00,
  unit_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  line_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  KEY idx_order_item_addons_item (order_item_id),
  CONSTRAINT fk_order_item_addons_item FOREIGN KEY (order_item_id) REFERENCES order_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

add_column($pdo, 'orders', 'customer_id', 'INT NULL AFTER customer_name');
add_column($pdo, 'orders', 'customer_phone', 'VARCHAR(40) NULL AFTER customer_id');
add_column($pdo, 'orders', 'delivery_address', 'VARCHAR(255) NULL AFTER table_name');
add_column($pdo, 'orders', 'delivery_neighborhood', 'VARCHAR(120) NULL AFTER delivery_address');
add_column($pdo, 'orders', 'delivery_fee', 'DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER discount');
add_column($pdo, 'orders', 'source', "VARCHAR(30) NOT NULL DEFAULT 'pos' AFTER status");
add_column($pdo, 'orders', 'fulfillment_status', "VARCHAR(30) NOT NULL DEFAULT 'recibido' AFTER source");

$addons = [
    ['Queso extra', 'Adiciones', 3000, 10],
    ['Chorizo extra', 'Adiciones', 6000, 20],
    ['Salsa extra', 'Salsas', 1000, 30],
    ['Maicitos', 'Adiciones', 2000, 40],
    ['Madurito', 'Adiciones', 2000, 50],
    ['Tocineta', 'Adiciones', 4000, 60],
];
$stmt = $pdo->prepare("INSERT INTO product_addons (name, category, price, sort_order, active) VALUES (?,?,?,?,1) ON DUPLICATE KEY UPDATE category=VALUES(category), price=VALUES(price), active=1, sort_order=VALUES(sort_order)");
foreach ($addons as $a) {
    $stmt->execute($a);
}

echo "OK\n";
