<?php
session_start();
require 'db.php';
ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL);

// 1. Bảo mật
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') { 
    header("Location: login.php"); 
    exit(); 
}

$adminId = $_SESSION['user_id'];
$adminName = $_SESSION['name'];
// Fix đường dẫn ảnh Admin
$adminAvatar = (!empty($_SESSION['avatar']) && $_SESSION['avatar'] != 'default.png') ? $_SESSION['avatar'] : 'img/default_admin.png';

$chatList = [];
$currentMessages = [];
$receiverId = isset($_GET['receiver_id']) ? $_GET['receiver_id'] : null;

try {
    // 2. Lấy danh sách những người đã nhắn tin
    $stmtList = $pdo->prepare("
        SELECT DISTINCT u.user_id, u.full_name, u.avatar_url, u.role
        FROM Messages m
        JOIN Users u ON (m.sender_id = u.user_id OR m.receiver_id = u.user_id)
        WHERE (m.sender_id = ? OR m.receiver_id = ?) AND u.user_id != ?
    ");
    $stmtList->execute([$adminId, $adminId, $adminId]);
    $chatList = $stmtList->fetchAll();

    // 3. Lấy nội dung hội thoại
    if ($receiverId) {
        $stmtMsg = $pdo->prepare("
            SELECT * FROM Messages 
            WHERE (sender_id = ? AND receiver_id = ?) 
               OR (sender_id = ? AND receiver_id = ?)
            ORDER BY created_at ASC
        ");
        $stmtMsg->execute([$adminId, $receiverId, $receiverId, $adminId]);
        $currentMessages = $stmtMsg->fetchAll();
        
        $stmtUser = $pdo->prepare("SELECT full_name FROM Users WHERE user_id = ?");
        $stmtUser->execute([$receiverId]);
        $receiverName = $stmtUser->fetchColumn();
    }
} catch (PDOException $e) {
    die("Lỗi Database: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pneumo-Care | Admin Messages</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap'); body { font-family: 'Inter', sans-serif; background-color: #f4f7fa; }</style>
</head>
<body class="flex h-screen overflow-hidden text-gray-800">
    <aside class="w-64 bg-white flex flex-col shadow-sm border-r border-gray-100">
        <div class="h-20 flex items-center px-6 border-b border-gray-100">
            <i class="fa-solid fa-lungs text-red-400 text-2xl mr-2"></i>
            <span class="text-xl font-semibold">Pneumo<span class="text-blue-500">-Care</span></span>
        </div>
        <nav class="flex flex-col gap-2 px-4 mt-4">
            <a href="adm_dashboard.php" class="flex items-center gap-4 px-4 py-3 text-gray-500 hover:bg-gray-50 rounded-lg">
                <i class="fa-solid fa-gauge-high w-6 text-center"></i> <span>Dashboard</span>
            </a>
            <a href="adm_patients.php" class="flex items-center gap-4 px-4 py-3 text-gray-500 hover:bg-gray-50 rounded-lg">
                <i class="fa-solid fa-user-group w-6 text-center"></i> <span>Patients</span>
            </a>
            <a href="adm_appointments.php" class="flex items-center gap-4 px-4 py-3 text-gray-500 hover:bg-gray-50 rounded-lg">
                <i class="fa-solid fa-file-lines w-6 text-center"></i> <span>Appointments</span>
            </a>
            <a href="adm_doctors.php" class="flex items-center gap-4 px-4 py-3 text-gray-500 hover:bg-gray-50 rounded-lg">
                <i class="fa-solid fa-user-doctor w-6 text-center"></i> <span>Doctors</span>
            </a>
            <a href="adm_messages.php" class="bg-blue-50 text-blue-600 border-l-4 border-blue-500 flex items-center gap-4 px-4 py-3 rounded-lg font-medium">
                <i class="fa-solid fa-message w-6 text-center"></i> <span>Messages</span>
            </a>
        </nav>
        <div class="mt-auto p-4 border-t border-gray-100">
            <a href="logout.php" class="text-gray-500 hover:text-red-500 flex items-center gap-4 px-4 py-3 transition-colors"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
        </div>
    </aside>

    <main class="flex-1 flex flex-col overflow-hidden">
        <header class="h-20 bg-white border-b border-gray-100 flex items-center justify-between px-8">
            <h1 class="text-xl font-semibold text-gray-700">Messages Center</h1>
            <div class="flex items-center space-x-3">
                <img src="<?php echo $adminAvatar; ?>" 
                    class="w-10 h-10 rounded-full border-2 border-blue-100 object-cover" 
                    alt="Admin Avatar">
                <div>
                    <p class="text-sm font-semibold text-gray-700"><?php echo htmlspecialchars($adminName); ?></p>
                    <p class="text-xs text-gray-400">System Administrator</p>
                </div>
            </div>
        </header>

        <div class="flex-1 flex overflow-hidden">
            <div class="w-80 bg-white border-r border-gray-100 flex flex-col">
                <div class="p-6 border-b border-gray-100">
                    <h2 class="text-lg font-bold text-gray-800">Recent Chats</h2>
                </div>
                <div class="flex-1 overflow-y-auto">
                    <?php foreach ($chatList as $chat): ?>
                    <a href="?receiver_id=<?php echo $chat['user_id']; ?>" 
                       class="flex items-center gap-4 p-4 hover:bg-gray-50 border-b border-gray-50 transition-colors <?php echo $receiverId == $chat['user_id'] ? 'bg-blue-50' : ''; ?>">
                        <img src="<?php echo $chat['avatar_url'] ?: 'https://ui-avatars.com/api/?name='.urlencode($chat['full_name']); ?>" 
                             class="w-12 h-12 rounded-full object-cover shadow-sm">
                        <div class="flex-1">
                            <div class="flex justify-between items-center">
                                <span class="font-semibold text-sm"><?php echo htmlspecialchars($chat['full_name']); ?></span>
                                <span class="text-[9px] uppercase px-2 py-0.5 bg-gray-100 rounded text-gray-400 font-bold"><?php echo $chat['role']; ?></span>
                            </div>
                            <p class="text-xs text-gray-500 truncate mt-1">Nhấp để xem tin nhắn</p>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="flex-1 flex flex-col bg-[#f8fafc]">
                <?php if ($receiverId): ?>
                    <div class="h-16 bg-white border-b border-gray-100 px-8 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <h3 class="font-bold text-gray-800 text-sm"><?php echo htmlspecialchars($receiverName); ?></h3>
                            <span class="w-2 h-2 bg-green-500 rounded-full shadow-[0_0_8px_rgba(34,197,94,0.6)]"></span>
                        </div>
                    </div>

                    <div class="flex-1 overflow-y-auto p-8 space-y-4">
                        <?php foreach ($currentMessages as $msg): ?>
                            <div class="flex <?php echo $msg['sender_id'] == $adminId ? 'justify-end' : 'justify-start'; ?>">
                                <div class="max-w-[70%] p-4 rounded-2xl text-sm shadow-sm <?php echo $msg['sender_id'] == $adminId ? 'bg-blue-500 text-white rounded-tr-none' : 'bg-white text-gray-700 rounded-tl-none'; ?>">
                                    <?php echo htmlspecialchars($msg['message_text']); ?>
                                    <p class="text-[9px] mt-1 opacity-60 text-right"><?php echo date('H:i', strtotime($msg['created_at'])); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="p-6 bg-white border-t border-gray-100">
                        <form class="flex gap-4">
                            <input type="text" placeholder="Nhập tin nhắn..." class="flex-1 bg-gray-50 border-0 rounded-xl px-6 py-3 text-sm outline-none focus:ring-2 focus:ring-blue-100">
                            <button type="button" class="bg-blue-500 text-white w-12 h-12 rounded-xl flex items-center justify-center hover:bg-blue-600 transition-all shadow-md">
                                <i class="fa-solid fa-paper-plane"></i>
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="flex-1 flex flex-col items-center justify-center text-gray-400">
                        <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                            <i class="fa-regular fa-comments text-3xl opacity-20"></i>
                        </div>
                        <p class="text-sm">Chọn một hội thoại để bắt đầu</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>