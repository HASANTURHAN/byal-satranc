<?php
// login.php - Görevli / Hakem Giriş Sayfası
require_once 'db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Zaten giriş yapmışsa
if (is_admin()) {
    header("Location: admin.php");
    exit();
}

$error_message = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($username) && !empty($password)) {
        // Veritabanından kullanıcı ara
        $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $user['username'];
            $_SESSION['admin_role'] = $user['role'];
            $_SESSION['admin_id'] = $user['id'];
            header("Location: admin.php");
            exit();
        } else {
            // Fallback: Eğer admin_users tablosu yoksa veya boşsa, eski sistemi dene
            try {
                $checkStmt = $pdo->query("SELECT COUNT(*) FROM admin_users");
                $adminCount = $checkStmt->fetchColumn();
            } catch (Exception $e) {
                $adminCount = 0;
            }

            if ($adminCount == 0 && $password === 'kurnazgezi26') {
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_username'] = 'admin';
                $_SESSION['admin_role'] = 'admin';
                header("Location: admin.php");
                exit();
            }

            $error_message = "Hatalı kullanıcı adı veya şifre.";
        }
    } else {
        $error_message = "Lütfen tüm alanları doldurun.";
    }
}

include 'header.php';
?>

<div class="min-h-[60vh] flex flex-col justify-center py-12 sm:px-6 lg:px-8">
    <div class="sm:mx-auto sm:w-full sm:max-w-md text-center">
        <div class="text-6xl mb-4">&#9812;</div>
        <h2 class="text-3xl font-extrabold text-gray-900">Görevli Girişi</h2>
        <p class="mt-2 text-sm text-gray-600">
            Turnuva yönetim paneline erişmek için bilgilerinizi girin
        </p>
    </div>

    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
        <div class="bg-white py-8 px-6 shadow-lg rounded-2xl border border-gray-100">

            <?php if (!empty($error_message)): ?>
                <div class="rounded-xl bg-red-50 p-4 mb-6 border border-red-200">
                    <div class="flex items-center">
                        <svg class="h-5 w-5 text-red-500 mr-3" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                        </svg>
                        <span class="text-sm font-medium text-red-800"><?php echo htmlspecialchars($error_message); ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <form class="space-y-5" action="login.php" method="POST">
                <div>
                    <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Kullanıcı Adı</label>
                    <input id="username" name="username" type="text" required autocomplete="username"
                           class="block w-full px-4 py-3 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                           placeholder="Kullanıcı adınız">
                </div>
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Şifre</label>
                    <input id="password" name="password" type="password" required autocomplete="current-password"
                           class="block w-full px-4 py-3 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                           placeholder="Şifrenizi girin">
                </div>
                <button type="submit"
                        class="w-full flex justify-center py-3 px-4 rounded-xl text-sm font-semibold text-white bg-gray-900 hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-900 transition shadow-lg shadow-gray-900/20">
                    Giriş Yap
                </button>
            </form>
        </div>

        <div class="mt-6 text-center">
            <a href="index.php" class="text-sm font-medium text-blue-600 hover:text-blue-500 transition">
                &larr; Ana Sayfaya Dön
            </a>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
