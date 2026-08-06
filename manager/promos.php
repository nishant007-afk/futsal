<?php
require_once __DIR__ . '/../config/db.php';
require_manager();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_promo'])) {
    verify_csrf();
    $code = strtoupper(trim($_POST['code'] ?? ''));
    $discount_type = $_POST['discount_type'] ?? 'percent';
    $discount_value = (float)($_POST['discount_value'] ?? 0);
    $min_total = (float)($_POST['min_total'] ?? 0);
    $max_uses = (int)($_POST['max_uses'] ?? 0);
    $starts_at = trim($_POST['starts_at'] ?? '') ?: null;
    $expires_at = trim($_POST['expires_at'] ?? '') ?: null;

    if (!preg_match('/^[A-Z0-9_-]{3,40}$/', $code)) {
        $errors['code'] = 'Code must be 3-40 characters using letters, numbers, dash or underscore.';
    }
    if (!in_array($discount_type, ['percent', 'flat'], true)) {
        $errors['discount_type'] = 'Discount type is invalid.';
    } else {
        if ($discount_type === 'percent' && ($discount_value <= 0 || $discount_value > 100)) {
            $errors['discount_value'] = 'Percent discount must be between 1 and 100.';
        } elseif ($discount_type === 'flat' && $discount_value <= 0) {
            $errors['discount_value'] = 'Flat discount must be greater than 0.';
        }
    }
    if ($min_total < 0) {
        $errors['min_total'] = 'Minimum total can\'t be a negative amount.';
    }
    if ($max_uses < 0) {
        $errors['max_uses'] = 'Max uses can\'t be negative.';
    }
    if ($starts_at !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $starts_at)) {
        $errors['starts_at'] = 'Start date must use YYYY-MM-DD format.';
    }
    if ($expires_at !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expires_at)) {
        $errors['expires_at'] = 'End date must use YYYY-MM-DD format.';
    }
    if ($starts_at && $expires_at && $expires_at < $starts_at) {
        $errors['expires_at'] = 'End date must be on or after the start date.';
    }
    if ($errors) {
        flash_form($errors, [
            'code' => $code,
            'discount_type' => $discount_type,
            'discount_value' => (string)$discount_value,
            'min_total' => (string)$min_total,
            'max_uses' => (string)$max_uses,
            'starts_at' => (string)$starts_at,
            'expires_at' => (string)$expires_at,
        ]);
    } else {
        $stmt = $conn->prepare('INSERT INTO promo_codes (manager_id, code, discount_type, discount_value, min_total, max_uses, starts_at, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('issddiss', $_SESSION['user_id'], $code, $discount_type, $discount_value, $min_total, $max_uses, $starts_at, $expires_at);
        if ($stmt->execute()) {
            set_flash('success', 'Promo code ' . $code . ' created for your courts.');
        } else {
        set_flash_error(
            'That promo code already exists.',
            'Each code must be unique on the platform.',
            'Pick a different code and try again.',
            'manager/promos.php'
        );
        }
    }
    redirect('manager/promos.php');
}

if (isset($_GET['toggle']) && isset($_GET['csrf']) && hash_equals($_SESSION['csrf_token'] ?? '', $_GET['csrf'])) {
    $id = (int)$_GET['toggle'];
    $stmt = $conn->prepare('UPDATE promo_codes SET is_active = 1 - is_active WHERE id = ? AND manager_id = ?');
    $stmt->bind_param('ii', $id, $_SESSION['user_id']);
    $stmt->execute();
    set_flash('success', 'Promo code updated.');
    redirect('manager/promos.php');
}

if (isset($_GET['delete']) && isset($_GET['csrf']) && hash_equals($_SESSION['csrf_token'] ?? '', $_GET['csrf'])) {
    $id = (int)$_GET['delete'];
    $stmt = $conn->prepare('DELETE FROM promo_codes WHERE id = ? AND manager_id = ?');
    $stmt->bind_param('ii', $id, $_SESSION['user_id']);
    $stmt->execute();
    set_flash('success', 'Promo code deleted.');
    redirect('manager/promos.php');
}

$promos = manager_promo_codes((int)$_SESSION['user_id']);
$today = date('Y-m-d');
$myGroundCount = $conn->query('SELECT COUNT(*) c FROM grounds WHERE manager_id = ' . (int)$_SESSION['user_id'])->fetch_assoc()['c'];
$errors = form_errors();
$old = form_old();

$page_title = 'Promo Codes';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <h2><i class="fa-solid fa-tags"></i> My Promo Codes</h2>
</div>
<p class="muted" style="margin-bottom:20px;">Create discount codes players can use <strong>only on your courts</strong>. Codes you make here won't work at other managers' grounds.</p>

<div class="form-card reveal" style="max-width:560px;margin-bottom:30px;">
    <h3 style="margin-bottom:6px;"><i class="fa-solid fa-wand-magic-sparkles"></i> New promo code</h3>
    <form method="post" action="" novalidate>
        <?php echo csrf_field(); ?>
        <div class="form-group<?php echo has_error($errors, 'code'); ?>">
            <label for="code">Code</label>
            <input type="text" id="code" name="code" maxlength="40" value="<?php echo e(old_value($old, 'code')); ?>" placeholder="e.g. SUMMER20" required>
            <?php field_error($errors, 'code'); ?>
        </div>
        <div class="form-group">
            <label for="discountType">Discount type</label>
            <select id="discountType" name="discount_type">
                <option value="percent" <?php echo old_value($old, 'discount_type') === 'percent' ? 'selected' : ''; ?>>Percent off</option>
                <option value="flat" <?php echo old_value($old, 'discount_type') === 'flat' ? 'selected' : ''; ?>>Fixed amount off</option>
            </select>
        </div>
        <div class="form-group<?php echo has_error($errors, 'discount_value'); ?>">
            <label for="discountValue">Discount value</label>
            <input type="number" id="discountValue" name="discount_value" step="0.01" min="1" value="<?php echo e(old_value($old, 'discount_value')); ?>" placeholder="10 = 10%" required>
            <?php field_error($errors, 'discount_value'); ?>
        </div>
        <div class="grid grid-2">
            <div class="form-group">
                <label for="minTotal">Minimum booking (Rs) <span class="muted" style="font-weight:400;">(0 = none)</span></label>
                <input type="number" id="minTotal" name="min_total" step="0.01" min="0" value="<?php echo e(old_value($old, 'min_total', '0')); ?>">
            </div>
            <div class="form-group">
                <label for="maxUses">Max uses <span class="muted" style="font-weight:400;">(0 = unlimited)</span></label>
                <input type="number" id="maxUses" name="max_uses" min="0" value="<?php echo e(old_value($old, 'max_uses', '0')); ?>">
            </div>
        </div>
        <div class="grid grid-2">
            <div class="form-group">
                <label for="startsAt">Starts <span class="muted" style="font-weight:400;">(optional)</span></label>
                <input type="date" id="startsAt" name="starts_at" value="<?php echo e(old_value($old, 'starts_at')); ?>">
            </div>
            <div class="form-group">
                <label for="expiresAt">Expires <span class="muted" style="font-weight:400;">(optional)</span></label>
                <input type="date" id="expiresAt" name="expires_at" value="<?php echo e(old_value($old, 'expires_at')); ?>">
            </div>
        </div>
        <button type="submit" name="add_promo" value="1" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Create promo</button>
    </form>
</div>

<h3 class="reveal" style="margin-bottom:14px;"><i class="fa-solid fa-list-check"></i> My promo codes (applies to your <?php echo (int)$myGroundCount; ?> courts)</h3>
<div class="table-wrap reveal">
    <table>
        <thead>
            <tr>
                <th>Code</th>
                <th>Discount</th>
                <th>Min booking</th>
                <th>Uses</th>
                <th>Valid</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$promos): ?>
                <tr><td colspan="7" class="muted" style="text-align:center;padding:22px;">You haven't created any promos yet.</td></tr>
            <?php else: ?>
                <?php foreach ($promos as $p): ?>
                    <?php
                    $expired = $p['expires_at'] && $today > $p['expires_at'];
                    $maxed = $p['max_uses'] > 0 && (int)$p['used_count'] >= (int)$p['max_uses'];
                    ?>
                    <tr>
                        <td class="strong"><span class="promo-chip"><?php echo e($p['code']); ?></span></td>
                        <td><?php echo $p['discount_type'] === 'percent' ? number_format((float)$p['discount_value'], 0) . '% off' : 'Rs ' . number_format((float)$p['discount_value'], 0) . ' off'; ?></td>
                        <td><?php echo (float)$p['min_total'] > 0 ? 'Rs ' . number_format((float)$p['min_total'], 0) : '-'; ?></td>
                        <td><?php echo (int)$p['used_count']; ?><?php echo $p['max_uses'] > 0 ? ' / ' . (int)$p['max_uses'] : ' / ∞'; ?></td>
                        <td><?php echo $p['expires_at'] ? 'until ' . e(date('M j, Y', strtotime($p['expires_at']))) : 'no expiry'; ?></td>
                        <td>
                            <?php if ($expired || $maxed): ?>
                                <span class="badge badge-cancelled"><?php echo $expired ? 'Expired' : 'Maxed'; ?></span>
                            <?php elseif ((int)$p['is_active'] === 1): ?>
                                <span class="badge badge-confirmed">Active</span>
                            <?php else: ?>
                                <span class="badge badge-pending">Paused</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="row-actions">
                                <a href="<?php echo base_url('manager/promos.php?toggle=' . (int)$p['id'] . '&csrf=' . csrf_token()); ?>" class="btn btn-outline btn-xs" title="Toggle active"><i class="fa-solid <?php echo (int)$p['is_active'] === 1 ? 'fa-pause' : 'fa-play'; ?>"></i></a>
                                <a href="<?php echo base_url('manager/promos.php?delete=' . (int)$p['id'] . '&csrf=' . csrf_token()); ?>" class="btn btn-danger btn-xs" data-confirm="Delete this promo code?" title="Delete"><i class="fa-solid fa-trash"></i></a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
