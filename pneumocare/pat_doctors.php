<?php
// ==========================================
// TÊN FILE: pat_doctors.php
// CHỨC NĂNG: Bệnh nhân xem danh sách và thông tin Bác sĩ
// ==========================================
session_start();
require 'db.php';
ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL);

// 1. Bảo mật: Chỉ cho phép Patient vào
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Patient') { 
    header("Location: login.php"); 
    exit(); 
}

$patientId = $_SESSION['user_id'];
$patientName = $_SESSION['name'];
$patientAvatar = (!empty($_SESSION['avatar']) && $_SESSION['avatar'] != 'default.png') ? $_SESSION['avatar'] : 'img/default_patient.png';

$doctors = [];
$current_doc = null;

try {
    // Lấy danh sách tất cả bác sĩ để hiện ở cột bên trái
    $doctors = $pdo->query("
        SELECT u.full_name, u.avatar_url, dp.* FROM Users u 
        JOIN Doctor_Profiles dp ON u.user_id = dp.doctor_id 
        WHERE u.role = 'Doctor' AND u.is_active = 1
    ")->fetchAll();
    
    // Xác định bác sĩ đang được chọn để xem chi tiết
    $selected_id = isset($_GET['id']) ? $_GET['id'] : null;
    
    // Nếu chưa chọn ai và danh sách có người, mặc định chọn người đầu tiên
    if (!$selected_id && !empty($doctors)) {
        $selected_id = $doctors[0]['doctor_id']; 
    }

    // Lấy thông tin chi tiết của bác sĩ đang được chọn
    if ($selected_id) {
        $stmt = $pdo->prepare("
            SELECT u.full_name, u.avatar_url, dp.* FROM Users u 
            JOIN Doctor_Profiles dp ON u.user_id = dp.doctor_id 
            WHERE u.user_id = ?
        ");
        $stmt->execute([$selected_id]);
        $current_doc = $stmt->fetch();
    }
} catch (PDOException $e) {
    die("<div class='bg-red-50 text-red-500 p-4 m-4 rounded'><b>Lỗi Database:</b> " . $e->getMessage() . "</div>");
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pneumo-Care | Doctor Directory</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Inter', sans-serif; 
            background-color: #f4f7fa;
            color: #1f2937;
        }

        .layout { display: flex; min-height: 100vh; overflow: hidden; }
        
        /* SIDEBAR CHUẨN DESIGN BỆNH NHÂN (RIGHT BORDER) */
        .sidebar { width: 260px; background: #ffffff; border-right: 1px solid #e5e7eb; display: flex; flex-direction: column; min-height: 100vh; flex-shrink: 0; z-index: 10; }
        .sidebar-logo { display: flex; align-items: center; padding: 22px 24px; border-bottom: 1px solid #f3f4f6; }
        .sidebar-menu { padding-top: 16px; flex: 1; display: flex; flex-direction: column; }
        
        .sidebar-item {
            display: block; padding: 16px 24px; color: #6b7280; font-size: 16px; font-weight: 500;
            text-decoration: none; border-right: 4px solid transparent; transition: all 0.2s ease;
        }
        .sidebar-item:hover { background-color: #f8fafc; color: #374151; }
        .sidebar-item.active { background-color: #eff6ff; color: #3b82f6; border-right-color: #3b82f6; font-weight: 600; }
        
        .sidebar-active { 
            background-color: #eff6ff; 
            color: #3b82f6; 
            border-left: 4px solid #3b82f6; 
        }        
        /* MAIN CONTENT */
        .main-content { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
        
        /* Topbar */
        .topbar-wrapper { padding: 32px 40px 0 40px; }
        .topbar { 
            height: 72px; background: #ffffff; border: 1px solid #f3f4f6; 
            display: flex; align-items: center; justify-content: space-between; 
            padding: 0 24px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); 
            margin-bottom: 24px;
        }
        .topbar h1 { font-size: 20px; font-weight: 600; color: #1f2937; margin: 0; }
        .topbar-right { display: flex; align-items: center; gap: 24px; height: 100%; }
        
        /* Scrollbar tùy chỉnh cho mượt */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
</head>
<body class="flex h-screen overflow-hidden text-gray-800">
    <aside class="w-64 bg-white border-r border-gray-100 flex flex-col h-full shadow-sm z-10">
        <div class="flex items-center gap-2 p-6 border-b">
            <i class="fa-solid fa-lungs text-3xl text-red-400"></i>
            <h1 class="text-xl font-semibold text-gray-700">Pneumo-<span class="text-blue-500">Care</span></h1>
        </div>

        <nav class="flex-1 px-4 py-6 space-y-2">

            <a href="pat_dashboard.php" class="flex items-center gap-4 px-4 py-3 text-gray-500 hover:bg-gray-50 hover:text-gray-800 rounded-xl transition-colors font-medium">
                <i class="fa-solid fa-gauge-high w-5 text-center text-xl"></i>
                <span>Dashboard</span>
            </a>
            
            <a href="pat_report.php" class="flex items-center gap-4 px-4 py-3 text-gray-500 hover:bg-gray-50 hover:text-gray-800 rounded-xl transition-colors font-medium">
                <i class="fa-solid fa-file-medical w-5 text-center text-xl"></i>
                <span>Report</span>
            </a>
            
            <a href="pat_appointments.php" class="flex items-center gap-4 px-4 py-3 text-gray-500 hover:bg-gray-50 hover:text-gray-800 rounded-xl transition-colors font-medium ">
                <i class="fa-solid fa-calendar-check w-5 text-center text-xl"></i>
                <span>Appointments</span>
            </a>
            
            <a href="pat_doctors.php" class="sidebar-active flex items-center gap-4 px-4 py-3 rounded-xl transition-colors font-medium">
                <i class="fa-solid fa-user-doctor w-5 text-center text-xl"></i>
                <span>Doctors</span>
            </a>
            
            <a href="pat_messages.php" class="flex items-center gap-4 px-4 py-3 text-gray-500 hover:bg-gray-50 hover:text-gray-800 rounded-xl transition-colors font-medium">
                <i class="fa-solid fa-comment-dots w-5 text-center text-xl"></i>
                <span>Messages</span>
            </a>

        </nav>

        <div class="p-6 border-t mt-auto">
            <a href="logout.php" class="flex items-center gap-4 text-gray-500 hover:text-red-500 transition-colors font-medium">
                <i class="fa-solid fa-right-from-bracket text-lg"></i>
                <span>Logout</span>
            </a>
        </div>
    </aside>

    <main class="flex-1 flex flex-col overflow-hidden">
        <header class="h-20 bg-white/50 backdrop-blur-sm flex items-center justify-between px-8">
            <h1 class="text-xl font-semibold text-gray-700">Doctor's Information</h1>
            <div class="flex items-center space-x-3">
                <img src="<?php echo $patientAvatar; ?>" 
                    class="w-10 h-10 rounded-full border-2 border-blue-100 object-cover" 
                    alt="Patient Avatar">
                <div>
                    <p class="text-sm font-semibold text-gray-700"><?php echo htmlspecialchars($patientName); ?></p>
                    <p class="text-xs text-gray-400">Patient</p>
                </div>
            </div>
        </header>

        <div class="flex-1 p-8 overflow-hidden flex gap-6">
            <div class="w-[300px] bg-white rounded-xl shadow-sm p-4 flex flex-col h-full overflow-y-auto">
                <h2 class="font-semibold text-gray-800 text-lg mb-4">Doctors List</h2>
                <div class="space-y-4">
                    <?php foreach ($doctors as $doc): ?>
                    <div class="border <?php echo $selected_id == $doc['doctor_id'] ? 'border-blue-400 bg-blue-50' : 'border-gray-200'; ?> rounded-lg p-4 flex flex-col items-center transition-all">
                        <img src="<?php echo $doc['avatar_url'] ?: 'https://ui-avatars.com/api/?name='.urlencode($doc['full_name']); ?>" class="w-16 h-16 rounded-full mb-3 shadow-sm object-cover">
                        <h3 class="font-semibold text-gray-800 text-center text-sm"><?php echo htmlspecialchars($doc['full_name']); ?></h3>
                        <p class="text-[10px] text-gray-400 mb-4"><?php echo htmlspecialchars($doc['speciality']); ?></p>
                        <a href="?id=<?php echo $doc['doctor_id']; ?>" class="text-blue-500 text-sm font-medium hover:underline text-center">View More</a>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if ($current_doc): ?>
            <div class="flex-1 bg-white rounded-xl shadow-sm p-8 h-full overflow-y-auto">
                <div class="flex justify-between items-start mb-6">
                    <div>
                        <h1 class="text-3xl font-bold text-[#003366] mb-2">Dr. <?php echo htmlspecialchars($current_doc['full_name']); ?></h1>
                        <p class="text-gray-500 text-sm max-w-xl"><span class="text-blue-500 font-medium">Speciality :</span> <?php echo htmlspecialchars($current_doc['speciality']); ?></p>
                    </div>
                    <div class="flex flex-col items-end">
                        <div class="text-yellow-400 text-lg mb-3"><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i> <span class="text-gray-600 text-sm ml-1">5/5</span></div>
                    </div>
                </div>

                <div class="border-t border-gray-100 my-6"></div>

                <div class="grid grid-cols-2 gap-12 mb-8">
                    <div>
                        <h3 class="font-semibold text-[#003366] text-lg mb-4">Education</h3>
                        <div class="mb-4">
                            <h4 class="text-blue-500 text-sm font-semibold">Undergraduate:</h4>
                            <p class="text-gray-500 text-sm"><?php echo htmlspecialchars($current_doc['undergraduate_edu']); ?></p>
                        </div>
                        <div>
                            <h4 class="text-blue-500 text-sm font-semibold">Medical Degree:</h4>
                            <p class="text-gray-500 text-sm"><?php echo htmlspecialchars($current_doc['medical_edu']); ?></p>
                        </div>
                    </div>
                    <div>
                        <h3 class="font-semibold text-[#003366] text-lg mb-4">Training</h3>
                        <p class="text-gray-500 text-sm leading-relaxed"><?php echo htmlspecialchars($current_doc['training']); ?></p>
                    </div>
                </div>

                <div class="border-t border-gray-100 my-6"></div>

                <div>
                    <h3 class="font-semibold text-[#003366] text-lg mb-4">About the Doctor</h3>
                    <p class="text-gray-500 text-sm leading-relaxed text-justify">
                        <?php echo nl2br(htmlspecialchars($current_doc['bio'])); ?>
                    </p>
                </div>
            </div>
            <?php else: ?>
                <div class="flex-1 bg-white rounded-xl shadow-sm p-8 flex items-center justify-center italic text-gray-400">Chọn một bác sĩ để xem chi tiết.</div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>