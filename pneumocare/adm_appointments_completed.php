<?php
// ==========================================
// TÊN FILE: adm_appointments_completed.php
// CHỨC NĂNG: Xem lịch sử khám đã xong và nhắc thanh toán
// ==========================================
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
    // 2. Truy vấn lịch hẹn đã hoàn thành + lấy thêm patient_id để gửi tin nhắn
    $stmt = $pdo->prepare("
        SELECT a.*, u_p.user_id as patient_id, u_p.full_name as p_name, u_p.avatar_url as p_avatar, pp.date_of_birth, u_d.full_name as d_name
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

// Xử lý đường dẫn ảnh Admin
$adminAvatar = (!empty($_SESSION['avatar']) && $_SESSION['avatar'] != 'default.png') ? $_SESSION['avatar'] : 'img/default_admin.png';
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pneumo-Care | Completed Appointments</title>
    
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
            <a href="adm_appointments.php" class="sidebar-active flex items-center gap-4 px-4 py-3 rounded-xl font-semibold transition-colors">
                <i class="fa-solid fa-file-lines w-5 text-center text-xl"></i><span>Appointments</span>
            </a>
            <a href="adm_doctors.php" class="flex items-center gap-4 px-4 py-3 text-gray-500 hover:bg-gray-50 hover:text-gray-800 rounded-xl transition-colors font-medium">
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
                <h1>Manage Appointments</h1>
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

        <div class="content-area max-w-7xl mx-auto w-full">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 flex flex-col min-h-full overflow-hidden">
                
                <div class="px-8 py-6 border-b border-gray-100 flex justify-between items-center bg-gray-50/30">
                    <div class="flex gap-8">
                        <a href="adm_appointments.php" class="pb-2 text-[13px] font-bold text-gray-400 hover:text-blue-500 uppercase tracking-widest transition-colors">NEW APPOINTMENTS</a>
                        <a href="adm_appointments_completed.php" class="pb-2 text-[13px] font-bold text-blue-600 border-b-2 border-blue-600 uppercase tracking-widest transition-colors">COMPLETED APPOINTMENTS</a>
                    </div>
                </div>

                <div class="flex-1 overflow-auto px-8 pb-4">
                    <table class="w-full text-left text-sm mt-4">
                        <thead class="text-gray-400 border-b border-gray-100 uppercase text-[11px] tracking-widest">
                            <tr>
                                <th class="py-4 font-semibold w-[10%]">Time</th>
                                <th class="py-4 font-semibold w-[12%]">Date</th>
                                <th class="py-4 font-semibold w-[22%]">Patient Name</th>
                                <th class="py-4 font-semibold w-[8%] text-center">Age</th>
                                <th class="py-4 font-semibold w-[18%]">Doctor</th>
                                <th class="py-4 font-semibold w-[12%]">Fee Status</th>
                                <th class="py-4 font-semibold w-[18%] text-right pr-4">Action</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-600">
                            <?php if (empty($completed)): ?>
                                <tr><td colspan="7" class="py-16 text-center text-gray-400 italic">No completed appointments found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($completed as $row): ?>
                                    <?php 
                                        $nameParts = explode(' ', trim($row['p_name']));
                                        $initials = strtoupper(substr($nameParts[0], 0, 1));
                                        if (count($nameParts) > 1) {
                                            $initials .= strtoupper(substr(end($nameParts), 0, 1));
                                        }
                                    ?>
                                    <tr class="border-b border-gray-50 hover:bg-blue-50/20 transition-colors">
                                        <td class="py-4 text-blue-600 font-bold text-sm"><?php echo date('h:i A', strtotime($row['appointment_time'])); ?></td>
                                        <td class="py-4 text-gray-500 font-medium text-sm"><?php echo date('d/m/Y', strtotime($row['appointment_date'])); ?></td>
                                        <td class="py-4">
                                            <div class="flex items-center gap-3">
                                                <div class="w-8 h-8 rounded-full bg-[#c0ca33] text-white flex items-center justify-center text-xs font-bold shadow-sm">
                                                    <?php echo $initials; ?>
                                                </div>
                                                <span class="text-gray-800 font-medium text-sm"><?php echo htmlspecialchars($row['p_name']); ?></span>
                                            </div>
                                        </td>
                                        <td class="py-4 text-gray-500 text-sm text-center font-medium"><?php echo calculateAge($row['date_of_birth']); ?></td>
                                        <td class="py-4 text-gray-600 text-sm font-medium">Dr. <?php echo htmlspecialchars($row['d_name']); ?> </td>
                                        <td class="py-4">
                                            <?php if($row['fee_status'] == 'Paid'): ?>
                                                <span class="bg-green-50 text-green-600 border border-green-100 px-3 py-1.5 rounded-full text-[10px] font-extrabold uppercase tracking-widest shadow-sm">Paid</span>
                                            <?php else: ?>
                                                <span class="bg-red-50 text-red-500 border border-red-100 px-3 py-1.5 rounded-full text-[10px] font-extrabold uppercase tracking-widest shadow-sm">Unpaid</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-4 text-right pr-4">
                                            <?php if($row['fee_status'] == 'Unpaid'): ?>
                                                <a href="adm_messages.php?receiver_id=<?php echo $row['patient_id']; ?>" 
                                                   class="inline-flex items-center justify-end gap-2 text-blue-600 hover:text-blue-800 font-bold text-[11px] uppercase tracking-widest transition-all group whitespace-nowrap"
                                                   title="Send a message to remind payment">
                                                    Request Fee <i class="fa-solid fa-paper-plane text-[10px] transform group-hover:translate-x-1 transition-transform"></i>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-gray-300 italic text-[11px] font-bold uppercase tracking-widest cursor-default whitespace-nowrap"><i class="fa-solid fa-check-double mr-1"></i> Settled</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>
</body>
</html>