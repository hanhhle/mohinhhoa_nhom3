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
$patientAvatar = (!empty($_SESSION['avatar']) && $_SESSION['avatar'] != 'default.png') ? $_SESSION['avatar'] : 'img/default_patient.png';

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

// 2. ĐÁNH DẤU ĐÃ ĐỌC (is_read = 1) KHI MỞ TIN NHẮN
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
$receiverAvatar = "img/default.png";
$receiverSpec = "";

try {
    // 3. LẤY THÔNG TIN ADMIN (Luôn ghim lên đầu kèm tin nhắn cuối & số tin chưa đọc)
    $stmtAdmin = $pdo->prepare("
        SELECT u.user_id, u.full_name, u.avatar_url, 'Support' as role,
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

    // 4. LẤY DANH SÁCH BÁC SĨ (Chỉ những người đã đặt lịch)
    $stmtList = $pdo->prepare("
        SELECT DISTINCT u.user_id, u.full_name, u.avatar_url, dp.speciality as role,
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
        JOIN Appointments a ON u.user_id = a.doctor_id
        WHERE u.role = 'Doctor' AND a.patient_id = :pat6
        ORDER BY last_interaction DESC
    ");
    $stmtList->execute([
        ':pat1' => $patientId, ':pat2' => $patientId, ':pat3' => $patientId,
        ':pat4' => $patientId, ':pat5' => $patientId, ':pat6' => $patientId
    ]);
    $doctorsList = $stmtList->fetchAll();

    // 5. LẤY LỊCH SỬ TIN NHẮN CỦA NGƯỜI ĐANG ĐƯỢC CHỌN
    if ($receiverId) {
        $stmtRec = $pdo->prepare("SELECT user_id, full_name, avatar_url, role FROM Users WHERE user_id = ?");
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
            $receiverAvatar = $recInfo['avatar_url'] ?: 'img/default.png';

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
        
        /* SIDEBAR CHUẨN ĐỒNG BỘ */
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
        
        /* CONTENT AREA CHO ỨNG DỤNG CHAT */
        .content-area { padding: 0 40px 40px 40px; flex: 1; display: flex; flex-direction: column; min-h-0; }

        /* Scrollbar */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        
        /* Ẩn scrollbar cho khung danh bạ nhưng vẫn cuộn được */
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
                        <img src="<?php echo $patientAvatar; ?>" class="w-10 h-10 rounded-full object-cover border border-gray-200 shadow-sm" alt="Avatar">
                    </div>
                </div>
            </header>
        </div>

        <div class="content-area">
            <div class="flex flex-1 bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden min-h-0">
                
                <div class="w-80 bg-white border-r border-gray-100 flex flex-col flex-shrink-0">
                    <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                        <h2 class="text-[15px] font-bold text-gray-800 uppercase tracking-wide">Recent Chats</h2>
                    </div>
                    
                    <div class="flex-1 overflow-y-auto no-scrollbar">
                        <?php if($adminContact): ?>
                        <a href="?receiver_id=<?php echo $adminContact['user_id']; ?>" 
                           class="block p-4 border-b border-gray-50 transition-colors <?php echo $receiverId == $adminContact['user_id'] ? 'bg-blue-50 border-l-4 border-l-blue-500' : 'hover:bg-gray-50 border-l-4 border-l-transparent'; ?>">
                            <div class="flex items-center gap-4">
                                <div class="relative flex-shrink-0">
                                    <div class="w-12 h-12 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-xl shadow-sm border border-blue-200">
                                        <i class="fa-solid fa-headset"></i>
                                    </div>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex justify-between items-center mb-1">
                                        <span class="text-sm truncate <?php echo $adminContact['unread_count'] > 0 ? 'font-bold text-gray-900' : 'font-semibold text-gray-700'; ?>">
                                            Pneumo-Care Support
                                        </span>
                                        <?php if($adminContact['unread_count'] > 0): ?>
                                            <span class="bg-red-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full min-w-[18px] text-center shadow-sm">
                                                <?php echo $adminContact['unread_count']; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-[9px] uppercase px-2 py-0.5 bg-gray-100 rounded text-gray-400 font-bold">Admin</span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-xs truncate <?php echo $adminContact['unread_count'] > 0 ? 'text-gray-900 font-bold' : 'text-gray-500'; ?>">
                                        <?php echo $adminContact['last_message'] ? htmlspecialchars($adminContact['last_message']) : 'Click to start messaging'; ?>
                                    </p>
                                </div>
                            </div>
                        </a>
                        <?php endif; ?>

                        <?php if(empty($doctorsList)): ?>
                            <div class="p-8 text-center text-gray-400 text-sm italic">No doctors available.</div>
                        <?php else: ?>
                            <?php foreach ($doctorsList as $doc): ?>
                            <a href="?receiver_id=<?php echo $doc['user_id']; ?>" 
                               class="block p-4 transition-colors <?php echo $receiverId == $doc['user_id'] ? 'bg-blue-50 border-l-4 border-l-blue-500' : 'hover:bg-gray-50 border-b border-gray-50 border-l-4 border-l-transparent'; ?>">
                                <div class="flex items-center gap-4">
                                    <img src="<?php echo $doc['avatar_url'] ?: 'img/default.png'; ?>" class="w-12 h-12 rounded-full object-cover shadow-sm flex-shrink-0 border border-gray-100">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex justify-between items-center mb-1">
                                            <span class="text-sm truncate <?php echo $doc['unread_count'] > 0 ? 'font-bold text-gray-900' : 'font-semibold text-gray-700'; ?>">
                                                Dr. <?php echo htmlspecialchars($doc['full_name']); ?>
                                            </span>
                                            <?php if($doc['unread_count'] > 0): ?>
                                                <span class="bg-red-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full min-w-[18px] text-center shadow-sm">
                                                    <?php echo $doc['unread_count']; ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <p class="text-xs truncate <?php echo $doc['unread_count'] > 0 ? 'text-gray-900 font-bold' : 'text-gray-500'; ?>">
                                            <?php echo $doc['last_message'] ? htmlspecialchars($doc['last_message']) : 'Click to start messaging'; ?>
                                        </p>
                                    </div>
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
                                <img src="<?php echo $receiverAvatar; ?>" class="w-10 h-10 rounded-full object-cover border border-gray-100 shadow-sm">
                                <div>
                                    <h3 class="font-bold text-gray-800 text-[15px] flex items-center gap-2">
                                        <?php echo htmlspecialchars($receiverName); ?>
                                        <span class="w-2 h-2 bg-green-500 rounded-full shadow-[0_0_5px_rgba(34,197,94,0.5)]" title="Online"></span>
                                    </h3>
                                    <p class="text-xs text-gray-500 font-medium"><?php echo htmlspecialchars($receiverSpec); ?></p>
                                </div>
                            </div>
                            <div class="text-gray-400 hover:text-blue-500 cursor-pointer transition-colors"><i class="fa-solid fa-circle-info text-xl"></i></div>
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
                                                <?php echo nl2br(htmlspecialchars($msg['message_content'])); ?>
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
                                <div class="w-10 h-10 rounded-full hover:bg-gray-100 flex items-center justify-center text-gray-400 cursor-pointer transition-colors"><i class="fa-solid fa-paperclip"></i></div>
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
    // Tự động cuộn xuống tin nhắn mới nhất
    const chatBox = document.getElementById('chatBox');
    if (chatBox) { 
        chatBox.scrollTop = chatBox.scrollHeight; 
        
        // Fix nhỏ: Cuộn lại một lần nữa sau khi ảnh (nếu có) load xong để đảm bảo chính xác 100%
        setTimeout(() => { chatBox.scrollTop = chatBox.scrollHeight; }, 100);
    }
</script>
</body>
</html>