<?php
session_start();
require 'db.php'; // Gọi file kết nối database đã tạo ở bước trước

$error = '';

// Nếu đã đăng nhập, tự động chuyển hướng
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'Admin') header("Location: 1_dashboard.php");
    elseif ($_SESSION['role'] === 'Doctor') header("Location: doctor_dashboard.php");
    else header("Location: patient_dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM Users WHERE email = ? AND is_active = 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['name'] = $user['full_name'];
        $_SESSION['avatar'] = $user['avatar_url'] ? $user['avatar_url'] : 'default_avatar.jpg';

        if ($user['role'] === 'Admin') header("Location: 1_dashboard.php");
        elseif ($user['role'] === 'Doctor') header("Location: doctor_dashboard.php");
        else header("Location: patient_dashboard.php");
        exit();
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
    <title>Pneumo-Care | Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap');
        :root { --tw-color-primary: #2563eb; }
        * { font-family: 'Inter', system_ui, sans-serif; }
        .login-card { box-shadow: 0 25px 50px -12px rgb(37 99 235 / 0.15); }
        .input-field { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .input-field:focus { box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.2); transform: translateY(-1px); }
        .eye-icon { cursor: pointer; }
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
                <a href="register_patient.php" class="text-[#2563eb] font-semibold hover:text-blue-700 transition-colors text-base">Signup</a>
            </nav>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-8 py-12 flex gap-16 items-center">
        <div class="flex-1 max-w-[460px]">
            <div class="bg-white rounded-3xl login-card p-10">
                <div class="mb-8">
                    <h1 class="text-4xl font-bold text-gray-900 leading-none mb-2">Welcome Back To<br>Pneumo-Care 👋</h1>
                    <?php if($error): ?>
                        <p class="text-red-500 font-medium mt-2"><?php echo $error; ?></p>
                    <?php endif; ?>
                </div>

                <form method="POST" action="" class="space-y-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2 tracking-wider">Email Address</label>
                        <input type="email" name="email" required placeholder="Enter your Email" class="input-field w-full bg-[#f1f5f9] border-0 rounded-3xl px-6 py-4 text-base placeholder:text-gray-400 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2 tracking-wider">Password</label>
                        <div class="relative">
                            <input type="password" id="password" name="password" required placeholder="Enter your Password" class="input-field w-full bg-[#f1f5f9] border-0 rounded-3xl px-6 py-4 text-base placeholder:text-gray-400 outline-none pr-12">
                            <button type="button" onclick="togglePassword()" class="eye-icon absolute right-6 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                <svg id="eyeIcon" xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5 16.477 5 20.268 7.943 21.542 12 20.268 16.057 16.477 19 12 19 7.523 19 3.732 16.057 2.458 12z" /></svg>
                            </button>
                        </div>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <div class="text-gray-600">Don't have an account? <a href="register_patient.php" class="text-[#2563eb] font-semibold hover:underline">Register here</a></div>
                    </div>
                    <button type="submit" class="w-full bg-[#2563eb] hover:bg-blue-700 active:scale-[0.97] transition-all text-white font-semibold text-lg py-4 rounded-3xl mt-4 shadow-lg shadow-blue-500/30">Login</button>
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
            pwd.type = pwd.type === 'password' ? 'text' : 'password';
        }
    </script>
</body>
</html>