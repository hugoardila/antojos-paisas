<?php
require_once __DIR__ . '/config.php';

function epayco_column_exists(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function epayco_add_column(PDO $pdo, string $table, string $column, string $definition): void {
    if (!epayco_column_exists($pdo, $table, $column)) {
        $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    }
}

$pdo = db();
epayco_add_column($pdo, 'orders', 'epayco_ref', 'VARCHAR(120) NULL AFTER fulfillment_status');
epayco_add_column($pdo, 'orders', 'epayco_transaction_id', 'VARCHAR(120) NULL AFTER epayco_ref');
epayco_add_column($pdo, 'orders', 'epayco_response', 'TEXT NULL AFTER epayco_transaction_id');

echo "OK\n";
