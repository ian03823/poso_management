<?php
header('content-type: application/json');

function out($x){ echo json_encode($x, JSON_PRETTY_PRINT); }

try {
  $env = [
    'php'      => PHP_VERSION,
    'pdo_pgsql'=> extension_loaded('pdo_pgsql'),
    'host'     => getenv('DB_HOST') ?: null,
    'port'     => getenv('DB_PORT') ?: null,
    'db'       => getenv('DB_DATABASE') ?: null,
    'user'     => getenv('DB_USERNAME') ?: null,
    'sslmode'  => getenv('DB_SSLMODE') ?: null,
  ];

  $dsn = sprintf(
    'pgsql:host=%s;port=%s;dbname=%s;sslmode=%s',
    getenv('DB_HOST'),
    getenv('DB_PORT') ?: '6543',
    getenv('DB_DATABASE') ?: 'postgres',
    getenv('DB_SSLMODE') ?: 'require'
  );

  $pdo = new PDO($dsn, getenv('DB_USERNAME'), getenv('DB_PASSWORD'), [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => true,  // important for Supabase pooler
    PDO::ATTR_PERSISTENT       => false,
  ]);

  $row = $pdo->query('select version(), now() as ts')->fetch(PDO::FETCH_ASSOC);
  out(['ok'=>true, 'env'=>$env, 'db'=>$row]);
} catch (Throwable $e) {
  out(['ok'=>false, 'error'=>$e->getMessage()]);
}
