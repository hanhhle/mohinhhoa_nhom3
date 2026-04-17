<?php
// ==========================================
// TÊN FILE: adm_patients.php
// CHỨC NĂNG: Quản lý Bệnh nhân (Nhắn tin, Xóa, Chặn đặt lịch nếu nợ phí, Tìm kiếm)
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
$adminAvatar = (!empty($_SESSION['avatar']) && $_SESSION['avatar'] != 'default.png') ? $_SESSION['avatar'] : 'img/default_admin.png';

$patients = [];
$msg = "";

// --- XỬ LÝ BLOCK / UNBLOCK ĐẶT LỊCH (is_active: 1 = Bình thường, 2 = Bị chặn đặt lịch do nợ phí) ---
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $u_id = $_GET['id'];
    
    try {
        if ($action == 'block') {
            $stmt = $pdo->prepare("UPDATE Users SET is_active = 2 WHERE user_id = ? AND role = 'Patient'");
            $stmt->execute([$u_id]);
            $msg = "<div class='bg-yellow-50 text-yellow-700 p-4 rounded-xl mb-6 border border-yellow-200 text-sm font-medium flex items-center gap-2'><i class='fa-solid fa-lock'></i> Patient has been restricted from booking new appointments.</div>";
        } elseif ($action == 'unblock') {
            $stmt = $pdo->prepare("UPDATE Users SET is_active = 1 WHERE user_id = ? AND role = 'Patient'");
            $stmt->execute([$u_id]);
            $msg = "<div class='bg-green-50 text-green-700 p-4 rounded-xl mb-6 border border-green-200 text-sm font-medium flex items-center gap-2'><i class='fa-solid fa-unlock'></i> Patient booking access restored.</div>";
        }
    } catch (PDOException $e) {
        $msg = "<div class='bg-red-50 text-red-600 p-4 rounded-xl mb-6 border border-red-200 text-sm'>Lỗi: " . $e->getMessage() . "</div>";
    }
}

try {
    // 2. Truy vấn lấy dữ liệu bệnh nhân + Kiểm tra xem có nợ phí (Unpaid) hay không
    $stmt = $pdo->query("
        SELECT u.user_id, u.full_name, u.email, u.avatar_url, u.is_active,
               pp.date_of_birth, pp.gender, pp.blood_group, pp.phone_number,
               (SELECT COUNT(*) FROM Appointments a WHERE a.patient_id = u.user_id AND a.status = 'Completed' AND a.fee_status = 'Unpaid') as unpaid_count
        FROM Users u 
        JOIN Patient_Profiles pp ON u.user_id = pp.patient_id 
        WHERE u.role = 'Patient' AND u.is_active IN (1, 2)
        ORDER BY unpaid_count DESC, u.full_name ASC
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
<body class="flex h-screen overflow-hidden text-gray-800">
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
            <a href="adm_patients.php" class="sidebar-active flex items-center gap-4 px-4 py-3 rounded-xl font-semibold transition-colors">
                <i class="fa-solid fa-user-group w-5 text-center text-xl"></i><span>Patients</span>
            </a>
            <a href="adm_appointments.php" class="flex items-center gap-4 px-4 py-3 text-gray-500 hover:bg-gray-50 hover:text-gray-800 rounded-xl transition-colors font-medium">
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
                <h1>Patient Management</h1>
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
            <?php echo $msg; ?>
            
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 flex flex-col min-h-full overflow-hidden">
                <div class="px-8 py-6 border-b border-gray-100 flex justify-between items-center bg-gray-50/30">
                    <div class="relative">
                        <input type="text" id="searchPatient" placeholder="Search patients..." class="w-64 bg-white border border-gray-200 rounded-xl py-2 px-4 pl-10 text-sm focus:border-blue-400 outline-none transition-colors shadow-sm">
                        <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    </div>
                </div>

                <div class="flex-1 overflow-auto px-8 pb-4">
                    <table class="w-full text-left text-sm mt-4">
                        <thead class="text-gray-400 border-b border-gray-100 uppercase text-[11px] tracking-widest">
                            <tr>
                                <th class="py-4 font-semibold w-[25%]">Patient Name</th>
                                <th class="py-4 font-semibold w-[10%]">Age</th>
                                <th class="py-4 font-semibold w-[10%]">Gender</th>
                                <th class="py-4 font-semibold w-[15%]">Contact</th>
                                <th class="py-4 font-semibold w-[20%] text-center">Payment Status</th>
                                <th class="py-4 font-semibold w-[20%] text-right pr-4">Action</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-600" id="patientTableBody">
                            <?php if (empty($patients)): ?>
                                <tr><td colspan="6" class="py-16 text-center text-gray-400 italic">Chưa có bệnh nhân nào trong hệ thống.</td></tr>
                            <?php else: ?>
                                <?php foreach ($patients as $p): ?>
                                <tr class="border-b border-gray-50 hover:bg-blue-50/30 transition-colors patient-row">
                                    <td class="py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="relative">
                                                <img src="<?php echo $p['avatar_url'] ?: 'img/default.png'; ?>" class="w-10 h-10 rounded-full object-cover shadow-sm border border-gray-100">
                                                <?php if($p['is_active'] == 2): ?>
                                                    <span class="absolute -bottom-1 -right-1 bg-white rounded-full p-[2px]"><i class="fa-solid fa-lock text-red-500 text-[10px]"></i></span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="flex flex-col">
                                                <span class="font-bold text-gray-800 <?php echo $p['is_active'] == 2 ? 'text-gray-400 line-through' : ''; ?>"><?php echo htmlspecialchars($p['full_name']); ?></span>
                                                <span class="text-[10px] text-gray-400 mt-0.5"><i class="fa-solid fa-droplet text-red-400 mr-1"></i><?php echo $p['blood_group']; ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-4 font-medium text-gray-700"><?php echo calculateAge($p['date_of_birth']); ?> yrs</td>
                                    <td class="py-4 font-medium text-gray-700"><?php echo $p['gender']; ?></td>
                                    <td class="py-4">
                                        <p class="font-semibold text-gray-700"><i class="fa-solid fa-phone text-gray-400 text-xs w-4"></i> <?php echo $p['phone_number']; ?></p>
                                        <p class="italic text-[11px] text-gray-400 mt-1 truncate max-w-[150px]" title="<?php echo htmlspecialchars($p['email']); ?>"><?php echo htmlspecialchars($p['email']); ?></p>
                                    </td>
                                    
                                    <td class="py-4 text-center">
                                        <?php if($p['unpaid_count'] > 0): ?>
                                            <span class="bg-yellow-50 text-yellow-600 border border-yellow-200 px-3 py-1.5 rounded-full text-[10px] font-extrabold uppercase tracking-widest shadow-sm cursor-help" title="Có <?php echo $p['unpaid_count']; ?> hóa đơn chưa thanh toán">
                                                Debt: <?php echo $p['unpaid_count']; ?> bill(s)
                                            </span>
                                        <?php else: ?>
                                            <span class="text-green-500 text-xs font-bold"><i class="fa-solid fa-check"></i> Clear</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td class="py-4 text-right pr-4 space-x-1.5">
                                        <a href="adm_messages.php?receiver_id=<?php echo $p['user_id']; ?>" title="Send Message" class="inline-flex items-center justify-center w-8 h-8 bg-blue-50 text-blue-500 hover:bg-blue-600 hover:text-white rounded-lg border border-blue-100 transition-all shadow-sm">
                                            <i class="fa-solid fa-message text-sm"></i>
                                        </a>

                                        <?php if($p['unpaid_count'] > 0 && $p['is_active'] == 1): ?>
                                            <a href="?action=block&id=<?php echo $p['user_id']; ?>" onclick="return confirm('Block this patient from booking new appointments?')" title="Block Booking Access" class="inline-flex items-center justify-center w-8 h-8 bg-orange-50 text-orange-500 hover:bg-orange-500 hover:text-white rounded-lg border border-orange-100 transition-all shadow-sm">
                                                <i class="fa-solid fa-lock text-sm"></i>
                                            </a>
                                        <?php elseif($p['is_active'] == 2): ?>
                                            <a href="?action=unblock&id=<?php echo $p['user_id']; ?>" onclick="return confirm('Restore booking access for this patient?')" title="Unlock Booking Access" class="inline-flex items-center justify-center w-8 h-8 bg-green-50 text-green-500 hover:bg-green-500 hover:text-white rounded-lg border border-green-100 transition-all shadow-sm">
                                                <i class="fa-solid fa-unlock text-sm"></i>
                                            </a>
                                        <?php endif; ?>

                                        <a href="delete_patient.php?id=<?php echo $p['user_id']; ?>" onclick="return confirm('Xóa bệnh nhân này?')" title="Delete Patient" class="inline-flex items-center justify-center w-8 h-8 bg-red-50 text-red-500 hover:bg-red-600 hover:text-white rounded-lg border border-red-100 transition-all shadow-sm">
                                            <i class="fa-solid fa-trash-can text-sm"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            
                            <tr id="empty-search" style="display: none;">
                                <td colspan="6" class="py-16 text-center text-gray-400 italic font-medium">No matching patients found.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
    document.getElementById('searchPatient').addEventListener('input', function(e) {
        const term = e.target.value.toLowerCase();
        const rows = document.querySelectorAll('.patient-row');
        let visibleCount = 0;
        
        rows.forEach(row => {
            // Lấy toàn bộ text trong thẻ tr (bao gồm cả Tên, SĐT, Email) để tìm kiếm
            const textContent = row.textContent.toLowerCase();
            
            if (textContent.includes(term)) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        // Hiển thị thông báo nếu không tìm thấy ai
        const emptyMsg = document.getElementById('empty-search');
        if (emptyMsg) {
            emptyMsg.style.display = (visibleCount === 0 && rows.length > 0) ? '' : 'none';
        }
    });
</script>

</body>
</html>