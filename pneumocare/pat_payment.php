<?php
// ==========================================
// TÊN FILE: pat_payment.php
// CHỨC NĂNG: Cổng thanh toán Online (QR Code / Chuyển khoản)
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

$appt_id = isset($_GET['appt_id']) ? $_GET['appt_id'] : null;

// Nếu không có ID cuộc hẹn, đá về trang danh sách
if (!$appt_id) {
    header("Location: pat_appointments.php");
    exit();
}

// 1. XỬ LÝ XÁC NHẬN THANH TOÁN (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_payment'])) {
    try {
        // Cập nhật trạng thái thành Paid và phương thức là Chuyển khoản
        $stmtUpdate = $pdo->prepare("UPDATE Appointments SET fee_status = 'Paid', payment_method = 'Chuyển khoản' WHERE appointment_id = ? AND patient_id = ?");
        $stmtUpdate->execute([$appt_id, $patientId]);
        
        // Chuyển hướng về trang lịch khám kèm tham số (tùy chọn để hiện thông báo)
        header("Location: pat_appointments.php");
        exit();
    } catch (PDOException $e) {
        die("Lỗi cập nhật: " . $e->getMessage());
    }
}

// 2. LẤY THÔNG TIN HÓA ĐƠN
try {
    $stmtPay = $pdo->prepare("
        SELECT a.*, u.full_name as doctor_name, dp.speciality, dp.consultation_fee 
        FROM Appointments a 
        JOIN Users u ON a.doctor_id = u.user_id 
        JOIN Doctor_Profiles dp ON u.user_id = dp.doctor_id 
        WHERE a.appointment_id = ? AND a.patient_id = ? AND a.fee_status = 'Unpaid'
    ");
    $stmtPay->execute([$appt_id, $patientId]);
    $payData = $stmtPay->fetch();

    if (!$payData) {
        // Nếu không tìm thấy hoặc đã thanh toán rồi thì quay lại
        header("Location: pat_appointments.php");
        exit();
    }
} catch (PDOException $e) {
    die("Lỗi Database: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pneumo-Care | Secure Payment</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; color: #1f2937; }

        .layout { display: flex; min-height: 100vh; overflow: hidden; }
        
        /* SIDEBAR CHUẨN ĐỒNG BỘ */
        .sidebar { width: 260px; background: #ffffff; border-right: 1px solid #e5e7eb; display: flex; flex-direction: column; min-height: 100vh; flex-shrink: 0; z-index: 10; }
        .sidebar-active { background-color: #eff6ff; color: #2563eb; border-left: 4px solid #2563eb; font-weight: 600; }

        /* MAIN CONTENT */
        .main-content { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
        .topbar-wrapper { padding: 32px 40px 0 40px; }
        .topbar { 
            height: 72px; background: #ffffff; border: 1px solid #f3f4f6; 
            display: flex; align-items: center; justify-content: space-between; 
            padding: 0 24px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); 
            margin-bottom: 24px;
        }
        .topbar h1 { font-size: 22px; font-weight: 600; color: #1f2937; margin: 0; }
        .content-area { padding: 0 40px 40px 40px; flex: 1; overflow-y: auto; }

        /* Scrollbar */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
</head>
<body class="flex h-screen overflow-hidden text-gray-800">
<div class="flex w-full h-full relative">
  
    <aside class="w-64 bg-white border-r border-gray-200 flex flex-col h-full flex-shrink-0 z-10 shadow-sm">
        <div class="h-[88px] flex items-center px-6 border-b border-gray-100">
            <svg viewBox="0 0 32 32" fill="none" width="30" height="30" class="mr-3 flex-shrink-0">
                <ellipse cx="10" cy="18" rx="7" ry="10" fill="#f87171" transform="rotate(-10 10 18)"/>
                <ellipse cx="22" cy="18" rx="7" ry="10" fill="#fca5a5" transform="rotate(10 22 18)"/>
            </svg>
            <div class="text-[22px] font-bold text-gray-900 tracking-tight">Pneumo-<span class="text-blue-500">Care</span></div>
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

    <main class="main-content bg-[#f4f7fa]">
        <div class="topbar-wrapper flex-shrink-0">
            <header class="topbar">
                <h1>Online Payment</h1>
                <div class="flex items-center gap-6">
                    <div class="relative cursor-pointer text-gray-400 hover:text-gray-600 transition-colors">
                        <i class="fa-solid fa-bell text-xl"></i>
                        <span class="absolute -top-0.5 -right-0.5 w-2.5 h-2.5 bg-red-500 rounded-full border-2 border-white"></span>
                    </div>
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

        <div class="content-area max-w-6xl mx-auto w-full">
            
            <div class="mb-6 flex items-center justify-between">
                <a href="pat_appointments.php" class="text-sm font-bold text-blue-600 hover:text-blue-800 transition-colors flex items-center gap-2 bg-blue-50 px-4 py-2 rounded-lg border border-blue-100">
                    <i class="fa-solid fa-arrow-left"></i> Back to Appointments
                </a>
                <div class="flex items-center gap-2 text-gray-500 text-sm font-medium">
                    <i class="fa-solid fa-lock text-green-500"></i> Secure Checkout
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-5 gap-8">
                
                <div class="lg:col-span-2 space-y-6">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8">
                        <h2 class="text-lg font-bold text-[#003366] border-b border-gray-100 pb-4 mb-6">Order Summary</h2>
                        
                        <div class="space-y-5">
                            <div>
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Doctor</p>
                                <p class="font-bold text-gray-800">Dr. <?php echo htmlspecialchars($payData['doctor_name']); ?></p>
                                <p class="text-xs text-gray-500 mt-0.5"><?php echo htmlspecialchars($payData['speciality']); ?></p>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Schedule</p>
                                <p class="font-medium text-gray-800 bg-gray-50 px-3 py-2 rounded-lg border border-gray-100 text-sm">
                                    <?php echo date('h:i A', strtotime($payData['appointment_time'])); ?> <span class="mx-2 text-gray-300">|</span> <?php echo date('F d, Y', strtotime($payData['appointment_date'])); ?>
                                </p>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Appointment ID</p>
                                <p class="font-bold text-gray-800">#PNM-<?php echo str_pad($payData['appointment_id'], 5, '0', STR_PAD_LEFT); ?></p>
                            </div>
                        </div>

                        <div class="mt-8 pt-6 border-t border-dashed border-gray-200">
                            <div class="flex justify-between items-end">
                                <p class="text-sm font-bold text-gray-500 uppercase tracking-widest">Total Amount</p>
                                <div class="text-right">
                                    <p class="text-3xl font-extrabold text-blue-600"><?php echo number_format($payData['consultation_fee']); ?> <span class="text-sm text-gray-500 font-medium">VND</span></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-blue-50 border border-blue-100 rounded-2xl p-6 flex items-start gap-4">
                        <i class="fa-solid fa-shield-halved text-blue-500 text-2xl mt-1"></i>
                        <div>
                            <h4 class="font-bold text-blue-800 text-sm">Payment Security</h4>
                            <p class="text-xs text-blue-600/80 mt-1 leading-relaxed">Your payment is encrypted and securely processed. We do not store any of your banking details.</p>
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-3">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden h-full flex flex-col">
                        <div class="bg-[#003366] px-8 py-5 flex items-center justify-between">
                            <h2 class="text-lg font-bold text-white flex items-center gap-2"><i class="fa-solid fa-qrcode"></i> Scan to Pay</h2>
                        </div>
                        
                        <div class="p-10 flex-1 flex flex-col md:flex-row items-center justify-center gap-10">
                            <?php 
                                $qrData = "PneumoCare Payment | ID: " . $payData['appointment_id'] . " | Amount: " . $payData['consultation_fee']; 
                                $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=" . urlencode($qrData);
                            ?>
                            <div class="bg-white p-4 rounded-2xl shadow-md border-2 border-gray-100 flex-shrink-0 relative group">
                                <img src="<?php echo $qrUrl; ?>" alt="QR Code" class="w-48 h-48">
                                <div class="absolute inset-0 bg-white/80 backdrop-blur-sm flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity rounded-xl">
                                    <span class="bg-blue-600 text-white px-4 py-2 rounded-full text-xs font-bold uppercase tracking-wider shadow-md">Open App</span>
                                </div>
                            </div>
                            
                            <div class="flex-1 w-full">
                                <p class="text-sm font-bold text-gray-500 uppercase tracking-widest mb-4 text-center md:text-left">Or Transfer Manually</p>
                                
                                <div class="space-y-4">
                                    <div class="bg-gray-50 border border-gray-200 rounded-xl p-4 flex justify-between items-center">
                                        <div>
                                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Bank</p>
                                            <p class="font-bold text-gray-800">VP Bank</p>
                                        </div>
                                    </div>
                                    <div class="bg-gray-50 border border-gray-200 rounded-xl p-4 flex justify-between items-center">
                                        <div>
                                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Account Name</p>
                                            <p class="font-bold text-gray-800 uppercase">Pneumo Care Clinic</p>
                                        </div>
                                    </div>
                                    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 flex justify-between items-center group cursor-pointer hover:bg-blue-100 transition-colors" onclick="navigator.clipboard.writeText('19034567890011'); alert('Account number copied!');">
                                        <div>
                                            <p class="text-[10px] font-bold text-blue-400 uppercase tracking-widest">Account Number</p>
                                            <p class="font-extrabold text-blue-700 text-lg tracking-wider">3911 2082 003</p>
                                        </div>
                                        <i class="fa-regular fa-copy text-blue-400 group-hover:text-blue-600 text-xl"></i>
                                    </div>
                                    <div class="bg-gray-50 border border-gray-200 rounded-xl p-4">
                                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Message (Content)</p>
                                        <p class="font-bold text-gray-800 font-mono tracking-wider">PNM<?php echo $payData['appointment_id']; ?> <?php echo strtoupper(str_replace(' ', '', $patientName)); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="p-6 border-t border-gray-100 bg-gray-50/50 mt-auto">
                            <form method="POST" action="">
                                <p class="text-xs text-gray-500 text-center mb-4 font-medium"><i class="fa-solid fa-circle-exclamation text-yellow-500 mr-1"></i> Please click the button below <strong>ONLY AFTER</strong> you have successfully completed the transfer.</p>
                                <button type="submit" name="confirm_payment" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-4 rounded-xl font-bold transition-all shadow-md hover:shadow-lg uppercase tracking-widest text-sm flex justify-center items-center gap-2">
                                    <i class="fa-solid fa-check-circle text-lg"></i> I Have Completed The Transfer
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

            </div>
            
        </div>
    </main>
</div>
</body>
</html> 