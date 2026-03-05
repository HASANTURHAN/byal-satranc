<?php
require_once 'db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!is_admin()) { header("Location: login.php"); exit(); }

// Turnuva istatistikleri
$totalPlayers = (int)$pdo->query("SELECT COUNT(*) FROM players")->fetchColumn();
$totalRounds = (int)$pdo->query("SELECT COUNT(*) FROM rounds")->fetchColumn();
$maxRound = (int)$pdo->query("SELECT COALESCE(MAX(round_number), 0) FROM rounds")->fetchColumn();

// Aktif tur (en yüksek aktif tur)
$activeRoundStmt = $pdo->query("SELECT round_number FROM rounds WHERE is_active = 1 ORDER BY round_number DESC LIMIT 1");
$activeRound = $activeRoundStmt->fetchColumn();
if (!$activeRound && $maxRound > 0) {
    $activeRound = $maxRound;
}

// Seçilen tur (GET parametresi veya aktif tur)
$selectedRound = isset($_GET['round']) ? (int)$_GET['round'] : ($activeRound ?: $maxRound);
if ($selectedRound < 1) $selectedRound = 1;

// Tüm turların listesi
$allRounds = $pdo->query("SELECT round_number, is_active FROM rounds ORDER BY round_number ASC")->fetchAll();

// Seçilen turdaki toplam ve tamamlanmış maç sayısı
$totalMatches = 0;
$completedMatches = 0;
if ($selectedRound > 0) {
    $totalMatches = (int)$pdo->prepare("SELECT COUNT(*) FROM pairings WHERE round = ?")->execute([$selectedRound]) ?
        $pdo->prepare("SELECT COUNT(*) FROM pairings WHERE round = ?") : 0;
    $stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM pairings WHERE round = ?");
    $stmtTotal->execute([$selectedRound]);
    $totalMatches = (int)$stmtTotal->fetchColumn();

    $stmtCompleted = $pdo->prepare("SELECT COUNT(*) FROM pairings WHERE round = ? AND result IS NOT NULL");
    $stmtCompleted->execute([$selectedRound]);
    $completedMatches = (int)$stmtCompleted->fetchColumn();
}

// Tüm turlar toplam istatistik
$totalAllMatches = (int)$pdo->query("SELECT COUNT(*) FROM pairings")->fetchColumn();
$totalAllCompleted = (int)$pdo->query("SELECT COUNT(*) FROM pairings WHERE result IS NOT NULL")->fetchColumn();

// Seçilen turdaki eşleşmeleri çek
$pairings = [];
if ($selectedRound > 0) {
    $stmt = $pdo->prepare("
        SELECT p.id, p.round, p.table_no, p.white_player_id, p.black_player_id,
               p.is_seed_table, p.result, p.white_points, p.black_points,
               p.white_photo, p.black_photo, p.played_at,
               p.match_date, p.match_time,
               w.name AS white_name, w.sinif AS white_sinif,
               b.name AS black_name, b.sinif AS black_sinif
        FROM pairings p
        LEFT JOIN players w ON p.white_player_id = w.id
        LEFT JOIN players b ON p.black_player_id = b.id
        WHERE p.round = ?
        ORDER BY p.table_no ASC
    ");
    $stmt->execute([$selectedRound]);
    $pairings = $stmt->fetchAll();
}

$allRoundComplete = ($totalMatches > 0 && $completedMatches === $totalMatches);

$csrfToken = csrf_token();

include 'header.php';
?>

<!-- Page Header -->
<div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
    <div>
        <h2 class="text-2xl font-bold text-gray-900">Skor Girişi Paneli</h2>
        <p class="text-sm text-gray-500 mt-1">Maç sonuçlarını girin ve fotoğrafları yükleyin.</p>
    </div>
    <div class="flex gap-2">
        <a href="standings.php" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 text-sm font-medium text-gray-700 rounded-xl hover:bg-gray-50 transition">
            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
            Puan Durumu
        </a>
    </div>
</div>

<!-- Genel Bilgi toast alanı -->
<div id="toast-container" class="fixed top-20 right-4 z-50 space-y-2"></div>

<!-- Turnuva Özeti -->
<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8">
    <div class="card p-4 text-center">
        <div class="text-2xl font-bold text-gray-900"><?= $totalPlayers ?></div>
        <div class="text-xs text-gray-500 mt-1">Toplam Oyuncu</div>
    </div>
    <div class="card p-4 text-center">
        <div class="text-2xl font-bold text-blue-600"><?= $totalRounds ?></div>
        <div class="text-xs text-gray-500 mt-1">Toplam Tur</div>
    </div>
    <div class="card p-4 text-center">
        <div class="text-2xl font-bold text-green-600"><?= $totalAllCompleted ?></div>
        <div class="text-xs text-gray-500 mt-1">Tamamlanan Maç</div>
    </div>
    <div class="card p-4 text-center">
        <div class="text-2xl font-bold text-amber-600"><?= $totalAllMatches - $totalAllCompleted ?></div>
        <div class="text-xs text-gray-500 mt-1">Bekleyen Maç</div>
    </div>
</div>

<!-- Tur Secici -->
<div class="card p-4 mb-6">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div class="flex items-center gap-3 flex-wrap">
            <span class="text-sm font-semibold text-gray-700">Tur Seç:</span>
            <?php if (empty($allRounds)): ?>
                <span class="text-sm text-gray-400">Henüz tur oluşturulmadı.</span>
            <?php else: ?>
                <?php foreach ($allRounds as $r): ?>
                    <a href="admin.php?round=<?= $r['round_number'] ?>"
                       class="inline-flex items-center px-3 py-1.5 rounded-lg text-sm font-medium transition
                              <?= $selectedRound == $r['round_number']
                                  ? 'bg-gray-900 text-white shadow-sm'
                                  : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">
                        <?= $r['round_number'] ?>. Tur
                        <?php if ($r['is_active']): ?>
                            <span class="ml-1.5 w-2 h-2 rounded-full bg-green-400 inline-block"></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if ($selectedRound > 0 && $totalMatches > 0): ?>
        <div class="flex items-center gap-2">
            <div class="text-sm text-gray-500">
                <span class="font-semibold text-gray-900"><?= $completedMatches ?></span> / <?= $totalMatches ?> maç tamamlandı
            </div>
            <div class="w-24 h-2 bg-gray-200 rounded-full overflow-hidden">
                <div class="h-full rounded-full transition-all duration-500
                    <?= $allRoundComplete ? 'bg-green-500' : 'bg-amber-500' ?>"
                     style="width: <?= $totalMatches > 0 ? round($completedMatches / $totalMatches * 100) : 0 ?>%"></div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Toplu Maç Takvimi Atama -->
<?php if ($selectedRound > 0 && !empty($pairings)): ?>
<div class="card p-5 mb-6 border border-blue-200/60 bg-blue-50/30">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-sm font-bold text-gray-700 flex items-center gap-2">
            <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            Toplu Maç Takvimi Ata
            <span class="text-xs font-normal text-blue-500 bg-blue-100 px-2 py-0.5 rounded-full">Seçili maçlara uygula</span>
        </h3>
        <span id="bulk-sched-msg" class="text-xs font-medium hidden"></span>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-3">
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Tarih</label>
            <input type="date" id="bulk_date"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Ders Saati</label>
            <select id="bulk_time"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white">
                <option value="">-- Ders Seçin --</option>
                <option value="1. Ders">1. Ders</option>
                <option value="2. Ders">2. Ders</option>
                <option value="3. Ders">3. Ders</option>
                <option value="4. Ders">4. Ders</option>
                <option value="5. Ders">5. Ders</option>
                <option value="6. Ders">6. Ders</option>
                <option value="7. Ders">7. Ders</option>
                <option value="8. Ders">8. Ders</option>
            </select>
        </div>
        <div class="flex items-end gap-2">
            <div class="flex-1">
                <label class="block text-xs font-medium text-gray-500 mb-1">Masalar</label>
                <input type="text" id="bulk_tables" placeholder="Örn: 1-5 veya 1,3,5,7"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white">
            </div>
            <button type="button" onclick="bulkAssignSchedule()" id="bulk-assign-btn"
                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition whitespace-nowrap">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                Uygula
            </button>
        </div>
    </div>
    <p class="text-xs text-gray-400">Masa aralığı: "1-5" = Masa 1'den 5'e. Tekli: "1,3,5,7". Boş bırakırsanız tüm maçlara uygulanır.</p>
</div>
<?php endif; ?>

<!-- Eşleşme Listesi ve Skor Girişi -->
<?php if (!empty($pairings)): ?>
<div class="space-y-3 mb-8">
    <?php foreach ($pairings as $pairing): ?>
    <div class="card overflow-hidden pairing-row" id="pairing-<?= $pairing['id'] ?>"
         data-pairing-id="<?= $pairing['id'] ?>">
        <div class="px-4 py-2 border-b border-gray-100 bg-gray-50/80">
            <div class="flex items-center gap-2">
                <span class="text-xs font-bold text-gray-400">MASA <?= $pairing['table_no'] ?></span>
                <?php if ($pairing['is_seed_table']): ?>
                    <span class="seed-badge text-xs px-1.5 py-0.5 rounded-full font-medium">Seri Başı</span>
                <?php endif; ?>
                <?php if ($pairing['result']): ?>
                    <span class="ml-auto inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-green-100 text-green-700">Tamamlandı</span>
                <?php else: ?>
                    <span class="ml-auto inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-700">Bekliyor</span>
                <?php endif; ?>
            </div>
            <!-- Maç Tarih/Saat -->
            <div class="flex items-center gap-2 mt-2">
                <svg class="w-3.5 h-3.5 text-blue-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                <input type="date" id="mdate_<?= $pairing['id'] ?>" value="<?= htmlspecialchars($pairing['match_date'] ?? '') ?>"
                       class="flex-1 border border-gray-200 rounded px-2 py-1 text-xs focus:ring-1 focus:ring-blue-400 focus:border-blue-400 bg-white">
                <svg class="w-3.5 h-3.5 text-violet-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <?php $currentTime = htmlspecialchars($pairing['match_time'] ?? ''); ?>
                <select id="mtime_<?= $pairing['id'] ?>"
                        class="w-28 border border-gray-200 rounded px-2 py-1 text-xs focus:ring-1 focus:ring-blue-400 focus:border-blue-400 bg-white">
                    <option value="">Ders</option>
                    <?php for ($d = 1; $d <= 8; $d++): $dVal = $d . '. Ders'; ?>
                    <option value="<?= $dVal ?>" <?= $currentTime === $dVal ? 'selected' : '' ?>><?= $dVal ?></option>
                    <?php endfor; ?>
                </select>
                <button type="button" onclick="saveMatchSchedule(<?= $pairing['id'] ?>)"
                        class="text-blue-500 hover:text-blue-700 transition p-1" title="Takvimi Kaydet">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                </button>
            </div>
        </div>

        <div class="p-4">
            <!-- Oyuncular ve Sonuc -->
            <div class="flex flex-col lg:flex-row items-stretch lg:items-center gap-4">
                <!-- Beyaz Oyuncu -->
                <div class="flex-1 flex items-center gap-3 p-3 rounded-xl border-2 transition
                    <?= $pairing['result'] === '1-0' ? 'border-green-300 bg-green-50' : 'border-gray-100 bg-white' ?>">
                    <div class="w-10 h-10 rounded-full bg-white border-2 border-gray-200 flex items-center justify-center flex-shrink-0 shadow-sm">
                        <span class="text-lg">&#9812;</span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-semibold text-gray-900 truncate"><?= htmlspecialchars($pairing['white_name'] ?? 'Bilinmiyor') ?></div>
                        <div class="text-xs text-gray-500"><?= htmlspecialchars($pairing['white_sinif'] ?? '') ?></div>
                    </div>
                    <?php if ($pairing['result'] === '1-0'): ?>
                        <span class="text-green-600 font-bold text-sm">1</span>
                    <?php elseif ($pairing['result'] === '0-1'): ?>
                        <span class="text-red-500 font-bold text-sm">0</span>
                    <?php elseif ($pairing['result'] === '1/2-1/2'): ?>
                        <span class="text-amber-600 font-bold text-sm">&frac12;</span>
                    <?php endif; ?>
                </div>

                <!-- VS / Sonuç Butonları -->
                <div class="flex flex-row lg:flex-col items-center justify-center gap-1.5 px-2">
                    <button type="button"
                            onclick="selectResult(<?= $pairing['id'] ?>, '1-0')"
                            class="result-btn result-btn-<?= $pairing['id'] ?> w-16 py-1.5 rounded-lg text-xs font-bold transition
                                   <?= $pairing['result'] === '1-0'
                                       ? 'bg-green-600 text-white shadow-md ring-2 ring-green-300'
                                       : 'bg-gray-100 text-gray-600 hover:bg-green-100 hover:text-green-700' ?>"
                            data-result="1-0">
                        1 - 0
                    </button>
                    <button type="button"
                            onclick="selectResult(<?= $pairing['id'] ?>, '1/2-1/2')"
                            class="result-btn result-btn-<?= $pairing['id'] ?> w-16 py-1.5 rounded-lg text-xs font-bold transition
                                   <?= $pairing['result'] === '1/2-1/2'
                                       ? 'bg-amber-500 text-white shadow-md ring-2 ring-amber-300'
                                       : 'bg-gray-100 text-gray-600 hover:bg-amber-100 hover:text-amber-700' ?>"
                            data-result="1/2-1/2">
                        &frac12; - &frac12;
                    </button>
                    <button type="button"
                            onclick="selectResult(<?= $pairing['id'] ?>, '0-1')"
                            class="result-btn result-btn-<?= $pairing['id'] ?> w-16 py-1.5 rounded-lg text-xs font-bold transition
                                   <?= $pairing['result'] === '0-1'
                                       ? 'bg-red-600 text-white shadow-md ring-2 ring-red-300'
                                       : 'bg-gray-100 text-gray-600 hover:bg-red-100 hover:text-red-700' ?>"
                            data-result="0-1">
                        0 - 1
                    </button>
                </div>

                <!-- Siyah Oyuncu -->
                <div class="flex-1 flex items-center gap-3 p-3 rounded-xl border-2 transition
                    <?= $pairing['result'] === '0-1' ? 'border-green-300 bg-green-50' : 'border-gray-100 bg-white' ?>">
                    <div class="w-10 h-10 rounded-full bg-gray-800 border-2 border-gray-600 flex items-center justify-center flex-shrink-0 shadow-sm">
                        <span class="text-lg text-white">&#9818;</span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-semibold text-gray-900 truncate"><?= htmlspecialchars($pairing['black_name'] ?? 'Bilinmiyor') ?></div>
                        <div class="text-xs text-gray-500"><?= htmlspecialchars($pairing['black_sinif'] ?? '') ?></div>
                    </div>
                    <?php if ($pairing['result'] === '0-1'): ?>
                        <span class="text-green-600 font-bold text-sm">1</span>
                    <?php elseif ($pairing['result'] === '1-0'): ?>
                        <span class="text-red-500 font-bold text-sm">0</span>
                    <?php elseif ($pairing['result'] === '1/2-1/2'): ?>
                        <span class="text-amber-600 font-bold text-sm">&frac12;</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Fotoğraf Yükleme ve Kaydet -->
            <div class="mt-4 flex flex-col sm:flex-row items-start sm:items-end gap-3">
                <div class="flex-1 grid grid-cols-1 sm:grid-cols-2 gap-3 w-full">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">
                            Beyaz Fotoğraf
                            <?php if ($pairing['white_photo']): ?>
                                <a href="<?= htmlspecialchars($pairing['white_photo']) ?>" target="_blank" class="text-blue-500 hover:underline ml-1">(mevcut)</a>
                            <?php endif; ?>
                        </label>
                        <input type="file" accept="image/jpeg,image/png,image/webp"
                               id="white_photo_<?= $pairing['id'] ?>"
                               class="block w-full text-xs text-gray-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-medium file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200 transition">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">
                            Siyah Fotoğraf
                            <?php if ($pairing['black_photo']): ?>
                                <a href="<?= htmlspecialchars($pairing['black_photo']) ?>" target="_blank" class="text-blue-500 hover:underline ml-1">(mevcut)</a>
                            <?php endif; ?>
                        </label>
                        <input type="file" accept="image/jpeg,image/png,image/webp"
                               id="black_photo_<?= $pairing['id'] ?>"
                               class="block w-full text-xs text-gray-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-medium file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200 transition">
                    </div>
                </div>
                <button type="button"
                        onclick="saveResult(<?= $pairing['id'] ?>)"
                        id="save-btn-<?= $pairing['id'] ?>"
                        class="save-btn flex-shrink-0 inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-semibold transition shadow-sm
                               bg-gray-900 text-white hover:bg-gray-800 active:scale-95">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    Kaydet
                </button>
            </div>

            <!-- Satır içi mesaj -->
            <div id="message-<?= $pairing['id'] ?>" class="mt-2 text-sm hidden"></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php elseif (empty($allRounds)): ?>
<div class="card flex items-center justify-center min-h-[300px]">
    <div class="text-center p-8">
        <div class="text-5xl mb-4">&#9812;</div>
        <h3 class="text-base font-semibold text-gray-900 mb-2">Henüz tur oluşturulmadı</h3>
        <p class="text-sm text-gray-500 mb-4">Turnuva başlatılmamış. Eşleştirme motorundan ilk turu oluşturun.</p>
        <p class="text-sm text-gray-500">İlk tur için import_data.php scriptini çalıştırın.</p>
    </div>
</div>
<?php else: ?>
<div class="card flex items-center justify-center min-h-[200px]">
    <div class="text-center p-8">
        <p class="text-sm text-gray-500">Bu turda eşleştirme bulunamadı.</p>
    </div>
</div>
<?php endif; ?>

<!-- Sonraki Turu Oluştur Butonu -->
<?php if ($allRoundComplete && $totalMatches > 0): ?>
<div class="card p-6 mb-8 border-2 border-green-200 bg-green-50/50">
    <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
        <div>
            <h3 class="text-lg font-bold text-gray-900">
                <?= $selectedRound ?>. Tur Tamamlandı!
            </h3>
            <p class="text-sm text-gray-600 mt-1">
                Tüm maçlar sonuçlandırıldı. Bir sonraki tur için eşleştirme yapabilirsiniz.
            </p>
        </div>
        <button type="button" onclick="generateNextRound()" id="next-round-btn"
           class="inline-flex items-center gap-2 px-6 py-3 bg-blue-600 text-white text-sm font-semibold rounded-xl hover:bg-blue-700 transition shadow-lg shadow-blue-600/25">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path></svg>
            Sonraki Turu Oluştur
        </button>
    </div>
</div>
<?php endif; ?>

<!-- Seçili sonuçlar state -->
<script>
const selectedResults = {};
const csrfToken = '<?= $csrfToken ?>';

// Mevcut sonuçları yükle
<?php foreach ($pairings as $p): ?>
<?php if ($p['result']): ?>
selectedResults[<?= $p['id'] ?>] = '<?= $p['result'] ?>';
<?php endif; ?>
<?php endforeach; ?>

function selectResult(pairingId, result) {
    selectedResults[pairingId] = result;

    // Butonları güncelle
    document.querySelectorAll('.result-btn-' + pairingId).forEach(btn => {
        const btnResult = btn.getAttribute('data-result');
        btn.className = 'result-btn result-btn-' + pairingId + ' w-16 py-1.5 rounded-lg text-xs font-bold transition ';

        if (btnResult === result) {
            if (result === '1-0') {
                btn.className += 'bg-green-600 text-white shadow-md ring-2 ring-green-300';
            } else if (result === '1/2-1/2') {
                btn.className += 'bg-amber-500 text-white shadow-md ring-2 ring-amber-300';
            } else if (result === '0-1') {
                btn.className += 'bg-red-600 text-white shadow-md ring-2 ring-red-300';
            }
        } else {
            btn.className += 'bg-gray-100 text-gray-600 hover:bg-gray-200';
        }
    });
}

function showMessage(pairingId, msg, type) {
    const el = document.getElementById('message-' + pairingId);
    el.classList.remove('hidden', 'text-green-700', 'text-red-700', 'bg-green-50', 'bg-red-50');
    if (type === 'success') {
        el.className = 'mt-2 text-sm rounded-lg px-3 py-2 bg-green-50 text-green-700 font-medium';
    } else {
        el.className = 'mt-2 text-sm rounded-lg px-3 py-2 bg-red-50 text-red-700 font-medium';
    }
    el.textContent = msg;
    el.classList.remove('hidden');

    setTimeout(() => { el.classList.add('hidden'); }, 5000);
}

function showToast(msg, type) {
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');
    toast.className = 'px-4 py-3 rounded-xl shadow-lg text-sm font-medium transform transition-all duration-300 translate-x-full opacity-0 ' +
        (type === 'success' ? 'bg-green-600 text-white' : 'bg-red-600 text-white');
    toast.textContent = msg;
    container.appendChild(toast);

    requestAnimationFrame(() => {
        toast.classList.remove('translate-x-full', 'opacity-0');
    });

    setTimeout(() => {
        toast.classList.add('translate-x-full', 'opacity-0');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

async function saveMatchSchedule(pairingId) {
    const dateEl = document.getElementById('mdate_' + pairingId);
    const timeEl = document.getElementById('mtime_' + pairingId);
    const formData = new FormData();
    formData.append('csrf_token', csrfToken);
    formData.append('pairing_id', pairingId);
    formData.append('match_date', dateEl.value);
    formData.append('match_time', timeEl.value);

    try {
        const response = await fetch('api/update_match_schedule.php', { method: 'POST', body: formData });
        const data = await response.json();
        if (data.success) {
            showToast('Masa ' + data.table_no + ' takvimi kaydedildi.', 'success');
        } else {
            showToast(data.message || 'Hata oluştu.', 'error');
        }
    } catch (err) {
        showToast('Bağlantı hatası.', 'error');
    }
}

function parseTables(input) {
    if (!input.trim()) return null; // boş = tüm masalar
    const tables = [];
    input.split(',').forEach(part => {
        part = part.trim();
        if (part.includes('-')) {
            const [a, b] = part.split('-').map(Number);
            for (let i = a; i <= b; i++) tables.push(i);
        } else if (!isNaN(parseInt(part))) {
            tables.push(parseInt(part));
        }
    });
    return tables;
}

async function bulkAssignSchedule() {
    const dateVal = document.getElementById('bulk_date').value;
    const timeVal = document.getElementById('bulk_time').value;
    if (!dateVal && !timeVal) {
        showToast('Lütfen tarih veya saat girin.', 'error');
        return;
    }
    const tablesInput = document.getElementById('bulk_tables').value;
    const targetTables = parseTables(tablesInput);

    const formData = new FormData();
    formData.append('csrf_token', csrfToken);
    formData.append('round', <?= $selectedRound ?>);
    formData.append('match_date', dateVal);
    formData.append('match_time', timeVal);
    if (targetTables) formData.append('tables', JSON.stringify(targetTables));

    const btn = document.getElementById('bulk-assign-btn');
    const origText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>';

    try {
        const response = await fetch('api/update_match_schedule.php', { method: 'POST', body: formData });
        const data = await response.json();
        if (data.success) {
            showToast(data.message, 'success');
            // Inputları güncelle
            document.querySelectorAll('.pairing-row').forEach(row => {
                const pid = row.getAttribute('data-pairing-id');
                const tableNo = parseInt(row.querySelector('.text-xs.font-bold.text-gray-400')?.textContent?.replace('MASA ', '') || 0);
                if (!targetTables || targetTables.includes(tableNo)) {
                    const d = document.getElementById('mdate_' + pid);
                    const t = document.getElementById('mtime_' + pid);
                    if (d && dateVal) d.value = dateVal;
                    if (t && timeVal) t.value = timeVal;
                }
            });
        } else {
            showToast(data.message || 'Hata oluştu.', 'error');
        }
    } catch (err) {
        showToast('Bağlantı hatası.', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = origText;
    }
}

async function generateNextRound() {
    if (!confirm('Sonraki tur eşleşmeleri oluşturulacak. Devam etmek istiyor musunuz?')) return;

    const btn = document.getElementById('next-round-btn');
    const originalContent = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg> Oluşturuluyor...';

    const formData = new FormData();
    formData.append('csrf_token', csrfToken);

    try {
        const response = await fetch('api/next_round.php', { method: 'POST', body: formData });
        const data = await response.json();

        if (data.success) {
            showToast(data.message, 'success');
            setTimeout(() => { location.href = 'admin.php?round=' + data.round; }, 1500);
        } else {
            showToast(data.message || 'Bir hata oluştu.', 'error');
            btn.disabled = false;
            btn.innerHTML = originalContent;
        }
    } catch (err) {
        showToast('Bağlantı hatası: ' + err.message, 'error');
        btn.disabled = false;
        btn.innerHTML = originalContent;
    }
}

async function saveResult(pairingId) {
    const result = selectedResults[pairingId];
    if (!result) {
        showMessage(pairingId, 'Lütfen bir sonuç seçin (1-0, 1/2-1/2 veya 0-1).', 'error');
        return;
    }

    const btn = document.getElementById('save-btn-' + pairingId);
    const originalContent = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg> Kaydediliyor...';

    const formData = new FormData();
    formData.append('pairing_id', pairingId);
    formData.append('result', result);
    formData.append('csrf_token', csrfToken);

    const whitePhoto = document.getElementById('white_photo_' + pairingId);
    if (whitePhoto && whitePhoto.files.length > 0) {
        formData.append('white_photo', whitePhoto.files[0]);
    }

    const blackPhoto = document.getElementById('black_photo_' + pairingId);
    if (blackPhoto && blackPhoto.files.length > 0) {
        formData.append('black_photo', blackPhoto.files[0]);
    }

    try {
        const response = await fetch('api/update_score.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            showMessage(pairingId, data.message, 'success');
            showToast('Masa ' + document.querySelector('#pairing-' + pairingId + ' .text-xs.font-bold.text-gray-400').textContent.replace('MASA ', '') + ' kaydedildi!', 'success');

            // Satır durumunu güncelle
            const row = document.getElementById('pairing-' + pairingId);
            const statusBadge = row.querySelector('.px-4.py-2 span:last-child');
            if (statusBadge) {
                statusBadge.className = 'ml-auto inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-green-100 text-green-700';
                statusBadge.textContent = 'Tamamlandı';
            }

            // Fotoğraf inputlarını temizle
            if (whitePhoto) whitePhoto.value = '';
            if (blackPhoto) blackPhoto.value = '';

            // İlerleme çubuğunu güncelle - sayfayi yenile
            setTimeout(() => { location.reload(); }, 1500);
        } else {
            showMessage(pairingId, data.message || 'Bir hata oluştu.', 'error');
        }
    } catch (err) {
        showMessage(pairingId, 'Bağlantı hatası: ' + err.message, 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalContent;
    }
}
</script>

<?php include 'footer.php'; ?>
