<?php
$code = isset($code) ? (int)$code : 404;
$title = isset($title) ? $title : 'Page not found';
$message = isset($message) ? $message : 'The page you were looking for could not be found.';
$cta_label = isset($cta_label) ? $cta_label : 'Back to home';
$cta_url = isset($cta_url) ? (preg_match('#^(https?://|/)#', $cta_url) ? $cta_url : base_url($cta_url)) : base_url('index.php');
?>
<div class="container error-page">
    <div class="empty reveal">
        <span class="big error-icon">
            <i class="fa-solid fa-<?php echo $code === 403 ? 'lock' : 'futbol'; ?>"></i>
        </span>
        <h1><?php echo e($title); ?></h1>
        <p><?php echo e($message); ?></p>
        <a href="<?php echo e($cta_url); ?>" class="btn btn-outline btn-lg">
            <i class="fa-solid fa-angle-left"></i> <?php echo e($cta_label); ?>
        </a>
    </div>
</div>