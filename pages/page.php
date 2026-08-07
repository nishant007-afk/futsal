<?php
require_once __DIR__ . '/../config/db.php';

$pages = [
    'about' => [
        'title' => 'About GoalSpace',
        'summary' => 'GoalSpace helps players find and book futsal courts, and helps owners keep their grounds full.',
        'body' => '
            <p><strong>GoalSpace</strong> is a booking platform made for futsal. It connects players who want a court with owners who have spare hours to fill.</p>
            <p>We started GoalSpace because booking a court usually meant calling three venues and hoping someone answered. We wanted something quicker: see what\'s free, pick a slot, done.</p>

            <h2>Who it\'s for</h2>
            <ul>
                <li><strong>Players</strong> can find nearby courts, see real-time availability, and book a slot in a couple of minutes.</li>
                <li><strong>Managers</strong> can list their courts, keep the calendar full, and always know who has paid.</li>
                <li><strong>Admins</strong> keep the platform running smoothly and manage users and listings.</li>
            </ul>

            <h2>What we care about</h2>
            <ul>
                <li><strong>Bookings that stick.</strong> When a slot is confirmed, it is locked. No double-booking, no surprises.</li>
                <li><strong>Simple tools for owners.</strong> Managing bookings and payments should not be a second job.</li>
                <li><strong>Honest information.</strong> Prices, hours and availability are shown straight, so you know what you are getting.</li>
            </ul>

            <h2>Contact</h2>
            <p>We read everything that comes in, whether it is a question, some feedback, or just to say hi. Write to us at <a href="mailto:hello@goalspace.com">hello@goalspace.com</a>.</p>
        ',
    ],
    'privacy' => [
        'title' => 'Privacy Policy',
        'summary' => 'What data we collect, why we collect it, and how we keep it safe.',
        'body' => '
            <p class="updated-note">Last updated: August 2026</p>

            <h2>Information we collect</h2>
            <ul>
                <li><strong>Account details</strong>: your name, email address and phone number, provided when you sign up.</li>
                <li><strong>Booking data</strong>: the courts, dates and time slots you reserve.</li>
                <li><strong>Usage data</strong>: pages you visit and actions you take, used to improve the platform.</li>
            </ul>

            <h2>Why we use it</h2>
            <ul>
                <li>To create and manage your account.</li>
                <li>To process and manage bookings including sharing booking details with the manager of the court.</li>
                <li>To keep the platform secure and prevent misuse.</li>
                <li>To improve performance and user experience.</li>
            </ul>

            <h2>Who we share it with</h2>
            <p>We do not sell your personal data. Your details are shared only with:</p>
            <ul>
                <li>The <strong>court manager</strong> when you book one of their grounds (so they can confirm your slot).</li>
                <li>Service providers that host and operate the platform, bound by confidentiality.</li>
                <li>Authorities, only where required by law.</li>
            </ul>

            <h2>Cookies and sessions</h2>
            <p>We use session cookies to keep you logged in. You can clear these at any time in your browser; you\'ll just need to log in again.</p>

            <h2>Data security</h2>
            <p>Passwords are stored as strong, one-way hashes and are never readable by staff. Access to dashboards is limited by role so each person sees only the information they need.</p>

            <h2>Your rights</h2>
            <p>You may request a copy of your data, ask us to correct it, or ask us to delete your account and bookings. Contact <a href="mailto:hello@goalspace.com">hello@goalspace.com</a> and we will act on your request within 30 days.</p>

            <h2>Changes to this policy</h2>
            <p>If we change this policy, we will update the date above and, where practical, notify you by email.</p>
        ',
    ],
    'terms' => [
        'title' => 'Terms of Service',
        'summary' => 'The rules for using GoalSpace as a player, manager or admin.',
        'body' => '
            <p class="updated-note">Last updated: August 2026</p>
            <p>By creating an account or using GoalSpace, you agree to these terms.</p>

            <h2>Your account</h2>
            <ul>
                <li>You must provide accurate information and keep your login details secure.</li>
                <li>One account per person. You may not share accounts or credentials.</li>
                <li>You are responsible for activity that happens under your account.</li>
            </ul>

            <h2>Booking courts</h2>
            <ul>
                <li>A booking is <strong>confirmed immediately</strong> the moment you reserve a slot. No approval needed.</li>
                <li>Once booked, the slot is locked and cannot be taken by anyone else.</li>
                <li>Confirmed bookings can be cancelled from "My Bookings" at any time before the game.</li>
                <li>Managers and admins may cancel any booking on their grounds or across the platform.</li>
                <li>Misusing the booking system (fake bookings, spam or harassment) may lead to account suspension.</li>
            </ul>

            <h2 id="for-managers">For managers</h2>
            <p>Managers are court owners who list their grounds and accept bookings. By becoming a manager you agree to the following:</p>
            <ul>
                <li><strong>Your courts only.</strong> You manage only the grounds assigned to your account. You cannot edit or cancel bookings for courts you do not own.</li>
                <li><strong>Listings must be accurate.</strong> Prices, opening hours and court details should be truthful and kept up to date. Misleading listings may be removed.</li>
                <li><strong>Honour confirmed slots.</strong> Once a booking is confirmed, keep the court available for that slot, or work with the player if a change is unavoidable.</li>
                <li><strong>Respect player data.</strong> You may see players\' names and contact details only to manage their bookings. Do not use them for marketing without permission.</li>
                <li><strong>No double-selling.</strong> A slot that is booked through GoalSpace must not also be sold elsewhere.</li>
            </ul>

            <h2>Admin responsibilities</h2>
            <ul>
                <li>Admins administer the platform: users, grounds and system settings.</li>
                <li>Admins may remove content or accounts that violate these terms.</li>
            </ul>

            <h2>Acceptable use</h2>
            <ul>
                <li>Do not attempt to access other users\' data or restricted areas.</li>
                <li>Do not disrupt, overload or attempt to break the platform.</li>
                <li>Do not use the platform for any unlawful purpose.</li>
            </ul>

            <h2>Limitation of liability</h2>
            <p>GoalSpace is a booking platform; court quality, availability and gameplay are the responsibility of each court owner. We are not liable for issues arising at a court, such as cancellations or facilities.</p>

            <h2>Changes and termination</h2>
            <p>We may update these terms or suspend accounts that breach them. Continued use after a change means you accept the updated terms.</p>
        ',
    ],
    'contact' => [
        'title' => 'Contact Us',
        'summary' => 'We are happy to help players and court owners.',
        'body' => '
            <p>Need help with a booking, your account or a court? Message us below, and we respond within 1 day.</p>
        ',
    ],
    'help' => [
        'title' => 'Help & Support',
        'summary' => 'Answers to common questions for players, managers and admins.',
        'body' => '
            <h2 id="for-players">For players</h2>
            <h3>How do I book a court?</h3>
            <p>Find a ground on the homepage, pick a date, choose an available time slot and confirm. Your slot locks in immediately, then you choose a payment option.</p>
            <h3>How does payment work?</h3>
            <p>After booking you can either pay a 20% advance online and the rest at the court, or pay the full amount online in one go. Both options are shown at checkout.</p>
            <h3>Can I cancel a booking?</h3>
            <p>Yes, confirmed bookings can be cancelled from "My Bookings" at any time.</p>

            <h2 id="for-managers">For managers</h2>
            <h3>How do I become a manager?</h3>
            <p>Sign up and choose the "Manager" option, or click <a href="%MANAGER_URL%">Become a manager</a>. You will land on your manager dashboard where you can add your first court.</p>
            <h3>How do I list a court?</h3>
            <p>From "My Grounds", click "Add Ground" and enter the name, location, capacity and hourly price. Once saved, the court is visible on the site for players to book.</p>
            <h3>How do I edit or remove a court?</h3>
            <p>Go to "My Grounds", find the court and use Edit or Delete. You can also mark a court inactive so it stops accepting bookings without deleting it.</p>
            <h3>How do bookings work?</h3>
            <p>When a player reserves a slot on your court, it is confirmed instantly. No approval needed. You can cancel any booking from the bookings page if you can no longer host.</p>
            <h3>Can I track payments and revenue?</h3>
            <p>Yes. Your dashboard shows revenue from your courts, plus a payment badge (Paid, Partial or Unpaid) on every booking.</p>
            <h3>What should I do if a player cancels?</h3>
            <p>When a player cancels from "My Bookings", the slot becomes free again automatically for others to book.</p>

            <h2 id="for-admins">For admins</h2>
            <h3>How do I change a user\'s role?</h3>
            <p>Go to Users in the admin dashboard and choose a new role from the dropdown.</p>
            <h3>How do I assign a ground to a manager?</h3>
            <p>Edit the ground in the admin dashboard and select the owning manager.</p>

            <h2>Still stuck?</h2>
            <p>Contact us at <a href="mailto:hello@goalspace.com">hello@goalspace.com</a> and we will help.</p>
        ',
    ],
];

$slug = $_GET['slug'] ?? 'about';
if (!isset($pages[$slug])) {
    $slug = 'about';
}
$page = $pages[$slug];
$page_title = $page['title'];

require __DIR__ . '/../includes/header.php';
?>

<div class="content-hero">
    <span class="eyebrow">GoalSpace</span>
    <h1><?php echo e($page['title']); ?></h1>
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
            <p class="muted contact-lead">Fill in the form below and we'll get back to you as soon as we can.</p>
            <form method="post" action="<?php echo base_url('pages/contact_submit.php'); ?>" novalidate>
                <?php echo csrf_field(); ?>
                <div class="grid-2">
                    <div class="form-group<?php echo has_error($cErrors, 'name'); ?>">
                        <label for="cName">Your name <span class="req">*</span></label>
                        <div class="input-group">
                            <i class="fa-solid fa-user"></i>
                            <input type="text" id="cName" name="name" value="<?php echo e(old_value($cOld, 'name', is_logged_in() ? ($site_user['name'] ?? '') : '')); ?>" placeholder="Full name" autocomplete="name" required>
                        </div>
                        <?php field_error($cErrors, 'name'); ?>
                    </div>
                    <div class="form-group<?php echo has_error($cErrors, 'email'); ?>">
                        <label for="cEmail">Email <span class="req">*</span></label>
                        <div class="input-group">
                            <i class="fa-solid fa-envelope"></i>
                            <input type="email" id="cEmail" name="email" value="<?php echo e(old_value($cOld, 'email', is_logged_in() ? ($site_user['email'] ?? '') : '')); ?>" placeholder="you@example.com" autocomplete="email" required>
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
                        <label for="cSubject">Subject</label>
                        <div class="input-group">
                            <i class="fa-solid fa-heading"></i>
                            <input type="text" id="cSubject" name="subject" value="<?php echo e(old_value($cOld, 'subject')); ?>" placeholder="Short summary">
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