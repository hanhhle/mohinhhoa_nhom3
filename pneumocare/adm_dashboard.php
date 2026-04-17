<?php
// ==========================================
// TÊN FILE: adm_dashboard.php
// CHỨC NĂNG: Tổng quan hệ thống cho Admin
// ==========================================
session_start();
require 'db.php';

// Ép PHP hiển thị lỗi để dễ dàng sửa chữa
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 1. Kiểm tra quyền Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

$adminName = $_SESSION['name'];
// Xử lý ảnh Admin
$adminAvatar = (!empty($_SESSION['avatar']) && $_SESSION['avatar'] != 'default.png') ? $_SESSION['avatar'] : 'img/default_admin.png';

// 2. Khởi tạo biến mặc định
$totalAppointments = 0;
$newPatients = 0;
$labTests = 50; // Giá trị Demo theo code cũ của bạn
$newAppointmentsList = [];
$pendingFees = [];

try {
    // Lấy số liệu Tổng quan (Chỉ đếm các lịch hẹn ở trạng thái Scheduled)
    $totalAppointments = $pdo->query("SELECT COUNT(*) FROM Appointments WHERE status = 'Scheduled'")->fetchColumn();

    // Lấy số bệnh nhân mới trong 30 ngày (Giả định bạn có cột created_at trong bảng Users)
    // Nếu bảng Users không có created_at, ta chỉ đếm tổng số Patient
    $checkColumn = $pdo->query("SHOW COLUMNS FROM Users LIKE 'created_at'")->rowCount();
    if ($checkColumn > 0) {
        $newPatients = $pdo->query("SELECT COUNT(*) FROM Users WHERE role = 'Patient' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
    } else {
        $newPatients = $pdo->query("SELECT COUNT(*) FROM Users WHERE role = 'Patient'")->fetchColumn();
    }

    // Lấy danh sách Upcoming Appointments (5 lịch gần nhất)
    $stmtAppt = $pdo->query("
        SELECT a.appointment_time, a.appointment_date, 
               u_p.full_name as patient_name, u_p.avatar_url as p_avatar,
               u_d.full_name as doctor_name
        FROM Appointments a
        JOIN Users u_p ON a.patient_id = u_p.user_id
        JOIN Users u_d ON a.doctor_id = u_d.user_id
        WHERE a.status = 'Scheduled'
        ORDER BY a.appointment_date ASC, a.appointment_time ASC
        LIMIT 5
    ");
    $newAppointmentsList = $stmtAppt->fetchAll();

    // Lấy danh sách Patient Fee Pending (Gom nhóm theo Bệnh nhân)
    $stmtFee = $pdo->query("
        SELECT u_p.full_name as patient_name, u_p.avatar_url, u_p.user_id, COUNT(a.appointment_id) as unpaid_bills
        FROM Appointments a
        JOIN Users u_p ON a.patient_id = u_p.user_id
        WHERE a.status = 'Completed' AND a.fee_status = 'Unpaid'
        GROUP BY u_p.user_id
        ORDER BY unpaid_bills DESC
        LIMIT 5
    ");
    $pendingFees = $stmtFee->fetchAll();

} catch (PDOException $e) {
    die("<div style='color:red; background:#fee2e2; padding:20px;'>Lỗi Database: " . $e->getMessage() . "</div>");
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pneumo-Care | Admin Dashboard</title>
    
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
        .content-area { padding: 0 40px 40px 40px; flex: 1; overflow-y: auto; }

        /* Scrollbar */
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
            <a href="adm_dashboard.php" class="sidebar-active flex items-center gap-4 px-4 py-3 rounded-xl font-semibold transition-colors">
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
            <a href="adm_messages.php" class="flex items-center gap-4 px-4 py-3 text-gray-500 hover:bg-gray-50 hover:text-gray-800 rounded-xl transition-colors font-medium">
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
                <h1>System Overview</h1>
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

        <div class="content-area max-w-7xl mx-auto w-full">
            <div class="grid grid-cols-1 xl:grid-cols-12 gap-8">
                
                <div class="xl:col-span-3 flex flex-col gap-6">
                    <div class="bg-blue-50/70 border border-blue-100 rounded-2xl p-8 flex flex-col items-center justify-center shadow-sm relative overflow-hidden group hover:shadow-md transition-all cursor-pointer" onclick="location.href='adm_appointments.php'">
                        <i class="fa-solid fa-calendar-check absolute -right-4 -bottom-4 text-6xl text-blue-500/10 group-hover:scale-110 transition-transform"></i>
                        <div class="relative z-10 text-center">
                            <span class="text-4xl font-extrabold text-blue-900 block mb-1"><?php echo number_format($totalAppointments); ?></span>
                            <span class="text-xs font-bold text-blue-600 uppercase tracking-widest">Upcoming Appointments</span>
                        </div>
                    </div>

                    <div class="bg-green-50/70 border border-green-100 rounded-2xl p-8 flex flex-col items-center justify-center shadow-sm relative overflow-hidden group hover:shadow-md transition-all cursor-pointer" onclick="location.href='adm_patients.php'">
                        <i class="fa-solid fa-users absolute -right-4 -bottom-4 text-6xl text-green-500/10 group-hover:scale-110 transition-transform"></i>
                        <div class="relative z-10 text-center">
                            <span class="text-4xl font-extrabold text-green-900 block mb-1"><?php echo number_format($newPatients); ?></span>
                            <span class="text-xs font-bold text-green-600 uppercase tracking-widest">New Patients</span>
                            <p class="text-[9px] text-green-500 font-medium mt-1">(Last 30 Days)</p>
                        </div>
                    </div>
                </div>

                <div class="xl:col-span-9 flex flex-col gap-8">
                    
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 flex flex-col overflow-hidden">
                        <div class="px-8 py-5 border-b border-gray-100 bg-gray-50/30 flex justify-between items-center">
                            <h3 class="font-bold text-[#003366] text-[13px] uppercase tracking-widest flex items-center gap-2">
                                <i class="fa-regular fa-calendar text-blue-500 text-lg"></i> Recent Upcoming Appointments
                            </h3>
                            <a href="adm_appointments.php" class="text-xs font-bold text-blue-500 hover:text-blue-700 uppercase tracking-wider transition-colors">View All</a>
                        </div>
                        
                        <div class="p-8 pt-4">
                            <table class="w-full text-left text-sm">
                                <thead class="text-gray-400 border-b border-gray-50">
                                    <tr>
                                        <th class="pb-3 font-semibold text-[11px] uppercase tracking-widest w-[15%]">Time</th>
                                        <th class="pb-3 font-semibold text-[11px] uppercase tracking-widest w-[20%]">Date</th>
                                        <th class="pb-3 font-semibold text-[11px] uppercase tracking-widest w-[30%]">Patient</th>
                                        <th class="pb-3 font-semibold text-[11px] uppercase tracking-widest w-[35%]">Doctor</th>
                                    </tr>
                                </thead>
                                <tbody class="text-gray-600">
                                    <?php if(empty($newAppointmentsList)): ?>
                                        <tr><td colspan="4" class="py-10 text-center text-gray-400 italic">No upcoming appointments found.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($newAppointmentsList as $appt): ?>
                                        <tr class="border-b border-gray-50 hover:bg-gray-50/50 transition-colors">
                                            <td class="py-4 font-bold text-blue-600"><?php echo date('h:i A', strtotime($appt['appointment_time'])); ?></td>
                                            <td class="py-4 font-medium text-gray-500"><?php echo date('d/m/Y', strtotime($appt['appointment_date'])); ?></td>
                                            <td class="py-4">
                                                <div class="flex items-center gap-3">
                                                    <img src="<?php echo $appt['p_avatar'] ?: 'img/default.png'; ?>" class="w-8 h-8 rounded-full object-cover border border-gray-100 shadow-sm">
                                                    <span class="font-bold text-gray-800"><?php echo htmlspecialchars($appt['patient_name']); ?></span>
                                                </div>
                                            </td>
                                            <td class="py-4 font-semibold text-gray-700">Dr. <?php echo htmlspecialchars($appt['doctor_name']); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 flex flex-col overflow-hidden">
                        <div class="px-8 py-5 border-b border-gray-100 bg-gray-50/30 flex justify-between items-center">
                            <h3 class="font-bold text-[#003366] text-[13px] uppercase tracking-widest flex items-center gap-2">
                                <i class="fa-solid fa-file-invoice-dollar text-red-500 text-lg"></i> Outstanding Payments
                            </h3>
                            <a href="adm_appointments_completed.php" class="text-xs font-bold text-blue-500 hover:text-blue-700 uppercase tracking-wider transition-colors">Manage Fees</a>
                        </div>
                        
                        <div class="p-8 pt-4">
                            <?php if(empty($pendingFees)): ?>
                                <div class="text-center py-8 text-green-500 italic bg-green-50/50 rounded-xl border border-dashed border-green-200 font-medium">
                                    <i class="fa-regular fa-face-smile-beam text-2xl mb-2 block"></i>
                                    All patient fees have been settled.
                                </div>
                            <?php else: ?>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <?php foreach ($pendingFees as $fee): ?>
                                    <div class="flex justify-between items-center p-4 border border-gray-100 bg-gray-50/30 hover:bg-red-50/30 hover:border-red-100 rounded-xl transition-all group">
                                        <div class="flex items-center gap-4">
                                            <img src="<?php echo $fee['avatar_url'] ?: 'img/default.png'; ?>" class="w-10 h-10 rounded-full object-cover border border-white shadow-sm">
                                            <div>
                                                <p class="text-sm font-bold text-gray-800"><?php echo htmlspecialchars($fee['patient_name']); ?></p>
                                                <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest mt-0.5">Owes: <span class="text-red-500 font-bold"><?php echo $fee['unpaid_bills']; ?> bill(s)</span></p>
                                            </div>
                                        </div>
                                        
                                        <a href="adm_messages.php?receiver_id=<?php echo $fee['user_id']; ?>" 
                                           class="w-8 h-8 flex items-center justify-center rounded-lg bg-white border border-gray-200 text-blue-500 hover:bg-blue-500 hover:text-white hover:border-blue-500 transition-colors shadow-sm"
                                           title="Message Patient">
                                            <i class="fa-solid fa-paper-plane text-xs"></i>
                                        </a>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </main>

</div>
</body>
</html>