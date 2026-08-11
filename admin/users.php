<?php
require_once __DIR__ . '/../config/db.php';
require_admin();

if (isset($_GET['impersonate'])) {
    if (!isset($_GET['csrf']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_GET['csrf'])) {
        exit('Invalid request.');
    }
    $id = (int)$_GET['impersonate'];
    if ($id === (int)$_SESSION['user_id']) {
        set_flash_error('You cannot impersonate yourself.', 'That would be redundant.', 'Pick another user.', 'admin/users.php');
    } else {
        $stmt = $conn->prepare('SELECT id, name, email, role FROM users WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $target = $stmt->get_result()->fetch_assoc();
        if ($target) {
            $_SESSION['impersonated_from'] = (int)$_SESSION['user_id'];
            $_SESSION['user_id'] = (int)$target['id'];
            $_SESSION['user_name'] = $target['name'];
            $_SESSION['user_email'] = $target['email'];
            $_SESSION['user_role'] = $target['role'];
            set_flash('success', 'Now impersonating ' . $target['name'] . ' (' . $target['role'] . ').');
            redirect(base_url('index.php'));
        } else {
            set_flash_error('User not found.', 'The account may have been deleted.', 'Refresh the user list.', 'admin/users.php');
        }
    }
    redirect('admin/users.php');
}

if (isset($_GET['stop_impersonate'])) {
    if (!isset($_GET['csrf']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_GET['csrf'])) {
        exit('Invalid request.');
    }
    if (!empty($_SESSION['impersonated_from'])) {
        $orig = (int)$_SESSION['impersonated_from'];
        $stmt = $conn->prepare('SELECT id, name, email, role FROM users WHERE id = ?');
        $stmt->bind_param('i', $orig);
        $stmt->execute();
        $origUser = $stmt->get_result()->fetch_assoc();
        if ($origUser) {
            $_SESSION['user_id'] = $origUser['id'];
            $_SESSION['user_name'] = $origUser['name'];
            $_SESSION['user_email'] = $origUser['email'];
            $_SESSION['user_role'] = $origUser['role'];
            unset($_SESSION['impersonated_from']);
            set_flash('success', 'Stopped impersonating. You are back as ' . $origUser['name'] . '.');
            redirect(base_url('admin/users.php'));
        }
    }
    redirect('admin/users.php');
}

if (isset($_GET['delete'])) {
    if (!isset($_GET['csrf']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_GET['csrf'])) {
        exit('Invalid request.');
    }
    $id = (int)$_GET['delete'];
    if ($id === (int)$_SESSION['user_id']) {
        set_flash_error(
            'You can\'t delete your own account.',
            'Deleting it would lock you out of the platform.',
            'Use the role switcher to manage your account instead.',
            'admin/users.php'
        );
    } else {
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

$users = $conn->query(
    'SELECT u.id, u.name, u.email, u.phone, u.role, u.created_at,
            (SELECT COUNT(*) FROM grounds g WHERE g.manager_id = u.id) AS grounds_owned
     FROM users u
     ORDER BY u.id'
)->fetch_all(MYSQLI_ASSOC);

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
    }
    export_csv($rows, 'users.csv');
}

$page_title = 'Manage Users';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head dash-page-head">
    <a href="<?php echo base_url('admin/dashboard.php'); ?>" class="nav-back mob-back" aria-label="Back to dashboard"><i class="fa-solid fa-chevron-left"></i></a>
    <div class="dash-head-main">
        <h2><i class="fa-solid fa-users"></i> Manage Users</h2>
        <div class="actions">
            <a href="<?php echo base_url('admin/users.php?export_excel=1'); ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-file-excel"></i> Export Excel</a>
            <a href="<?php echo base_url('admin/users.php?export=1'); ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-file-csv"></i> Export CSV</a>
            <a href="<?php echo base_url('admin/dashboard.php'); ?>" class="btn btn-outline btn-sm dash-in-actions"><i class="fa-solid fa-arrow-left"></i> Dashboard</a>
        </div>
    </div>
</div>

<?php if (!empty($errors['general'])): render_inline($errors['general']); endif; ?>

<div class="table-wrap reveal">
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Grounds Owned</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><i class="fa-solid fa-user muted"></i> <?php echo e($u['name']); ?></td>
                    <td><?php echo e($u['email']); ?></td>
                    <td><span class="badge badge-<?php echo e($u['role']); ?>"><?php echo e($u['role']); ?></span></td>
                    <td><?php echo (int)$u['grounds_owned']; ?></td>
                    <td>
                        <div class="actions">
                            <form method="post" action="" class="role-switch<?php echo (int)$u['id'] === $affectedId ? ' has-error' : ''; ?>" data-role="<?php echo e($u['role']); ?>">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="id" value="<?php echo (int)$u['id']; ?>">
                                <span class="role-icon"><i class="fa-solid fa-user-tag"></i></span>
                                <select name="role" data-role-select>
                                    <?php $selRole = ((int)$u['id'] === $affectedId && !empty($old['role'])) ? (string)$old['role'] : $u['role']; ?>
                                    <?php foreach (['user' => 'Player', 'manager' => 'Manager', 'admin' => 'Admin'] as $val => $label): ?>
                                        <option value="<?php echo $val; ?>" <?php echo $selRole === $val ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <noscript><button class="btn btn-outline btn-sm" type="submit" aria-label="Make admin"><i class="fa-solid fa-check"></i></button></noscript>
                                <?php if ((int)$u['id'] === $affectedId && !empty($errors['role'])): ?>
                                    <p class="field-error role-error"><i class="fa-solid fa-circle-exclamation"></i><?php echo e($errors['role']); ?></p>
                                <?php endif; ?>
                            </form>
                            <?php if ((int)$u['id'] !== (int)$_SESSION['user_id']): ?>
                                <a href="<?php echo base_url('admin/users.php?impersonate=' . (int)$u['id'] . '&csrf=' . csrf_token()); ?>" class="btn btn-outline btn-sm" data-confirm="Log in as this user?" aria-label="Impersonate user"><i class="fa-solid fa-user-secret"></i></a>
                                <a href="<?php echo base_url('admin/users.php?delete=' . (int)$u['id'] . '&csrf=' . csrf_token()); ?>" class="btn btn-danger btn-sm" data-confirm="Delete this user?" aria-label="Delete user"><i class="fa-solid fa-trash"></i></a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

