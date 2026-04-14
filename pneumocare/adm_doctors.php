<?php
session_start();
require 'db.php';
ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL);

// 1. Bảo mật: Chỉ cho phép Admin vào
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') { 
    header("Location: login.php"); 
    exit(); 
}
$adminName = $_SESSION['name'];

// Xử lý ảnh Admin từ folder img nếu session chưa có
$adminAvatar = (!empty($_SESSION['avatar']) && $_SESSION['avatar'] != 'default.png') ? $_SESSION['avatar'] : 'img/default_admin.png';

$doctors = [];
$current_doc = null;

try {
    // Lấy danh sách tất cả bác sĩ để hiện ở cột bên trái
    $doctors = $pdo->query("SELECT u.full_name, u.avatar_url, dp.* FROM Users u JOIN Doctor_Profiles dp ON u.user_id = dp.doctor_id WHERE u.role = 'Doctor'")->fetchAll();
    
    // Xác định bác sĩ đang được chọn để xem chi tiết
    $selected_id = isset($_GET['id']) ? $_GET['id'] : null;
    if (!$selected_id && !empty($doctors)) {
        $selected_id = $doctors[0]['doctor_id']; // Mặc định chọn bác sĩ đầu tiên
    }

    if ($selected_id) {
        $stmt = $pdo->prepare("SELECT u.full_name, u.avatar_url, dp.* FROM Users u JOIN Doctor_Profiles dp ON u.user_id = dp.doctor_id WHERE u.user_id = ?");
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
    <style>@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap'); body { font-family: 'Inter', sans-serif; background-color: #f4f7fa; }</style>
</head>
<body class="flex h-screen overflow-hidden text-gray-800">
    <aside class="w-64 bg-white flex flex-col shadow-sm">
        <div class="h-20 flex items-center px-6 border-b border-gray-100">
            <i class="fa-solid fa-lungs text-red-400 text-2xl mr-2"></i>
            <span class="text-xl font-semibold">Pneumo<span class="text-blue-500">-Care</span></span>
        </div>
        <nav class="flex flex-col gap-2 px-4 mt-4">
            <a href="adm_dashboard.php" class="flex items-center gap-4 px-4 py-3 text-gray-500 hover:bg-gray-50 rounded-lg">
                <i class="fa-solid fa-gauge-high w-6 text-center"></i> <span>Dashboard</span>
            </a>
            <a href="adm_patients.php" class="flex items-center gap-4 px-4 py-3 text-gray-500 hover:bg-gray-50 rounded-lg">
                <i class="fa-solid fa-user-group w-6 text-center"></i> <span>Patients</span>
            </a>
            <a href="adm_appointments.php" class="flex items-center gap-4 px-4 py-3 text-gray-500 hover:bg-gray-50 rounded-lg">
                <i class="fa-solid fa-file-lines w-6 text-center"></i> <span>Appointments</span>
            </a>
            <a href="adm_doctors.php" class="bg-blue-50 text-blue-600 border-l-4 border-blue-500 flex items-center gap-4 px-4 py-3 rounded-lg font-medium">
                <i class="fa-solid fa-user-doctor w-6 text-center"></i> <span>Doctors</span>
            </a>
            <a href="adm_messages.php" class="flex items-center gap-4 px-4 py-3 text-gray-500 hover:bg-gray-50 rounded-lg">
                <i class="fa-solid fa-message w-6 text-center"></i> <span>Messages</span>
            </a>
        </nav>
        <div class="mt-auto p-4 border-t border-gray-100">
            <a href="logout.php" class="flex items-center gap-4 text-gray-500 font-medium hover:text-red-500 px-4 py-3 transition-colors">
                <i class="fa-solid fa-right-from-bracket"></i> Logout
            </a>
        </div>
    </aside>

    <main class="flex-1 flex flex-col overflow-hidden">
        <header class="h-20 bg-white/50 backdrop-blur-sm flex items-center justify-between px-8">
            <h1 class="text-xl font-semibold text-gray-700">Doctor's Information</h1>
            <div class="flex items-center space-x-3">
                <img src="<?php echo $adminAvatar; ?>" 
                    class="w-10 h-10 rounded-full border-2 border-blue-100 object-cover" 
                    alt="Admin Avatar">
                <div>
                    <p class="text-sm font-semibold text-gray-700"><?php echo htmlspecialchars($adminName); ?></p>
                    <p class="text-xs text-gray-400">System Administrator</p>
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