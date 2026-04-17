<?php
// ==========================================
// TÊN FILE: doc_medical_record.php
// CHỨC NĂNG: Xem hồ sơ bệnh án chi tiết (Giao diện chuẩn EMR)
// ==========================================
session_start();
require 'db.php';
ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL);

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Doctor') { header("Location: login.php"); exit(); }
$doctorId = $_SESSION['user_id'];
$doctorName = $_SESSION['name'];
$doctorAvatar = (!empty($_SESSION['avatar']) && $_SESSION['avatar'] != 'default.png') ? $_SESSION['avatar'] : 'img/default.png';

$patientId = isset($_GET['patient_id']) ? $_GET['patient_id'] : null;
$patientInfo = null;
$diseases = [];
$surgeries = [];
$appointmentHistory = [];
$latestAppt = null;
$expertComments = [];
$msg = "";

// 1. XỬ LÝ LƯU CẬP NHẬT BỆNH ÁN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_record'])) {
    try {
        $apptId = $_POST['appointment_id'];
        $diagnose = $_POST['diagnose'];
        $notes = $_POST['clinical_notes'];
        $treatment = $_POST['treatment_plan'];

        $stmtUp = $pdo->prepare("UPDATE Appointments SET status = 'Completed', patient_notes = ?, patient_symptoms_note = ?, treatment_plan = ? WHERE appointment_id = ?");
        $stmtUp->execute([$diagnose, $notes, $treatment, $apptId]);
        $msg = "<div class='bg-green-50 text-green-600 p-4 rounded-xl mb-6 border border-green-200 text-sm font-medium'>✅ Bệnh án đã được cập nhật thành công!</div>";
    } catch (PDOException $e) { $msg = "<div class='bg-red-50 text-red-600 p-4 rounded-xl mb-6'>Lỗi: " . $e->getMessage() . "</div>"; }
}

// 2. XỬ LÝ THÊM COMMENT HỘI CHẨN (EXPERT COMMENT)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_comment'])) {
    try {
        $apptId = $_POST['appointment_id'];
        $commentContent = trim($_POST['new_comment']);
        if (!empty($commentContent)) {
            $stmtCmt = $pdo->prepare("INSERT INTO Expert_Comments (appointment_id, doctor_id, comment_content) VALUES (?, ?, ?)");
            $stmtCmt->execute([$apptId, $doctorId, $commentContent]);
            $msg = "<div class='bg-blue-50 text-blue-600 p-4 rounded-xl mb-6 border border-blue-200 text-sm font-medium'>✅ Đã gửi bình luận hội chẩn!</div>";
        }
    } catch (PDOException $e) { $msg = "<div class='bg-red-50 text-red-600 p-4 rounded-xl mb-6'>Lỗi: " . $e->getMessage() . "</div>"; }
}

// 3. LẤY DỮ LIỆU TỪ DATABASE
if ($patientId) {
    try {
        // Thông tin cá nhân
        $stmt = $pdo->prepare("SELECT u.full_name, u.avatar_url, pp.* FROM Users u JOIN Patient_Profiles pp ON u.user_id = pp.patient_id WHERE u.user_id = ?");
        $stmt->execute([$patientId]);
        $patientInfo = $stmt->fetch();

        // Lịch sử khám
        $stmtHist = $pdo->prepare("SELECT a.*, u.full_name as doc_name FROM Appointments a JOIN Users u ON a.doctor_id = u.user_id WHERE a.patient_id = ? ORDER BY a.appointment_date DESC, a.appointment_time DESC");
        $stmtHist->execute([$patientId]);
        $appointmentHistory = $stmtHist->fetchAll();
        
        if (!empty($appointmentHistory)) {
            $latestAppt = $appointmentHistory[0];
            
            // Lấy Comment Hội chẩn cho cuộc hẹn gần nhất
            $stmtCmt = $pdo->prepare("SELECT ec.*, u.full_name as doc_name FROM Expert_Comments ec JOIN Users u ON ec.doctor_id = u.user_id WHERE ec.appointment_id = ? ORDER BY ec.created_at ASC");
            $stmtCmt->execute([$latestAppt['appointment_id']]);
            $expertComments = $stmtCmt->fetchAll();
        }

        // Phân loại Tiền sử bệnh (Disease) và Phẫu thuật (Surgery)
        $stmtMed = $pdo->prepare("SELECT * FROM Medical_History WHERE patient_id = ? ORDER BY date_recorded DESC");
        $stmtMed->execute([$patientId]);
        $allHistory = $stmtMed->fetchAll();
        foreach ($allHistory as $mh) {
            if ($mh['type'] == 'Disease') $diseases[] = $mh;
            elseif ($mh['type'] == 'Surgery') $surgeries[] = $mh;
        }

    } catch (PDOException $e) { $msg = "<div class='bg-red-50 text-red-600 p-4 rounded-xl mb-6'>DB Error: " . $e->getMessage() . "</div>"; }
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f4f7fa; color: #1f2937; }
        .sidebar-active { background-color: #eff6ff; color: #2563eb; border-left: 4px solid #2563eb; }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        
        /* Style cho các tiêu đề gạch chân màu xanh (giống ảnh Figma) */
        .section-title {
            display: inline-block;
            font-size: 16px;
            font-weight: 700;
            color: #1f2937;
            padding-bottom: 8px;
            border-bottom: 3px solid #3b82f6;
            margin-bottom: 20px;
        }
        
        /* Box viền xanh bên phải */
        .blue-border-box {
            border: 1px solid #bfdbfe;
            border-radius: 12px;
            overflow: hidden;
            background: #ffffff;
        }
        .blue-border-header {
            background-color: #eff6ff;
            padding: 12px 20px;
            font-size: 14px;
            font-weight: 600;
            color: #1e3a8a;
            border-bottom: 1px solid #bfdbfe;
        }
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

    <div class="flex-1 flex flex-col h-screen overflow-hidden bg-white">
        <header class="bg-white border-b px-8 py-5 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <h2 class="text-2xl font-bold text-[#374151]">Detailed Medical Record</h2>
            </div>
            <div class="flex items-center gap-4">
                <div class="flex items-center gap-3 border-l pl-4 border-gray-200">
                    <div class="text-right hidden sm:block"><p class="text-sm font-bold text-blue-600" style="line-height: 1.2;">Dr. <?php echo htmlspecialchars($doctorName); ?></p><p class="text-xs text-gray-500 font-medium">Doctor</p></div>
                    <img src="<?php echo $doctorAvatar; ?>" class="w-10 h-10 rounded-full border border-gray-200 object-cover">
                </div>
            </div>
        </header>

        <div class="flex-1 overflow-auto p-10 bg-[#f8fafc]">
            <?php echo $msg; ?>

            <?php if(!$patientInfo): ?>
                <div class="bg-white rounded-2xl p-10 text-center text-gray-400 italic shadow-sm border border-gray-100">Please select a patient to view.</div>
            <?php else: ?>
            
            <div class="grid grid-cols-12 gap-10">
                <div class="col-span-12 lg:col-span-7 space-y-8">
                    
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8">
                        <div class="section-title">Patient Information</div>
                        <div class="flex gap-8 items-center">
                            <img src="<?php echo $patientInfo['avatar_url'] ?: 'img/default.png'; ?>" class="w-32 h-32 rounded-full object-cover border-4 border-gray-50 shadow-sm">
                            <div class="flex-1 grid grid-cols-2 gap-y-4 gap-x-6 text-sm">
                                <div class="flex flex-col"><span class="text-gray-900 font-bold mb-1">Name</span><span class="text-blue-600 font-semibold"><?php echo htmlspecialchars($patientInfo['full_name']); ?></span></div>
                                <div class="flex flex-col"><span class="text-gray-900 font-bold mb-1">Date of birth</span><span class="text-gray-600"><?php echo date('d/m/Y', strtotime($patientInfo['date_of_birth'])); ?></span></div>
                                <div class="flex flex-col"><span class="text-gray-900 font-bold mb-1">Gender</span><span class="text-gray-600"><?php echo htmlspecialchars($patientInfo['gender']); ?></span></div>
                                <div class="flex flex-col"><span class="text-gray-900 font-bold mb-1">Address</span><span class="text-gray-600"><?php echo htmlspecialchars($patientInfo['address'] ?? 'N/A'); ?></span></div>
                                <div class="flex flex-col"><span class="text-gray-900 font-bold mb-1">Phone Number</span><span class="text-gray-600"><?php echo htmlspecialchars($patientInfo['phone_number']); ?></span></div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8">
                        <div class="section-title">Medical History</div>
                        <div class="space-y-6">
                            <div>
                                <h4 class="text-[15px] font-bold text-[#1e3a8a] mb-2">Previous Medical History</h4>
                                <?php if(empty($diseases)): ?>
                                    <p class="text-gray-500 text-sm">No</p>
                                <?php else: ?>
                                    <ul class="text-gray-700 text-sm space-y-1">
                                        <?php foreach($diseases as $d): ?>
                                            <li>+ <?php echo htmlspecialchars($d['condition_name']); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                            
                            <div>
                                <h4 class="text-[15px] font-bold text-[#1e3a8a] mb-2">Previous Surgeries</h4>
                                <?php if(empty($surgeries)): ?>
                                    <p class="text-blue-600 font-medium text-sm">No</p>
                                <?php else: ?>
                                    <ul class="text-gray-700 text-sm space-y-1">
                                        <?php foreach($surgeries as $s): ?>
                                            <li>+ <?php echo htmlspecialchars($s['condition_name']); ?> (<?php echo date('Y', strtotime($s['date_recorded'])); ?>)</li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>
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
                                        <th class="py-3 font-bold w-[40%]">Note <i class="fa-solid fa-sort text-gray-400 ml-1"></i></th>
                                    </tr>
                                </thead>
                                <tbody class="text-gray-600">
                                    <?php foreach($appointmentHistory as $h): ?>
                                    <tr class="border-b border-gray-50 align-top">
                                        <td class="py-4 font-medium"><?php echo date('H:i', strtotime($h['appointment_time'])); ?></td>
                                        <td class="py-4 font-medium"><?php echo date('d/m/Y', strtotime($h['appointment_date'])); ?></td>
                                        <td class="py-4">Dr. <?php echo htmlspecialchars($h['doc_name']); ?></td>
                                        <td class="py-4 leading-relaxed">
                                            <?php 
                                                $note = "";
                                                if (!empty($h['ai_prediction_label'])) {
                                                    $note .= "<strong class='text-blue-600'>AI Result:</strong> " . $h['ai_prediction_label'] . " (" . $h['ai_confidence_score'] . "%)<br>";
                                                }
                                                if (!empty($h['patient_notes'])) {
                                                    $note .= htmlspecialchars($h['patient_notes']);
                                                }
                                                echo $note ?: '<span class="italic text-gray-400">No notes</span>';
                                            ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-span-12 lg:col-span-5 space-y-6">
                    
                    <form method="POST">
                        <input type="hidden" name="appointment_id" value="<?php echo $latestAppt['appointment_id'] ?? ''; ?>">
                        
                        <div class="space-y-6">
                            <div class="blue-border-box">
                                <div class="blue-border-header">Current Diagnosis & Notes</div>
                                <div class="p-5 space-y-4">
                                    <div>
                                        <label class="block font-bold text-gray-800 text-[15px] mb-2">Diagnose</label>
                                        <textarea name="diagnose" class="w-full border-none p-0 text-sm text-gray-700 bg-transparent focus:ring-0 resize-none" rows="2" placeholder="Enter final diagnosis..."><?php echo htmlspecialchars($latestAppt['patient_notes'] ?? ''); ?></textarea>
                                    </div>
                                    <hr class="border-gray-100">
                                    <div>
                                        <label class="block font-bold text-gray-800 text-[15px] mb-2">Clinical Notes</label>
                                        <textarea name="clinical_notes" class="w-full border-none p-0 text-sm text-gray-700 bg-transparent focus:ring-0 resize-none" rows="3" placeholder="Enter clinical notes..."><?php echo htmlspecialchars($latestAppt['patient_symptoms_note'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="blue-border-box">
                                <div class="blue-border-header">Treatment Plan & Notes of Doctor</div>
                                <div class="p-5">
                                    <label class="block font-bold text-[#1e3a8a] text-[15px] mb-2">Treatment Plan</label>
                                    <textarea name="treatment_plan" class="w-full border-none p-0 text-sm text-gray-700 bg-transparent focus:ring-0 resize-none leading-relaxed" rows="5" placeholder="1. Medication...&#10;2. Instructions..."><?php echo htmlspecialchars($latestAppt['treatment_plan'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="flex gap-4 mt-6">
                            <button type="submit" name="update_record" class="flex-1 bg-[#3b82f6] hover:bg-blue-700 text-white font-semibold py-3.5 rounded-xl transition-colors shadow-sm">Save & Update Here</button>
                            <button type="button" class="w-32 bg-white border border-gray-200 hover:bg-gray-50 text-gray-600 font-semibold py-3.5 rounded-xl transition-colors">Cancel</button>
                        </div>
                    </form>

                    <div class="blue-border-box !border-red-400 mt-8 relative">
                        <div class="absolute inset-0 border-2 border-red-500 rounded-xl pointer-events-none"></div>
                        <div class="blue-border-header !bg-transparent !border-b-gray-100 !text-gray-800 relative z-10">Expert Commentary Area</div>
                        
                        <div class="p-5 relative z-10 flex flex-col h-[280px]">
                            <div class="flex-1 overflow-y-auto space-y-4 mb-4 pr-2">
                                <?php if(empty($expertComments)): ?>
                                    <p class="text-sm text-gray-400 italic text-center mt-4">No comments yet.</p>
                                <?php else: ?>
                                    <?php foreach($expertComments as $cmt): ?>
                                        <div>
                                            <p class="font-bold text-gray-900 text-sm mb-1">Dr. <?php echo htmlspecialchars($cmt['doc_name']); ?></p>
                                            <p class="text-sm text-gray-700 leading-relaxed bg-gray-50 p-3 rounded-lg border border-gray-100">
                                                <?php echo nl2br(htmlspecialchars($cmt['comment_content'])); ?>
                                            </p>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                            <form method="POST" class="mt-auto flex gap-2">
                                <input type="hidden" name="appointment_id" value="<?php echo $latestAppt['appointment_id'] ?? ''; ?>">
                                <input type="text" name="new_comment" required class="flex-1 border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-blue-500" placeholder="Add your new comment...">
                                <button type="submit" name="add_comment" class="bg-[#3b82f6] hover:bg-blue-700 text-white font-semibold px-6 py-2.5 rounded-full transition-colors shadow-sm">Send</button>
                            </form>
                        </div>
                    </div>

                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>