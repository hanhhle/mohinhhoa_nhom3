<?php
session_start();
require 'db.php';
ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL);

// 1. Bảo mật: Kiểm tra quyền Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') { 
    header("Location: login.php"); 
    exit(); 
}

$adminName = $_SESSION['name'];
// Xử lý ảnh Admin: Ưu tiên ảnh Session, nếu trống lấy ảnh mặc định trong folder img
$adminAvatar = (!empty($_SESSION['avatar']) && $_SESSION['avatar'] != 'default.png') ? $_SESSION['avatar'] : 'img/default_admin.png';

$patients = [];

try {
    // 2. Truy vấn lấy dữ liệu bệnh nhân thật từ DB
    $stmt = $pdo->query("
        SELECT u.user_id, u.full_name, u.email, u.avatar_url, 
               pp.date_of_birth, pp.gender, pp.blood_group, pp.phone_number 
        FROM Users u 
        JOIN Patient_Profiles pp ON u.user_id = pp.patient_id 
        WHERE u.role = 'Patient' AND u.is_active = 1
    ");
    $patients = $stmt->fetchAll();
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
    <title>Pneumo-Care | Patient Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap'); body { font-family: 'Inter', sans-serif; background-color: #f4f7fa; }</style>
</head>
<body class="flex h-screen overflow-hidden text-gray-800">
    <aside class="w-64 bg-white flex flex-col shadow-sm border-r">
        <div class="h-20 flex items-center px-6 border-b border-gray-100">
            <i class="fa-solid fa-lungs text-red-400 text-2xl mr-2"></i>
            <span class="text-xl font-semibold">Pneumo<span class="text-blue-500">-Care</span></span>
        </div>
        <nav class="flex flex-col gap-2 px-4 mt-4">
            <a href="adm_dashboard.php" class="flex items-center gap-4 px-4 py-3 text-gray-500 hover:bg-gray-50 rounded-lg transition-all">
                <i class="fa-solid fa-gauge-high w-6 text-center"></i> <span>Dashboard</span>
            </a>
            <a href="adm_patients.php" class="bg-blue-50 text-blue-600 border-l-4 border-blue-500 flex items-center gap-4 px-4 py-3 rounded-lg font-medium">
                <i class="fa-solid fa-user-group w-6 text-center"></i> <span>Patients</span>
            </a>
            <a href="adm_appointments.php" class="flex items-center gap-4 px-4 py-3 text-gray-500 hover:bg-gray-50 rounded-lg transition-all">
                <i class="fa-solid fa-file-lines w-6 text-center"></i> <span>Appointments</span>
            </a>
            <a href="adm_doctors.php" class="flex items-center gap-4 px-4 py-3 text-gray-500 hover:bg-gray-50 rounded-lg transition-all">
                <i class="fa-solid fa-user-doctor w-6 text-center"></i> <span>Doctors</span>
            </a>
            <a href="adm_messages.php" class="flex items-center gap-4 px-4 py-3 text-gray-500 hover:bg-gray-50 rounded-lg transition-all">
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
        <header class="h-20 bg-white border-b border-gray-100 flex items-center justify-between px-8">
            <h1 class="text-xl font-semibold text-gray-700">Patient Details</h1>
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

        <div class="flex-1 p-8 overflow-auto">
            <div class="bg-white rounded-xl shadow-sm p-6 flex flex-col min-h-full">
                <div class="flex justify-between items-center border-b border-gray-100 mb-6 pb-4">
                    <span class="font-semibold text-gray-800 border-b-2 border-blue-500 pb-4 -mb-[18px]">Patient Info</span>
                </div>

                <table class="w-full text-left text-sm mt-4">
                    <thead class="text-gray-400 border-b border-gray-50 uppercase text-xs tracking-wider">
                        <tr>
                            <th class="py-3">Patient Name</th>
                            <th class="py-3">Age</th>
                            <th class="py-3">Gender</th>
                            <th class="py-3">Blood</th>
                            <th class="py-3">Phone</th>
                            <th class="py-3">Email</th>
                            <th class="py-3 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-600">
                        <?php if (empty($patients)): ?>
                            <tr><td colspan="7" class="py-10 text-center text-gray-400 italic">Chưa có bệnh nhân nào trong hệ thống.</td></tr>
                        <?php else: ?>
                            <?php foreach ($patients as $p): ?>
                            <tr class="border-b border-gray-50 hover:bg-gray-50 transition-colors">
                                <td class="py-4 flex items-center">
                                    <img src="<?php echo $p['avatar_url'] ?: 'img/default.png'; ?>" class="w-9 h-9 rounded-full mr-3 object-cover shadow-sm">
                                    <span class="font-medium text-gray-700"><?php echo htmlspecialchars($p['full_name']); ?></span>
                                </td>
                                <td class="py-4"><?php echo calculateAge($p['date_of_birth']); ?></td>
                                <td class="py-4"><?php echo $p['gender']; ?></td>
                                <td class="py-4"><span class="px-2 py-1 bg-red-50 text-red-500 rounded text-[10px] font-bold"><?php echo $p['blood_group']; ?></span></td>
                                <td class="py-4"><?php echo $p['phone_number']; ?></td>
                                <td class="py-4 italic text-xs"><?php echo $p['email']; ?></td>
                                <td class="py-4 text-center space-x-2">
                                    <button title="Gửi tin nhắn" class="text-blue-500 hover:bg-blue-50 w-8 h-8 rounded-md border border-blue-100 transition-colors"><i class="fa-solid fa-message"></i></button>
                                    <a href="delete_patient.php?id=<?php echo $p['user_id']; ?>" onclick="return confirm('Xóa bệnh nhân này?')" class="text-red-400 hover:bg-red-50 w-8 h-8 rounded-md border border-red-100 inline-flex items-center justify-center transition-colors"><i class="fa-solid fa-xmark"></i></a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</body>
</html>