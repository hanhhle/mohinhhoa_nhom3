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
$adminAvatar = (!empty($_SESSION['avatar']) && $_SESSION['avatar'] != 'default.png') ? $_SESSION['avatar'] : 'img/default_admin.png';

$receiverId = isset($_GET['receiver_id']) ? $_GET['receiver_id'] : null;

// 2. Xử lý Gửi tin nhắn
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['message_content']) && $receiverId) {
    $msgText = trim($_POST['message_content']);
    if (!empty($msgText)) {
        try {
            $stmtInsert = $pdo->prepare("INSERT INTO Messages (sender_id, receiver_id, message_content) VALUES (?, ?, ?)");
            $stmtInsert->execute([$adminId, $receiverId, $msgText]);
            header("Location: adm_messages.php?receiver_id=" . $receiverId);
            exit();
        } catch (PDOException $e) {}
    }
}

// 3. Đánh dấu đã đọc khi Admin mở tin nhắn của một người
if ($receiverId) {
    try {
        $stmtRead = $pdo->prepare("UPDATE Messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ?");
        $stmtRead->execute([$receiverId, $adminId]);
    } catch (PDOException $e) {}
}

$chatList = [];
$currentMessages = [];
$receiverName = "";
$receiverInfo = null;

try {
    // 4. Lấy danh sách chat (Kèm theo Tin nhắn cuối & Số tin chưa đọc)
    $stmtList = $pdo->prepare("
        SELECT 
            u.user_id, u.full_name, u.avatar_url, u.role,
            (SELECT message_content FROM Messages 
             WHERE (sender_id = u.user_id AND receiver_id = :admin1) 
                OR (sender_id = :admin2 AND receiver_id = u.user_id) 
             ORDER BY sent_at DESC LIMIT 1) as last_message,
            (SELECT COUNT(*) FROM Messages 
             WHERE sender_id = u.user_id AND receiver_id = :admin3 AND is_read = 0) as unread_count
        FROM Users u
        WHERE u.user_id IN (
            SELECT sender_id FROM Messages WHERE receiver_id = :admin4
            UNION
            SELECT receiver_id FROM Messages WHERE sender_id = :admin5
        ) AND u.user_id != :admin6
        ORDER BY (SELECT MAX(sent_at) FROM Messages WHERE (sender_id = u.user_id AND receiver_id = :admin7) OR (sender_id = :admin8 AND receiver_id = u.user_id)) DESC
    ");
    $stmtList->execute([
        ':admin1' => $adminId, ':admin2' => $adminId, 
        ':admin3' => $adminId, ':admin4' => $adminId, 
        ':admin5' => $adminId, ':admin6' => $adminId,
        ':admin7' => $adminId, ':admin8' => $adminId
    ]);
    $chatList = $stmtList->fetchAll();

    // 5. Lấy nội dung hội thoại chi tiết và thông tin người nhận
    if ($receiverId) {
        $stmtMsg = $pdo->prepare("
            SELECT * FROM Messages 
            WHERE (sender_id = ? AND receiver_id = ?) 
               OR (sender_id = ? AND receiver_id = ?)
            ORDER BY sent_at ASC
        ");
        $stmtMsg->execute([$adminId, $receiverId, $receiverId, $adminId]);
        $currentMessages = $stmtMsg->fetchAll();
        
        $stmtUser = $pdo->prepare("SELECT full_name, avatar_url, role FROM Users WHERE user_id = ?");
        $stmtUser->execute([$receiverId]);
        $receiverInfo = $stmtUser->fetch();
        $receiverName = $receiverInfo['full_name'] ?? '';
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
    <title>Pneumo-Care | Messages Center</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background-color: #f4f7fa; color: #1f2937; }

        .layout { display: flex; min-height: 100vh; overflow: hidden; }
        
        /* SIDEBAR CHUẨN ĐỒNG BỘ */
        .sidebar { width: 260px; background: #ffffff; border-right: 1px solid #e5e7eb; display: flex; flex-direction: column; min-height: 100vh; flex-shrink: 0; z-index: 10; }
        .sidebar-active { background-color: #eff6ff; color: #2563eb; border-left: 4px solid #2563eb; font-weight: 600; }

        /* MAIN CONTENT */
        .main-content { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
        .topbar-wrapper { padding: 32px 40px 0 40px; }
        .topbar { 
            height: 72px; background: #ffffff; border: 1px solid #f3f4f6; 
            display: flex; align-items: center; justify-content: space-between; 
            padding: 0 24px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); 
            margin-bottom: 24px;
        }
        .topbar h1 { font-size: 22px; font-weight: 600; color: #1f2937; margin: 0; }
        .content-area { padding: 0 40px 40px 40px; flex: 1; overflow-y: auto; display: flex; flex-direction: column; }

        /* Scrollbar tinh chỉnh cho khung chat */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    </style>
</head>
<body class="flex h-screen overflow-hidden text-gray-800 relative">
<div class="flex w-full h-full relative">

    <aside class="w-64 bg-white border-r border-gray-200 flex flex-col h-full flex-shrink-0 z-10 shadow-sm">
        <div class="h-20 flex items-center px-6 border-b border-gray-100">
            <i class="fa-solid fa-lungs text-red-400 text-2xl mr-2"></i>
            <span class="text-xl font-semibold">Pneumo<span class="text-blue-500">-Care</span></span>
        </div>

        <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto">
            <a href="adm_dashboard.php" class="flex items-center gap-4 px-4 py-3 text-gray-500 hover:bg-gray-50 hover:text-gray-800 rounded-xl transition-colors font-medium">
                <i class="fa-solid fa-gauge-high w-5 text-center text-xl"></i><span>Dashboard</span>
            </a>
            <a href="adm_patients.php" class="flex items-center gap-4 px-4 py-3 text-gray-500 hover:bg-gray-50 hover:text-gray-800 rounded-xl transition-colors font-medium">
                <i class="fa-solid fa-user-group w-5 text-center text-xl"></i><span>Patients</span>
            </a>
            <a href="adm_appointments.php" class="flex items-center gap-4 px-4 py-3 text-gray-500 hover:bg-gray-50 hover:text-gray-800 rounded-xl transition-colors font-medium">
                <i class="fa-solid fa-file-lines w-5 text-center text-xl"></i><span>Appointments</span>
            </a>
            <a href="adm_doctors.php" class="flex items-center gap-4 px-4 py-3 text-gray-500 hover:bg-gray-50 hover:text-gray-800 rounded-xl transition-colors font-medium">
                <i class="fa-solid fa-user-doctor w-5 text-center text-xl"></i><span>Doctors</span>
            </a>
            <a href="adm_messages.php" class="sidebar-active flex items-center gap-4 px-4 py-3 rounded-xl font-semibold transition-colors">
                <i class="fa-solid fa-message w-5 text-center text-xl"></i><span>Messages</span>
            </a>
        </nav>

        <div class="p-6 border-t mt-auto border-gray-100">
            <a href="logout.php" class="flex items-center gap-4 text-gray-500 hover:text-red-500 transition-colors font-medium">
                <i class="fa-solid fa-right-from-bracket text-xl"></i><span>Logout</span>
            </a>
        </div>
    </aside>

    <main class="main-content bg-[#f4f7fa]">
        
        <div class="topbar-wrapper flex-shrink-0">
            <header class="topbar">
                <h1>Messages Center</h1>
                <div class="flex items-center gap-6">
                    <div class="flex items-center gap-3 cursor-pointer">
                        <div class="text-right hidden sm:block">
                            <p class="text-sm font-semibold text-gray-800" style="line-height: 1.2;"><?php echo htmlspecialchars($adminName); ?></p>
                            <p class="text-xs text-gray-500 font-medium">Administrator</p>
                        </div>
                        <img src="<?php echo $adminAvatar; ?>" class="w-10 h-10 rounded-full object-cover border border-gray-200 shadow-sm" alt="Admin">
                    </div>
                </div>
            </header>
        </div>

        <div class="content-area max-w-7xl mx-auto w-full pb-10">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 flex flex-1 overflow-hidden min-h-[650px] max-h-[800px]">
                
                <div class="w-[340px] bg-gray-50/50 border-r border-gray-100 flex flex-col flex-shrink-0">
                    <div class="p-6 border-b border-gray-100 bg-white">
                        <h2 class="font-bold text-gray-800 text-[14px] uppercase tracking-widest mb-4">Recent Chats</h2>
                        <div class="relative">
                            <input type="text" placeholder="Search messages..." class="w-full bg-gray-50 border border-gray-200 rounded-xl py-2 px-4 pl-10 text-sm focus:border-blue-400 outline-none transition-colors shadow-sm">
                            <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                        </div>
                    </div>
                    
                    <div class="flex-1 overflow-y-auto p-3 space-y-1">
                        <?php if (empty($chatList)): ?>
                            <div class="p-8 text-center text-gray-400 text-sm italic mt-10">
                                <i class="fa-regular fa-comments text-3xl opacity-30 mb-3 block"></i>
                                No active conversations.
                            </div>
                        <?php else: ?>
                            <?php foreach ($chatList as $chat): ?>
                            <a href="?receiver_id=<?php echo $chat['user_id']; ?>" 
                               class="flex items-center gap-3 p-3 rounded-xl transition-all border <?php echo $receiverId == $chat['user_id'] ? 'bg-blue-50/60 border-blue-200 shadow-sm' : 'border-transparent hover:bg-white hover:border-gray-100 hover:shadow-sm'; ?>">
                                
                                <div class="relative flex-shrink-0">
                                    <img src="<?php echo $chat['avatar_url'] ?: 'img/default.png'; ?>" class="w-12 h-12 rounded-full object-cover border border-gray-100 shadow-sm">
                                    <?php if($chat['unread_count'] > 0): ?>
                                        <span class="absolute -top-1 -right-1 bg-red-500 text-white text-[9px] font-bold px-1.5 py-0.5 rounded-full min-w-[18px] text-center shadow-sm border border-white">
                                            <?php echo $chat['unread_count']; ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="flex-1 min-w-0">
                                    <div class="flex justify-between items-baseline mb-0.5">
                                        <span class="text-sm truncate block <?php echo $chat['unread_count'] > 0 ? 'font-extrabold text-gray-900' : 'font-bold text-gray-800'; ?>">
                                            <?php echo htmlspecialchars($chat['full_name']); ?>
                                        </span>
                                        <span class="text-[9px] uppercase px-1.5 py-0.5 rounded font-extrabold tracking-wider ml-2 flex-shrink-0 <?php echo $chat['role'] == 'Doctor' ? 'bg-blue-100 text-blue-600' : 'bg-green-100 text-green-600'; ?>">
                                            <?php echo $chat['role']; ?>
                                        </span>
                                    </div>
                                    <p class="text-xs truncate <?php echo $chat['unread_count'] > 0 ? 'text-gray-900 font-bold' : 'text-gray-500 font-medium'; ?>">
                                        <?php echo $chat['last_message'] ? htmlspecialchars($chat['last_message']) : '<span class="italic text-gray-400">Click to start chatting</span>'; ?>
                                    </p>
                                </div>
                            </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="flex-1 flex flex-col bg-white relative">
                    <?php if ($receiverId && $receiverInfo): ?>
                        <div class="h-[76px] bg-white border-b border-gray-100 px-8 flex items-center justify-between flex-shrink-0 shadow-[0_4px_20px_-15px_rgba(0,0,0,0.1)] z-10">
                            <div class="flex items-center gap-4">
                                <div class="relative">
                                    <img src="<?php echo $receiverInfo['avatar_url'] ?: 'img/default.png'; ?>" class="w-10 h-10 rounded-full object-cover border border-gray-100 shadow-sm">
                                    <span class="absolute bottom-0 right-0 w-2.5 h-2.5 bg-green-500 border-2 border-white rounded-full"></span>
                                </div>
                                <div>
                                    <h3 class="font-bold text-gray-800 text-[15px]"><?php echo htmlspecialchars($receiverName); ?></h3>
                                    <p class="text-[11px] font-semibold uppercase tracking-widest mt-0.5 <?php echo $receiverInfo['role'] == 'Doctor' ? 'text-blue-500' : 'text-green-500'; ?>"><?php echo htmlspecialchars($receiverInfo['role']); ?></p>
                                </div>
                            </div>
                            <div class="flex gap-3">
                                <button class="w-9 h-9 flex items-center justify-center rounded-full bg-gray-50 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-colors"><i class="fa-solid fa-phone"></i></button>
                                <button class="w-9 h-9 flex items-center justify-center rounded-full bg-gray-50 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-colors"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                            </div>
                        </div>

                        <div class="flex-1 overflow-y-auto p-8 space-y-5 bg-[#f8fafc]" id="chatBox">
                            <?php if(empty($currentMessages)): ?>
                                <div class="flex flex-col items-center justify-center h-full text-gray-400">
                                    <i class="fa-solid fa-hand-sparkles text-4xl text-blue-200 mb-3 block"></i>
                                    <p class="text-sm font-medium">This is the start of your conversation with <?php echo htmlspecialchars($receiverName); ?>.</p>
                                </div>
                            <?php endif; ?>

                            <?php foreach ($currentMessages as $msg): ?>
                                <div class="flex <?php echo $msg['sender_id'] == $adminId ? 'justify-end' : 'justify-start'; ?>">
                                    <div class="max-w-[65%] flex flex-col <?php echo $msg['sender_id'] == $adminId ? 'items-end' : 'items-start'; ?>">
                                        <div class="p-4 rounded-2xl text-[14px] shadow-sm leading-relaxed <?php echo $msg['sender_id'] == $adminId ? 'bg-blue-600 text-white rounded-tr-sm' : 'bg-white text-gray-800 rounded-tl-sm border border-gray-100'; ?>">
                                            <?php echo nl2br(htmlspecialchars($msg['message_content'])); ?>
                                        </div>
                                        <span class="text-[10px] font-semibold text-gray-400 mt-1.5 uppercase tracking-wider px-1">
                                            <?php echo date('H:i', strtotime($msg['sent_at'])); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="p-6 bg-white border-t border-gray-100 flex-shrink-0">
                            <form method="POST" action="?receiver_id=<?php echo $receiverId; ?>" class="flex items-center gap-4">
                                <button type="button" class="w-10 h-10 flex items-center justify-center rounded-full text-gray-400 hover:bg-gray-50 hover:text-blue-500 transition-colors flex-shrink-0">
                                    <i class="fa-solid fa-paperclip text-lg"></i>
                                </button>
                                <input type="text" name="message_content" required autocomplete="off" placeholder="Type your message here..." class="flex-1 bg-gray-50 border border-gray-200 rounded-xl px-5 py-3.5 text-sm font-medium outline-none focus:ring-2 focus:ring-blue-100 focus:border-blue-400 focus:bg-white transition-all">
                                <button type="submit" class="bg-blue-600 text-white px-6 py-3.5 rounded-xl font-bold uppercase tracking-wider text-xs hover:bg-blue-700 transition-all shadow-md flex items-center gap-2 flex-shrink-0">
                                    Send <i class="fa-solid fa-paper-plane"></i>
                                </button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="flex-1 flex flex-col items-center justify-center text-gray-400 bg-[#f8fafc]">
                            <div class="w-24 h-24 bg-white rounded-full flex items-center justify-center mb-6 shadow-sm border border-gray-50">
                                <i class="fa-regular fa-comments text-4xl text-gray-300"></i>
                            </div>
                            <h3 class="text-lg font-bold text-gray-600 mb-1">Your Messages</h3>
                            <p class="text-sm font-medium">Select a patient or doctor from the list to start chatting.</p>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </main>

</div>

<script>
    // Tự động cuộn xuống tin nhắn mới nhất
    const chatBox = document.getElementById('chatBox');
    if (chatBox) { chatBox.scrollTop = chatBox.scrollHeight; }
</script>

</body>
</html>