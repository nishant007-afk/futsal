<?php
require_once __DIR__ . '/../config/db.php';
require_admin();

if (isset($_GET['export'])) {
    $filter = $_GET['filter'] ?? 'all';
    $where = '1=1';
    if ($filter === 'open') {
        $where = 'is_resolved = 0';
    } elseif ($filter === 'resolved') {
        $where = 'is_resolved = 1';
    } elseif ($filter === 'login_locked') {
        $where = 'topic = "login_locked"';
    }
    $stmt = $conn->prepare('SELECT id, name, email, topic, subject, message, is_resolved, user_id, created_at FROM contact_messages WHERE ' . $where . ' ORDER BY is_resolved ASC, id DESC');
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $topicLabels = [
        'general' => 'General', 'booking' => 'Booking', 'account' => 'Account',
        'manager' => 'Manager', 'feedback' => 'Feedback', 'login_locked' => 'Login locked',
    ];
    $csv = [['ID', 'Name', 'Email', 'Topic', 'Subject', 'Message', 'Resolved', 'User ID', 'Created At']];
    foreach ($rows as $m) {
        $csv[] = [
            (int)$m['id'],
            $m['name'],
            $m['email'],
            $topicLabels[$m['topic']] ?? $m['topic'],
            $m['subject'],
            $m['message'],
            $m['is_resolved'] ? 'Yes' : 'No',
            (int)$m['user_id'],
            $m['created_at'],
        ];
    }
    export_csv($csv, 'contact-messages.csv');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resolve_msg'])) {
    verify_csrf();
    $id = (int)$_POST['resolve_msg'];
    $stmt = $conn->prepare('UPDATE contact_messages SET is_resolved = 1 WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    set_flash('success', 'Message marked as resolved.');
    redirect('admin/contact_messages.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_msg'])) {
    verify_csrf();
    $id = (int)$_POST['delete_msg'];
    $stmt = $conn->prepare('DELETE FROM contact_messages WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    set_flash('success', 'Message deleted.');
    redirect('admin/contact_messages.php');
}

$filter = $_GET['filter'] ?? 'all';
if (!in_array($filter, ['all', 'open', 'resolved', 'login_locked'], true)) {
    $filter = 'all';
}

$where = '1=1';
$types = '';
$params = [];
if ($filter === 'open') {
    $where = 'is_resolved = 0';
} elseif ($filter === 'resolved') {
    $where = 'is_resolved = 1';
} elseif ($filter === 'login_locked') {
    $where = 'topic = "login_locked"';
}

$sql = 'SELECT * FROM contact_messages WHERE ' . $where . ' ORDER BY is_resolved ASC, id DESC';
$stmt = $conn->prepare($sql);
$stmt->execute();
$messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$topicLabels = [
    'general' => 'General',
    'booking' => 'Booking',
    'account' => 'Account',
    'manager' => 'Manager',
    'feedback' => 'Feedback',
    'login_locked' => 'Login locked',
];

$page_title = 'Contact Messages';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head dash-page-head">
    <a href="<?php echo base_url('admin/dashboard.php'); ?>" class="page-back-arrow" data-back aria-label="Back to dashboard"><i class="fa-solid fa-arrow-left"></i></a>
    <div class="dash-head-main">
        <h1 class="page-title">Contact Messages</h1>
        <div class="actions">
            <a href="?filter=all&export=1" class="btn btn-outline btn-sm"><i class="fa-solid fa-file-csv"></i> Export CSV</a>
            <a href="?filter=all" class="btn btn-outline btn-sm <?php echo $filter === 'all' ? 'btn-primary' : ''; ?>">All (<?php echo count($messages); ?>)</a>
            <a href="?filter=open" class="btn btn-outline btn-sm <?php echo $filter === 'open' ? 'btn-primary' : ''; ?>">Open</a>
            <a href="?filter=login_locked" class="btn btn-outline btn-sm <?php echo $filter === 'login_locked' ? 'btn-primary' : ''; ?>">Login locked</a>
        </div>
    </div>
</div>

<?php if (!$messages): ?>
    <div class="empty reveal"><span class="big"><i class="fa-regular fa-envelope-open"></i></span><h3>No contact messages</h3><p>Messages from the contact form will appear here.</p></div>
<?php else: ?>
    <div class="mbookings reveal">
        <?php foreach ($messages as $m): ?>
            <div class="mbooking top <?php echo $m['is_resolved'] ? '' : 'unresolved'; ?>">
                <div class="mbooking-date sm">
                    <span class="bd-month"><?php echo e(strtoupper(date('M', strtotime($m['created_at'])))); ?></span>
                    <span class="bd-day"><?php echo (int)date('d', strtotime($m['created_at'])); ?></span>
                </div>
                <div class="mbooking-main">
                    <div class="mbooking-head">
                        <h3><?php echo e($topicLabels[$m['topic']] ?? ucfirst($m['topic'])); ?> &middot; <?php echo e($m['subject'] !== '' ? $m['subject'] : 'No subject'); ?></h3>
                        <span class="mbooking-status">
                            <span class="badge <?php echo $m['is_resolved'] ? 'badge-confirmed' : 'badge-pending'; ?>"><?php echo $m['is_resolved'] ? 'Resolved' : 'Open'; ?></span>
                        </span>
                    </div>
                    <div class="mbooking-meta">
                        <span><i class="fa-solid fa-user"></i> <?php echo e($m['name'] !== '' ? $m['name'] : 'Anonymous'); ?></span>
                        <span><i class="fa-solid fa-envelope"></i> <?php echo e($m['email']); ?></span>
                        <?php if ($m['user_id']): ?><span><i class="fa-solid fa-user-check"></i> User #<?php echo (int)$m['user_id']; ?></span><?php endif; ?>
                        <span><i class="fa-regular fa-clock"></i> <?php echo e(date('M j, Y g:i A', strtotime($m['created_at']))); ?></span>
                    </div>
                    <p class="msg-body"><?php echo e($m['message']); ?></p>
                </div>
                <div class="mbooking-side">
                    <div class="mbooking-actions actions-tight">
                        <?php if (!$m['is_resolved']): ?>
                            <?php echo post_action_form(base_url('admin/contact_messages.php'), 'resolve_msg', (string)(int)$m['id'], '<i class="fa-solid fa-check"></i>', 'btn btn-outline btn-sm', '', 'Mark as resolved'); ?>
                        <?php endif; ?>
                        <?php echo post_action_form(base_url('admin/contact_messages.php'), 'delete_msg', (string)(int)$m['id'], '<i class="fa-solid fa-trash"></i>', 'btn btn-danger btn-sm', 'Delete this message?', 'Delete message'); ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
