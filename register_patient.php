<?php
session_start();
require 'db.php';

$step = isset($_POST['step']) ? $_POST['step'] : 1;
$error = '';

// Xử lý Dữ liệu Bước 1 (Chuyển sang Bước 2)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $step == 2) {
    if ($_POST['password'] !== $_POST['confirm_password']) {
        $error = "Mật khẩu xác nhận không khớp!";
        $step = 1; // Quay lại bước 1
    } else {
        // Lưu tạm dữ liệu Bước 1 vào Session
        $_SESSION['reg_email'] = $_POST['email'];
        $_SESSION['reg_phone'] = $_POST['phone'];
        $_SESSION['reg_password'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
    }
}

// Xử lý Dữ liệu Bước 2 (Lưu vào Database)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $step == 3) {
    $email = $_SESSION['reg_email'];
    $phone = $_SESSION['reg_phone'];
    $password = $_SESSION['reg_password'];
    
    $full_name = trim($_POST['first_name'] . ' ' . $_POST['last_name']);
    $dob = $_POST['dob'];
    $address = $_POST['address'];
    $gender = $_POST['gender'];
    $blood_group = $_POST['blood_group'];
    $cccd = !empty($_POST['cccd']) ? $_POST['cccd'] : NULL;

    try {
        $pdo->beginTransaction();
        
        // 1. Thêm vào Users
        $stmt1 = $pdo->prepare("INSERT INTO Users (email, password_hash, full_name, role) VALUES (?, ?, ?, 'Patient')");
        $stmt1->execute([$email, $password, $full_name]);
        $user_id = $pdo->lastInsertId();

        // 2. Thêm vào Patient_Profiles
        $stmt2 = $pdo->prepare("INSERT INTO Patient_Profiles (patient_id, date_of_birth, gender, blood_group, phone_number, address, identity_card_number) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt2->execute([$user_id, $dob, $gender, $blood_group, $phone, $address, $cccd]);

        $pdo->commit();
        
        // Xóa session đăng ký tạm
        unset($_SESSION['reg_email'], $_SESSION['reg_phone'], $_SESSION['reg_password']);
        
        // Bật popup hoặc alert thành công rồi chuyển hướng
        echo "<script>alert('Đăng ký thành công! Đang chuyển hướng đến trang đăng nhập...'); window.location.href='login.php';</script>";
        exit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "Lỗi đăng ký! Có thể Email hoặc CCCD đã được sử dụng.";
        $step = 2; // Giữ lại ở Bước 2 nếu lỗi DB
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pneumo-Care | Patient Sign up</title>
<style>
/* Giữ nguyên toàn bộ CSS của bạn */
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Segoe UI', sans-serif; background: #f0f4f8; color: #1a2a3a; }
.navbar { background: #fff; border-bottom: 1px solid #e0e8f0; padding: 0 32px; height: 64px; display: flex; align-items: center; justify-content: space-between; }
.nav-logo { display: flex; align-items: center; gap: 8px; font-size: 20px; font-weight: 700; color: #1a2a3a; }
.nav-logo span { color: #3b82f6; }
.signup-bg { background: #eef3fb; min-height: calc(100vh - 64px); padding: 40px 20px; }
.step-bar { display: flex; align-items: center; justify-content: center; padding-bottom: 32px; }
.step { display: flex; flex-direction: column; align-items: center; gap: 8px; z-index: 10; }
.step-circle { width: 44px; height: 44px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 18px; color: #fff; }
.step-circle.orange { background: #f59e0b; }
.step-circle.blue { background: #3b82f6; }
.step-line { width: 200px; height: 3px; background: #3b82f6; margin-top: -20px; }
.step-label { font-size: 13px; font-weight: 600; color: #3b82f6; }
.form-card, .patient-card { background: #fff; border-radius: 16px; padding: 32px 40px; margin: 0 auto; box-shadow: 0 2px 16px rgba(0,0,0,0.06); }
.form-card { max-width: 720px; } .patient-card { max-width: 1000px; }
.form-card h2 { text-align: center; font-size: 20px; font-weight: 700; color: #3b82f6; margin-bottom: 24px; }
.form-group { margin-bottom: 16px; width: 100%;}
.form-group label { display: block; font-size: 13px; font-weight: 500; margin-bottom: 6px; color: #374151; }
.form-input, .form-select { width: 100%; padding: 12px 16px; border: 1px solid #e5e7eb; border-radius: 8px; font-size: 14px; background: #f9fafb; outline: none; }
.form-input:focus, .form-select:focus { border-color: #3b82f6; background: #fff; }
.form-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; margin-bottom: 16px; }
.btn-primary { background: #3b82f6; color: #fff; border: none; padding: 12px 40px; border-radius: 10px; font-size: 16px; font-weight: 600; cursor: pointer; display: block; margin: 24px auto 0; min-width: 140px; }
.btn-primary:hover { background: #2563eb; }
.error-msg { color: #ef4444; text-align: center; margin-bottom: 15px; font-weight: 500; }
</style>
</head>
<body>

<nav class="navbar">
  <div class="nav-logo">
    <svg viewBox="0 0 32 32" fill="none" width="30" height="30"><ellipse cx="10" cy="18" rx="7" ry="10" fill="#f87171" transform="rotate(-10 10 18)"/><ellipse cx="22" cy="18" rx="7" ry="10" fill="#fca5a5" transform="rotate(10 22 18)"/><ellipse cx="10" cy="18" rx="4" ry="6" fill="#ef4444" transform="rotate(-10 10 18)"/><ellipse cx="22" cy="18" rx="4" ry="6" fill="#f87171" transform="rotate(10 22 18)"/></svg>
    Pneumo-<span>Care</span>
  </div>
</nav>

<div class="signup-bg">
  
  <?php if($error): ?>
      <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
  <?php endif; ?>

  <?php if($step == 1): ?>
  <div class="step-bar">
    <div class="step"><div class="step-circle orange">1</div><div class="step-label">Account Setup</div></div>
    <div class="step-line"></div>
    <div class="step"><div class="step-circle blue">2</div><div class="step-label">Details</div></div>
    <div class="step-line"></div>
    <div class="step"><div class="step-circle blue">3</div><div class="step-label">Detail Proof</div></div>
  </div>

  <div class="form-card">
    <h2>Create your Account</h2>
    <form method="POST" action="">
      <input type="hidden" name="step" value="2">
      <div class="form-group"><input class="form-input" type="email" name="email" required placeholder="Email"></div>
      <div class="form-group"><input class="form-input" type="tel" name="phone" required placeholder="Phone Number"></div>
      <div class="form-group"><input class="form-input" type="password" name="password" required placeholder="Password"></div>
      <div class="form-group"><input class="form-input" type="password" name="confirm_password" required placeholder="Confirm Password"></div>
      <button type="submit" class="btn-primary">Next</button>
    </form>
  </div>

  <?php elseif($step == 2): ?>
  <div class="step-bar">
    <div class="step"><div class="step-circle blue">1</div><div class="step-label">Account Setup</div></div>
    <div class="step-line"></div>
    <div class="step"><div class="step-circle orange">2</div><div class="step-label">Details</div></div>
    <div class="step-line"></div>
    <div class="step"><div class="step-circle blue">3</div><div class="step-label">Detail Proof</div></div>
  </div>

  <div class="patient-card">
    <div style="font-size: 18px; font-weight: 700; color: #1a2a3a; margin-bottom: 16px;">Patient Information</div>
    <hr style="border:none; border-top:1px solid #e5e7eb; margin-bottom:24px;">

    <form method="POST" action="">
      <input type="hidden" name="step" value="3">
      
      <div class="form-grid">
        <div class="form-group"><label>First Name</label><input class="form-input" name="first_name" required placeholder="ex. Hoang Anh"></div>
        <div class="form-group"><label>Last Name</label><input class="form-input" name="last_name" required placeholder="ex. Le"></div>
        <div class="form-group"><label>Date of Birth</label><input class="form-input" type="date" name="dob" required></div>
      </div>

      <div class="form-grid">
        <div class="form-group"><label>Address</label><input class="form-input" name="address" required placeholder="ex. Dong Da, Ha Noi"></div>
        <div class="form-group"><label>Identity Card (CCCD - Tùy chọn)</label><input class="form-input" name="cccd" placeholder="ex. 001203xxxxxx"></div>
        <div class="form-group">
            <label>Gender</label>
            <select class="form-select" name="gender" required>
                <option value="Male">Male</option>
                <option value="Female">Female</option>
                <option value="Other">Other</option>
            </select>
        </div>
      </div>

      <div class="form-grid">
        <div class="form-group">
            <label>Blood Group</label>
            <select class="form-select" name="blood_group">
                <option value="A+">A+</option>
                <option value="A-">A-</option>
                <option value="B+">B+</option>
                <option value="B-">B-</option>
                <option value="AB+">AB+</option>
                <option value="AB-">AB-</option>
                <option value="O+">O+</option>
                <option value="O-">O-</option>
            </select>
        </div>
      </div>

      <button type="submit" class="btn-primary">Complete Registration</button>
    </form>
  </div>
  <?php endif; ?>

</div>
</body>
</html>