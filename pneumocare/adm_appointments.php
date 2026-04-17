<?php
session_start();
require 'db.php';
ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL);

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') { 
    header("Location: login.php"); 
    exit(); 
}
$adminName = $_SESSION['name'];

// Fix đường dẫn ảnh Admin
$adminAvatar = (!empty($_SESSION['avatar'])) ? $_SESSION['avatar'] : 'img/default_admin.png';

$search = isset($_GET['search']) ? "%".$_GET['search']."%" : "%";
$filterDate = isset($_GET['date']) && !empty($_GET['date']) ? $_GET['date'] : null;
$appointments = [];
$msg = "";
$available_slots = ['09:00', '10:30', '13:30', '15:00'];

// ==========================================
// 1. XỬ LÝ HỦY LỊCH HẸN (CANCEL)
// ==========================================
if (isset($_GET['cancel_id'])) {
    $c_id = $_GET['cancel_id'];
    try {
        $stmtCancel = $pdo->prepare("UPDATE Appointments SET status = 'Cancelled' WHERE appointment_id = ?");
        $stmtCancel->execute([$c_id]);
        $msg = "<div class='bg-red-50 text-red-600 p-4 rounded-xl mb-6 border border-red-200 text-sm font-medium flex items-center gap-2'><i class='fa-solid fa-circle-xmark'></i> Appointment has been cancelled.</div>";
    } catch (PDOException $e) {
        $msg = "<div class='bg-red-50 text-red-600 p-4 rounded-xl mb-6 border border-red-200 text-sm font-medium'>Error: " . $e->getMessage() . "</div>";
    }
}

// ==========================================
// 2. XỬ LÝ CẬP NHẬT DỜI LỊCH (RESCHEDULE POST)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_reschedule'])) {
    $r_id = $_POST['reschedule_appt_id'];
    $n_date = $_POST['new_date'];
    $n_time = $_POST['new_time'];

    try {
        $stmtUp = $pdo->prepare("UPDATE Appointments SET appointment_date = ?, appointment_time = ? WHERE appointment_id = ?");
        $stmtUp->execute([$n_date, $n_time, $r_id]);
        header("Location: adm_appointments.php?success=rescheduled");
        exit();
    } catch (PDOException $e) {
        $msg = "<div class='bg-red-50 text-red-600 p-4 rounded-xl mb-6 border border-red-200 text-sm font-medium'>Error: " . $e->getMessage() . "</div>";
    }
}

if (isset($_GET['success']) && $_GET['success'] == 'rescheduled') {
    $msg = "<div class='bg-green-50 text-green-600 p-4 rounded-xl mb-6 border border-green-200 text-sm font-medium flex items-center gap-2 shadow-sm'><i class='fa-solid fa-circle-check'></i> Appointment successfully rescheduled!</div>";
}
if (isset($_GET['success']) && $_GET['success'] == 'created') {
    $msg = "<div class='bg-green-50 text-green-600 p-4 rounded-xl mb-6 border border-green-200 text-sm font-medium flex items-center gap-2 shadow-sm'><i class='fa-solid fa-circle-check'></i> New appointment created successfully!</div>";
}

// ==========================================
// 3. LẤY DỮ LIỆU MODAL RESCHEDULE
// ==========================================
$reschedule_id = isset($_GET['reschedule_id']) ? $_GET['reschedule_id'] : null;
$selected_date = isset($_GET['new_date']) ? $_GET['new_date'] : null;
$rescheduleData = null;
$booked_slots = [];

if ($reschedule_id) {
    try {
        $stmtRes = $pdo->prepare("
            SELECT a.*, u_d.full_name as doctor_name, u_p.full_name as patient_name 
            FROM Appointments a 
            JOIN Users u_d ON a.doctor_id = u_d.user_id 
            JOIN Users u_p ON a.patient_id = u_p.user_id
            WHERE a.appointment_id = ?
        ");
        $stmtRes->execute([$reschedule_id]);
        $rescheduleData = $stmtRes->fetch();

        if ($rescheduleData) {
            if (!$selected_date) $selected_date = $rescheduleData['appointment_date'];
            $stmtCheck = $pdo->prepare("SELECT appointment_time FROM Appointments WHERE doctor_id = ? AND appointment_date = ? AND status != 'Cancelled' AND appointment_id != ?");
            $stmtCheck->execute([$rescheduleData['doctor_id'], $selected_date, $reschedule_id]);
            while ($row = $stmtCheck->fetch()) { 
                $booked_slots[] = date('H:i', strtotime($row['appointment_time'])); 
            }
        }
    } catch (PDOException $e) {}
}

// ==========================================
// 4. XỬ LÝ TẠO LỊCH KHÁM MỚI (CÓ THỂ TẠO CẢ USER MỚI)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_appointment'])) {
    $patient_type = $_POST['patient_type'] ?? 'existing';
    $new_d_id = $_POST['doctor_id'];
    $new_date = $_POST['appt_date'];
    $new_time = $_POST['appt_time'];

    try {
        $pdo->beginTransaction(); // Bắt đầu Transaction
        $new_p_id = null;

        if ($patient_type === 'existing') {
            $new_p_id = $_POST['patient_id'];
            if (empty($new_p_id)) throw new Exception("Please select an existing patient.");
        } else {
            // Xử lý tạo Bệnh nhân mới
            $full_name = trim($_POST['new_full_name']);
            $phone = trim($_POST['new_phone']);
            $email = trim($_POST['new_email']);
            $dob = $_POST['new_dob'];
            $gender = $_POST['new_gender'];

            if (empty($full_name) || empty($phone) || empty($email) || empty($dob)) {
                throw new Exception("Please fill in all required fields for the new patient.");
            }

            // Kiểm tra Email trùng
            $stmtCheckMail = $pdo->prepare("SELECT user_id FROM Users WHERE email = ?");
            $stmtCheckMail->execute([$email]);
            if ($stmtCheckMail->rowCount() > 0) {
                throw new Exception("Email '$email' is already registered! Please use 'Existing Patient' tab.");
            }

            // Tạo User mới (Mật khẩu mặc định: 123456)
            $default_pw = password_hash('123456', PASSWORD_DEFAULT);
            $stmtUser = $pdo->prepare("INSERT INTO Users (full_name, email, password_hash, role, is_active) VALUES (?, ?, ?, 'Patient', 1)");
            $stmtUser->execute([$full_name, $email, $default_pw]);
            $new_p_id = $pdo->lastInsertId();

            // Tạo Patient Profile
            $stmtProf = $pdo->prepare("INSERT INTO Patient_Profiles (patient_id, date_of_birth, gender, phone_number) VALUES (?, ?, ?, ?)");
            $stmtProf->execute([$new_p_id, $dob, $gender, $phone]);
        }

        // Tạo Appointment
        $stmtNew = $pdo->prepare("INSERT INTO Appointments (patient_id, doctor_id, appointment_date, appointment_time, status, fee_status) VALUES (?, ?, ?, ?, 'Scheduled', 'Unpaid')");
        $stmtNew->execute([$new_p_id, $new_d_id, $new_date, $new_time]);

        $pdo->commit(); // Xác nhận Transaction
        header("Location: adm_appointments.php?success=created");
        exit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); } // Hủy nếu lỗi
        $msg = "<div class='bg-red-50 text-red-600 p-4 rounded-xl mb-6 border border-red-200 text-sm font-medium'><i class='fa-solid fa-circle-exclamation'></i> " . $e->getMessage() . "</div>";
    }
}

// Lấy Data cho Modal New Appointment
$is_new_appt = isset($_GET['new_appt']) ? true : false;
$pat_type = isset($_GET['pat_type']) ? $_GET['pat_type'] : 'existing';
$sel_pat = isset($_GET['sel_pat']) ? $_GET['sel_pat'] : '';
$sel_doc = isset($_GET['sel_doc']) ? $_GET['sel_doc'] : '';
$sel_date = isset($_GET['sel_date']) ? $_GET['sel_date'] : date('Y-m-d');
$new_booked_slots = [];
$all_patients = [];
$all_doctors = [];

if ($is_new_appt) {
    try {
        $all_patients = $pdo->query("SELECT user_id, full_name, email FROM Users WHERE role = 'Patient' AND is_active = 1 ORDER BY full_name ASC")->fetchAll();
        $all_doctors = $pdo->query("SELECT u.user_id, u.full_name, dp.speciality FROM Users u JOIN Doctor_Profiles dp ON u.user_id = dp.doctor_id WHERE u.role = 'Doctor' ORDER BY u.full_name ASC")->fetchAll();

        if ($sel_doc && $sel_date) {
            $stmtCheckNew = $pdo->prepare("SELECT appointment_time FROM Appointments WHERE doctor_id = ? AND appointment_date = ? AND status != 'Cancelled'");
            $stmtCheckNew->execute([$sel_doc, $sel_date]);
            while ($row = $stmtCheckNew->fetch()) { 
                $new_booked_slots[] = date('H:i', strtotime($row['appointment_time'])); 
            }
        }
    } catch (PDOException $e) {}
}

// ==========================================
// 5. LẤY DANH SÁCH LỊCH HẸN
// ==========================================
try {
    $sql = "SELECT a.*, u_p.full_name as p_name, u_p.avatar_url as p_avatar, u_d.full_name as d_name, pp.date_of_birth 
            FROM Appointments a 
            JOIN Users u_p ON a.patient_id = u_p.user_id 
            JOIN Patient_Profiles pp ON u_p.user_id = pp.patient_id 
            JOIN Users u_d ON a.doctor_id = u_d.user_id 
            WHERE a.status = 'Scheduled' AND u_p.full_name LIKE ?";
    $params = [$search];
    
    if ($filterDate) { 
        $sql .= " AND a.appointment_date = ?"; 
        $params[] = $filterDate; 
    }
    $sql .= " ORDER BY a.appointment_date ASC, a.appointment_time ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $appointments = $stmt->fetchAll();
} catch (PDOException $e) {
    die("<div style='color:red; padding:20px; background:#fee2e2; border:1px solid #ef4444; margin:20px;'><b>Lỗi DB:</b> " . $e->getMessage() . "</div>");
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
    <title>Pneumo-Care | Manage Appointments</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background-color: #f4f7fa; color: #1f2937; }
        .layout { display: flex; min-height: 100vh; overflow: hidden; }
        .sidebar { width: 260px; background: #ffffff; border-right: 1px solid #e5e7eb; display: flex; flex-direction: column; min-height: 100vh; flex-shrink: 0; z-index: 10; }
        .sidebar-active { background-color: #eff6ff; color: #2563eb; border-left: 4px solid #2563eb; font-weight: 600; }
        .main-content { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
        .topbar-wrapper { padding: 32px 40px 0 40px; }
        .topbar { height: 72px; background: #ffffff; border: 1px solid #f3f4f6; display: flex; align-items: center; justify-content: space-between; padding: 0 24px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 24px; }
        .topbar h1 { font-size: 22px; font-weight: 600; color: #1f2937; margin: 0; }
        .content-area { padding: 0 40px 40px 40px; flex: 1; overflow-y: auto; }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    </style>
</head>
<body class="flex h-screen overflow-hidden text-gray-800 relative">
<div class="flex w-full h-full relative">

    <aside class="w-64 bg-white border-r border-gray-200 flex flex-col h-full flex-shrink-0 z-10 shadow-sm">
        <div class="h-20 flex items-center px-6 border-b border-gray-100">
            <i class="fa-solid fa-lungs text-red-400 text-2xl mr-2"></i>
            <span class="text-xl font-semibold">Pneumo<span class="text-blue-500">-Care</span></span>
        </div>

        <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto">
            <a href="adm_dashboard.php" class="flex items-center gap-4 px-4 py-3 text-gray-500 hover:bg-gray-50 hover:text-gray-800 rounded-xl transition-colors font-medium">
                <i class="fa-solid fa-gauge-high w-5 text-center text-xl"></i><span>Dashboard</span>
            </a>
            <a href="adm_patients.php" class="flex items-center gap-4 px-4 py-3 text-gray-500 hover:bg-gray-50 hover:text-gray-800 rounded-xl transition-colors font-medium">
                <i class="fa-solid fa-user-group w-5 text-center text-xl"></i><span>Patients</span>
            </a>
            <a href="adm_appointments.php" class="sidebar-active flex items-center gap-4 px-4 py-3 rounded-xl font-semibold transition-colors">
                <i class="fa-solid fa-file-lines w-5 text-center text-xl"></i><span>Appointments</span>
            </a>
            <a href="adm_doctors.php" class="flex items-center gap-4 px-4 py-3 text-gray-500 hover:bg-gray-50 hover:text-gray-800 rounded-xl transition-colors font-medium">
                <i class="fa-solid fa-user-doctor w-5 text-center text-xl"></i><span>Doctors</span>
            </a>
            <a href="adm_messages.php" class="flex items-center gap-4 px-4 py-3 text-gray-500 hover:bg-gray-50 hover:text-gray-800 rounded-xl transition-colors font-medium">
                <i class="fa-solid fa-message w-5 text-center text-xl"></i><span>Messages</span>
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
                <h1>Manage Appointments</h1>
                <div class="flex items-center gap-6">
                    <div class="flex items-center gap-3 cursor-pointer">
                        <div class="text-right hidden sm:block">
                            <p class="text-sm font-semibold text-gray-800" style="line-height: 1.2;"><?php echo htmlspecialchars($adminName); ?></p>
                            <p class="text-xs text-gray-500 font-medium">Administrator</p>
                        </div>
                        <img src="<?php echo $adminAvatar; ?>" class="w-10 h-10 rounded-full object-cover border border-gray-200 shadow-sm" alt="Admin">
                    </div>
                </div>
            </header>
        </div>

        <div class="content-area max-w-7xl mx-auto w-full">
            <?php echo $msg; ?>
            
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 flex flex-col min-h-full overflow-hidden">
                <div class="px-8 py-6 border-b border-gray-100 flex justify-between items-center bg-gray-50/30">
                    <div class="flex gap-8">
                        <a href="adm_appointments.php" class="pb-2 text-[13px] font-bold text-blue-600 border-b-2 border-blue-600 uppercase tracking-widest transition-colors">NEW APPOINTMENTS</a>
                        <a href="adm_appointments_completed.php" class="pb-2 text-[13px] font-bold text-gray-400 hover:text-blue-500 uppercase tracking-widest transition-colors">COMPLETED APPOINTMENTS</a>
                    </div>
                    <button onclick="location.href='?new_appt=1'" class="bg-blue-600 text-white px-5 py-2.5 rounded-xl text-xs font-bold hover:bg-blue-700 transition-all shadow-sm uppercase tracking-wide flex items-center gap-2">
                        <i class="fa-solid fa-plus"></i> New Appointment
                    </button>
                </div>

                <div class="px-8 py-5 border-b border-gray-100 bg-white flex items-center gap-4">
                    <form method="GET" class="flex space-x-4 w-full">
                        <div class="relative w-72">
                            <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                            <input type="text" name="search" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>" placeholder="Search patient name..." class="bg-gray-50 w-full pl-10 pr-4 py-2.5 rounded-xl text-sm font-medium focus:outline-none border border-gray-200 focus:border-blue-400 transition-colors shadow-sm">
                        </div>
                        <div class="relative w-56">
                            <input type="date" name="date" value="<?php echo htmlspecialchars($filterDate ?? ''); ?>" class="border border-gray-200 text-gray-500 font-medium bg-gray-50 w-full px-4 py-2.5 rounded-xl text-sm focus:outline-none focus:border-blue-400 transition-colors shadow-sm">
                        </div>
                        <button type="submit" class="bg-blue-50 text-blue-600 border border-blue-100 px-8 py-2.5 rounded-xl text-sm font-bold hover:bg-blue-600 hover:text-white transition-colors shadow-sm">Filter</button>
                    </form>
                </div>

                <div class="flex-1 overflow-auto px-8 pb-4">
                    <table class="w-full text-left text-sm mt-4">
                        <thead class="text-gray-400 border-b border-gray-100 uppercase text-[11px] tracking-widest">
                            <tr>
                                <th class="py-4 font-semibold w-[15%]">Time</th>
                                <th class="py-4 font-semibold w-[15%]">Date</th>
                                <th class="py-4 font-semibold w-[25%]">Patient Name</th>
                                <th class="py-4 font-semibold w-[10%] text-center">Age</th>
                                <th class="py-4 font-semibold w-[20%]">Doctor</th>
                                <th class="py-4 font-semibold w-[15%] text-right pr-4">Action</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-600">
                            <?php if (empty($appointments)): ?>
                                <tr><td colspan="6" class="py-16 text-center text-gray-400 italic">Không tìm thấy lịch hẹn nào.</td></tr>
                            <?php else: ?>
                                <?php foreach ($appointments as $app): ?>
                                    <?php 
                                        $timeFormatted = date("h:i A", strtotime($app['appointment_time']));
                                        $dateFormatted = date("d/m/Y", strtotime($app['appointment_date']));
                                        $age = calculateAge($app['date_of_birth']);
                                        $nameParts = explode(' ', trim($app['p_name']));
                                        $initials = strtoupper(substr($nameParts[0], 0, 1));
                                        if (count($nameParts) > 1) { $initials .= strtoupper(substr(end($nameParts), 0, 1)); }
                                    ?>
                                    <tr class="border-b border-gray-50 hover:bg-blue-50/20 transition-colors">
                                        <td class="py-4 text-blue-600 font-bold text-sm"><?php echo $timeFormatted; ?></td>
                                        <td class="py-4 text-gray-500 font-medium text-sm"><?php echo $dateFormatted; ?></td>
                                        <td class="py-4">
                                            <div class="flex items-center gap-3">
                                                <div class="w-8 h-8 rounded-full bg-[#c0ca33] text-white flex items-center justify-center text-xs font-bold shadow-sm">
                                                    <?php echo $initials; ?>
                                                </div>
                                                <span class="text-gray-800 font-medium text-sm"><?php echo htmlspecialchars($app['p_name']); ?></span>
                                            </div>
                                        </td>
                                        <td class="py-4 text-gray-500 text-sm text-center font-medium"><?php echo $age; ?></td>
                                        <td class="py-4 text-gray-600 text-sm font-medium">Dr. <?php echo htmlspecialchars($app['d_name']); ?> </td>
                                        <td class="py-4 text-right pr-4">
                                            <div class="flex items-center justify-end gap-4">
                                                <a href="?reschedule_id=<?php echo $app['appointment_id']; ?>&search=<?php echo urlencode($_GET['search'] ?? ''); ?>&date=<?php echo urlencode($_GET['date'] ?? ''); ?>" class="text-blue-500 text-[11px] font-extrabold uppercase tracking-widest hover:text-blue-700 transition-colors">RESCHEDULE</a>
                                                <a href="?cancel_id=<?php echo $app['appointment_id']; ?>&search=<?php echo urlencode($_GET['search'] ?? ''); ?>&date=<?php echo urlencode($_GET['date'] ?? ''); ?>" onclick="return confirm('Bạn có chắc chắn muốn hủy lịch hẹn này không?')" class="w-8 h-8 flex items-center justify-center bg-red-50 text-red-500 border border-red-100 rounded-lg hover:bg-red-500 hover:text-white transition-colors shadow-sm" title="Cancel Appointment">
                                                    <i class="fa-solid fa-xmark"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <?php if($rescheduleData): ?>
    <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm flex items-center justify-center z-50">
        <div class="bg-white rounded-2xl shadow-2xl w-[500px] overflow-hidden">
            <div class="bg-blue-600 px-6 py-4 flex items-center justify-between text-white">
                <h3 class="font-semibold text-lg">Reschedule Appointment</h3>
                <a href="adm_appointments.php?search=<?php echo urlencode($_GET['search'] ?? ''); ?>&date=<?php echo urlencode($_GET['date'] ?? ''); ?>" class="text-white/80 hover:text-white transition-colors"><i class="fa-solid fa-xmark text-xl"></i></a>
            </div>
            
            <form method="POST" class="p-8">
                <input type="hidden" name="reschedule_appt_id" value="<?php echo $rescheduleData['appointment_id']; ?>">
                
                <div class="mb-6 bg-blue-50 border border-blue-100 rounded-xl p-4 flex justify-between items-center">
                    <div>
                        <p class="text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1">Patient</p>
                        <p class="font-bold text-gray-800 text-sm"><?php echo htmlspecialchars($rescheduleData['patient_name']); ?></p>
                    </div>
                    <div class="text-right">
                        <p class="text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1">Doctor</p>
                        <p class="font-bold text-[#003366] text-sm">Dr. <?php echo htmlspecialchars($rescheduleData['doctor_name']); ?></p>
                    </div>
                </div>

                <div class="mb-6">
                    <label class="block text-[11px] font-bold text-gray-600 mb-2 uppercase tracking-widest">1. Pick New Date</label>
                    <input type="date" name="new_date" required min="<?php echo date('Y-m-d'); ?>" 
                           value="<?php echo htmlspecialchars($selected_date); ?>"
                           onchange="window.location.href='?reschedule_id=<?php echo $reschedule_id; ?>&search=<?php echo urlencode($_GET['search'] ?? ''); ?>&date=<?php echo urlencode($_GET['date'] ?? ''); ?>&new_date=' + this.value"
                           class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:border-blue-500 outline-none bg-gray-50 font-medium transition-all">
                </div>

                <div class="mb-8">
                    <label class="block text-[11px] font-bold text-gray-600 mb-3 uppercase tracking-widest">2. Select Available Time</label>
                    <div class="grid grid-cols-2 gap-3">
                        <?php foreach($available_slots as $time): ?>
                            <?php 
                            $is_booked = in_array($time, $booked_slots); 
                            $is_current = (date('H:i', strtotime($rescheduleData['appointment_time'])) == $time && $selected_date == $rescheduleData['appointment_date']);
                            ?>
                            <label class="relative cursor-pointer">
                                <input type="radio" name="new_time" value="<?php echo $time; ?>:00" class="peer hidden" <?php echo $is_booked ? 'disabled' : ''; ?> <?php echo $is_current ? 'checked' : ''; ?> required>
                                <div class="border border-gray-200 rounded-xl py-3 text-center text-sm font-semibold transition-all <?php if($is_booked): ?> bg-gray-100 text-gray-400 cursor-not-allowed opacity-60 <?php else: ?> bg-white text-gray-700 hover:border-blue-300 peer-checked:bg-blue-600 peer-checked:border-blue-600 peer-checked:text-white shadow-sm <?php endif; ?>">
                                    <?php if($is_booked): ?><i class="fa-solid fa-lock text-[10px] mr-1"></i><?php endif; ?>
                                    <?php echo date('h:i A', strtotime($time)); ?>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="flex gap-3 pt-4 border-t border-gray-100">
                    <a href="adm_appointments.php?search=<?php echo urlencode($_GET['search'] ?? ''); ?>&date=<?php echo urlencode($_GET['date'] ?? ''); ?>" class="flex-1 text-center py-3 border border-gray-200 rounded-xl text-gray-500 font-bold hover:bg-gray-50 transition-colors uppercase text-xs">Cancel</a>
                    <button type="submit" name="confirm_reschedule" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-3 rounded-xl font-bold transition-all shadow-md uppercase text-xs">Confirm Change</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <?php if($is_new_appt): ?>
    <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm flex items-center justify-center z-50">
        <div class="bg-white rounded-2xl shadow-2xl w-[500px] overflow-hidden flex flex-col max-h-[90vh]">
            <div class="bg-blue-600 px-6 py-4 flex items-center justify-between text-white flex-shrink-0">
                <h3 class="font-semibold text-lg">Create New Appointment</h3>
                <a href="adm_appointments.php" class="text-white/80 hover:text-white transition-colors"><i class="fa-solid fa-xmark text-xl"></i></a>
            </div>
            
            <form method="POST" class="p-6 overflow-y-auto">
                <div class="flex gap-3 mb-5">
                    <div class="flex-1">
                        <label class="block text-[11px] font-bold text-gray-600 mb-1.5 uppercase tracking-widest">1. Doctor</label>
                        <select name="doctor_id" id="sel_doc" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:border-blue-500 outline-none bg-white font-medium transition-all" required onchange="reloadNewApptModal()">
                            <option value="">-- Choose Doctor --</option>
                            <?php foreach($all_doctors as $d): ?>
                                <option value="<?php echo $d['user_id']; ?>" <?php echo $sel_doc == $d['user_id'] ? 'selected' : ''; ?>>Dr. <?php echo htmlspecialchars($d['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="flex-1">
                        <label class="block text-[11px] font-bold text-gray-600 mb-1.5 uppercase tracking-widest">2. Date</label>
                        <input type="date" name="appt_date" id="sel_date" required min="<?php echo date('Y-m-d'); ?>" value="<?php echo htmlspecialchars($sel_date); ?>" onchange="reloadNewApptModal()" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:border-blue-500 outline-none bg-white font-medium transition-all">
                    </div>
                </div>

                <div class="mb-6">
                    <label class="block text-[11px] font-bold text-gray-600 mb-2 uppercase tracking-widest">3. Available Time</label>
                    <?php if(!$sel_doc): ?>
                        <div class="text-xs text-gray-400 italic bg-gray-50 p-3 rounded-xl border border-dashed border-gray-200 text-center">Select a doctor to view slots.</div>
                    <?php else: ?>
                        <div class="grid grid-cols-4 gap-2">
                            <?php foreach($available_slots as $time): ?>
                                <?php $is_booked = in_array($time, $new_booked_slots); ?>
                                <label class="relative cursor-pointer">
                                    <input type="radio" name="appt_time" value="<?php echo $time; ?>:00" class="peer hidden" <?php echo $is_booked ? 'disabled' : ''; ?> required>
                                    <div class="border border-gray-200 rounded-lg py-2 text-center text-xs font-semibold transition-all <?php if($is_booked): ?> bg-gray-100 text-gray-400 cursor-not-allowed opacity-60 <?php else: ?> bg-white text-gray-700 hover:border-blue-300 peer-checked:bg-blue-600 peer-checked:border-blue-600 peer-checked:text-white shadow-sm <?php endif; ?>">
                                        <?php if($is_booked): ?><i class="fa-solid fa-lock text-[10px] mr-1"></i><?php endif; ?> <?php echo date('h:i A', strtotime($time)); ?>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <hr class="border-gray-100 mb-5">

                <label class="block text-[11px] font-bold text-gray-600 mb-2 uppercase tracking-widest">4. Patient Profile</label>
                <div class="mb-4 flex gap-2 bg-gray-50 p-1 rounded-lg border border-gray-200">
                    <label class="flex-1 text-center cursor-pointer">
                        <input type="radio" name="patient_type" value="existing" class="peer hidden" <?php echo $pat_type == 'existing' ? 'checked' : ''; ?> onchange="togglePatientForm()">
                        <div class="py-2 rounded-md text-[11px] font-bold uppercase tracking-widest peer-checked:bg-white peer-checked:text-blue-600 peer-checked:shadow-sm text-gray-500 transition-all">Existing Patient</div>
                    </label>
                    <label class="flex-1 text-center cursor-pointer">
                        <input type="radio" name="patient_type" value="new" class="peer hidden" <?php echo $pat_type == 'new' ? 'checked' : ''; ?> onchange="togglePatientForm()">
                        <div class="py-2 rounded-md text-[11px] font-bold uppercase tracking-widest peer-checked:bg-white peer-checked:text-blue-600 peer-checked:shadow-sm text-gray-500 transition-all">Create New</div>
                    </label>
                </div>

                <div id="existing_patient_div" style="<?php echo $pat_type == 'existing' ? '' : 'display:none;'; ?>">
                    <select name="patient_id" id="sel_pat" class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:border-blue-500 outline-none bg-white font-medium transition-all">
                        <option value="">-- Choose Existing Patient --</option>
                        <?php foreach($all_patients as $p): ?>
                            <option value="<?php echo $p['user_id']; ?>" <?php echo $sel_pat == $p['user_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($p['full_name']); ?> (<?php echo htmlspecialchars($p['email']); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="new_patient_div" class="space-y-3 p-4 bg-blue-50/50 border border-blue-100 rounded-xl" style="<?php echo $pat_type == 'new' ? '' : 'display:none;'; ?>">
                    <div>
                        <label class="block text-[10px] font-bold text-blue-800 mb-1 uppercase tracking-widest">Full Name</label>
                        <input type="text" name="new_full_name" class="w-full border border-blue-200 rounded-lg px-3 py-2 text-sm outline-none bg-white">
                    </div>
                    <div class="flex gap-3">
                        <div class="flex-1">
                            <label class="block text-[10px] font-bold text-blue-800 mb-1 uppercase tracking-widest">Phone</label>
                            <input type="text" name="new_phone" class="w-full border border-blue-200 rounded-lg px-3 py-2 text-sm outline-none bg-white">
                        </div>
                        <div class="flex-1">
                            <label class="block text-[10px] font-bold text-blue-800 mb-1 uppercase tracking-widest">Email (Login)</label>
                            <input type="email" name="new_email" class="w-full border border-blue-200 rounded-lg px-3 py-2 text-sm outline-none bg-white">
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <div class="flex-1">
                            <label class="block text-[10px] font-bold text-blue-800 mb-1 uppercase tracking-widest">DOB</label>
                            <input type="date" name="new_dob" class="w-full border border-blue-200 rounded-lg px-3 py-2 text-sm outline-none bg-white max-w-[150px]">
                        </div>
                        <div class="flex-1">
                            <label class="block text-[10px] font-bold text-blue-800 mb-1 uppercase tracking-widest">Gender</label>
                            <select name="new_gender" class="w-full border border-blue-200 rounded-lg px-3 py-2 text-sm outline-none bg-white">
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                    </div>
                    <p class="text-[10px] text-blue-600 italic mt-1"><i class="fa-solid fa-circle-info"></i> Default password is <strong>123456</strong>.</p>
                </div>

                <div class="flex gap-3 pt-5 mt-5 border-t border-gray-100 flex-shrink-0">
                    <a href="adm_appointments.php" class="flex-1 text-center py-3 border border-gray-200 rounded-xl text-gray-500 font-bold hover:bg-gray-50 transition-colors uppercase text-xs">Cancel</a>
                    <button type="submit" name="create_appointment" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-3 rounded-xl font-bold transition-all shadow-md uppercase text-xs" <?php echo !$sel_doc ? 'disabled style="opacity:0.5; cursor:not-allowed;"' : ''; ?>>Create Appointment</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function reloadNewApptModal() {
            var patType = document.querySelector('input[name="patient_type"]:checked').value;
            var pat = document.getElementById('sel_pat').value;
            var doc = document.getElementById('sel_doc').value;
            var date = document.getElementById('sel_date').value;
            window.location.href = '?new_appt=1&pat_type=' + patType + '&sel_pat=' + pat + '&sel_doc=' + doc + '&sel_date=' + date;
        }

        function togglePatientForm() {
            var patType = document.querySelector('input[name="patient_type"]:checked').value;
            if(patType === 'existing') {
                document.getElementById('existing_patient_div').style.display = 'block';
                document.getElementById('new_patient_div').style.display = 'none';
            } else {
                document.getElementById('existing_patient_div').style.display = 'none';
                document.getElementById('new_patient_div').style.display = 'block';
            }
        }
    </script>
    <?php endif; ?>
    
</div>
</body>
</html>