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

<div class="faq-hero reveal">
    <div class="title-back-row">
        <a href="<?php echo base_url('index.php'); ?>" class="page-back-arrow" data-back aria-label="Go back"><i class="fa-solid fa-arrow-left"></i></a>
        <h1 class="faq-hero-title">How can we help?</h1>
    </div>
    <p class="faq-hero-sub">Find answers about booking, payments, cancellations, and more.</p>
</div>

<div class="faq-search" role="search">
    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
    <input type="search" id="faqSearch" placeholder="Search questions..." autocomplete="off">
</div>

<div class="faq-layout" id="faqLayout">
    <aside class="faq-side">
        <nav class="faq-cats" aria-label="FAQ topics">
            <a href="#faqLayout" class="faq-cat active" data-cat="all" aria-current="true"><i class="fa-solid fa-border-all"></i> All topics</a>
            <a href="#cat-booking" class="faq-cat" data-cat="cat-booking"><i class="fa-solid fa-calendar-check"></i> Booking</a>
            <a href="#cat-payment" class="faq-cat" data-cat="cat-payment"><i class="fa-solid fa-credit-card"></i> Payments</a>
            <a href="#cat-cancel" class="faq-cat" data-cat="cat-cancel"><i class="fa-solid fa-circle-xmark"></i> Cancellations</a>
            <a href="#cat-account" class="faq-cat" data-cat="cat-account"><i class="fa-solid fa-shield-halved"></i> Account</a>
            <a href="#cat-owners" class="faq-cat" data-cat="cat-owners"><i class="fa-solid fa-store"></i> Managers</a>
            <a href="#cat-tech" class="faq-cat" data-cat="cat-tech"><i class="fa-solid fa-gear"></i> Technical</a>
        </nav>

        <div class="faq-support">
            <h2>Still have questions?</h2>
            <p>Our support team replies within 4 hours on business days.</p>
            <a class="btn btn-primary btn-sm" href="<?php echo base_url('pages/page.php?slug=contact'); ?>">Submit a Ticket</a>
            <a class="btn btn-outline btn-sm" href="tel:+9779800000000"><i class="fa-solid fa-phone"></i> Call Support</a>
        </div>
    </aside>

    <div class="faq-main">
        <div class="faq-list">
            <div class="faq-group" id="cat-booking">
                <div class="faq-group-head">
                    <span class="faq-group-icon"><i class="fa-solid fa-calendar-check"></i></span>
                    <h2 class="faq-group-title">Booking a Court</h2>
                </div>
                <div class="faq-group-body">
                    <details class="faq-item">
                        <summary>How do I book a futsal court on GoalSpace?</summary>
                        <p>Find a venue via search or the <a href="<?php echo base_url('pages/courts.php'); ?>">courts directory</a>, choose your date and time slot, confirm the booking, and pay via QR code or at the venue. Your slot is locked instantly.</p>
                    </details>
                    <details class="faq-item">
                        <summary>Do I need an account to make a reservation?</summary>
                        <p>Yes. A free account records your booking and emails your receipt.</p>
                    </details>
                    <details class="faq-item">
                        <summary>Can I book recurring weekly slots?</summary>
                        <p>Yes. On courts that support it, select weekly recurrence.</p>
                    </details>
                    <details class="faq-item">
                        <summary>Is my booking confirmed instantly?</summary>
                        <p>Yes. Every booking is confirmed immediately upon submission. No manual approval needed.</p>
                    </details>
                </div>
            </div>

            <div class="faq-group" id="cat-payment">
                <div class="faq-group-head">
                    <span class="faq-group-icon"><i class="fa-solid fa-credit-card"></i></span>
                    <h2 class="faq-group-title">Payments and Pricing</h2>
                </div>
                <div class="faq-group-body">
                    <details class="faq-item">
                        <summary>What payment options are available?</summary>
                        <p>20% advance via QR, 100% via QR, or pay at the counter.</p>
                    </details>
                    <details class="faq-item">
                        <summary>How does QR code payment work?</summary>
                        <p>Scan the venue's QR with Khalti, eSewa, IME Pay, or mobile banking. Status updates automatically.</p>
                    </details>
                    <details class="faq-item">
                        <summary>Are digital payments secure?</summary>
                        <p>Yes. QR payments happen in your bank or wallet app. We never store your credentials.</p>
                    </details>
                    <details class="faq-item">
                        <summary>Where can I find my receipt?</summary>
                        <p>A receipt is emailed upon confirmation. You can also view it anytime from <a href="<?php echo base_url('pages/my_bookings.php'); ?>">My Bookings</a>.</p>
                    </details>
                </div>
            </div>

            <div class="faq-group" id="cat-cancel">
                <div class="faq-group-head">
                    <span class="faq-group-icon"><i class="fa-solid fa-circle-xmark"></i></span>
                    <h2 class="faq-group-title">Cancellations and Refunds</h2>
                </div>
                <div class="faq-group-body">
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
            </div>

            <div class="faq-group" id="cat-account">
                <div class="faq-group-head">
                    <span class="faq-group-icon"><i class="fa-solid fa-shield-halved"></i></span>
                    <h2 class="faq-group-title">Account and Security</h2>
                </div>
                <div class="faq-group-body">
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
            </div>

            <div class="faq-group" id="cat-owners">
                <div class="faq-group-head">
                    <span class="faq-group-icon"><i class="fa-solid fa-store"></i></span>
                    <h2 class="faq-group-title">For Court Owners</h2>
                </div>
                <div class="faq-group-body">
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
                        <p>Open Promos to create percentage or fixed discounts.</p>
                    </details>
                </div>
            </div>

            <div class="faq-group" id="cat-tech">
                <div class="faq-group-head">
                    <span class="faq-group-icon"><i class="fa-solid fa-gear"></i></span>
                    <h2 class="faq-group-title">Technical Help</h2>
                </div>
                <div class="faq-group-body">
                    <details class="faq-item">
                        <summary>How do I contact support?</summary>
                        <p>Email <a href="mailto:hello@goalspace.com">hello@goalspace.com</a> or use our <a href="<?php echo base_url('pages/page.php?slug=contact'); ?>">contact form</a>. We respond within 4 hours on business days.</p>
                    </details>
                </div>
            </div>
        </div>

        <div class="faq-empty" id="faqEmpty" hidden>
            <div class="faq-empty-icon"><i class="fa-regular fa-face-meh"></i></div>
            <p class="faq-empty-title">No results found</p>
            <p class="faq-empty-sub">Try different keywords or browse categories above.</p>
        </div>

        <?php if ($logged_in && $isManager): ?>
        <div class="notice faq-help">
            <i class="fa-solid fa-circle-info"></i>
            <span>Managing a court? See the <a href="<?php echo base_url('pages/page.php?slug=help#for-managers'); ?>">manager help section</a>.</span>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="faq-cta reveal">
    <div class="faq-cta-copy">
        <h2>Still need help?</h2>
        <p>WhatsApp or phone, whichever is easier for you.</p>
    </div>
    <div class="faq-cta-actions">
        <a class="btn btn-outline" href="https://wa.me/9779800000000" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i> Chat on WhatsApp</a>
        <a class="btn btn-primary" href="tel:+9779800000000"><i class="fa-solid fa-phone"></i> Call Support</a>
    </div>
</div>

<script>
(function () {
    var input = document.getElementById('faqSearch');
    var empty = document.getElementById('faqEmpty');
    var items = document.querySelectorAll('.faq-item');
    var groups = document.querySelectorAll('.faq-group');
    var cats = document.querySelectorAll('.faq-cat');
    var activeCat = 'all';
    var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    var filter = function () {
        var term = ((input && input.value) || '').trim().toLowerCase();
        var shown = 0;
        groups.forEach(function (g) {
            var inCat = activeCat === 'all' || g.id === activeCat;
            var visible = 0;
            g.querySelectorAll('.faq-item').forEach(function (item) {
                var text = (item.textContent || '').toLowerCase();
                var match = inCat && text.indexOf(term) !== -1;
                item.style.display = match ? '' : 'none';
                if (match) {
                    visible++;
                    shown++;
                    if (term.length > 0) item.open = true;
                } else {
                    item.open = false;
                }
            });
            g.style.display = (inCat && visible > 0) ? '' : 'none';
        });
        if (empty) {
            empty.hidden = shown !== 0;
        }
    };

    if (input) input.addEventListener('input', filter);

    cats.forEach(function (c) {
        c.addEventListener('click', function (e) {
            e.preventDefault();
            activeCat = c.getAttribute('data-cat') || 'all';
            cats.forEach(function (x) { x.classList.remove('active'); x.removeAttribute('aria-current'); });
            c.classList.add('active');
            c.setAttribute('aria-current', 'true');
            filter();
            var target = document.getElementById(activeCat === 'all' ? 'faqLayout' : activeCat);
            if (target && target.offsetParent !== null) {
                target.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'start' });
            }
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
