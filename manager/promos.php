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
        if ($discount_type === 'percent' && ($discount_value <= 0 || $discount_value > 90)) {
            $errors['discount_value'] = 'Percent discount must be between 1 and 90 (100% free codes are not allowed).';
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_promo'])) {
    verify_csrf();
    $id = (int)$_POST['toggle_promo'];
    $stmt = $conn->prepare('UPDATE promo_codes SET is_active = 1 - is_active WHERE id = ? AND manager_id = ?');
    $stmt->bind_param('ii', $id, $_SESSION['user_id']);
    $stmt->execute();
    set_flash('success', 'Promo code updated.');
    redirect('manager/promos.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_promo'])) {
    verify_csrf();
    $id = (int)$_POST['delete_promo'];
    $stmt = $conn->prepare('DELETE FROM promo_codes WHERE id = ? AND manager_id = ?');
    $stmt->bind_param('ii', $id, $_SESSION['user_id']);
    $stmt->execute();
    set_flash('success', 'Promo code deleted.');
    redirect('manager/promos.php');
}

$promos = manager_promo_codes((int)$_SESSION['user_id']);
$today = date('Y-m-d');
$errors = form_errors();
$old = form_old();

// Summary counters above the table. Derived from the same rows the table renders,
// using the shared promo_status() so the two can never disagree.
$promoStats = manager_promo_stats((int)$_SESSION['user_id'], $promos, $today);

$page_title = 'Promo Codes';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <div class="title-back-row">
        <a href="<?php echo base_url('manager/dashboard.php'); ?>" class="page-back-arrow" data-back aria-label="Go back"><i class="fa-solid fa-arrow-left"></i></a>
        <h1 class="page-title">My Promo Codes</h1>
    </div>
</div>

<div class="promos-layout">
<div class="form-card reveal md" id="promoCodeForm">
    <h3>New promo code</h3>
    <form method="post" action="" class="promo-form" novalidate>
        <?php echo csrf_field(); ?>
        <div class="form-group<?php echo has_error($errors, 'code'); ?>">
            <div class="input-group floating">
                <!-- KEPT (removed per request): the code field had no case styling, so a manager
                     typing "dash10" saw it stored that way until submit. text-transform now shows
                     DASH10 as they type; the server still uppercases on save as the real guard. -->
                <input type="text" id="code" name="code" maxlength="40" value="<?php echo e(old_value($old, 'code')); ?>" placeholder=" " required
                       class="js-upper" autocapitalize="characters" autocomplete="off" spellcheck="false">
                <label for="code">Code <span class="req">*</span></label>
            </div>
            <?php field_error($errors, 'code'); ?>
        </div>
        <div class="form-group<?php echo has_error($errors, 'discount_type'); ?>">
            <label for="discountType">Discount type</label>
            <!-- KEPT (removed per request): the value below was a bare number box, so a manager
                 could not tell "Rs 20 off" from "20% off" without looking at the dropdown.
                 data-affix-target lets the script swap the inner unit when this changes. -->
            <select id="discountType" name="discount_type" data-affix-for="discountValue">
                <option value="percent" <?php echo old_value($old, 'discount_type', 'percent') === 'percent' ? 'selected' : ''; ?>>Percent off</option>
                <option value="flat" <?php echo old_value($old, 'discount_type') === 'flat' ? 'selected' : ''; ?>>Fixed amount off</option>
            </select>
            <?php field_error($errors, 'discount_type'); ?>
        </div>
        <div class="form-group<?php echo has_error($errors, 'discount_value'); ?>">
            <div class="input-group floating affix-group" id="discountValueGroup"
                 data-affix-state="<?php echo old_value($old, 'discount_type', 'percent') === 'flat' ? 'flat' : 'percent'; ?>">
                <span class="affix affix-prefix" id="discountValuePrefix" aria-hidden="true">Rs.</span>
                <input type="number" id="discountValue" name="discount_value" step="0.01" min="1" value="<?php echo e(old_value($old, 'discount_value')); ?>" placeholder=" " required>
                <span class="affix affix-suffix" id="discountValueSuffix" aria-hidden="true">%</span>
                <label for="discountValue">Discount value <span class="req">*</span></label>
            </div>
            <?php field_error($errors, 'discount_value'); ?>
        </div>
        <div class="grid grid-2">
            <!-- KEPT (removed per request): the instruction was packed into the floating label
                 itself - "Minimum booking (Rs) (0 = none)" and "Max uses (0 = unlimited)". The
                 labels are now short and the guidance sits below the box as helper text, which
                 keeps the label readable while the hint stays discoverable. -->
            <div class="form-group<?php echo has_error($errors, 'min_total'); ?>">
                <div class="input-group floating">
                    <input type="number" id="minTotal" name="min_total" step="0.01" min="0" value="<?php echo e(old_value($old, 'min_total', '0')); ?>" placeholder=" ">
                    <label for="minTotal">Minimum Booking Amount</label>
                </div>
                <p class="field-hint">Leave 0 for no minimum amount</p>
                <?php field_error($errors, 'min_total'); ?>
            </div>
            <div class="form-group<?php echo has_error($errors, 'max_uses'); ?>">
                <div class="input-group floating">
                    <input type="number" id="maxUses" name="max_uses" min="0" value="<?php echo e(old_value($old, 'max_uses', '0')); ?>" placeholder=" ">
                    <label for="maxUses">Usage Limit</label>
                </div>
                <p class="field-hint">Leave 0 for unlimited uses</p>
                <?php field_error($errors, 'max_uses'); ?>
            </div>
        </div>

        <!-- KEPT (removed per request): Starts/Expires were floating labels inside date inputs.
             A date input cannot show a placeholder, so the floating label never lifted reliably and
             the pair read as an undated stack of boxes. They are now ordinary labelled fields
             side by side, which is also what the two-column layout wants. -->
        <div class="grid grid-2">
            <div class="form-group<?php echo has_error($errors, 'starts_at'); ?>">
                <label for="startsAt">Start Date <span class="muted">(optional)</span></label>
                <input type="date" id="startsAt" name="starts_at" value="<?php echo e(old_value($old, 'starts_at')); ?>">
                <?php field_error($errors, 'starts_at'); ?>
            </div>
            <div class="form-group<?php echo has_error($errors, 'expires_at'); ?>">
                <label for="expiresAt">End Date <span class="muted">(optional)</span></label>
                <input type="date" id="expiresAt" name="expires_at" value="<?php echo e(old_value($old, 'expires_at')); ?>">
                <?php field_error($errors, 'expires_at'); ?>
            </div>
        </div>
        <button type="submit" name="add_promo" value="1" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Create promo</button>
    </form>
</div>

<div class="promos-list">
<h3 class="reveal block-title">My promo codes</h3>

<!-- KEPT (removed per request): the table had no summary, so a manager could not see at a
     glance whether any code was actually working. Three counters derived from the rows below. -->
<div class="promo-stats reveal" role="group" aria-label="Promo code summary">
    <div class="promo-stat">
        <span class="promo-stat-label">Active Promos</span>
        <strong class="promo-stat-value"><?php echo (int)$promoStats['active']; ?></strong>
        <span class="promo-stat-hint">of <?php echo (int)$promoStats['total']; ?> total</span>
    </div>
    <div class="promo-stat">
        <span class="promo-stat-label">Total Redemptions</span>
        <strong class="promo-stat-value"><?php echo number_format((int)$promoStats['redemptions']); ?></strong>
        <span class="promo-stat-hint">times used at checkout</span>
    </div>
    <div class="promo-stat">
        <span class="promo-stat-label">Total Discount Given</span>
        <strong class="promo-stat-value"><?php echo format_price((float)$promoStats['discount']); ?></strong>
        <span class="promo-stat-hint">across all bookings</span>
    </div>
</div>

<?php if (!$promos): ?>
    <!-- KEPT (removed per request): the empty table rendered column headers with a single grey
         line, "You haven't created any promos yet.", under seven irrelevant column labels.
         The bare <table> markup is kept here, commented out. -->
    <!--
    <div class="table-wrap reveal">
        <table>
            <thead>
                <tr>
                    <th>Code</th><th class="num">Discount</th><th class="num">Min booking</th>
                    <th class="num">Uses</th><th>Valid</th><th>Status</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr><td colspan="7" class="muted table-empty">You haven't created any promos yet.</td></tr>
            </tbody>
        </table>
    </div>
    -->
    <div class="promo-empty reveal">
        <span class="promo-empty-icon"><i class="fa-solid fa-ticket"></i></span>
        <h3>No active promo codes</h3>
        <p>
            Discount codes are the cheapest way to fill quiet off-peak slots. Create a code and
            share it on WhatsApp or social media - players apply it at checkout and the discount
            comes straight out of your booking total.
        </p>
        <a href="#promoCodeForm" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-plus"></i> Create your first promo
        </a>
    </div>
<?php else: ?>
    <div class="table-wrap reveal">
        <table>
            <thead>
                <tr>
                    <th>Code</th>
                    <th class="num">Discount</th>
                    <th class="num">Min booking</th>
                    <th class="num">Uses</th>
                    <th>Valid</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($promos as $p): ?>
                    <?php
                    // Single source of truth for the label + colour, shared with the counters above.
                    $st = promo_status($p, $today);
                    $canToggle = !in_array($st['key'], ['expired', 'depleted'], true);
                    ?>
                    <tr>
                        <td class="strong" data-label="Code">
                            <div class="promo-code-cell">
                                <span class="promo-chip"><?php echo e($p['code']); ?></span>
                                <!-- KEPT (removed per request): there was no way to copy a code, so
                                     sharing one meant retyping it by hand from the table. -->
                                <button type="button" class="btn btn-outline btn-xs promo-copy"
                                        data-copy="<?php echo e($p['code']); ?>"
                                        aria-label="Copy promo code <?php echo e($p['code']); ?>">
                                    <i class="fa-solid fa-copy"></i> <span class="promo-copy-text">Copy Code</span>
                                </button>
                            </div>
                        </td>
                        <td class="num" data-label="Discount"><?php echo $p['discount_type'] === 'percent' ? number_format((float)$p['discount_value'], 0) . '% off' : 'Rs ' . number_format((float)$p['discount_value'], 0) . ' off'; ?></td>
                        <td class="num" data-label="Min booking"><?php echo (float)$p['min_total'] > 0 ? 'Rs ' . number_format((float)$p['min_total'], 0) : '-'; ?></td>
                        <td class="num" data-label="Uses"><?php echo (int)$p['used_count']; ?><?php echo $p['max_uses'] > 0 ? ' / ' . (int)$p['max_uses'] : ' / &infin;'; ?></td>
                        <td data-label="Valid"><?php echo $p['expires_at'] ? 'until ' . e(date('M j, Y', strtotime($p['expires_at']))) : 'no expiry'; ?></td>
                        <td data-label="Status">
                            <!-- KEPT (removed per request): the badge only distinguished
                                 Expired / Maxed / Active / Paused, so a code about to lapse looked
                                 identical to a healthy one. promo_status() now also reports
                                 "Expiring Soon" (today or tomorrow) and "Scheduled", with tones. -->
                            <span class="badge promo-badge promo-badge--<?php echo e($st['tone']); ?>">
                                <?php echo e($st['label']); ?>
                            </span>
                        </td>
                        <td data-label="">
                            <div class="row-actions">
                                <!-- KEPT (removed per request): pausing was an icon-only button that
                                     looked like a delete control. It is now a labelled switch that
                                     still posts the same toggle_promo action, so the backend and CSRF
                                     handling are unchanged. Expired and depleted codes cannot be
                                     reactivated, so the switch is disabled for them. -->
                                <form method="post" action="" class="promo-toggle-form" novalidate>
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="toggle_promo" value="<?php echo (int)$p['id']; ?>">
                                    <button type="submit"
                                            class="promo-switch<?php echo (int)$p['is_active'] === 1 ? ' is-on' : ''; ?>"
                                            role="switch"
                                            aria-checked="<?php echo (int)$p['is_active'] === 1 ? 'true' : 'false'; ?>"
                                            <?php echo $canToggle ? '' : 'disabled title="Expired or depleted codes cannot be reactivated"'; ?>>
                                        <span class="promo-switch-track"><span class="promo-switch-thumb"></span></span>
                                        <span class="promo-switch-text"><?php echo (int)$p['is_active'] === 1 ? 'Active' : 'Paused'; ?></span>
                                    </button>
                                </form>
                                <?php echo post_action_form(base_url('manager/promos.php'), 'delete_promo', (string)(int)$p['id'], '<i class="fa-solid fa-trash"></i>', 'btn btn-danger btn-xs', 'Delete this promo code?', 'Delete promo', [], 'Delete promo code?'); ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
</div>
</div>

<script>
/* Promo page: dynamic unit on the discount value, and copy-to-clipboard for codes. */
document.addEventListener('DOMContentLoaded', function () {
    // 1. Swap the inner unit when the discount type changes.
    //    "Percent off" -> % badge on the right edge, "Fixed amount off" -> Rs. prefix.
    var typeSel = document.getElementById('discountType');
    var group = document.getElementById('discountValueGroup');
    var prefix = document.getElementById('discountValuePrefix');
    var suffix = document.getElementById('discountValueSuffix');

    function syncAffix() {
        if (!typeSel || !group) return;
        var isFlat = typeSel.value === 'flat';
        group.setAttribute('data-affix-state', isFlat ? 'flat' : 'percent');
        if (prefix) prefix.hidden = !isFlat;
        if (suffix) suffix.hidden = isFlat;
    }
    if (typeSel) {
        typeSel.addEventListener('change', syncAffix);
        syncAffix();                       // apply the restored value on first paint too
    }

    // 2. Copy Code buttons.
    document.querySelectorAll('[data-copy]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var text = btn.getAttribute('data-copy') || '';
            var label = btn.querySelector('.promo-copy-text');
            var original = label ? label.textContent : null;

            function flash(ok) {
                var icon = btn.querySelector('i');
                if (icon) icon.className = ok ? 'fa-solid fa-check' : 'fa-solid fa-xmark';
                if (label) label.textContent = ok ? 'Copied' : 'Press Ctrl+C';
                btn.classList.toggle('is-copied', ok);
                btn.classList.toggle('is-failed', !ok);
                setTimeout(function () {
                    if (icon) icon.className = 'fa-solid fa-copy';
                    if (label && original !== null) label.textContent = original;
                    btn.classList.remove('is-copied', 'is-failed');
                }, 1600);
            }

            // navigator.clipboard needs a secure context; fall back to a hidden textarea
            // so this still works on plain http:// on a LAN or local XAMPP.
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(function () { flash(true); }, function () { legacy(); });
            } else {
                legacy();
            }

            function legacy() {
                try {
                    var ta = document.createElement('textarea');
                    ta.value = text;
                    ta.setAttribute('readonly', '');
                    ta.style.position = 'fixed';
                    ta.style.opacity = '0';
                    document.body.appendChild(ta);
                    ta.select();
                    var ok = document.execCommand('copy');
                    document.body.removeChild(ta);
                    flash(ok);
                } catch (err) {
                    flash(false);
                }
            }
        });
    });
});
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
