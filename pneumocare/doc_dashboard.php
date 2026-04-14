<?php
// ==========================================
// TÊN FILE: doc_dashboard.php
// CHỨC NĂNG: Bảng điều khiển chính của Bác sĩ
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

try {
    // Lấy tổng số lịch hẹn của bác sĩ
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM Appointments WHERE doctor_id = ? AND status = 'Scheduled'");
    $stmtCount->execute([$doctorId]);
    $totalAppointments = $stmtCount->fetchColumn();

    // Lấy danh sách lịch khám sắp tới (giới hạn 5 cái cho Dashboard)
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

} catch (PDOException $e) {
    die("Lỗi Database: " . $e->getMessage());
}

function calculateAge($birthDate) { 
    if(!$birthDate) return "N/A";
    return date_diff(date_create($birthDate), date_create('today'))->y; 
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
        .sidebar-active { background-color: #eff6ff; color: #2563eb; border-left: 4px solid #2563eb; }
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
                <span>AI Diagnosis</span>
            </a>
            <a href="#" class="flex items-center gap-4 px-4 py-3 text-gray-500 hover:bg-gray-50 rounded-lg transition-colors">
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

    <main class="flex-1 overflow-y-auto bg-gray-50">
        <div class="p-8">
            <header class="flex justify-between items-center mb-8">
                <h2 class="text-2xl font-semibold text-gray-700">Dashboard</h2>
                <div class="flex items-center gap-6">
                    <div class="relative cursor-pointer">
                        <i class="fa-solid fa-bell text-xl text-gray-400"></i>
                        <span class="absolute -top-1 -right-1 w-2.5 h-2.5 bg-red-500 rounded-full"></span>
                    </div>
                    <div class="flex items-center gap-3">
                        <img src="<?php echo $doctorAvatar; ?>" class="w-10 h-10 rounded-full border-2 border-white shadow object-cover" alt="Doctor">
                        <div>
                            <p class="text-sm font-semibold"><?php echo htmlspecialchars($doctorName); ?></p>
                            <p class="text-xs text-gray-500">Doctor</p>
                        </div>
                    </div>
                </div>
            </header>

            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 mb-8">
                <div class="p-6 border-b flex justify-between items-center">
                    <h3 class="font-semibold text-lg">UPCOMING APPOINTMENTS <span class="text-blue-500 ml-2 text-sm">(<?php echo $totalAppointments; ?>)</span></h3>
                    <a href="doc_appointments.php"><i class="fa-solid fa-arrow-up-right-from-square text-blue-500 cursor-pointer hover:text-blue-700"></i></a>
                </div>
                
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr class="text-gray-500 border-b">
                            <th class="py-4 px-6 text-left font-medium">Time</th>
                            <th class="py-4 px-6 text-left font-medium">Date</th>
                            <th class="py-4 px-6 text-left font-medium">Patient Name</th>
                            <th class="py-4 px-6 text-left font-medium text-center">Patient Age</th>
                            <th class="py-4 px-6 text-left font-medium">User Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y text-gray-700">
                        <?php if(empty($todayAppointments)): ?>
                            <tr><td colspan="5" class="py-10 text-center text-gray-400 italic">You have no upcoming appointments.</td></tr>
                        <?php else: ?>
                            <?php foreach($todayAppointments as $appt): ?>
                            <tr class="hover:bg-blue-50/30 transition-colors">
                                <td class="px-6 py-4 font-semibold text-blue-600"><?php echo date('h:i A', strtotime($appt['appointment_time'])); ?></td>
                                <td class="px-6 py-4"><?php echo date('d/m/Y', strtotime($appt['appointment_date'])); ?></td>
                                <td class="px-6 py-4 flex items-center gap-3">
                                    <img src="<?php echo $appt['p_avatar'] ?: 'img/default.png'; ?>" class="w-8 h-8 rounded-full object-cover" alt="">
                                    <span class="font-medium"><?php echo htmlspecialchars($appt['patient_name']); ?></span>
                                </td>
                                <td class="px-6 py-4 text-center"><?php echo calculateAge($appt['date_of_birth']); ?></td>
                                <td class="px-6 py-4">
                                    <a href="#" class="text-blue-600 hover:underline cursor-pointer">View Details</a>
                                    <button class="ml-4 text-red-400 hover:text-red-600 transition-colors"><i class="fa-solid fa-xmark"></i></button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-gray-100">
                <div class="p-6 border-b flex justify-between items-center">
                    <h3 class="font-semibold text-lg">Messages</h3>
                    <i class="fa-solid fa-arrow-up-right-from-square text-blue-500 cursor-pointer"></i>
                </div>
                <div class="p-10 text-center text-gray-400">
                    <i class="fa-regular fa-comments text-4xl mb-3 opacity-30"></i>
                    <p>No new messages.</p>
                </div>
            </div>
        </div>
    </main>
</body>
</html>