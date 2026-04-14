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
$completed = [];

try {
    // 2. Truy vấn lịch hẹn đã hoàn thành
    $stmt = $pdo->prepare("
        SELECT a.*, u_p.full_name as p_name, u_p.avatar_url as p_avatar, pp.date_of_birth, u_d.full_name as d_name
        FROM Appointments a
        JOIN Users u_p ON a.patient_id = u_p.user_id
        JOIN Patient_Profiles pp ON u_p.user_id = pp.patient_id
        JOIN Users u_d ON a.doctor_id = u_d.user_id
        WHERE a.status = 'Completed'
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
    ");
    $stmt->execute();
    $completed = $stmt->fetchAll();
} catch (PDOException $e) {
    die("<div style='color:red; padding:20px; background:#fee2e2; border:1px solid #ef4444; margin:20px;'><b>Lỗi DB:</b> " . $e->getMessage() . "</div>");
}

function calculateAge($birthDate) { 
    if(!$birthDate) return "N/A";
    return date_diff(date_create($birthDate), date_create('today'))->y; 
}

// Xử lý đường dẫn ảnh Admin: Nếu session trống thì lấy ảnh default trong folder img
$adminAvatar = (!empty($_SESSION['avatar'])) ? $_SESSION['avatar'] : 'img/default_admin.png';
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pneumo-Care | Completed Appointments</title>
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
            <a href="adm_appointments.php" class="bg-blue-50 text-blue-600 border-l-4 border-blue-500 flex items-center gap-4 px-4 py-3 rounded-lg font-medium">
                <i class="fa-solid fa-file-lines w-6 text-center"></i> <span>Appointments</span>
            </a>
            <a href="adm_doctors.php" class="flex items-center gap-4 px-4 py-3 text-gray-500 hover:bg-gray-50 rounded-lg">
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
            <h1 class="text-xl font-semibold text-gray-700">History</h1>
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
            <div class="bg-white rounded-xl shadow-sm p-6 relative min-h-full flex flex-col">
                <div class="flex justify-between items-center border-b border-gray-100 mb-6 pb-4">
                    <div class="flex space-x-8">
                        <a href="adm_appointments.php" class="font-semibold text-gray-400 pb-4 hover:text-blue-500">NEW APPOINTMENTS</a>
                        <a href="adm_appointments_completed.php" class="font-semibold text-blue-500 border-b-2 border-blue-500 pb-4 -mb-[18px]">COMPLETED APPOINTMENTS</a>
                    </div>
                </div>

                <table class="w-full text-left text-sm mb-6 flex-1">
                    <thead class="text-gray-400 border-b border-gray-50 uppercase text-xs">
                        <tr>
                            <th class="py-3 font-medium">Time</th>
                            <th class="py-3 font-medium">Date</th>
                            <th class="py-3 font-medium">Patient Name</th>
                            <th class="py-3 text-center">Age</th>
                            <th class="py-3">Doctor</th>
                            <th class="py-3">Fee Status</th>
                            <th class="py-3">User Action</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-600">
                        <?php if (empty($completed)): ?>
                            <tr><td colspan="7" class="py-10 text-center text-gray-400 italic">Chưa có lịch hẹn nào hoàn thành.</td></tr>
                        <?php else: ?>
                            <?php foreach ($completed as $row): ?>
                            <tr class="border-b border-gray-50 hover:bg-gray-50 transition-colors">
                                <td class="py-4 font-medium"><?php echo date('h:i A', strtotime($row['appointment_time'])); ?></td>
                                <td class="py-4"><?php echo date('d/m/Y', strtotime($row['appointment_date'])); ?></td>
                                <td class="flex items-center py-4">
                                    <img src="<?php echo $row['p_avatar'] ?: 'https://ui-avatars.com/api/?name='.urlencode($row['p_name']); ?>" class="w-8 h-8 rounded-full mr-3 object-cover shadow-sm">
                                    <?php echo htmlspecialchars($row['p_name']); ?>
                                </td>
                                <td class="text-center py-4"><?php echo calculateAge($row['date_of_birth']); ?></td>
                                <td class="py-4">Dr. <?php echo htmlspecialchars($row['d_name']); ?></td>
                                <td class="py-4 font-semibold <?php echo $row['fee_status'] == 'Paid' ? 'text-green-500' : 'text-red-400'; ?>">
                                    <?php echo $row['fee_status']; ?>
                                </td>
                                <td class="py-4">
                                    <?php if($row['fee_status'] == 'Unpaid'): ?>
                                        <a href="adm_request_fee.php?id=<?php echo $row['appointment_id']; ?>" class="text-blue-500 hover:underline font-medium">Request Fee</a>
                                    <?php else: ?>
                                        <span class="text-gray-400 italic text-xs">Settled</span>
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
</body>
</html>