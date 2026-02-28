<?php
require_once 'db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!is_admin()) { header("Location: login.php"); exit(); }

$message = '';
$messageType = '';

// POST İşlemleri
if ($_SERVER["REQUEST_METHOD"] === "POST" && verify_csrf()) {
    $action = $_POST['action'] ?? '';

    // Öğrenci Ekleme
    if ($action === 'add_player') {
        $name = trim($_POST['name'] ?? '');
        $class_name = trim($_POST['class_name'] ?? '');
        if (!empty($name) && !empty($class_name)) {
            $stmt = $pdo->prepare("INSERT INTO players (name, class_name) VALUES (?, ?)");
            if ($stmt->execute([$name, $class_name])) {
                $message = "\"" . htmlspecialchars($name) . "\" basariyla eklendi.";
                $messageType = 'success';
            }
        } else {
            $message = "Lutfen tum alanlari doldurun.";
            $messageType = 'error';
        }
    }

    // Toplu Öğrenci Ekleme
    elseif ($action === 'bulk_add') {
        $bulk_text = trim($_POST['bulk_text'] ?? '');
        if (!empty($bulk_text)) {
            $lines = array_filter(array_map('trim', explode("\n", $bulk_text)));
            $added = 0;
            $stmt = $pdo->prepare("INSERT INTO players (name, class_name) VALUES (?, ?)");
            foreach ($lines as $line) {
                // Format: "İsim Soyisim - Sınıf" veya "İsim Soyisim, Sınıf" veya "İsim Soyisim | Sınıf"
                $parts = preg_split('/[\-\,\|]/', $line, 2);
                if (count($parts) >= 2) {
                    $name = trim($parts[0]);
                    $class = trim($parts[1]);
                    if (!empty($name) && !empty($class)) {
                        $stmt->execute([$name, $class]);
                        $added++;
                    }
                }
            }
            $message = "$added ogrenci toplu olarak eklendi.";
            $messageType = 'success';
        }
    }

    // Öğrenci Silme
    elseif ($action === 'delete_player') {
        $player_id = (int)($_POST['player_id'] ?? 0);
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM matches WHERE player1_id = ? OR player2_id = ?");
        $checkStmt->execute([$player_id, $player_id]);
        if ($checkStmt->fetchColumn() > 0) {
            $message = "Bu ogrencinin maci oldugu icin silinemez. Deaktif edilebilir.";
            $messageType = 'error';
        } else {
            $stmt = $pdo->prepare("DELETE FROM players WHERE id = ?");
            $stmt->execute([$player_id]);
            $message = "Ogrenci silindi.";
            $messageType = 'success';
        }
    }

    // Turnuva Durumu Güncelleme
    elseif ($action === 'update_status') {
        $new_status = $_POST['new_status'] ?? '';
        if (in_array($new_status, ['basvurular_acik', 'turnuva_basladi', 'turnuva_bitti'])) {
            $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'tournament_status'");
            $stmt->execute([$new_status]);
            $message = "Turnuva durumu guncellendi.";
            $messageType = 'success';
        }
    }

    // Turnuva Sıfırlama
    elseif ($action === 'reset_tournament') {
        $pdo->exec("DELETE FROM matches");
        $pdo->exec("UPDATE players SET points = 0, wins = 0, draws = 0, losses = 0, matches_played = 0");
        $pdo->exec("UPDATE settings SET setting_value = 'basvurular_acik' WHERE setting_key = 'tournament_status'");
        $message = "Turnuva sifirlandi. Tum maclar silindi, puanlar sifirlandi.";
        $messageType = 'success';
    }
}

// Verileri çek
$players = $pdo->query("SELECT * FROM players ORDER BY name ASC")->fetchAll();
$statusStmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'tournament_status'");
$tournament_status = $statusStmt->fetchColumn() ?: 'basvurular_acik';

include 'header.php';
?>

<!-- Page Header -->
<div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
    <div>
        <h2 class="text-2xl font-bold text-gray-900">Yonetim Paneli</h2>
        <p class="text-sm text-gray-500 mt-1">Ogrencileri ve turnuvayi yonetin.</p>
    </div>
    <div class="flex gap-2">
        <a href="matchmaking.php" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-xl hover:bg-blue-700 transition shadow-sm">
            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
            Eslestirme
        </a>
        <a href="results.php" class="inline-flex items-center px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-xl hover:bg-green-700 transition shadow-sm">
            Sonuc Gir
        </a>
    </div>
</div>

<!-- Alert Messages -->
<?php if (!empty($message)): ?>
<div class="mb-6 rounded-xl p-4 flex items-start gap-3 <?php echo $messageType === 'success' ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'; ?>">
    <?php if ($messageType === 'success'): ?>
        <svg class="w-5 h-5 text-green-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
    <?php else: ?>
        <svg class="w-5 h-5 text-red-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
    <?php endif; ?>
    <span class="text-sm font-medium <?php echo $messageType === 'success' ? 'text-green-800' : 'text-red-800'; ?>"><?php echo $message; ?></span>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
    <!-- Sol: Öğrenci Ekleme + Turnuva Kontrolleri -->
    <div class="space-y-6">
        <!-- Öğrenci Ekleme -->
        <div class="card p-5">
            <h3 class="font-semibold text-gray-900 mb-4">Ogrenci Ekle</h3>
            <form action="admin.php" method="POST" class="space-y-3">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="add_player">
                <div>
                    <input type="text" name="name" required placeholder="Ad Soyad"
                           class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <input type="text" name="class_name" required placeholder="Sinif (10-A)"
                           class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <button type="submit" class="w-full bg-gray-900 text-white py-2.5 rounded-xl text-sm font-medium hover:bg-gray-800 transition">
                    Kaydet
                </button>
            </form>
        </div>

        <!-- Toplu Ekleme -->
        <div class="card p-5">
            <h3 class="font-semibold text-gray-900 mb-2">Toplu Ekleme</h3>
            <p class="text-xs text-gray-500 mb-3">Her satira bir ogrenci: <code class="bg-gray-100 px-1 py-0.5 rounded text-xs">Ad Soyad - Sinif</code></p>
            <form action="admin.php" method="POST" class="space-y-3">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="bulk_add">
                <textarea name="bulk_text" rows="5" placeholder="Ali Yilmaz - 10A&#10;Ayse Demir - 11B&#10;Mehmet Kaya - 9C"
                          class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-none"></textarea>
                <button type="submit" class="w-full bg-indigo-600 text-white py-2.5 rounded-xl text-sm font-medium hover:bg-indigo-700 transition">
                    Toplu Ekle
                </button>
            </form>
        </div>

        <!-- Turnuva Kontrolleri -->
        <div class="card p-5">
            <h3 class="font-semibold text-gray-900 mb-4">Turnuva Kontrolu</h3>
            <div class="space-y-3">
                <form action="admin.php" method="POST">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="update_status">
                    <div class="flex gap-2">
                        <select name="new_status" class="flex-1 border border-gray-300 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="basvurular_acik" <?php echo $tournament_status === 'basvurular_acik' ? 'selected' : ''; ?>>Basvurular Acik</option>
                            <option value="turnuva_basladi" <?php echo $tournament_status === 'turnuva_basladi' ? 'selected' : ''; ?>>Turnuva Basladi</option>
                            <option value="turnuva_bitti" <?php echo $tournament_status === 'turnuva_bitti' ? 'selected' : ''; ?>>Tamamlandi</option>
                        </select>
                        <button type="submit" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-xl text-sm font-medium hover:bg-gray-200 transition">
                            Guncelle
                        </button>
                    </div>
                </form>

                <form action="admin.php" method="POST" onsubmit="return confirm('DIKKAT: Tum maclar silinecek ve puanlar sifirlanacak! Devam etmek istiyor musunuz?');">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="reset_tournament">
                    <button type="submit" class="w-full px-4 py-2 bg-red-50 text-red-700 rounded-xl text-sm font-medium hover:bg-red-100 transition border border-red-200">
                        Turnuvayi Sifirla
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Sağ: Öğrenci Listesi -->
    <div class="lg:col-span-2">
        <div class="card overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <h3 class="font-semibold text-gray-900">
                    Kayitli Ogrenciler
                    <span class="ml-2 bg-gray-100 text-gray-600 text-xs font-medium px-2 py-0.5 rounded-full"><?php echo count($players); ?></span>
                </h3>
            </div>

            <?php if (count($players) > 0): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">#</th>
                            <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Isim</th>
                            <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sinif</th>
                            <th class="px-5 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Puan</th>
                            <th class="px-5 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Mac</th>
                            <th class="px-5 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Islem</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($players as $i => $player): ?>
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-5 py-3 text-sm text-gray-400"><?php echo $i + 1; ?></td>
                            <td class="px-5 py-3">
                                <span class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($player['name']); ?></span>
                            </td>
                            <td class="px-5 py-3 text-sm text-gray-500"><?php echo htmlspecialchars($player['class_name']); ?></td>
                            <td class="px-5 py-3 text-sm text-center font-medium text-gray-900"><?php echo number_format($player['points'], 1); ?></td>
                            <td class="px-5 py-3 text-sm text-center text-gray-500"><?php echo $player['matches_played']; ?></td>
                            <td class="px-5 py-3 text-center">
                                <form action="admin.php" method="POST" class="inline" onsubmit="return confirm('Bu ogrenciyi silmek istediginize emin misiniz?');">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="delete_player">
                                    <input type="hidden" name="player_id" value="<?php echo $player['id']; ?>">
                                    <button type="submit" class="text-red-500 hover:text-red-700 text-xs font-medium px-2 py-1 rounded-lg hover:bg-red-50 transition">Sil</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="text-center py-12">
                <p class="text-sm text-gray-500">Henuz kayitli ogrenci bulunmuyor.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
