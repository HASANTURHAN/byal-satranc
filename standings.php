<?php
require_once 'db.php';
include 'header.php';

$players = $pdo->query("
    SELECT * FROM players
    WHERE is_active = 1
    ORDER BY points DESC, wins DESC, matches_played ASC, name ASC
")->fetchAll();
?>

<div class="mb-8">
    <div class="flex items-center gap-3 mb-2">
        <span class="text-3xl">♚</span>
        <h2 class="text-3xl font-bold text-gray-900">Puan Durumu</h2>
    </div>
    <p class="text-sm text-gray-500">Turnuva genel siralaması ve oyuncu istatistikleri.</p>
</div>

<?php if (!empty($players)): ?>

<!-- Podium (Top 3) -->
<?php if (count($players) >= 3): ?>
<div class="grid grid-cols-3 gap-4 mb-10 max-w-3xl mx-auto items-end">
    <!-- 2nd Place - Silver -->
    <div class="relative transform hover:scale-105 transition-all duration-300">
        <div class="card p-6 text-center border-2 border-gray-300 bg-gradient-to-b from-gray-50 to-white podium-silver">
            <div class="absolute -top-4 left-1/2 transform -translate-x-1/2">
                <div class="w-12 h-12 rounded-full bg-gradient-to-br from-gray-300 to-gray-400 flex items-center justify-center shadow-lg border-2 border-white">
                    <span class="text-xl">♕</span>
                </div>
            </div>
            <div class="mt-6 mb-3">
                <div class="text-4xl font-black text-gray-600 mb-1">2</div>
                <div class="w-8 h-1 bg-gray-400 mx-auto rounded-full"></div>
            </div>
            <p class="text-sm font-bold text-gray-900 truncate mb-1"><?php echo htmlspecialchars($players[1]['name']); ?></p>
            <p class="text-xs text-gray-500 mb-3"><?php echo htmlspecialchars($players[1]['class_name']); ?></p>
            <div class="bg-white/60 rounded-lg p-2 border border-gray-200">
                <p class="text-2xl font-extrabold text-gray-700"><?php echo number_format($players[1]['points'], 1); ?></p>
                <p class="text-[10px] text-gray-500 uppercase font-semibold">puan</p>
            </div>
        </div>
    </div>

    <!-- 1st Place - Gold -->
    <div class="relative transform hover:scale-105 transition-all duration-300 -mt-6">
        <div class="card p-8 text-center border-2 border-amber-300 bg-gradient-to-b from-amber-50 to-yellow-50 podium-gold">
            <div class="absolute -top-5 left-1/2 transform -translate-x-1/2">
                <div class="w-16 h-16 rounded-full bg-gradient-to-br from-yellow-300 to-amber-500 flex items-center justify-center shadow-2xl border-3 border-white animate-pulse">
                    <span class="text-3xl">♔</span>
                </div>
            </div>
            <div class="mt-8 mb-4">
                <div class="text-5xl font-black text-amber-600 mb-2">1</div>
                <div class="w-12 h-1.5 bg-gradient-to-r from-yellow-400 to-amber-500 mx-auto rounded-full"></div>
            </div>
            <p class="text-base font-bold text-gray-900 truncate mb-1"><?php echo htmlspecialchars($players[0]['name']); ?></p>
            <p class="text-xs text-gray-600 mb-4"><?php echo htmlspecialchars($players[0]['class_name']); ?></p>
            <div class="bg-white/70 rounded-xl p-3 border-2 border-amber-200 shadow-inner">
                <p class="text-3xl font-black text-amber-600"><?php echo number_format($players[0]['points'], 1); ?></p>
                <p class="text-[10px] text-amber-700 uppercase font-bold tracking-wide">puan</p>
            </div>
        </div>
    </div>

    <!-- 3rd Place - Bronze -->
    <div class="relative transform hover:scale-105 transition-all duration-300">
        <div class="card p-6 text-center border-2 border-orange-300 bg-gradient-to-b from-orange-50 to-white podium-bronze">
            <div class="absolute -top-4 left-1/2 transform -translate-x-1/2">
                <div class="w-12 h-12 rounded-full bg-gradient-to-br from-orange-300 to-orange-500 flex items-center justify-center shadow-lg border-2 border-white">
                    <span class="text-xl">♖</span>
                </div>
            </div>
            <div class="mt-6 mb-3">
                <div class="text-4xl font-black text-orange-600 mb-1">3</div>
                <div class="w-8 h-1 bg-orange-400 mx-auto rounded-full"></div>
            </div>
            <p class="text-sm font-bold text-gray-900 truncate mb-1"><?php echo htmlspecialchars($players[2]['name']); ?></p>
            <p class="text-xs text-gray-500 mb-3"><?php echo htmlspecialchars($players[2]['class_name']); ?></p>
            <div class="bg-white/60 rounded-lg p-2 border border-orange-200">
                <p class="text-2xl font-extrabold text-orange-700"><?php echo number_format($players[2]['points'], 1); ?></p>
                <p class="text-[10px] text-orange-600 uppercase font-semibold">puan</p>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Full Table -->
<div class="card overflow-hidden mb-8">
    <div class="overflow-x-auto">
        <table class="min-w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-16">Sira</th>
                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ogrenci</th>
                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden sm:table-cell">Sinif</th>
                    <th class="px-5 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Mac</th>
                    <th class="px-5 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider hidden sm:table-cell">G</th>
                    <th class="px-5 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider hidden sm:table-cell">B</th>
                    <th class="px-5 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider hidden sm:table-cell">M</th>
                    <th class="px-5 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider font-bold">Puan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($players as $i => $player):
                    $rowBg = '';
                    if ($i === 0) $rowBg = 'bg-amber-50/50';
                    elseif ($i === 1) $rowBg = 'bg-gray-50/50';
                    elseif ($i === 2) $rowBg = 'bg-orange-50/30';
                ?>
                <tr class="<?php echo $rowBg; ?> hover:bg-blue-50/50 transition group">
                    <td class="px-5 py-4 text-sm font-medium <?php echo $i < 3 ? 'text-gray-900 font-bold' : 'text-gray-400'; ?>">
                        <div class="flex items-center gap-2">
                            <?php
                            if ($i === 0) echo '<span class="text-lg">♔</span>';
                            elseif ($i === 1) echo '<span class="text-lg">♕</span>';
                            elseif ($i === 2) echo '<span class="text-lg">♖</span>';
                            else echo ($i + 1) . '.';
                            ?>
                        </div>
                    </td>
                    <td class="px-5 py-4">
                        <span class="text-sm font-medium text-gray-900 <?php echo $i < 3 ? 'font-bold' : ''; ?>">
                            <?php echo htmlspecialchars($player['name']); ?>
                        </span>
                        <span class="text-xs text-gray-400 sm:hidden block"><?php echo htmlspecialchars($player['class_name']); ?></span>
                    </td>
                    <td class="px-5 py-4 text-sm text-gray-500 hidden sm:table-cell"><?php echo htmlspecialchars($player['class_name']); ?></td>
                    <td class="px-5 py-4 text-sm text-gray-500 text-center"><?php echo $player['matches_played']; ?></td>
                    <td class="px-5 py-4 text-sm text-green-600 text-center font-semibold hidden sm:table-cell"><?php echo $player['wins'] ?? 0; ?></td>
                    <td class="px-5 py-4 text-sm text-amber-600 text-center font-semibold hidden sm:table-cell"><?php echo $player['draws'] ?? 0; ?></td>
                    <td class="px-5 py-4 text-sm text-red-500 text-center font-semibold hidden sm:table-cell"><?php echo $player['losses'] ?? 0; ?></td>
                    <td class="px-5 py-4 text-center">
                        <span class="inline-flex items-center px-3 py-1.5 rounded-lg text-sm font-bold shadow-sm transition-transform group-hover:scale-105
                            <?php
                            if ($i === 0) echo 'bg-gradient-to-r from-amber-100 to-yellow-100 text-amber-700 border border-amber-200';
                            elseif ($i === 1) echo 'bg-gradient-to-r from-gray-100 to-gray-200 text-gray-700 border border-gray-300';
                            elseif ($i === 2) echo 'bg-gradient-to-r from-orange-100 to-orange-200 text-orange-700 border border-orange-200';
                            else echo 'bg-gray-50 text-gray-700 border border-gray-200';
                            ?>">
                            <?php echo number_format($player['points'], 1); ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php else: ?>
<div class="card p-12 text-center">
    <div class="text-4xl mb-4">&#9812;</div>
    <h3 class="text-base font-semibold text-gray-900 mb-2">Henuz puan durumu olusmadi</h3>
    <p class="text-sm text-gray-500">Sisteme oyuncu eklenmediginde veya mac oynanmadiginda burada gorunecek.</p>
</div>
<?php endif; ?>

<?php include 'footer.php'; ?>
