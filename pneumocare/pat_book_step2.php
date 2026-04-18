<?php
session_start();
require 'db.php';
ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL);

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Patient' || !isset($_SESSION['booking_info'])) { 
    header("Location: pat_book_step1.php"); 
    exit(); 
}

$patientName = $_SESSION['name'];
$patientAvatar = (!empty($_SESSION['avatar']) && $_SESSION['avatar'] != 'default.png') ? $_SESSION['avatar'] : 'img/default_patient.png';

$expanded_doc = isset($_GET['expand_doc']) ? $_GET['expand_doc'] : null;
$selected_date = isset($_GET['select_date']) ? $_GET['select_date'] : date('Y-m-d');

// 1. Lấy danh sách bác sĩ
$doctors = [];
try {
    $doctors = $pdo->query("SELECT u.user_id, u.full_name, u.avatar_url, dp.speciality, dp.consultation_fee FROM Users u JOIN Doctor_Profiles dp ON u.user_id = dp.doctor_id WHERE u.role = 'Doctor'")->fetchAll();
} catch (PDOException $e) {}

// 2. Xử lý khi bệnh nhân CHỌN xong Bác sĩ & Ngày giờ (Lưu vào Session và chuyển sang Step 3)
if (isset($_GET['doc_id']) && isset($_GET['date']) && isset($_GET['time'])) {
    $_SESSION['booking_info']['doctor_id'] = $_GET['doc_id'];
    $_SESSION['booking_info']['appt_date'] = $_GET['date'];
    $_SESSION['booking_info']['appt_time'] = $_GET['time'];
    foreach($doctors as $d) { 
        if($d['user_id'] == $_GET['doc_id']) { 
            $_SESSION['booking_info']['doctor_name'] = $d['full_name']; 
            break; 
        } 
    }
    header("Location: pat_book_step3.php"); 
    exit();
}

// 3. LOGIC KIỂM TRA LỊCH BẬN CỦA BÁC SĨ TỪ DATABASE
$booked_slots = [];
if ($expanded_doc) {
    try {
        // Lấy tất cả các lịch đã được đặt (trừ những lịch đã bị Hủy)
        $stmtSlots = $pdo->prepare("SELECT appointment_date, appointment_time FROM Appointments WHERE doctor_id = ? AND status != 'Cancelled' AND appointment_date >= CURDATE()");
        $stmtSlots->execute([$expanded_doc]);
        while ($row = $stmtSlots->fetch()) {
            // Định dạng lại giờ thành HH:mm để dễ so sánh
            $booked_slots[$row['appointment_date']][] = date('H:i', strtotime($row['appointment_time']));
        }
    } catch (PDOException $e) {}
}

// Định nghĩa các khung giờ khám mặc định
$morning_slots = ['09:00', '10:30'];
$afternoon_slots = ['13:30', '15:00'];
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pneumo-Care | Patient - Pick Doctor</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background-color: #f4f7fa; color: #1f2937; }

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
            <h1>Pick Doctor & Schedule</h1>
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
                    <div class="w-10 h-10 rounded-xl bg-blue-600 text-white font-bold flex items-center justify-center shadow-md">2</div>
                    <span class="text-xs font-bold text-blue-600 mt-2 tracking-wide uppercase">Doctor & Time</span>
                </div>
                <div class="w-16 h-1 bg-gray-200 mx-2 rounded-full -mt-6"></div>
                <div class="flex flex-col items-center opacity-50">
                    <div class="w-10 h-10 rounded-xl bg-gray-200 text-gray-500 font-bold flex items-center justify-center">3</div>
                    <span class="text-xs font-bold text-gray-500 mt-2 tracking-wide uppercase">Confirm</span>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-6 flex justify-between items-center">
                <div class="flex items-center gap-3">
                    <span class="text-sm font-semibold text-gray-700">Filter By Doctor</span>
                </div>
                <div class="bg-blue-50 text-blue-600 text-sm px-4 py-2 rounded-lg font-medium border border-blue-100">
                    <?php echo count($doctors); ?> matching doctors found
                </div>
            </div>

            <div class="space-y-4 mb-10">
                <?php foreach($doctors as $doc): ?>
                    <?php if($expanded_doc == $doc['user_id']): ?>
                        
                        <div class="bg-white border-2 border-blue-500 rounded-t-xl p-5 flex items-center gap-5">
                            <img src="<?php echo $doc['avatar_url'] ?: 'img/default.png'; ?>" class="w-16 h-16 rounded-full object-cover border border-gray-100 shadow-sm">
                            <div class="flex-1">
                                <h3 class="text-lg font-bold text-gray-800">Dr. <?php echo htmlspecialchars($doc['full_name']); ?></h3>
                                <p class="text-sm text-gray-500 mt-1"><span class="text-blue-500 font-medium">Speciality:</span> <?php echo htmlspecialchars($doc['speciality']); ?></p>
                            </div>
                            <div class="text-right mr-6 hidden md:block">
                                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Consultation Fee</p>
                                <p class="text-lg font-bold text-yellow-600"><?php echo number_format($doc['consultation_fee']); ?> <span class="text-sm">VND</span></p>
                            </div>
                            <button class="bg-gray-100 text-gray-600 hover:bg-gray-200 px-6 py-2.5 rounded-lg text-sm font-semibold transition-colors" onclick="location.href='pat_book_step2.php'">
                                Cancel
                            </button>
                        </div>
                        
                        <div class="bg-blue-50/50 border-2 border-t-0 border-blue-500 rounded-b-xl p-6">
                            <p class="text-sm font-semibold text-gray-700 mb-4 uppercase tracking-wide">Pick a Date</p>
                            <div class="flex gap-3 mb-8 overflow-x-auto pb-2">
                                <?php for($i=0; $i<5; $i++): $d = date('Y-m-d', strtotime("+$i days")); ?>
                                    <div onclick="location.href='?expand_doc=<?php echo $doc['user_id']; ?>&select_date=<?php echo $d; ?>'" 
                                         class="cursor-pointer min-w-[80px] text-center border transition-all rounded-xl py-3
                                         <?php echo $selected_date == $d ? 'bg-blue-600 border-blue-600 text-white shadow-md' : 'bg-white border-gray-200 text-gray-600 hover:border-blue-300 hover:bg-blue-50'; ?>">
                                        <p class="text-xs font-semibold uppercase mb-1 <?php echo $selected_date == $d ? 'text-blue-100' : 'text-gray-400'; ?>"><?php echo date('D', strtotime($d)); ?></p>
                                        <p class="text-lg font-bold"><?php echo date('d', strtotime($d)); ?></p>
                                    </div>
                                <?php endfor; ?>
                            </div>
                            
                            <div class="space-y-4">
                                <div class="flex items-center gap-6">
                                    <span class="text-sm font-semibold text-gray-500 w-20 flex-shrink-0 flex items-center gap-2"><i class="fa-regular fa-sun text-yellow-500"></i> Morning</span>
                                    <div class="flex gap-3 flex-wrap">
                                        <?php foreach($morning_slots as $time): ?>
                                            <?php if(isset($booked_slots[$selected_date]) && in_array($time, $booked_slots[$selected_date])): ?>
                                                <button disabled class="bg-gray-100 border border-gray-200 text-gray-400 px-4 py-2 rounded-lg text-sm font-medium cursor-not-allowed flex items-center gap-2 opacity-60 line-through">
                                                    <i class="fa-solid fa-lock text-xs"></i> <?php echo $time; ?>
                                                </button>
                                            <?php else: ?>
                                                <button onclick="location.href='?doc_id=<?php echo $doc['user_id']; ?>&date=<?php echo $selected_date; ?>&time=<?php echo $time; ?>:00'" class="bg-white border border-gray-200 text-gray-700 hover:border-blue-500 hover:text-blue-600 hover:bg-blue-50 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
                                                    <?php echo $time; ?>
                                                </button>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                
                                <div class="flex items-center gap-6 mt-4">
                                    <span class="text-sm font-semibold text-gray-500 w-20 flex-shrink-0 flex items-center gap-2"><i class="fa-solid fa-cloud-sun text-orange-400"></i> Afternoon</span>
                                    <div class="flex gap-3 flex-wrap">
                                        <?php foreach($afternoon_slots as $time): ?>
                                            <?php if(isset($booked_slots[$selected_date]) && in_array($time, $booked_slots[$selected_date])): ?>
                                                <button disabled class="bg-gray-100 border border-gray-200 text-gray-400 px-4 py-2 rounded-lg text-sm font-medium cursor-not-allowed flex items-center gap-2 opacity-60 line-through">
                                                    <i class="fa-solid fa-lock text-xs"></i> <?php echo $time; ?>
                                                </button>
                                            <?php else: ?>
                                                <button onclick="location.href='?doc_id=<?php echo $doc['user_id']; ?>&date=<?php echo $selected_date; ?>&time=<?php echo $time; ?>:00'" class="bg-white border border-gray-200 text-gray-700 hover:border-blue-500 hover:text-blue-600 hover:bg-blue-50 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
                                                    <?php echo $time; ?>
                                                </button>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                    <?php else: ?>
                        
                        <div class="bg-white border border-gray-100 rounded-xl p-5 flex items-center gap-5 hover:shadow-md transition-shadow">
                            <img src="<?php echo $doc['avatar_url'] ?: 'img/default.png'; ?>" class="w-16 h-16 rounded-full object-cover border border-gray-100 shadow-sm">
                            <div class="flex-1">
                                <h3 class="text-lg font-bold text-gray-800">Dr. <?php echo htmlspecialchars($doc['full_name']); ?></h3>
                                <p class="text-sm text-gray-500 mt-1"><span class="text-blue-500 font-medium">Speciality:</span> <?php echo htmlspecialchars($doc['speciality']); ?></p>
                            </div>
                            <div class="text-right mr-6 hidden md:block">
                                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Consultation Fee</p>
                                <p class="text-lg font-bold text-yellow-600"><?php echo number_format($doc['consultation_fee']); ?> <span class="text-sm">VND</span></p>
                            </div>
                            <button class="bg-blue-50 text-blue-600 border border-blue-200 hover:bg-blue-600 hover:text-white px-8 py-2.5 rounded-lg text-sm font-semibold transition-colors" onclick="location.href='?expand_doc=<?php echo $doc['user_id']; ?>'">
                                Pick Doctor
                            </button>
                        </div>
                        
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            
        </div>
    </main>
</div>
</body>
</html>