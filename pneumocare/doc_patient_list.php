<?php
// ==========================================
// TÊN FILE: doc_patient_list.php
// CHỨC NĂNG: Danh sách bệnh nhân của Bác sĩ (Để chạy AI)
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
    <title>Pneumo-Care | Patient List - AI Diagnosis</title>
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
                    <span>AI Diagnosis</span>
                </a>
                <a href="#" class="flex items-center gap-4 px-4 py-3 text-gray-500 hover:bg-gray-50 rounded-lg transition-colors">
                    <i class="fa-solid fa-comment-dots w-5"></i>
                    <span>Messages</span>
                </a>
            </nav>
        <div class="p-6 border-t mt-auto">
            <a href="logout.php" class="flex items-center gap-4 text-gray-500 hover:text-red-500 font-medium transition-colors">
                <i class="fa-solid fa-right-from-bracket"></i><span>Logout</span>
            </a>
        </div>
    </aside>

    <main class="flex-1 overflow-y-auto bg-gray-50">
        <div class="p-8">
            <header class="flex justify-between items-center mb-8">
                <h2 class="text-2xl font-semibold text-gray-700">Patient List</h2>
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

            <div class="bg-white rounded-2xl shadow-sm border border-gray-100">
                <div class="p-6 border-b">
                    <h3 class="font-semibold text-lg">Patient List</h3>
                </div>

                <div class="p-6 border-b">
                    <div class="relative max-w-md">
                        <input type="text" id="searchPatient" placeholder="Search patients..." class="w-full bg-gray-50 border border-gray-200 rounded-xl py-3 pl-12 pr-4 focus:outline-none focus:border-blue-500">
                        <i class="fa-solid fa-magnifying-glass absolute left-4 top-3.5 text-gray-400"></i>
                    </div>
                </div>

                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr class="text-gray-500 border-b">
                            <th class="py-5 px-6 text-left font-medium">Patient Name</th>
                            <th class="py-5 px-6 text-left font-medium">Age</th>
                            <th class="py-5 px-6 text-left font-medium">Gender</th>
                            <th class="py-5 px-6 text-left font-medium">Blood Group</th>
                            <th class="py-5 px-6 text-left font-medium">More Information</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y text-gray-700" id="patientTable">
                        <?php if(empty($patients)): ?>
                            <tr><td colspan="5" class="py-10 text-center text-gray-400 italic">No patients found.</td></tr>
                        <?php else: ?>
                            <?php foreach($patients as $p): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 flex items-center gap-3">
                                    <img src="<?php echo $p['avatar_url'] ?: 'img/default.png'; ?>" class="w-9 h-9 rounded-full object-cover" alt="">
                                    <span class="font-medium"><?php echo htmlspecialchars($p['full_name']); ?></span>
                                </td>
                                <td class="px-6 py-4"><?php echo calculateAge($p['date_of_birth']); ?></td>
                                <td class="px-6 py-4"><?php echo $p['gender']; ?></td>
                                <td class="px-6 py-4 font-semibold"><?php echo $p['blood_group']; ?></td>
                                <td class="px-6 py-4">
                                    <a href="doc_ai_workspace.php?patient_id=<?php echo $p['user_id']; ?>" class="text-blue-600 hover:underline mr-6">View AI Diagnosis</a>
                                    <a href="doc_medical_record.php?patient_id=<?php echo $p['user_id']; ?>" class="text-blue-600 hover:underline">View Medical Report</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        // Tìm kiếm realtime
        document.getElementById('searchPatient').addEventListener('input', function(e) {
            const term = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('#patientTable tr');
            rows.forEach(row => {
                const name = row.querySelector('td:first-child')?.textContent.toLowerCase() || '';
                row.style.display = name.includes(term) ? '' : 'none';
            });
        });
    </script>
</body>
</html>