<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('pages/page.php?slug=contact');
}

verify_csrf();

// Bot protection: honeypot + IP rate limit (5 messages/IP/hour)
if (is_honeypot_filled()) {
    set_flash('success', 'Thanks for reaching out! We\'ll get back to you soon.');
    redirect('pages/page.php?slug=contact');
}
if (rate_limit_exceeded('contact', 5, 3600)) {
    set_flash_error(
        'Too many messages.',
        'You\'ve sent too many contact messages recently.',
        'Please wait an hour before trying again.',
        'pages/page.php?slug=contact'
    );
    redirect('pages/page.php?slug=contact');
}

$errors = [];
$name    = trim((string)($_POST['name'] ?? ''));
$email   = trim((string)($_POST['email'] ?? ''));
$topic   = trim((string)($_POST['topic'] ?? 'general'));
$subject = trim((string)($_POST['subject'] ?? ''));
$message = trim((string)($_POST['message'] ?? ''));

// Strip CR/LF from free-text fields used in email headers / notifications
// to prevent email header injection via the subject line.
$name    = str_replace(["\r", "\n"], ' ', $name);
$subject = str_replace(["\r", "\n"], ' ', $subject);
$message = str_replace(["\r", "\n"], ' ', $message);

if ($name === '') { $errors['name'] = 'Your name is required.'; }
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors['email'] = 'A valid email is required.'; }
if (!in_array($topic, ['general', 'booking', 'account', 'manager', 'feedback', 'login_locked'], true)) { $errors['topic'] = 'Please choose a topic.'; }
if ($message === '' || mb_strlen($message) < 10) { $errors['message'] = 'Message must be at least 10 characters.'; }

if ($errors) {
    foreach ($errors as $field => $msg) {
        set_flash('error', $msg);
    }
    flash_form($errors, $_POST);
    redirect('pages/page.php?slug=contact');
}

$ip = client_ip();
$ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
$uid  = is_logged_in() ? (int)$_SESSION['user_id'] : null;

$stmt = $conn->prepare('INSERT INTO contact_messages (name, email, topic, subject, message, ip, user_id) VALUES (?, ?, ?, ?, ?, ?, ?)');
$stmt->bind_param('ssssssi', $name, $email, $topic, $subject, $message, $ip, $uid);
$stmt->execute();
$msgId = (int)$stmt->insert_id;
$stmt->close();

$admins = $conn->query("SELECT id FROM users WHERE role = 'admin'")->fetch_all(MYSQLI_ASSOC);
foreach ($admins as $a) {
    notify_user(
        (int)$a['id'],
        'New contact message: ' . $subject,
        $name . ' (' . $email . ') wrote about ' . $topic . ': ' . mb_substr($message, 0, 120) . '…',
        'fa-paper-plane',
        'admin/contact_messages.php'
    );
}

$to = 'hello@goalspace.com';
$mailSubject = "GoalSpace Contact: {$topic} - {$subject}";
$body = "Name: {$name}\nEmail: {$email}\nTopic: {$topic}\nSubject: {$subject}\n\nMessage:\n{$message}\n\n---\nIP: {$ip}\nUA: {$ua}\nMsg ID: {$msgId}";
$headers = "From: GoalSpace <noreply@goalspace.com>\r\nReply-To: {$email}\r\n";
@mail($to, $mailSubject, $body, $headers);

set_flash('success', 'Thanks for reaching out! We\'ll get back to you soon.');
redirect('pages/page.php?slug=contact');