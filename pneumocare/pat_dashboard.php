<?php
// ==========================================
// TÊN FILE: pat_dashboard.php
// CHỨC NĂNG: Màn hình chính của Bệnh nhân (Lịch khám, Học phí, Tin nhắn)
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

$totalAppointments = 0;
$totalCompleted = 0;
$pendingFees = [];
$recentMessages = [];

try {
    // Đếm số lịch khám sắp tới
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM Appointments WHERE patient_id = ? AND status = 'Scheduled'");
    $stmt->execute([$patientId]);
    $totalAppointments = $stmt->fetchColumn();

    // Đếm số lịch khám đã hoàn thành
    $stmtComp = $pdo->prepare("SELECT COUNT(*) FROM Appointments WHERE patient_id = ? AND status = 'Completed'");
    $stmtComp->execute([$patientId]);
    $totalCompleted = $stmtComp->fetchColumn();

    // Lấy danh sách hóa đơn chưa thanh toán
    $stmtFee = $pdo->prepare("
        SELECT a.appointment_id, a.appointment_date, u_d.full_name as doctor_name, u_d.avatar_url as doc_avatar, dp.consultation_fee
        FROM Appointments a
        JOIN Users u_d ON a.doctor_id = u_d.user_id
        JOIN Doctor_Profiles dp ON u_d.user_id = dp.doctor_id
        WHERE a.patient_id = ? AND a.status = 'Completed' AND a.fee_status = 'Unpaid'
    ");
    $stmtFee->execute([$patientId]);
    $pendingFees = $stmtFee->fetchAll();

    // LẤY DỮ LIỆU TIN NHẮN MỚI NHẤT (Từ Admin hoặc Bác sĩ)
    $stmtMsg = $pdo->prepare("
        SELECT m.sender_id, m.message_content, m.sent_at, m.is_read, u.full_name as sender_name, u.avatar_url as sender_avatar, u.role
        FROM Messages m
        JOIN Users u ON m.sender_id = u.user_id
        WHERE m.receiver_id = ?
        AND m.sent_at = (
            SELECT MAX(sent_at) 
            FROM Messages 
            WHERE sender_id = m.sender_id AND receiver_id = ?
        )
        ORDER BY m.sent_at DESC
        LIMIT 3
    ");
    $stmtMsg->execute([$patientId, $patientId]);
    $recentMessages = $stmtMsg->fetchAll();

} catch (PDOException $e) {}

// Hàm format thời gian rút gọn
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
    <title>Pneumo-Care | Patient Dashboard</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; color: #1f2937; }

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
        .content-area { padding: 0 40px 40px 40px; flex: 1; overflow-y: auto; }
        
        /* Scrollbar */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        
        .card-title { font-size: 14px; font-weight: 700; color: #374151; text-transform: uppercase; letter-spacing: 0.5px; }
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
            <a href="pat_dashboard.php" class="sidebar-active flex items-center gap-4 px-4 py-3 rounded-xl transition-colors font-medium">
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
            <a href="pat_messages.php" class="flex items-center gap-4 px-4 py-3 text-gray-500 hover:bg-gray-50 hover:text-gray-800 rounded-xl transition-colors font-medium">
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
                <h1>Dashboard</h1>
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

        <div class="content-area max-w-7xl mx-auto w-full">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden flex flex-col">
                    <div class="px-8 py-5 border-b border-gray-100 bg-gray-50/30 flex justify-between items-center">
                        <h3 class="card-title">Activity Overview</h3>
                        <a href="pat_appointments.php" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-blue-50 text-blue-500 transition-colors" title="Manage appointments"><i class="fa-solid fa-arrow-up-right-from-square"></i></a>
                    </div>
                    
                    <div class="p-8 space-y-5">
                        <div class="bg-blue-50/60 rounded-xl p-6 flex items-center gap-6 border border-blue-100 hover:shadow-md transition-shadow cursor-pointer" onclick="location.href='pat_appointments.php'">
                            <div class="w-14 h-14 bg-white rounded-full flex items-center justify-center text-2xl shadow-sm text-blue-600"><i class="fa-solid fa-calendar-days"></i></div>
                            <div>
                                <p class="text-3xl font-extrabold text-blue-900"><?php echo $totalAppointments; ?></p>
                                <p class="text-sm text-blue-600/80 font-bold uppercase tracking-wider mt-1">Upcoming Appointments</p>
                            </div>
                        </div>  
                        
                        <div class="bg-green-50/60 rounded-xl p-6 flex items-center gap-6 border border-green-100 hover:shadow-md transition-shadow cursor-pointer" onclick="location.href='pat_report.php'">
                            <div class="w-14 h-14 bg-white rounded-full flex items-center justify-center text-2xl shadow-sm text-green-600"><i class="fa-solid fa-file-medical"></i></div>
                            <div>
                                <p class="text-3xl font-extrabold text-green-900"><?php echo $totalCompleted; ?></p>
                                <p class="text-sm text-green-600/80 font-bold uppercase tracking-wider mt-1">Medical Records Generated</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden flex flex-col">
                    <div class="px-8 py-5 border-b border-gray-100 bg-gray-50/30 flex justify-between items-center">
                        <h3 class="card-title">Recent Messages</h3>
                        <a href="pat_messages.php" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-blue-50 text-blue-500 transition-colors" title="Open Messenger"><i class="fa-solid fa-arrow-up-right-from-square"></i></a>
                    </div>
                    
                    <div class="p-2 flex-1">
                        <?php if(empty($recentMessages)): ?>
                            <div class="flex flex-col items-center justify-center h-full text-gray-400 min-h-[200px]">
                                <div class="w-14 h-14 bg-gray-50 rounded-full flex items-center justify-center mb-3"><i class="fa-regular fa-comments text-2xl text-gray-300"></i></div>
                                <p class="italic text-sm font-medium">No new messages.</p>
                            </div>
                        <?php else: ?>
                            <div class="flex flex-col h-full">
                                <?php foreach($recentMessages as $msg): ?>
                                <a href="pat_messages.php?receiver_id=<?php echo $msg['sender_id']; ?>" class="flex items-start gap-4 p-5 border-b border-gray-50 last:border-0 hover:bg-blue-50/20 transition-colors group">
                                    <div class="relative flex-shrink-0">
                                        <img src="<?php echo $msg['sender_avatar'] ?: 'img/default.png'; ?>" class="w-12 h-12 rounded-full object-cover border border-gray-200">
                                        <?php if(!$msg['is_read']): ?>
                                            <span class="absolute top-0 right-0 w-3.5 h-3.5 bg-red-500 border-2 border-white rounded-full"></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex justify-between items-baseline mb-1">
                                            <p class="font-bold text-gray-800 text-sm truncate group-hover:text-blue-600 transition-colors">
                                                <?php echo $msg['role'] == 'Admin' ? 'Support' : 'Dr. ' . htmlspecialchars($msg['sender_name']); ?>
                                            </p>
                                            <span class="text-[11px] font-semibold text-gray-400 flex-shrink-0 ml-2"><?php echo timeAgo($msg['sent_at']); ?></span>
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
                    <div class="bg-gray-50/50 px-8 py-3 border-t border-gray-100 text-center">
                        <a href="pat_messages.php" class="text-blue-600 hover:text-blue-800 text-xs font-bold uppercase tracking-wider transition-colors">View All Messages</a>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden col-span-1 lg:col-span-2">
                    <div class="px-8 py-5 border-b border-gray-100 bg-gray-50/30 flex justify-between items-center">
                        <h3 class="card-title text-yellow-600">Pending Fees <span class="bg-yellow-100 text-yellow-700 px-2 py-0.5 rounded-full ml-2"><?php echo count($pendingFees); ?></span></h3>
                    </div>
                    
                    <div class="p-8">
                        <?php if(empty($pendingFees)): ?>
                            <div class="text-center py-10 text-gray-400 bg-gray-50 rounded-xl border border-dashed border-gray-200">
                                <i class="fa-solid fa-check-circle text-3xl text-green-300 mb-3 block"></i>
                                <p class="font-medium italic">You have no pending fees. Great!</p>
                            </div>
                        <?php else: ?>
                            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                                <?php foreach($pendingFees as $fee): ?>
                                <div class="bg-white border-2 border-yellow-100 rounded-xl p-6 hover:shadow-lg transition-all relative overflow-hidden group">
                                    <div class="absolute -right-6 -top-6 w-24 h-24 bg-yellow-50 rounded-full z-0 group-hover:scale-110 transition-transform"></div>
                                    
                                    <div class="relative z-10 flex flex-col h-full">
                                        <div class="flex items-center gap-4 mb-4">
                                            <img src="<?php echo $fee['doc_avatar'] ?: 'img/default.png'; ?>" class="w-14 h-14 rounded-full object-cover border-2 border-white shadow-sm">
                                            <div>
                                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-0.5">Doctor</p>
                                                <p class="font-bold text-gray-800 text-sm">Dr. <?php echo htmlspecialchars($fee['doctor_name']); ?></p>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-5">
                                            <p class="text-xs text-gray-500 font-medium bg-gray-50 inline-block px-3 py-1.5 rounded-md border border-gray-100">
                                                <i class="fa-regular fa-calendar mr-1"></i> <?php echo date('d/m/Y', strtotime($fee['appointment_date'])); ?>
                                            </p>
                                        </div>
                                        
                                        <div class="mt-auto border-t border-dashed border-yellow-200 pt-4 flex items-center justify-between">
                                            <div>
                                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-0.5">Amount</p>
                                                <p class="text-lg font-extrabold text-yellow-600"><?php echo number_format($fee['consultation_fee']); ?> <span class="text-[10px] text-gray-500">VND</span></p>
                                            </div>
                                            <button onclick="location.href='pat_payment.php?appt_id=<?php echo $fee['appointment_id']; ?>'" class="bg-blue-600 text-white px-5 py-2.5 rounded-xl text-xs font-bold hover:bg-blue-700 transition-colors shadow-md uppercase tracking-wider">
                                                Pay Now
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
            </div>
        </div>
    </main>
</div>
</body>
</html>