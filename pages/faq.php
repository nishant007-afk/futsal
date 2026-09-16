<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$logged_in = is_logged_in();
$user = $logged_in ? current_user() : null;
$isManager = $user !== null && $user['role'] === 'manager';

$page_title = 'Frequently Asked Questions';
$page_description = 'Find clear answers to common questions about booking futsal courts, digital QR payments, cancellations, and venue management on GoalSpace.';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head reveal">
    <div class="title-back-row">
        <a href="<?php echo base_url('index.php'); ?>" class="page-back-arrow" data-back aria-label="Go back"><i class="fa-solid fa-arrow-left"></i></a>
        <h1 class="page-title">FAQ</h1>
    </div>
</div>

<div class="faq-search" role="search">
    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
    <input type="search" id="faqSearch" placeholder="Search questions..." autocomplete="off">
</div>

<div class="faq-cats" aria-label="Jump to a topic">
    <a href="#cat-booking" class="faq-cat active">Booking</a>
    <a href="#cat-payment" class="faq-cat">Payments</a>
    <a href="#cat-cancel" class="faq-cat">Cancellations</a>
    <a href="#cat-account" class="faq-cat">Account</a>
    <a href="#cat-owners" class="faq-cat">Managers</a>
    <a href="#cat-tech" class="faq-cat">Technical</a>
</div>

<div class="faq-list">

    <div class="faq-group">
        <h2 class="faq-group-title" id="cat-booking">Booking a Court</h2>

        <details class="faq-item">
            <summary>How do I book a futsal court on GoalSpace?</summary>
            <p>Find a venue via search or the <a href="<?php echo base_url('pages/courts.php'); ?>">courts directory</a>, choose your date and time slot, confirm the booking, and pay via QR code or at the venue. Your slot is locked instantly.</p>
        </details>

        <details class="faq-item">
            <summary>Do I need an account to make a reservation?</summary>
            <p>Yes. A free player account ensures your booking is recorded, your receipt is emailed, and you can reschedule or cancel if plans change.</p>
        </details>

        <details class="faq-item">
            <summary>Can I book recurring weekly slots?</summary>
            <p>Yes. On courts that support it, select weekly recurrence to reserve the same time across multiple weeks.</p>
        </details>

        <details class="faq-item">
            <summary>Is my booking confirmed instantly?</summary>
            <p>Yes. Every booking is confirmed immediately upon submission. No manual approval needed.</p>
        </details>
    </div>

    <div class="faq-group">
        <h2 class="faq-group-title" id="cat-payment">Payments and Pricing</h2>

        <details class="faq-item">
            <summary>What payment options are available?</summary>
            <p>Three options: 20% advance via QR with balance at venue, 100% online via QR, or pay at the venue counter directly.</p>
        </details>

        <details class="faq-item">
            <summary>How does QR code payment work?</summary>
            <p>During checkout, the court owner's verified QR code is displayed. Scan it with your digital wallet (Khalti, eSewa, IME Pay, or mobile banking) and approve the amount. Your booking status updates automatically.</p>
        </details>

        <details class="faq-item">
            <summary>Are digital payments secure?</summary>
            <p>Yes. QR payments happen inside your bank or wallet app. GoalSpace never stores your payment credentials or PINs.</p>
        </details>

        <details class="faq-item">
            <summary>Where can I find my receipt?</summary>
            <p>A receipt is emailed upon confirmation. You can also view it anytime from <a href="<?php echo base_url('pages/my_bookings.php'); ?>">My Bookings</a>.</p>
        </details>
    </div>

    <div class="faq-group">
        <h2 class="faq-group-title" id="cat-cancel">Cancellations and Refunds</h2>

        <details class="faq-item">
            <summary>What is the cancellation policy?</summary>
            <p><strong>24+ hours before:</strong> Full refund. <strong>Within 24 hours:</strong> Advance retained as credit. <strong>No-show:</strong> Advance forfeited.</p>
        </details>

        <details class="faq-item">
            <summary>How do I cancel or reschedule?</summary>
            <p>Go to <a href="<?php echo base_url('pages/my_bookings.php'); ?>">My Bookings</a>, find the reservation, and click Cancel or Reschedule. You'll see the refund breakdown before confirming.</p>
        </details>

        <details class="faq-item">
            <summary>What if bad weather makes the court unplayable?</summary>
            <p>The manager can cancel the slot. You'll receive a 100% refund or credit to reschedule.</p>
        </details>
    </div>

    <div class="faq-group">
        <h2 class="faq-group-title" id="cat-account">Account and Security</h2>

        <details class="faq-item">
            <summary>How do I register?</summary>
            <p>Click <a href="<?php echo base_url('pages/register.php'); ?>">Sign Up</a>, fill in your details, select Player role. You can also sign in with Google.</p>
        </details>

        <details class="faq-item">
            <summary>What if I forget my password?</summary>
            <p>Click <a href="<?php echo base_url('pages/forgot_password.php'); ?>">Forgot password?</a> on the login page, enter your email, and we'll send a one-time code.</p>
        </details>

        <details class="faq-item">
            <summary>Can I change my phone or email?</summary>
            <p>Yes. Open your <a href="<?php echo base_url('pages/profile.php'); ?>">Profile</a> and go to Settings to update your details.</p>
        </details>
    </div>

    <div class="faq-group">
        <h2 class="faq-group-title" id="cat-owners">For Court Owners</h2>

        <details class="faq-item">
            <summary>How do I list my court?</summary>
            <p>Register as a <a href="<?php echo base_url('pages/register.php?role=manager'); ?>">Manager</a>, go to My Grounds, and click Add Ground to set specs, rates, photos, and location.</p>
        </details>

        <details class="faq-item">
            <summary>How do I receive QR payments?</summary>
            <p>In My Grounds, edit your court and upload your merchant QR code. Players scan it during checkout.</p>
        </details>

        <details class="faq-item">
            <summary>How do I record cash payments?</summary>
            <p>In your Bookings screen, find the reservation and click Mark Paid.</p>
        </details>

        <details class="faq-item">
            <summary>Can I block slots for private events?</summary>
            <p>Yes. In the court edit page, use Blocked Dates to close hours, full days, or custom ranges.</p>
        </details>

        <details class="faq-item">
            <summary>How do promo codes work?</summary>
            <p>In your manager portal, open Promos to create percentage or fixed discounts for filling quiet hours.</p>
        </details>
    </div>

    <div class="faq-group">
        <h2 class="faq-group-title" id="cat-tech">Technical Help</h2>

        <details class="faq-item">
            <summary>How does location sorting work?</summary>
            <p>When enabled, your browser calculates distances to nearby courts. Your location is never stored on our servers.</p>
        </details>

        <details class="faq-item">
            <summary>Can I install GoalSpace as an app?</summary>
            <p>Yes. On Android Chrome, tap menu and select "Install app". On iPhone Safari, tap Share and "Add to Home Screen".</p>
        </details>

        <details class="faq-item">
            <summary>How do I contact support?</summary>
            <p>Email <a href="mailto:hello@goalspace.com">hello@goalspace.com</a> or use our <a href="<?php echo base_url('pages/page.php?slug=contact'); ?>">contact form</a>. We respond within 4 hours on business days.</p>
        </details>
    </div>
</div>

<div class="faq-empty" id="faqEmpty" hidden>
    <p>No results for "<span id="faqEmptyTerm"></span>". Try different keywords.</p>
</div>

<?php if ($logged_in && $isManager): ?>
<div class="notice faq-help">
    <i class="fa-solid fa-circle-info"></i>
    <span>Managing a court? See the <a href="<?php echo base_url('pages/page.php?slug=help#for-managers'); ?>">manager help section</a>.</span>
</div>
<?php endif; ?>

<script>
(function () {
    var input = document.getElementById('faqSearch');
    var empty = document.getElementById('faqEmpty');
    var termEl = document.getElementById('faqEmptyTerm');
    if (!input) return;
    var items = document.querySelectorAll('.faq-item');
    var cats = document.querySelectorAll('.faq-cat');
    var filter = function () {
        var term = (input.value || '').trim().toLowerCase();
        var shown = 0;
        items.forEach(function (item) {
            var summary = item.querySelector('summary');
            var text = summary ? summary.textContent.toLowerCase() : '';
            var match = text.indexOf(term) !== -1;
            item.style.display = match ? '' : 'none';
            if (match) { shown++; if (term.length > 0) item.open = true; }
        });
        document.querySelectorAll('.faq-group').forEach(function (g) {
            var vis = g.querySelectorAll('.faq-item:not([style*="display: none"])');
            g.style.display = vis.length === 0 ? 'none' : '';
        });
        if (empty && termEl) {
            empty.hidden = shown !== 0;
            termEl.textContent = input.value.trim();
        }
    };
    input.addEventListener('input', filter);
    cats.forEach(function (c) {
        c.addEventListener('click', function (e) {
            cats.forEach(function (x) { x.classList.remove('active'); });
            c.classList.add('active');
        });
    });
    items.forEach(function (item) {
        item.addEventListener('toggle', function () {
            item.setAttribute('aria-expanded', item.open ? 'true' : 'false');
        });
    });
})();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
