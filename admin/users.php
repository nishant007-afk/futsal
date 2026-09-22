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
$uSql = 'SELECT u.id, u.name, u.email, u.phone, u.role, u.created_at,
            (SELECT COUNT(*) FROM grounds g WHERE g.manager_id = u.id) AS grounds_owned
     FROM users u
     WHERE ' . implode(' AND ', $uWhere) . '
     ORDER BY u.id';
$uStmt = $conn->prepare($uSql);
if ($uTypes !== '') { $uStmt->bind_param($uTypes, ...$uParams); }
$uStmt->execute();
$users = $uStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$errors = form_errors();
$old = form_old();
$affectedId = (int)($old['user_id'] ?? 0);

if (isset($_GET['export']) || isset($_GET['export_excel'])) {
    $rows = [['ID', 'Name', 'Email', 'Phone', 'Role', 'Grounds Owned', 'Joined At']];
    foreach ($users as $u) {
        $rows[] = [$u['id'], $u['name'], $u['email'], $u['phone'] ?? '', $u['role'], $u['grounds_owned'], $u['created_at']];
    }
    if (isset($_GET['export_excel'])) {
        export_excel($rows, 'users.xlsx');
    } else {
        export_csv($rows, 'users.csv');
    }
}

$page_title = 'Manage Users';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head dash-page-head">
    <a href="<?php echo base_url('admin/dashboard.php'); ?>" class="page-back-arrow" data-back aria-label="Back to dashboard"><i class="fa-solid fa-arrow-left"></i></a>
    <div class="dash-head-main">
        <h2>Manage Users</h2>
        <div class="actions">
            <a href="<?php echo base_url('admin/users.php?export_excel=1'); ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-file-excel"></i> Export Excel</a>
            <a href="<?php echo base_url('admin/users.php?export=1'); ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-file-csv"></i> Export CSV</a>
        </div>
    </div>
</div>

<?php if (!empty($errors['general'])): render_inline($errors['general']); endif; ?>

<div class="table-toolbar reveal" style="margin-bottom:14px;">
    <form method="get" action="<?php echo base_url('admin/users.php'); ?>" class="courts-search" style="max-width:640px;">
        <div class="search-field">
            <label for="uSearch">Search</label>
            <input type="text" id="uSearch" name="search" placeholder="Name or email" value="<?php echo e($search); ?>">
        </div>
        <div class="search-field">
            <label for="uRole">Role</label>
            <select id="uRole" name="role">
                <option value="">All roles</option>
                <option value="user" <?php echo $roleFilter === 'user' ? 'selected' : ''; ?>>Player</option>
                <option value="manager" <?php echo $roleFilter === 'manager' ? 'selected' : ''; ?>>Manager</option>
                <option value="admin" <?php echo $roleFilter === 'admin' ? 'selected' : ''; ?>>Admin</option>
            </select>
        </div>
        <div class="toolbar-actions">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-magnifying-glass"></i> Filter</button>
            <?php if ($search !== '' || $roleFilter !== ''): ?>
                <a href="<?php echo base_url('admin/users.php'); ?>" class="btn btn-outline"><i class="fa-solid fa-xmark"></i> Clear</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="table-wrap reveal">
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
                    <td><?php echo e($u['name']); ?></td>
                    <td><?php echo e($u['email']); ?></td>
                    <td><span class="badge badge-<?php echo e($u['role']); ?>"><?php echo e($u['role']); ?></span></td>
                    <td class="num"><?php echo (int)$u['grounds_owned']; ?></td>
                    <td>
                        <div class="actions">
                            <form method="post" action="" class="role-switch<?php echo (int)$u['id'] === $affectedId ? ' has-error' : ''; ?>" data-role="<?php echo e($u['role']); ?>">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="id" value="<?php echo (int)$u['id']; ?>">
                                <span class="role-icon"><i class="fa-solid fa-user-tag"></i></span>
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
                                <form method="post" action="" style="display:inline;" onsubmit="return confirm('Delete this user?');">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="delete_user" value="<?php echo (int)$u['id']; ?>">
                                    <button type="submit" class="btn btn-danger btn-sm" aria-label="Delete user"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

