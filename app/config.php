<?php
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (!str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v, " \t\n\r\0\x0B\"'");
        if (!getenv($k)) putenv("$k=$v");
    }
}

$databaseUrl = getenv('SUPABASE_DATABASE_URL');
if (!$databaseUrl) {
    die('SUPABASE_DATABASE_URL belum diatur di environment.');
}

// Parse manual dengan regex — tahan password karakter spesial
if (!preg_match('#^postgres(?:ql)?://([^:]+):(.+)@([^:/@]+):(\d+)/(.+)$#', $databaseUrl, $m)) {
    die('Format SUPABASE_DATABASE_URL tidak valid. Cek .env.');
}

$user   = $m[1];
$pass   = urldecode($m[2]);
$host   = $m[3];
$port   = $m[4];
$dbname = $m[5];

$connString = sprintf(
    "host=%s port=%s dbname=%s user=%s password=%s sslmode=require",
    $host, $port, $dbname, $user, $pass
);

$conn = @pg_connect($connString);

if (!$conn) {
    $err = error_get_last()['message'] ?? 'Unknown error';
    die('Koneksi database gagal: ' . $err);
}