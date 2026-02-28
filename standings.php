<?php
require_once 'db.php';
include 'header.php';

$players = $pdo->query("
    SELECT * FROM players
    WHERE is_active = 1
    ORDER BY points DESC, wins DESC, matches_played ASC, name ASC
")->fetchAll();
?>

<div class="mb-6">
    <h2 class="text-2xl font-bold text-gray-900">Puan Durumu</h2>
    <p class="text-sm text-gray-500 mt-1">Turnuva genel siralaması ve oyuncu istatistikleri.</p>
</div>

<?php if (!empty($players)): ?>

<!-- Podium (Top 3) -->
<?php if (count($players) >= 3): ?>
<div class="grid grid-cols-3 gap-3 mb-8 max-w-2xl mx-auto">
    <!-- 2. Sıra -->
    <div class="card p-4 text-center mt-8">
        <div class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center mx-auto mb-2 text-lg font-bold text-gray-600">2</div>
        <p class="text-sm font-semibold text-gray-900 truncate"><?php echo htmlspecialchars($players[1]['name']); ?></p>
        <p class="text-xs text-gray-500"><?php echo htmlspecialchars($players[1]['class_name']); ?></p>
        <p class="text-lg font-bold text-gray-700 mt-2"><?php echo number_format($players[1]['points'], 1); ?></p>
        <p class="text-[10px] text-gray-400 uppercase">puan</p>
    </div>
    <!-- 1. Sıra -->
    <div class="card p-4 text-center border-amber-200 bg-amber-50/50">
        <div class="w-12 h-12 rounded-full bg-amber-100 flex items-center justify-center mx-auto mb-2 text-xl font-bold text-amber-600 border-2 border-amber-200">1</div>
        <p class="text-sm font-bold text-gray-900 truncate"><?php echo htmlspecialchars($players[0]['name']); ?></p>
        <p class="text-xs text-gray-500"><?php echo htmlspecialchars($players[0]['class_name']); ?></p>
        <p class="text-2xl font-extrabold text-amber-600 mt-2"><?php echo number_format($players[0]['points'], 1); ?></p>
        <p class="text-[10px] text-amber-600 uppercase font-semibold">puan</p>
    </div>
    <!-- 3. Sıra -->
    <div class="card p-4 text-center mt-8">
        <div class="w-10 h-10 rounded-full bg-orange-100 flex items-center justify-center mx-auto mb-2 text-lg font-bold text-orange-600">3</div>
        <p class="text-sm font-semibold text-gray-900 truncate"><?php echo htmlspecialchars($players[2]['name']); ?></p>
        <p class="text-xs text-gray-500"><?php echo htmlspecialchars($players[2]['class_name']); ?></p>
        <p class="text-lg font-bold text-gray-700 mt-2"><?php echo number_format($players[2]['points'], 1); ?></p>
        <p class="text-[10px] text-gray-400 uppercase">puan</p>
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
                <tr class="<?php echo $rowBg; ?> hover:bg-blue-50/50 transition">
                    <td class="px-5 py-3 text-sm font-medium <?php echo $i < 3 ? 'text-gray-900 font-bold' : 'text-gray-400'; ?>">
                        <?php echo $i + 1; ?>.
                    </td>
                    <td class="px-5 py-3">
                        <span class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($player['name']); ?></span>
                        <span class="text-xs text-gray-400 sm:hidden block"><?php echo htmlspecialchars($player['class_name']); ?></span>
                    </td>
                    <td class="px-5 py-3 text-sm text-gray-500 hidden sm:table-cell"><?php echo htmlspecialchars($player['class_name']); ?></td>
                    <td class="px-5 py-3 text-sm text-gray-500 text-center"><?php echo $player['matches_played']; ?></td>
                    <td class="px-5 py-3 text-sm text-green-600 text-center font-medium hidden sm:table-cell"><?php echo $player['wins'] ?? 0; ?></td>
                    <td class="px-5 py-3 text-sm text-yellow-600 text-center font-medium hidden sm:table-cell"><?php echo $player['draws'] ?? 0; ?></td>
                    <td class="px-5 py-3 text-sm text-red-500 text-center font-medium hidden sm:table-cell"><?php echo $player['losses'] ?? 0; ?></td>
                    <td class="px-5 py-3 text-center">
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-sm font-bold
                            <?php echo $i < 3 ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700'; ?>">
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
