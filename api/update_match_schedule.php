<?php
// api/update_match_schedule.php - Maç bazlı takvim güncelleme
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

$matchDate = trim($_POST['match_date'] ?? '');
$matchTime = trim($_POST['match_time'] ?? '');

if (mb_strlen($matchDate) > 100 || mb_strlen($matchTime) > 50) {
    echo json_encode(['success' => false, 'message' => 'Girdi çok uzun.']);
    exit;
}

// Tek maç güncelleme
$pairingId = isset($_POST['pairing_id']) ? (int)$_POST['pairing_id'] : 0;
if ($pairingId > 0) {
    try {
        $stmt = $pdo->prepare("UPDATE pairings SET match_date = ?, match_time = ? WHERE id = ?");
        $stmt->execute([$matchDate, $matchTime, $pairingId]);

        $info = $pdo->prepare("SELECT table_no FROM pairings WHERE id = ?");
        $info->execute([$pairingId]);
        $tableNo = $info->fetchColumn();

        echo json_encode([
            'success' => true,
            'message' => "Masa {$tableNo} takvimi güncellendi.",
            'table_no' => $tableNo
        ]);
    } catch (Exception $e) {
        error_log("Match schedule update error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Veritabanı hatası oluştu.']);
    }
    exit;
}

// Toplu güncelleme (round + opsiyonel table_no listesi)
$round = isset($_POST['round']) ? (int)$_POST['round'] : 0;
if ($round <= 0) {
    echo json_encode(['success' => false, 'message' => 'Geçersiz tur veya maç ID.']);
    exit;
}

$tablesJson = $_POST['tables'] ?? '';
$targetTables = $tablesJson ? json_decode($tablesJson, true) : null;

try {
    if ($targetTables && is_array($targetTables)) {
        $placeholders = implode(',', array_fill(0, count($targetTables), '?'));
        $params = [];
        if ($matchDate !== '') {
            $sql = "UPDATE pairings SET match_date = ?";
            $params[] = $matchDate;
        }
        if ($matchTime !== '') {
            $sql = ($matchDate !== '' ? $sql . ", match_time = ?" : "UPDATE pairings SET match_time = ?");
            $params[] = $matchTime;
        }
        if (empty($params)) {
            echo json_encode(['success' => false, 'message' => 'Tarih veya saat girin.']);
            exit;
        }
        $sql .= " WHERE round = ? AND table_no IN ($placeholders)";
        $params[] = $round;
        $params = array_merge($params, $targetTables);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $count = $stmt->rowCount();
        echo json_encode(['success' => true, 'message' => "{$count} maçın takvimi güncellendi."]);
    } else {
        // Tüm maçlar
        $params = [];
        if ($matchDate !== '' && $matchTime !== '') {
            $sql = "UPDATE pairings SET match_date = ?, match_time = ? WHERE round = ?";
            $params = [$matchDate, $matchTime, $round];
        } elseif ($matchDate !== '') {
            $sql = "UPDATE pairings SET match_date = ? WHERE round = ?";
            $params = [$matchDate, $round];
        } else {
            $sql = "UPDATE pairings SET match_time = ? WHERE round = ?";
            $params = [$matchTime, $round];
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $count = $stmt->rowCount();
        echo json_encode(['success' => true, 'message' => "Tüm {$count} maçın takvimi güncellendi."]);
    }
} catch (Exception $e) {
    error_log("Bulk schedule update error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Veritabanı hatası oluştu.']);
}
