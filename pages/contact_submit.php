<?php
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('pages/page.php?slug=contact');
}

verify_csrf();

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$topic = trim($_POST['topic'] ?? 'general');
$subject = trim($_POST['subject'] ?? '');
$message = trim($_POST['message'] ?? '');

if (!in_array($topic, ['general', 'booking', 'account', 'manager', 'feedback', 'login_locked'], true)) {
    $topic = 'general';
}

$errors = [];
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Enter a valid email address, e.g. you@example.com.';
}
if ($message === '' || mb_strlen($message) < 10) {
    $errors['message'] = 'Please write a message of at least 10 characters.';
}
if ($topic !== 'login_locked' && $name === '') {
    $errors['name'] = 'Please enter your name.';
}

$redirectTarget = $topic === 'login_locked' ? 'pages/login.php' : 'pages/page.php?slug=contact';

if ($errors) {
    flash_form($errors, ['name' => $name, 'email' => $email, 'subject' => $subject, 'message' => $message, 'topic' => $topic]);
    if ($topic === 'login_locked') {
        set_flash('error', 'Please check the highlighted fields and try again.');
    }
    redirect($redirectTarget);
}

$stmt = $conn->prepare('INSERT INTO contact_messages (name, email, topic, subject, message, ip, user_id) VALUES (?, ?, ?, ?, ?, ?, ?)');
$uid = is_logged_in() ? (int)$_SESSION['user_id'] : null;
$ip = client_ip();
$stmt->bind_param('ssssssi', $name, $email, $topic, $subject, $message, $ip, $uid);
$stmt->execute();

if ($topic === 'login_locked') {
    set_flash('success', 'Your message has been sent to the admin. They will review and restore your access shortly.');
    redirect('pages/login.php');
}

set_flash('success', 'Thanks for reaching out! We will get back to you at ' . $email . ' soon.');
redirect('pages/page.php?slug=contact');
