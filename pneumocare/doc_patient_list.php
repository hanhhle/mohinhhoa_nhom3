<?php
// ==========================================
// TÊN FILE: doc_patient_list.php
// CHỨC NĂNG: Danh sách bệnh nhân của Bác sĩ
// ==========================================
session_start();
require 'db.php';
ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL);

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Doctor') { 
    header("Location: login.php"); exit(); 
}

$doctorId = $_SESSION['user_id'];
$doctorName = $_SESSION['name'];
$doctorAvatar = (!empty($_SESSION['avatar']) && $_SESSION['avatar'] != 'default.png') ? $_SESSION['avatar'] : 'img/default.png';

$patients = [];
try {
    // Lấy danh sách bệnh nhân ĐỘC NHẤT (DISTINCT) đã từng đặt lịch với bác sĩ này
    $stmt = $pdo->prepare("
        SELECT DISTINCT u.user_id, u.full_name, u.avatar_url, pp.date_of_birth, pp.gender, pp.blood_group
        FROM Appointments a
        JOIN Users u ON a.patient_id = u.user_id
        JOIN Patient_Profiles pp ON u.user_id = pp.patient_id
        WHERE a.doctor_id = ?
    ");
    $stmt->execute([$doctorId]);
    $patients = $stmt->fetchAll();
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
    <title>Pneumo-Care | Patient List</title>
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
            <a href="doc_patient_list.php" class="sidebar-active flex items-center gap-4 px-4 py-3 rounded-lg font-medium">
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
                <h2 class="text-2xl font-bold text-[#003366]">Patient List</h2>
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
                
                <div class="p-8 border-b border-gray-100">
                    <h3 class="font-bold text-gray-800 text-lg mb-6">Patient Directory</h3>
                    <div class="relative w-[380px]">
                        <input type="text" id="searchPatient" placeholder="Search patients by name..." class="w-full bg-gray-50 border border-gray-100 focus:bg-white focus:border-blue-400 rounded-xl py-3 px-5 pl-12 text-sm outline-none transition-colors shadow-inner">
                        <i class="fa-solid fa-magnifying-glass absolute left-5 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    </div>
                </div>

                <div class="flex-1 overflow-auto px-8">
                    <table class="w-full text-sm text-left">
                        <thead>
                            <tr class="text-gray-400 uppercase text-xs tracking-wider border-b border-gray-100">
                                <th class="py-4 font-semibold w-[35%]">Patient Name</th>
                                <th class="py-4 font-semibold w-[15%]">Age</th>
                                <th class="py-4 font-semibold w-[15%]">Gender</th>
                                <th class="py-4 font-semibold w-[15%]">Blood Group</th>
                                <th class="py-4 font-semibold w-[20%] text-right">More Information</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-700" id="patientTable">
                            <?php if(empty($patients)): ?>
                                <tr><td colspan="5" class="py-10 text-center text-gray-400 italic">No patients found.</td></tr>
                            <?php else: ?>
                                <?php foreach($patients as $p): ?>
                                <tr class="border-b border-gray-50 hover:bg-blue-50/20 transition-colors patient-row">
                                    <td class="py-4">
                                        <div class="flex items-center gap-4">
                                            <img src="<?php echo $p['avatar_url'] ?: 'img/default.png'; ?>" class="w-10 h-10 rounded-full object-cover shadow-sm border border-gray-100" alt="">
                                            <span class="font-bold text-gray-800"><?php echo htmlspecialchars($p['full_name']); ?></span>
                                        </div>
                                    </td>
                                    <td class="py-4 font-medium text-gray-600"><?php echo calculateAge($p['date_of_birth']); ?></td>
                                    <td class="py-4 font-medium text-gray-600"><?php echo htmlspecialchars($p['gender']); ?></td>
                                    <td class="py-4">
                                        <span class="font-bold text-red-500 bg-red-50 px-2.5 py-1 rounded-md border border-red-100"><?php echo htmlspecialchars($p['blood_group'] ?? 'N/A'); ?></span>
                                    </td>
                                    <td class="py-4 text-right">
                                        <a href="doc_medical_record.php?patient_id=<?php echo $p['user_id']; ?>" class="inline-flex items-center gap-2 text-blue-600 hover:text-blue-800 font-bold transition-colors">
                                            <i class="fa-regular fa-folder-open text-lg"></i> View Medical Report
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <tr id="empty-search" class="hidden"><td colspan="5" class="py-10 text-center text-gray-400 italic">No matching patients found.</td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="px-8 py-6 flex items-center justify-between border-t border-gray-100 mt-auto bg-gray-50/50 rounded-b-2xl">
                    <span class="text-xs font-semibold text-gray-400 uppercase tracking-widest">Showing <?php echo count($patients); ?> Patients</span>
                    <div class="flex items-center gap-1.5 text-sm">
                        <span class="mr-4 cursor-pointer text-gray-400 hover:text-gray-700 font-medium transition-colors">Previous</span>
                        <span class="w-8 h-8 flex items-center justify-center bg-blue-500 text-white rounded-full shadow-sm font-semibold cursor-pointer">1</span>
                        <span class="w-8 h-8 flex items-center justify-center text-gray-600 hover:bg-gray-200 rounded-full font-medium cursor-pointer transition-colors">2</span>
                        <span class="w-8 h-8 flex items-center justify-center text-gray-600 hover:bg-gray-200 rounded-full font-medium cursor-pointer transition-colors">3</span>
                        <span class="ml-4 cursor-pointer text-gray-600 hover:text-gray-900 font-medium transition-colors">Next</span>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Tìm kiếm realtime cực mượt
        document.getElementById('searchPatient').addEventListener('input', function(e) {
            const term = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('.patient-row');
            let visibleCount = 0;
            
            rows.forEach(row => {
                const name = row.querySelector('td:first-child span').textContent.toLowerCase();
                if (name.includes(term)) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            // Hiện dòng thông báo nếu tìm không ra
            const emptyMsg = document.getElementById('empty-search');
            if (emptyMsg) {
                emptyMsg.style.display = visibleCount === 0 ? '' : 'none';
            }
        });
    </script>
</body>
</html>