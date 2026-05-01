<?php
/** @var string $title */
/** @var string $subtitle */
?>
<div class="card">
    <h2 style="margin:0 0 8px 0;"><?php echo htmlspecialchars((string)($title ?? '功能占位页')); ?></h2>
    <div class="muted"><?php echo htmlspecialchars((string)($subtitle ?? '')); ?></div>
</div>
