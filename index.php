<?php
require_once 'db.php';

// Turnuva ayarlarını çek
$stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$status = $settings['tournament_status'] ?? 'basvurular_acik';
$deadline = $settings['deadline'] ?? '4 Mart 2026';
$tournament_name = $settings['tournament_name'] ?? 'Sultangazi BYAL Satranc Turnuvasi';

// İstatistikler
$playerCount = $pdo->query("SELECT COUNT(*) FROM players WHERE is_active = 1")->fetchColumn();
$matchCount = $pdo->query("SELECT COUNT(*) FROM matches")->fetchColumn();
$completedCount = $pdo->query("SELECT COUNT(*) FROM matches WHERE status = 'completed'")->fetchColumn();
$currentRound = $pdo->query("SELECT COALESCE(MAX(round_number), 0) FROM matches")->fetchColumn();

// Son tamamlanan maçlar
$recentMatches = $pdo->query("
    SELECT m.*, p1.name as p1_name, p2.name as p2_name
    FROM matches m
    LEFT JOIN players p1 ON m.player1_id = p1.id
    LEFT JOIN players p2 ON m.player2_id = p2.id
    WHERE m.status = 'completed' AND m.player2_id IS NOT NULL
    ORDER BY m.id DESC LIMIT 5
")->fetchAll();

// Top 5 oyuncu
$topPlayers = $pdo->query("
    SELECT * FROM players WHERE is_active = 1
    ORDER BY points DESC, wins DESC, matches_played DESC
    LIMIT 5
")->fetchAll();

include 'header.php';
?>

<!-- Hero Section -->
<div class="relative overflow-hidden rounded-2xl mb-8">
    <div class="bg-gradient-to-br from-gray-900 via-gray-800 to-gray-900 rounded-2xl p-8 sm:p-12 lg:p-16 relative">
        <!-- Chess pattern overlay -->
        <div class="absolute inset-0 opacity-5">
            <div class="chess-pattern w-full h-full"></div>
        </div>

        <div class="relative z-10 max-w-3xl">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-blue-500/20 border border-blue-500/30 text-blue-300 text-xs font-semibold mb-6">
                <span>&#9812;</span>
                <span>Okul Ici Etkinlik</span>
            </div>

            <h1 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold text-white mb-4 leading-tight">
                <?php echo htmlspecialchars($tournament_name); ?>
            </h1>

            <p class="text-gray-300 text-base sm:text-lg mb-8 max-w-2xl leading-relaxed">
                Okulumuzun en iyilerini belirlemek icin duzenlenen geleneksel satranc turnuvasina hos geldiniz.
                Stratejini belirle, hamleni yap ve sampiyon ol!
            </p>

            <div class="flex flex-wrap gap-3">
                <a href="fixtures.php" class="inline-flex items-center px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-xl transition shadow-lg shadow-blue-600/25">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                    Fiksturu Gor
                </a>
                <a href="standings.php" class="inline-flex items-center px-6 py-3 bg-white/10 hover:bg-white/20 text-white text-sm font-semibold rounded-xl transition border border-white/20">
                    Puan Durumu
                </a>
                <a href="rules.php" class="inline-flex items-center px-6 py-3 text-gray-300 hover:text-white text-sm font-medium transition">
                    Kurallar &rarr;
                </a>
            </div>
        </div>

        <!-- Right side info -->
        <div class="absolute top-8 right-8 hidden lg:block">
            <div class="bg-white/10 backdrop-blur rounded-2xl p-6 border border-white/10 text-center">
                <p class="text-gray-400 text-xs font-medium uppercase tracking-wider mb-1">
                    <?php echo $status === 'basvurular_acik' ? 'Basvuru Son Tarih' : ($status === 'turnuva_basladi' ? 'Aktif Tur' : 'Turnuva'); ?>
                </p>
                <div class="text-3xl font-extrabold text-white mb-1">
                    <?php
                    if ($status === 'basvurular_acik') echo htmlspecialchars($deadline);
                    elseif ($status === 'turnuva_basladi') echo $currentRound . '. Tur';
                    else echo 'Bitti';
                    ?>
                </div>
                <p class="text-gray-400 text-xs">
                    <?php
                    if ($status === 'basvurular_acik') echo 'Basvurular devam ediyor';
                    elseif ($status === 'turnuva_basladi') echo $completedCount . '/' . $matchCount . ' mac oynandi';
                    else echo 'Tebrikler!';
                    ?>
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8">
    <div class="card p-5 text-center">
        <div class="text-3xl font-extrabold text-gray-900"><?php echo $playerCount; ?></div>
        <div class="text-xs font-medium text-gray-500 mt-1">Katilimci</div>
    </div>
    <div class="card p-5 text-center">
        <div class="text-3xl font-extrabold text-gray-900"><?php echo $currentRound; ?></div>
        <div class="text-xs font-medium text-gray-500 mt-1">Tur</div>
    </div>
    <div class="card p-5 text-center">
        <div class="text-3xl font-extrabold text-gray-900"><?php echo $completedCount; ?></div>
        <div class="text-xs font-medium text-gray-500 mt-1">Oynanan Mac</div>
    </div>
    <div class="card p-5 text-center">
        <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold
            <?php echo $status === 'basvurular_acik' ? 'bg-green-100 text-green-700' : ($status === 'turnuva_basladi' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-700'); ?>">
            <span class="w-1.5 h-1.5 rounded-full <?php echo $status === 'basvurular_acik' ? 'bg-green-500' : ($status === 'turnuva_basladi' ? 'bg-blue-500' : 'bg-gray-500'); ?>"></span>
            <?php
            if ($status === 'basvurular_acik') echo 'Basvuru Acik';
            elseif ($status === 'turnuva_basladi') echo 'Devam Ediyor';
            else echo 'Tamamlandi';
            ?>
        </div>
        <div class="text-xs font-medium text-gray-500 mt-2">Durum</div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
    <!-- Top 5 -->
    <?php if (!empty($topPlayers)): ?>
    <div class="card overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="font-semibold text-gray-900">Puan Siralaması</h3>
            <a href="standings.php" class="text-xs text-blue-600 hover:text-blue-700 font-medium">Tumunu Gor &rarr;</a>
        </div>
        <div class="divide-y divide-gray-50">
            <?php foreach ($topPlayers as $i => $p): ?>
            <div class="px-5 py-3 flex items-center gap-3 hover:bg-gray-50 transition">
                <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold
                    <?php echo $i === 0 ? 'bg-amber-100 text-amber-700' : ($i === 1 ? 'bg-gray-100 text-gray-600' : ($i === 2 ? 'bg-orange-100 text-orange-700' : 'bg-gray-50 text-gray-500')); ?>">
                    <?php echo $i + 1; ?>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-900 truncate"><?php echo htmlspecialchars($p['name']); ?></p>
                    <p class="text-xs text-gray-500"><?php echo htmlspecialchars($p['class_name']); ?></p>
                </div>
                <div class="text-right">
                    <span class="text-sm font-bold text-gray-900"><?php echo number_format($p['points'], 1); ?></span>
                    <span class="text-xs text-gray-400 ml-0.5">puan</span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Son Maçlar -->
    <?php if (!empty($recentMatches)): ?>
    <div class="card overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="font-semibold text-gray-900">Son Sonuclar</h3>
            <a href="fixtures.php" class="text-xs text-blue-600 hover:text-blue-700 font-medium">Tum Maclar &rarr;</a>
        </div>
        <div class="divide-y divide-gray-50">
            <?php foreach ($recentMatches as $m): ?>
            <div class="px-5 py-3 flex items-center gap-3 hover:bg-gray-50 transition">
                <div class="flex-1 text-right">
                    <span class="text-sm <?php echo $m['result'] === '1-0' ? 'font-bold text-gray-900' : 'text-gray-500'; ?>">
                        <?php echo htmlspecialchars($m['p1_name']); ?>
                    </span>
                </div>
                <div class="px-3">
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold
                        <?php echo $m['result'] === '0.5-0.5' ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-900 text-white'; ?>">
                        <?php echo $m['result'] === '0.5-0.5' ? '½-½' : htmlspecialchars($m['result']); ?>
                    </span>
                </div>
                <div class="flex-1">
                    <span class="text-sm <?php echo $m['result'] === '0-1' ? 'font-bold text-gray-900' : 'text-gray-500'; ?>">
                        <?php echo htmlspecialchars($m['p2_name']); ?>
                    </span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (empty($topPlayers) && empty($recentMatches)): ?>
    <!-- Empty state -->
    <div class="lg:col-span-2 card p-12 text-center">
        <div class="text-5xl mb-4">&#9812;</div>
        <h3 class="text-lg font-semibold text-gray-900 mb-2">Turnuva Henuz Baslamadi</h3>
        <p class="text-sm text-gray-500 max-w-md mx-auto">Basvurular devam ediyor. Katilimci listesini gorebilir ve kurallari okuyabilirsiniz.</p>
        <div class="flex justify-center gap-3 mt-6">
            <a href="participants.php" class="text-sm font-medium text-blue-600 hover:text-blue-700">Katilimcilar</a>
            <span class="text-gray-300">|</span>
            <a href="rules.php" class="text-sm font-medium text-blue-600 hover:text-blue-700">Kurallar</a>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Puanlama Bilgi -->
<div class="card p-6 mb-8">
    <h3 class="font-semibold text-gray-900 mb-4">Puanlama Sistemi</h3>
    <div class="grid grid-cols-3 gap-4 text-center">
        <div class="p-3 rounded-xl bg-green-50 border border-green-100">
            <div class="text-2xl font-bold text-green-600">1</div>
            <div class="text-xs text-green-700 font-medium mt-1">Galibiyet</div>
        </div>
        <div class="p-3 rounded-xl bg-yellow-50 border border-yellow-100">
            <div class="text-2xl font-bold text-yellow-600">0.5</div>
            <div class="text-xs text-yellow-700 font-medium mt-1">Beraberlik</div>
        </div>
        <div class="p-3 rounded-xl bg-red-50 border border-red-100">
            <div class="text-2xl font-bold text-red-600">0</div>
            <div class="text-xs text-red-700 font-medium mt-1">Maglubiyet</div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
