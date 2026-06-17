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

function getInitials($fullName) {
    $words = explode(' ', trim($fullName));
    $count = count($words);
    if ($count >= 2) {
        $first = mb_substr($words[$count - 2], 0, 1, 'UTF-8');
        $second = mb_substr($words[$count - 1], 0, 1, 'UTF-8');
        return mb_strtoupper($first . $second, 'UTF-8');
    } elseif ($count == 1) {
        return mb_strtoupper(mb_substr($words[0], 0, 1, 'UTF-8'), 'UTF-8');
    }
    return '?';
}

$doctors = [];
$current_doc = null;

// ==========================================
// XỬ LÝ THÊM BÁC SĨ MỚI
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_doctor'])) {
    $d_name = trim($_POST['full_name']);
    $d_email = trim($_POST['email']);
    $d_spec = trim($_POST['speciality']);
    $d_fee = $_POST['fee'];
    $d_pass = password_hash('123456', PASSWORD_DEFAULT);

    try {
        $pdo->beginTransaction();
        // 1. Thêm vào bảng Users
        $stmt1 = $pdo->prepare("INSERT INTO Users (full_name, email, password_hash, role, is_active) VALUES (?, ?, ?, 'Doctor', 1)");
        $stmt1->execute([$d_name, $d_email, $d_pass]);
        $new_doc_id = $pdo->lastInsertId();

        // 2. Thêm vào bảng Doctor_Profiles
        $stmt2 = $pdo->prepare("INSERT INTO Doctor_Profiles (doctor_id, speciality, consultation_fee) VALUES (?, ?, ?)");
        $stmt2->execute([$new_doc_id, $d_spec, $d_fee]);

        $pdo->commit();
        header("Location: adm_doctors.php");
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "<script>alert('Lỗi tạo Bác sĩ: Email có thể đã tồn tại trong hệ thống!');</script>";
    }
}

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
                        <div class="w-10 h-10 rounded-full bg-[#003366] text-white flex items-center justify-center font-bold text-sm shadow-sm border border-gray-200">
                            <?php echo getInitials($adminName); ?>
                        </div>
                    </div>
                </div>
            </header>
        </div>

        <div class="content-area max-w-7xl mx-auto w-full flex gap-8">
            
            <div class="w-[320px] bg-white rounded-2xl shadow-sm border border-gray-100 flex flex-col h-full overflow-hidden flex-shrink-0">
                <div class="px-6 py-5 border-b border-gray-100 bg-gray-50/30 flex justify-between items-center">
                    <h2 class="font-bold text-gray-800 text-[13px] uppercase tracking-widest">Doctors List</h2>
                    <button onclick="document.getElementById('addDocModal').classList.remove('hidden')" class="bg-blue-600 text-white p-1.5 px-3 rounded-lg text-[10px] font-bold uppercase tracking-widest hover:bg-blue-700 shadow-sm transition-colors flex items-center gap-1">
                        <i class="fa-solid fa-plus"></i> Add
                    </button>
                </div>
                
                <div class="flex-1 overflow-y-auto p-4 space-y-3">
                    <?php if (empty($doctors)): ?>
                        <div class="text-center py-10 text-gray-400 italic text-sm">No doctors found.</div>
                    <?php else: ?>
                        <?php foreach ($doctors as $doc): ?>
                        <a href="?id=<?php echo $doc['user_id']; ?>" class="block border <?php echo $selected_id == $doc['user_id'] ? 'border-blue-400 bg-blue-50/50 shadow-sm' : 'border-gray-100 hover:border-gray-300 hover:bg-gray-50'; ?> rounded-xl p-4 flex flex-col items-center transition-all group">
                        <div class="flex flex-col items-center">
                            <div class="w-16 h-16 rounded-full mb-3 shadow-sm flex items-center justify-center text-xl font-bold text-white bg-gradient-to-br from-blue-500 to-indigo-600 border-2 <?php echo $selected_id == $doc['user_id'] ? 'border-blue-300 ring-2 ring-blue-100' : 'border-white'; ?> overflow-hidden">
                                <?php 
                                    // Tự động tạo chữ cái đầu từ tên Bác sĩ
                                    $nameParts = explode(' ', trim($doc['full_name']));
                                    $initials = strtoupper(substr($nameParts[0], 0, 1));
                                    if (count($nameParts) > 1) { $initials .= strtoupper(substr(end($nameParts), 0, 1)); }

                                    // Kiểm tra ảnh
                                    $avatarPath = $doc['avatar_url'] ?: 'img/default.jpg';
                                    if (!empty($doc['avatar_url']) && file_exists($doc['avatar_url'])): 
                                ?>
                                    <img src="<?php echo $avatarPath; ?>" 
                                        class="w-full h-full object-cover" 
                                        onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                    <span class="hidden w-full h-full items-center justify-center"><?php echo $initials; ?></span>
                                <?php else: ?>
                                    <span><?php echo $initials; ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
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
                    <div class="w-32 h-32 rounded-full border-4 border-gray-50 shadow-sm flex items-center justify-center text-5xl font-extrabold text-white bg-gradient-to-br from-blue-500 to-indigo-600 flex-shrink-0">
                        <?php echo getInitials($current_doc['full_name']); ?>
                    </div>
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

<div id="addDocModal" class="hidden fixed inset-0 bg-gray-900/60 backdrop-blur-sm z-50 flex items-center justify-center">
    <div class="bg-white rounded-2xl shadow-2xl w-[450px] overflow-hidden">
        <div class="bg-blue-600 px-6 py-4 flex items-center justify-between text-white">
            <h3 class="font-semibold text-lg">Add New Doctor</h3>
            <button type="button" onclick="document.getElementById('addDocModal').classList.add('hidden')" class="text-white/80 hover:text-white"><i class="fa-solid fa-xmark text-xl"></i></button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <div>
                <label class="block text-[11px] font-bold text-gray-600 mb-1 uppercase tracking-widest">Full Name</label>
                <input type="text" name="full_name" required placeholder="Ex: Nguyen Van A" class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm outline-none focus:border-blue-500 font-medium">
            </div>
            <div>
                <label class="block text-[11px] font-bold text-gray-600 mb-1 uppercase tracking-widest">Email (For Login)</label>
                <input type="email" name="email" required placeholder="doctor@pneumocare.com" class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm outline-none focus:border-blue-500 font-medium">
            </div>
            <div>
                <label class="block text-[11px] font-bold text-gray-600 mb-1 uppercase tracking-widest">Speciality</label>
                <input type="text" name="speciality" required placeholder="Ex: Pulmonologist" class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm outline-none focus:border-blue-500 font-medium">
            </div>
            <div>
                <label class="block text-[11px] font-bold text-gray-600 mb-1 uppercase tracking-widest">Consultation Fee (VND)</label>
                <input type="number" name="fee" required value="300000" min="0" step="50000" class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm outline-none focus:border-blue-500 font-medium">
            </div>
            <div class="bg-blue-50 border border-blue-100 p-3 rounded-lg flex items-center gap-3">
                <i class="fa-solid fa-circle-info text-blue-500 text-xl"></i>
                <p class="text-xs text-blue-800 font-medium">Default password is <strong class="text-blue-600">123456</strong>. The doctor can change it later.</p>
            </div>
            <div class="flex gap-3 pt-4 border-t border-gray-100">
                <button type="button" onclick="document.getElementById('addDocModal').classList.add('hidden')" class="flex-1 bg-gray-100 text-gray-600 py-2.5 rounded-xl font-bold hover:bg-gray-200 transition-colors">Cancel</button>
                <button type="submit" name="add_doctor" class="flex-1 bg-blue-600 text-white py-2.5 rounded-xl font-bold hover:bg-blue-700 transition-colors shadow-md">Create Doctor</button>
            </div>
        </form>
    </div>
</div>

</body>
</html>