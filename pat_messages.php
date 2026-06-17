<?php
// ==========================================
// TÊN FILE: pat_messages.php
// CHỨC NĂNG: Bệnh nhân nhắn tin với Admin & Bác sĩ đã khám
// ==========================================
session_start();
require 'db.php';
ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL);

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Patient') { 
    header("Location: login.php"); exit(); 
}

$patientId = $_SESSION['user_id'];
$patientName = $_SESSION['name'];

// HÀM TẠO AVATAR CHỮ CÁI
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

// HÀM TẠO MÀU NỀN AVATAR
function getAvatarColor($fullName) {
    $colors = ['bg-blue-500', 'bg-red-500', 'bg-green-500', 'bg-yellow-500', 'bg-purple-500', 'bg-pink-500', 'bg-indigo-500', 'bg-teal-500'];
    $index = strlen($fullName) % count($colors);
    return $colors[$index];
}

// HÀM NHẬN DIỆN LINK
function autoLink($text) {
    $pattern = '/(https?:\/\/[^\s]+)/';
    $replacement = '<a href="$1" target="_blank" class="underline font-bold text-yellow-300 hover:text-blue-200 transition-colors">$1</a>';
    return preg_replace($pattern, $replacement, $text);
}

$receiverId = isset($_GET['receiver_id']) ? $_GET['receiver_id'] : null;

// 1. XỬ LÝ GỬI TIN NHẮN 
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['message_content']) && $receiverId) {
    $msgText = trim($_POST['message_content']);
    if (!empty($msgText)) {
        try {
            $stmtInsert = $pdo->prepare("INSERT INTO Messages (sender_id, receiver_id, message_content) VALUES (?, ?, ?)");
            $stmtInsert->execute([$patientId, $receiverId, $msgText]);
            header("Location: pat_messages.php?receiver_id=" . $receiverId);
            exit();
        } catch (PDOException $e) {}
    }
}

// 2. ĐÁNH DẤU ĐÃ ĐỌC
if ($receiverId) {
    try {
        $stmtRead = $pdo->prepare("UPDATE Messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ?");
        $stmtRead->execute([$receiverId, $patientId]);
    } catch (PDOException $e) {}
}

$adminContact = null;
$doctorsList = [];
$currentMessages = [];
$receiverName = "Select a contact";
$receiverSpec = "";

try {
    // 3. LẤY THÔNG TIN ADMIN 
    $stmtAdmin = $pdo->prepare("
        SELECT u.user_id, u.full_name, 'Support' as role,
            (SELECT message_content FROM Messages 
             WHERE (sender_id = u.user_id AND receiver_id = :pat1) 
                OR (sender_id = :pat2 AND receiver_id = u.user_id) 
             ORDER BY sent_at DESC LIMIT 1) as last_message,
            (SELECT COUNT(*) FROM Messages 
             WHERE sender_id = u.user_id AND receiver_id = :pat3 AND is_read = 0) as unread_count
        FROM Users u WHERE u.role = 'Admin' LIMIT 1
    ");
    $stmtAdmin->execute([':pat1' => $patientId, ':pat2' => $patientId, ':pat3' => $patientId]);
    $adminContact = $stmtAdmin->fetch();

    // 4. LẤY DANH SÁCH BÁC SĨ (Đã sửa lỗi ẩn bác sĩ chưa có tin nhắn)
    $stmtList = $pdo->prepare("
        SELECT u.user_id, u.full_name, dp.speciality as role,
            (SELECT message_content FROM Messages 
             WHERE (sender_id = u.user_id AND receiver_id = :pat1) 
                OR (sender_id = :pat2 AND receiver_id = u.user_id) 
             ORDER BY sent_at DESC LIMIT 1) as last_message,
            (SELECT COUNT(*) FROM Messages 
             WHERE sender_id = u.user_id AND receiver_id = :pat3 AND is_read = 0) as unread_count,
            (SELECT MAX(sent_at) FROM Messages 
             WHERE (sender_id = u.user_id AND receiver_id = :pat4) 
                OR (sender_id = :pat5 AND receiver_id = u.user_id)) as last_interaction
        FROM Users u
        JOIN Doctor_Profiles dp ON u.user_id = dp.doctor_id
        WHERE u.role = 'Doctor' 
          AND u.user_id IN (SELECT DISTINCT doctor_id FROM Appointments WHERE patient_id = :pat6)
        ORDER BY last_interaction IS NULL ASC, last_interaction DESC, u.full_name ASC
    ");

    $stmtList->execute([
        ':pat1' => $patientId, ':pat2' => $patientId, ':pat3' => $patientId,
        ':pat4' => $patientId, ':pat5' => $patientId, ':pat6' => $patientId
    ]);
    $doctorsList = $stmtList->fetchAll();

    // 5. LẤY LỊCH SỬ TIN NHẮN
    if ($receiverId) {
        $stmtRec = $pdo->prepare("SELECT user_id, full_name, role FROM Users WHERE user_id = ?");
        $stmtRec->execute([$receiverId]);
        $recInfo = $stmtRec->fetch();

        if ($recInfo) {
            if ($recInfo['role'] == 'Admin') {
                $receiverName = "Pneumo-Care Support";
                $receiverSpec = "System Administrator";
            } else {
                $stmtSpec = $pdo->prepare("SELECT speciality FROM Doctor_Profiles WHERE doctor_id = ?");
                $stmtSpec->execute([$receiverId]);
                $spec = $stmtSpec->fetchColumn();
                $receiverName = "Dr. " . $recInfo['full_name'];
                $receiverSpec = $spec ?: 'Specialist';
            }

            $stmtMsg = $pdo->prepare("
                SELECT * FROM Messages 
                WHERE (sender_id = ? AND receiver_id = ?) 
                   OR (sender_id = ? AND receiver_id = ?)
                ORDER BY sent_at ASC
            ");
            $stmtMsg->execute([$patientId, $receiverId, $receiverId, $patientId]);
            $currentMessages = $stmtMsg->fetchAll();
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
    <title>Pneumo-Care | Messages</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background-color: #f4f7fa; color: #1f2937; }

        .layout { display: flex; min-height: 100vh; overflow: hidden; }
        .sidebar-active { background-color: #eff6ff; color: #2563eb; border-left: 4px solid #2563eb; font-weight: 600; }
        .main-content { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
        .topbar-wrapper { padding: 32px 40px 0 40px; }
        .topbar { height: 72px; background: #ffffff; border: 1px solid #f3f4f6; display: flex; align-items: center; justify-content: space-between; padding: 0 24px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 24px; }
        .topbar h1 { font-size: 22px; font-weight: 600; color: #1f2937; margin: 0; }
        .content-area { padding: 0 40px 40px 40px; flex: 1; display: flex; flex-direction: column; min-h-0; }

        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="flex h-screen overflow-hidden text-gray-800">
<div class="flex w-full h-full relative">
  
    <aside class="w-64 bg-white border-r border-gray-100 flex flex-col h-full shadow-sm z-10">
        <div class="flex items-center gap-2 p-6 border-b">
            <i class="fa-solid fa-lungs text-3xl text-red-400"></i>
            <h1 class="text-xl font-semibold text-gray-700">Pneumo-<span class="text-blue-500">Care</span></h1>
        </div>

        <nav class="flex-1 px-4 py-6 space-y-2">
            <a href="pat_dashboard.php" class="flex items-center gap-4 px-4 py-3 text-gray-500 hover:bg-gray-50 hover:text-gray-800 rounded-xl transition-colors font-medium">
                <i class="fa-solid fa-gauge-high w-5 text-center text-xl"></i>
                <span>Dashboard</span>
            </a>
            <a href="pat_report.php" class="flex items-center gap-4 px-4 py-3 text-gray-500 hover:bg-gray-50 hover:text-gray-800 rounded-xl transition-colors font-medium">
                <i class="fa-solid fa-file-medical w-5 text-center text-xl"></i>
                <span>Report</span>
            </a>
            <a href="pat_appointments.php" class="flex items-center gap-4 px-4 py-3 text-gray-500 hover:bg-gray-50 hover:text-gray-800 rounded-xl transition-colors font-medium">
                <i class="fa-solid fa-calendar-check w-5 text-center text-xl"></i>
                <span>Appointments</span>
            </a>
            <a href="pat_doctors.php" class="flex items-center gap-4 px-4 py-3 text-gray-500 hover:bg-gray-50 hover:text-gray-800 rounded-xl transition-colors font-medium">
                <i class="fa-solid fa-user-doctor w-5 text-center text-xl"></i>
                <span>Doctors</span>
            </a>
            <a href="pat_messages.php" class="sidebar-active flex items-center gap-4 px-4 py-3 rounded-xl transition-colors font-medium">
                <i class="fa-solid fa-comment-dots w-5 text-center text-xl"></i>
                <span>Messages</span>
            </a>
        </nav>

        <div class="p-6 border-t mt-auto">
            <a href="logout.php" class="flex items-center gap-4 text-gray-500 hover:text-red-500 transition-colors font-medium">
                <i class="fa-solid fa-right-from-bracket text-lg"></i>
                <span>Logout</span>
            </a>
        </div>
    </aside>

    <main class="main-content bg-[#f4f7fa]">
        <div class="topbar-wrapper flex-shrink-0">
            <header class="topbar">
                <h1>Messages</h1>
                <div class="flex items-center gap-6">
                    <div class="flex items-center gap-3 cursor-pointer">
                        <div class="text-right hidden sm:block">
                            <p class="text-sm font-semibold text-gray-800" style="line-height: 1.2;"><?php echo htmlspecialchars($patientName); ?></p>
                            <p class="text-xs text-gray-500 font-medium">Patient</p>
                        </div>
                        
                        <div class="w-10 h-10 rounded-full <?php echo getAvatarColor($patientName); ?> text-white flex items-center justify-center font-bold text-sm shadow-sm border border-gray-200 flex-shrink-0">
                            <?php echo getInitials($patientName); ?>
                        </div>

                    </div>
                </div>
            </header>
        </div>

        <div class="content-area">
            <div class="flex flex-1 bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden min-h-0">
                
                <div class="w-80 bg-white border-r border-gray-100 flex flex-col flex-shrink-0">
                    <div class="p-6 border-b border-gray-100 bg-white">
                        <h2 class="font-bold text-gray-800 text-[14px] uppercase tracking-widest mb-4">Recent Chats</h2>
                        <div class="relative">
                            <input type="text" id="searchInput" onkeyup="searchChat()" placeholder="Search messages..." class="w-full bg-gray-50 border border-gray-200 rounded-xl py-2 px-4 pl-10 text-sm focus:border-blue-400 outline-none transition-colors shadow-sm">
                            <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                        </div>
                    </div>
                    
                    <div class="flex-1 overflow-y-auto no-scrollbar p-3 space-y-1">
                        
                        <?php if($adminContact): ?>
                        <a href="?receiver_id=<?php echo $adminContact['user_id']; ?>" 
                           class="chat-item flex items-center gap-3 p-3 rounded-xl transition-all border <?php echo $receiverId == $adminContact['user_id'] ? 'bg-blue-50/60 border-blue-200 shadow-sm' : 'border-transparent hover:bg-white hover:border-gray-100 hover:shadow-sm'; ?>"
                           data-name="pneumo-care support admin">
                            
                            <div class="relative flex-shrink-0">
                                <div class="w-12 h-12 rounded-full bg-[#003366] text-white flex items-center justify-center text-[15px] font-bold shadow-sm">
                                    PC
                                </div>
                            </div>
                            
                            <div class="flex-1 min-w-0">
                                <div class="flex justify-between items-center mb-1 gap-2">
                                    <span class="text-sm truncate <?php echo $adminContact['unread_count'] > 0 ? 'font-bold text-gray-900' : 'font-semibold text-gray-700'; ?>">
                                        Pneumo-Care Support
                                    </span>
                                    <?php if($adminContact['unread_count'] > 0): ?>
                                        <div class="bg-red-500 text-white text-[10px] font-bold h-5 min-w-[20px] px-1.5 rounded-full flex items-center justify-center shadow-sm flex-shrink-0">
                                            <?php echo $adminContact['unread_count']; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <p class="text-xs truncate <?php echo $adminContact['unread_count'] > 0 ? 'text-gray-900 font-bold' : 'text-gray-500'; ?>">
                                    <?php echo $adminContact['last_message'] ? htmlspecialchars($adminContact['last_message']) : 'Click to start messaging'; ?>
                                </p>
                            </div>
                        </a>
                        <?php endif; ?>

                        <?php if(empty($doctorsList)): ?>
                            <div class="p-8 text-center text-gray-400 text-sm italic">No doctors available.</div>
                        <?php else: ?>
                            <?php foreach ($doctorsList as $doc): ?>
                            <a href="?receiver_id=<?php echo $doc['user_id']; ?>" 
                               class="chat-item flex items-center gap-3 p-3 rounded-xl transition-all border <?php echo $receiverId == $doc['user_id'] ? 'bg-blue-50/60 border-blue-200 shadow-sm' : 'border-transparent hover:bg-white hover:border-gray-100 hover:shadow-sm'; ?>"
                               data-name="<?php echo strtolower(htmlspecialchars('Dr. ' . $doc['full_name'])); ?>">
                                
                                <div class="relative flex-shrink-0">
                                    <div class="w-12 h-12 rounded-full <?php echo getAvatarColor($doc['full_name']); ?> text-white flex items-center justify-center text-[15px] font-bold shadow-sm">
                                        <?php echo getInitials($doc['full_name']); ?>
                                    </div>
                                </div>
                                
                                <div class="flex-1 min-w-0">
                                    <div class="flex justify-between items-center mb-1 gap-2">
                                        <span class="text-sm truncate <?php echo $doc['unread_count'] > 0 ? 'font-bold text-gray-900' : 'font-semibold text-gray-700'; ?>">
                                            Dr. <?php echo htmlspecialchars($doc['full_name']); ?>
                                        </span>
                                        <?php if($doc['unread_count'] > 0): ?>
                                            <div class="bg-red-500 text-white text-[10px] font-bold h-5 min-w-[20px] px-1.5 rounded-full flex items-center justify-center shadow-sm flex-shrink-0">
                                                <?php echo $doc['unread_count']; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-xs truncate <?php echo $doc['unread_count'] > 0 ? 'text-gray-900 font-bold' : 'text-gray-500'; ?>">
                                        <?php echo $doc['last_message'] ? htmlspecialchars($doc['last_message']) : 'Click to start messaging'; ?>
                                    </p>
                                </div>
                            </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="flex-1 flex flex-col bg-white relative min-w-0">
                    <?php if ($receiverId): ?>
                        
                        <div class="h-[76px] bg-white border-b border-gray-100 px-8 flex items-center justify-between flex-shrink-0 z-10 shadow-[0_4px_10px_-10px_rgba(0,0,0,0.1)]">
                                <div class="flex items-center gap-4">
                                <div class="w-10 h-10 rounded-full <?php echo $receiverName == 'Pneumo-Care Support' ? 'bg-[#003366]' : getAvatarColor($receiverName); ?> text-white flex items-center justify-center text-sm font-bold shadow-sm relative flex-shrink-0">
                                    <?php echo $receiverName == 'Pneumo-Care Support' ? 'PC' : getInitials($receiverName); ?>
                                </div>
                                <div>
                                    <h3 class="font-bold text-gray-800 text-[15px] flex items-center gap-2">
                                        <?php echo htmlspecialchars($receiverName); ?>
                                    </h3>
                                    <p class="text-xs text-gray-500 font-medium"><?php echo htmlspecialchars($receiverSpec); ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="flex-1 overflow-y-auto p-8 space-y-6 bg-gray-50/50" id="chatBox">
                            <?php if(empty($currentMessages)): ?>
                                <div class="flex flex-col items-center justify-center h-full text-gray-400">
                                    <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-3"><i class="fa-regular fa-comment-dots text-2xl opacity-50"></i></div>
                                    <p class="text-sm font-medium">Send a message to start the conversation.</p>
                                </div>
                            <?php else: ?>
                                <div class="text-center mb-6"><span class="text-[10px] font-bold uppercase tracking-wider text-gray-400 bg-gray-100 px-3 py-1 rounded-full">Beginning of conversation</span></div>
                                <?php foreach ($currentMessages as $msg): ?>
                                    <div class="flex <?php echo $msg['sender_id'] == $patientId ? 'justify-end' : 'justify-start'; ?> group">
                                        <div class="max-w-[70%] relative flex flex-col <?php echo $msg['sender_id'] == $patientId ? 'items-end' : 'items-start'; ?>">
                                            <div class="p-4 text-[14px] leading-relaxed shadow-sm <?php echo $msg['sender_id'] == $patientId ? 'bg-blue-600 text-white rounded-2xl rounded-tr-sm' : 'bg-white text-gray-700 border border-gray-100 rounded-2xl rounded-tl-sm'; ?>">
                                                <?php echo autoLink(nl2br(htmlspecialchars($msg['message_content']))); ?>
                                            </div>
                                            <p class="text-[10px] mt-1.5 font-medium text-gray-400 px-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                                <?php echo date('H:i', strtotime($msg['sent_at'])); ?> 
                                                <?php if($msg['sender_id'] == $patientId): ?>
                                                    <i class="fa-solid fa-check-double ml-1 <?php echo $msg['is_read'] ? 'text-blue-500' : 'text-gray-300'; ?>"></i>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <div class="p-5 bg-white border-t border-gray-100 flex-shrink-0 z-10">
                            <form method="POST" action="?receiver_id=<?php echo $receiverId; ?>" class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full hover:bg-gray-100 flex items-center justify-center text-gray-400 cursor-pointer transition-colors"></div>
                                <div class="flex-1 bg-gray-50 border border-gray-200 rounded-full px-5 flex items-center focus-within:bg-white focus-within:border-blue-400 focus-within:shadow-[0_0_0_3px_rgba(59,130,246,0.1)] transition-all">
                                    <input type="text" name="message_content" required autocomplete="off" placeholder="Type your message here..." class="w-full bg-transparent border-0 py-3.5 text-sm font-medium text-gray-700 outline-none placeholder-gray-400">
                                    <div class="w-8 h-8 rounded-full hover:bg-gray-200 flex items-center justify-center text-gray-400 cursor-pointer"><i class="fa-regular fa-face-smile"></i></div>
                                </div>
                                <button type="submit" class="bg-blue-600 text-white w-12 h-12 rounded-full flex items-center justify-center hover:bg-blue-700 transition-all shadow-md flex-shrink-0 transform hover:scale-105 active:scale-95">
                                    <i class="fa-solid fa-paper-plane text-sm ml-[-2px] mt-[1px]"></i>
                                </button>
                            </form>
                        </div>
                        
                    <?php else: ?>
                        <div class="flex-1 flex flex-col items-center justify-center text-gray-400 bg-gray-50/50">
                            <div class="w-24 h-24 bg-white shadow-sm border border-gray-100 rounded-full flex items-center justify-center mb-6">
                                <i class="fa-regular fa-comments text-4xl text-blue-300"></i>
                            </div>
                            <h3 class="text-lg font-bold text-gray-600 mb-1">Your Messages</h3>
                            <p class="text-sm font-medium">Select a contact from the left menu to start chatting</p>
                        </div>
                    <?php endif; ?>
                </div>
                
            </div>
        </div>
    </main>
</div>

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