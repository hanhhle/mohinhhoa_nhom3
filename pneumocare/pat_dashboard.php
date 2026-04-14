<?php
session_start();
require 'db.php';
ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL);

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Patient') { 
    header("Location: login.php"); exit(); 
}

$patientId = $_SESSION['user_id'];
$patientName = $_SESSION['name'];
$patientAvatar = (!empty($_SESSION['avatar']) && $_SESSION['avatar'] != 'default.png') ? $_SESSION['avatar'] : 'img/default_patient.png';

$totalAppointments = 0;
$pendingFees = [];

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM Appointments WHERE patient_id = ? AND status = 'Scheduled'");
    $stmt->execute([$patientId]);
    $totalAppointments = $stmt->fetchColumn();

    $stmtFee = $pdo->prepare("
        SELECT a.appointment_id, u_d.full_name as doctor_name, u_d.avatar_url as doc_avatar
        FROM Appointments a
        JOIN Users u_d ON a.doctor_id = u_d.user_id
        WHERE a.patient_id = ? AND a.status = 'Completed' AND a.fee_status = 'Unpaid'
    ");
    $stmtFee->execute([$patientId]);
    $pendingFees = $stmtFee->fetchAll();
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Pneumo-Care | Patient - Dashboard</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f0f4f8; color: #1a2a3a; }

        .navbar { background: #fff; border-bottom: 1px solid #e0e8f0; padding: 0 32px; height: 64px; display: flex; align-items: center; justify-content: space-between; }
        .nav-logo { display: flex; align-items: center; gap: 8px; font-size: 20px; font-weight: 700; color: #1a2a3a; }
        .nav-logo span { color: #3b82f6; }
        .nav-links { display: flex; align-items: center; gap: 28px; }
        .nav-links a { text-decoration: none; color: #6b7280; font-size: 15px; }
        .btn-login { background: none; border: none; font-size: 15px; font-weight: 600; cursor: pointer; color: #1a2a3a; }
        .btn-signup { background: #6b7280; color: #fff; border: none; padding: 10px 22px; border-radius: 8px; font-size: 15px; cursor: pointer; }

        .layout { display: flex; min-height: calc(100vh - 64px); }
        .sidebar { width: 220px; background: #fff; border-right: 1px solid #e0e8f0; padding: 0; display: flex; flex-direction: column; min-height: 100vh; }
        .sidebar-logo { display: flex; align-items: center; gap: 8px; padding: 18px 20px 18px; font-size: 17px; font-weight: 700; border-bottom: 1px solid #e0e8f0; }
        .sidebar-logo span { color: #3b82f6; }
        .sidebar-menu { flex: 1; padding-top: 8px; }
        .sidebar-item { display: flex; align-items: center; gap: 10px; padding: 11px 20px; color: #6b7280; font-size: 14px; cursor: pointer; text-decoration: none; transition: all 0.15s; }
        .sidebar-item:hover { background: #f0f4f8; color: #1a2a3a; }
        .sidebar-item.active { background: #eff6ff; color: #3b82f6; border-right: 3px solid #3b82f6; font-weight: 600; }
        .sidebar-item svg { width: 18px; height: 18px; flex-shrink: 0; }
        .sidebar-logout { padding: 13px 20px; display: flex; align-items: center; gap: 10px; color: #6b7280; font-size: 14px; cursor: pointer; border-top: 1px solid #e0e8f0; }
        .main-content { flex: 1; padding: 28px 32px; }
        .topbar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; }
        .topbar h1 { font-size: 22px; font-weight: 600; }
        .topbar-right { display: flex; align-items: center; gap: 16px; }
        .notif-bell { position: relative; cursor: pointer; }
        .notif-dot { position: absolute; top: -2px; right: -2px; width: 8px; height: 8px; background: #ef4444; border-radius: 50%; }
        .user-info { display: flex; align-items: center; gap: 10px; cursor: pointer; }
        .user-name { font-size: 14px; font-weight: 600; }
        .user-role { font-size: 12px; color: #6b7280; }
        .user-avatar { width: 38px; height: 38px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px; }

        .signup-bg { background: #eef3fb; min-height: calc(100vh - 64px); padding: 40px 20px; }
        .step-bar { display: flex; align-items: center; justify-content: center; padding-bottom: 32px; }
        .step { display: flex; flex-direction: column; align-items: center; gap: 8px; }
        .step-circle { width: 44px; height: 44px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 18px; color: #fff; }
        .step-circle.orange { background: #f59e0b; }
        .step-circle.blue { background: #3b82f6; }
        .step-line { width: 200px; height: 3px; background: #3b82f6; margin-top: -20px; }
        .step-label { font-size: 13px; font-weight: 600; color: #3b82f6; }

        .form-card { background: #fff; border-radius: 16px; padding: 32px 40px; max-width: 720px; margin: 0 auto; box-shadow: 0 2px 16px rgba(0,0,0,0.06); }
        .form-card h2 { text-align: center; font-size: 20px; font-weight: 700; color: #3b82f6; margin-bottom: 24px; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 13px; font-weight: 500; margin-bottom: 6px; color: #374151; }
        .form-input { width: 100%; padding: 12px 16px; border: 1px solid #e5e7eb; border-radius: 8px; font-size: 14px; background: #f9fafb; outline: none; transition: border 0.2s; font-family: inherit; }
        .form-input:focus { border-color: #3b82f6; background: #fff; }
        .form-input-wrap { position: relative; }
        .form-input-wrap .eye-icon { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #9ca3af; }
        .form-select { width: 100%; padding: 12px 16px; border: 1px solid #e5e7eb; border-radius: 8px; font-size: 14px; background: #f9fafb; outline: none; font-family: inherit; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; margin-bottom: 16px; }
        .form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .btn-primary { background: #3b82f6; color: #fff; border: none; padding: 12px 40px; border-radius: 10px; font-size: 16px; font-weight: 600; cursor: pointer; display: block; margin: 24px auto 0; min-width: 140px; }
        .btn-primary:hover { background: #2563eb; }
        .btn-dark { background: #1e293b; color: #f59e0b; border: none; padding: 13px 40px; border-radius: 10px; font-size: 15px; font-weight: 600; cursor: pointer; display: block; margin: 24px auto 0; min-width: 200px; }
        .back-title { font-size: 18px; font-weight: 700; color: #1a2a3a; display: flex; align-items: center; gap: 6px; cursor: pointer; margin-bottom: 16px; }

        .dash-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .dash-card { background: #fff; border-radius: 14px; padding: 20px; border: 1px solid #e0e8f0; }
        .dash-card-title { font-size: 15px; font-weight: 600; color: #374151; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center; }
        .activity-tile { border-radius: 10px; padding: 14px 18px; display: flex; align-items: center; gap: 16px; margin-bottom: 10px; }
        .activity-tile:last-child { margin-bottom: 0; }
        .tile-num { font-size: 26px; font-weight: 700; color: #1a2a3a; }
        .tile-label { font-size: 13px; color: #6b7280; }
        .donut-wrap { display: flex; align-items: center; gap: 24px; }
        .legend-item { display: flex; align-items: center; gap: 8px; font-size: 13px; margin-bottom: 8px; }
        .legend-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
        .fee-row { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
        .fee-row:last-child { margin-bottom: 0; }
        .fee-avatar { width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; flex-shrink: 0; }
        .fee-name { font-size: 13px; font-weight: 600; }
        .fee-status { font-size: 12px; color: #f59e0b; }
        .btn-fee { background: #3b82f6; color: #fff; border: none; padding: 6px 14px; border-radius: 8px; font-size: 13px; cursor: pointer; white-space: nowrap; margin-left: auto; }

        .filter-bar { background: #fff; border-radius: 10px; padding: 14px 20px; border: 1px solid #e0e8f0; display: flex; align-items: center; gap: 16px; margin-bottom: 16px; }
        .filter-select { border: 1px solid #e0e8f0; border-radius: 8px; padding: 8px 14px; font-size: 14px; background: #fff; min-width: 200px; font-family: inherit; }
        .search-count { display: flex; align-items: center; gap: 8px; font-size: 14px; background: #eff6ff; border-radius: 8px; padding: 8px 14px; border: 1px solid #bfdbfe; color: #374151; }

        .doctor-row { background: #fff; border: 1px solid #e0e8f0; border-radius: 10px; padding: 16px 20px; display: flex; align-items: center; gap: 16px; margin-bottom: 8px; }
        .doctor-avatar { width: 52px; height: 52px; border-radius: 50%; background: #c7d2fe; display: flex; align-items: center; justify-content: center; font-weight: 700; color: #3730a3; font-size: 16px; flex-shrink: 0; }
        .doctor-name { font-size: 16px; font-weight: 700; color: #1e3a5f; }
        .doctor-spec { font-size: 13px; color: #3b82f6; }
        .doctor-spec span { color: #6b7280; }
        .doctor-fee-section { text-align: right; margin-right: 12px; }
        .fee-label-sm { font-size: 12px; color: #6b7280; }
        .fee-amount { font-size: 16px; font-weight: 700; color: #f59e0b; }
        .btn-pick { background: #3b82f6; color: #fff; border: none; padding: 10px 22px; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; }

        .schedule-block { background: #f8fafc; border: 1px solid #e0e8f0; border-top: none; border-radius: 0 0 10px 10px; padding: 16px 20px; margin-top: -8px; margin-bottom: 8px; }
        .date-pills { display: flex; gap: 10px; margin: 10px 0 14px; }
        .date-pill { padding: 8px 16px; border-radius: 8px; border: 1px solid #e0e8f0; cursor: pointer; text-align: center; background: #fff; }
        .date-pill.active { background: #1e3a5f; color: #fff; border-color: #1e3a5f; }
        .date-pill .day { font-size: 11px; font-weight: 600; }
        .date-pill .dt { font-size: 14px; font-weight: 700; }
        .slot-row { display: flex; gap: 10px; align-items: center; margin-bottom: 8px; }
        .slot-label { font-size: 12px; color: #6b7280; width: 64px; }
        .slot-pill { padding: 6px 14px; border-radius: 6px; border: 1px solid #e0e8f0; font-size: 13px; cursor: pointer; background: #fff; }
        .slot-pill:hover { border-color: #3b82f6; }

        .summary-card { background: #fff; border-radius: 14px; padding: 28px 32px; border: 1px solid #e0e8f0; max-width: 860px; margin: 0 auto; }
        .summary-title { text-align: center; font-size: 24px; font-weight: 700; color: #1e3a5f; margin-bottom: 20px; }
        .summary-inner { border: 1.5px solid #3b82f6; border-radius: 10px; overflow: hidden; }
        .summary-doctor-row { padding: 16px 20px; display: flex; align-items: center; gap: 14px; border-bottom: 1px solid #e0e8f0; }
        .summary-info-rows { padding: 16px 20px; display: flex; flex-direction: column; gap: 12px; }
        .summary-row { display: flex; align-items: flex-start; gap: 12px; font-size: 14px; }
        .symptom-textarea { width: 100%; border: 1px solid #e0e8f0; border-radius: 8px; padding: 12px; font-size: 14px; color: #9ca3af; min-height: 80px; font-family: inherit; resize: vertical; }

        .table-card { background: #fff; border-radius: 14px; padding: 20px 24px; border: 1px solid #e0e8f0; }
        .tab-row { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
        .tab-links { display: flex; border-bottom: 2px solid #e0e8f0; }
        .tab-link { padding: 10px 20px; font-size: 14px; font-weight: 600; color: #9ca3af; cursor: pointer; border-bottom: 2px solid transparent; margin-bottom: -2px; text-decoration: none; }
        .tab-link.active { color: #3b82f6; border-bottom-color: #3b82f6; }
        .btn-new { background: #3b82f6; color: #fff; border: none; padding: 9px 16px; border-radius: 8px; font-size: 14px; cursor: pointer; display: flex; align-items: center; gap: 6px; }
        .search-row { display: flex; gap: 12px; margin-bottom: 16px; }
        .search-box { display: flex; align-items: center; gap: 8px; border: 1px solid #e0e8f0; border-radius: 8px; padding: 8px 14px; background: #fff; }
        .search-box input { border: none; outline: none; font-size: 14px; background: transparent; font-family: inherit; }
        .filter-date-btn { display: flex; align-items: center; gap: 8px; border: 1px solid #e0e8f0; border-radius: 20px; padding: 8px 14px; font-size: 14px; cursor: pointer; background: #fff; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { font-size: 13px; color: #6b7280; font-weight: 600; padding: 10px 12px; border-bottom: 1px solid #e0e8f0; text-align: left; }
        .data-table td { padding: 12px 12px; font-size: 14px; border-bottom: 1px solid #f3f4f6; }
        .data-table tr:last-child td { border-bottom: none; }
        .patient-cell { display: flex; align-items: center; gap: 10px; }
        .link-blue { color: #3b82f6; cursor: pointer; text-decoration: none; }
        .link-green { color: #22c55e; font-weight: 600; }
        .btn-reschedule { background: none; border: none; color: #3b82f6; font-size: 13px; cursor: pointer; }
        .btn-icon { width: 28px; height: 28px; border-radius: 50%; border: 1px solid #e0e8f0; background: #fff; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; font-size: 13px; }
        .pagination { display: flex; align-items: center; gap: 6px; justify-content: flex-end; margin-top: 16px; }
        .page-btn { width: 30px; height: 30px; border-radius: 6px; border: 1px solid #e0e8f0; background: #fff; cursor: pointer; font-size: 13px; display: inline-flex; align-items: center; justify-content: center; }
        .page-btn.active { background: #3b82f6; color: #fff; border-color: #3b82f6; }
        .page-btn-text { background: none; border: none; color: #6b7280; font-size: 13px; cursor: pointer; }

        .detail-header { display: flex; align-items: center; justify-content: space-between; border-bottom: 2px solid #e0e8f0; padding-bottom: 12px; margin-bottom: 20px; }
        .detail-tab-active { font-size: 14px; font-weight: 700; color: #3b82f6; border-bottom: 2px solid #3b82f6; padding-bottom: 13px; margin-bottom: -14px; }
        .appt-code { font-size: 13px; color: #6b7280; }
        .appt-code strong { color: #1a2a3a; }
        .badge-approved { color: #22c55e; font-weight: 700; font-size: 14px; }
        .detail-title { font-size: 22px; font-weight: 700; margin-bottom: 24px; }
        .weekly-badge{background:#f3f4f6;border-radius:6px;padding:4px 10px;font-size:13px;color:#6b7280;cursor:pointer;border:none;font-family:inherit;}
    </style>
</head>
<body>
<div class="layout">
  <div class="sidebar">
    <div class="sidebar-logo">
      <svg viewBox="0 0 32 32" fill="none" width="26" height="26"><ellipse cx="10" cy="18" rx="7" ry="10" fill="#f87171" transform="rotate(-10 10 18)"/><ellipse cx="22" cy="18" rx="7" ry="10" fill="#fca5a5" transform="rotate(10 22 18)"/></svg>
      Pneumo-<span>Care</span>
    </div>
    <nav class="sidebar-menu">
      <a class="sidebar-item active" href="pat_dashboard.php">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" width="18" height="18"><rect x="3" y="3" width="7" height="7" rx="1" stroke-width="2"/><rect x="14" y="3" width="7" height="7" rx="1" stroke-width="2"/><rect x="3" y="14" width="7" height="7" rx="1" stroke-width="2"/><rect x="14" y="14" width="7" height="7" rx="1" stroke-width="2"/></svg> Dashboard
      </a>
      <a class="sidebar-item" href="#">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" width="18" height="18"><path d="M9 17v-2m3 2v-4m3 4v-6M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" stroke-width="2"/></svg> Report
      </a>
      <a class="sidebar-item" href="pat_appointments.php">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" width="18" height="18"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" stroke-width="2"/></svg> Appointments
      </a>
      <a class="sidebar-item" href="#">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" width="18" height="18"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" stroke-width="2"/></svg> Doctors
      </a>
      <a class="sidebar-item" href="#">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" width="18" height="18"><path d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" stroke-width="2"/></svg> Messages
      </a>
      <a class="sidebar-item" href="#">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" width="18" height="18"><circle cx="12" cy="12" r="3" stroke-width="2"/></svg> Settings
      </a>
    </nav>
    <div class="sidebar-logout" onclick="location.href='logout.php'">
      <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" width="18" height="18"><path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" stroke-width="2"/></svg> Logout
    </div>
  </div>

  <div class="main-content">
    <div class="topbar">
      <h1>Dashboard</h1>
      <div class="topbar-right">
        <div class="notif-bell">
          <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" width="22" height="22"><path d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 10-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" stroke-width="2"/></svg>
          <div class="notif-dot"></div>
        </div>
        <div class="user-info">
          <div style="text-align:right"><div class="user-name"><?php echo htmlspecialchars($patientName); ?></div><div class="user-role">Patient</div></div>
          <img src="<?php echo $patientAvatar; ?>" class="user-avatar" style="object-fit: cover;" alt="Avatar">
        </div>
      </div>
    </div>

    <div class="dash-grid">
      <div class="dash-card">
        <div class="dash-card-title">Activity Overview</div>
        <div class="activity-tile" style="background:#dbeafe;">
          <span style="font-size:22px;">📋</span>
          <div><div class="tile-num"><?php echo $totalAppointments; ?></div><div class="tile-label">Upcoming Appointments</div></div>
        </div>
        <div class="activity-tile" style="background:#ede9fe;">
          <span style="font-size:22px;">🧪</span>
          <div><div class="tile-num">0</div><div class="tile-label">Lab Tests</div></div>
        </div>
      </div>

      <div class="dash-card" style="min-height:300px; display:flex; align-items:center; justify-content:center; color:#9ca3af;">
        Health Chart (Upcoming Feature)
      </div>

      <div class="dash-card">
        <div class="dash-card-title">Pending Fees</div>
        <?php if(empty($pendingFees)): ?>
            <p style="color:#6b7280; font-size:13px;">You have no pending fees.</p>
        <?php else: ?>
            <?php foreach($pendingFees as $fee): ?>
            <div class="fee-row">
              <img src="<?php echo $fee['doc_avatar'] ?: 'img/default.png'; ?>" class="fee-avatar" style="object-fit:cover;">
              <div>
                  <div class="fee-name">Dr. <?php echo htmlspecialchars($fee['doctor_name']); ?></div>
                  <div class="fee-status">Consultation fee pending</div>
              </div>
              <button class="btn-fee" onclick="location.href='pat_payment.php?id=<?php echo $fee['appointment_id']; ?>'">Pay</button>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
</body>
</html>