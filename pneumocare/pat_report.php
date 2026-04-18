<?php
// ==========================================
// TÊN FILE: pat_report.php
// CHỨC NĂNG: Bệnh nhân xem Bệnh án (Read-only) và Cập nhật Tiền sử bệnh + Thông tin cá nhân
// ==========================================
session_start();
require 'db.php';
ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL);

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Patient') { 
    header("Location: login.php"); 
    exit(); 
}

$patientId = $_SESSION['user_id'];
$patientName = $_SESSION['name'];
$patientAvatar = (!empty($_SESSION['avatar']) && $_SESSION['avatar'] != 'default.png') ? $_SESSION['avatar'] : 'img/default_patient.png';

$updateMsg = "";
$latestAppt = null;
$apptHistory = [];
$expertComments = [];
$medicalHistory = [];

// ==========================================
// 1. XỬ LÝ CẬP NHẬT THÔNG TIN CÁ NHÂN (PROFILE)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $phone = trim($_POST['phone_number']);
    $address = trim($_POST['address']);
    $bloodGroup = trim($_POST['blood_group']);
    
    try {
        $stmtUpdate = $pdo->prepare("UPDATE Patient_Profiles SET phone_number = ?, address = ?, blood_group = ? WHERE patient_id = ?");
        $stmtUpdate->execute([$phone, $address, $bloodGroup, $patientId]);
        $updateMsg = "<div class='mb-6 p-4 bg-green-50 text-green-700 rounded-xl border border-green-200 flex items-center gap-3 font-medium shadow-sm'><i class='fa-solid fa-circle-check'></i> Personal profile updated successfully!</div>";
    } catch (PDOException $e) {
        $updateMsg = "<div class='mb-6 p-4 bg-red-50 text-red-600 rounded-xl border border-red-200 flex items-center gap-3 shadow-sm'><i class='fa-solid fa-circle-exclamation'></i> DB Error: " . $e->getMessage() . "</div>";
    }
}

// ==========================================
// 2. XỬ LÝ THÊM MỚI MEDICAL HISTORY
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_history'])) {
    $conditionName = trim($_POST['condition_name']);
    $conditionType = $_POST['condition_type']; // 'Disease' hoặc 'Surgery'
    
    if (!empty($conditionName) && in_array($conditionType, ['Disease', 'Surgery'])) {
        try {
            $stmtInsert = $pdo->prepare("INSERT INTO Medical_History (patient_id, condition_name, type, date_recorded) VALUES (?, ?, ?, CURDATE())");
            $stmtInsert->execute([$patientId, $conditionName, $conditionType]);
            $updateMsg = "<div class='mb-6 p-4 bg-green-50 text-green-700 rounded-xl border border-green-200 flex items-center gap-3 font-medium shadow-sm'><i class='fa-solid fa-circle-check'></i> History record added successfully!</div>";
        } catch (PDOException $e) {
            $updateMsg = "<div class='mb-6 p-4 bg-red-50 text-red-600 rounded-xl border border-red-200 flex items-center gap-3 shadow-sm'><i class='fa-solid fa-circle-exclamation'></i> DB Error: " . $e->getMessage() . "</div>";
        }
    }
}

// ==========================================
// 3. XỬ LÝ XÓA MEDICAL HISTORY
// ==========================================
if (isset($_GET['delete_history'])) {
    $delId = $_GET['delete_history'];
    try {
        $stmtDel = $pdo->prepare("DELETE FROM Medical_History WHERE history_id = ? AND patient_id = ?");
        $stmtDel->execute([$delId, $patientId]);
        header("Location: pat_report.php");
        exit();
    } catch (PDOException $e) {}
}

// ==========================================
// 4. LẤY DỮ LIỆU HIỂN THỊ
// ==========================================
$patientInfo = null;
try {
    // Thông tin cá nhân
    $stmtInfo = $pdo->prepare("SELECT u.full_name, u.avatar_url, pp.* FROM Users u JOIN Patient_Profiles pp ON u.user_id = pp.patient_id WHERE u.user_id = ?");
    $stmtInfo->execute([$patientId]);
    $patientInfo = $stmtInfo->fetch();

    // Danh sách Tiền sử bệnh
    $stmtMed = $pdo->prepare("SELECT * FROM Medical_History WHERE patient_id = ? ORDER BY date_recorded DESC");
    $stmtMed->execute([$patientId]);
    $medicalHistory = $stmtMed->fetchAll();

    // Lịch sử Khám (Chỉ lấy Completed)
    $stmtHistory = $pdo->prepare("
        SELECT a.*, u_d.full_name as doctor_name 
        FROM Appointments a 
        JOIN Users u_d ON a.doctor_id = u_d.user_id 
        WHERE a.patient_id = ? AND a.status = 'Completed' 
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
    ");
    $stmtHistory->execute([$patientId]);
    $apptHistory = $stmtHistory->fetchAll();

    // Lấy Expert Comments cho ca khám mới nhất
    if (!empty($apptHistory)) {
        $latestAppt = $apptHistory[0];
        
        $stmtCmt = $pdo->prepare("SELECT ec.*, u.full_name as doc_name FROM Expert_Comments ec JOIN Users u ON ec.doctor_id = u.user_id WHERE ec.appointment_id = ? ORDER BY ec.created_at ASC");
        $stmtCmt->execute([$latestAppt['appointment_id']]);
        $expertComments = $stmtCmt->fetchAll();
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
    <title>Pneumo-Care | Medical Report</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background-color: #f4f7fa; color: #1f2937; }
        .layout { display: flex; min-height: 100vh; }
        
        .sidebar { width: 260px; background: #ffffff; border-right: 1px solid #e5e7eb; display: flex; flex-direction: column; min-height: 100vh; flex-shrink: 0; z-index: 10; }
        .sidebar-active { background-color: #eff6ff; color: #2563eb; border-left: 4px solid #2563eb; font-weight: 600; }

        .main-content { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
        .topbar-wrapper { padding: 32px 40px 0 40px; }
        .topbar { height: 72px; background: #ffffff; border: 1px solid #f3f4f6; display: flex; align-items: center; justify-content: space-between; padding: 0 24px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 24px; }
        .topbar h1 { font-size: 22px; font-weight: 600; color: #1f2937; margin: 0; }
        .content-area { padding: 0 40px 40px 40px; flex: 1; overflow-y: auto; }

        .section-title { display: inline-block; font-size: 16px; font-weight: 700; color: #1f2937; padding-bottom: 8px; border-bottom: 3px solid #3b82f6; margin-bottom: 20px; }
        .blue-border-box { border: 1px solid #bfdbfe; border-radius: 12px; overflow: hidden; background: #ffffff; }
        .blue-border-header { background-color: #eff6ff; padding: 12px 20px; font-size: 14px; font-weight: 600; color: #1e3a8a; border-bottom: 1px solid #bfdbfe; }

        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    </style>
</head>
<body class="flex h-screen overflow-hidden text-gray-800">
<div class="flex w-full h-full relative">
  
    <aside class="w-64 bg-white border-r border-gray-200 flex flex-col h-full flex-shrink-0 z-10 shadow-sm">
        <div class="h-[88px] flex items-center px-6 border-b border-gray-100">
            <svg viewBox="0 0 32 32" fill="none" width="30" height="30" class="mr-3 flex-shrink-0"><ellipse cx="10" cy="18" rx="7" ry="10" fill="#f87171" transform="rotate(-10 10 18)"/><ellipse cx="22" cy="18" rx="7" ry="10" fill="#fca5a5" transform="rotate(10 22 18)"/></svg>
            <div class="text-[22px] font-bold text-gray-900 tracking-tight">Pneumo-<span class="text-blue-500">Care</span></div>
        </div>

        <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto">
            <a href="pat_dashboard.php" class="flex items-center gap-4 px-4 py-3 text-gray-500 hover:bg-gray-50 hover:text-gray-800 rounded-xl transition-colors font-medium">
                <i class="fa-solid fa-gauge-high w-5 text-center text-xl"></i><span>Dashboard</span>
            </a>
            <a href="pat_report.php" class="sidebar-active flex items-center gap-4 px-4 py-3 rounded-xl font-semibold transition-colors">
                <i class="fa-solid fa-file-medical w-5 text-center text-xl"></i><span>Report</span>
            </a>
            <a href="pat_appointments.php" class="flex items-center gap-4 px-4 py-3 text-gray-500 hover:bg-gray-50 hover:text-gray-800 rounded-xl transition-colors font-medium">
                <i class="fa-solid fa-calendar-check w-5 text-center text-xl"></i><span>Appointments</span>
            </a>
            <a href="pat_doctors.php" class="flex items-center gap-4 px-4 py-3 text-gray-500 hover:bg-gray-50 hover:text-gray-800 rounded-xl transition-colors font-medium">
                <i class="fa-solid fa-user-doctor w-5 text-center text-xl"></i><span>Doctors</span>
            </a>
            <a href="pat_messages.php" class="flex items-center gap-4 px-4 py-3 text-gray-500 hover:bg-gray-50 hover:text-gray-800 rounded-xl transition-colors font-medium">
                <i class="fa-solid fa-comment-dots w-5 text-center text-xl"></i><span>Messages</span>
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
            <h1>My Medical Report</h1>
            <div class="flex items-center gap-6">
                <div class="flex items-center gap-3 cursor-pointer">
                    <div class="text-right hidden sm:block">
                        <p class="text-sm font-semibold text-gray-800" style="line-height: 1.2;"><?php echo htmlspecialchars($patientName); ?></p>
                        <p class="text-xs text-gray-500 font-medium">Patient</p>
                    </div>
                    <img src="<?php echo $patientAvatar; ?>" class="w-10 h-10 rounded-full object-cover border border-gray-200 shadow-sm" alt="Avatar">
                </div>
            </div>
            </header>
        </div>

        <div class="content-area">
            <?php echo $updateMsg; ?>

            <div class="grid grid-cols-1 xl:grid-cols-12 gap-10 max-w-7xl mx-auto w-full">
                
                <div class="xl:col-span-7 space-y-8">
                    
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 relative">
                        <button onclick="document.getElementById('editProfileModal').classList.remove('hidden')" class="absolute top-8 right-8 text-gray-400 hover:text-blue-600 transition-colors bg-gray-50 hover:bg-blue-50 px-3 py-1.5 rounded-lg border border-gray-100 flex items-center gap-2 text-xs font-bold uppercase tracking-widest shadow-sm">
                            <i class="fa-solid fa-pen-to-square"></i> Edit
                        </button>

                        <div class="section-title">Patient Information</div>
                        <div class="flex gap-8 items-center mt-2">
                            <img src="<?php echo $patientInfo['avatar_url'] ?: 'img/default.png'; ?>" class="w-28 h-28 rounded-full object-cover border-4 border-gray-50 shadow-sm">
                            <div class="flex-1 grid grid-cols-2 gap-y-5 gap-x-6 text-sm">
                                <div class="flex flex-col"><span class="text-gray-900 font-bold mb-1">Name</span><span class="text-blue-600 font-semibold"><?php echo htmlspecialchars($patientInfo['full_name']); ?></span></div>
                                <div class="flex flex-col"><span class="text-gray-900 font-bold mb-1">Date of birth</span><span class="text-gray-600"><?php echo date('d/m/Y', strtotime($patientInfo['date_of_birth'])); ?></span></div>
                                <div class="flex flex-col"><span class="text-gray-900 font-bold mb-1">Gender</span><span class="text-gray-600"><?php echo htmlspecialchars($patientInfo['gender']); ?></span></div>
                                <div class="flex flex-col"><span class="text-gray-900 font-bold mb-1">Address</span><span class="text-gray-600"><?php echo htmlspecialchars($patientInfo['address'] ?? 'N/A'); ?></span></div>
                                <div class="flex flex-col"><span class="text-gray-900 font-bold mb-1">Phone Number</span><span class="text-gray-600"><?php echo htmlspecialchars($patientInfo['phone_number']); ?></span></div>
                                <div class="flex flex-col"><span class="text-gray-900 font-bold mb-1">Blood Group</span><span class="font-bold text-red-500"><?php echo htmlspecialchars($patientInfo['blood_group'] ?? 'N/A'); ?></span></div>
                            </div>
                        </div>
                    </div>

                    <div id="editProfileModal" class="hidden fixed inset-0 bg-gray-900/60 backdrop-blur-sm z-50 flex items-center justify-center">
                        <div class="bg-white rounded-2xl shadow-2xl w-[500px] overflow-hidden">
                            <div class="bg-blue-600 px-6 py-4 flex items-center justify-between text-white">
                                <h3 class="font-semibold text-lg">Update Profile Info</h3>
                                <button type="button" onclick="document.getElementById('editProfileModal').classList.add('hidden')" class="text-white/80 hover:text-white transition-colors"><i class="fa-solid fa-xmark text-xl"></i></button>
                            </div>
                            
                            <form method="POST" class="p-8 space-y-5">
                                <div>
                                    <label class="block text-[11px] font-bold text-gray-600 mb-2 uppercase tracking-widest">Phone Number</label>
                                    <input type="text" name="phone_number" required value="<?php echo htmlspecialchars($patientInfo['phone_number']); ?>" class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:border-blue-500 outline-none bg-gray-50 font-medium transition-all">
                                </div>
                                
                                <div>
                                    <label class="block text-[11px] font-bold text-gray-600 mb-2 uppercase tracking-widest">Address</label>
                                    <input type="text" name="address" value="<?php echo htmlspecialchars($patientInfo['address'] ?? ''); ?>" placeholder="Enter your current address" class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:border-blue-500 outline-none bg-gray-50 font-medium transition-all">
                                </div>

                                <div class="flex gap-3 pt-4 border-t border-gray-100 mt-2">
                                    <button type="button" onclick="document.getElementById('editProfileModal').classList.add('hidden')" class="flex-1 text-center py-3 border border-gray-200 rounded-xl text-gray-500 font-bold hover:bg-gray-50 transition-colors uppercase text-xs">Cancel</button>
                                    <button type="submit" name="update_profile" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-3 rounded-xl font-bold transition-all shadow-md uppercase text-xs flex items-center justify-center gap-2"><i class="fa-solid fa-floppy-disk"></i> Save Changes</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8">
                        <div class="flex items-center justify-between border-b border-gray-100 pb-4 mb-6">
                            <div class="section-title !mb-0 !border-0 !pb-0">Medical History</div>
                        </div>
                        
                        <p class="text-sm font-medium text-gray-500 mb-5">Please declare your known medical history, previous surgeries, or allergies to assist the doctor during diagnosis.</p>

                        <div class="space-y-3 mb-8">
                            <?php if(empty($medicalHistory)): ?>
                                <div class="bg-gray-50 border border-dashed border-gray-200 rounded-xl p-4 text-center text-sm text-gray-400 italic">No medical history recorded yet.</div>
                            <?php else: ?>
                                <?php foreach($medicalHistory as $mh): ?>
                                    <div class="flex items-center justify-between bg-gray-50 border border-gray-100 rounded-xl p-3 px-5 group hover:bg-white hover:shadow-sm transition-all">
                                        <div class="flex items-center gap-3">
                                            <i class="fa-solid fa-circle-dot text-blue-400 text-[10px]"></i>
                                            <span class="text-sm font-semibold text-gray-700"><?php echo htmlspecialchars($mh['condition_name']); ?></span>
                                            <span class="text-[10px] uppercase font-bold <?php echo $mh['type'] == 'Disease' ? 'text-purple-500' : 'text-orange-500'; ?> bg-white px-2 py-0.5 rounded border border-gray-100 shadow-sm"><?php echo $mh['type']; ?></span>
                                        </div>
                                        <a href="?delete_history=<?php echo $mh['history_id']; ?>" onclick="return confirm('Remove this record?')" class="text-gray-300 hover:text-red-500 transition-colors opacity-0 group-hover:opacity-100">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <form method="POST" action="" class="bg-blue-50/50 border border-blue-100 rounded-xl p-5">
                            <p class="text-[11px] font-bold text-blue-600 uppercase mb-3 tracking-widest"><i class="fa-solid fa-notes-medical mr-1"></i> Add New Record</p>
                            <div class="flex flex-col sm:flex-row gap-3">
                                <input type="text" name="condition_name" required placeholder="E.g., Asthma, Hypertension, Penicillin allergy..." class="flex-1 bg-white border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:border-blue-400 outline-none transition-colors font-medium">
                                <select name="condition_type" class="bg-white border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:border-blue-400 outline-none w-32 text-gray-700 font-semibold cursor-pointer">
                                    <option value="Disease">Disease</option>
                                    <option value="Surgery">Surgery</option>
                                </select>
                                <button type="submit" name="add_history" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2.5 rounded-lg text-sm font-bold transition-all shadow-md flex items-center justify-center gap-2 uppercase tracking-wide">
                                    <i class="fa-solid fa-plus"></i> Save
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8">
                        <div class="section-title">Appointment History</div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm text-left">
                                <thead>
                                    <tr class="text-gray-800 border-b border-gray-200">
                                        <th class="py-3 font-bold w-[15%]">Time <i class="fa-solid fa-sort text-gray-400 ml-1"></i></th>
                                        <th class="py-3 font-bold w-[20%]">Date <i class="fa-solid fa-sort text-gray-400 ml-1"></i></th>
                                        <th class="py-3 font-bold w-[25%]">Doctor <i class="fa-solid fa-sort text-gray-400 ml-1"></i></th>
                                        <th class="py-3 font-bold w-[40%]">Diagnosis <i class="fa-solid fa-sort text-gray-400 ml-1"></i></th>
                                    </tr>
                                </thead>
                                <tbody class="text-gray-600">
                                    <?php if(empty($apptHistory)): ?>
                                        <tr><td colspan="4" class="py-10 text-center text-gray-400 italic">No completed appointments found.</td></tr>
                                    <?php else: ?>
                                        <?php foreach($apptHistory as $h): ?>
                                        <tr class="border-b border-gray-50 align-top hover:bg-gray-50/50 transition-colors">
                                            <td class="py-5 font-medium text-gray-800"><?php echo date('H:i', strtotime($h['appointment_time'])); ?></td>
                                            <td class="py-5 font-medium text-gray-800"><?php echo date('d/m/Y', strtotime($h['appointment_date'])); ?></td>
                                            <td class="py-5 font-medium">Dr. <?php echo htmlspecialchars($h['doctor_name']); ?></td>
                                            <td class="py-5 text-xs leading-relaxed italic">
                                                <?php 
                                                    if (!empty($h['ai_prediction_label'])) echo "<strong class='text-blue-600 not-italic'>AI Result:</strong> " . $h['ai_prediction_label'] . " (" . $h['ai_confidence_score'] . "%)<br>";
                                                    echo !empty($h['patient_notes']) ? htmlspecialchars($h['patient_notes']) : '<span class="text-gray-400">No notes recorded.</span>';
                                                ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="xl:col-span-5 space-y-6">
                    
                    <?php if(!$latestAppt): ?>
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-10 text-center h-full flex flex-col justify-center items-center">
                            <i class="fa-solid fa-folder-open text-4xl text-gray-200 mb-4"></i>
                            <h3 class="text-lg font-bold text-gray-400">No Medical Records</h3>
                            <p class="text-sm text-gray-400 mt-2">Complete an appointment to view your doctor's clinical notes, diagnosis, and treatment plan here.</p>
                        </div>
                    <?php else: ?>
                        <div class="bg-blue-50 border border-blue-100 rounded-xl p-4 flex items-center justify-between">
                            <div>
                                <p class="text-[10px] font-bold text-blue-400 uppercase tracking-widest">Latest Record</p>
                                <p class="font-bold text-[#003366] text-sm mt-0.5">Dr. <?php echo htmlspecialchars($latestAppt['doctor_name']); ?></p>
                            </div>
                            <span class="bg-blue-600 text-white font-bold text-xs px-3 py-1.5 rounded-lg shadow-sm">
                                <?php echo date('d/m/Y', strtotime($latestAppt['appointment_date'])); ?>
                            </span>
                        </div>

                        <div class="blue-border-box shadow-sm">
                            <div class="blue-border-header">Current Diagnosis & Notes</div>
                            <div class="p-5 space-y-4">
                                <div>
                                    <label class="block font-bold text-gray-800 text-[13px] uppercase tracking-wide mb-2">Diagnose</label>
                                    <div class="w-full bg-gray-50 border border-gray-100 rounded-xl p-4 text-sm text-gray-700 min-h-[60px] leading-relaxed">
                                        <?php echo !empty($latestAppt['patient_notes']) ? nl2br(htmlspecialchars($latestAppt['patient_notes'])) : '<span class="text-gray-400 italic">No diagnosis provided.</span>'; ?>
                                    </div>
                                </div>
                                <hr class="border-gray-100">
                                <div>
                                    <label class="block font-bold text-gray-800 text-[13px] uppercase tracking-wide mb-2">Clinical Notes & Symptoms</label>
                                    <div class="w-full bg-gray-50 border border-gray-100 rounded-xl p-4 text-sm text-gray-700 min-h-[80px] leading-relaxed">
                                        <?php echo !empty($latestAppt['patient_symptoms_note']) ? nl2br(htmlspecialchars($latestAppt['patient_symptoms_note'])) : '<span class="text-gray-400 italic">No clinical notes recorded.</span>'; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="blue-border-box shadow-sm">
                            <div class="blue-border-header">Treatment Plan & Doctor's Advice</div>
                            <div class="p-5">
                                <div class="w-full bg-blue-50/30 border border-blue-100 rounded-xl p-5 text-sm text-gray-800 min-h-[120px] leading-relaxed font-medium">
                                    <?php echo !empty($latestAppt['treatment_plan']) ? nl2br(htmlspecialchars($latestAppt['treatment_plan'])) : '<span class="text-gray-400 italic font-normal">No treatment plan provided.</span>'; ?>
                                </div>
                            </div>
                        </div>

                        <div class="blue-border-box !border-red-400 mt-8 relative shadow-sm">
                            <div class="absolute inset-0 border-2 border-red-500 rounded-xl pointer-events-none"></div>
                            <div class="blue-border-header !bg-transparent !border-b-gray-100 !text-gray-800 relative z-10 flex items-center justify-between">
                                <span>Expert Commentary Area</span>
                                <i class="fa-solid fa-users-viewfinder text-red-400"></i>
                            </div>
                            
                            <div class="p-5 relative z-10 flex flex-col max-h-[300px] overflow-y-auto no-scrollbar">
                                <?php if(empty($expertComments)): ?>
                                    <div class="text-center py-8">
                                        <i class="fa-regular fa-comments text-3xl text-gray-200 mb-2"></i>
                                        <p class="text-sm text-gray-400 italic">No expert consultation notes for this record.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="space-y-4">
                                        <?php foreach($expertComments as $cmt): ?>
                                            <div>
                                                <p class="font-bold text-gray-900 text-sm mb-1">Dr. <?php echo htmlspecialchars($cmt['doc_name']); ?> <span class="text-[10px] text-gray-400 font-medium ml-2"><?php echo date('d/m, H:i', strtotime($cmt['created_at'])); ?></span></p>
                                                <p class="text-sm text-gray-700 leading-relaxed bg-red-50/50 p-3.5 rounded-xl border border-red-100/50 shadow-sm">
                                                    <?php echo nl2br(htmlspecialchars($cmt['comment_content'])); ?>
                                                </p>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <p class="text-center text-xs text-gray-400 mt-3"><i class="fa-solid fa-lock text-gray-300 mr-1"></i> These records are locked and provided strictly for your viewing.</p>
                    <?php endif; ?>
                    
                </div>
            </div>
        </div>
    </main>
</div>
</body>
</html>