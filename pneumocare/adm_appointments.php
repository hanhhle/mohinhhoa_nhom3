<?php
session_start();
require 'db.php';
ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL);

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') { 
    header("Location: login.php"); 
    exit(); 
}
$adminName = $_SESSION['name'];

// Fix đường dẫn ảnh Admin: lấy từ folder img nếu session chưa có
$adminAvatar = (!empty($_SESSION['avatar'])) ? $_SESSION['avatar'] : 'img/default_admin.png';

$search = isset($_GET['search']) ? "%".$_GET['search']."%" : "%";
$filterDate = isset($_GET['date']) && !empty($_GET['date']) ? $_GET['date'] : null;
$appointments = [];

try {
    $sql = "SELECT a.*, u_p.full_name as p_name, u_p.avatar_url as p_avatar, u_d.full_name as d_name, pp.date_of_birth 
            FROM Appointments a 
            JOIN Users u_p ON a.patient_id = u_p.user_id 
            JOIN Patient_Profiles pp ON u_p.user_id = pp.patient_id 
            JOIN Users u_d ON a.doctor_id = u_d.user_id 
            WHERE a.status = 'Scheduled' AND u_p.full_name LIKE ?";
    $params = [$search];
    if ($filterDate) { $sql .= " AND a.appointment_date = ?"; $params[] = $filterDate; }
    $sql .= " ORDER BY a.appointment_date ASC, a.appointment_time ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $appointments = $stmt->fetchAll();
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
    <title>Pneumo-Care | New Appointments</title>
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
            <h1 class="text-xl font-semibold text-gray-700">Manage Appointments</h1>
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
                        <a href="adm_appointments.php" class="font-semibold text-blue-500 border-b-2 border-blue-500 pb-4 -mb-[18px]">NEW APPOINTMENTS</a>
                        <a href="adm_appointments_completed.php" class="font-semibold text-gray-400 pb-4 hover:text-gray-600">COMPLETED APPOINTMENTS</a>
                    </div>
                    <button class="bg-blue-500 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-600 transition-all"><i class="fa-solid fa-plus mr-2"></i> New Appointment</button>
                </div>

                <form method="GET" class="flex space-x-4 mb-6">
                    <div class="relative w-64">
                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-gray-400"></i>
                        <input type="text" name="search" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>" placeholder="Search patient..." class="bg-gray-50 w-full pl-10 pr-4 py-2 rounded-full text-sm focus:outline-none border border-transparent focus:border-blue-300">
                    </div>
                    <div class="relative w-48">
                        <input type="date" name="date" value="<?php echo $filterDate; ?>" class="border border-blue-200 text-blue-500 w-full px-4 py-2 rounded-full text-sm focus:outline-none">
                    </div>
                    <button type="submit" class="bg-gray-100 text-gray-600 px-4 py-2 rounded-full text-sm hover:bg-gray-200">Lọc</button>
                </form>

                <table class="w-full text-left text-sm mb-6 flex-1">
                    <thead class="text-gray-400 border-b border-gray-50 uppercase text-xs tracking-wider">
                        <tr>
                            <th class="py-3">Time</th>
                            <th class="py-3">Date</th>
                            <th class="py-3">Patient Name</th>
                            <th class="py-3 text-center">Age</th>
                            <th class="py-3">Doctor</th>
                            <th class="py-3">Action</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-600">
                        <?php if (empty($appointments)): ?>
                            <tr><td colspan="6" class="py-10 text-center text-gray-400 italic">Không tìm thấy lịch hẹn nào.</td></tr>
                        <?php else: ?>
                            <?php foreach ($appointments as $app): ?>
                                <?php 
                                    // Format lại thời gian và ngày tháng cho đẹp
                                    $timeFormatted = date("h:i A", strtotime($app['appointment_time']));
                                    $dateFormatted = date("d/m/Y", strtotime($app['appointment_date']));
                                    $age = calculateAge($app['date_of_birth']);
                                    
                                    // Tạo Avatar chữ cái (VD: Van Cuong -> VC)
                                    $nameParts = explode(' ', trim($app['p_name']));
                                    $initials = strtoupper(substr($nameParts[0], 0, 1));
                                    if (count($nameParts) > 1) {
                                        $initials .= strtoupper(substr(end($nameParts), 0, 1));
                                    }
                                ?>
                                <tr class="border-b border-gray-100 hover:bg-gray-50 transition-colors">
                                    
                                    <td class="py-4 text-blue-600 font-semibold text-sm">
                                        <?php echo $timeFormatted; ?>
                                    </td>

                                    <td class="py-4 text-gray-500 text-sm">
                                        <?php echo $dateFormatted; ?>
                                    </td>

                                    <td class="py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-full bg-[#c0ca33] text-white flex items-center justify-center text-xs font-bold shadow-sm">
                                                <?php echo $initials; ?>
                                            </div>
                                            <span class="text-gray-800 font-medium text-sm"><?php echo htmlspecialchars($app['p_name']); ?></span>
                                        </div>
                                    </td>

                                    <td class="py-4 text-gray-500 text-sm text-center">
                                        <?php echo $age; ?>
                                    </td>

                                    <td class="py-4 text-gray-600 text-sm">
                                        <?php echo htmlspecialchars($app['d_name']); ?> </td>

                                    <td class="py-4">
                                        <div class="flex items-center gap-4">
                                            <button class="text-blue-500 text-xs font-bold tracking-wide hover:underline">RESCHEDULE</button>
                                            <button class="w-7 h-7 flex items-center justify-center bg-red-50 text-red-500 rounded-md hover:bg-red-100 transition-colors">
                                                <i class="fa-solid fa-xmark"></i>
                                            </button>
                                        </div>
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