<?php
/**
 * Clean CodeCanyon Installable Database Dump Generator
 * Generates an up-to-date, sanitized database.sql with all 130 tables,
 * clean demo credentials (admin@zoompos.com / admin1234), and default seed data.
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$pdo = DB::connection()->getPdo();
$dbName = config('database.connections.mysql.database');

echo "Generating clean database dump for CodeCanyon from '{$dbName}'..." . PHP_EOL;

$outputFile = __DIR__ . '/../public/zoom-sales-crm-database-clean.sql';
$fp = fopen($outputFile, 'w');

// Header
fwrite($fp, "-- ==============================================================================\n");
fwrite($fp, "-- Zoom Sales CRM & Inventory POS — Complete Clean Database Schema\n");
fwrite($fp, "-- Version: 1.0.6 (CodeCanyon Release)\n");
fwrite($fp, "-- Includes: Core Multi-Tenant Platform with Retail & Restaurant POS Built-in\n");
fwrite($fp, "-- Generated at: " . date('Y-m-d H:i:s') . " UTC\n");
fwrite($fp, "-- ==============================================================================\n\n");
fwrite($fp, "SET FOREIGN_KEY_CHECKS=0;\n");
fwrite($fp, "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n");
fwrite($fp, "SET time_zone = \"+00:00\";\n");
fwrite($fp, "SET NAMES utf8mb4;\n\n");

// Tables that should have data exported (seed / reference / demo data)
$dataTables = [
    'roles',
    'permissions',
    'model_has_roles',
    'model_has_permissions',
    'role_has_permissions',
    'plans',
    'plan_addons',
    'currencies',
    'languages',
    'units',
    'categories',
    'payment_methods',
    'settings',
    'migrations',
];

// Tables that should be strictly empty (logs, sessions, caches)
$emptyTables = [
    'activity_log',
    'sessions',
    'cache',
    'cache_locks',
    'failed_jobs',
    'jobs',
    'job_batches',
    'personal_access_tokens',
    'password_reset_tokens',
    'notifications',
    'customer_wallet_transactions',
    'loyalty_points_transactions',
    'hrm_attendances',
    'hrm_leaves',
    'hrm_payrolls',
    'orders',
    'payments',
    'sales',
    'sale_items',
    'purchases',
    'purchase_items',
];

$allTables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

foreach ($allTables as $table) {
    $createStmt = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_ASSOC);

    // Check if it is a VIEW
    if (!empty($createStmt['Create View'])) {
        fwrite($fp, "-- -------------------------------------------------------------\n");
        fwrite($fp, "-- View structure for `{$table}`\n");
        fwrite($fp, "-- -------------------------------------------------------------\n");
        fwrite($fp, "DROP VIEW IF EXISTS `{$table}`;\n");
        // Strip DEFINER clause so it works seamlessly on any buyer's database user
        $viewSql = preg_replace('/DEFINER=`[^`]+`@`[^`]+`\s*/', '', $createStmt['Create View']);
        fwrite($fp, $viewSql . ";\n\n");
        continue;
    }

    // 1. Table structure
    fwrite($fp, "-- -------------------------------------------------------------\n");
    fwrite($fp, "-- Table structure for `{$table}`\n");
    fwrite($fp, "-- -------------------------------------------------------------\n");
    fwrite($fp, "DROP TABLE IF EXISTS `{$table}`;\n");
    
    $createSql = $createStmt['Create Table'] ?? '';
    
    // Clean AUTO_INCREMENT value
    $createSql = preg_replace('/AUTO_INCREMENT=\d+\s*/', '', $createSql);
    fwrite($fp, $createSql . ";\n\n");

    // 2. Table Data
    if (in_array($table, $emptyTables, true)) {
        continue;
    }

    if (in_array($table, $dataTables, true)) {
        $rows = $pdo->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($rows)) {
            fwrite($fp, "-- Dumping data for table `{$table}`\n");
            fwrite($fp, "LOCK TABLES `{$table}` WRITE;\n");
            fwrite($fp, "/*!40000 ALTER TABLE `{$table}` DISABLE KEYS */;\n");

            $cols = array_keys($rows[0]);
            $colList = implode('`, `', $cols);

            $chunks = array_chunk($rows, 50);
            foreach ($chunks as $chunk) {
                $values = [];
                foreach ($chunk as $row) {
                    $valList = [];
                    foreach ($row as $val) {
                        if ($val === null) {
                            $valList[] = 'NULL';
                        } else {
                            $valList[] = $pdo->quote($val);
                        }
                    }
                    $values[] = '(' . implode(', ', $valList) . ')';
                }
                fwrite($fp, "INSERT INTO `{$table}` (`{$colList}`) VALUES \n" . implode(",\n", $values) . ";\n");
            }

            fwrite($fp, "/*!40000 ALTER TABLE `{$table}` ENABLE KEYS */;\n");
            fwrite($fp, "UNLOCK TABLES;\n\n");
        }
    }
}

// Now insert clean SuperAdmin, Demo Store Manager, and Demo Cashier into `users` and `companies` and `stores`
fwrite($fp, "-- -------------------------------------------------------------\n");
fwrite($fp, "-- Default Demo Accounts & Tenant Structure\n");
fwrite($fp, "-- -------------------------------------------------------------\n");

// Hash for 'admin1234'
$adminHash = '$2y$12$e6m407mO19z7k77eU4R8QeXy671gM7cIq9B3QhYF9eD1R8k6dGz7y'; // bcrypt of 'admin1234'
// Or generate dynamically
$adminHash = password_hash('admin1234', PASSWORD_BCRYPT);

fwrite($fp, "LOCK TABLES `companies` WRITE;\n");
fwrite($fp, "INSERT INTO `companies` (`id`, `name`, `subdomain`, `email`, `phone`, `is_active`, `created_at`, `updated_at`) VALUES (1, 'Zoom Demo Store', 'demo', 'store@demo.com', '+1234567890', 1, NOW(), NOW()) ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);\n");
fwrite($fp, "UNLOCK TABLES;\n\n");

fwrite($fp, "LOCK TABLES `stores` WRITE;\n");
fwrite($fp, "INSERT INTO `stores` (`id`, `company_id`, `name`, `code`, `is_active`, `created_at`, `updated_at`) VALUES (1, 1, 'Main Retail & Cafe Branch', 'STORE-01', 1, NOW(), NOW()) ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);\n");
fwrite($fp, "UNLOCK TABLES;\n\n");

fwrite($fp, "LOCK TABLES `users` WRITE;\n");
fwrite($fp, "INSERT INTO `users` (`id`, `company_id`, `store_id`, `name`, `email`, `password`, `is_active`, `created_at`, `updated_at`) VALUES \n");
fwrite($fp, "(1, NULL, NULL, 'Super Administrator', 'admin@zoompos.com', " . $pdo->quote($adminHash) . ", 1, NOW(), NOW()),\n");
fwrite($fp, "(2, 1, 1, 'Store Manager', 'store@demo.com', " . $pdo->quote($adminHash) . ", 1, NOW(), NOW()),\n");
fwrite($fp, "(3, 1, 1, 'Cashier Demo', 'cashier@demo.com', " . $pdo->quote($adminHash) . ", 1, NOW(), NOW())\n");
fwrite($fp, "ON DUPLICATE KEY UPDATE `password`=VALUES(`password`);\n");
fwrite($fp, "UNLOCK TABLES;\n\n");

// Assign roles
fwrite($fp, "LOCK TABLES `model_has_roles` WRITE;\n");
// Superadmin role (id 1 or name superadmin)
$roleRows = $pdo->query("SELECT id, name FROM roles")->fetchAll(PDO::FETCH_KEY_PAIR);
$superadminRoleId = array_search('superadmin', $roleRows) ?: 1;
$adminRoleId = array_search('admin', $roleRows) ?: 2;
$cashierRoleId = array_search('cashier', $roleRows) ?: 3;

fwrite($fp, "INSERT INTO `model_has_roles` (`role_id`, `model_type`, `model_id`) VALUES \n");
fwrite($fp, "({$superadminRoleId}, 'App\\\\Models\\\\User', 1),\n");
fwrite($fp, "({$adminRoleId}, 'App\\\\Models\\\\User', 2),\n");
fwrite($fp, "({$cashierRoleId}, 'App\\\\Models\\\\User', 3)\n");
fwrite($fp, "ON DUPLICATE KEY UPDATE `role_id`=VALUES(`role_id`);\n");
fwrite($fp, "UNLOCK TABLES;\n\n");

// Restaurant tables demo
fwrite($fp, "LOCK TABLES `restaurant_tables` WRITE;\n");
fwrite($fp, "INSERT INTO `restaurant_tables` (`id`, `company_id`, `store_id`, `name`, `seating_capacity`, `status`, `created_at`, `updated_at`) VALUES \n");
fwrite($fp, "(1, 1, 1, 'Table T-01', 4, 'available', NOW(), NOW()),\n");
fwrite($fp, "(2, 1, 1, 'Table T-02', 2, 'available', NOW(), NOW()),\n");
fwrite($fp, "(3, 1, 1, 'Table T-03', 6, 'available', NOW(), NOW()),\n");
fwrite($fp, "(4, 1, 1, 'VIP Lounge-01', 8, 'available', NOW(), NOW())\n");
fwrite($fp, "ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);\n");
fwrite($fp, "UNLOCK TABLES;\n\n");

fwrite($fp, "SET FOREIGN_KEY_CHECKS=1;\n");
fclose($fp);

$sizeMb = round(filesize($outputFile) / 1024 / 1024, 2);
echo "SUCCESS: Clean database dump saved to '{$outputFile}' ({$sizeMb} MB)." . PHP_EOL;
