<?php
ob_start(); // BẮT BUỘC CÓ ĐỂ TỰ ĐỘNG CHUYỂN TRANG
// ==========================================
// TÊN FILE: doc_appointments.php
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

// 1. XỬ LÝ CHUYỂN ĐỔI TRẠNG THÁI LỊCH KHÁM (FLOW)
if (isset($_GET['action']) && isset($_GET['appt_id'])) {
    $action = $_GET['action'];
    $apptId = $_GET['appt_id'];
    
    try {
        if ($action === 'start') {
            $stmtCheck = $pdo->prepare("SELECT fee_status FROM Appointments WHERE appointment_id = ?");
            $stmtCheck->execute([$apptId]);
            $currentFeeStatus = trim($stmtCheck->fetchColumn());

            if ($currentFeeStatus === 'Paid') {
                $stmt = $pdo->prepare("UPDATE Appointments SET status = 'In Progress' WHERE appointment_id = ? AND doctor_id = ?");
                $stmt->execute([$apptId, $doctorId]);
                
                // TỰ ĐỘNG NHẢY SANG TRANG AI WORKSPACE NGAY LẬP TỨC
                header("Location: doc_ai_workspace.php?appt_id=" . $apptId);
                exit();
            } else {
                header("Location: doc_appointments.php");
                exit();
            }
            
        } elseif ($action === 'complete') {
            $stmt = $pdo->prepare("UPDATE Appointments SET status = 'Completed' WHERE appointment_id = ? AND doctor_id = ?");
            $stmt->execute([$apptId, $doctorId]);
            header("Location: doc_appointments.php");
            exit();
        }
    } catch (PDOException $e) {
        die("Lỗi Database: " . $e->getMessage());
    }
}

$appointments = [];
$completed = [];

try {
    // 2. LẤY LỊCH HẸN MỚI
    $stmtNew = $pdo->prepare("
        SELECT a.*, u_p.full_name as patient_name, u_p.avatar_url as p_avatar, pp.date_of_birth
        FROM Appointments a
        JOIN Users u_p ON a.patient_id = u_p.user_id
        JOIN Patient_Profiles pp ON u_p.user_id = pp.patient_id
        WHERE a.doctor_id = ? AND a.status IN ('Scheduled', 'In Progress')
        ORDER BY a.appointment_date ASC, a.appointment_time ASC
    ");
    $stmtNew->execute([$doctorId]);
    $appointments = $stmtNew->fetchAll();

    // 3. LẤY LỊCH HẸN ĐÃ HOÀN THÀNH
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; color: #1f2937; }
        .sidebar-active { background-color: #eff6ff; color: #2563eb; border-left: 4px solid #2563eb; }
        .tab-active { border-bottom: 3px solid #2563eb; color: #1e3a8a; font-weight: 700; }
        .hide { display: none !important; }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        input[type="date"]::-webkit-calendar-picker-indicator { cursor: pointer; opacity: 0.6; transition: 0.2s; }
        input[type="date"]::-webkit-calendar-picker-indicator:hover { opacity: 1; }
    </style>
</head>
<body class="flex h-screen overflow-hidden">

    <aside class="w-64 bg-white border-r border-gray-100 flex flex-col h-full shadow-sm">
        <div class="flex items-center gap-2 p-6 border-b">
            <i class="fa-solid fa-lungs text-3xl text-red-400"></i>
            <h1 class="text-xl font-semibold text-gray-700">Pneumo-<span class="text-blue-500">Care</span></h1>
        </div>

        <nav class="flex-1 px-4 py-6 space-y-1">
            <a href="doc_dashboard.php" class="flex items-center gap-4 px-4 py-3 text-gray-500 hover:bg-gray-50 rounded-lg transition-colors"><i class="fa-solid fa-gauge-high w-5"></i><span>Dashboard</span></a>
            <a href="doc_patient_list.php" class="flex items-center gap-4 px-4 py-3 text-gray-500 hover:bg-gray-50 rounded-lg transition-colors"><i class="fa-solid fa-users w-5"></i><span>Patient</span></a>
            <a href="doc_appointments.php" class="sidebar-active flex items-center gap-4 px-4 py-3 rounded-lg font-medium"><i class="fa-solid fa-calendar-check w-5"></i><span>Appointments</span></a>
            <a href="doc_ai_workspace.php" class="flex items-center gap-4 px-4 py-3 text-gray-500 hover:bg-gray-50 rounded-lg transition-colors"><i class="fa-solid fa-brain w-5"></i><span>Diagnosis</span></a>
            <a href="doc_messages.php" class="flex items-center gap-4 px-4 py-3 text-gray-500 hover:bg-gray-50 rounded-lg transition-colors"><i class="fa-solid fa-comment-dots w-5"></i><span>Messages</span></a>
        </nav>

        <div class="p-6 border-t mt-auto">
            <a href="logout.php" class="flex items-center gap-4 text-gray-500 hover:text-red-500 transition-colors font-medium"><i class="fa-solid fa-right-from-bracket"></i><span>Logout</span></a>
        </div>
    </aside>

    <main class="flex-1 flex flex-col overflow-hidden">
        <div class="px-10 pt-8 pb-6 flex-shrink-0">
            <header class="h-[72px] bg-white border border-gray-100 rounded-2xl shadow-sm flex items-center justify-between px-6">
                <h2 class="text-2xl font-bold text-[#003366]">Appointments</h2>
                <div class="flex items-center gap-6">
                    <div class="flex items-center gap-3">
                        <div class="text-right hidden sm:block"><p class="text-sm font-semibold text-gray-800" style="line-height: 1.2;"><?php echo htmlspecialchars($doctorName); ?></p><p class="text-xs text-gray-500 font-medium">Doctor</p></div>
                        <img src="<?php echo $doctorAvatar; ?>" class="w-10 h-10 rounded-full border border-gray-200 shadow-sm object-cover">
                    </div>
                </div>
            </header>
        </div>

        <div class="flex-1 px-10 pb-10 overflow-y-auto">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 flex flex-col min-h-full">

                <div class="flex border-b border-gray-100 px-8 pt-6 items-center justify-between">
                    <div class="flex gap-4">
                        <button onclick="switchTab(0)" id="tab-new" class="tab-active px-4 pb-4 text-sm uppercase tracking-wide transition-all">NEW APPOINTMENTS</button>
                        <button onclick="switchTab(1)" id="tab-completed" class="px-4 pb-4 text-sm font-semibold text-gray-400 hover:text-gray-600 uppercase tracking-wide transition-all">COMPLETED APPOINTMENTS</button>
                    </div>
                </div>

                <div class="flex items-center justify-between px-8 py-6">
                    <div class="relative w-[380px]">
                        <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                        <input type="text" id="searchInput" class="w-full bg-gray-50 border border-gray-100 focus:bg-white focus:border-blue-400 rounded-xl py-2.5 px-5 pl-11 text-sm outline-none transition-colors" placeholder="Search patient name...">
                    </div>
                    <div class="relative flex items-center">
                        <i class="fa-regular fa-calendar absolute left-4 text-gray-500 pointer-events-none"></i>
                        <input type="date" id="dateFilter" class="bg-white border border-gray-200 text-gray-700 pl-10 pr-4 py-2.5 rounded-xl text-sm font-semibold hover:bg-gray-50 focus:outline-none focus:border-blue-400 transition-colors cursor-pointer">
                    </div>
                </div>

                <div class="flex-1 overflow-auto px-8">
                    <table class="w-full text-sm text-left">
                        <thead>
                            <tr class="border-b border-gray-100 text-gray-400 text-xs uppercase tracking-wider">
                                <th class="py-4 font-semibold w-[12%]">Time</th>
                                <th class="py-4 font-semibold w-[15%]">Date</th>
                                <th class="py-4 font-semibold w-[30%]">Patient Name</th>
                                <th class="py-4 font-semibold w-[10%]">Age</th>
                                <th class="py-4 font-semibold w-[10%]">Status</th>
                                <th class="py-4 font-semibold w-[23%] text-right pr-4">Action</th>
                            </tr>
                        </thead>
                        <tbody id="appointmentTable">
                            
                            <?php foreach($appointments as $appt): ?>
                                <tr class="row-new border-b border-gray-50 hover:bg-blue-50/20 transition-colors" data-date="<?php echo $appt['appointment_date']; ?>">
                                    <td class="py-5 font-bold text-blue-600"><?php echo date('h:i A', strtotime($appt['appointment_time'])); ?></td>
                                    <td class="py-5 font-medium text-gray-600"><?php echo date('d/m/Y', strtotime($appt['appointment_date'])); ?></td>
                                    <td class="py-5">
                                        <div class="flex items-center gap-3">
                                            <img src="<?php echo $appt['p_avatar'] ?: 'img/default.png'; ?>" class="w-8 h-8 rounded-full object-cover shadow-sm border border-gray-100">
                                            <span class="font-semibold text-gray-800"><?php echo htmlspecialchars($appt['patient_name']); ?></span>
                                        </div>
                                    </td>
                                    <td class="py-5 text-gray-600 font-medium"><?php echo calculateAge($appt['date_of_birth']); ?></td>
                                    <td class="py-5">
                                        <?php if ($appt['status'] == 'Scheduled'): ?>
                                            <span class="px-3 py-1 bg-yellow-50 text-yellow-600 rounded-md text-[10px] font-extrabold uppercase tracking-widest border border-yellow-100">Scheduled</span>
                                        <?php elseif ($appt['status'] == 'In Progress'): ?>
                                            <span class="px-3 py-1 bg-blue-50 text-blue-600 rounded-md text-[10px] font-extrabold uppercase tracking-widest border border-blue-100">In Progress</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-5 text-right pr-4">
                                        <div class="flex items-center justify-end gap-4 text-sm font-bold">
                                            <a href="doc_medical_record.php?patient_id=<?php echo $appt['patient_id']; ?>" title="View Medical Record" class="text-gray-400 hover:text-blue-600 transition-colors">
                                                <i class="fa-regular fa-file-lines text-lg"></i>
                                            </a>

                                            <?php if ($appt['status'] == 'Scheduled'): ?>
                                                <?php if ($appt['fee_status'] === 'Paid'): ?>
                                                    <a href="?action=start&appt_id=<?php echo $appt['appointment_id']; ?>" class="text-blue-600 hover:text-blue-800 transition-colors flex items-center gap-1.5 bg-blue-50 px-3 py-1.5 rounded-lg border border-blue-100">
                                                        <i class="fa-solid fa-play text-xs"></i> Start Exam
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-red-500 font-bold text-[10px] uppercase tracking-widest bg-red-50 px-3 py-1.5 rounded-lg border border-red-100 flex items-center gap-1.5 cursor-not-allowed">
                                                        <i class="fa-solid fa-triangle-exclamation text-xs"></i> Pending Payment
                                                    </span>
                                                <?php endif; ?>
                                                
                                            <?php elseif ($appt['status'] == 'In Progress'): ?>
                                                <a href="doc_ai_workspace.php?appt_id=<?php echo $appt['appointment_id']; ?>" class="text-blue-600 hover:underline transition-colors flex items-center gap-1.5 bg-blue-50 px-3 py-1.5 rounded-lg border border-blue-100">
                                                    <i class="fa-solid fa-brain"></i> AI Diagnosis
                                                </a>
                                                <a href="?action=complete&appt_id=<?php echo $appt['appointment_id']; ?>" class="text-emerald-600 hover:text-white hover:bg-emerald-600 transition-colors bg-emerald-50 px-3 py-1.5 rounded-lg border border-emerald-200" onclick="return confirm('Xác nhận hoàn thành cuộc khám?')">
                                                    Complete
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <?php foreach($completed as $row): ?>
                                <tr class="row-completed hide border-b border-gray-50 hover:bg-gray-50 transition-colors" data-date="<?php echo $row['appointment_date']; ?>">
                                    <td class="py-5 font-medium text-gray-500"><?php echo date('h:i A', strtotime($row['appointment_time'])); ?></td>
                                    <td class="py-5 font-medium text-gray-500"><?php echo date('d/m/Y', strtotime($row['appointment_date'])); ?></td>
                                    <td class="py-5">
                                        <div class="flex items-center gap-3 opacity-80">
                                            <img src="<?php echo $row['p_avatar'] ?: 'img/default.png'; ?>" class="w-8 h-8 rounded-full object-cover shadow-sm border border-gray-100 grayscale">
                                            <span class="font-semibold text-gray-700"><?php echo htmlspecialchars($row['patient_name']); ?></span>
                                        </div>
                                    </td>
                                    <td class="py-5 text-gray-500 font-medium"><?php echo calculateAge($row['date_of_birth']); ?></td>
                                    <td class="py-5">
                                        <span class="px-3 py-1 bg-gray-100 text-gray-500 rounded-md text-[10px] font-extrabold uppercase tracking-widest border border-gray-200">Done</span>
                                    </td>
                                    <td class="py-5 text-right pr-4">
                                        <div class="flex items-center justify-end">
                                            <a href="doc_medical_record.php?patient_id=<?php echo $row['patient_id']; ?>" class="text-blue-600 hover:text-blue-800 font-bold text-sm transition-colors flex items-center gap-1.5">
                                                <i class="fa-regular fa-file-lines"></i> View Record
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <tr id="empty-msg" class="hide"><td colspan="6" class="py-10 text-center text-gray-400 italic">No appointments match your filter.</td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="px-8 py-5 flex items-center justify-end gap-1.5 text-sm border-t border-gray-100 mt-auto">
                    <span class="mr-4 cursor-pointer text-gray-400 hover:text-gray-700 font-medium transition-colors">Previous</span>
                    <span class="w-8 h-8 flex items-center justify-center bg-blue-500 text-white rounded-full shadow-sm font-semibold cursor-pointer">1</span>
                    <span class="w-8 h-8 flex items-center justify-center text-gray-600 hover:bg-gray-100 rounded-full font-medium cursor-pointer transition-colors">2</span>
                    <span class="ml-4 cursor-pointer text-gray-600 hover:text-gray-900 font-medium transition-colors">Next</span>
                </div>
            </div>
        </div>
    </main>

    <script>
        let currentTab = 0;
        function switchTab(tab) {
            currentTab = tab;
            const newTab = document.getElementById('tab-new');
            const completedTab = document.getElementById('tab-completed');
            if (tab === 0) {
                newTab.className = 'tab-active px-4 pb-4 text-sm uppercase tracking-wide transition-all';
                completedTab.className = 'px-4 pb-4 text-sm font-semibold text-gray-400 hover:text-gray-600 uppercase tracking-wide transition-all';
            } else {
                completedTab.className = 'tab-active px-4 pb-4 text-sm uppercase tracking-wide transition-all';
                newTab.className = 'px-4 pb-4 text-sm font-semibold text-gray-400 hover:text-gray-600 uppercase tracking-wide transition-all';
            }
            runFilters();
        }

        document.getElementById('searchInput').addEventListener('input', runFilters);
        document.getElementById('dateFilter').addEventListener('change', runFilters);

        function runFilters() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const dateTerm = document.getElementById('dateFilter').value;
            const newRows = document.querySelectorAll('.row-new');
            const completedRows = document.querySelectorAll('.row-completed');
            let visibleCount = 0;

            function checkRow(row) {
                const name = row.querySelector('td:nth-child(3)').textContent.toLowerCase();
                const rowDate = row.getAttribute('data-date');
                let matchesName = name.includes(searchTerm);
                let matchesDate = (dateTerm === "") ? true : (rowDate === dateTerm);
                if (matchesName && matchesDate) { row.classList.remove('hide'); visibleCount++; } 
                else { row.classList.add('hide'); }
            }

            if (currentTab === 0) {
                newRows.forEach(checkRow);
                completedRows.forEach(row => row.classList.add('hide'));
            } else {
                completedRows.forEach(checkRow);
                newRows.forEach(row => row.classList.add('hide'));
            }

            const emptyMsg = document.getElementById('empty-msg');
            if (visibleCount === 0) emptyMsg.classList.remove('hide');
            else emptyMsg.classList.add('hide');
        }
        switchTab(0);
    </script>
</body>
</html>