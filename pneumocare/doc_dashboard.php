<?php
// ==========================================
// TÊN FILE: doc_dashboard.php
// CHỨC NĂNG: Màn hình tổng quan (Lịch khám & Tin nhắn mới) của Bác sĩ
// ==========================================
session_start();
require 'db.php';
ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL);

// 1. Kiểm tra quyền Doctor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Doctor') { 
    header("Location: login.php"); 
    exit(); 
}

$doctorId = $_SESSION['user_id'];
$doctorName = $_SESSION['name'];
$doctorAvatar = (!empty($_SESSION['avatar']) && $_SESSION['avatar'] != 'default.png') ? $_SESSION['avatar'] : 'img/default.png';

$todayAppointments = [];
$totalAppointments = 0;
$recentMessages = [];

try {
    // --- LẤY DỮ LIỆU APPOINTMENTS ---
    // Tổng số lịch hẹn của bác sĩ
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM Appointments WHERE doctor_id = ? AND status = 'Scheduled'");
    $stmtCount->execute([$doctorId]);
    $totalAppointments = $stmtCount->fetchColumn();

    // Danh sách lịch khám sắp tới (giới hạn 5 cái cho Dashboard)
    $stmtAppts = $pdo->prepare("
        SELECT a.*, u_p.full_name as patient_name, u_p.avatar_url as p_avatar, pp.date_of_birth
        FROM Appointments a
        JOIN Users u_p ON a.patient_id = u_p.user_id
        JOIN Patient_Profiles pp ON u_p.user_id = pp.patient_id
        WHERE a.doctor_id = ? AND a.status = 'Scheduled'
        ORDER BY a.appointment_date ASC, a.appointment_time ASC
        LIMIT 5
    ");
    $stmtAppts->execute([$doctorId]);
    $todayAppointments = $stmtAppts->fetchAll();

    // --- LẤY DỮ LIỆU TIN NHẮN (MỚI NHẤT) ---
    // Truy vấn để lấy tin nhắn gần nhất từ mỗi người gửi (Group by sender_id)
    $stmtMsg = $pdo->prepare("
        SELECT m.sender_id, m.message_content, m.sent_at, m.is_read, u.full_name as sender_name, u.avatar_url as sender_avatar
        FROM Messages m
        JOIN Users u ON m.sender_id = u.user_id
        WHERE m.receiver_id = ?
        AND m.sent_at = (
            SELECT MAX(sent_at) 
            FROM Messages 
            WHERE sender_id = m.sender_id AND receiver_id = ?
        )
        ORDER BY m.sent_at DESC
        LIMIT 4
    ");
    $stmtMsg->execute([$doctorId, $doctorId]);
    $recentMessages = $stmtMsg->fetchAll();

} catch (PDOException $e) {
    die("<div style='color:red; padding: 20px;'>Lỗi Database: " . $e->getMessage() . "</div>");
}

function calculateAge($birthDate) { 
    if(!$birthDate) return "N/A";
    return date_diff(date_create($birthDate), date_create('today'))->y; 
}

// Hàm format thời gian rút gọn (vd: "2 hours ago", "Yesterday")
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    if ($diff < 60) return "Just now";
    if ($diff < 3600) return floor($diff / 60) . "m ago";
    if ($diff < 86400) return floor($diff / 3600) . "h ago";
    if ($diff < 172800) return "Yesterday";
    return date('d/m/Y', $time);
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pneumo-Care | Doctor Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; color: #1f2937; }
        .sidebar-active { background-color: #eff6ff; color: #2563eb; border-left: 4px solid #2563eb; }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        
        .card-title { font-size: 14px; font-weight: 700; color: #374151; text-transform: uppercase; letter-spacing: 0.5px; }
    </style>
</head>
<body class="flex h-screen overflow-hidden text-gray-800">

    <aside class="w-64 bg-white border-r border-gray-100 flex flex-col h-full shadow-sm">
        <div class="flex items-center gap-2 p-6 border-b">
            <i class="fa-solid fa-lungs text-3xl text-red-400"></i>
            <h1 class="text-xl font-semibold text-gray-700">Pneumo-<span class="text-blue-500">Care</span></h1>
        </div>

        <nav class="flex-1 px-4 py-6 space-y-1">
            <a href="doc_dashboard.php" class="sidebar-active flex items-center gap-4 px-4 py-3 rounded-lg font-medium">
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
            <a href="doc_messages.php" class="flex items-center gap-4 px-4 py-3 text-gray-500 hover:bg-gray-50 rounded-lg transition-colors">
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
            <header class="h-[72px] bg-white border border-gray-100 rounded-2xl shadow-sm flex items-center justify-between px-6">
                <h2 class="text-2xl font-bold text-[#003366]">Dashboard</h2>
                <div class="flex items-center gap-6">
                    <div class="flex items-center gap-3">
                        <div class="text-right hidden sm:block"><p class="text-sm font-semibold text-gray-800" style="line-height: 1.2;"><?php echo htmlspecialchars($doctorName); ?></p><p class="text-xs text-gray-500 font-medium">Doctor</p></div>
                        <img src="<?php echo $doctorAvatar; ?>" class="w-10 h-10 rounded-full border border-gray-200 shadow-sm object-cover">
                    </div>
                </div>
            </header>
        </div>

        <div class="flex-1 px-10 pb-10 overflow-y-auto">
            
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 mb-8 overflow-hidden">
                <div class="px-8 py-5 border-b border-gray-100 flex justify-between items-center bg-gray-50/30">
                    <h3 class="card-title">Upcoming Appointments <span class="text-blue-600 ml-1 text-xs px-2 py-0.5 bg-blue-50 rounded-full border border-blue-100"><?php echo $totalAppointments; ?></span></h3>
                    <a href="doc_appointments.php" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-blue-50 text-blue-500 transition-colors" title="View all appointments">
                        <i class="fa-solid fa-arrow-up-right-from-square"></i>
                    </a>
                </div>
                
                <table class="w-full text-sm text-left">
                    <thead>
                        <tr class="text-gray-400 border-b border-gray-100 text-xs uppercase tracking-wider">
                            <th class="py-4 px-8 font-semibold w-[15%]">Time</th>
                            <th class="py-4 px-8 font-semibold w-[20%]">Date</th>
                            <th class="py-4 px-8 font-semibold w-[30%]">Patient Name</th>
                            <th class="py-4 px-8 font-semibold w-[15%]">Age</th>
                            <th class="py-4 px-8 font-semibold w-[20%] text-right">User Action</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700">
                        <?php if(empty($todayAppointments)): ?>
                            <tr><td colspan="5" class="py-12 text-center text-gray-400 italic">You have no upcoming appointments.</td></tr>
                        <?php else: ?>
                            <?php foreach($todayAppointments as $appt): ?>
                            <tr class="hover:bg-blue-50/20 transition-colors border-b border-gray-50 last:border-0">
                                <td class="px-8 py-5 font-bold text-blue-600"><?php echo date('h:i A', strtotime($appt['appointment_time'])); ?></td>
                                <td class="px-8 py-5 font-medium text-gray-600"><?php echo date('d/m/Y', strtotime($appt['appointment_date'])); ?></td>
                                <td class="px-8 py-5">
                                    <div class="flex items-center gap-3">
                                        <img src="<?php echo $appt['p_avatar'] ?: 'img/default.png'; ?>" class="w-9 h-9 rounded-full object-cover shadow-sm border border-gray-100" alt="">
                                        <span class="font-bold text-gray-800"><?php echo htmlspecialchars($appt['patient_name']); ?></span>
                                    </div>
                                </td>
                                <td class="px-8 py-5 font-medium text-gray-600"><?php echo calculateAge($appt['date_of_birth']); ?> years</td>
                                <td class="px-8 py-5 text-right">
                                    <a href="doc_appointments.php" class="text-blue-600 hover:text-blue-800 font-semibold text-sm transition-colors">Manage <i class="fa-solid fa-chevron-right ml-1 text-[10px]"></i></a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-8 py-5 border-b border-gray-100 flex justify-between items-center bg-gray-50/30">
                    <h3 class="card-title">Recent Messages</h3>
                    <a href="doc_messages.php" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-blue-50 text-blue-500 transition-colors" title="Open Messenger">
                        <i class="fa-solid fa-arrow-up-right-from-square"></i>
                    </a>
                </div>
                
                <div class="p-2">
                    <?php if(empty($recentMessages)): ?>
                        <div class="p-10 text-center text-gray-400">
                            <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-3"><i class="fa-regular fa-comments text-2xl text-gray-300"></i></div>
                            <p class="italic text-sm">No new messages.</p>
                        </div>
                    <?php else: ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 p-4">
                            <?php foreach($recentMessages as $msg): ?>
                            <a href="doc_messages.php?receiver_id=<?php echo $msg['sender_id']; ?>" class="flex items-start gap-4 p-4 rounded-xl border border-gray-100 hover:border-blue-200 hover:shadow-sm hover:bg-blue-50/10 transition-all group">
                                <div class="relative flex-shrink-0">
                                    <img src="<?php echo $msg['sender_avatar'] ?: 'img/default.png'; ?>" class="w-12 h-12 rounded-full object-cover border border-gray-200">
                                    <?php if(!$msg['is_read']): ?>
                                        <span class="absolute top-0 right-0 w-3 h-3 bg-red-500 border-2 border-white rounded-full"></span>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex justify-between items-baseline mb-1">
                                        <p class="font-bold text-gray-800 text-sm truncate group-hover:text-blue-600 transition-colors"><?php echo htmlspecialchars($msg['sender_name']); ?></p>
                                        <span class="text-[11px] font-medium text-gray-400 flex-shrink-0 ml-2"><?php echo timeAgo($msg['sent_at']); ?></span>
                                    </div>
                                    <p class="text-sm text-gray-500 truncate <?php echo !$msg['is_read'] ? 'font-semibold text-gray-800' : ''; ?>">
                                        <?php echo htmlspecialchars($msg['message_content']); ?>
                                    </p>
                                </div>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if(!empty($recentMessages)): ?>
                <div class="bg-gray-50 px-8 py-3 border-t border-gray-100 text-center">
                    <a href="doc_messages.php" class="text-blue-600 hover:text-blue-800 text-xs font-bold uppercase tracking-wider transition-colors">View All Messages</a>
                </div>
                <?php endif; ?>
            </div>

        </div>
    </main>
</body>
</html>