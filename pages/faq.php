<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$logged_in = is_logged_in();
$user = $logged_in ? current_user() : null;
$isManager = $user !== null && $user['role'] === 'manager';

$page_title = 'Frequently Asked Questions';
$page_description = 'Clear answers about court booking, QR payments, cancellations, and venue management on GoalSpace.';

$faqCategories = [
    'cat-booking' => [
        'title' => 'Booking & Reservations',
        'items' => [
            [
                'q' => 'How do I book a futsal court on GoalSpace?',
                'a' => 'Browse open venues in the <a href="' . base_url('pages/courts.php') . '">courts directory</a>, pick your court, choose an available date and time slot, and confirm. Your booking is locked instantly.'
            ],
            [
                'q' => 'How long is a time slot held during checkout?',
                'a' => 'When you select an open slot, GoalSpace holds it for 10 minutes so you can review match details and complete payment without anyone else booking over you.'
            ],
            [
                'q' => 'Do I need an account to make a reservation?',
                'a' => 'Yes. A free player account tracks your booking records, generates downloadable receipts, and lets you manage or cancel reservations directly.'
            ],
            [
                'q' => 'Can I book recurring weekly slots for my team?',
                'a' => 'Yes. For courts that support recurrence, select the weekly repeat option during checkout to automatically reserve the same day and time.'
            ],
            [
                'q' => 'How do we check in at the futsal venue?',
                'a' => 'Arrive 10 minutes before kickoff and show your booking confirmation or receipt from <a href="' . base_url('pages/my_bookings.php') . '">My Bookings</a> at the venue counter.'
            ],
        ]
    ],
    'cat-payment' => [
        'title' => 'Payments & Receipts',
        'items' => [
            [
                'q' => 'What payment options are available?',
                'a' => 'You can pay a 20% advance via QR and settle the rest on arrival, pay 100% upfront via QR, or pay the full fee in cash directly at the counter.'
            ],
            [
                'q' => 'Which digital wallets and bank apps work for QR payment?',
                'a' => 'Any service supporting Nepal QR / Fonepay works—including eSewa, Khalti, IME Pay, and all major Nepali mobile banking apps scanning the court\'s QR code.'
            ],
            [
                'q' => 'How does QR verification work?',
                'a' => 'Scan the venue\'s QR code during checkout and enter your transaction ID or upload a screenshot. The venue manager verifies it in their bookings dashboard.'
            ],
            [
                'q' => 'Where can I find and download my booking receipt?',
                'a' => 'Receipts are available immediately in <a href="' . base_url('pages/my_bookings.php') . '">My Bookings</a>. You can view, print, or download a digital copy anytime.'
            ],
        ]
    ],
    'cat-cancel' => [
        'title' => 'Cancellations & Rescheduling',
        'items' => [
            [
                'q' => 'What is the cancellation and refund policy?',
                'a' => '<strong>24+ hours before kickoff:</strong> Full 100% refund. <strong>Within 24 hours:</strong> Advance payment is retained as credit for future games. <strong>No-shows:</strong> Advance is forfeited.'
            ],
            [
                'q' => 'How do I cancel or reschedule an existing reservation?',
                'a' => 'Open <a href="' . base_url('pages/my_bookings.php') . '">My Bookings</a>, locate your upcoming match, and click <strong>Cancel</strong> or <strong>Reschedule</strong>. You will see the policy breakdown before confirming.'
            ],
            [
                'q' => 'What happens if rain or weather makes an outdoor court unplayable?',
                'a' => 'The venue manager cancels the affected slots, and you will receive a 100% refund or game credit to pick another time.'
            ],
        ]
    ],
    'cat-account' => [
        'title' => 'Account & Security',
        'items' => [
            [
                'q' => 'How do I register or sign in?',
                'a' => 'Click <a href="' . base_url('pages/register.php') . '">Sign Up</a> to create an account with your email and phone, or log in instantly using your Google account.'
            ],
            [
                'q' => 'What should I do if I forget my password?',
                'a' => 'Click <a href="' . base_url('pages/forgot_password.php') . '">Forgot password?</a> on the login page. Enter your registered email, and we\'ll send a secure one-time verification code to reset it.'
            ],
            [
                'q' => 'Can I change my registered phone number or email address?',
                'a' => 'Yes. Navigate to your <a href="' . base_url('pages/profile.php') . '">Profile Settings</a> to update contact information and security credentials.'
            ],
        ]
    ],
    'cat-managers' => [
        'title' => 'For Venue Managers',
        'items' => [
            [
                'q' => 'How do I list my futsal court on GoalSpace?',
                'a' => 'Register as a <a href="' . base_url('pages/register.php?role=manager') . '">Manager</a>. From your manager dashboard, go to My Grounds and click Add Ground to set hourly rates, surface specs, photos, and location.'
            ],
            [
                'q' => 'How do I set up my payment QR code?',
                'a' => 'In My Grounds, click Edit on your venue and upload your merchant QR image (Fonepay / eSewa / Khalti). Players scan this QR code directly during checkout.'
            ],
            [
                'q' => 'How do I record cash payments collected at the arena?',
                'a' => 'In your Manager Bookings table, find the reservation and click <strong>Mark Paid</strong>. The status immediately updates to full settlement.'
            ],
            [
                'q' => 'Can I block dates for private tournaments or maintenance?',
                'a' => 'Yes. Under Ground Settings, use Blocked Dates to close specific hours, whole days, or custom maintenance windows from public view.'
            ],
            [
                'q' => 'How do promo codes work?',
                'a' => 'Open Promos in your manager area to create percentage or flat rupee discount codes with customizable expiry dates and usage limits.'
            ],
            [
                'q' => 'How does the manager monthly subscription and billing work?',
                'a' => 'Venue partners pay a flat monthly subscription charge (plus a one-time setup fee) to list courts, accept online reservations, and use management tools. GoalSpace charges 0% commission on your court bookings. You can review invoices and submit renewal confirmations directly from <a href="' . base_url('manager/subscription.php') . '">Subscription & Billing</a>.'
            ],
        ]
    ],
    'cat-subscription' => [
        'title' => 'Venue Subscription & Billing',
        'items' => [
            [
                'q' => 'What are the platform fees for futsal venue managers?',
                'a' => 'Venue managers pay a one-time onboarding setup fee and a fixed monthly subscription charge. GoalSpace never takes a percentage commission on your match bookings.'
            ],
            [
                'q' => 'How do I pay and renew my monthly subscription?',
                'a' => 'Transfer your fee via eSewa, Khalti, or Bank Transfer to GoalSpace\'s official platform accounts shown in your <a href="' . base_url('manager/subscription.php') . '">Subscription & Billing</a> dashboard, then click "Confirm Renewal". Admin verifies and extends your billing cycle within 2 hours.'
            ],
            [
                'q' => 'What happens if my subscription is overdue?',
                'a' => 'If a subscription expires, your court listings are temporarily paused from player search and checkout until renewed. Once payment is recorded, your courts immediately reactivate with all schedule and photo data intact.'
            ],
        ]
    ],
    'cat-tech' => [
        'title' => 'Technical & Support',
        'items' => [
            [
                'q' => 'How do I contact customer support?',
                'a' => 'Email <a href="mailto:hello@goalspace.com">hello@goalspace.com</a> or submit an inquiry through our <a href="' . base_url('pages/page.php?slug=contact') . '">contact form</a>. Our team responds within regular business hours.'
            ],
            [
                'q' => 'What browsers and devices are supported?',
                'a' => 'GoalSpace works on all modern desktop and mobile browsers, including Chrome, Safari, Firefox, and Edge with JavaScript enabled.'
            ],
        ]
    ],
];

$totalFaqCount = 0;
foreach ($faqCategories as $cat) {
    $totalFaqCount += count($cat['items']);
}

require __DIR__ . '/../includes/header.php';
?>

<div class="page-head reveal">
    <div class="title-back-row">
        <a href="<?php echo base_url('index.php'); ?>" class="page-back-arrow" data-back aria-label="Go back"><i class="fa-solid fa-arrow-left"></i></a>
        <h1 class="page-title">Frequently Asked Questions</h1>
    </div>
</div>

<div class="faq-toolbar reveal">
    <div class="faq-search" role="search">
        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
        <input type="search" id="faqSearch" placeholder="Search questions or keywords..." autocomplete="off" aria-label="Search questions">
        <button type="button" class="faq-search-clear" id="faqSearchClear" aria-label="Clear search" hidden><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="faq-actions">
        <span class="faq-count-badge" id="faqCount"><?php echo $totalFaqCount; ?> questions</span>
        <button type="button" class="faq-toggle-all" id="faqToggleAll" aria-expanded="false"><i class="fa-solid fa-arrows-up-down"></i> <span id="faqToggleAllText">Expand all</span></button>
    </div>
</div>

<div class="faq-sections" id="faqSections">
    <?php foreach ($faqCategories as $catId => $cat): ?>
        <section class="faq-section" id="<?php echo e($catId); ?>">
            <h2 class="faq-section-title"><?php echo e($cat['title']); ?></h2>
            <div class="faq-accordion">
                <?php foreach ($cat['items'] as $item): ?>
                    <details class="faq-item">
                        <summary><?php echo e($item['q']); ?></summary>
                        <div class="faq-answer"><?php echo $item['a']; ?></div>
                    </details>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>
</div>

<div class="faq-empty" id="faqEmpty" hidden>
    <div class="faq-empty-icon"><i class="fa-regular fa-face-meh"></i></div>
    <p class="faq-empty-title">No questions found</p>
    <p class="faq-empty-sub">Try searching with different terms or select "All" to browse all categories.</p>
    <button type="button" class="btn btn-outline btn-sm" id="faqResetSearchBtn" style="margin-top:10px;">Clear search</button>
</div>

<?php if ($logged_in && $isManager): ?>
<div class="notice faq-help" style="margin-top:24px;">
    <i class="fa-solid fa-circle-info"></i>
    <span>Managing a court? See the <a href="<?php echo base_url('pages/page.php?slug=help#for-managers'); ?>">manager documentation</a> for ground setup and booking operations.</span>
</div>
<?php endif; ?>

<div class="faq-footer-help">
    <div class="faq-footer-help-inner">
        <div>
            <h3>Can't find what you're looking for?</h3>
            <p>Our Kathmandu team is available to assist with bookings, payments, and venue inquiries.</p>
        </div>
        <div class="faq-footer-actions">
            <a class="btn btn-outline btn-sm" href="https://wa.me/9779800000000" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i> WhatsApp</a>
            <a class="btn btn-outline btn-sm" href="tel:+9779800000000"><i class="fa-solid fa-phone"></i> Call us</a>
            <a class="btn btn-primary btn-sm" href="<?php echo base_url('pages/page.php?slug=contact'); ?>"><i class="fa-solid fa-envelope"></i> Contact form</a>
        </div>
    </div>
</div>

<script type="application/ld+json">
<?php
$schemaQuestions = [];
foreach ($faqCategories as $cat) {
    foreach ($cat['items'] as $item) {
        $schemaQuestions[] = [
            '@type' => 'Question',
            'name' => $item['q'],
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => strip_tags($item['a']),
            ],
        ];
    }
}
echo json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'FAQPage',
    'mainEntity' => $schemaQuestions,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>
</script>

<script>
(function () {
    var searchInput = document.getElementById('faqSearch');
    var searchClear = document.getElementById('faqSearchClear');
    var resetBtn = document.getElementById('faqResetSearchBtn');
    var emptyBox = document.getElementById('faqEmpty');
    var countBox = document.getElementById('faqCount');
    var toggleAllBtn = document.getElementById('faqToggleAll');
    var toggleAllText = document.getElementById('faqToggleAllText');
    var sections = document.querySelectorAll('.faq-section');
    var items = document.querySelectorAll('.faq-item');
    var totalQuestions = items.length;
    var isAllExpanded = false;

    function applyFilter() {
        var query = (searchInput ? searchInput.value : '').trim().toLowerCase();
        var isSearching = query.length > 0;
        var totalShown = 0;

        if (searchClear) {
            searchClear.hidden = !isSearching;
        }

        sections.forEach(function (section) {
            var visibleInSection = 0;

            section.querySelectorAll('.faq-item').forEach(function (item) {
                var summary = item.querySelector('summary');
                var answer = item.querySelector('.faq-answer');
                var text = ((summary ? summary.textContent : '') + ' ' + (answer ? answer.textContent : '')).toLowerCase();
                var matches = !isSearching || (text.indexOf(query) !== -1);

                if (matches) {
                    item.style.display = '';
                    visibleInSection++;
                    totalShown++;
                } else {
                    item.style.display = 'none';
                }
            });

            section.style.display = (visibleInSection > 0) ? '' : 'none';
        });

        if (emptyBox) {
            emptyBox.hidden = (totalShown > 0);
        }

        if (countBox) {
            if (isSearching) {
                countBox.textContent = totalShown === 1 ? '1 question found' : totalShown + ' questions found';
            } else {
                countBox.textContent = totalQuestions + ' questions';
            }
        }

        syncToggleAllState();
    }

    var searchTimer;
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(applyFilter, 80);
        });

        searchInput.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                searchInput.value = '';
                applyFilter();
            }
        });
    }

    if (searchClear) {
        searchClear.addEventListener('click', function () {
            if (searchInput) {
                searchInput.value = '';
                searchInput.focus();
            }
            applyFilter();
        });
    }

    if (resetBtn) {
        resetBtn.addEventListener('click', function () {
            if (searchInput) {
                searchInput.value = '';
                searchInput.focus();
            }
            applyFilter();
        });
    }

    function syncToggleAllState() {
        if (!toggleAllBtn || !toggleAllText) return;
        var visibleItems = Array.prototype.filter.call(items, function (item) {
            return item.style.display !== 'none';
        });
        if (visibleItems.length === 0) return;
        var allOpen = visibleItems.every(function (item) { return item.open; });
        isAllExpanded = allOpen;
        toggleAllText.textContent = isAllExpanded ? 'Collapse all' : 'Expand all';
        toggleAllBtn.setAttribute('aria-expanded', isAllExpanded ? 'true' : 'false');
    }

    if (toggleAllBtn) {
        toggleAllBtn.addEventListener('click', function () {
            isAllExpanded = !isAllExpanded;
            items.forEach(function (item) {
                if (item.style.display !== 'none') {
                    item.open = isAllExpanded;
                }
            });
            syncToggleAllState();
        });
    }

    items.forEach(function (item) {
        item.addEventListener('toggle', function () {
            item.setAttribute('aria-expanded', item.open ? 'true' : 'false');
            syncToggleAllState();
        });
    });

    // Auto-open and scroll when landing with a hash anchor (e.g. #cat-subscription or #cat-payment)
    if (window.location.hash) {
        var targetSec = document.querySelector(window.location.hash);
        if (targetSec) {
            targetSec.querySelectorAll('.faq-item').forEach(function (el) {
                el.open = true;
            });
            setTimeout(function () {
                targetSec.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 100);
        }
    }

})();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
