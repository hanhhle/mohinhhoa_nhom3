<?php
session_start();
require 'db.php';
ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL);

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Patient') { header("Location: login.php"); exit(); }
$patientId = $_SESSION['user_id'];
$patientName = $_SESSION['name'];
$patientAvatar = (!empty($_SESSION['avatar']) && $_SESSION['avatar'] != 'default.png') ? $_SESSION['avatar'] : 'img/default_patient.png';

$completed = [];
try {
    // SỬA: Lấy cả lịch 'Completed' và 'Cancelled'
    $stmt = $pdo->prepare("SELECT a.*, u_d.full_name as doctor_name FROM Appointments a JOIN Users u_d ON a.doctor_id = u_d.user_id WHERE a.patient_id = ? AND a.status IN ('Completed', 'Cancelled') ORDER BY a.appointment_date DESC, a.appointment_time DESC");
    $stmt->execute([$patientId]);
    $completed = $stmt->fetchAll();
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pneumo-Care | Appointments - Completed</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; color: #1f2937; }

        /* MAIN CONTENT CSS */
        .main-content { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
        .topbar-wrapper { padding: 32px 40px 0 40px; }
        .topbar { 
            height: 72px; background: #ffffff; border: 1px solid #f3f4f6; 
            display: flex; align-items: center; justify-content: space-between; 
            padding: 0 24px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); 
            margin-bottom: 24px;
        }
        .topbar h1 { font-size: 22px; font-weight: 600; color: #1f2937; margin: 0; }
        .topbar-right { display: flex; align-items: center; gap: 24px; height: 100%; }
        
        .content-area { padding: 0 40px 40px 40px; flex: 1; overflow-y: auto; }
        
        .table-card { background: #fff; border-radius: 14px; padding: 24px 32px; border: 1px solid #f3f4f6; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .tab-row { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; }
        .tab-links { display: flex; border-bottom: 2px solid #e5e7eb; width: 100%; max-width: 450px;}
        .tab-link { padding: 12px 24px; font-size: 14px; font-weight: 600; color: #9ca3af; cursor: pointer; border-bottom: 2px solid transparent; margin-bottom: -2px; text-decoration: none; transition: 0.2s; white-space: nowrap;}
        .tab-link:hover { color: #6b7280; }
        .tab-link.active { color: #3b82f6; border-bottom-color: #3b82f6; }
        
        .btn-new { background: #3b82f6; color: #fff; border: none; padding: 10px 20px; border-radius: 8px; font-size: 14px; font-weight: 500; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: 0.2s; box-shadow: 0 2px 4px rgba(59,130,246,0.3); }
        .btn-new:hover { background: #2563eb; }
        
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { font-size: 13px; color: #6b7280; font-weight: 600; padding: 14px 16px; border-bottom: 1px solid #e5e7eb; text-align: left; text-transform: uppercase; letter-spacing: 0.5px; }
        .data-table td { padding: 16px 16px; font-size: 14px; border-bottom: 1px solid #f3f4f6; color: #374151; }
        .data-table tr:hover td { background-color: #f8fafc; }
        .data-table tr:last-child td { border-bottom: none; }
        
        .link-green { color: #10b981; font-weight: 600; background: #d1fae5; padding: 4px 10px; border-radius: 20px; font-size: 12px; }
        .badge-unpaid { color: #f59e0b; font-weight: 600; background: #fef3c7; padding: 4px 10px; border-radius: 20px; font-size: 12px; }
        .sidebar-active { background-color: #eff6ff; color: #2563eb; border-left: 4px solid #2563eb; font-weight: 600; }
    </style>
</head>
<body class="flex h-screen overflow-hidden">
<div class="flex w-full h-full">
  
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

    <main class="main-content">
        <div class="topbar-wrapper">
            <header class="topbar">
            <h1>Appointments History</h1>
            <div class="topbar-right">
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
            <div class="table-card">
            <div class="tab-row">
                <div class="tab-links">
                <a class="tab-link" href="pat_appointments.php">NEW APPOINTMENTS</a>
                <a class="tab-link active" href="pat_appointments_history.php">COMPLETED APPOINTMENTS</a>
                </div>
                <button class="btn-new" onclick="location.href='pat_book_step1.php'">
                    <i class="fa-solid fa-plus"></i> New Appointment
                </button>
            </div>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Date</th>
                        <th>Doctor</th>
                        <th>Fee Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if(empty($completed)): ?>
                    <tr><td colspan="5" style="text-align:center; padding:40px; color:#9ca3af; font-style: italic;">No completed or cancelled appointments yet.</td></tr>
                <?php else: ?>
                    <?php foreach($completed as $row): ?>
                    <tr class="<?php echo $row['status'] == 'Cancelled' ? 'opacity-70 bg-gray-50' : ''; ?>">
                        <td style="font-weight: 500; color: #4b5563; <?php echo $row['status'] == 'Cancelled' ? 'text-decoration: line-through;' : ''; ?>">
                            <?php echo date('h:i A', strtotime($row['appointment_time'])); ?>
                        </td>
                        <td class="<?php echo $row['status'] == 'Cancelled' ? 'text-decoration: line-through;' : ''; ?>">
                            <?php echo date('d/m/Y', strtotime($row['appointment_date'])); ?>
                        </td>
                        <td style="font-weight: 500;">Dr. <?php echo htmlspecialchars($row['doctor_name']); ?></td>
                        <td>
                            <?php if($row['status'] == 'Cancelled'): ?>
                                <span class="text-gray-400 font-medium text-[11px] uppercase tracking-widest"><i class="fa-solid fa-minus"></i></span>
                            <?php elseif($row['fee_status'] == 'Paid'): ?>
                                <span class="link-green">Paid</span>
                            <?php else: ?>
                                <span class="badge-unpaid">Unpaid</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if($row['status'] == 'Cancelled'): ?>
                                <span class="bg-red-50 text-red-500 font-bold px-3 py-1.5 rounded-lg border border-red-100 text-[11px] uppercase tracking-widest flex items-center gap-1.5 w-max">
                                    <i class="fa-solid fa-ban"></i> Cancelled
                                </span>
                            <?php elseif($row['fee_status'] == 'Unpaid'): ?>
                                <a href="pat_payment.php?appt_id=<?php echo $row['appointment_id']; ?>" style="color:#3b82f6; font-weight:600; text-decoration:none; padding: 6px 12px; border-radius: 6px; transition: 0.2s;" onmouseover="this.style.background='#eff6ff'" onmouseout="this.style.background='transparent'">Pay Now</a>
                            <?php else: ?>
                                <span style="color:#9ca3af; font-size: 13px;">No action needed</span>
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
</div>
</body>
</html>