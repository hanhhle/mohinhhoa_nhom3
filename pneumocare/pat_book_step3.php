<?php
session_start();
require 'db.php';
ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL);

// Nếu chưa có session booking_info, nhưng nếu vừa đặt xong (booking_success) thì vẫn cho ở lại xem nút Thanh toán
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Patient') { 
    header("Location: login.php"); 
    exit(); 
}

// Nếu không có booking_info và cũng không phải vừa đặt xong -> quay về step 1
if (!isset($_SESSION['booking_info']) && !isset($booking_success)) {
    header("Location: pat_book_step1.php"); 
    exit();
}

$patientId = $_SESSION['user_id'];
$patientName = $_SESSION['name'];
$patientAvatar = (!empty($_SESSION['avatar']) && $_SESSION['avatar'] != 'default.png') ? $_SESSION['avatar'] : 'img/default_patient.png';

$booking_success = false;
$new_appt_id = null;

if (isset($_SESSION['booking_info'])) {
    $booking = $_SESSION['booking_info'];
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_booking'])) {
    try {
        // Lấy note từ form
        $notes = isset($_POST['patient_notes']) ? trim($_POST['patient_notes']) : null;
        
        $stmt = $pdo->prepare("INSERT INTO Appointments (patient_id, doctor_id, appointment_date, appointment_time, status, fee_status, patient_notes) VALUES (?, ?, ?, ?, 'Scheduled', 'Unpaid', ?)");
        $stmt->execute([$patientId, $booking['doctor_id'], $booking['appt_date'], $booking['appt_time'], $notes]);
        
        // Lấy ID của lịch khám vừa tạo để truyền sang trang Thanh toán
        $new_appt_id = $pdo->lastInsertId();
        $booking_success = true;
        
        // Xóa session booking
        unset($_SESSION['booking_info']);
        
    } catch (PDOException $e) {
        die("Lỗi: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pneumo-Care | Patient - Confirm Appointment</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; color: #1f2937; }

        .layout { display: flex; min-height: 100vh; overflow: hidden; }
        
        .sidebar { width: 260px; background: #ffffff; border-right: 1px solid #e5e7eb; display: flex; flex-direction: column; min-height: 100vh; flex-shrink: 0; z-index: 10; }
        .sidebar-active { background-color: #eff6ff; color: #2563eb; border-left: 4px solid #2563eb; font-weight: 600; }

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

        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
</head>
<body class="flex h-screen overflow-hidden text-gray-800">
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

    <main class="main-content bg-[#f4f7fa]">
        <div class="topbar-wrapper flex-shrink-0">
            <header class="topbar">
            <h1>Confirm Appointment</h1>
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

        <div class="content-area max-w-5xl mx-auto w-full">
            
            <div class="flex justify-center items-center mb-10 mt-2">
                <div class="flex flex-col items-center">
                    <div class="w-10 h-10 rounded-xl bg-blue-600 text-white font-bold flex items-center justify-center shadow-md"><i class="fa-solid fa-check"></i></div>
                    <span class="text-xs font-bold text-blue-600 mt-2 tracking-wide uppercase">Patient Info</span>
                </div>
                <div class="w-16 h-1 bg-blue-600 mx-2 rounded-full -mt-6"></div>
                <div class="flex flex-col items-center">
                    <div class="w-10 h-10 rounded-xl bg-blue-600 text-white font-bold flex items-center justify-center shadow-md"><i class="fa-solid fa-check"></i></div>
                    <span class="text-xs font-bold text-blue-600 mt-2 tracking-wide uppercase">Doctor & Time</span>
                </div>
                <div class="w-16 h-1 bg-blue-600 mx-2 rounded-full -mt-6"></div>
                <div class="flex flex-col items-center">
                    <div class="w-10 h-10 rounded-xl bg-blue-600 text-white font-bold flex items-center justify-center shadow-md">3</div>
                    <span class="text-xs font-bold text-blue-600 mt-2 tracking-wide uppercase">Confirm</span>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-10 max-w-3xl mx-auto">
                
                <?php if ($booking_success): ?>
                    <div class="text-center py-6">
                        <div class="w-20 h-20 bg-green-100 text-green-500 rounded-full flex items-center justify-center text-4xl mx-auto mb-6 shadow-sm">
                            <i class="fa-solid fa-check"></i>
                        </div>
                        <h2 class="text-2xl font-bold text-[#003366] mb-2">Appointment booked successfully!</h2>
                        <p class="text-gray-500 font-medium">Thank you for choosing Pneumo-Care.</p>

                        <div class="mt-8 text-center border-t border-gray-100 pt-8">
                            <p class="text-sm text-yellow-700 bg-yellow-50 border border-yellow-200 px-5 py-4 rounded-xl mb-6 font-medium shadow-sm">
                                <i class="fa-solid fa-circle-exclamation mr-1.5 text-yellow-500"></i> Please complete the online payment now, or pay in cash at the reception desk before entering the clinic.
                            </p>
                            
                            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                                <a href="pat_appointments.php" class="px-6 py-3.5 bg-gray-50 text-gray-600 border border-gray-200 rounded-xl font-bold text-sm hover:bg-gray-100 hover:text-gray-900 transition-all">
                                    Done Later
                                </a>
                                
                                <a href="pat_payment.php?appt_id=<?php echo $new_appt_id; ?>" class="px-6 py-3.5 bg-blue-600 text-white rounded-xl font-bold text-sm hover:bg-blue-700 shadow-md transition-all flex items-center justify-center gap-2">
                                    <i class="fa-brands fa-cc-visa text-lg"></i> Pay Now
                                </a>
                            </div>
                        </div>
                    </div>

                <?php else: ?>
                    <div class="border-b border-gray-100 pb-5 mb-8 flex items-center justify-between">
                        <h2 class="text-xl font-bold text-[#003366]">Step 3: Review & Confirm</h2>
                        <a href="pat_book_step2.php" class="text-sm font-medium text-gray-400 hover:text-blue-600 transition-colors flex items-center gap-2">
                            <i class="fa-solid fa-arrow-left"></i> Back to Schedule
                        </a>
                    </div>

                    <form method="POST" action="">
                        <input type="hidden" name="confirm_booking" value="1">
                        <div class="border-2 border-blue-100 rounded-2xl overflow-hidden mb-8 shadow-sm">
                            <div class="bg-blue-50 px-6 py-5 flex items-center gap-4 border-b border-blue-100">
                                <div class="w-14 h-14 bg-white text-blue-600 rounded-full flex items-center justify-center font-bold text-2xl shadow-sm">
                                    <i class="fa-solid fa-user-doctor"></i>
                                </div>
                                <div>
                                    <p class="text-[11px] text-gray-500 font-bold uppercase tracking-widest mb-1">Selected Doctor</p>
                                    <h3 class="text-xl font-extrabold text-[#003366]">Dr. <?php echo htmlspecialchars($booking['doctor_name']); ?></h3>
                                </div>
                            </div>
                            
                            <div class="p-8 space-y-6 bg-white">
                                <div class="flex items-start gap-6">
                                    <div class="w-24 flex-shrink-0 font-bold text-red-500 uppercase text-xs tracking-wider mt-1">Time:</div>
                                    <div class="font-semibold text-gray-800 text-lg bg-gray-50 px-4 py-2 rounded-lg border border-gray-100 w-full">
                                        <?php echo date('h:i A', strtotime($booking['appt_time'])); ?> 
                                        <span class="mx-3 text-gray-300">|</span> 
                                        <?php echo date('F d, Y', strtotime($booking['appt_date'])); ?>
                                    </div>
                                </div>
                                
                                <div class="flex items-start gap-6">
                                    <div class="w-24 flex-shrink-0 font-bold text-blue-600 uppercase text-xs tracking-wider mt-1">Patient:</div>
                                    <div class="font-semibold text-gray-800 text-lg bg-blue-50/50 px-4 py-3 rounded-lg border border-blue-100 w-full">
                                        <?php echo htmlspecialchars($booking['name']); ?> 
                                        <span class="text-sm text-gray-500 font-medium block mt-1.5"><i class="fa-solid fa-phone text-xs mr-1 opacity-70"></i> <?php echo htmlspecialchars($booking['phone']); ?></span>
                                    </div>
                                </div>
                                
                                <div class="flex items-start gap-6">
                                    <div class="w-24 flex-shrink-0 font-bold text-gray-400 uppercase text-xs tracking-wider mt-1">Location:</div>
                                    <div class="text-gray-700 font-medium bg-gray-50 px-4 py-3 rounded-lg border border-gray-100 w-full flex items-start gap-2">
                                        <i class="fa-solid fa-location-dot mt-1 text-red-400"></i>
                                        PneumoCare Medical Center, 1st Floor, Building A.
                                    </div>
                                </div>
                                
                                <div class="pt-8 mt-6 border-t border-gray-100">
                                    <label class="block text-[11px] font-bold text-gray-600 mb-3 uppercase tracking-widest">Symptoms / Notes for Doctor <span class="text-gray-400 font-normal normal-case">(Optional)</span></label>
                                    <textarea name="patient_notes" rows="4" class="w-full bg-gray-50 border border-gray-200 rounded-xl p-4 text-sm font-medium text-gray-700 focus:border-blue-400 focus:bg-white focus:shadow-[0_0_0_3px_rgba(59,130,246,0.1)] outline-none transition-all resize-none placeholder-gray-400" placeholder="Briefly describe your symptoms (e.g., cough, fever duration) or leave a note..."></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end border-t border-gray-100 pt-6">
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-8 py-4 rounded-xl text-sm font-bold transition-all shadow-md hover:shadow-lg flex items-center gap-3 uppercase tracking-wide">
                                <i class="fa-solid fa-check-circle text-lg"></i> Confirm & Book
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
            
        </div>
    </main>
</div>
</body>
</html>