<?php
/**
 * import_data.php - CSV'den veri aktarım scripti
 * Kullanım: php import_data.php
 */

require_once __DIR__ . '/db.php';

echo "=== BYAL Satranç Turnuvası - Veri İçe Aktarım ===\n\n";

// Varsayılan ayarları güncelle
$defaults = [
    'tournament_name' => '2025-2026 Okul Satranç Turnuvası',
    'tournament_status' => 'turnuva_basladi',
    'total_rounds' => '6',
    'total_players' => '68',
    'deadline' => '4 Mart 2026',
    'tournament_system' => 'İsviçre Sistemi (6 Tur)',
];
foreach ($defaults as $key => $value) {
    set_setting($key, $value);
}
echo "[OK] Turnuva ayarları güncellendi.\n";

// ==========================================
// 1. OYUNCULARI İÇE AKTAR
// ==========================================
$players_file = __DIR__ . '/tournament_players.csv';
if (!file_exists($players_file)) {
    die("[HATA] tournament_players.csv bulunamadı!\n");
}

$handle = fopen($players_file, 'r');
$header = fgetcsv($handle); // başlık satırı
$player_count = 0;
$player_updated = 0;

$stmt_check = $pdo->prepare("SELECT id FROM players WHERE school_no = ?");
$stmt_insert = $pdo->prepare("INSERT INTO players (id, name, sinif, school_no, is_seed, phone, total_points) VALUES (?, ?, ?, ?, ?, ?, 0)");
$stmt_update = $pdo->prepare("UPDATE players SET name=?, sinif=?, is_seed=?, phone=? WHERE school_no=?");

$pdo->beginTransaction();
while (($row = fgetcsv($handle)) !== false) {
    if (count($row) < 6) continue;

    $id = intval($row[0]);
    $name = trim($row[1]);
    $sinif = trim($row[2]);
    $school_no = trim($row[3]);
    $is_seed = intval($row[4]);
    $phone = trim($row[5]);

    $stmt_check->execute([$school_no]);
    $existing = $stmt_check->fetch();

    if ($existing) {
        $stmt_update->execute([$name, $sinif, $is_seed, $phone, $school_no]);
        $player_updated++;
    } else {
        $stmt_insert->execute([$id, $name, $sinif, $school_no, $is_seed, $phone]);
        $player_count++;
    }
}
$pdo->commit();
fclose($handle);

echo "[OK] Oyuncular: {$player_count} yeni eklendi, {$player_updated} güncellendi.\n";

// ==========================================
// 2. 1. TUR EŞLEŞTİRMELERİNİ İÇE AKTAR
// ==========================================
$pairings_file = __DIR__ . '/tournament_round1_pairings.csv';
if (!file_exists($pairings_file)) {
    die("[HATA] tournament_round1_pairings.csv bulunamadı!\n");
}

// Önce round 1 varsa sil
$pdo->exec("DELETE FROM pairings WHERE round = 1");

// Round 1 kaydı oluştur
$stmt_round_check = $pdo->prepare("SELECT id FROM rounds WHERE round_number = 1");
$stmt_round_check->execute();
if (!$stmt_round_check->fetch()) {
    $pdo->exec("INSERT INTO rounds (round_number, is_active) VALUES (1, 1)");
}

$handle = fopen($pairings_file, 'r');
$header = fgetcsv($handle); // başlık satırı
$pairing_count = 0;

$stmt_pair = $pdo->prepare("INSERT INTO pairings (round, table_no, white_player_id, black_player_id, is_seed_table, result, white_points, black_points) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

$pdo->beginTransaction();
while (($row = fgetcsv($handle)) !== false) {
    if (count($row) < 14) continue;

    $round = intval($row[0]);
    $table_no = intval($row[1]);
    $white_id = intval($row[2]);
    $black_id = intval($row[6]);
    $is_seed_table = intval($row[10]);
    $result = !empty(trim($row[11])) ? trim($row[11]) : null;
    $white_points = !empty(trim($row[12])) ? floatval($row[12]) : null;
    $black_points = !empty(trim($row[13])) ? floatval($row[13]) : null;

    $stmt_pair->execute([$round, $table_no, $white_id, $black_id, $is_seed_table, $result, $white_points, $black_points]);
    $pairing_count++;
}
$pdo->commit();
fclose($handle);

echo "[OK] 1. Tur Eşleştirmeleri: {$pairing_count} masa eklendi.\n";

// Özet
echo "\n=== ÖZET ===\n";
$total_players = $pdo->query("SELECT COUNT(*) FROM players")->fetchColumn();
$seed_players = $pdo->query("SELECT COUNT(*) FROM players WHERE is_seed = 1")->fetchColumn();
$total_pairings = $pdo->query("SELECT COUNT(*) FROM pairings WHERE round = 1")->fetchColumn();
echo "Toplam Oyuncu: {$total_players}\n";
echo "Seri Başı: {$seed_players}\n";
echo "1. Tur Masa Sayısı: {$total_pairings}\n";
echo "\n[TAMAMLANDI] Veri aktarımı başarılı!\n";
