<?php
// 1. Bật hiển thị lỗi để debug trên Macbook
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require 'db.php'; 

$error = '';

/**
 * Hàm điều hướng người dùng dựa trên vai trò
 * Giúp quản lý tập trung các trang Dashboard của 3 Role
 */
function redirectByRole($role) {
    switch ($role) {
        case 'Admin':
            header("Location: adm_dashboard.php");
            break;
        case 'Doctor':
            header("Location: doc_dashboard.php");
            break;
        case 'Patient':
            header("Location: pat_dashboard.php");
            break;
        default:
            header("Location: login.php");
    }
    exit();
}

// Nếu đã đăng nhập rồi thì không cho ở lại trang login nữa
if (isset($_SESSION['user_id'])) {
    redirectByRole($_SESSION['role']);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Truy vấn kiểm tra User
    $stmt = $pdo->prepare("SELECT * FROM Users WHERE email = ? AND is_active = 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        // Bảo mật: Đổi ID session sau khi login thành công
        session_regenerate_id(true);

        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['avatar'] = (!empty($user['avatar_url'])) ? $user['avatar_url'] : 'img/default_admin.png';
        // Điều hướng đến Dashboard tương ứng
        redirectByRole($user['role']);
    } else {
        $error = "Email hoặc mật khẩu không chính xác!";
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pneumo-Care a| Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap');
        * { font-family: 'Inter', sans-serif; }
        .login-card { box-shadow: 0 25px 50px -12px rgb(37 99 235 / 0.15); }
        .input-field { transition: all 0.3s ease; }
        .input-field:focus { box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.2); transform: translateY(-1px); }
    </style>
</head>
<body class="bg-[#f8fafc]">
    <header class="bg-white border-b border-gray-100">
        <div class="max-w-7xl mx-auto px-8 py-5 flex items-center justify-between">
            <div class="flex items-center gap-x-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-9 h-9" viewBox="0 0 24 24" fill="none" stroke="#ec4899" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 4a4 4 0 0 0-4 4v2a4 4 0 0 0 4 4 4 4 0 0 0 4-4V8a4 4 0 0 0-4-4Z"/>
                    <path d="M8 8v2a4 4 0 0 1-4 4"/><path d="M16 8v2a4 4 0 0 1 4 4"/>
                    <path d="M12 20v-4"/><path d="M4 12h2"/><path d="M18 12h2"/>
                </svg>
                <div class="text-3xl font-bold tracking-tight"><span class="text-gray-900">Pneumo</span><span class="text-[#2563eb]">Care</span></div>
            </div>
            <nav class="flex items-center gap-x-9 text-[15px] font-medium">
                <a href="#" class="text-gray-700 hover:text-gray-900 transition-colors">Home</a>
                <a href="#" class="text-gray-700 hover:text-gray-900 transition-colors">Contact</a>
                <a href="register_patient.php" class="text-[#2563eb] font-semibold text-base">Signup</a>
            </nav>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-8 py-12 flex gap-16 items-center">
        <div class="flex-1 max-w-[460px]">
            <div class="bg-white rounded-3xl login-card p-10">
                <div class="mb-8">
                    <h1 class="text-4xl font-bold text-gray-900 leading-none mb-2">Welcome Back To<br>Pneumo-Care 👋</h1>
                    <?php if($error): ?>
                        <p class="text-red-500 font-medium mt-2"><?php echo htmlspecialchars($error); ?></p>
                    <?php endif; ?>
                </div>

                <form method="POST" action="" class="space-y-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2 tracking-wider">Email Address</label>
                        <input type="email" name="email" required placeholder="Enter your Email" 
                               class="input-field w-full bg-[#f1f5f9] border-0 rounded-3xl px-6 py-4 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2 tracking-wider">Password</label>
                        <div class="relative">
                            <input type="password" id="password" name="password" required placeholder="Enter your Password" 
                                   class="input-field w-full bg-[#f1f5f9] border-0 rounded-3xl px-6 py-4 outline-none pr-12">
                            <button type="button" onclick="togglePassword()" class="absolute right-6 top-1/2 -translate-y-1/2 text-gray-400">
                                <i class="fa-solid fa-eye" id="eyeIcon"></i>
                            </button>
                        </div>
                    </div>
                    <div class="text-sm">
                        <span class="text-gray-600">Don't have an account?</span> 
                        <a href="register_patient.php" class="text-[#2563eb] font-semibold hover:underline">Register here</a>
                    </div>
                    <button type="submit" class="w-full bg-[#2563eb] hover:bg-blue-700 text-white font-semibold text-lg py-4 rounded-3xl mt-4 shadow-lg">
                        Login
                    </button>
                </form>
            </div>
        </div>
        <div class="flex-1 flex items-center justify-center">
            <img src="illustration.png" alt="Illustration" class="max-w-[520px] w-full drop-shadow-2xl">
        </div>
    </main>

    <script>
        function togglePassword() {
            const pwd = document.getElementById('password');
            const icon = document.getElementById('eyeIcon');
            if (pwd.type === 'password') {
                pwd.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                pwd.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }
    </script>
</body>
</html>