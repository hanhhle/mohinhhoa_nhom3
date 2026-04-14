<?php
// ==========================================
// TÊN FILE: doc_appointments.php
// CHỨC NĂNG: Danh sách Lịch hẹn (New & Completed) của Doctor
// ==========================================
session_start();
require 'db.php';
ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL);

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Doctor') { 
    header("Location: login.php"); 
    exit(); 
}

$doctorId = $_SESSION['user_id'];
$doctorName = $_SESSION['name'];
$doctorAvatar = (!empty($_SESSION['avatar']) && $_SESSION['avatar'] != 'default.png') ? $_SESSION['avatar'] : 'img/default.png';

$appointments = [];
$completed = [];

try {
    // Lấy lịch hẹn mới (Scheduled)
    $stmtNew = $pdo->prepare("
        SELECT a.*, u_p.full_name as patient_name, u_p.avatar_url as p_avatar, pp.date_of_birth
        FROM Appointments a
        JOIN Users u_p ON a.patient_id = u_p.user_id
        JOIN Patient_Profiles pp ON u_p.user_id = pp.patient_id
        WHERE a.doctor_id = ? AND a.status = 'Scheduled'
        ORDER BY a.appointment_date ASC, a.appointment_time ASC
    ");
    $stmtNew->execute([$doctorId]);
    $appointments = $stmtNew->fetchAll();

    // Lấy lịch hẹn đã hoàn thành (Completed)
    $stmtDone = $pdo->prepare("
        SELECT a.*, u_p.full_name as patient_name, u_p.avatar_url as p_avatar, pp.date_of_birth
        FROM Appointments a
        JOIN Users u_p ON a.patient_id = u_p.user_id
        JOIN Patient_Profiles pp ON u_p.user_id = pp.patient_id
        WHERE a.doctor_id = ? AND a.status = 'Completed'
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
    ");
    $stmtDone->execute([$doctorId]);
    $completed = $stmtDone->fetchAll();

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
    <title>Pneumo-Care | Doctor Appointments</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
        .nav-active { background-color: #eff6ff; color: #3b82f5; border-left: 4px solid #3b82f5; }
        .tab-active { border-bottom: 3px solid #3b82f5; color: #1e40af; font-weight: 600; }
        .hide { display: none !important; }
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
            <a href="doc_appointments.php" class="sidebar-active flex items-center gap-4 px-4 py-3 rounded-lg font-medium">
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
            <a href="logout.php" class="flex items-center gap-3 text-gray-500 hover:text-red-500 transition-colors font-medium">
                <i class="fa-solid fa-right-from-bracket"></i>
                <span>Logout</span>
            </a>
        </div>
    </aside>

    <div class="flex-1 flex flex-col h-screen overflow-hidden">
        
        <header class="bg-white border-b px-8 py-4 flex items-center justify-between">
            <h2 class="text-2xl font-semibold text-gray-700">Appointments</h2>
            
            <div class="flex items-center gap-6">
                <div class="relative cursor-pointer">
                    <i class="fa-solid fa-bell text-xl text-gray-400"></i>
                    <div class="absolute -top-1 -right-1 w-2.5 h-2.5 bg-red-500 rounded-full border-2 border-white"></div>
                </div>
                
                <div class="flex items-center gap-3">
                    <img src="<?php echo $doctorAvatar; ?>" class="w-10 h-10 rounded-full object-cover border-2 border-blue-100" alt="Doctor Avatar">
                    <div>
                        <p class="text-sm font-semibold text-gray-700"><?php echo htmlspecialchars($doctorName); ?></p>
                        <p class="text-xs text-gray-500">Doctor</p>
                    </div>
                </div>
            </div>
        </header>

        <div class="flex-1 overflow-auto p-8">
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 h-full flex flex-col">

                <div class="flex border-b border-gray-100 px-8 pt-6">
                    <button onclick="switchTab(0)" id="tab-new" class="tab-active px-8 py-4 text-lg transition-all">NEW APPOINTMENTS</button>
                    <button onclick="switchTab(1)" id="tab-completed" class="px-8 py-4 text-lg text-gray-500 hover:text-gray-700 transition-all">COMPLETED APPOINTMENTS</button>
                    
                    <div class="ml-auto flex items-center gap-4">
                        <button class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2.5 rounded-2xl flex items-center gap-2 font-medium transition-colors">
                            <i class="fa-solid fa-plus"></i> New Appointment
                        </button>
                        <button class="text-gray-400 hover:text-gray-600"><i class="fa-solid fa-arrow-up-right-from-square text-xl"></i></button>
                    </div>
                </div>

                <div class="flex items-center gap-4 px-8 py-6 border-b">
                    <div class="flex-1 relative">
                        <input type="text" id="searchInput" class="w-full bg-gray-50 border border-gray-200 focus:border-blue-400 rounded-2xl py-3 px-5 pl-12 text-sm focus:outline-none" placeholder="Search patient name...">
                        <i class="fa-solid fa-magnifying-glass absolute left-5 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    </div>
                    <button class="flex items-center gap-2 border border-gray-200 hover:border-gray-300 px-5 py-3 rounded-2xl text-sm font-medium transition-colors">
                        <i class="fa-solid fa-calendar"></i> Filter by Date
                    </button>
                </div>

                <div class="flex-1 overflow-auto px-8">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 text-gray-400">
                                <th class="py-5 text-left font-medium">Time <i class="fa-solid fa-sort ml-1"></i></th>
                                <th class="py-5 text-left font-medium">Date <i class="fa-solid fa-sort ml-1"></i></th>
                                <th class="py-5 text-left font-medium">Patient Name <i class="fa-solid fa-sort ml-1"></i></th>
                                <th class="py-5 text-center font-medium">Age <i class="fa-solid fa-sort ml-1"></i></th>
                                <th class="py-5 text-left font-medium">Status <i class="fa-solid fa-sort ml-1"></i></th>
                                <th class="py-5 text-left font-medium">Action</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-700" id="appointmentTable">
                            <?php foreach($appointments as $appt): ?>
                                <tr class="row-new border-b border-gray-100 hover:bg-blue-50/30 transition-colors">
                                    <td class="py-6 font-semibold text-blue-600"><?php echo date('h:i A', strtotime($appt['appointment_time'])); ?></td>
                                    <td class="py-6"><?php echo date('d/m/Y', strtotime($appt['appointment_date'])); ?></td>
                                    <td class="py-6">
                                        <div class="flex items-center gap-3">
                                            <img src="<?php echo $appt['p_avatar'] ?: 'img/default.png'; ?>" class="w-9 h-9 rounded-2xl object-cover shadow-sm">
                                            <span class="font-medium text-gray-800"><?php echo htmlspecialchars($appt['patient_name']); ?></span>
                                        </div>
                                    </td>
                                    <td class="py-6 text-center"><?php echo calculateAge($appt['date_of_birth']); ?></td>
                                    <td class="py-6"><span class="px-3 py-1 bg-yellow-50 text-yellow-600 rounded-full text-xs font-bold uppercase">Scheduled</span></td>
                                    <td class="py-6">
                                        <div class="flex gap-4 text-blue-600 font-medium text-sm">
                                            <a href="#" class="hover:underline">AI Diagnosis</a>
                                            <a href="#" class="hover:underline">Complete</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <?php foreach($completed as $row): ?>
                                <tr class="row-completed hide border-b border-gray-100 hover:bg-gray-50 transition-colors">
                                    <td class="py-6 font-medium text-gray-500"><?php echo date('h:i A', strtotime($row['appointment_time'])); ?></td>
                                    <td class="py-6 text-gray-500"><?php echo date('d/m/Y', strtotime($row['appointment_date'])); ?></td>
                                    <td class="py-6">
                                        <div class="flex items-center gap-3">
                                            <img src="<?php echo $row['p_avatar'] ?: 'img/default.png'; ?>" class="w-9 h-9 rounded-2xl object-cover grayscale opacity-70">
                                            <span class="font-medium text-gray-600"><?php echo htmlspecialchars($row['patient_name']); ?></span>
                                        </div>
                                    </td>
                                    <td class="py-6 text-center text-gray-500"><?php echo calculateAge($row['date_of_birth']); ?></td>
                                    <td class="py-6"><span class="px-3 py-1 bg-green-50 text-green-600 rounded-full text-xs font-bold uppercase">Done</span></td>
                                    <td class="py-6">
                                        <a href="#" class="text-gray-400 hover:text-blue-500 transition-colors"><i class="fa-solid fa-file-medical text-lg"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <?php if(empty($appointments) && empty($completed)): ?>
                                <tr id="empty-msg"><td colspan="6" class="py-10 text-center text-gray-400 italic">No appointments found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="border-t px-8 py-5 flex items-center justify-end gap-2 text-sm bg-white rounded-b-3xl">
                    <button class="px-4 py-2 text-gray-500 hover:text-gray-700 transition-colors">Previous</button>
                    <button class="bg-blue-500 text-white px-4 py-2 rounded-xl shadow-sm">1</button>
                    <button class="px-4 py-2 text-gray-500 hover:bg-gray-50 rounded-xl transition-colors">2</button>
                    <button class="px-4 py-2 text-gray-500 hover:bg-gray-50 rounded-xl transition-colors">3</button>
                    <button class="px-4 py-2 text-gray-500 hover:text-gray-700 transition-colors">Next</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function switchTab(tab) {
            const newTab = document.getElementById('tab-new');
            const completedTab = document.getElementById('tab-completed');
            const newRows = document.querySelectorAll('.row-new');
            const completedRows = document.querySelectorAll('.row-completed');
            
            if (tab === 0) {
                newTab.classList.add('tab-active');
                newTab.classList.remove('text-gray-500', 'hover:text-gray-700');
                completedTab.classList.remove('tab-active');
                completedTab.classList.add('text-gray-500', 'hover:text-gray-700');
                
                newRows.forEach(row => row.classList.remove('hide'));
                completedRows.forEach(row => row.classList.add('hide'));
            } else {
                completedTab.classList.add('tab-active');
                completedTab.classList.remove('text-gray-500', 'hover:text-gray-700');
                newTab.classList.remove('tab-active');
                newTab.classList.add('text-gray-500', 'hover:text-gray-700');
                
                completedRows.forEach(row => row.classList.remove('hide'));
                newRows.forEach(row => row.classList.add('hide'));
            }
        }

        // Tìm kiếm realtime cực mượt
        document.getElementById('searchInput').addEventListener('input', function(e) {
            const term = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('#appointmentTable tr:not(#empty-msg)');
            
            rows.forEach(row => {
                // Chỉ tìm trong cột tên bệnh nhân (cột số 3)
                const patientName = row.querySelector('td:nth-child(3)').textContent.toLowerCase();
                if (patientName.includes(term)) {
                    // Nếu tab nào đang mở thì mới hiện
                    const isNewTab = document.getElementById('tab-new').classList.contains('tab-active');
                    if (isNewTab && row.classList.contains('row-new')) row.style.display = '';
                    else if (!isNewTab && row.classList.contains('row-completed')) row.style.display = '';
                    else row.style.display = 'none';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>