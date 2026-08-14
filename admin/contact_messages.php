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

if (isset($_GET['resolve'])) {
    if (!isset($_GET['csrf']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_GET['csrf'])) {
        exit('Invalid request.');
    }
    $id = (int)$_GET['resolve'];
    $stmt = $conn->prepare('UPDATE contact_messages SET is_resolved = 1 WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    set_flash('success', 'Message marked as resolved.');
    redirect('admin/contact_messages.php');
}

if (isset($_GET['delete'])) {
    if (!isset($_GET['csrf']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_GET['csrf'])) {
        exit('Invalid request.');
    }
    $id = (int)$_GET['delete'];
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
    <a href="<?php echo base_url('admin/dashboard.php'); ?>" class="nav-back mob-back" aria-label="Back to dashboard"><i class="fa-solid fa-arrow-left"></i></a>
    <div class="dash-head-main">
        <h2>Contact Messages</h2>
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
                    <div class="mbooking-actions">
                        <?php if (!$m['is_resolved']): ?>
                            <a href="<?php echo base_url('admin/contact_messages.php?resolve=' . (int)$m['id'] . '&csrf=' . csrf_token()); ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-check"></i> Resolve</a>
                        <?php endif; ?>
                        <a href="<?php echo base_url('admin/contact_messages.php?delete=' . (int)$m['id'] . '&csrf=' . csrf_token()); ?>" class="btn btn-danger btn-sm" data-confirm="Delete this message?" data-confirm-ok="Yes, delete" data-confirm-cancel="No"><i class="fa-solid fa-trash"></i> Delete</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
