<?php
// setup.php - Veritabanı kurulum dosyası
require_once 'db.php';

// Kurulum zaten yapıldıysa yönlendir
$check = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='admin_users'");
$adminTableExists = $check->fetch();

if ($adminTableExists) {
    $adminCheck = $pdo->query("SELECT COUNT(*) FROM admin_users");
    if ($adminCheck->fetchColumn() > 0 && !isset($_GET['force'])) {
        header("Location: index.php");
        exit();
    }
}

$message = '';
$step = 1;

// Tabloları oluştur
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS players (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        class_name TEXT NOT NULL,
        points REAL DEFAULT 0.0,
        wins INTEGER DEFAULT 0,
        draws INTEGER DEFAULT 0,
        losses INTEGER DEFAULT 0,
        matches_played INTEGER DEFAULT 0,
        is_active INTEGER DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS matches (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        round_number INTEGER NOT NULL,
        player1_id INTEGER,
        player2_id INTEGER,
        result TEXT,
        status TEXT DEFAULT 'pending',
        played_at DATETIME,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (player1_id) REFERENCES players(id),
        FOREIGN KEY (player2_id) REFERENCES players(id)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        setting_key TEXT PRIMARY KEY,
        setting_value TEXT NOT NULL
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        role TEXT DEFAULT 'hakem',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Varsayılan ayarlar
    $pdo->exec("INSERT OR IGNORE INTO settings (setting_key, setting_value) VALUES ('tournament_status', 'basvurular_acik')");
    $pdo->exec("INSERT OR IGNORE INTO settings (setting_key, setting_value) VALUES ('tournament_name', 'Sultangazi BYAL Satranç Turnuvası')");
    $pdo->exec("INSERT OR IGNORE INTO settings (setting_key, setting_value) VALUES ('deadline', '4 Mart 2026')");
    $pdo->exec("INSERT OR IGNORE INTO settings (setting_key, setting_value) VALUES ('total_rounds', '5')");

    // Players tablosuna eksik kolonları ekle (migration)
    try { $pdo->exec("ALTER TABLE players ADD COLUMN wins INTEGER DEFAULT 0"); } catch(Exception $e) {}
    try { $pdo->exec("ALTER TABLE players ADD COLUMN draws INTEGER DEFAULT 0"); } catch(Exception $e) {}
    try { $pdo->exec("ALTER TABLE players ADD COLUMN losses INTEGER DEFAULT 0"); } catch(Exception $e) {}
    try { $pdo->exec("ALTER TABLE players ADD COLUMN is_active INTEGER DEFAULT 1"); } catch(Exception $e) {}

    $step = 2;
} catch (PDOException $e) {
    $message = "Tablo oluşturma hatası: " . $e->getMessage();
}

// Admin kullanıcı oluştur
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_admin'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'hakem';

    if (strlen($username) < 3 || strlen($password) < 4) {
        $message = "Kullanıcı adı en az 3, şifre en az 4 karakter olmalıdır.";
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT OR REPLACE INTO admin_users (username, password_hash, role) VALUES (?, ?, ?)");
        if ($stmt->execute([$username, $hash, $role])) {
            $message = "Admin kullanıcı oluşturuldu! Giriş yapabilirsiniz.";
            $step = 3;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kurulum - BYAL Satranç</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center font-[Inter]">
<div class="max-w-lg w-full mx-4">
    <div class="bg-white rounded-2xl shadow-xl border border-gray-200 overflow-hidden">
        <div class="bg-gray-900 px-8 py-6 text-center">
            <h1 class="text-2xl font-bold text-white">BYAL Satranc Turnuvasi Kurulumu</h1>
            <p class="text-gray-400 mt-1 text-sm">Veritabani ve admin ayarlari</p>
        </div>
        <div class="p-8">
            <?php if ($message): ?>
                <div class="mb-6 p-4 rounded-lg <?php echo $step === 3 ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200'; ?> text-sm">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if ($step === 1): ?>
                <p class="text-gray-600 mb-4">Veritabani tablolari olusturuluyor...</p>
            <?php elseif ($step === 2): ?>
                <div class="mb-6 p-3 bg-green-50 rounded-lg text-green-700 text-sm border border-green-200">
                    Tablolar basariyla olusturuldu!
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Admin Kullanici Olustur</h3>
                <form method="POST" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Kullanici Adi</label>
                        <input type="text" name="username" required minlength="3"
                               class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="admin">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Sifre</label>
                        <input type="password" name="password" required minlength="4"
                               class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="En az 4 karakter">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Rol</label>
                        <select name="role" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="admin">Admin (Tam Yetki)</option>
                            <option value="hakem">Hakem (Sonuc Girisi)</option>
                        </select>
                    </div>
                    <button type="submit" name="create_admin" value="1"
                            class="w-full bg-gray-900 text-white py-2.5 rounded-lg font-medium hover:bg-gray-800 transition">
                        Kullanici Olustur
                    </button>
                </form>
            <?php elseif ($step === 3): ?>
                <div class="text-center py-4">
                    <div class="text-5xl mb-4">&#9812;</div>
                    <p class="text-gray-600 mb-6">Kurulum tamamlandi! Simdi giris yapabilirsiniz.</p>
                    <a href="login.php" class="inline-block bg-blue-600 text-white px-8 py-3 rounded-lg font-medium hover:bg-blue-700 transition">
                        Giris Yap
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
