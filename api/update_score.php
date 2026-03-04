<?php
// api/update_score.php - Maç sonucu güncelleme API
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Sadece POST kabul et
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Sadece POST isteği kabul edilir.']);
    exit;
}

// Admin kontrolü
if (!is_admin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Yetkiniz yok. Lütfen giriş yapın.']);
    exit;
}

// CSRF kontrolü
$csrfToken = $_POST['csrf_token'] ?? '';
if (empty($csrfToken) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Güvenlik doğrulaması başarısız. Sayfayı yenileyip tekrar deneyin.']);
    exit;
}

// Parametreleri al
$pairingId = isset($_POST['pairing_id']) ? (int)$_POST['pairing_id'] : 0;
$result = trim($_POST['result'] ?? '');

// Validasyon
if ($pairingId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Geçersiz eşleştirme ID.']);
    exit;
}

$validResults = ['1-0', '0-1', '1/2-1/2'];
if (!in_array($result, $validResults)) {
    echo json_encode(['success' => false, 'message' => 'Geçersiz sonuç. 1-0, 0-1 veya 1/2-1/2 seçin.']);
    exit;
}

// Eşleştirmeyi bul
$stmt = $pdo->prepare("SELECT * FROM pairings WHERE id = ?");
$stmt->execute([$pairingId]);
$pairing = $stmt->fetch();

if (!$pairing) {
    echo json_encode(['success' => false, 'message' => 'Eşleştirme bulunamadı.']);
    exit;
}

$roundNo = $pairing['round'];
$tableNo = $pairing['table_no'];
$whitePlayerId = $pairing['white_player_id'];
$blackPlayerId = $pairing['black_player_id'];

// Puanları hesapla
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

// Fotoğraf yükleme işlemi
$uploadDir = __DIR__ . '/../uploads/match_photos/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$maxFileSize = 5 * 1024 * 1024; // 5MB
$allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
$allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];

$whitePhotoPath = $pairing['white_photo']; // mevcut değeri koru
$blackPhotoPath = $pairing['black_photo']; // mevcut değeri koru

// Beyaz fotoğraf yükleme
if (isset($_FILES['white_photo']) && $_FILES['white_photo']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['white_photo'];

    // Boyut kontrolü
    if ($file['size'] > $maxFileSize) {
        echo json_encode(['success' => false, 'message' => 'Beyaz oyuncu fotoğrafı 5MB\'dan büyük olamaz.']);
        exit;
    }

    // Tip kontrolü
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    if (!in_array($mimeType, $allowedTypes)) {
        echo json_encode(['success' => false, 'message' => 'Beyaz oyuncu fotoğrafı sadece JPG, PNG veya WebP olabilir.']);
        exit;
    }

    // Uzantı belirle
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExtensions)) {
        $extMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $ext = $extMap[$mimeType] ?? 'jpg';
    }

    $filename = "round_{$roundNo}_table_{$tableNo}_white.{$ext}";
    $targetPath = $uploadDir . $filename;

    // Eski dosyayı sil (farkli uzantida olabilir)
    foreach ($allowedExtensions as $oldExt) {
        $oldFile = $uploadDir . "round_{$roundNo}_table_{$tableNo}_white.{$oldExt}";
        if (file_exists($oldFile) && $oldFile !== $targetPath) {
            unlink($oldFile);
        }
    }

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        $whitePhotoPath = "uploads/match_photos/{$filename}";
    } else {
        echo json_encode(['success' => false, 'message' => 'Beyaz oyuncu fotoğrafı yüklenemedi.']);
        exit;
    }
}

// Siyah fotoğraf yükleme
if (isset($_FILES['black_photo']) && $_FILES['black_photo']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['black_photo'];

    // Boyut kontrolü
    if ($file['size'] > $maxFileSize) {
        echo json_encode(['success' => false, 'message' => 'Siyah oyuncu fotoğrafı 5MB\'dan büyük olamaz.']);
        exit;
    }

    // Tip kontrolü
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    if (!in_array($mimeType, $allowedTypes)) {
        echo json_encode(['success' => false, 'message' => 'Siyah oyuncu fotoğrafı sadece JPG, PNG veya WebP olabilir.']);
        exit;
    }

    // Uzantı belirle
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExtensions)) {
        $extMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $ext = $extMap[$mimeType] ?? 'jpg';
    }

    $filename = "round_{$roundNo}_table_{$tableNo}_black.{$ext}";
    $targetPath = $uploadDir . $filename;

    // Eski dosyayı sil
    foreach ($allowedExtensions as $oldExt) {
        $oldFile = $uploadDir . "round_{$roundNo}_table_{$tableNo}_black.{$oldExt}";
        if (file_exists($oldFile) && $oldFile !== $targetPath) {
            unlink($oldFile);
        }
    }

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        $blackPhotoPath = "uploads/match_photos/{$filename}";
    } else {
        echo json_encode(['success' => false, 'message' => 'Siyah oyuncu fotoğrafı yüklenemedi.']);
        exit;
    }
}

// Veritabanı güncelleme (transaction ile)
try {
    $pdo->beginTransaction();

    // Pairings tablosunu güncelle
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

    // Beyaz oyuncunun toplam puanını yeniden hesapla
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

    // Siyah oyuncunun toplam puanını yeniden hesapla
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
        'message' => 'Sonuç başarıyla kaydedildi.',
        'white_points' => $whiteTotalPoints,
        'black_points' => $blackTotalPoints,
        'result' => $result
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Score update error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Veritabanı hatası oluştu. Lütfen tekrar deneyin.'
    ]);
}
