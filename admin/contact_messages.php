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

// KEPT (removed per request): the "All (n)" tab read count($messages), which is the
// size of the *active* filter - so on ?filter=open the "All" tab advertised the open
// count. These are the real per-view totals, straight from the table, so each tab
// states how many rows it will actually show. Prepared like the query above, because
// ->get_result() only exists on a statement - $conn->query() already hands back a
// mysqli_result.
$stmt = $conn->prepare(
    'SELECT COUNT(*) AS all_n,
            COALESCE(SUM(is_resolved = 0), 0) AS open_n,
            COALESCE(SUM(is_resolved = 1), 0) AS resolved_n,
            COALESCE(SUM(topic = "login_locked"), 0) AS locked_n
     FROM contact_messages'
);
$stmt->execute();
$msgCounts = $stmt->get_result()->fetch_assoc() ?: [];
$msgCounts += ['all_n' => 0, 'open_n' => 0, 'resolved_n' => 0, 'locked_n' => 0];

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
            <a href="?filter=all&amp;export=1" class="btn btn-outline btn-sm"><i class="fa-solid fa-file-csv" aria-hidden="true"></i> Export CSV</a>
        </div>
    </div>
</div>

<?php /* KEPT (removed per request): All / Open / Login locked used to sit in the same
        .actions row as Export CSV as `btn btn-outline btn-sm`, with the active one
        flipped to btn-primary. That put a view filter and a real action in the same
        group and gave the active filter no container to sit in. Export CSV now stands
        alone in .actions, and the views are a .subtabs tab group below - the same
        component admin/announce.php already uses. The backend has always supported
        ?filter=resolved; that view is still not surfaced as a tab. */ ?>
<nav class="subtabs" aria-label="Message views">
    <a class="subtab<?php echo $filter === 'all' ? ' is-active' : ''; ?>" href="?filter=all"<?php echo $filter === 'all' ? ' aria-current="page"' : ''; ?>>
        <i class="fa-solid fa-layer-group" aria-hidden="true"></i> All <span class="subtab-count"><?php echo (int)$msgCounts['all_n']; ?></span>
    </a>
    <a class="subtab<?php echo $filter === 'open' ? ' is-active' : ''; ?>" href="?filter=open"<?php echo $filter === 'open' ? ' aria-current="page"' : ''; ?>>
        <i class="fa-solid fa-envelope-open" aria-hidden="true"></i> Open <span class="subtab-count"><?php echo (int)$msgCounts['open_n']; ?></span>
    </a>
    <a class="subtab<?php echo $filter === 'login_locked' ? ' is-active' : ''; ?>" href="?filter=login_locked"<?php echo $filter === 'login_locked' ? ' aria-current="page"' : ''; ?>>
        <i class="fa-solid fa-lock" aria-hidden="true"></i> Login locked <span class="subtab-count"><?php echo (int)$msgCounts['locked_n']; ?></span>
    </a>
</nav>

<?php if (!$messages): ?>
    <div class="empty reveal"><span class="big"><i class="fa-regular fa-envelope-open"></i></span><h3>No contact messages</h3><p>Messages from the contact form will appear here.</p></div>
<?php else: ?>
    <?php /* KEPT (removed per request): the card was built from the shared .mbooking
            primitives (.mbooking-head / .mbooking-meta / .mbooking-side), so the sender
            name, email, user id and timestamp sat in a column to the right of the body
            text while the resolve and delete buttons sat in a third column with a
            128px min-width each. It now uses its own .msgcard shell: subject, then the
            metadata directly beneath it, and a compact icon-button group in the
            top-right. The .mbooking primitives are untouched - bookings_table_html()
            still renders those, so restyling them here would have moved the booking
            cards too. */ ?>
    <div class="msgcards reveal">
        <?php foreach ($messages as $m):
            $mName    = $m['name'] !== '' ? $m['name'] : 'Anonymous';
            $mTopic   = $topicLabels[$m['topic']] ?? ucfirst($m['topic']);
            $mSubject = $m['subject'] !== '' ? $m['subject'] : 'No subject';
            $mWhen    = date('M j, Y g:i A', strtotime($m['created_at']));
            // Only offer Reply when the stored address is still a valid mailto target.
            $mReply = filter_var($m['email'], FILTER_VALIDATE_EMAIL)
                ? 'mailto:' . rawurlencode($m['email']) . '?subject=' . rawurlencode('Re: ' . $mSubject)
                : '';
        ?>
            <article class="msgcard<?php echo $m['is_resolved'] ? '' : ' is-open'; ?>">
                <div class="msgcard-head">
                    <div class="msgcard-id">
                        <h3 class="msgcard-subject"><?php echo e($mSubject); ?></h3>
                        <div class="msgcard-meta">
                            <span class="msgcard-topic"><?php echo e($mTopic); ?></span>
                            <span><i class="fa-solid fa-user" aria-hidden="true"></i> <?php echo e($mName); ?></span>
                            <span><i class="fa-solid fa-envelope" aria-hidden="true"></i> <?php echo e($m['email']); ?></span>
                            <span><i class="fa-regular fa-clock" aria-hidden="true"></i> <?php echo e($mWhen); ?></span>
                            <?php if ($m['user_id']): ?>
                                <span><i class="fa-solid fa-id-card" aria-hidden="true"></i> User #<?php echo (int)$m['user_id']; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php /* Icon-only tools. The tooltip is drawn from aria-label via
                            content: attr(aria-label), so the bubble and the accessible
                            name can never drift apart, and post_action_form() needed no
                            change to carry it. */ ?>
                    <div class="msgcard-tools">
                        <span class="badge <?php echo $m['is_resolved'] ? 'badge-confirmed' : 'badge-pending'; ?>"><?php echo $m['is_resolved'] ? 'Resolved' : 'Open'; ?></span>
                        <?php if ($mReply !== ''): ?>
                            <a class="msgcard-tool msgcard-tool--reply" href="<?php echo e($mReply); ?>"
                               aria-label="Reply to <?php echo e($mName); ?>"><i class="fa-solid fa-reply" aria-hidden="true"></i></a>
                        <?php endif; ?>
                        <?php if (!$m['is_resolved']): ?>
                            <?php echo post_action_form(base_url('admin/contact_messages.php'), 'resolve_msg', (string)(int)$m['id'], '<i class="fa-solid fa-check" aria-hidden="true"></i>', 'msgcard-tool msgcard-tool--resolve', '', 'Mark as resolved'); ?>
                        <?php endif; ?>
                        <?php echo post_action_form(base_url('admin/contact_messages.php'), 'delete_msg', (string)(int)$m['id'], '<i class="fa-solid fa-trash" aria-hidden="true"></i>', 'msgcard-tool msgcard-tool--danger', 'Delete this message?', 'Delete message', [], 'Delete message?'); ?>
                    </div>
                </div>
                <p class="msgcard-body"><?php echo e($m['message']); ?></p>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
