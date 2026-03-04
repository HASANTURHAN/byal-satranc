<?php
require_once 'db.php';
include 'header.php';

$players = $pdo->query("SELECT * FROM players WHERE is_active = 1 ORDER BY name ASC")->fetchAll();

// Sınıflara göre grupla
$byClass = [];
foreach ($players as $p) {
    $byClass[$p['class_name']][] = $p;
}
ksort($byClass);
?>

<div class="mb-6">
    <h2 class="text-2xl font-bold text-gray-900">Katilimcilar</h2>
    <p class="text-sm text-gray-500 mt-1">
        Turnuvaya katilan ogrenciler.
        <span class="font-semibold text-gray-700"><?php echo count($players); ?></span> ogrenci
    </p>
</div>

<?php if (!empty($players)): ?>

<!-- Sınıf Özeti -->
<?php if (count($byClass) > 1): ?>
<div class="flex gap-2 mb-6 overflow-x-auto pb-2">
    <?php foreach ($byClass as $class => $classPlayers): ?>
    <span class="inline-flex items-center px-3 py-1.5 rounded-xl bg-white border border-gray-200 text-xs font-medium text-gray-700 whitespace-nowrap">
        <?php echo htmlspecialchars($class); ?>
        <span class="ml-1.5 bg-gray-100 text-gray-500 px-1.5 py-0.5 rounded-full text-[10px]"><?php echo count($classPlayers); ?></span>
    </span>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="card overflow-hidden mb-8">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 divide-y sm:divide-y-0 divide-gray-50">
        <?php foreach ($players as $i => $player): ?>
        <div class="px-5 py-3.5 flex items-center gap-3 hover:bg-gray-50 transition <?php echo ($i > 0) ? 'border-t sm:border-t-0 sm:border-l border-gray-50' : ''; ?>">
            <div class="w-9 h-9 rounded-full bg-gray-900 flex items-center justify-center text-white font-semibold text-sm flex-shrink-0">
                <?php echo htmlspecialchars(mb_substr($player['name'], 0, 1, 'UTF-8')); ?>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-900 truncate"><?php echo htmlspecialchars($player['name']); ?></p>
                <p class="text-xs text-gray-500"><?php echo htmlspecialchars($player['class_name']); ?></p>
            </div>
            <span class="text-xs text-gray-400 font-medium">#<?php echo $i + 1; ?></span>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php else: ?>
<div class="card p-12 text-center">
    <div class="text-4xl mb-4">&#9812;</div>
    <h3 class="text-base font-semibold text-gray-900 mb-2">Henuz katilimci yok</h3>
    <p class="text-sm text-gray-500">Sisteme henuz ogrenci eklenmemis.</p>
</div>
<?php endif; ?>

<?php include 'footer.php'; ?>
