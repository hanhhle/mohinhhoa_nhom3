<?php
session_start();
require 'db.php';
ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL);

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Patient') { header("Location: login.php"); exit(); }
$patientId = $_SESSION['user_id'];
$patientName = $_SESSION['name'];
$patientAvatar = (!empty($_SESSION['avatar']) && $_SESSION['avatar'] != 'default.png') ? $_SESSION['avatar'] : 'img/default_patient.png';

$userInfo = [];
try {
    // Đã gọi bảng Patient_Profiles (pp.*) nên sẽ lấy được cả address và identity_card_number
    $stmt = $pdo->prepare("SELECT u.full_name, u.email, pp.* FROM Users u JOIN Patient_Profiles pp ON u.user_id = pp.patient_id WHERE u.user_id = ?");
    $stmt->execute([$patientId]);
    $userInfo = $stmt->fetch();
} catch (PDOException $e) {}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $_SESSION['booking_info'] = $_POST;
    header("Location: pat_book_step2.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pneumo-Care | Book Appointment - Step 1</title>
    
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

        /* FORM BÓNG BẨY MỚI */
        .form-input, .form-select {
            width: 100%; background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 12px;
            padding: 12px 16px; font-size: 14px; color: #374151; transition: all 0.2s; outline: none;
        }
        .form-input:focus, .form-select:focus {
            background-color: #ffffff; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
        }
        .form-label { display: block; font-size: 12px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; }
        
        /* Scrollbar */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
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
                <h1>Book Appointment</h1>
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
            
            <div class="flex justify-center items-center mb-10 mt-2">
                <div class="flex flex-col items-center">
                    <div class="w-10 h-10 rounded-xl bg-blue-600 text-white font-bold flex items-center justify-center shadow-md">1</div>
                    <span class="text-xs font-bold text-blue-600 mt-2 tracking-wide uppercase">Patient Info</span>
                </div>
                <div class="w-16 h-1 bg-gray-200 mx-2 rounded-full -mt-6"></div>
                <div class="flex flex-col items-center opacity-50">
                    <div class="w-10 h-10 rounded-xl bg-gray-200 text-gray-500 font-bold flex items-center justify-center">2</div>
                    <span class="text-xs font-bold text-gray-500 mt-2 tracking-wide uppercase">Doctor & Time</span>
                </div>
                <div class="w-16 h-1 bg-gray-200 mx-2 rounded-full -mt-6"></div>
                <div class="flex flex-col items-center opacity-50">
                    <div class="w-10 h-10 rounded-xl bg-gray-200 text-gray-500 font-bold flex items-center justify-center">3</div>
                    <span class="text-xs font-bold text-gray-500 mt-2 tracking-wide uppercase">Confirm</span>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-10 max-w-4xl mx-auto">
                <div class="border-b border-gray-100 pb-5 mb-8 flex items-center justify-between">
                    <h2 class="text-xl font-bold text-[#003366]">Step 1: Confirm Patient Details</h2>
                    <a href="pat_appointments.php" class="text-sm font-medium text-gray-400 hover:text-red-500 transition-colors">Cancel Booking</a>
                </div>

                <form method="POST" action="">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                        <div>
                            <label class="form-label">Full Name <span class="text-red-500">*</span></label>
                            <input class="form-input bg-gray-50 font-medium" name="name" value="<?php echo htmlspecialchars($userInfo['full_name'] ?? ''); ?>" required placeholder="e.g. Nguyen Van A">
                        </div>
                        <div>
                            <label class="form-label">Date of Birth <span class="text-red-500">*</span></label>
                            <input type="date" class="form-input bg-gray-50 font-medium" name="dob" value="<?php echo htmlspecialchars($userInfo['date_of_birth'] ?? ''); ?>" required>
                        </div>
                        <div>
                            <label class="form-label">Phone Number <span class="text-red-500">*</span></label>
                            <input class="form-input bg-gray-50 font-medium" name="phone" value="<?php echo htmlspecialchars($userInfo['phone_number'] ?? ''); ?>" required placeholder="e.g. 0912345678">
                        </div>
                        <div>
                            <label class="form-label">Gender <span class="text-red-500">*</span></label>
                            <select class="form-select bg-gray-50 font-medium" name="gender">
                                <option value="Male" <?php echo ($userInfo['gender'] ?? '') == 'Male' ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo ($userInfo['gender'] ?? '') == 'Female' ? 'selected' : ''; ?>>Female</option>
                                <option value="Other" <?php echo ($userInfo['gender'] ?? '') == 'Other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Email Address <span class="text-red-500">*</span></label>
                            <input type="email" class="form-input bg-gray-50 font-medium" name="email" value="<?php echo htmlspecialchars($userInfo['email'] ?? ''); ?>" required placeholder="e.g. email@example.com">
                        </div>
                        
                        <div>
                            <label class="form-label">ID / Passport Number <span class="text-red-500">*</span></label>
                            <input class="form-input bg-gray-50 font-medium" name="id_card" value="<?php echo htmlspecialchars($userInfo['identity_card_number'] ?? ''); ?>" required placeholder="Enter ID number">
                        </div>
                        
                        <div class="md:col-span-2">
                            <label class="form-label">Current Residence <span class="text-red-500">*</span></label>
                            <input class="form-input bg-gray-50 font-medium" name="address" value="<?php echo htmlspecialchars($userInfo['address'] ?? ''); ?>" required placeholder="Enter your full address">
                        </div>
                    </div>
                    
                    <div class="flex justify-end pt-6 border-t border-gray-100">
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-8 py-3.5 rounded-xl text-sm font-bold transition-all shadow-md hover:shadow-lg flex items-center gap-3 tracking-wide uppercase">
                            Proceed to Step 2 <i class="fa-solid fa-arrow-right"></i>
                        </button>
                    </div>
                </form>
            </div>
            
        </div>
    </main>
</div>
</body>
</html>