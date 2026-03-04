<?php
// api/update_schedule.php - Tur takvim bilgisi güncelleme
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Sadece POST kabul edilir.']);
    exit;
}

if (!is_admin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Yetkiniz yok.']);
    exit;
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (empty($csrfToken) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Güvenlik doğrulaması başarısız.']);
    exit;
}

$roundNumber = isset($_POST['round_number']) ? (int)$_POST['round_number'] : 0;
if ($roundNumber <= 0) {
    echo json_encode(['success' => false, 'message' => 'Geçersiz tur numarası.']);
    exit;
}

$matchDate = trim($_POST['match_date'] ?? '');
$matchTime = trim($_POST['match_time'] ?? '');
$matchPeriod = trim($_POST['match_period'] ?? '');
$matchLocation = trim($_POST['match_location'] ?? '');

// Input uzunluk kontrolü
if (mb_strlen($matchDate) > 100 || mb_strlen($matchTime) > 50 || mb_strlen($matchPeriod) > 100 || mb_strlen($matchLocation) > 200) {
    echo json_encode(['success' => false, 'message' => 'Girdi çok uzun.']);
    exit;
}

try {
    // Round kaydı var mı kontrol et
    $stmt = $pdo->prepare("SELECT id FROM rounds WHERE round_number = ?");
    $stmt->execute([$roundNumber]);
    $exists = $stmt->fetch();

    if ($exists) {
        $stmt = $pdo->prepare("UPDATE rounds SET match_date = ?, match_time = ?, match_period = ?, match_location = ? WHERE round_number = ?");
        $stmt->execute([$matchDate, $matchTime, $matchPeriod, $matchLocation, $roundNumber]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO rounds (round_number, is_active, match_date, match_time, match_period, match_location) VALUES (?, 1, ?, ?, ?, ?)");
        $stmt->execute([$roundNumber, $matchDate, $matchTime, $matchPeriod, $matchLocation]);
    }

    echo json_encode([
        'success' => true,
        'message' => "Tur {$roundNumber} takvim bilgileri güncellendi.",
        'data' => [
            'match_date' => $matchDate,
            'match_time' => $matchTime,
            'match_period' => $matchPeriod,
            'match_location' => $matchLocation,
        ]
    ]);
} catch (Exception $e) {
    error_log("Schedule update error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Veritabanı hatası oluştu.']);
}
