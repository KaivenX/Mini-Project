<?php
$footerYear = date('Y');
?>
<footer class="site-footer">
    <div class="footer-inner">
        <div class="brand" aria-label="BloodLink home">
            <span class="brand-mark" aria-hidden="true">B</span>
            <span>BloodLink</span>
        </div>
        <div>&copy; <?= htmlspecialchars((string) $footerYear, ENT_QUOTES, 'UTF-8') ?> BloodLink. Together we save lives.</div>
    </div>
</footer>
