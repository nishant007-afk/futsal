<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$slug = $_GET['slug'] ?? 'about';
$page = page_content($slug);
if ($page === null) {
    http_response_code(404);
    require __DIR__ . '/../includes/header.php';
    echo '<div class="error-page" style="text-align:center;padding:60px 0;">';
    echo '<div class="error-icon" style="font-size:48px;margin-bottom:12px;"><i class="fa-solid fa-map-location-dot"></i></div>';
    echo '<h1 style="font-size:28px;margin:0 0 8px;">Page not found</h1>';
    echo '<p style="color:var(--ink-2);margin:0 0 20px;">That page doesn\'t exist. Try About or Contact instead.</p>';
    echo '<a class="btn btn-primary" href="' . e(base_url('pages/page.php?slug=about')) . '">Go to About</a> ';
    echo '<a class="btn btn-outline" href="' . e(base_url('index.php')) . '">Home</a>';
    echo '</div>';
    require __DIR__ . '/../includes/footer.php';
    exit;
}
$page_title = $page['title'];

/* The %MANAGER_URL% placeholder has to be substituted BEFORE sanitising. sanitize_page_body()
   rejects any href that is not http(s)/mailto/tel/#/root-relative, so leaving the literal
   "%MANAGER_URL%" in place made the Help page's "Become a Manager" link render as href="#". */
$bodyHtml = sanitize_page_body(str_replace('%MANAGER_URL%', base_url('pages/register.php?role=manager'), $page['body']));

/* KEPT (removed per request): the "Last updated: September 2026" line was the first
   thing in the body, so it read as body copy. It is now pulled out of the markup and
   rendered as a chip beside the page heading. */
$updatedLabel = '';
if (preg_match('/<p class="updated-note">\s*(.*?)\s*<\/p>/is', $bodyHtml, $m)) {
    $updatedLabel = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES, 'UTF-8'));
    $bodyHtml = str_replace($m[0], '', $bodyHtml);
}

/* Legal pages: give every <h2> a stable anchor id.
   KEPT (removed per request): this used to also collect the headings into a $toc array
   for the sticky "On this page" rail. The rail is gone, so the array went with it, but
   the ids stay - the footer deep-links to #for-managers, and section-N ids mean a
   shared link to any clause still lands in the right place. Headings that already
   carry an id keep it. */
$isLegal = in_array($slug, ['terms', 'privacy'], true);
if ($isLegal) {
    $headingIndex = 0;
    $bodyHtml = preg_replace_callback('/<h2([^>]*)>(.*?)<\/h2>/is', function ($m) use (&$headingIndex) {
        $headingIndex++;
        $attrs = $m[1];
        if (!preg_match('/\bid\s*=\s*"[^"]+"/i', $attrs)) {
            $attrs .= ' id="section-' . $headingIndex . '"';
        }
        return '<h2' . $attrs . '>' . $m[2] . '</h2>';
    }, $bodyHtml);
}

/* About: the opening copy moves out of the prose column into a hero beside the metrics. */
$isAbout = ($slug === 'about');
$heroCopy = '';
if ($isAbout && preg_match('/<div class="about-hero-copy">(.*?)<\/div>/is', $bodyHtml, $m)) {
    $heroCopy = trim($m[1]);
    $bodyHtml = str_replace($m[0], '', $bodyHtml);
}

/* The court count is read live so the hero metric cannot drift from the database. */
$aboutMetrics = [];
if ($isAbout) {
    $courtCount = 0;
    try {
        $r = $conn->query('SELECT COUNT(*) AS c FROM grounds');
        if ($r) { $row = $r->fetch_assoc(); $courtCount = (int)($row['c'] ?? 0); }
    } catch (Throwable $e) { $courtCount = 0; }
    $aboutMetrics = [
        ['icon' => 'fa-solid fa-futbol',   'value' => $courtCount . '+', 'label' => 'Courts listed'],
        ['icon' => 'fa-solid fa-bolt',     'value' => 'Instant',        'label' => 'Real-time confirmation'],
        ['icon' => 'fa-solid fa-tag',      'value' => '0%',             'label' => 'Hidden booking fees'],
    ];
}

require __DIR__ . '/../includes/header.php';
?>

<div class="content-hero<?php echo $isLegal ? ' content-hero--narrow' : ''; ?>">
    <div class="title-back-row">
        <a href="<?php echo base_url('index.php'); ?>" class="page-back-arrow" data-back aria-label="Go back"><i class="fa-solid fa-arrow-left"></i></a>
        <h1><?php echo e($page['title']); ?></h1>
        <?php if ($updatedLabel !== ''): ?>
            <span class="updated-chip"><i class="fa-regular fa-clock" aria-hidden="true"></i> <?php echo e($updatedLabel); ?></span>
        <?php endif; ?>
    </div>
    <p><?php echo e($page['summary']); ?></p>
</div>

<?php if ($isAbout && $heroCopy !== ''): ?>
    <section class="about-hero reveal">
        <div class="about-hero-copy">
            <?php echo $heroCopy; ?>
        </div>
        <ul class="about-metrics">
            <?php foreach ($aboutMetrics as $metric): ?>
                <li class="about-metric">
                    <span class="about-metric-ico"><i class="<?php echo e($metric['icon']); ?>" aria-hidden="true"></i></span>
                    <strong><?php echo e($metric['value']); ?></strong>
                    <span><?php echo e($metric['label']); ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

<?php /* KEPT (removed per request): the sticky "On this page" contents rail that sat
       beside the legal text. Removing the rail does not mean giving up the reading
       measure - .legal-prose keeps its 820px cap and is now simply centred in the
       container, so the line length stays comfortable without a second column.
       The heading ids are deliberately kept: the footer deep-links to
       #for-managers, and shared links to a section still land correctly. */ ?>
<?php if ($isLegal): ?>
    <div class="prose legal-prose">
        <?php echo $bodyHtml; ?>
    </div>
<?php else: ?>
    <div class="prose">
        <?php echo $bodyHtml; ?>
    </div>
<?php endif; ?>

<?php if ($slug === 'contact'): ?>
<?php $cErrors = form_errors(); $cOld = form_old(); ?>
<div class="contact-layout reveal">
    <div class="contact-form-col">
        <div class="detail-box">
            <h3>Send us a message</h3>
            <form method="post" action="<?php echo base_url('pages/contact_submit.php'); ?>" novalidate>
                <?php echo csrf_field(); ?>
                <?php honeypot_field(); ?>
                <div class="grid-2">
                    <div class="form-group<?php echo has_error($cErrors, 'name'); ?>">
                        <div class="input-group floating">
                            <input type="text" id="cName" name="name" value="<?php echo e(old_value($cOld, 'name', is_logged_in() ? ($site_user['name'] ?? '') : '')); ?>" placeholder=" " autocomplete="name" maxlength="100" required aria-required="true">
                            <label for="cName">Your name <span class="req">*</span></label>
                        </div>
                        <?php field_error($cErrors, 'name'); ?>
                    </div>
                    <div class="form-group<?php echo has_error($cErrors, 'email'); ?>">
                        <div class="input-group floating">
                            <input type="email" id="cEmail" name="email" value="<?php echo e(old_value($cOld, 'email', is_logged_in() ? ($site_user['email'] ?? '') : '')); ?>" placeholder=" " autocomplete="email" required aria-required="true">
                            <label for="cEmail">Email <span class="req">*</span></label>
                        </div>
                        <?php field_error($cErrors, 'email'); ?>
                    </div>
                </div>
                <?php /* KEPT (removed per request): Subject used a floating label while the Topic
                        select beside it used a static one, so the two boxes rendered at different
                        heights with different inner padding. Both are static labels now, and
                        .contact-form-col .form-row-2 select / input[type="text"] share one height
                        and one padding block. */ ?>
                <div class="grid-2 form-row-2">
                    <div class="form-group">
                        <label for="cTopic" class="form-label-static">Topic <span class="req">*</span></label>
                        <select id="cTopic" name="topic" required aria-required="true">
                            <option value="general" <?php echo old_value($cOld, 'topic') === 'general' ? 'selected' : ''; ?>>General question</option>
                            <option value="booking" <?php echo old_value($cOld, 'topic') === 'booking' ? 'selected' : ''; ?>>Booking help</option>
                            <option value="account" <?php echo old_value($cOld, 'topic') === 'account' ? 'selected' : ''; ?>>Account issue</option>
                            <option value="manager" <?php echo old_value($cOld, 'topic') === 'manager' ? 'selected' : ''; ?>>Manager / court owner</option>
                            <option value="feedback" <?php echo old_value($cOld, 'topic') === 'feedback' ? 'selected' : ''; ?>>Feedback</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="cSubject" class="form-label-static">Subject</label>
                        <input type="text" id="cSubject" name="subject" value="<?php echo e(old_value($cOld, 'subject')); ?>" maxlength="200" autocomplete="off">
                    </div>
                </div>
                <div class="form-group<?php echo has_error($cErrors, 'message'); ?>">
                    <div class="field-label-row">
                        <label for="cMessage" class="form-label-static">Message <span class="req">*</span></label>
                        <?php /* Live feedback for the "at least 10 characters" rule. */ ?>
                        <span class="char-count" id="cMessageCount" data-min="10" aria-live="polite">10 characters minimum</span>
                    </div>
                    <textarea id="cMessage" name="message" rows="5" placeholder="Tell us how we can help (at least 10 characters)." required aria-required="true" aria-describedby="cMessageCount"><?php echo e(old_value($cOld, 'message')); ?></textarea>
                    <?php field_error($cErrors, 'message'); ?>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> <?php echo is_logged_in() ? 'Send message' : 'Log in to send'; ?></button>
            </form>
        </div>
    </div>
    <div class="contact-info-col">
        <div class="detail-box">
            <h3>Contact us</h3>
            <div class="contact-channels">
                <div class="contact-channel">
                    <span class="contact-ico"><i class="fa-solid fa-envelope"></i></span>
                    <div>
                        <strong>Email</strong>
                        <a href="mailto:hello@goalspace.com">hello@goalspace.com</a>
                        <em>Usually answered within 4 hours on business days</em>
                    </div>
                </div>
                <div class="contact-channel">
                    <span class="contact-ico"><i class="fa-solid fa-phone"></i></span>
                    <div>
                        <strong>Phone</strong>
                        <?php /* KEPT (removed per request): the number was inert text. */ ?>
                        <a href="tel:+9779800000000">+977 9800 000 000</a>
                        <em>Sun&ndash;Fri, 9:00&ndash;18:00 NPT</em>
                    </div>
                </div>
                <div class="contact-channel">
                    <span class="contact-ico"><i class="fa-solid fa-location-dot"></i></span>
                    <div>
                        <strong>Office</strong>
                        <span>GoalSpace Technologies<br>Kathmandu, Nepal</span>
                        <?php /* partners@goalspace.com used to live only in the duplicated
                               Support Channels list that was removed from the page copy. */ ?>
                        <a class="channel-link" href="https://www.google.com/maps/search/?api=1&amp;query=GoalSpace+Technologies+Kathmandu+Nepal" target="_blank" rel="noopener noreferrer">Get Directions <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></a>
                    </div>
                </div>
                <div class="contact-channel">
                    <span class="contact-ico"><i class="fa-solid fa-user-tie"></i></span>
                    <div>
                        <strong>Manager support</strong>
                        <em>Court owners needing help can email</em>
                        <a href="mailto:managers@goalspace.com">managers@goalspace.com</a>
                        <a href="mailto:partners@goalspace.com">partners@goalspace.com</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
/* Live character count for the contact message. */
(function () {
    var field = document.getElementById('cMessage');
    var out = document.getElementById('cMessageCount');
    if (!field || !out) return;
    var min = parseInt(out.getAttribute('data-min'), 10) || 10;
    var max = field.getAttribute('maxlength');
    function render() {
        var n = field.value.length;
        out.textContent = max
            ? n + ' / ' + max + ' characters'
            : n + ' characters';
        out.classList.toggle('is-ok', n >= min);
        if (n > 0 && n < min) {
            out.textContent = (min - n) + ' more character' + (min - n === 1 ? '' : 's') + ' needed';
        }
    }
    field.addEventListener('input', render);
    render();
})();
</script>
<?php /* end contact-only block */ endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>