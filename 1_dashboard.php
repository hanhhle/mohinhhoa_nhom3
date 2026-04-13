<?php
session_start();
require 'db.php';

// Kiểm tra quyền Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

$adminName = $_SESSION['name'];

// --- 1. LẤY SỐ LIỆU TỔNG QUAN (ACTIVITY OVERVIEW) ---
// Tổng Lịch hẹn
$stmt = $pdo->query("SELECT COUNT(*) as total_appts FROM Appointments");
$totalAppointments = $stmt->fetch()['total_appts'];

// Tổng Bệnh nhân mới (Ví dụ: trong 30 ngày qua)
$stmt = $pdo->query("SELECT COUNT(*) as new_patients FROM Users WHERE role = 'Patient' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
$newPatients = $stmt->fetch()['new_patients'];

// Tổng Số thuốc bán ra
$stmt = $pdo->query("SELECT SUM(quantity) as total_meds FROM Prescriptions");
$totalMeds = $stmt->fetch()['total_meds'] ?? 0;

// --- 2. LẤY DANH SÁCH LỊCH HẸN SẮP TỚI (NEW APPOINTMENTS) ---
$stmtAppt = $pdo->query("
    SELECT a.appointment_time, a.appointment_date, 
           u_patient.full_name as patient_name, u_patient.avatar_url as p_avatar,
           u_doctor.full_name as doctor_name
    FROM Appointments a
    JOIN Users u_patient ON a.patient_id = u_patient.user_id
    JOIN Users u_doctor ON a.doctor_id = u_doctor.user_id
    WHERE a.status = 'Scheduled'
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
    LIMIT 5
");
$newAppointmentsList = $stmtAppt->fetchAll();

// --- 3. LẤY DANH SÁCH CHƯA TRẢ PHÍ (PATIENT FEE PENDING) ---
$stmtFee = $pdo->query("
    SELECT u_patient.full_name as patient_name, u_patient.avatar_url, u_patient.user_id
    FROM Appointments a
    JOIN Users u_patient ON a.patient_id = u_patient.user_id
    WHERE a.status = 'Completed' AND a.fee_status = 'Unpaid'
    GROUP BY u_patient.user_id
    LIMIT 4
");
$pendingFees = $stmtFee->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pneumo-Care | Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style> body { font-family: 'Inter', sans-serif; background-color: #f8fafc; } </style>
</head>
<body class="flex h-screen overflow-hidden text-gray-800">
    <aside class="w-64 bg-white border-r border-gray-100 flex flex-col justify-between h-full z-10 shadow-sm relative">
        <div>
            <div class="flex items-center gap-2 p-6 mb-4">
                <i class="fa-solid fa-lungs text-3xl text-red-400"></i>
                <h1 class="text-xl font-semibold text-gray-700">Pneumo-<span class="text-blue-500">Care</span></h1>
            </div>
            <nav class="flex flex-col gap-2 px-4">
                <a href="#" class="flex items-center gap-4 px-4 py-3 bg-blue-50 text-blue-500 rounded-lg font-medium border-l-4 border-blue-500">
                    <i class="fa-solid fa-gauge-high w-5 text-center"></i> Dashboard
                </a>
                <a href="#" class="flex items-center gap-4 px-4 py-3 text-gray-400 hover:bg-gray-50 rounded-lg transition-colors">
                    <i class="fa-solid fa-user-group w-5 text-center"></i> Patients
                </a>
                <a href="#" class="flex items-center gap-4 px-4 py-3 text-gray-400 hover:bg-gray-50 rounded-lg transition-colors">
                    <i class="fa-solid fa-file-lines w-5 text-center"></i> Appointments
                </a>
                <a href="#" class="flex items-center gap-4 px-4 py-3 text-gray-400 hover:bg-gray-50 rounded-lg transition-colors">
                    <i class="fa-solid fa-user-doctor w-5 text-center"></i> Doctors
                </a>
                <a href="#" class="flex items-center gap-4 px-4 py-3 text-gray-400 hover:bg-gray-50 rounded-lg transition-colors">
                    <i class="fa-solid fa-comment-dots w-5 text-center"></i> Messages
                </a>
                <a href="#" class="flex items-center gap-4 px-4 py-3 text-gray-400 hover:bg-gray-50 rounded-lg transition-colors">
                    <i class="fa-solid fa-gear w-5 text-center"></i> Settings
                </a>
            </nav>
        </div>
        <div class="p-6">
            <a href="#" class="flex items-center gap-4 text-gray-500 font-medium hover:text-gray-700">
                <i class="fa-solid fa-right-from-bracket"></i> Logout
            </a>
        </div>
    </aside>

    <main class="flex-1 h-full overflow-y-auto p-8">
        <header class="flex justify-between items-center mb-8">
            <h2 class="text-2xl font-semibold text-gray-700">Dashboard</h2>
            <div class="flex items-center gap-6">
                <div class="relative cursor-pointer">
                    <i class="fa-solid fa-bell text-gray-400 text-xl"></i>
                    <div class="absolute -top-1 -right-1 w-2.5 h-2.5 bg-red-500 rounded-full border-2 border-white"></div>
                </div>
                <div class="flex items-center gap-3">
                    <img src="https://randomuser.me/api/portraits/women/44.jpg" class="w-10 h-10 rounded-full border-2 border-blue-100" alt="Admin">
                    <div>
                        <p class="text-sm font-semibold text-gray-700"><?php echo htmlspecialchars($adminName); ?></p>
                        <p class="text-xs text-gray-400">Admin</p>
                    </div>
                </div>
            </div>
        </header>

        <div class="grid grid-cols-12 gap-6">
            <div class="col-span-3 bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="font-semibold text-gray-700">Activity Overview</h3>
                    <button class="text-sm text-gray-400 flex items-center gap-1">Weekly <i class="fa-solid fa-caret-down"></i></button>
                </div>
                <div class="flex flex-col gap-4">
                    <div class="bg-blue-50 text-blue-800 p-4 rounded-xl flex flex-col items-center justify-center h-28">
                        <i class="fa-solid fa-file-lines text-2xl mb-2 text-blue-500"></i>
                        <span class="text-xl font-bold"><?php echo $totalAppointments; ?></span>
                        <span class="text-sm text-blue-600">Appointments</span>
                    </div>
                    <div class="bg-yellow-50 text-yellow-800 p-4 rounded-xl flex flex-col items-center justify-center h-28">
                        <i class="fa-solid fa-prescription-bottle-medical text-2xl mb-2 text-yellow-500"></i>
                        <span class="text-xl font-bold"><?php echo $totalMeds; ?></span>
                        <span class="text-sm text-yellow-600">Medicines Sold</span>
                    </div>
                    <div class="bg-green-50 text-green-800 p-4 rounded-xl flex flex-col items-center justify-center h-28">
                        <i class="fa-solid fa-user-group text-2xl mb-2 text-green-500"></i>
                        <span class="text-xl font-bold"><?php echo $newPatients; ?></span>
                        <span class="text-sm text-green-600">New Patients</span>
                    </div>
                    <div class="bg-purple-50 text-purple-800 p-4 rounded-xl flex flex-col items-center justify-center h-28">
                        <i class="fa-solid fa-flask text-2xl mb-2 text-purple-500"></i>
                        <span class="text-xl font-bold">50</span>
                        <span class="text-sm text-purple-600">Lab Tests</span>
                    </div>
                </div>
            </div>

            <div class="col-span-9 flex flex-col gap-6">
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 flex-1 relative">
                    <i class="fa-solid fa-expand absolute top-6 right-6 text-blue-400 cursor-pointer text-sm"></i>
                    <div class="flex gap-6 mb-6 border-b border-gray-100">
                        <button class="pb-3 text-sm font-semibold text-blue-500 border-b-2 border-blue-500">NEW APPOINTMENTS</button>
                        <button class="pb-3 text-sm font-medium text-gray-400">COMPLETED APPOINTMENTS</button>
                    </div>
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="text-gray-400 border-b border-gray-50">
                                <th class="pb-3 font-medium">Time <i class="fa-solid fa-sort ml-1"></i></th>
                                <th class="pb-3 font-medium">Date <i class="fa-solid fa-sort ml-1"></i></th>
                                <th class="pb-3 font-medium">Patient Name <i class="fa-solid fa-sort ml-1"></i></th>
                                <th class="pb-3 font-medium">Doctor <i class="fa-solid fa-sort ml-1"></i></th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-600">
                            <?php foreach ($newAppointmentsList as $appt): ?>
                            <tr class="border-b border-gray-50 hover:bg-gray-50">
                                <td class="py-4"><?php echo date('h:i A', strtotime($appt['appointment_time'])); ?></td>
                                <td class="py-4"><?php echo date('d/m/Y', strtotime($appt['appointment_date'])); ?></td>
                                <td class="py-4 flex items-center gap-3">
                                    <img src="<?php echo $appt['p_avatar'] ? $appt['p_avatar'] : 'https://ui-avatars.com/api/?name='.urlencode($appt['patient_name']); ?>" class="w-8 h-8 rounded-full"> 
                                    <?php echo htmlspecialchars($appt['patient_name']); ?>
                                </td>
                                <td class="py-4">Dr. <?php echo htmlspecialchars($appt['doctor_name']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="grid grid-cols-2 gap-6">
                    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 relative">
                        <i class="fa-solid fa-expand absolute top-6 right-6 text-blue-400 cursor-pointer text-sm"></i>
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="font-semibold text-gray-700">Top Medicines Sold</h3>
                            <button class="text-sm text-gray-400 flex items-center gap-1 mr-6">Weekly <i class="fa-solid fa-caret-down"></i></button>
                        </div>
                        <div class="flex items-center justify-between mt-8">
                            <ul class="text-sm text-gray-500 space-y-3">
                                <li class="flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span> Beta-lactam</li>
                                <li class="flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-full bg-purple-500"></span> Vitatree Lung Detox</li>
                                <li class="flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-full bg-green-500"></span> Isoniazid (INH)</li>
                                <li class="flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-full bg-yellow-400"></span> Others</li>
                            </ul>
                            <div class="w-36 h-36 rounded-full relative flex items-center justify-center mr-8" style="background: conic-gradient(#3b82f6 0% 55%, #a855f7 55% 80%, #22c55e 80% 92%, #facc15 92% 100%);">
                                <div class="w-24 h-24 bg-white rounded-full"></div>
                                <span class="absolute top-4 left-6 text-white text-xs font-bold">55%</span>
                                <span class="absolute bottom-4 right-6 text-white text-xs font-bold">25%</span>
                                <span class="absolute top-10 right-2 text-white text-xs font-bold">12%</span>
                                <span class="absolute bottom-4 left-6 text-white text-xs font-bold">8%</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 relative">
                        <i class="fa-solid fa-expand absolute top-6 right-6 text-blue-400 cursor-pointer text-sm"></i>
                        <h3 class="font-semibold text-gray-700 mb-6">Patient Fee</h3>
                            <div class="flex flex-col gap-4">
                                <?php foreach ($pendingFees as $fee): ?>
                                <div class="flex justify-between items-center">
                                    <div class="flex items-center gap-3">
                                        <img src="<?php echo $fee['avatar_url'] ? $fee['avatar_url'] : 'https://ui-avatars.com/api/?name='.urlencode($fee['patient_name']); ?>" class="w-10 h-10 rounded-full">
                                        <div>
                                            <p class="text-sm font-semibold text-gray-700"><?php echo htmlspecialchars($fee['patient_name']); ?></p>
                                            <p class="text-xs text-red-400">Doctor fee pending</p>
                                        </div>
                                    </div>
                                    <button class="bg-blue-500 text-white px-4 py-1.5 rounded text-sm hover:bg-blue-600">Request Fee</button>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="flex justify-between items-center">
                                <div class="flex items-center gap-3">
                                    <img src="https://randomuser.me/api/portraits/women/65.jpg" class="w-10 h-10 rounded-full">
                                    <div>
                                        <p class="text-sm font-semibold text-gray-700">Đỗ Hương Giang</p>
                                        <p class="text-xs text-red-400">Doctor fee pending</p>
                                    </div>
                                </div>
                                <button class="bg-blue-500 text-white px-4 py-1.5 rounded text-sm hover:bg-blue-600">Request Fee</button>
                            </div>
                            <div class="flex justify-between items-center">
                                <div class="flex items-center gap-3">
                                    <img src="https://randomuser.me/api/portraits/men/32.jpg" class="w-10 h-10 rounded-full">
                                    <div>
                                        <p class="text-sm font-semibold text-gray-700">Lê Hoàng Lan</p>
                                        <p class="text-xs text-red-400">Doctor fee pending</p>
                                    </div>
                                </div>
                                <button class="bg-blue-500 text-white px-4 py-1.5 rounded text-sm hover:bg-blue-600">Request Fee</button>
                            </div>
                            <div class="flex justify-between items-center">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-blue-500 text-white flex items-center justify-center font-bold">KR</div>
                                    <div>
                                        <p class="text-sm font-semibold text-gray-700">Nguyễn Phương Thuý</p>
                                        <p class="text-xs text-red-400">Doctor fee pending</p>
                                    </div>
                                </div>
                                <button class="bg-blue-500 text-white px-4 py-1.5 rounded text-sm hover:bg-blue-600">Request Fee</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>