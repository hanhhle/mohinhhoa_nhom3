<?php
session_start();
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_SESSION['user_id'])) {
    $sender_id = $_SESSION['user_id'];
    $receiver_id = $_POST['receiver_id'];
    $content = trim($_POST['message_content']);

    if (!empty($content)) {
        $stmt = $pdo->prepare("INSERT INTO Messages (sender_id, receiver_id, message_content) VALUES (?, ?, ?)");
        if ($stmt->execute([$sender_id, $receiver_id, $content])) {
            echo json_encode(["status" => "success", "time" => date('h:i a')]);
        } else {
            echo json_encode(["status" => "error"]);
        }
    }
}
?>