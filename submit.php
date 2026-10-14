<?php
declare(strict_types=1);

require_once __DIR__ . '/app/config.php';

header('Content-Type: application/json; charset=utf-8');

function fail(int $code, string $msg): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail(405, 'Method Not Allowed');
}

if (!isset($_POST['latitude'], $_POST['longitude'])) {
    fail(400, 'Data tidak lengkap');
}

$latitude  = $_POST['latitude'];
$longitude = $_POST['longitude'];

if (!is_numeric($latitude) || !is_numeric($longitude)) {
    fail(400, 'Koordinat tidak valid');
}
$lat = (float)$latitude;
$lng = (float)$longitude;

/* ====== Simpan selfie ke Supabase Storage ====== */
$photoUrl = null;

if (isset($_FILES['selfie']) && $_FILES['selfie']['error'] === UPLOAD_ERR_OK) {
    $selfie = $_FILES['selfie'];

    if ($selfie['size'] > 5 * 1024 * 1024) {
        fail(400, 'File terlalu besar');
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $selfie['tmp_name']);
    finfo_close($finfo);

    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($allowed[$mime])) {
        fail(400, 'Format harus JPG/PNG/WEBP (terdeteksi: ' . $mime . ')');
    }

    $supabaseUrl = getenv('SUPABASE_URL');
    $secretKey   = getenv('SUPABASE_SECRET_KEY');

    if (!$supabaseUrl || !$secretKey) {
        fail(500, 'Env Supabase belum diatur');
    }

    $filename = 'trx_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    $fileContent = file_get_contents($selfie['tmp_name']);

    $uploadUrl = rtrim($supabaseUrl, '/') . '/storage/v1/object/transaction-photos/' . $filename;

    $ch = curl_init($uploadUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $fileContent,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . $secretKey,
            'Authorization: Bearer ' . $secretKey,
            'Content-Type: ' . $mime,
            'x-upsert: false',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode < 200 || $httpCode >= 300) {
        fail(500, 'Gagal upload foto (HTTP ' . $httpCode . '): ' . $response);
    }

    $photoUrl = rtrim($supabaseUrl, '/') . '/storage/v1/object/public/transaction-photos/' . $filename;
}

/* ====== Simpan metadata ke Supabase ====== */
$mapsUrl = "https://www.google.com/maps?q={$lat},{$lng}";

$sql = "INSERT INTO transactions 
    (user_name, amount, status, latitude, longitude, address, photo_path)
    VALUES ($1, $2, 'confirmed', $3, $4, $5, $6)
    RETURNING id";

$result = pg_query_params($conn, $sql, [
    'Guest',
    0,
    $lat,
    $lng,
    null,
    $photoUrl,
]);

if (!$result) {
    fail(500, 'Gagal simpan ke database: ' . pg_last_error($conn));
}

$row = pg_fetch_assoc($result);
$id = $row['id'];

echo json_encode([
    'ok'      => true,
    'message' => 'Data berhasil disimpan!',
    'id'      => (int)$id,
    'photo'   => $photoUrl,
    'maps'    => $mapsUrl,
]);