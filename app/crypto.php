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

$url = parse_url($databaseUrl);
$connString = sprintf(
    "host=%s port=%s dbname=%s user=%s password=%s sslmode=require",
    $url['host'],
    $url['port'] ?? 6543,
    ltrim($url['path'], '/'),
    $url['user'],
    $url['pass']
);

$conn = @pg_connect($connString);

if (!$conn) {
    die('Koneksi database gagal: ' . pg_last_error());
}