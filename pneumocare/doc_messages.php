<?php
// ==========================================
// TÊN FILE: doc_messages.php
// CHỨC NĂNG: Nhắn tin (Có fix Logo Lá Phổi chuẩn + Nút Upload File/Ảnh)
// ==========================================
session_start();
require 'db.php';
ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL);

// 1. Kiểm tra quyền Bác sĩ
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Doctor') { 
    header("Location: login.php"); 
    exit(); 
}

$doctorId = $_SESSION['user_id'];
$doctorName = $_SESSION['name'];
$doctorAvatar = (!empty($_SESSION['avatar']) && $_SESSION['avatar'] != 'default.png') ? $_SESSION['avatar'] : 'img/default.png';

$receiverId = isset($_GET['receiver_id']) ? $_GET['receiver_id'] : null;

// 2. Xử lý Gửi tin nhắn & Upload File
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $receiverId) {
    $msgText = isset($_POST['message_content']) ? trim($_POST['message_content']) : '';
    
    // Xử lý nôm na tên file để thêm vào tin nhắn (Prototype UI)
    if (isset($_FILES['attachment_file']) && $_FILES['attachment_file']['error'] == UPLOAD_ERR_OK) {
        $fileName = $_FILES['attachment_file']['name'];
        $msgText .= ($msgText !== '' ? "\n" : "") . "📎 [Đã đính kèm tệp: " . $fileName . "]";
    }
    if (isset($_FILES['attachment_image']) && $_FILES['attachment_image']['error'] == UPLOAD_ERR_OK) {
        $imgName = $_FILES['attachment_image']['name'];
        $msgText .= ($msgText !== '' ? "\n" : "") . "🖼️ [Đã đính kèm ảnh: " . $imgName . "]";
    }

    if (!empty($msgText)) {
        try {
            $stmtInsert = $pdo->prepare("INSERT INTO Messages (sender_id, receiver_id, message_content) VALUES (?, ?, ?)");
            $stmtInsert->execute([$doctorId, $receiverId, $msgText]);
            header("Location: doc_messages.php?receiver_id=" . $receiverId);
            exit();
        } catch (PDOException $e) {
            die("Lỗi Database: " . $e->getMessage());
        }
    }
}

// 3. Đánh dấu đã đọc
if ($receiverId) {
    try {
        $stmtRead = $pdo->prepare("UPDATE Messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ?");
        $stmtRead->execute([$receiverId, $doctorId]);
    } catch (PDOException $e) {}
}

$chatList = [];
$currentMessages = [];
$receiverName = "";
$receiverRole = "";

try {
    // 4. Lấy danh sách chat (Kèm đếm tin nhắn chưa đọc)
    $stmtList = $pdo->prepare("
        SELECT 
            u.user_id, u.full_name, u.avatar_url, u.role,
            (SELECT message_content FROM Messages 
             WHERE (sender_id = u.user_id AND receiver_id = :doc1) 
                OR (sender_id = :doc2 AND receiver_id = u.user_id) 
             ORDER BY sent_at DESC LIMIT 1) as last_message,
            (SELECT COUNT(*) FROM Messages 
             WHERE sender_id = u.user_id AND receiver_id = :doc3 AND is_read = 0) as unread_count
        FROM Users u
        WHERE u.user_id IN (
            SELECT sender_id FROM Messages WHERE receiver_id = :doc4
            UNION
            SELECT receiver_id FROM Messages WHERE sender_id = :doc5
        ) AND u.user_id != :doc6
        ORDER BY (SELECT MAX(sent_at) FROM Messages WHERE (sender_id = u.user_id AND receiver_id = :doc7) OR (sender_id = :doc8 AND receiver_id = u.user_id)) DESC
    ");
    $stmtList->execute([
        ':doc1' => $doctorId, ':doc2' => $doctorId, 
        ':doc3' => $doctorId, ':doc4' => $doctorId, 
        ':doc5' => $doctorId, ':doc6' => $doctorId,
        ':doc7' => $doctorId, ':doc8' => $doctorId
    ]);
    $chatList = $stmtList->fetchAll();

    // 5. Lấy nội dung hội thoại
    if ($receiverId) {
        $stmtMsg = $pdo->prepare("
            SELECT * FROM Messages 
            WHERE (sender_id = ? AND receiver_id = ?) 
               OR (sender_id = ? AND receiver_id = ?)
            ORDER BY sent_at ASC
        ");
        $stmtMsg->execute([$doctorId, $receiverId, $receiverId, $doctorId]);
        $currentMessages = $stmtMsg->fetchAll();
        
        $stmtUser = $pdo->prepare("SELECT full_name, role FROM Users WHERE user_id = ?");
        $stmtUser->execute([$receiverId]);
        $receiverInfo = $stmtUser->fetch();
        if ($receiverInfo) {
            $receiverName = $receiverInfo['role'] == 'Admin' ? "Pneumo-Care Support" : $receiverInfo['full_name'];
            $receiverRole = $receiverInfo['role'] == 'Admin' ? "System Administrator" : "Patient";
        }
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
    <title>Pneumo-Care | Doctor Messages</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
        .sidebar-active { background-color: #eff6ff; color: #2563eb; border-left: 4px solid #2563eb; }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #cbd5e1; }
    </style>
</head>
<body class="flex h-screen overflow-hidden text-gray-800">

    <aside class="w-64 bg-white border-r border-gray-100 flex flex-col h-full shadow-sm">
        <div class="flex items-center gap-2 p-6 border-b">
            <i class="fa-solid fa-lungs text-3xl text-red-400"></i>
            <h1 class="text-xl font-semibold text-gray-700">Pneumo-<span class="text-blue-500">Care</span></h1>
        </div>

        <nav class="flex-1 px-4 py-6 space-y-1">
            <a href="doc_dashboard.php" class="flex items-center gap-4 px-4 py-3 text-gray-500 hover:bg-gray-50 rounded-lg transition-colors">
                <i class="fa-solid fa-gauge-high w-5"></i>
                <span>Dashboard</span>
            </a>
            <a href="doc_patient_list.php" class="flex items-center gap-4 px-4 py-3 text-gray-500 hover:bg-gray-50 rounded-lg transition-colors">
                <i class="fa-solid fa-users w-5"></i>
                <span>Patient</span>
            </a>
            <a href="doc_appointments.php" class="flex items-center gap-4 px-4 py-3 text-gray-500 hover:bg-gray-50 rounded-lg transition-colors">
                <i class="fa-solid fa-calendar-check w-5"></i>
                <span>Appointments</span>
            </a>
            <a href="doc_ai_workspace.php" class="flex items-center gap-4 px-4 py-3 text-gray-500 hover:bg-gray-50 rounded-lg transition-colors">
                <i class="fa-solid fa-brain w-5"></i>
                <span>Diagnosis</span>
            </a>
            <a href="doc_messages.php" class="sidebar-active flex items-center gap-4 px-4 py-3 rounded-lg font-medium">
                <i class="fa-solid fa-comment-dots w-5"></i>
                <span>Messages</span>
            </a>
        </nav>

        <div class="p-6 border-t mt-auto">
            <a href="logout.php" class="flex items-center gap-4 text-gray-500 hover:text-red-500 transition-colors font-medium">
                <i class="fa-solid fa-right-from-bracket"></i>
                <span>Logout</span>
            </a>
        </div>
    </aside>

    <main class="flex-1 flex flex-col overflow-hidden bg-[#f4f7fa]">
        
        <div class="px-10 pt-8 pb-6 flex-shrink-0">
            <header class="h-[72px] bg-white border border-gray-100 rounded-xl shadow-sm flex items-center justify-between px-6">
                <h2 class="text-2xl font-semibold text-gray-700">Messages</h2>
                <div class="flex items-center gap-6">
                    <div class="flex items-center gap-3">
                        <div class="text-right hidden sm:block">
                            <p class="text-sm font-semibold text-gray-800" style="line-height: 1.2;"><?php echo htmlspecialchars($doctorName); ?></p>
                            <p class="text-xs text-gray-500 font-medium">Doctor</p>
                        </div>
                        <img src="<?php echo $doctorAvatar; ?>" class="w-10 h-10 rounded-full border border-gray-200 shadow-sm object-cover" alt="Doctor">
                    </div>
                </div>
            </header>
        </div>

        <div class="flex-1 flex overflow-hidden px-10 pb-10 relative">
            
            <div class="flex flex-1 h-full bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="w-[340px] border-r border-gray-100 flex flex-col flex-shrink-0">
                    <div class="p-6 border-b border-gray-100">
                        <div class="relative">
                            <i class="fa-solid fa-magnifying-glass absolute left-4 top-3.5 text-gray-400"></i>
                            <input type="text" placeholder="Search conversations..." class="w-full bg-gray-50 border border-gray-200 rounded-xl py-3 pl-12 pr-4 focus:outline-none focus:border-blue-400 text-sm transition-colors">
                        </div>
                    </div>
                    
                    <div class="flex-1 overflow-y-auto">
                        <?php if(empty($chatList)): ?>
                            <p class='p-8 text-center text-gray-400 text-sm italic'>No messages yet.</p>
                        <?php else: ?>
                            <?php foreach ($chatList as $chat): ?>
                            <a href="?receiver_id=<?php echo $chat['user_id']; ?>" class="flex items-center gap-4 p-5 hover:bg-gray-50 border-b border-gray-50 transition-colors <?php echo $receiverId == $chat['user_id'] ? 'bg-blue-50/50' : ''; ?>">
                                
                                <div class="relative flex-shrink-0">
                                    <?php if($chat['role'] == 'Admin'): ?>
                                        <div class="w-12 h-12 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-xl shadow-sm border border-blue-200"><i class="fa-solid fa-headset"></i></div>
                                    <?php else: ?>
                                        <img src="<?php echo $chat['avatar_url'] ?: 'img/default.png'; ?>" class="w-12 h-12 rounded-full object-cover shadow-sm border border-gray-100">
                                    <?php endif; ?>
                                </div>

                                <div class="flex-1 overflow-hidden">
                                    <div class="flex justify-between items-center mb-1">
                                        <span class="text-sm truncate <?php echo $chat['unread_count'] > 0 ? 'font-extrabold text-gray-900' : 'font-semibold text-gray-800'; ?>">
                                            <?php echo $chat['role'] == 'Admin' ? 'Pneumo-Care Support' : htmlspecialchars($chat['full_name']); ?>
                                        </span>
                                        
                                        <div class="flex items-center gap-1.5">
                                            <?php if($chat['unread_count'] > 0): ?>
                                                <span class="bg-red-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full min-w-[18px] text-center shadow-sm"><?php echo $chat['unread_count']; ?></span>
                                            <?php endif; ?>
                                            <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-gray-100 text-gray-500 uppercase"><?php echo $chat['role']; ?></span>
                                        </div>
                                    </div>
                                    <p class="text-xs truncate <?php echo $chat['unread_count'] > 0 ? 'font-bold text-gray-900' : 'text-gray-500'; ?>">
                                        <?php echo $chat['last_message'] ? htmlspecialchars($chat['last_message']) : 'Nhấp để xem hội thoại...'; ?>
                                    </p>
                                </div>
                            </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="flex-1 flex flex-col bg-[#f8fafc]/50 relative">
                    <?php if ($receiverId): ?>
                        
                        <div class="h-[72px] bg-white border-b border-gray-100 px-8 flex items-center justify-between flex-shrink-0">
                            <div class="flex items-center gap-3">
                                <h3 class="font-bold text-gray-800"><?php echo htmlspecialchars($receiverName); ?></h3>
                                <span class="text-xs font-semibold text-gray-400 border border-gray-200 px-2 py-0.5 rounded bg-gray-50"><?php echo $receiverRole; ?></span>
                            </div>
                            <div class="flex gap-4 text-gray-400">
                                <i class="fa-solid fa-phone cursor-pointer hover:text-blue-500 transition-colors w-8 h-8 flex items-center justify-center rounded-full hover:bg-blue-50"></i>
                                <i class="fa-solid fa-video cursor-pointer hover:text-blue-500 transition-colors w-8 h-8 flex items-center justify-center rounded-full hover:bg-blue-50"></i>
                                <i class="fa-solid fa-circle-info cursor-pointer hover:text-blue-500 transition-colors w-8 h-8 flex items-center justify-center rounded-full hover:bg-blue-50"></i>
                            </div>
                        </div>

                        <div class="flex-1 overflow-y-auto p-8 flex flex-col space-y-6" id="chatBox">
                            <?php if(empty($currentMessages)): ?>
                                <div class="m-auto flex flex-col items-center justify-center text-gray-400 opacity-80">
                                    <div class="w-20 h-20 bg-blue-50 text-blue-400 rounded-full flex items-center justify-center mb-4 shadow-sm border border-blue-100">
                                        <i class="fa-regular fa-paper-plane text-3xl"></i>
                                    </div>
                                    <h4 class="font-semibold text-gray-600 mb-1">No messages yet</h4>
                                    <p class="text-sm">Bắt đầu cuộc trò chuyện với <?php echo htmlspecialchars($receiverName); ?>.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($currentMessages as $msg): ?>
                                    <div class="flex <?php echo $msg['sender_id'] == $doctorId ? 'justify-end' : 'justify-start'; ?>">
                                        <div class="max-w-[70%]">
                                            <div class="p-4 shadow-sm text-sm <?php echo $msg['sender_id'] == $doctorId ? 'bg-blue-600 text-white rounded-2xl rounded-tr-sm' : 'bg-white text-gray-700 rounded-2xl rounded-tl-sm border border-gray-100'; ?>">
                                                <?php echo nl2br(htmlspecialchars($msg['message_content'])); ?>
                                            </div>
                                            <p class="text-[10px] mt-1.5 text-gray-400 font-medium <?php echo $msg['sender_id'] == $doctorId ? 'text-right' : 'text-left'; ?>">
                                                <?php echo date('H:i', strtotime($msg['sent_at'])); ?>
                                            </p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <div id="filePreview" class="hidden absolute bottom-[84px] left-6 bg-white border border-gray-200 shadow-lg rounded-xl px-4 py-3 flex items-center gap-3 text-sm text-gray-700 z-20">
                            <i id="fileIcon" class="fa-solid fa-file text-blue-500 text-lg"></i>
                            <span id="fileName" class="font-medium max-w-[200px] truncate">...</span>
                            <button type="button" onclick="clearFile()" class="text-red-400 hover:text-red-600 ml-2"><i class="fa-solid fa-xmark"></i></button>
                        </div>

                        <div class="p-6 bg-white border-t border-gray-100 flex-shrink-0 relative z-10">
                            <form method="POST" action="?receiver_id=<?php echo $receiverId; ?>" enctype="multipart/form-data" class="flex gap-4 items-center">
                                
                                <label class="cursor-pointer">
                                    <i class="fa-solid fa-paperclip text-xl text-gray-400 hover:text-gray-600 ml-2 transition-colors"></i>
                                    <input type="file" name="attachment_file" class="hidden" onchange="showFile(this, 'file')">
                                </label>
                                
                                <label class="cursor-pointer">
                                    <i class="fa-regular fa-image text-xl text-gray-400 hover:text-gray-600 transition-colors"></i>
                                    <input type="file" name="attachment_image" accept="image/*" class="hidden" onchange="showFile(this, 'image')">
                                </label>
                                
                                <input type="text" name="message_content" id="msgInput" autocomplete="off" placeholder="Type a message..." class="flex-1 bg-gray-50 border border-gray-200 rounded-xl px-5 py-3.5 text-sm outline-none focus:border-blue-400 focus:bg-white transition-colors">
                                
                                <button type="submit" class="bg-blue-600 text-white w-12 h-12 rounded-xl flex items-center justify-center hover:bg-blue-700 transition-all shadow-md flex-shrink-0">
                                    <i class="fa-solid fa-paper-plane"></i>
                                </button>
                            </form>
                        </div>

                    <?php else: ?>
                        <div class="flex-1 flex flex-col items-center justify-center text-gray-400 bg-gray-50/50">
                            <div class="w-24 h-24 bg-white rounded-full flex items-center justify-center mb-6 shadow-sm border border-gray-100">
                                <i class="fa-regular fa-comments text-4xl text-blue-200"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-600 mb-2">Pneumo-Care Messages</h3>
                            <p class="text-sm">Select a conversation from the left to start chatting.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script>
        // 1. Tự động cuộn xuống tin nhắn mới nhất
        const chatBox = document.getElementById('chatBox');
        if(chatBox) { chatBox.scrollTop = chatBox.scrollHeight; }

        // 2. Logic hiển thị File Preview khi người dùng bấm upload
        const filePreview = document.getElementById('filePreview');
        const fileNameSpan = document.getElementById('fileName');
        const fileIcon = document.getElementById('fileIcon');
        const msgInput = document.getElementById('msgInput');

        function showFile(input, type) {
            if (input.files && input.files[0]) {
                filePreview.classList.remove('hidden');
                fileNameSpan.textContent = input.files[0].name;
                
                if(type === 'image') {
                    fileIcon.className = "fa-regular fa-image text-blue-500 text-lg";
                } else {
                    fileIcon.className = "fa-solid fa-file-medical text-blue-500 text-lg";
                }

                // Tự động bỏ 'required' ở thẻ input text vì có file là được gửi
                msgInput.removeAttribute("required");
            }
        }

        function clearFile() {
            filePreview.classList.add('hidden');
            document.querySelector('input[name="attachment_file"]').value = "";
            document.querySelector('input[name="attachment_image"]').value = "";
            msgInput.setAttribute("required", "required"); // Bắt buộc nhập text lại
        }
    </script>
</body>
</html>