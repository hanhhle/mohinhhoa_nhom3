<?php
// ==========================================
// TÊN FILE: pat_appointments.php
// CHỨC NĂNG: Quản lý lịch khám (Đặt mới, Dời lịch, Hủy lịch có điều kiện 2 tiếng, Thanh toán)
// ==========================================
session_start();
require 'db.php';
ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL);

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Patient') { header("Location: login.php"); exit(); }
$patientId = $_SESSION['user_id'];
$patientName = $_SESSION['name'];
$patientAvatar = (!empty($_SESSION['avatar']) && $_SESSION['avatar'] != 'default.png') ? $_SESSION['avatar'] : 'img/default_patient.png';

$appointments = [];
$rescheduleData = null;
$payData = null; 
$booked_slots = [];
$msg = "";

// 1. LẤY THÔNG TIN ĐỂ RESCHEDULE HOẶC PAYMENT
$reschedule_id = isset($_GET['reschedule_id']) ? $_GET['reschedule_id'] : null;
$selected_date = isset($_GET['new_date']) ? $_GET['new_date'] : null;
$pay_id = isset($_GET['pay_id']) ? $_GET['pay_id'] : null;

try {
    // ==========================================
    // 2. XỬ LÝ HỦY LỊCH KHÁM (CANCEL) VỚI ĐIỀU KIỆN TRƯỚC 2 TIẾNG
    // ==========================================
    if (isset($_GET['cancel_id'])) {
        $c_id = $_GET['cancel_id'];
        
        // Bước 1: Lấy thông tin thời gian của lịch hẹn cần hủy
        $stmtCheckTime = $pdo->prepare("SELECT appointment_date, appointment_time FROM Appointments WHERE appointment_id = ? AND patient_id = ? AND status = 'Scheduled'");
        $stmtCheckTime->execute([$c_id, $patientId]);
        $apptData = $stmtCheckTime->fetch();

        if ($apptData) {
            // Bước 2: Tính toán khoảng cách thời gian
            date_default_timezone_set('Asia/Ho_Chi_Minh'); // Set timezone chuẩn
            $now = new DateTime();
            $apptDateTime = new DateTime($apptData['appointment_date'] . ' ' . $apptData['appointment_time']);
            
            // Tính số phút chênh lệch
            $intervalMinutes = ($apptDateTime->getTimestamp() - $now->getTimestamp()) / 60;

            if ($intervalMinutes >= 120) {
                // Đủ điều kiện hủy (Trước 2 tiếng)
                $stmtCancel = $pdo->prepare("UPDATE Appointments SET status = 'Cancelled' WHERE appointment_id = ? AND patient_id = ?");
                $stmtCancel->execute([$c_id, $patientId]);
                $msg = "<div class='bg-emerald-50 text-emerald-600 p-4 rounded-xl mb-6 border border-emerald-200 text-sm font-medium flex items-center gap-2 shadow-sm'><i class='fa-solid fa-circle-check'></i> The appointment has been successfully canceled.</div>";
            } else {
                // Vi phạm điều kiện
                $msg = "<div class='bg-red-50 text-red-600 p-4 rounded-xl mb-6 border border-red-200 text-sm font-medium flex items-center gap-2 shadow-sm'><i class='fa-solid fa-circle-exclamation'></i> <b>Cannot cancel appointment!</b> The time until the appointment is less than 2 hours. Please contact the reception directly.</div>";
            }
        }
    }

    // 3. XỬ LÝ CẬP NHẬT RESCHEDULE (POST)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_reschedule'])) {
        $r_id = $_POST['reschedule_appt_id'];
        $n_date = $_POST['new_date'];
        $n_time = $_POST['new_time'];

        $stmtUp = $pdo->prepare("UPDATE Appointments SET appointment_date = ?, appointment_time = ? WHERE appointment_id = ? AND patient_id = ?");
        $stmtUp->execute([$n_date, $n_time, $r_id, $patientId]);
        $msg = "<div class='bg-green-50 text-green-600 p-4 rounded-xl mb-6 border border-green-200 text-sm font-medium flex items-center gap-2 shadow-sm'><i class='fa-solid fa-circle-check'></i> Appointment successfully rescheduled!</div>";
        $reschedule_id = null;
    }

    // XỬ LÝ XÁC NHẬN CHỌN "TIỀN MẶT"
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_cash'])) {
        $p_id = $_POST['payment_appt_id'];
        // Đánh dấu là sẽ trả tiền mặt (Giữ nguyên Unpaid để lễ tân thu tiền lúc đến)
        $stmtCash = $pdo->prepare("UPDATE Appointments SET payment_method = 'Tiền mặt' WHERE appointment_id = ? AND patient_id = ?");
        $stmtCash->execute([$p_id, $patientId]);
        $msg = "<div class='bg-blue-50 text-blue-600 p-4 rounded-xl mb-6 border border-blue-200 text-sm font-medium flex items-center gap-2 shadow-sm'><i class='fa-solid fa-circle-info'></i> Payment method set to Cash. Please pay at the clinic.</div>";
        $pay_id = null;
    }

    // 4. LẤY DỮ LIỆU ĐỂ HIỂN THỊ MODAL RESCHEDULE
    if ($reschedule_id) {
        $stmtRes = $pdo->prepare("SELECT a.*, u.full_name as doctor_name FROM Appointments a JOIN Users u ON a.doctor_id = u.user_id WHERE a.appointment_id = ? AND a.patient_id = ?");
        $stmtRes->execute([$reschedule_id, $patientId]);
        $rescheduleData = $stmtRes->fetch();

        if ($rescheduleData) {
            if (!$selected_date) $selected_date = $rescheduleData['appointment_date'];
            // Lấy giờ bận của bác sĩ
            $stmtCheck = $pdo->prepare("SELECT appointment_time FROM Appointments WHERE doctor_id = ? AND appointment_date = ? AND status != 'Cancelled' AND appointment_id != ?");
            $stmtCheck->execute([$rescheduleData['doctor_id'], $selected_date, $reschedule_id]);
            while ($row = $stmtCheck->fetch()) { $booked_slots[] = date('H:i', strtotime($row['appointment_time'])); }
        }
    }

    // LẤY DỮ LIỆU ĐỂ HIỂN THỊ MODAL THANH TOÁN
    if ($pay_id) {
        $stmtPay = $pdo->prepare("SELECT a.*, u.full_name as doctor_name, dp.consultation_fee FROM Appointments a JOIN Users u ON a.doctor_id = u.user_id JOIN Doctor_Profiles dp ON u.user_id = dp.doctor_id WHERE a.appointment_id = ? AND a.patient_id = ?");
        $stmtPay->execute([$pay_id, $patientId]);
        $payData = $stmtPay->fetch();
    }

    // 5. LẤY DANH SÁCH LỊCH KHÁM ĐANG HOẠT ĐỘNG
    $stmt = $pdo->prepare("
        SELECT a.*, u_d.full_name as doctor_name 
        FROM Appointments a 
        JOIN Users u_d ON a.doctor_id = u_d.user_id 
        WHERE a.patient_id = ? AND a.status IN ('Scheduled', 'In Progress') 
        ORDER BY a.appointment_date ASC, a.appointment_time ASC
    ");
    $stmt->execute([$patientId]);
    $appointments = $stmt->fetchAll();

} catch (PDOException $e) { $msg = "Error: " . $e->getMessage(); }

$available_slots = ['09:00', '10:30', '13:30', '15:00'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pneumo-Care | Appointments</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; color: #1f2937; }
        
        .main-content { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
        .topbar-wrapper { padding: 32px 40px 0 40px; }
        .topbar { height: 72px; background: #ffffff; border: 1px solid #f3f4f6; display: flex; align-items: center; justify-content: space-between; padding: 0 24px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 24px; }
        .topbar h1 { font-size: 22px; font-weight: 600; color: #1f2937; margin: 0; }
        .content-area { padding: 0 40px 40px 40px; flex: 1; overflow-y: auto; }
        
        .table-card { background: #fff; border-radius: 14px; padding: 24px 32px; border: 1px solid #f3f4f6; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .tab-link { padding: 12px 24px; font-size: 14px; font-weight: 600; color: #9ca3af; text-decoration: none; border-bottom: 2px solid transparent; margin-bottom: -2px; transition: 0.2s; }
        .tab-link.active { color: #3b82f6; border-bottom-color: #3b82f6; }
        
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { font-size: 12px; color: #6b7280; font-weight: 600; padding: 14px 16px; border-bottom: 1px solid #e5e7eb; text-align: left; text-transform: uppercase; letter-spacing: 0.5px; }
        .data-table td { padding: 16px 16px; font-size: 14px; border-bottom: 1px solid #f3f4f6; color: #374151; }
        
        .btn-reschedule { border: 1px solid #e5e7eb; color: #374151; font-size: 13px; font-weight: 500; padding: 6px 14px; border-radius: 6px; transition: 0.2s; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; height: 32px; }
        .btn-reschedule:hover { background: #f3f4f6; }
        .btn-cancel { border: 1px solid #fee2e2; color: #ef4444; font-size: 13px; font-weight: 500; padding: 6px 14px; border-radius: 6px; transition: 0.2s; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; height: 32px;}
        .btn-cancel:hover { background: #fef2f2; border-color: #fca5a5; }
        .btn-pay { border: 1px solid #bfdbfe; color: #2563eb; background: #eff6ff; font-size: 13px; font-weight: 600; padding: 6px 14px; border-radius: 6px; transition: 0.2s; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; height: 32px;}
        .btn-pay:hover { background: #3b82f6; color: #fff; }

        .sidebar-active { background-color: #eff6ff; color: #2563eb; border-left: 4px solid #2563eb; font-weight: 600; }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    </style>
</head>
<body class="flex h-screen overflow-hidden">
<div class="flex w-full h-full relative">
  
    <aside class="w-64 bg-white border-r border-gray-200 flex flex-col h-full flex-shrink-0 z-10 shadow-sm">
        <div class="flex items-center gap-2 p-6 border-b">
            <i class="fa-solid fa-lungs text-3xl text-red-400"></i>
            <h1 class="text-xl font-semibold text-gray-700">Pneumo-<span class="text-blue-500">Care</span></h1>
        </div>

        <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto">
            <a href="pat_dashboard.php" class="flex items-center gap-4 px-4 py-3 text-gray-500 hover:bg-gray-50 hover:text-gray-800 rounded-xl transition-colors font-medium">
                <i class="fa-solid fa-gauge-high w-5 text-center text-xl"></i><span>Dashboard</span>
            </a>
            <a href="pat_report.php" class="flex items-center gap-4 px-4 py-3 text-gray-500 hover:bg-gray-50 hover:text-gray-800 rounded-xl transition-colors font-medium">
                <i class="fa-solid fa-file-medical w-5 text-center text-xl"></i><span>Report</span>
            </a>
            <a href="pat_appointments.php" class="sidebar-active flex items-center gap-4 px-4 py-3 rounded-xl font-semibold transition-colors">
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

    <main class="main-content">
        <div class="topbar-wrapper">
            <header class="topbar">
                <h1>Appointments</h1>
                <div class="flex items-center gap-6">
                    <div class="flex items-center gap-3"><div class="text-right"><p class="text-sm font-semibold"><?php echo htmlspecialchars($patientName); ?></p><p class="text-xs text-gray-500">Patient</p></div><img src="<?php echo $patientAvatar; ?>" class="w-10 h-10 rounded-full border border-gray-200 shadow-sm object-cover"></div>
                </div>
            </header>
        </div>

        <div class="content-area">
            <?php echo $msg; ?>
            <div class="table-card">
                <div class="flex justify-between items-center mb-6">
                    <div class="flex border-b-2 border-gray-100">
                        <a class="tab-link active" href="pat_appointments.php">NEW APPOINTMENTS</a>
                        <a class="tab-link" href="pat_appointments_history.php">COMPLETED APPOINTMENTS</a>
                    </div>
                    <button class="bg-blue-600 text-white px-5 py-2.5 rounded-lg text-sm font-medium flex items-center gap-2" onclick="location.href='pat_book_step1.php'"><i class="fa-solid fa-plus"></i> New Appointment</button>
                </div>

                <table class="data-table">
                    <thead><tr><th class="w-[15%]">Time</th><th class="w-[20%]">Date</th><th class="w-[25%]">Doctor</th><th class="w-[15%]">Fee Status</th><th class="w-[25%]">Action</th></tr></thead>
                    <tbody>
                    <?php if(empty($appointments)): ?>
                        <tr><td colspan="5" class="text-center py-10 text-gray-400 italic">No upcoming appointments.</td></tr>
                    <?php else: ?>
                        <?php foreach($appointments as $appt): ?>
                        <tr>
                            <td class="font-semibold text-gray-800"><?php echo date('h:i A', strtotime($appt['appointment_time'])); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($appt['appointment_date'])); ?></td>
                            <td class="font-medium text-gray-700">Dr. <?php echo htmlspecialchars($appt['doctor_name']); ?></td>
                            <td>
                                <?php if($appt['fee_status'] == 'Unpaid'): ?>
                                    <span class="bg-yellow-50 text-yellow-600 border border-yellow-200 px-3 py-1 rounded-full text-[11px] font-bold uppercase tracking-wider">Unpaid</span>
                                <?php else: ?>
                                    <span class="bg-green-50 text-green-600 border border-green-200 px-3 py-1 rounded-full text-[11px] font-bold uppercase tracking-wider">Paid</span>
                                <?php endif; ?>
                            </td>
                            <td class="flex items-center gap-3">
                                <?php if($appt['fee_status'] == 'Unpaid'): ?>
                                    <a href="?pay_id=<?php echo $appt['appointment_id']; ?>" class="btn-pay"><i class="fa-brands fa-cc-visa mr-1"></i> Pay Now</a>
                                <?php endif; ?>
                                
                                <?php if($appt['status'] == 'Scheduled'): ?>
                                    <a href="?reschedule_id=<?php echo $appt['appointment_id']; ?>" class="btn-reschedule">Reschedule</a>
                                    
                                    <?php 
                                        // KIỂM TRA THỜI GIAN ĐỂ HIỂN THỊ NÚT CANCEL
                                        date_default_timezone_set('Asia/Ho_Chi_Minh');
                                        $now = new DateTime();
                                        $apptDateTime = new DateTime($appt['appointment_date'] . ' ' . $appt['appointment_time']);
                                        $intervalMinutes = ($apptDateTime->getTimestamp() - $now->getTimestamp()) / 60;
                                        
                                        if ($intervalMinutes >= 120): // Còn hơn 2 tiếng
                                    ?>
                                        <a href="?cancel_id=<?php echo $appt['appointment_id']; ?>" onclick="return confirm('Bạn có chắc chắn muốn hủy lịch khám này không?')" class="btn-cancel">Cancel</a>
                                    <?php else: // Dưới 2 tiếng -> Khóa nút ?>
                                        <span class="border border-gray-200 bg-gray-50 text-gray-400 font-medium text-[11px] uppercase tracking-widest px-3 py-1 rounded-md cursor-not-allowed flex items-center justify-center h-[32px]" title="Không thể hủy lịch khi giờ khám chỉ còn dưới 2 tiếng"><i class="fa-solid fa-lock mr-1"></i> Locked</span>
                                    <?php endif; ?>

                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <?php if($payData): ?>
    <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm flex items-center justify-center z-50">
        <div class="bg-white rounded-2xl shadow-2xl w-[450px] overflow-hidden">
            <div class="bg-blue-600 px-6 py-4 flex items-center justify-between text-white">
                <h3 class="font-semibold text-lg flex items-center gap-2"><i class="fa-solid fa-credit-card"></i> Payment Method</h3>
                <a href="pat_appointments.php" class="text-white/80 hover:text-white"><i class="fa-solid fa-xmark text-xl"></i></a>
            </div>
            
            <div class="p-8">
                <div class="text-center mb-6 border-b border-gray-100 pb-6">
                    <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-1">Total Amount</p>
                    <p class="text-4xl font-extrabold text-blue-600"><?php echo number_format($payData['consultation_fee']); ?> <span class="text-lg text-gray-500 font-medium">VND</span></p>
                    <p class="text-sm font-medium text-gray-600 mt-2">Dr. <?php echo htmlspecialchars($payData['doctor_name']); ?> - <?php echo date('d/m/Y', strtotime($payData['appointment_date'])); ?></p>
                </div>

                <p class="text-sm font-bold text-gray-700 mb-4 uppercase tracking-wide">Select how you want to pay:</p>
                
                <div class="space-y-3">
                    <button onclick="location.href='pat_payment.php?appt_id=<?php echo $payData['appointment_id']; ?>'" class="w-full flex items-center justify-between p-4 border-2 border-gray-200 rounded-xl hover:border-blue-500 hover:bg-blue-50 transition-all group">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 bg-white rounded-full flex items-center justify-center shadow-sm text-blue-500 text-lg border border-gray-100"><i class="fa-solid fa-qrcode"></i></div>
                            <div class="text-left">
                                <p class="font-bold text-gray-800">Online Payment</p>
                                <p class="text-xs font-medium text-gray-500">Momo, ZaloPay, Bank Transfer</p>
                            </div>
                        </div>
                        <i class="fa-solid fa-chevron-right text-gray-300 group-hover:text-blue-500"></i>
                    </button>

                    <form method="POST">
                        <input type="hidden" name="payment_appt_id" value="<?php echo $payData['appointment_id']; ?>">
                        <button type="submit" name="pay_cash" onclick="return confirm('You will pay cash at the clinic. Confirm?')" class="w-full flex items-center justify-between p-4 border-2 border-gray-200 rounded-xl hover:border-green-500 hover:bg-green-50 transition-all group">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 bg-white rounded-full flex items-center justify-center shadow-sm text-green-500 text-lg border border-gray-100"><i class="fa-solid fa-money-bill-wave"></i></div>
                                <div class="text-left">
                                    <p class="font-bold text-gray-800">Pay Cash at Clinic</p>
                                    <p class="text-xs font-medium text-gray-500">Pay directly at the reception desk</p>
                                </div>
                            </div>
                            <i class="fa-solid fa-chevron-right text-gray-300 group-hover:text-green-500"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if($rescheduleData): ?>
    <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm flex items-center justify-center z-50">
        <div class="bg-white rounded-2xl shadow-2xl w-[500px] overflow-hidden">
            <div class="bg-blue-600 px-6 py-4 flex items-center justify-between text-white">
                <h3 class="font-semibold text-lg">Reschedule Appointment</h3>
                <a href="pat_appointments.php" class="text-white/80 hover:text-white"><i class="fa-solid fa-xmark text-xl"></i></a>
            </div>
            <form method="POST" class="p-8">
                <input type="hidden" name="reschedule_appt_id" value="<?php echo $rescheduleData['appointment_id']; ?>">
                <div class="mb-6 bg-blue-50 border border-blue-100 rounded-xl p-4">
                    <p class="text-xs font-bold text-blue-600 uppercase mb-1">Doctor</p>
                    <p class="font-bold text-[#003366]">Dr. <?php echo htmlspecialchars($rescheduleData['doctor_name']); ?></p>
                </div>
                <div class="mb-6">
                    <label class="block text-sm font-bold text-gray-700 mb-2 uppercase tracking-wide">1. Pick New Date</label>
                    <input type="date" name="new_date" id="rescheduleDate" required min="<?php echo date('Y-m-d'); ?>" 
                           value="<?php echo $selected_date; ?>"
                           onchange="window.location.href='?reschedule_id=<?php echo $reschedule_id; ?>&new_date=' + this.value"
                           class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:border-blue-500 outline-none bg-gray-50 font-medium">
                </div>
                <div class="mb-8">
                    <label class="block text-sm font-bold text-gray-700 mb-3 uppercase tracking-wide">2. Select Available Time</label>
                    <div class="grid grid-cols-2 gap-3">
                        <?php foreach($available_slots as $time): ?>
                            <?php 
                            $is_booked = in_array($time, $booked_slots); 
                            $is_current = (date('H:i', strtotime($rescheduleData['appointment_time'])) == $time && $selected_date == $rescheduleData['appointment_date']);
                            ?>
                            <label class="relative cursor-pointer">
                                <input type="radio" name="new_time" value="<?php echo $time; ?>:00" class="peer hidden" 
                                       <?php echo $is_booked ? 'disabled' : ''; ?> <?php echo $is_current ? 'checked' : ''; ?> required>
                                <div class="border rounded-xl py-3 text-center text-sm font-semibold transition-all
                                    <?php if($is_booked): ?> bg-gray-100 border-gray-200 text-gray-400 cursor-not-allowed opacity-60
                                    <?php else: ?> bg-white border-gray-200 text-gray-700 hover:border-blue-300 peer-checked:bg-blue-600 peer-checked:border-blue-600 peer-checked:text-white
                                    <?php endif; ?>">
                                    <?php if($is_booked): ?><i class="fa-solid fa-lock text-[10px] mr-1"></i><?php endif; ?>
                                    <?php echo date('h:i A', strtotime($time)); ?>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="flex gap-3 pt-4 border-t border-gray-100">
                    <a href="pat_appointments.php" class="flex-1 text-center py-3.5 border border-gray-200 rounded-xl text-gray-500 font-bold hover:bg-gray-50 transition-colors uppercase text-xs">Cancel</a>
                    <button type="submit" name="confirm_reschedule" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-3.5 rounded-xl font-bold transition-all shadow-md uppercase text-xs">Confirm Change</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

</div>
</body>
</html>