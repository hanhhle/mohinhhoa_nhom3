<?php
// ==========================================
// TÊN FILE: doc_messages.php
// CHỨC NĂNG: Nhắn tin
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

// ==========================================
// CÁC HÀM XỬ LÝ GIAO DIỆN (Đã thêm mới)
// ==========================================
// Hàm tạo Avatar chữ cái
function getInitials($fullName) {
    $words = explode(' ', trim($fullName));
    $count = count($words);
    if ($count >= 2) {
        $first = mb_substr($words[$count - 2], 0, 1, 'UTF-8');
        $second = mb_substr($words[$count - 1], 0, 1, 'UTF-8');
        return mb_strtoupper($first . $second, 'UTF-8');
    } elseif ($count == 1) {
        return mb_strtoupper(mb_substr($words[0], 0, 1, 'UTF-8'), 'UTF-8');
    }
    return '?';
}

// Hàm random màu nền Avatar
function getAvatarColor($fullName) {
    $colors = ['bg-blue-500', 'bg-red-500', 'bg-green-500', 'bg-yellow-500', 'bg-purple-500', 'bg-pink-500', 'bg-indigo-500', 'bg-teal-500'];
    $index = strlen($fullName) % count($colors);
    return $colors[$index];
}

// Hàm tự động nhận diện Link
function autoLink($text) {
    $pattern = '/(https?:\/\/[^\s]+)/';
    $replacement = '<a href="$1" target="_blank" class="underline font-bold text-yellow-300 hover:text-blue-200 transition-colors">$1</a>';
    return preg_replace($pattern, $replacement, $text);
}

$receiverId = isset($_GET['receiver_id']) ? $_GET['receiver_id'] : null;

// ========================================================
// [BUSINESS LAYER] LỚP BẢO MẬT: KIỂM TRA QUYỀN NHẮN TIN
// ========================================================
$isAuthorized = false;
if ($receiverId) {
    $stmtCheck = $pdo->prepare("SELECT role FROM Users WHERE user_id = ?");
    $stmtCheck->execute([$receiverId]);
    $recRole = $stmtCheck->fetchColumn();

    if ($recRole === 'Admin') {
        $isAuthorized = true; 
    } elseif ($recRole === 'Patient') {
        $stmtAptCheck = $pdo->prepare("SELECT 1 FROM Appointments WHERE doctor_id = ? AND patient_id = ? LIMIT 1");
        $stmtAptCheck->execute([$doctorId, $receiverId]);
        if ($stmtAptCheck->fetchColumn()) {
            $isAuthorized = true; 
        }
    }
    
    if (!$isAuthorized) {
        header("Location: doc_messages.php");
        exit();
    }
}

// 2. Xử lý Gửi tin nhắn
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $receiverId && $isAuthorized) {
    $msgText = isset($_POST['message_content']) ? trim($_POST['message_content']) : '';
    
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
if ($receiverId && $isAuthorized) {
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
    // Lấy danh sách liên hệ (Admin + chỉ các bệnh nhân ĐÃ NHẮN TIN)
    $stmtList = $pdo->prepare("
        SELECT 
            u.user_id, u.full_name, u.role,
            (SELECT message_content FROM Messages 
             WHERE (sender_id = u.user_id AND receiver_id = :doc1) 
                OR (sender_id = :doc2 AND receiver_id = u.user_id) 
             ORDER BY sent_at DESC LIMIT 1) as last_message,
            (SELECT COUNT(*) FROM Messages 
             WHERE sender_id = u.user_id AND receiver_id = :doc3 AND is_read = 0) as unread_count,
            (SELECT MAX(sent_at) FROM Messages 
             WHERE (sender_id = u.user_id AND receiver_id = :doc4) 
                OR (sender_id = :doc5 AND receiver_id = u.user_id)) as last_interaction
        FROM Users u
        WHERE u.user_id != :doc6 
          AND (
              u.role = 'Admin' 
              OR u.role = 'Patient'
          )
          -- ĐIỀU KIỆN MỚI: Chỉ lấy người đã có bản ghi trong bảng Messages với bác sĩ
          AND EXISTS (
              SELECT 1 FROM Messages m 
              WHERE (m.sender_id = u.user_id AND m.receiver_id = :doc7) 
                 OR (m.sender_id = :doc7 AND m.receiver_id = u.user_id)
          )
        ORDER BY last_interaction DESC, u.full_name ASC
    ");
    $stmtList->execute([
        ':doc1' => $doctorId, ':doc2' => $doctorId, 
        ':doc3' => $doctorId, ':doc4' => $doctorId, 
        ':doc5' => $doctorId, ':doc6' => $doctorId,
        ':doc7' => $doctorId
    ]);
    $chatList = $stmtList->fetchAll();

    if ($receiverId && $isAuthorized) {
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

    <aside class="w-64 bg-white border-r border-gray-100 flex flex-col h-full shadow-sm flex-shrink-0 z-10">
        <div class="flex items-center gap-2 p-6 border-b">
            <i class="fa-solid fa-lungs text-3xl text-red-400"></i>
            <h1 class="text-xl font-semibold text-gray-700">Pneumo-<span class="text-blue-500">Care</span></h1>
        </div>

        <nav class="flex-1 px-4 py-6 space-y-1">
            <a href="doc_dashboard.php" class="flex items-center gap-4 px-4 py-3 text-gray-500 hover:bg-gray-50 rounded-lg transition-colors">
                <i class="fa-solid fa-gauge-high w-5"></i><span>Dashboard</span>
            </a>
            <a href="doc_patient_list.php" class="flex items-center gap-4 px-4 py-3 text-gray-500 hover:bg-gray-50 rounded-lg transition-colors">
                <i class="fa-solid fa-users w-5"></i><span>Patient</span>
            </a>
            <a href="doc_appointments.php" class="flex items-center gap-4 px-4 py-3 text-gray-500 hover:bg-gray-50 rounded-lg transition-colors">
                <i class="fa-solid fa-calendar-check w-5"></i><span>Appointments</span>
            </a>
            <a href="doc_ai_workspace.php" class="flex items-center gap-4 px-4 py-3 text-gray-500 hover:bg-gray-50 rounded-lg transition-colors">
                <i class="fa-solid fa-brain w-5"></i><span>Diagnosis</span>
            </a>
            <a href="doc_messages.php" class="sidebar-active flex items-center gap-4 px-4 py-3 rounded-lg font-medium">
                <i class="fa-solid fa-comment-dots w-5"></i><span>Messages</span>
            </a>
        </nav>

        <div class="p-6 border-t mt-auto">
            <a href="logout.php" class="flex items-center gap-4 text-gray-500 hover:text-red-500 transition-colors font-medium">
                <i class="fa-solid fa-right-from-bracket"></i><span>Logout</span>
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
                            <p class="text-sm font-semibold text-gray-800" style="line-height: 1.2;">Dr. <?php echo htmlspecialchars($doctorName); ?></p>
                            <p class="text-xs text-gray-500 font-medium">Doctor</p>
                        </div>
                        
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 text-white flex items-center justify-center font-bold text-sm shadow-sm border border-gray-200 flex-shrink-0">
                            <?php echo getInitials($doctorName); ?>
                        </div>

                    </div>
                </div>
            </header>
        </div>

        <div class="flex-1 flex overflow-hidden px-10 pb-10">
                <div class="flex flex-1 h-full bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden flex-row">
                
                <div class="w-[340px] border-r border-gray-100 flex flex-col flex-shrink-0">
                    <div class="p-6 border-b border-gray-100">
                        <div class="relative">
                            <i class="fa-solid fa-magnifying-glass absolute left-4 top-3.5 text-gray-400"></i>
                            <input type="text" id="searchInput" onkeyup="searchChat()" placeholder="Search..." class="w-full bg-gray-50 border border-gray-200 rounded-xl py-3 pl-12 pr-4 focus:outline-none focus:border-blue-400 text-sm transition-colors">
                        </div>
                    </div>
                    
                    <div class="flex-1 overflow-y-auto">
                        <?php if(empty($chatList)): ?>
                            <p class='p-8 text-center text-gray-400 text-sm italic'>No patients available.</p>
                        <?php else: ?>
                            <?php foreach ($chatList as $chat): ?>
                            <a href="?receiver_id=<?php echo $chat['user_id']; ?>" 
                               class="chat-item flex items-center gap-4 p-5 hover:bg-gray-50 border-b border-gray-50 transition-colors <?php echo $receiverId == $chat['user_id'] ? 'bg-blue-50/50' : ''; ?>"
                               data-name="<?php echo strtolower($chat['role'] == 'Admin' ? 'Support' : htmlspecialchars($chat['full_name'])); ?>">
                                
                                <div class="relative flex-shrink-0">
                                    <?php if($chat['role'] == 'Admin'): ?>
                                        <div class="w-12 h-12 rounded-full bg-[#003366] text-white flex items-center justify-center text-[15px] font-bold shadow-sm">
                                            PC
                                        </div>
                                    <?php else: ?>
                                        <div class="w-12 h-12 rounded-full <?php echo getAvatarColor($chat['full_name']); ?> text-white flex items-center justify-center text-[15px] font-bold shadow-sm">
                                            <?php echo getInitials($chat['full_name']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="flex-1 min-w-0">
                                    <div class="flex justify-between items-center mb-1 gap-2">
                                        <span class="text-sm truncate <?php echo $chat['unread_count'] > 0 ? 'font-extrabold text-gray-900' : 'font-semibold text-gray-800'; ?>">
                                            <?php echo $chat['role'] == 'Admin' ? 'Pneumo-Care Support' : htmlspecialchars($chat['full_name']); ?>
                                        </span>
                                        <?php if($chat['unread_count'] > 0): ?>
                                            <div class="bg-red-500 text-white text-[10px] font-bold h-5 min-w-[20px] px-1.5 rounded-full flex items-center justify-center shadow-sm flex-shrink-0">
                                                <?php echo $chat['unread_count']; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-[9px] font-bold px-2 py-0.5 rounded bg-gray-100 text-gray-500 uppercase flex-shrink-0"><?php echo $chat['role']; ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-xs truncate <?php echo $chat['unread_count'] > 0 ? 'font-bold text-gray-900' : 'text-gray-500'; ?>">
                                        <?php echo $chat['last_message'] ? htmlspecialchars($chat['last_message']) : 'Click to start messaging'; ?>
                                    </p>
                                </div>
                            </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="flex-1 flex flex-col bg-[#f8fafc]/50 relative min-w-0">
                    <?php if ($receiverId && $isAuthorized): ?>
                        
                        <div class="h-[72px] bg-white border-b border-gray-100 px-8 flex items-center justify-between flex-shrink-0">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full <?php echo $receiverName == 'Pneumo-Care Support' ? 'bg-[#003366]' : getAvatarColor($receiverName); ?> text-white flex items-center justify-center text-sm font-bold shadow-sm relative flex-shrink-0">
                                    <?php echo $receiverName == 'Pneumo-Care Support' ? 'PC' : getInitials($receiverName); ?>
                                </div>
                                <div>
                                    <h3 class="font-bold text-gray-800"><?php echo htmlspecialchars($receiverName); ?></h3>
                                    <span class="text-[10px] font-semibold text-gray-400 border border-gray-200 px-1.5 py-0.5 rounded bg-gray-50 uppercase tracking-wide inline-block mt-0.5"><?php echo $receiverRole; ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="flex-1 overflow-y-auto p-8 flex flex-col space-y-6" id="chatBox">
                            <?php if(empty($currentMessages)): ?>
                                <div class="m-auto text-center text-gray-400 opacity-80">
                                    <div class="w-20 h-20 bg-blue-50 text-blue-400 rounded-full flex items-center justify-center mb-4 mx-auto border border-blue-100 shadow-sm">
                                        <i class="fa-regular fa-paper-plane text-3xl"></i>
                                    </div>
                                    <p class="text-sm">Bắt đầu trò chuyện với <?php echo htmlspecialchars($receiverName); ?>.</p>
                                </div>
                            <?php else: ?>
                                <div class="text-center mb-6"><span class="text-[10px] font-bold uppercase tracking-wider text-gray-400 bg-gray-100 px-3 py-1 rounded-full">Beginning of conversation</span></div>
                                <?php foreach ($currentMessages as $msg): ?>
                                    <div class="flex <?php echo $msg['sender_id'] == $doctorId ? 'justify-end' : 'justify-start'; ?> group">
                                        <div class="max-w-[75%] flex flex-col <?php echo $msg['sender_id'] == $doctorId ? 'items-end' : 'items-start'; ?>">
                                            <div class="p-4 shadow-sm text-[14px] leading-relaxed <?php echo $msg['sender_id'] == $doctorId ? 'bg-blue-600 text-white rounded-2xl rounded-tr-sm' : 'bg-white text-gray-700 rounded-2xl rounded-tl-sm border border-gray-100'; ?>">
                                                <?php echo autoLink(nl2br(htmlspecialchars($msg['message_content']))); ?>
                                            </div>
                                            <p class="text-[10px] mt-1.5 text-gray-400 font-medium px-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                                <?php echo date('H:i', strtotime($msg['sent_at'])); ?>
                                                <?php if($msg['sender_id'] == $doctorId): ?>
                                                    <i class="fa-solid fa-check-double ml-1 <?php echo $msg['is_read'] ? 'text-blue-500' : 'text-gray-300'; ?>"></i>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <div class="p-6 bg-white border-t border-gray-100 flex-shrink-0">
                            <form method="POST" action="?receiver_id=<?php echo $receiverId; ?>" enctype="multipart/form-data" class="flex gap-4 items-center">
                                <input type="text" name="message_content" id="msgInput" autocomplete="off" placeholder="Type a message..." class="flex-1 bg-gray-50 border border-gray-200 rounded-xl px-5 py-3.5 text-sm outline-none focus:border-blue-400 transition-colors">
                                <button type="submit" class="bg-blue-600 text-white w-12 h-12 rounded-xl flex items-center justify-center hover:bg-blue-700 transition-all shadow-md">
                                    <i class="fa-solid fa-paper-plane"></i>
                                </button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="flex-1 flex flex-col items-center justify-center text-gray-400">
                            <div class="w-24 h-24 bg-white shadow-sm border border-gray-100 rounded-full flex items-center justify-center mb-6">
                                <i class="fa-regular fa-comments text-4xl text-blue-300"></i>
                            </div>
                            <h3 class="text-lg font-bold text-gray-600 mb-1">Your Messages</h3>
                            <p class="text-sm font-medium">Select a patient from the list to start chatting.</p>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </main>

    <script>
        // 1. Tự động cuộn xuống tin nhắn mới nhất
        const chatBox = document.getElementById('chatBox');
        if (chatBox) { 
            chatBox.scrollTop = chatBox.scrollHeight; 
            setTimeout(() => { chatBox.scrollTop = chatBox.scrollHeight; }, 100);
        }

        // 2. Logic của thanh Search
        function searchChat() {
            let input = document.getElementById('searchInput').value.toLowerCase();
            let items = document.querySelectorAll('.chat-item');
            
            items.forEach(item => {
                let name = item.getAttribute('data-name');
                if (name && name.includes(input)) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>