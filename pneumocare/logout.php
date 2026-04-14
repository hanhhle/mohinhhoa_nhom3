<?php
session_start();
session_unset();    // Xóa tất cả biến session
session_destroy();  // Hủy phiên làm việc
header("Location: login.php"); // Đẩy về trang đăng nhập
exit();
?>