<?php
// ==========================================
// TÊN FILE: doc_appointment_detail.php
// CHỨC NĂNG: Bác sĩ xem chi tiết Lịch hẹn & Bệnh nhân
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

if (!isset($_GET['id'])) {
    header("Location: doc_dashboard.php"); exit();
}
$apptId = $_GET['id'];

$apptDetail = null;
try {
    // Lấy toàn bộ thông tin lịch hẹn + Thông tin cá nhân bệnh nhân
    $stmt = $pdo->prepare("
        SELECT a.*, 
               u_p.full_name as patient_name, u_p.email as patient_email, u_p.avatar_url as p_avatar,
               pp.date_of_birth, pp.gender, pp.blood_group, pp.phone_number
        FROM Appointments a
        JOIN Users u_p ON a.patient_id = u_p.user_id
        JOIN Patient_Profiles pp ON u_p.user_id = pp.patient_id
        WHERE a.appointment_id = ? AND a.doctor_id = ?
    ");
    $stmt->execute([$apptId, $doctorId]);
    $apptDetail = $stmt->fetch();

    if (!$apptDetail) {
        die("Không tìm thấy lịch hẹn hoặc bạn không có quyền xem lịch này.");
    }
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
    <title>Pneumo-Care | Appointment Details</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; color: #1a2a3a; }
        .sidebar-active { background-color: #eff6ff; color: #2563eb; border-left: 4px solid #2563eb; }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #cbd5e1; }
        
        /* Giao diện Summary Card từ Patient */
        .summary-card { background: #fff; border-radius: 14px; padding: 28px 32px; border: 1px solid #e0e8f0; max-width: 860px; margin: 0 auto; box-shadow: 0 2px 10px rgba(0,0,0,0.02); }
        .summary-title { text-align: center; font-size: 24px; font-weight: 700; color: #1e3a5f; margin-bottom: 20px; }
        .summary-inner { border: 1.5px solid #3b82f6; border-radius: 10px; overflow: hidden; }
        .summary-doctor-row { padding: 16px 20px; display: flex; align-items: center; gap: 14px; border-bottom: 1px solid #e0e8f0; background-color: #f8fafc; }
        .summary-info-rows { padding: 16px 20px; display: flex; flex-direction: column; gap: 12px; }
        .summary-row { display: flex; align-items: flex-start; gap: 12px; font-size: 14px; }
        .symptom-textarea { width: 100%; border: 1px solid #e0e8f0; border-radius: 8px; padding: 12px; font-size: 14px; color: #4b5563; min-height: 80px; font-family: inherit; resize: vertical; background: #f9fafb; outline: none; }
        .symptom-textarea:focus { border-color: #3b82f6; background: #fff; }
        
        .btn-dark { background: #1e293b; color: #f59e0b; border: none; padding: 13px 40px; border-radius: 10px; font-size: 15px; font-weight: 600; cursor: pointer; display: block; margin: 24px auto 0; min-width: 200px; transition: 0.2s; }
        .btn-dark:hover { background: #0f172a; }
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

    <main class="flex-1 overflow-y-auto bg-gray-50">
        <div class="p-8">
            <header class="flex justify-between items-center mb-8">
                <div class="flex items-center gap-4">
                    <a href="doc_dashboard.php" class="text-gray-400 hover:text-blue-600 text-xl"><i class="fa-solid fa-arrow-left"></i></a>
                    <h2 class="text-2xl font-semibold text-gray-700">Appointment Details</h2>
                </div>
                <div class="flex items-center gap-6">
                    <div class="flex items-center gap-3">
                        <img src="<?php echo $doctorAvatar; ?>" class="w-10 h-10 rounded-full border-2 border-white shadow object-cover" alt="Doctor">
                        <div><p class="text-sm font-semibold"><?php echo htmlspecialchars($doctorName); ?></p><p class="text-xs text-gray-500">Doctor</p></div>
                    </div>
                </div>
            </header>

            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-8 max-w-[860px] mx-auto">
                <h3 class="text-lg font-semibold text-gray-700 mb-4 border-b pb-3">Patient Profile</h3>
                <div class="flex gap-6 items-center">
                    <img src="<?php echo $apptDetail['p_avatar'] ?: 'img/default.png'; ?>" class="w-24 h-24 rounded-2xl object-cover border border-gray-200 shadow-sm" alt="Patient">
                    <div class="flex-1 grid grid-cols-3 gap-y-4 gap-x-6 text-sm">
                        <div><p class="text-gray-400 text-xs uppercase font-semibold mb-1">Full Name</p><p class="font-bold text-gray-800 text-base"><?php echo htmlspecialchars($apptDetail['patient_name']); ?></p></div>
                        <div><p class="text-gray-400 text-xs uppercase font-semibold mb-1">Age & Gender</p><p class="font-medium text-gray-800"><?php echo calculateAge($apptDetail['date_of_birth']); ?> years • <?php echo $apptDetail['gender']; ?></p></div>
                        <div><p class="text-gray-400 text-xs uppercase font-semibold mb-1">Blood Group</p><p class="font-bold text-red-500"><?php echo $apptDetail['blood_group']; ?></p></div>
                        <div><p class="text-gray-400 text-xs uppercase font-semibold mb-1">Phone Number</p><p class="font-medium text-gray-800"><?php echo htmlspecialchars($apptDetail['phone_number']); ?></p></div>
                        <div class="col-span-2"><p class="text-gray-400 text-xs uppercase font-semibold mb-1">Email</p><p class="font-medium text-gray-800"><?php echo htmlspecialchars($apptDetail['patient_email']); ?></p></div>
                    </div>
                </div>
            </div>

            <div class="summary-card">
                <div class="summary-title">Appointment Schedule</div>
                <div class="summary-inner">
                    <div class="summary-doctor-row">
                        <div class="w-12 h-12 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center font-bold text-lg"><i class="fa-regular fa-calendar"></i></div>
                        <div>
                            <div style="font-size:18px;font-weight:700;color:#1e3a5f;">Status: <span class="text-yellow-600 uppercase text-sm bg-yellow-100 px-2 py-1 rounded"><?php echo $apptDetail['status']; ?></span></div>
                            <div style="font-size:13px; margin-top:4px;"><span style="color:#6b7280;">Fee Status: </span><span class="<?php echo $apptDetail['fee_status'] == 'Paid' ? 'text-green-600 font-bold' : 'text-red-500 font-bold'; ?>"><?php echo $apptDetail['fee_status']; ?></span></div>
                        </div>
                    </div>
                    
                    <div class="summary-info-rows">
                        <div class="summary-row">
                            <span style="font-weight:bold; color:#ef4444; width: 80px;">Time:</span>
                            <span class="font-medium"><?php echo date('h:i A', strtotime($apptDetail['appointment_time'])) . ' – ' . date('F d, Y', strtotime($apptDetail['appointment_date'])); ?></span>
                        </div>
                        <div class="summary-row">
                            <span style="font-weight:bold; color:#3b82f6; width: 80px;">Patient:</span>
                            <span class="font-medium"><?php echo htmlspecialchars($apptDetail['patient_name']); ?></span>
                        </div>
                        <div class="summary-row">
                            <span style="font-weight:bold; color:#6b7280; width: 80px;">Location:</span>
                            <span>Medical Center No. 1 Ton That Tung, Dong Da, Ha Noi</span>
                        </div>
                        
                        <div class="summary-row" style="margin-top:10px;">
                            <span style="color:#6b7280; font-weight:bold;">Patient's Symptoms / Notes:</span>
                        </div>
                            <textarea class="symptom-textarea" readonly placeholder="Patient did not provide specific notes...">
                            <?php echo htmlspecialchars($apptDetail['patient_notes'] ?? ''); ?>
                            </textarea>
                    </div>
                </div>
                
                <div class="flex gap-4 mt-6">
                    <button class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-medium py-3.5 rounded-xl transition-colors" onclick="location.href='doc_ai_workspace.php?patient_id=<?php echo $apptDetail['patient_id']; ?>'">
                        <i class="fa-solid fa-brain mr-2"></i> Start AI Diagnosis
                    </button>
                    <button class="flex-1 border border-gray-300 text-gray-700 hover:bg-gray-50 font-medium py-3.5 rounded-xl transition-colors">
                        Mark as Completed
                    </button>
                </div>
            </div>

        </div>
    </main>
</body>
</html>