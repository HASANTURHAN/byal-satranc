<?php
require_once 'db.php';

// İstatistikler
$total_players = $pdo->query("SELECT COUNT(*) FROM players")->fetchColumn();
$seed_players = $pdo->query("SELECT COUNT(*) FROM players WHERE is_seed = 1")->fetchColumn();
$current_round = $pdo->query("SELECT COALESCE(MAX(round), 0) FROM pairings")->fetchColumn();
$completed_matches = $pdo->query("SELECT COUNT(*) FROM pairings WHERE result IS NOT NULL AND result != ''")->fetchColumn();
$pending_matches = $pdo->query("SELECT COUNT(*) FROM pairings WHERE result IS NULL OR result = ''")->fetchColumn();
$total_matches = $completed_matches + $pending_matches;

$tournament_name = get_setting('tournament_name', '2025-2026 Okul Satranç Turnuvası');
$status = get_setting('tournament_status', 'turnuva_basladi');

// Son 5 sonuç
$recent = $pdo->query("
    SELECT p.*,
           w.name as white_name, w.sinif as white_sinif,
           b.name as black_name, b.sinif as black_sinif
    FROM pairings p
    JOIN players w ON p.white_player_id = w.id
    LEFT JOIN players b ON p.black_player_id = b.id
    WHERE p.result IS NOT NULL AND p.result != ''
    ORDER BY p.played_at DESC, p.id DESC
    LIMIT 5
")->fetchAll();

// Top 5 oyuncu
$top5 = $pdo->query("SELECT * FROM players ORDER BY total_points DESC, name ASC LIMIT 5")->fetchAll();

// Seri başı oyuncular
$seeds = $pdo->query("SELECT * FROM players WHERE is_seed = 1 ORDER BY id")->fetchAll();

include 'header.php';
?>

<!-- Hero Section -->
<div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-gray-900 via-gray-800 to-gray-900 text-white p-8 md:p-12 mb-8 shadow-2xl">
    <div class="absolute inset-0 opacity-10">
        <div class="absolute top-4 right-8 text-8xl chess-piece-float">♔</div>
        <div class="absolute bottom-4 left-8 text-6xl chess-piece-float" style="animation-delay: 1s;">♚</div>
        <div class="absolute top-20 right-40 text-5xl chess-piece-float" style="animation-delay: 2s;">♜</div>
    </div>
    <div class="relative z-10 max-w-3xl">
        <div class="inline-flex items-center gap-2 px-3 py-1 bg-amber-500/20 rounded-full text-amber-300 text-sm font-medium mb-4">
            <span>♔</span>
            <span>2025-2026 Sezonu</span>
        </div>
        <h1 class="text-3xl md:text-4xl font-extrabold mb-2"><?php echo htmlspecialchars($tournament_name); ?></h1>
        <p class="text-gray-300 text-lg mb-2">Sultangazi Bahattin Yıldız Anadolu Lisesi</p>
        <p class="text-gray-400 text-sm mb-6">İsviçre Sistemi (6 Tur) &middot; <?php echo $total_players; ?> Oyuncu &middot; <?php echo $seed_players; ?> Seri Başı</p>
        <div class="flex flex-wrap gap-4">
            <div class="bg-white/10 backdrop-blur rounded-xl px-4 py-2">
                <div class="text-2xl font-bold"><?php echo $total_players; ?></div>
                <div class="text-xs text-gray-400">Oyuncu</div>
            </div>
            <div class="bg-white/10 backdrop-blur rounded-xl px-4 py-2">
                <div class="text-2xl font-bold"><?php echo $current_round; ?>/6</div>
                <div class="text-xs text-gray-400">Tur</div>
            </div>
            <div class="bg-white/10 backdrop-blur rounded-xl px-4 py-2">
                <div class="text-2xl font-bold"><?php echo $completed_matches; ?></div>
                <div class="text-xs text-gray-400">Oynanan Maç</div>
            </div>
            <div class="bg-white/10 backdrop-blur rounded-xl px-4 py-2">
                <div class="text-2xl font-bold">İsviçre</div>
                <div class="text-xs text-gray-400">Sistem</div>
            </div>
        </div>
        <div class="flex flex-wrap gap-3 mt-6">
            <a href="round.php" class="inline-flex items-center px-6 py-3 bg-amber-600 hover:bg-amber-700 text-white text-sm font-semibold rounded-xl transition shadow-lg">
                ♟ Fikstürü Gör
            </a>
            <a href="standings.php" class="inline-flex items-center gap-2 px-6 py-3 bg-white/10 hover:bg-white/20 text-white text-sm font-semibold rounded-xl transition border border-white/20">
                ♚ Puan Durumu
            </a>
        </div>
    </div>
</div>

<!-- İstatistik Kartları -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
    <div class="card p-5 text-center">
        <div class="text-3xl font-bold text-gray-900"><?php echo $total_players; ?></div>
        <div class="text-sm text-gray-500 mt-1">Katılımcı</div>
    </div>
    <div class="card p-5 text-center">
        <div class="text-3xl font-bold text-amber-600"><?php echo $current_round; ?>/6</div>
        <div class="text-sm text-gray-500 mt-1">Aktif Tur</div>
    </div>
    <div class="card p-5 text-center">
        <div class="text-3xl font-bold text-green-600"><?php echo $completed_matches; ?></div>
        <div class="text-sm text-gray-500 mt-1">Oynanan Maç</div>
    </div>
    <div class="card p-5 text-center">
        <div class="text-3xl font-bold text-orange-500"><?php echo $pending_matches; ?></div>
        <div class="text-sm text-gray-500 mt-1">Bekleyen Maç</div>
    </div>
</div>

<div class="grid md:grid-cols-2 gap-8 mb-8">
    <!-- Top 5 Sıralama -->
    <div class="card p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                <span class="text-amber-500">♚</span> Puan Sıralaması
            </h2>
            <a href="standings.php" class="text-sm text-amber-600 hover:text-amber-700 font-medium">Tümünü Gör &rarr;</a>
        </div>
        <?php if (empty($top5)): ?>
            <p class="text-gray-500 text-sm">Henüz puan verisi yok.</p>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($top5 as $i => $p): ?>
                    <div class="flex items-center gap-3 p-2 rounded-lg <?php echo $i === 0 ? 'bg-amber-50' : ''; ?>">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold
                            <?php echo $i === 0 ? 'bg-amber-100 text-amber-700' : ($i === 1 ? 'bg-gray-100 text-gray-700' : ($i === 2 ? 'bg-orange-100 text-orange-700' : 'bg-gray-50 text-gray-500')); ?>">
                            <?php echo $i === 0 ? '♔' : ($i === 1 ? '♕' : ($i === 2 ? '♖' : ($i + 1))); ?>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="font-semibold text-gray-900 truncate">
                                <?php echo htmlspecialchars($p['name']); ?>
                                <?php if ($p['is_seed']): ?>
                                    <span class="inline-flex items-center px-1.5 py-0.5 text-xs seed-badge rounded-full ml-1">⭐</span>
                                <?php endif; ?>
                            </div>
                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($p['sinif']); ?></div>
                        </div>
                        <div class="text-lg font-bold text-gray-900"><?php echo number_format($p['total_points'], 1); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Son Sonuçlar -->
    <div class="card p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                <span class="text-green-500">♜</span> Son Sonuçlar
            </h2>
            <a href="results.php" class="text-sm text-amber-600 hover:text-amber-700 font-medium">Tümünü Gör &rarr;</a>
        </div>
        <?php if (empty($recent)): ?>
            <p class="text-gray-500 text-sm">Henüz tamamlanan maç yok.</p>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($recent as $m): ?>
                    <div class="flex items-center gap-3 p-3 rounded-lg bg-gray-50">
                        <div class="flex-1 text-right">
                            <div class="font-medium text-sm <?php echo $m['result'] === '1-0' ? 'text-green-700 font-bold' : 'text-gray-700'; ?> truncate">
                                <?php echo htmlspecialchars($m['white_name']); ?>
                            </div>
                            <div class="text-xs text-gray-400"><?php echo htmlspecialchars($m['white_sinif']); ?></div>
                        </div>
                        <div class="px-3 py-1 rounded-lg text-xs font-bold
                            <?php echo $m['result'] === '1/2-1/2' ? 'bg-amber-100 text-amber-700' : 'bg-gray-900 text-white'; ?>">
                            <?php echo $m['result'] === '1/2-1/2' ? '½-½' : htmlspecialchars($m['result']); ?>
                        </div>
                        <div class="flex-1">
                            <div class="font-medium text-sm <?php echo $m['result'] === '0-1' ? 'text-green-700 font-bold' : 'text-gray-700'; ?> truncate">
                                <?php echo htmlspecialchars($m['black_name'] ?? 'BYE'); ?>
                            </div>
                            <div class="text-xs text-gray-400"><?php echo htmlspecialchars($m['black_sinif'] ?? ''); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Seri Başı Oyuncular -->
<div class="card p-6 mb-8">
    <h2 class="text-lg font-bold text-gray-900 flex items-center gap-2 mb-4">
        <span class="text-amber-500">⭐</span> Seri Başı Oyuncular (<?php echo $seed_players; ?> Kişi)
    </h2>
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
        <?php foreach ($seeds as $i => $s): ?>
            <div class="text-center p-4 rounded-xl bg-gradient-to-b from-amber-50 to-white border border-amber-200">
                <div class="w-12 h-12 rounded-full bg-amber-100 text-amber-700 flex items-center justify-center text-lg font-bold mx-auto mb-2">
                    <?php echo $i + 1; ?>
                </div>
                <div class="font-semibold text-sm text-gray-900"><?php echo htmlspecialchars($s['name']); ?></div>
                <div class="text-xs text-gray-500"><?php echo htmlspecialchars($s['sinif']); ?> – No: <?php echo htmlspecialchars($s['school_no']); ?></div>
                <div class="mt-1 text-sm font-bold text-amber-600"><?php echo number_format($s['total_points'], 1); ?> puan</div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Puanlama Sistemi -->
<div class="card p-6 mb-8">
    <h2 class="text-lg font-bold text-gray-900 flex items-center gap-2 mb-4">
        <span>♝</span> Puanlama Sistemi
    </h2>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="text-center p-4 rounded-xl bg-green-50 border border-green-200">
            <div class="text-2xl font-bold text-green-700">1</div>
            <div class="text-sm text-green-600">Galibiyet</div>
        </div>
        <div class="text-center p-4 rounded-xl bg-amber-50 border border-amber-200">
            <div class="text-2xl font-bold text-amber-700">0.5</div>
            <div class="text-sm text-amber-600">Beraberlik</div>
        </div>
        <div class="text-center p-4 rounded-xl bg-red-50 border border-red-200">
            <div class="text-2xl font-bold text-red-700">0</div>
            <div class="text-sm text-red-600">Mağlubiyet</div>
        </div>
        <div class="text-center p-4 rounded-xl bg-blue-50 border border-blue-200">
            <div class="text-2xl font-bold text-blue-700">1</div>
            <div class="text-sm text-blue-600">BYE (Bay)</div>
        </div>
    </div>
</div>

<!-- Hızlı Linkler -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4">
    <a href="round.php" class="card p-5 text-center group">
        <div class="text-3xl mb-2">♟</div>
        <div class="font-semibold text-gray-900 group-hover:text-amber-600 transition">Fikstür</div>
        <div class="text-xs text-gray-500">Maç Eşleştirmeleri</div>
    </a>
    <a href="results.php" class="card p-5 text-center group">
        <div class="text-3xl mb-2">♜</div>
        <div class="font-semibold text-gray-900 group-hover:text-amber-600 transition">Sonuçlar</div>
        <div class="text-xs text-gray-500">Maç Sonuçları</div>
    </a>
    <a href="standings.php" class="card p-5 text-center group">
        <div class="text-3xl mb-2">♚</div>
        <div class="font-semibold text-gray-900 group-hover:text-amber-600 transition">Puan Durumu</div>
        <div class="text-xs text-gray-500">Canlı Sıralama</div>
    </a>
    <a href="players.php" class="card p-5 text-center group">
        <div class="text-3xl mb-2">♞</div>
        <div class="font-semibold text-gray-900 group-hover:text-amber-600 transition">Katılımcılar</div>
        <div class="text-xs text-gray-500"><?php echo $total_players; ?> Oyuncu</div>
    </a>
</div>

<?php include 'footer.php'; ?>
