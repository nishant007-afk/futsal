<?php
require_once __DIR__ . '/../config/db.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    redirect('admin/users.php');
}

$stmt = $conn->prepare('SELECT id, name, email, phone, role, email_verified, created_at FROM users WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
if (!$user) {
    set_flash('error', 'User not found.');
    redirect('admin/users.php');
}

$errors = [];
$name = $user['name'];
$email = $user['email'];
$phone = $user['phone'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if ($name === '' || strlen($name) < 2) {
        $errors['name'] = 'Name must be at least 2 characters.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address.';
    }
    if (strlen($phone) > 20) {
        $errors['phone'] = 'Phone number is too long.';
    }

    if (!$errors) {
        $stmt = $conn->prepare('UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?');
        $stmt->bind_param('sssi', $name, $email, $phone, $id);
        if ($stmt->execute()) {
            set_flash('success', 'User updated.');
            redirect('admin/users.php');
        } else {
            $errors['general'] = 'Could not update user.';
        }
    }
}

$page_title = 'Edit User';
require __DIR__ . '/../includes/header.php';
?>

<div class="container py-lg-40">
    <div class="page-top">
        <a href="<?php echo base_url('admin/users.php'); ?>" class="page-back-arrow" data-back aria-label="Go back"><i class="fa-solid fa-arrow-left"></i></a>
        <h1>Edit User</h1>
    </div>

    <div class="settings-card reveal">
        <div class="settings-body">
            <?php if (!empty($errors['general'])): render_inline($errors['general']); endif; ?>
            <form method="post" action="" novalidate>
                <?php echo csrf_field(); ?>
                <div class="form-group<?php echo has_error($errors, 'name'); ?>">
                    <div class="input-group floating">
                        <input type="text" id="name" name="name" value="<?php echo e($name); ?>" placeholder=" " maxlength="100" required>
                        <label for="name">Name <span class="req">*</span></label>
                    </div>
                    <?php field_error($errors, 'name'); ?>
                </div>
                <div class="form-group<?php echo has_error($errors, 'email'); ?>">
                    <div class="input-group floating">
                        <input type="email" id="email" name="email" value="<?php echo e($email); ?>" placeholder=" " required>
                        <label for="email">Email <span class="req">*</span></label>
                    </div>
                    <?php field_error($errors, 'email'); ?>
                </div>
                <div class="form-group<?php echo has_error($errors, 'phone'); ?>">
                    <div class="input-group floating">
                        <input type="tel" id="phone" name="phone" value="<?php echo e($phone); ?>" placeholder=" " maxlength="20">
                        <label for="phone">Phone</label>
                    </div>
                    <?php field_error($errors, 'phone'); ?>
                </div>
                <p class="form-hint">Role: <strong><?php echo e(ucfirst($user['role'])); ?></strong> &middot; Member since <?php echo date('M j, Y', strtotime($user['created_at'])); ?></p>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Save changes</button>
            </form>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
