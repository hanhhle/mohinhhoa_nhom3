<?php
// ==========================================
// TÊN FILE: adm_doctors.php
// CHỨC NĂNG: Admin xem danh sách và hồ sơ chi tiết Bác sĩ
// ==========================================
session_start();
require 'db.php';
ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL);

// 1. Bảo mật: Chỉ cho phép Admin vào
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') { 
    header("Location: login.php"); 
    exit(); 
}
$adminName = $_SESSION['name'];

// Xử lý ảnh Admin
$adminAvatar = (!empty($_SESSION['avatar']) && $_SESSION['avatar'] != 'default.png') ? $_SESSION['avatar'] : 'img/default_admin.png';

$doctors = [];
$current_doc = null;

try {
    // Lấy danh sách tất cả bác sĩ để hiện ở cột bên trái
    $doctors = $pdo->query("SELECT u.user_id, u.full_name, u.avatar_url, dp.* FROM Users u JOIN Doctor_Profiles dp ON u.user_id = dp.doctor_id WHERE u.role = 'Doctor' ORDER BY u.full_name ASC")->fetchAll();
    
    // Xác định bác sĩ đang được chọn để xem chi tiết
    $selected_id = isset($_GET['id']) ? $_GET['id'] : null;
    if (!$selected_id && !empty($doctors)) {
        $selected_id = $doctors[0]['user_id']; // Mặc định chọn bác sĩ đầu tiên
    }

    if ($selected_id) {
        $stmt = $pdo->prepare("SELECT u.user_id, u.full_name, u.avatar_url, dp.* FROM Users u JOIN Doctor_Profiles dp ON u.user_id = dp.doctor_id WHERE u.user_id = ?");
        $stmt->execute([$selected_id]);
        $current_doc = $stmt->fetch();
    }
} catch (PDOException $e) {
    die("<div style='color:red; padding:20px; background:#fee2e2; border:1px solid #ef4444; margin:20px;'><b>Lỗi DB:</b> " . $e->getMessage() . "</div>");
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
            <a href="adm_appointments.php" class="flex items-center gap-4 px-4 py-3 text-gray-500 hover:bg-gray-50 hover:text-gray-800 rounded-xl transition-colors font-medium">
                <i class="fa-solid fa-file-lines w-5 text-center text-xl"></i><span>Appointments</span>
            </a>
            <a href="adm_doctors.php" class="sidebar-active flex items-center gap-4 px-4 py-3 rounded-xl font-semibold transition-colors">
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
                <h1>Doctor Directory</h1>
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

        <div class="content-area max-w-7xl mx-auto w-full flex gap-8">
            
            <div class="w-[320px] bg-white rounded-2xl shadow-sm border border-gray-100 flex flex-col h-full overflow-hidden flex-shrink-0">
                <div class="px-6 py-5 border-b border-gray-100 bg-gray-50/30">
                    <h2 class="font-bold text-gray-800 text-[13px] uppercase tracking-widest">Doctors List</h2>
                </div>
                
                <div class="flex-1 overflow-y-auto p-4 space-y-3">
                    <?php if (empty($doctors)): ?>
                        <div class="text-center py-10 text-gray-400 italic text-sm">No doctors found.</div>
                    <?php else: ?>
                        <?php foreach ($doctors as $doc): ?>
                        <a href="?id=<?php echo $doc['user_id']; ?>" class="block border <?php echo $selected_id == $doc['user_id'] ? 'border-blue-400 bg-blue-50/50 shadow-sm' : 'border-gray-100 hover:border-gray-300 hover:bg-gray-50'; ?> rounded-xl p-4 flex flex-col items-center transition-all group">
                            <img src="<?php echo $doc['avatar_url'] ?: 'img/default.png'; ?>" class="w-16 h-16 rounded-full mb-3 shadow-sm object-cover border-2 <?php echo $selected_id == $doc['user_id'] ? 'border-blue-200' : 'border-white'; ?>">
                            <h3 class="font-bold text-gray-800 text-center text-sm group-hover:text-blue-600 transition-colors">Dr. <?php echo htmlspecialchars($doc['full_name']); ?></h3>
                            <p class="text-[11px] font-semibold text-gray-400 uppercase tracking-widest mt-1 text-center"><?php echo htmlspecialchars($doc['speciality']); ?></p>
                            
                            <?php if ($selected_id != $doc['user_id']): ?>
                                <span class="mt-3 text-[10px] font-bold text-blue-500 opacity-0 group-hover:opacity-100 transition-opacity uppercase tracking-widest">View Profile <i class="fa-solid fa-arrow-right ml-1"></i></span>
                            <?php endif; ?>
                        </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($current_doc): ?>
            <div class="flex-1 bg-white rounded-2xl shadow-sm border border-gray-100 p-10 h-full overflow-y-auto relative">
                
                <a href="adm_messages.php?receiver_id=<?php echo $current_doc['user_id']; ?>" class="absolute top-10 right-10 bg-blue-600 text-white px-5 py-2.5 rounded-xl text-xs font-bold hover:bg-blue-700 transition-all shadow-md uppercase tracking-wide flex items-center gap-2 group">
                    <i class="fa-solid fa-message"></i> Message Doctor
                </a>

                <div class="flex items-start gap-8 mb-8">
                    <img src="<?php echo $current_doc['avatar_url'] ?: 'img/default.png'; ?>" class="w-32 h-32 rounded-full border-4 border-gray-50 shadow-sm object-cover">
                    <div class="pt-2">
                        <h1 class="text-3xl font-extrabold text-[#003366] mb-2 tracking-tight">Dr. <?php echo htmlspecialchars($current_doc['full_name']); ?></h1>
                        <p class="text-sm font-semibold text-blue-600 uppercase tracking-widest mb-3"><?php echo htmlspecialchars($current_doc['speciality']); ?></p>
                        <div class="flex items-center gap-1.5 text-yellow-400 text-sm bg-yellow-50 px-3 py-1.5 rounded-lg inline-flex border border-yellow-100">
                            <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i> 
                            <span class="text-yellow-700 font-bold ml-1">5.0</span>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-x-12 gap-y-10">
                    
                    <div class="border border-gray-100 rounded-2xl p-6 bg-gray-50/30">
                        <h3 class="font-bold text-gray-800 text-[13px] uppercase tracking-widest flex items-center gap-2 mb-5 border-b border-gray-200 pb-3">
                            <i class="fa-solid fa-graduation-cap text-blue-500 text-lg"></i> Education
                        </h3>
                        <div class="space-y-4">
                            <div>
                                <h4 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Undergraduate</h4>
                                <p class="text-sm font-semibold text-gray-700"><?php echo htmlspecialchars($current_doc['undergraduate_edu']); ?></p>
                            </div>
                            <div>
                                <h4 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Medical Degree</h4>
                                <p class="text-sm font-semibold text-gray-700"><?php echo htmlspecialchars($current_doc['medical_edu']); ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="border border-gray-100 rounded-2xl p-6 bg-gray-50/30">
                        <h3 class="font-bold text-gray-800 text-[13px] uppercase tracking-widest flex items-center gap-2 mb-5 border-b border-gray-200 pb-3">
                            <i class="fa-solid fa-certificate text-green-500 text-lg"></i> Training & Certifications
                        </h3>
                        <p class="text-sm text-gray-700 font-medium leading-relaxed bg-white p-4 rounded-xl border border-gray-100 shadow-sm">
                            <?php echo nl2br(htmlspecialchars($current_doc['training'])); ?>
                        </p>
                    </div>

                    <div class="col-span-2 border border-gray-100 rounded-2xl p-8 bg-blue-50/30">
                        <h3 class="font-bold text-[#003366] text-[13px] uppercase tracking-widest flex items-center gap-2 mb-5 border-b border-blue-100 pb-3">
                            <i class="fa-regular fa-address-card text-blue-500 text-lg"></i> About the Doctor
                        </h3>
                        <div class="text-sm text-gray-700 font-medium leading-relaxed">
                            <?php echo nl2br(htmlspecialchars($current_doc['bio'])); ?>
                        </div>
                    </div>

                </div>
            </div>
            <?php else: ?>
                <div class="flex-1 bg-white rounded-2xl shadow-sm border border-gray-100 p-10 flex flex-col items-center justify-center text-gray-400">
                    <i class="fa-solid fa-user-doctor text-6xl text-gray-200 mb-4"></i>
                    <p class="font-medium text-lg text-gray-500">Select a doctor from the list</p>
                    <p class="text-sm mt-1">View their full profile, education, and message them directly.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>
</body>
</html>