<?php
// db.php - Veritabanı bağlantı dosyası

// Session güvenlik ayarları
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', 1);
$db_file = __DIR__ . '/database.sqlite';

try {
    $pdo = new PDO('sqlite:' . $db_file);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA journal_mode=WAL');
    $pdo->exec('PRAGMA foreign_keys=ON');
    $pdo->exec("PRAGMA encoding = 'UTF-8'");
} catch (PDOException $e) {
    error_log("DB bağlantı hatası: " . $e->getMessage());
    die("Veritabanı bağlantı hatası. Lütfen yöneticiyle iletişime geçin.");
}

// Yeni tablo şeması - migration
try {
    // Yeni players tablosu (eski varsa korunur, eksik kolonlar eklenir)
    $pdo->exec("CREATE TABLE IF NOT EXISTS players (
        id INTEGER PRIMARY KEY,
        name TEXT NOT NULL,
        sinif TEXT,
        school_no TEXT UNIQUE,
        is_seed INTEGER DEFAULT 0,
        phone TEXT,
        photo_path TEXT,
        total_points REAL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Rounds tablosu
    $pdo->exec("CREATE TABLE IF NOT EXISTS rounds (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        round_number INTEGER NOT NULL,
        is_active INTEGER DEFAULT 1
    )");

    // Pairings tablosu
    $pdo->exec("CREATE TABLE IF NOT EXISTS pairings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        round INTEGER NOT NULL,
        table_no INTEGER NOT NULL,
        white_player_id INTEGER REFERENCES players(id),
        black_player_id INTEGER REFERENCES players(id),
        is_seed_table INTEGER DEFAULT 0,
        result TEXT,
        white_points REAL,
        black_points REAL,
        white_photo TEXT,
        black_photo TEXT,
        played_at DATETIME
    )");

    // Admin users tablosu
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        role TEXT DEFAULT 'hakem',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Settings tablosu
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        setting_key TEXT PRIMARY KEY,
        setting_value TEXT NOT NULL
    )");

    // Migration: eksik kolonları ekle (hata varsa yoksay)
    $migrations = [
        "ALTER TABLE players ADD COLUMN sinif TEXT",
        "ALTER TABLE players ADD COLUMN school_no TEXT",
        "ALTER TABLE players ADD COLUMN is_seed INTEGER DEFAULT 0",
        "ALTER TABLE players ADD COLUMN phone TEXT",
        "ALTER TABLE players ADD COLUMN photo_path TEXT",
        "ALTER TABLE players ADD COLUMN total_points REAL DEFAULT 0",
        "ALTER TABLE pairings ADD COLUMN white_photo TEXT",
        "ALTER TABLE pairings ADD COLUMN black_photo TEXT",
        "ALTER TABLE pairings ADD COLUMN is_seed_table INTEGER DEFAULT 0",
        "ALTER TABLE pairings ADD COLUMN match_date TEXT",
        "ALTER TABLE pairings ADD COLUMN match_time TEXT",
    ];
    foreach ($migrations as $sql) {
        try { $pdo->exec($sql); } catch (Exception $e) {}
    }

} catch (PDOException $e) {
    // Tablo zaten varsa sorun yok
}

// CSRF token helper
function csrf_token() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function verify_csrf() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $token = $_POST['csrf_token'] ?? '';
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function is_admin() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

function is_super_admin() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    return is_admin() && isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'admin';
}

// Turnuva ayarını oku
function get_setting($key, $default = '') {
    global $pdo;
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? $row['setting_value'] : $default;
}

// Turnuva ayarını yaz
function set_setting($key, $value) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT OR REPLACE INTO settings (setting_key, setting_value) VALUES (?, ?)");
    $stmt->execute([$key, $value]);
}
