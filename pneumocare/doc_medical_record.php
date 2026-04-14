<?php
// ==========================================
// TÊN FILE: doc_medical_record.php
// CHỨC NĂNG: Xem hồ sơ và Bệnh án chi tiết
// ==========================================
session_start();
require 'db.php';
ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL);

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Doctor') { header("Location: login.php"); exit(); }
$doctorName = $_SESSION['name'];
$doctorAvatar = (!empty($_SESSION['avatar']) && $_SESSION['avatar'] != 'default.png') ? $_SESSION['avatar'] : 'img/default.png';

// Lấy thông tin chi tiết bệnh nhân
$patientInfo = null;
if (isset($_GET['patient_id'])) {
    $stmt = $pdo->prepare("SELECT u.full_name, u.avatar_url, pp.* FROM Users u JOIN Patient_Profiles pp ON u.user_id = pp.patient_id WHERE u.user_id = ?");
    $stmt->execute([$_GET['patient_id']]);
    $patientInfo = $stmt->fetch();
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
    <title>Pneumo-Care | Detailed Medical Record</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
        .nav-active { background-color: #eff6ff; color: #3b82f5; border-left: 4px solid #3b82f5; }
    </style>
</head>
<body class="flex h-screen overflow-hidden text-gray-800">
    <aside class="w-64 bg-white border-r border-gray-100 flex flex-col h-full shadow-sm">
        <div class="flex items-center gap-2 p-6 border-b"><i class="fa-solid fa-lungs text-3xl text-red-400"></i><h1 class="text-xl font-semibold text-gray-700">Pneumo-<span class="text-blue-500">Care</span></h1></div>
            <nav class="flex-1 px-4 py-6 space-y-1">
                <a href="doc_dashboard.php" class="flex items-center gap-4 px-4 py-3 text-gray-500 hover:bg-gray-50 rounded-lg transition-colors">
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
                <a href="doc_ai_workspace.php" class="sidebar-active flex items-center gap-4 px-4 py-3 rounded-lg font-medium">
                    <i class="fa-solid fa-brain w-5"></i>
                    <span>AI Diagnosis</span>
                </a>
                <a href="#" class="flex items-center gap-4 px-4 py-3 text-gray-500 hover:bg-gray-50 rounded-lg transition-colors">
                    <i class="fa-solid fa-comment-dots w-5"></i>
                    <span>Messages</span>
                </a>
            </nav>
        <div class="p-6 border-t"><a href="logout.php" class="flex items-center gap-3 text-gray-500 hover:text-red-500 font-medium"><i class="fa-solid fa-right-from-bracket"></i><span>Logout</span></a></div>
    </aside>

    <div class="flex-1 flex flex-col h-screen overflow-hidden">
        <header class="bg-white border-b px-8 py-4 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <a href="doc_patient_list.php" class="text-gray-400 hover:text-blue-500"><i class="fa-solid fa-arrow-left"></i></a>
                <h2 class="text-2xl font-semibold text-gray-700">Detailed Medical Record</h2>
            </div>
            <div class="flex items-center gap-6">
                <div class="relative cursor-pointer"><i class="fa-solid fa-bell text-xl text-gray-400"></i></div>
                <div class="flex items-center gap-3">
                    <img src="<?php echo $doctorAvatar; ?>" class="w-10 h-10 rounded-full object-cover border-2 border-blue-100" alt="">
                    <div><p class="text-sm font-semibold text-gray-700"><?php echo htmlspecialchars($doctorName); ?></p><p class="text-xs text-gray-500">Doctor</p></div>
                </div>
            </div>
        </header>

        <div class="flex-1 overflow-auto p-8">
            <?php if(!$patientInfo): ?>
                <div class="bg-white rounded-2xl p-10 text-center text-gray-500 italic">Please select a patient from the Patient List to view details.</div>
            <?php else: ?>
            <div class="grid grid-cols-12 gap-6">
                <div class="col-span-7 space-y-6">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <h3 class="text-lg font-semibold text-gray-700 mb-4 border-b pb-3">Patient Information</h3>
                        <div class="flex gap-6">
                            <div class="flex-shrink-0">
                                <img src="<?php echo $patientInfo['avatar_url'] ?: 'img/default.png'; ?>" class="w-28 h-28 rounded-2xl object-cover border border-gray-200" alt="">
                            </div>
                            <div class="flex-1 grid grid-cols-2 gap-y-4 text-sm">
                                <div><p class="text-gray-500">Name</p><p class="font-semibold text-gray-800"><?php echo htmlspecialchars($patientInfo['full_name']); ?></p></div>
                                <div><p class="text-gray-500">Date of birth</p><p class="font-semibold text-gray-800"><?php echo date('d/m/Y', strtotime($patientInfo['date_of_birth'])); ?></p></div>
                                <div><p class="text-gray-500">Gender</p><p class="font-semibold text-gray-800"><?php echo $patientInfo['gender']; ?></p></div>
                                <div><p class="text-gray-500">Address</p><p class="font-semibold text-gray-800">Ha Dong, Ha Noi (Demo)</p></div>
                                <div><p class="text-gray-500">Phone Number</p><p class="font-semibold text-gray-800"><?php echo $patientInfo['phone_number']; ?></p></div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <h3 class="text-lg font-semibold text-gray-700 mb-4 border-b pb-3">Medical History</h3>
                        <div class="mb-6">
                            <p class="font-medium text-gray-700 mb-2">Previous Medical History</p>
                            <ul class="list-disc list-inside space-y-1 text-gray-600">
                                <li>+ Asthma</li>
                                <li>+ Hypertension</li>
                            </ul>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <h3 class="text-lg font-semibold text-gray-700 mb-4">Appointment History</h3>
                        <table class="w-full text-sm">
                            <thead><tr class="text-gray-400 border-b"><th class="py-3 text-left font-medium">Time</th><th class="py-3 text-left font-medium">Date</th><th class="py-3 text-left font-medium">Doctor</th><th class="py-3 text-left font-medium">Note</th></tr></thead>
                            <tbody class="text-gray-600">
                                <tr class="border-b hover:bg-gray-50"><td class="py-4">09:00</td><td class="py-4">12/07/2026</td><td class="py-4">Dr. <?php echo htmlspecialchars($doctorName); ?></td><td class="py-4 text-gray-500">Pneumonia suspected...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="col-span-5 space-y-6">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <h3 class="text-lg font-semibold text-gray-700 mb-4 border-b pb-3">Current Diagnosis & Notes</h3>
                        <div class="mb-6"><p class="font-medium text-gray-700 mb-1">Diagnose</p><p class="text-gray-800">Local pneumonia, right lower lobe (confirmed by X-ray and AI)</p></div>
                        <div><p class="font-medium text-gray-700 mb-1">Clinical Notes</p><textarea class="w-full bg-gray-50 border border-gray-100 rounded-xl p-4 text-sm text-gray-600 min-h-[80px] focus:outline-none focus:border-blue-300"></textarea></div>
                    </div>

                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <h3 class="text-lg font-semibold text-gray-700 mb-4 border-b pb-3">Treatment Plan</h3>
                        <textarea class="w-full bg-gray-50 border border-gray-100 rounded-xl p-4 text-sm text-gray-600 min-h-[120px] focus:outline-none focus:border-blue-300" placeholder="e.g. 1. Amoxicillin 500mg 3 time/day - 10 days..."></textarea>
                        
                        <div class="flex gap-4 pt-6">
                            <button class="flex-1 bg-blue-500 hover:bg-blue-600 text-white py-4 rounded-2xl font-semibold transition-colors">Save & Update Here</button>
                            <button class="flex-1 border border-gray-300 hover:bg-gray-50 py-4 rounded-2xl font-semibold transition-colors">Cancel</button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>