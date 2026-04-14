<?php
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
// Xử lý ảnh Admin: Ưu tiên ảnh trong Session, nếu không có thì lấy ảnh mặc định trong folder img
$adminAvatar = (!empty($_SESSION['avatar']) && $_SESSION['avatar'] != 'default.png') ? $_SESSION['avatar'] : 'img/default_admin.png';

// 2. Khởi tạo biến mặc định
$totalAppointments = 0;
$newPatients = 0;
$newAppointmentsList = [];
$pendingFees = [];

try {
    // Lấy số liệu Tổng quan
    $totalAppointments = $pdo->query("SELECT COUNT(*) FROM Appointments")->fetchColumn();

    $newPatients = $pdo->query("SELECT COUNT(*) FROM Users WHERE role = 'Patient' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();

    // Lấy danh sách New Appointments
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

    // Lấy danh sách Patient Fee Pending
    $stmtFee = $pdo->query("
        SELECT u_p.full_name as patient_name, u_p.avatar_url, u_p.user_id
        FROM Appointments a
        JOIN Users u_p ON a.patient_id = u_p.user_id
        WHERE a.status = 'Completed' AND a.fee_status = 'Unpaid'
        GROUP BY u_p.user_id
        LIMIT 4
    ");
    $pendingFees = $stmtFee->fetchAll();

} catch (PDOException $e) {
    echo "<div style='color:red; background:#fee2e2; padding:20px;'>Lỗi Database: " . $e->getMessage() . "</div>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pneumo-Care | Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style> body { font-family: 'Inter', sans-serif; background-color: #f8fafc; } </style>
</head>
<body class="flex h-screen overflow-hidden text-gray-800">
    <aside class="w-64 bg-white border-r border-gray-100 flex flex-col justify-between h-full shadow-sm">
        <div>
            <div class="flex items-center gap-2 p-6 mb-4">
                <i class="fa-solid fa-lungs text-3xl text-red-400"></i>
                <h1 class="text-xl font-semibold text-gray-700">Pneumo-<span class="text-blue-500">Care</span></h1>
            </div>
            <nav class="flex flex-col gap-2 px-4">
                <a href="adm_dashboard.php" class="flex items-center gap-4 px-4 py-3 bg-blue-50 text-blue-600 rounded-lg font-medium border-l-4 border-blue-500">
                    <i class="fa-solid fa-gauge-high w-5 text-center"></i> Dashboard
                </a>
                <a href="adm_patients.php" class="flex items-center gap-4 px-4 py-3 text-gray-500 hover:bg-gray-50 rounded-lg">
                    <i class="fa-solid fa-user-group w-5 text-center"></i> Patients
                </a>
                <a href="adm_appointments.php" class="flex items-center gap-4 px-4 py-3 text-gray-500 hover:bg-gray-50 rounded-lg transition-colors">
                    <i class="fa-solid fa-file-lines w-5 text-center"></i> Appointments
                </a>
                <a href="adm_doctors.php" class="flex items-center gap-4 px-4 py-3 text-gray-500 hover:bg-gray-50 rounded-lg transition-colors">
                    <i class="fa-solid fa-user-doctor w-5 text-center"></i> Doctors
                </a>
                <a href="adm_messages.php" class="flex items-center gap-4 px-4 py-3 text-gray-500 hover:bg-gray-50 rounded-lg transition-colors">
                    <i class="fa-solid fa-comment-dots w-5 text-center"></i> Messages
                </a>
            </nav>
        </div>
        <div class="p-6 border-t">
            <a href="logout.php" class="flex items-center gap-4 text-gray-500 font-medium hover:text-red-500 transition-colors">
                <i class="fa-solid fa-right-from-bracket"></i> Logout
            </a>
        </div>
    </aside>

    <main class="flex-1 h-full overflow-y-auto p-8">
        <header class="flex justify-between items-center mb-8">
            <h2 class="text-2xl font-semibold text-gray-700">Admin Overview</h2>
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

        <div class="grid grid-cols-12 gap-6">
            <div class="col-span-3 flex flex-col gap-4">
                <div class="bg-blue-50 text-blue-800 p-6 rounded-xl flex flex-col items-center justify-center shadow-sm">
                    <span class="text-2xl font-bold"><?php echo $totalAppointments; ?></span>
                    <span class="text-sm text-blue-600">Total Appointments</span>
                </div>
                <div class="bg-green-50 text-green-800 p-6 rounded-xl flex flex-col items-center justify-center shadow-sm">
                    <span class="text-2xl font-bold"><?php echo $newPatients; ?></span>
                    <span class="text-sm text-green-600">New Patients (30d)</span>
                </div>
                <div class="bg-purple-50 text-purple-800 p-6 rounded-xl flex flex-col items-center justify-center shadow-sm">
                    <span class="text-2xl font-bold">50</span>
                    <span class="text-sm text-purple-600">Lab Tests</span>
                </div>
            </div>

            <div class="col-span-9 flex flex-col gap-6">
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                    <h3 class="font-semibold text-gray-700 mb-6 border-b pb-3">UPCOMING APPOINTMENTS</h3>
                    <table class="w-full text-left text-sm">
                        <thead class="text-gray-400">
                            <tr>
                                <th class="pb-3 font-medium">Time</th>
                                <th class="pb-3 font-medium">Date</th>
                                <th class="pb-3 font-medium">Patient</th>
                                <th class="pb-3 font-medium">Doctor</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($newAppointmentsList as $appt): ?>
                            <tr class="border-b hover:bg-gray-50 transition-colors">
                                <td class="py-4 font-medium text-blue-500"><?php echo date('h:i A', strtotime($appt['appointment_time'])); ?></td>
                                <td class="py-4"><?php echo date('d/m/Y', strtotime($appt['appointment_date'])); ?></td>
                                <td class="py-4 font-semibold text-gray-700"><?php echo htmlspecialchars($appt['patient_name']); ?></td>
                                <td class="py-4">Dr. <?php echo htmlspecialchars($appt['doctor_name']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($newAppointmentsList)) echo "<tr><td colspan='4' class='py-10 text-center text-gray-400 italic'>No data found.</td></tr>"; ?>
                        </tbody>
                    </table>
                </div>

                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                    <h3 class="font-semibold text-gray-700 mb-6 border-b pb-3">Patient Fee Pending</h3>
                    <div class="space-y-4">
                        <?php foreach ($pendingFees as $fee): ?>
                        <div class="flex justify-between items-center p-2 hover:bg-red-50 rounded-lg transition-colors">
                            <span class="text-sm font-medium text-gray-700"><?php echo htmlspecialchars($fee['patient_name']); ?></span>
                            <span class="px-3 py-1 bg-red-100 text-red-600 text-[10px] font-bold rounded-full uppercase">Unpaid</span>
                        </div>
                        <?php endforeach; ?>
                        <?php if(empty($pendingFees)) echo "<p class='text-gray-400 text-sm italic py-4'>No pending fees.</p>"; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>