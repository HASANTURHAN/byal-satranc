<?php
// api/update_score.php - Mac sonucu guncelleme API
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Sadece POST kabul et
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Sadece POST istegi kabul edilir.']);
    exit;
}

// Admin kontrolu
if (!is_admin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Yetkiniz yok. Lutfen giris yapin.']);
    exit;
}

// CSRF kontrolu
$csrfToken = $_POST['csrf_token'] ?? '';
if (empty($csrfToken) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Guvenlik dogrulamasi basarisiz. Sayfayi yenileyip tekrar deneyin.']);
    exit;
}

// Parametreleri al
$pairingId = isset($_POST['pairing_id']) ? (int)$_POST['pairing_id'] : 0;
$result = trim($_POST['result'] ?? '');

// Validasyon
if ($pairingId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Gecersiz eslestirme ID.']);
    exit;
}

$validResults = ['1-0', '0-1', '1/2-1/2'];
if (!in_array($result, $validResults)) {
    echo json_encode(['success' => false, 'message' => 'Gecersiz sonuc. 1-0, 0-1 veya 1/2-1/2 secin.']);
    exit;
}

// Eslestirmeyi bul
$stmt = $pdo->prepare("SELECT * FROM pairings WHERE id = ?");
$stmt->execute([$pairingId]);
$pairing = $stmt->fetch();

if (!$pairing) {
    echo json_encode(['success' => false, 'message' => 'Eslestirme bulunamadi.']);
    exit;
}

$roundNo = $pairing['round'];
$tableNo = $pairing['table_no'];
$whitePlayerId = $pairing['white_player_id'];
$blackPlayerId = $pairing['black_player_id'];

// Puanlari hesapla
$whitePoints = 0;
$blackPoints = 0;
switch ($result) {
    case '1-0':
        $whitePoints = 1;
        $blackPoints = 0;
        break;
    case '0-1':
        $whitePoints = 0;
        $blackPoints = 1;
        break;
    case '1/2-1/2':
        $whitePoints = 0.5;
        $blackPoints = 0.5;
        break;
}

// Fotograf yukleme islemi
$uploadDir = __DIR__ . '/../uploads/match_photos/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$maxFileSize = 5 * 1024 * 1024; // 5MB
$allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
$allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];

$whitePhotoPath = $pairing['white_photo']; // mevcut degeri koru
$blackPhotoPath = $pairing['black_photo']; // mevcut degeri koru

// Beyaz fotograf yukleme
if (isset($_FILES['white_photo']) && $_FILES['white_photo']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['white_photo'];

    // Boyut kontrolu
    if ($file['size'] > $maxFileSize) {
        echo json_encode(['success' => false, 'message' => 'Beyaz oyuncu fotografi 5MB\'dan buyuk olamaz.']);
        exit;
    }

    // Tip kontrolu
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    if (!in_array($mimeType, $allowedTypes)) {
        echo json_encode(['success' => false, 'message' => 'Beyaz oyuncu fotografi sadece JPG, PNG veya WebP olabilir.']);
        exit;
    }

    // Uzanti belirle
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExtensions)) {
        $extMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $ext = $extMap[$mimeType] ?? 'jpg';
    }

    $filename = "round_{$roundNo}_table_{$tableNo}_white.{$ext}";
    $targetPath = $uploadDir . $filename;

    // Eski dosyayi sil (farkli uzantida olabilir)
    foreach ($allowedExtensions as $oldExt) {
        $oldFile = $uploadDir . "round_{$roundNo}_table_{$tableNo}_white.{$oldExt}";
        if (file_exists($oldFile) && $oldFile !== $targetPath) {
            unlink($oldFile);
        }
    }

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        $whitePhotoPath = "uploads/match_photos/{$filename}";
    } else {
        echo json_encode(['success' => false, 'message' => 'Beyaz oyuncu fotografi yuklenemedi.']);
        exit;
    }
}

// Siyah fotograf yukleme
if (isset($_FILES['black_photo']) && $_FILES['black_photo']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['black_photo'];

    // Boyut kontrolu
    if ($file['size'] > $maxFileSize) {
        echo json_encode(['success' => false, 'message' => 'Siyah oyuncu fotografi 5MB\'dan buyuk olamaz.']);
        exit;
    }

    // Tip kontrolu
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    if (!in_array($mimeType, $allowedTypes)) {
        echo json_encode(['success' => false, 'message' => 'Siyah oyuncu fotografi sadece JPG, PNG veya WebP olabilir.']);
        exit;
    }

    // Uzanti belirle
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExtensions)) {
        $extMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $ext = $extMap[$mimeType] ?? 'jpg';
    }

    $filename = "round_{$roundNo}_table_{$tableNo}_black.{$ext}";
    $targetPath = $uploadDir . $filename;

    // Eski dosyayi sil
    foreach ($allowedExtensions as $oldExt) {
        $oldFile = $uploadDir . "round_{$roundNo}_table_{$tableNo}_black.{$oldExt}";
        if (file_exists($oldFile) && $oldFile !== $targetPath) {
            unlink($oldFile);
        }
    }

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        $blackPhotoPath = "uploads/match_photos/{$filename}";
    } else {
        echo json_encode(['success' => false, 'message' => 'Siyah oyuncu fotografi yuklenemedi.']);
        exit;
    }
}

// Veritabani guncelleme (transaction ile)
try {
    $pdo->beginTransaction();

    // Pairings tablosunu guncelle
    $updateStmt = $pdo->prepare("
        UPDATE pairings
        SET result = ?,
            white_points = ?,
            black_points = ?,
            white_photo = ?,
            black_photo = ?,
            played_at = datetime('now', 'localtime')
        WHERE id = ?
    ");
    $updateStmt->execute([
        $result,
        $whitePoints,
        $blackPoints,
        $whitePhotoPath,
        $blackPhotoPath,
        $pairingId
    ]);

    // Beyaz oyuncunun toplam puanini yeniden hesapla
    $calcWhite = $pdo->prepare("
        SELECT COALESCE(SUM(white_points), 0)
        FROM pairings
        WHERE white_player_id = ? AND result IS NOT NULL
    ");
    $calcWhite->execute([$whitePlayerId]);
    $whiteTotalFromWhite = (float)$calcWhite->fetchColumn();

    $calcWhiteAsBlack = $pdo->prepare("
        SELECT COALESCE(SUM(black_points), 0)
        FROM pairings
        WHERE black_player_id = ? AND result IS NOT NULL
    ");
    $calcWhiteAsBlack->execute([$whitePlayerId]);
    $whiteTotalFromBlack = (float)$calcWhiteAsBlack->fetchColumn();

    $whiteTotalPoints = $whiteTotalFromWhite + $whiteTotalFromBlack;

    $updateWhitePlayer = $pdo->prepare("UPDATE players SET total_points = ? WHERE id = ?");
    $updateWhitePlayer->execute([$whiteTotalPoints, $whitePlayerId]);

    // Siyah oyuncunun toplam puanini yeniden hesapla
    $calcBlack = $pdo->prepare("
        SELECT COALESCE(SUM(white_points), 0)
        FROM pairings
        WHERE white_player_id = ? AND result IS NOT NULL
    ");
    $calcBlack->execute([$blackPlayerId]);
    $blackTotalFromWhite = (float)$calcBlack->fetchColumn();

    $calcBlackAsBlack = $pdo->prepare("
        SELECT COALESCE(SUM(black_points), 0)
        FROM pairings
        WHERE black_player_id = ? AND result IS NOT NULL
    ");
    $calcBlackAsBlack->execute([$blackPlayerId]);
    $blackTotalFromBlack = (float)$calcBlackAsBlack->fetchColumn();

    $blackTotalPoints = $blackTotalFromWhite + $blackTotalFromBlack;

    $updateBlackPlayer = $pdo->prepare("UPDATE players SET total_points = ? WHERE id = ?");
    $updateBlackPlayer->execute([$blackTotalPoints, $blackPlayerId]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Sonuc basariyla kaydedildi.',
        'white_points' => $whiteTotalPoints,
        'black_points' => $blackTotalPoints,
        'result' => $result
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Score update error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Veritabani hatasi olustu. Lutfen tekrar deneyin.'
    ]);
}
