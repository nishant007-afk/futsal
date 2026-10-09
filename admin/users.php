<?php
require_once __DIR__ . '/../config/db.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    verify_csrf();
    $id = (int)$_POST['delete_user'];
    if ($id === (int)$_SESSION['user_id']) {
        set_flash_error(
            'You can\'t delete your own account.',
            'Deleting it would lock you out of the platform.',
            'Use the role switcher to manage your account instead.',
            'admin/users.php'
        );
    } else {
        // Prevent deleting the last admin
        $delTarget = $conn->prepare('SELECT role FROM users WHERE id = ?');
        $delTarget->bind_param('i', $id);
        $delTarget->execute();
        $delRow = $delTarget->get_result()->fetch_assoc();
        $delTarget->close();
        if ($delRow && $delRow['role'] === 'admin') {
            $adminCount = $conn->query('SELECT COUNT(*) c FROM users WHERE role = "admin"')->fetch_assoc()['c'] ?? 0;
            if ((int)$adminCount <= 1) {
                set_flash_error(
                    'Cannot delete the last admin.',
                    'This would lock out all administrators.',
                    'Promote another user to admin first.',
                    'admin/users.php'
                );
                redirect('admin/users.php');
            }
        }
        $stmt = $conn->prepare('DELETE FROM users WHERE id = ?');
        $stmt->bind_param('i', $id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            set_flash('success', 'User deleted.');
        } else {
            set_flash_error(
                'Could not delete that user.',
                'The account may have already been removed.',
                'Refresh the user list to confirm the current state.',
                'admin/users.php'
            );
        }
    }
    redirect('admin/users.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['role'])) {
    verify_csrf();
    $id = (int)$_POST['id'];
    $role = $_POST['role'];
    if (!in_array($role, ['admin', 'manager', 'user'], true)) {
        flash_form(['role' => 'Invalid role.'], ['user_id' => $id, 'role' => $role]);
    } elseif ($id === (int)$_SESSION['user_id'] && $role !== 'admin') {
        flash_form(['role' => 'You can\'t change your own role - you are the logged-in admin.'], ['user_id' => $id, 'role' => $role]);
    } else {
        // Prevent demoting the last admin
        $targetUser = $conn->prepare('SELECT role FROM users WHERE id = ?');
        $targetUser->bind_param('i', $id);
        $targetUser->execute();
        $targetRow = $targetUser->get_result()->fetch_assoc();
        $targetUser->close();
        if ($targetRow && $targetRow['role'] === 'admin' && $role !== 'admin') {
            $adminCount = $conn->query('SELECT COUNT(*) c FROM users WHERE role = "admin"')->fetch_assoc()['c'] ?? 0;
            if ((int)$adminCount <= 1) {
                flash_form(['role' => 'Cannot demote the last admin. Promote another user first.'], ['user_id' => $id, 'role' => $role]);
                redirect('admin/users.php');
            }
        }
        $stmt = $conn->prepare('UPDATE users SET role = ? WHERE id = ?');
        $stmt->bind_param('si', $role, $id);
        if ($stmt->execute()) {
            if ($role === 'manager' && !manager_subscription($id)) {
                create_manager_subscription($id);
            }
            set_flash('success', 'User role updated.');
        } else {
            flash_form(['general' => 'Could not update role.'], ['user_id' => $id, 'role' => $role]);
        }
    }
    redirect('admin/users.php');
}

$search = trim($_GET['search'] ?? '');
$roleFilter = trim($_GET['role'] ?? '');
if (mb_strlen($search) > 80) { $search = mb_substr($search, 0, 80); }
if (!in_array($roleFilter, ['', 'admin', 'manager', 'user'], true)) { $roleFilter = ''; }

$uWhere = ['1=1'];
$uParams = [];
$uTypes = '';
if ($search !== '') {
    $uWhere[] = '(u.name LIKE ? OR u.email LIKE ?)';
    $like = '%' . $search . '%';
    $uParams[] = $like;
    $uParams[] = $like;
    $uTypes .= 'ss';
}
if ($roleFilter !== '') {
    $uWhere[] = 'u.role = ?';
    $uParams[] = $roleFilter;
    $uTypes .= 's';
}
$perPage = 10;
$page = max(1, (int)($_GET['page'] ?? 1));

// Count total matching users
$countSql = 'SELECT COUNT(*) c FROM users u WHERE ' . implode(' AND ', $uWhere);
$countStmt = $conn->prepare($countSql);
if ($uTypes !== '') { $countStmt->bind_param($uTypes, ...$uParams); }
$countStmt->execute();
$totalUsers = (int)($countStmt->get_result()->fetch_assoc()['c'] ?? 0);
$totalPages = max(1, (int)ceil($totalUsers / $perPage));
if ($page > $totalPages) { $page = $totalPages; }
$offset = ($page - 1) * $perPage;

// If exporting, retrieve all matching users without pagination limit
if (isset($_GET['export']) || isset($_GET['export_excel'])) {
    $uExportSql = 'SELECT u.id, u.name, u.email, u.phone, u.role, u.created_at,
                (SELECT COUNT(*) FROM grounds g WHERE g.manager_id = u.id) AS grounds_owned
         FROM users u
         WHERE ' . implode(' AND ', $uWhere) . '
         ORDER BY u.id';
    $uExportStmt = $conn->prepare($uExportSql);
    if ($uTypes !== '') { $uExportStmt->bind_param($uTypes, ...$uParams); }
    $uExportStmt->execute();
    $exportUsers = $uExportStmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $rows = [['ID', 'Name', 'Email', 'Phone', 'Role', 'Grounds Owned', 'Joined At']];
    foreach ($exportUsers as $u) {
        $rows[] = [$u['id'], $u['name'], $u['email'], $u['phone'] ?? '', $u['role'], $u['grounds_owned'], $u['created_at']];
    }
    if (isset($_GET['export_excel'])) {
        export_excel($rows, 'users.xlsx');
    } else {
        export_csv($rows, 'users.csv');
    }
}

// Fetch paginated users for current view
$uSql = 'SELECT u.id, u.name, u.email, u.phone, u.role, u.created_at,
            (SELECT COUNT(*) FROM grounds g WHERE g.manager_id = u.id) AS grounds_owned
     FROM users u
     WHERE ' . implode(' AND ', $uWhere) . '
     ORDER BY u.id
     LIMIT ? OFFSET ?';
$uPaginatedTypes = $uTypes . 'ii';
$uPaginatedParams = array_merge($uParams, [$perPage, $offset]);
$uStmt = $conn->prepare($uSql);
$uStmt->bind_param($uPaginatedTypes, ...$uPaginatedParams);
$uStmt->execute();
$users = $uStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$errors = form_errors();
$old = form_old();
$affectedId = (int)($old['user_id'] ?? 0);

function build_users_query(array $overrides): string
{
    $params = array_merge(
        [
            'search' => trim($_GET['search'] ?? ''),
            'role' => trim($_GET['role'] ?? ''),
        ],
        $overrides
    );
    $params = array_filter($params, fn($v) => $v !== '' && $v !== null);
    return http_build_query($params);
}

$page_title = 'Manage Users';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head dash-page-head">
    <a href="<?php echo base_url('admin/dashboard.php'); ?>" class="page-back-arrow" data-back aria-label="Back to dashboard"><i class="fa-solid fa-arrow-left"></i></a>
    <div class="dash-head-main">
        <h1 class="page-title">Manage Users</h1>
        <div class="actions">
            <span class="courts-count-pill">
                <?php if ($totalUsers > count($users)): ?>
                    Showing <?php echo count($users); ?> of <?php echo $totalUsers; ?> user<?php echo $totalUsers === 1 ? '' : 's'; ?>
                <?php else: ?>
                    <?php echo $totalUsers; ?> user<?php echo $totalUsers === 1 ? '' : 's'; ?>
                <?php endif; ?>
            </span>
            <a href="<?php echo base_url('admin/users.php?' . build_users_query(['export_excel' => 1])); ?>" class="btn btn-outline btn-sm">Export Excel</a>
            <a href="<?php echo base_url('admin/users.php?' . build_users_query(['export' => 1])); ?>" class="btn btn-outline btn-sm">Export CSV</a>
        </div>
    </div>
</div>

<?php if (!empty($errors['general'])): render_inline($errors['general']); endif; ?>

<div class="courts-toolbar reveal">
    <form method="get" action="<?php echo base_url('admin/users.php'); ?>" class="courts-search" data-ajax-results="usersResults">
        <div class="toolbar-flex-main">
            <div class="search-field sf-grow">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <input type="text" id="uSearch" name="search" placeholder="Search name or email..." value="<?php echo e($search); ?>" autocomplete="off" aria-label="Search name or email">
            </div>
            <div class="search-field">
                <i class="fa-solid fa-user-tag" aria-hidden="true"></i>
                <select id="uRole" name="role" aria-label="Filter by role">
                    <option value="">All roles</option>
                    <option value="user" <?php echo $roleFilter === 'user' ? 'selected' : ''; ?>>Player</option>
                    <option value="manager" <?php echo $roleFilter === 'manager' ? 'selected' : ''; ?>>Manager</option>
                    <option value="admin" <?php echo $roleFilter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                </select>
            </div>
            <div class="toolbar-actions">
                <?php if ($search !== '' || $roleFilter !== ''): ?>
                    <a href="<?php echo base_url('admin/users.php'); ?>" class="btn btn-outline btn-sm">Clear</a>
                <?php endif; ?>
            </div>
        </div>
    </form>

</div>

<div id="usersResults" class="reveal">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th class="num">Grounds Owned</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$users): ?>
                    <tr><td colspan="5" class="muted table-empty"><?php echo $search !== '' || $roleFilter !== '' ? 'No users match your search.' : 'No users yet.'; ?></td></tr>
                <?php endif; ?>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td data-label="Name" class="strong"><?php echo e($u['name']); ?></td>
                        <td data-label="Email"><?php echo e($u['email']); ?></td>
                        <td data-label="Role"><span class="badge badge-<?php echo e($u['role']); ?>"><?php echo ucfirst(e($u['role'])); ?></span></td>
                        <td class="num" data-label="Grounds Owned"><?php echo (int)$u['grounds_owned']; ?></td>
                        <td data-label="">
                            <div class="actions">
                                <form method="post" action="" class="role-switch<?php echo (int)$u['id'] === $affectedId ? ' has-error' : ''; ?>" data-role="<?php echo e($u['role']); ?>" novalidate>
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="id" value="<?php echo (int)$u['id']; ?>">
                                    <select name="role" data-role-select aria-label="Change role for <?php echo e($u['name']); ?>">
                                        <?php $selRole = ((int)$u['id'] === $affectedId && !empty($old['role'])) ? (string)$old['role'] : $u['role']; ?>
                                        <?php foreach (['user' => 'Player', 'manager' => 'Manager', 'admin' => 'Admin'] as $val => $label): ?>
                                            <option value="<?php echo $val; ?>" <?php echo $selRole === $val ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <noscript><button class="btn btn-outline btn-sm" type="submit" aria-label="Save role"><i class="fa-solid fa-check"></i></button></noscript>
                                    <?php if ((int)$u['id'] === $affectedId && !empty($errors['role'])): ?>
                                        <p class="field-error role-error"><i class="fa-solid fa-circle-exclamation"></i><?php echo e($errors['role']); ?></p>
                                    <?php endif; ?>
                                </form>
                                <?php if ((int)$u['id'] !== (int)$_SESSION['user_id']): ?>
                                    <form method="post" action="" style="display:inline;" data-confirm="Are you sure you want to delete this user? All their data will be removed." data-confirm-title="Delete user" data-confirm-ok="Yes, delete" data-confirm-cancel="No" novalidate>
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="delete_user" value="<?php echo (int)$u['id']; ?>">
                                        <button type="submit" class="btn btn-outline btn-sm" title="Delete user" aria-label="Delete user" style="color:var(--danger); border-color:var(--line);"><i class="fa-solid fa-trash"></i></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <nav class="pagination" aria-label="Users pages" data-ajax-link="usersResults">
            <?php if ($page > 1): ?>
                <a class="page-link" href="<?php echo base_url('admin/users.php?' . build_users_query(['page' => $page - 1])); ?>" aria-label="Previous page"><i class="fa-solid fa-chevron-left"></i></a>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a class="page-link <?php echo $i === $page ? 'active' : ''; ?>" href="<?php echo base_url('admin/users.php?' . build_users_query(['page' => $i])); ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
                <a class="page-link" href="<?php echo base_url('admin/users.php?' . build_users_query(['page' => $page + 1])); ?>" aria-label="Next page"><i class="fa-solid fa-chevron-right"></i></a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

