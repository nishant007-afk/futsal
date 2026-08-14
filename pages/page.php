<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$slug = $_GET['slug'] ?? 'about';
$page = page_content($slug);
if ($page === null) {
    $slug = 'about';
    $page = page_content('about');
}
$page_title = $page['title'];

require __DIR__ . '/../includes/header.php';
?>

<div class="content-hero">
    <div class="title-back-row">
        <a href="<?php echo base_url('index.php'); ?>" class="nav-back mob-title-back" data-back aria-label="Go back"><i class="fa-solid fa-arrow-left"></i></a>
        <h1><?php echo e($page['title']); ?></h1>
    </div>
    <p><?php echo e($page['summary']); ?></p>
</div>

<div class="prose">
    <?php echo str_replace('%MANAGER_URL%', base_url('pages/register.php?role=manager'), $page['body']); ?>
</div>

<?php if ($slug === 'contact'): ?>
<?php $cErrors = form_errors(); $cOld = form_old(); ?>
<div class="contact-layout reveal">
    <div class="contact-form-col">
        <div class="detail-box">
            <h3><i class="fa-solid fa-paper-plane"></i> Send us a message</h3>
            <form method="post" action="<?php echo base_url('pages/contact_submit.php'); ?>" novalidate>
                <?php echo csrf_field(); ?>
                <div class="grid-2">
                    <div class="form-group<?php echo has_error($cErrors, 'name'); ?>">
                        <div class="input-group floating">
                            <i class="fa-solid fa-user"></i>
                            <input type="text" id="cName" name="name" value="<?php echo e(old_value($cOld, 'name', is_logged_in() ? ($site_user['name'] ?? '') : '')); ?>" placeholder=" " autocomplete="name" required>
                            <label for="cName">Your name <span class="req">*</span></label>
                        </div>
                        <?php field_error($cErrors, 'name'); ?>
                    </div>
                    <div class="form-group<?php echo has_error($cErrors, 'email'); ?>">
                        <div class="input-group floating">
                            <i class="fa-solid fa-envelope"></i>
                            <input type="email" id="cEmail" name="email" value="<?php echo e(old_value($cOld, 'email', is_logged_in() ? ($site_user['email'] ?? '') : '')); ?>" placeholder=" " autocomplete="email" required>
                            <label for="cEmail">Email <span class="req">*</span></label>
                        </div>
                        <?php field_error($cErrors, 'email'); ?>
                    </div>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label for="cTopic">Topic <span class="req">*</span></label>
                        <select id="cTopic" name="topic" required>
                            <option value="general" <?php echo old_value($cOld, 'topic') === 'general' ? 'selected' : ''; ?>>General question</option>
                            <option value="booking" <?php echo old_value($cOld, 'topic') === 'booking' ? 'selected' : ''; ?>>Booking help</option>
                            <option value="account" <?php echo old_value($cOld, 'topic') === 'account' ? 'selected' : ''; ?>>Account issue</option>
                            <option value="manager" <?php echo old_value($cOld, 'topic') === 'manager' ? 'selected' : ''; ?>>Manager / court owner</option>
                            <option value="feedback" <?php echo old_value($cOld, 'topic') === 'feedback' ? 'selected' : ''; ?>>Feedback</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <div class="input-group floating">
                            <i class="fa-solid fa-heading"></i>
                            <input type="text" id="cSubject" name="subject" value="<?php echo e(old_value($cOld, 'subject')); ?>" placeholder=" ">
                            <label for="cSubject">Subject</label>
                        </div>
                    </div>
                </div>
                <div class="form-group<?php echo has_error($cErrors, 'message'); ?>">
                    <label for="cMessage">Message <span class="req">*</span></label>
                    <textarea id="cMessage" name="message" rows="5" placeholder="Tell us how we can help (at least 10 characters)." required><?php echo e(old_value($cOld, 'message')); ?></textarea>
                    <?php field_error($cErrors, 'message'); ?>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> <?php echo is_logged_in() ? 'Send message' : 'Log in to send'; ?></button>
            </form>
        </div>
    </div>
    <div class="contact-info-col">
        <div class="detail-box">
            <h3><i class="fa-solid fa-address-book"></i> Contact us</h3>
            <div class="contact-channels">
                <div class="contact-channel">
                    <span class="contact-ico"><i class="fa-solid fa-envelope"></i></span>
                    <div>
                        <strong>Email</strong>
                        <a href="mailto:hello@goalspace.com">hello@goalspace.com</a>
                    </div>
                </div>
                <div class="contact-channel">
                    <span class="contact-ico"><i class="fa-solid fa-phone"></i></span>
                    <div>
                        <strong>Phone</strong>
                        <span>+977 9800 000 000<br>(Sun&ndash;Fri, 9:00&ndash;18:00)</span>
                    </div>
                </div>
                <div class="contact-channel">
                    <span class="contact-ico"><i class="fa-solid fa-location-dot"></i></span>
                    <div>
                        <strong>Office</strong>
                        <span>GoalSpace<br>Kathmandu, Nepal</span>
                    </div>
                </div>
                <div class="contact-channel">
                    <span class="contact-ico"><i class="fa-solid fa-user-tie"></i></span>
                    <div>
                        <strong>Manager support</strong>
                        <span>Court owners needing help can email</span>
                        <a href="mailto:managers@goalspace.com">managers@goalspace.com</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>