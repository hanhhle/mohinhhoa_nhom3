<?php
// ==========================================
// TÊN FILE: doc_medical_record.php
// CHỨC NĂNG: Xem hồ sơ bệnh án chi tiết dạng TIMELINE KÈM FORM EDIT
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
$expertCommentsByAppt = [];
$msg = "";

// 1. XỬ LÝ LƯU CẬP NHẬT BỆNH ÁN TỪ FORM TRONG TIMELINE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_record'])) {
    try {
        $apptId = $_POST['appointment_id'];
        $diagnose = $_POST['diagnose'];
        $notes = $_POST['clinical_notes'];
        $treatment = $_POST['treatment_plan'];

        $stmtUp = $pdo->prepare("UPDATE Appointments SET patient_notes = ?, patient_symptoms_note = ?, treatment_plan = ? WHERE appointment_id = ?");
        $stmtUp->execute([$diagnose, $notes, $treatment, $apptId]);
        $msg = "<div class='bg-emerald-50 text-emerald-600 p-4 rounded-xl mb-6 border border-emerald-200 text-sm font-medium flex items-center gap-2 shadow-sm'><i class='fa-solid fa-circle-check'></i> Bệnh án đã được cập nhật thành công!</div>";
    } catch (PDOException $e) { 
        $msg = "<div class='bg-red-50 text-red-600 p-4 rounded-xl mb-6 border border-red-200 text-sm font-medium'>Lỗi: " . $e->getMessage() . "</div>"; 
    }
}

// 2. XỬ LÝ THÊM COMMENT HỘI CHẨN (EXPERT COMMENT)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_comment'])) {
    try {
        $apptId = $_POST['appointment_id'];
        $commentContent = trim($_POST['new_comment']);
        if (!empty($commentContent)) {
            $stmtCmt = $pdo->prepare("INSERT INTO Expert_Comments (appointment_id, doctor_id, comment_content) VALUES (?, ?, ?)");
            $stmtCmt->execute([$apptId, $doctorId, $commentContent]);
            $msg = "<div class='bg-blue-50 text-blue-600 p-4 rounded-xl mb-6 border border-blue-200 text-sm font-medium flex items-center gap-2 shadow-sm'><i class='fa-solid fa-paper-plane'></i> Đã gửi bình luận hội chẩn!</div>";
        }
    } catch (PDOException $e) { 
        $msg = "<div class='bg-red-50 text-red-600 p-4 rounded-xl mb-6 border border-red-200 text-sm font-medium'>Lỗi: " . $e->getMessage() . "</div>"; 
    }
}

// 3. LẤY DỮ LIỆU TỪ DATABASE
if ($patientId) {
    try {
        $stmt = $pdo->prepare("SELECT u.full_name, u.avatar_url, pp.* FROM Users u JOIN Patient_Profiles pp ON u.user_id = pp.patient_id WHERE u.user_id = ?");
        $stmt->execute([$patientId]);
        $patientInfo = $stmt->fetch();

        $stmtMed = $pdo->prepare("SELECT * FROM Medical_History WHERE patient_id = ? ORDER BY date_recorded DESC");
        $stmtMed->execute([$patientId]);
        $allHistory = $stmtMed->fetchAll();
        foreach ($allHistory as $mh) {
            if ($mh['type'] == 'Disease') $diseases[] = $mh;
            elseif ($mh['type'] == 'Surgery') $surgeries[] = $mh;
        }

        // Lấy toàn bộ lịch sử khám
        $stmtHist = $pdo->prepare("SELECT a.*, u.full_name as doc_name FROM Appointments a JOIN Users u ON a.doctor_id = u.user_id WHERE a.patient_id = ? AND a.status IN ('Completed', 'In Progress') ORDER BY a.appointment_date DESC, a.appointment_time DESC");
        $stmtHist->execute([$patientId]);
        $appointmentHistory = $stmtHist->fetchAll();
        
        // Lấy Comment Hội chẩn nhóm theo appointment_id
        if (!empty($appointmentHistory)) {
            $apptIds = array_column($appointmentHistory, 'appointment_id');
            $inClause = implode(',', array_fill(0, count($apptIds), '?'));
            $stmtCmt = $pdo->prepare("SELECT ec.*, u.full_name as doc_name FROM Expert_Comments ec JOIN Users u ON ec.doctor_id = u.user_id WHERE ec.appointment_id IN ($inClause) ORDER BY ec.created_at ASC");
            $stmtCmt->execute($apptIds);
            $comments = $stmtCmt->fetchAll();
            foreach ($comments as $c) {
                $expertCommentsByAppt[$c['appointment_id']][] = $c;
            }
        }
    } catch (PDOException $e) { 
        $msg = "<div class='bg-red-50 text-red-600 p-4 rounded-xl mb-6'>DB Error: " . $e->getMessage() . "</div>"; 
    }
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
        
        .section-title { display: inline-block; font-size: 16px; font-weight: 700; color: #1f2937; padding-bottom: 8px; border-bottom: 3px solid #3b82f6; margin-bottom: 20px; }
        .blue-border-box { border: 1px solid #bfdbfe; border-radius: 12px; overflow: hidden; background: #ffffff; }
        .blue-border-header { background-color: #eff6ff; padding: 12px 20px; font-size: 14px; font-weight: 600; color: #1e3a8a; border-bottom: 1px solid #bfdbfe; }
    </style>
</head>
<body class="flex h-screen overflow-hidden text-gray-800">

    <aside class="w-64 bg-white border-r border-gray-100 flex flex-col h-full shadow-sm z-10">
        <div class="flex items-center gap-2 p-6 border-b">
            <i class="fa-solid fa-lungs text-3xl text-red-400"></i>
            <h1 class="text-xl font-semibold text-gray-700">Pneumo-<span class="text-blue-500">Care</span></h1>
        </div>

        <nav class="flex-1 px-4 py-6 space-y-1">
            <a href="doc_dashboard.php" class="flex items-center gap-4 px-4 py-3 text-gray-500 hover:bg-gray-50 rounded-lg transition-colors">
                <i class="fa-solid fa-gauge-high w-5"></i><span>Dashboard</span>
            </a>
            <a href="doc_patient_list.php" class="sidebar-active flex items-center gap-4 px-4 py-3 rounded-lg font-medium">
                <i class="fa-solid fa-users w-5"></i><span>Patient</span>
            </a>
            <a href="doc_appointments.php" class="flex items-center gap-4 px-4 py-3 text-gray-500 hover:bg-gray-50 rounded-lg transition-colors">
                <i class="fa-solid fa-calendar-check w-5"></i><span>Appointments</span>
            </a>
            <a href="doc_ai_workspace.php" class="flex items-center gap-4 px-4 py-3 text-gray-500 hover:bg-gray-50 rounded-lg transition-colors">
                <i class="fa-solid fa-brain w-5"></i><span>Diagnosis</span>
            </a>
            <a href="doc_messages.php" class="flex items-center gap-4 px-4 py-3 text-gray-500 hover:bg-gray-50 rounded-lg transition-colors">
                <i class="fa-solid fa-comment-dots w-5"></i><span>Messages</span>
            </a>
        </nav>

        <div class="p-6 border-t mt-auto">
            <a href="logout.php" class="flex items-center gap-4 text-gray-500 hover:text-red-500 transition-colors font-medium">
                <i class="fa-solid fa-right-from-bracket"></i><span>Logout</span>
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
                        <div class="flex gap-6 items-center">
                            <img src="<?php echo $patientInfo['avatar_url'] ?: 'img/default.png'; ?>" class="w-24 h-24 rounded-full object-cover border-4 border-gray-50 shadow-sm">
                            <div class="flex-1 grid grid-cols-2 gap-y-4 gap-x-4 text-sm">
                                <div class="flex flex-col"><span class="text-gray-900 font-bold mb-1">Name</span><span class="text-blue-600 font-semibold"><?php echo htmlspecialchars($patientInfo['full_name']); ?></span></div>
                                <div class="flex flex-col"><span class="text-gray-900 font-bold mb-1">Date of birth</span><span class="text-gray-600"><?php echo date('d/m/Y', strtotime($patientInfo['date_of_birth'])); ?></span></div>
                                <div class="flex flex-col"><span class="text-gray-900 font-bold mb-1">Gender</span><span class="text-gray-600"><?php echo htmlspecialchars($patientInfo['gender']); ?></span></div>
                                <div class="flex flex-col"><span class="text-gray-900 font-bold mb-1">Phone Number</span><span class="text-gray-600"><?php echo htmlspecialchars($patientInfo['phone_number']); ?></span></div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8">
                        <div class="section-title">Medical History</div>
                        <div class="space-y-6">
                            <div>
                                <h4 class="text-[14px] font-bold text-[#1e3a8a] mb-2">Previous Medical History</h4>
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
                                <h4 class="text-[14px] font-bold text-[#1e3a8a] mb-2">Previous Surgeries</h4>
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
                                        <th class="py-3 font-bold w-[15%]">Time</i></th>
                                        <th class="py-3 font-bold w-[20%]">Date</i></th>
                                        <th class="py-3 font-bold w-[25%]">Doctor</i></th>
                                        <th class="py-3 font-bold w-[40%]">Note</i></th>
                                    </tr>
                                </thead>
                                <tbody class="text-gray-600">
                                    <?php if(empty($appointmentHistory)): ?>
                                        <tr><td colspan="4" class="py-10 text-center text-gray-400 italic">No completed appointments found.</td></tr>
                                    <?php else: ?>
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
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>

                <div class="col-span-12 lg:col-span-5 space-y-6 h-[calc(100vh-140px)] overflow-y-auto no-scrollbar pb-10 pr-4">
                    
                    <div class="flex items-center justify-between sticky top-0 bg-[#f8fafc] pb-4 z-10">
                        <h3 class="text-xl font-bold text-[#003366]"><i class="fa-solid fa-clock-rotate-left mr-2 text-blue-500"></i> Clinical Timeline</h3>
                        <span class="text-xs font-bold text-blue-600 bg-blue-50 px-3 py-1 rounded-full border border-blue-200 shadow-sm"><i class="fa-solid fa-pen-to-square"></i> Editable</span>
                    </div>

                    <?php if(empty($appointmentHistory)): ?>
                        <div class="bg-white rounded-2xl p-10 text-center text-gray-400 italic shadow-sm border border-gray-100 mt-4 flex flex-col items-center">
                            <i class="fa-regular fa-folder-open text-4xl text-gray-200 mb-3"></i>
                            <p>Bệnh nhân này chưa có hồ sơ bệnh án.</p>
                        </div>
                    <?php else: ?>
                        <div class="relative border-l-2 border-blue-200 ml-4 space-y-10 mt-4">
                            <?php foreach($appointmentHistory as $index => $appt): ?>
                                <div class="relative pl-8">
                                    <div class="absolute -left-[11px] top-0 w-5 h-5 bg-blue-500 rounded-full border-4 border-white shadow-sm <?php echo $index === 0 ? 'ring-4 ring-blue-100' : ''; ?>"></div>
                                    
                                    <div class="flex items-center justify-between mb-4 bg-white px-5 py-3 rounded-xl shadow-sm border border-gray-100 w-full max-w-[400px]">
                                        <div>
                                            <span class="font-extrabold text-blue-600 text-sm"><?php echo date('d/m/Y', strtotime($appt['appointment_date'])); ?></span>
                                            <span class="text-gray-300 mx-2">|</span>
                                            <span class="text-xs font-semibold text-gray-500"><i class="fa-regular fa-clock mr-1"></i><?php echo date('H:i', strtotime($appt['appointment_time'])); ?></span>
                                        </div>
                                        <span class="bg-gray-100 text-gray-700 text-[10px] uppercase tracking-widest font-bold px-3 py-1 rounded-md">Dr. <?php echo htmlspecialchars($appt['doc_name']); ?></span>
                                    </div>

                                    <form method="POST" class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden mb-4">
                                        <input type="hidden" name="appointment_id" value="<?php echo $appt['appointment_id']; ?>">
                                        
                                        <div class="bg-gray-50 border-b border-gray-100 px-6 py-3 flex justify-between items-center">
                                            <span class="text-sm font-bold text-gray-800">Medical Record Details</span>
                                            <?php if (!empty($appt['ai_prediction_label'])): ?>
                                                <span class="text-[10px] font-bold px-2 py-1 rounded-md uppercase tracking-widest <?php echo $appt['ai_prediction_label'] == 'Positive' ? 'bg-red-100 text-red-600' : 'bg-green-100 text-green-600'; ?>">
                                                    <i class="fa-solid fa-robot mr-1"></i> AI: <?php echo $appt['ai_prediction_label']; ?> (<?php echo $appt['ai_confidence_score']; ?>%)
                                                </span>
                                            <?php endif; ?>
                                        </div>

                                        <div class="p-6 space-y-5">
                                            <div>
                                                <label class="block font-bold text-gray-800 text-[12px] uppercase tracking-wide mb-2">Diagnose</label>
                                                <textarea name="diagnose" class="w-full bg-gray-50 border border-gray-200 rounded-xl p-4 text-sm text-gray-700 focus:outline-none focus:border-blue-400 focus:bg-white transition-colors resize-none" rows="2" placeholder="Enter final diagnosis..."><?php echo htmlspecialchars($appt['patient_notes'] ?? ''); ?></textarea>
                                            </div>
                                            <div>
                                                <label class="block font-bold text-gray-800 text-[12px] uppercase tracking-wide mb-2">Clinical Notes</label>
                                                <textarea name="clinical_notes" class="w-full bg-gray-50 border border-gray-200 rounded-xl p-4 text-sm text-gray-700 focus:outline-none focus:border-blue-400 focus:bg-white transition-colors resize-none" rows="2" placeholder="Enter clinical notes..."><?php echo htmlspecialchars($appt['patient_symptoms_note'] ?? ''); ?></textarea>
                                            </div>
                                            <div>
                                                <label class="block font-bold text-[#1e3a8a] text-[12px] uppercase tracking-wide mb-2">Treatment Plan</label>
                                                <textarea name="treatment_plan" class="w-full bg-blue-50/30 border border-blue-100 rounded-xl p-4 text-sm text-gray-800 focus:outline-none focus:border-blue-400 focus:bg-white transition-colors resize-none leading-relaxed" rows="3" placeholder="1. Medication...&#10;2. Instructions..."><?php echo htmlspecialchars($appt['treatment_plan'] ?? ''); ?></textarea>
                                            </div>
                                        </div>
                                        
                                        <div class="bg-gray-50 px-6 py-4 border-t border-gray-100 flex justify-end">
                                            <button type="submit" name="update_record" class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-6 py-2 rounded-lg shadow-sm transition-colors text-[13px] flex items-center gap-2">
                                                <i class="fa-solid fa-floppy-disk"></i> Save Updates
                                            </button>
                                        </div>
                                    </form>

                                    <div class="bg-white border border-red-200 rounded-2xl shadow-sm overflow-hidden relative">
                                        <div class="absolute inset-0 border-2 border-red-100 rounded-2xl pointer-events-none"></div>
                                        <div class="bg-red-50 px-6 py-3 border-b border-red-100 flex justify-between items-center text-red-600 font-bold text-sm">
                                            <span>Expert Consultations</span>
                                            <i class="fa-solid fa-users-viewfinder"></i>
                                        </div>
                                        
                                        <div class="p-6">
                                            <div class="space-y-4 mb-5 max-h-[150px] overflow-y-auto pr-2 no-scrollbar">
                                                <?php if(empty($expertCommentsByAppt[$appt['appointment_id']])): ?>
                                                    <p class="text-[13px] text-gray-400 italic text-center">No comments yet.</p>
                                                <?php else: ?>
                                                    <?php foreach($expertCommentsByAppt[$appt['appointment_id']] as $cmt): ?>
                                                        <div class="bg-gray-50 border border-gray-100 rounded-xl p-3">
                                                            <div class="flex justify-between items-center mb-1">
                                                                <p class="font-bold text-gray-800 text-[13px]">Dr. <?php echo htmlspecialchars($cmt['doc_name']); ?></p>
                                                                <span class="text-[10px] text-gray-400 font-medium"><?php echo date('d/m, H:i', strtotime($cmt['created_at'])); ?></span>
                                                            </div>
                                                            <p class="text-[13px] text-gray-600 leading-relaxed"><?php echo nl2br(htmlspecialchars($cmt['comment_content'])); ?></p>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </div>

                                            <form method="POST" class="flex gap-2">
                                                <input type="hidden" name="appointment_id" value="<?php echo $appt['appointment_id']; ?>">
                                                <input type="text" name="new_comment" required class="flex-1 bg-gray-50 border border-gray-200 rounded-xl px-4 py-2 text-[13px] focus:outline-none focus:border-blue-400 focus:bg-white transition-colors" placeholder="Add comment...">
                                                <button type="submit" name="add_comment" class="bg-red-500 hover:bg-red-600 text-white font-bold px-5 py-2 rounded-xl transition-colors shadow-sm text-[13px]">Send</button>
                                            </form>
                                        </div>
                                    </div>
                                    
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>