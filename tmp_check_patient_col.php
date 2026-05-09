<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=aidora_db;charset=utf8mb4', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
try {
  $pdo->exec('ALTER TABLE patient ADD COLUMN ai_health_summary LONGTEXT NULL');
} catch (Throwable $e) {
  if (stripos($e->getMessage(), 'Duplicate column name') === false) {
    throw $e;
  }
}
$stmt = $pdo->query("SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='aidora_db' AND TABLE_NAME='patient' AND COLUMN_NAME='ai_health_summary'");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo 'COLUMN_EXISTS=' . $row['c'] . PHP_EOL;
