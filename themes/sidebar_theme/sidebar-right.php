<?php
// Nový soubor pro pravý postranní panel.
$widgets = get_sidebar_widgets('right');

if (!empty($widgets)):
?>
<aside id="sidebar-right" class="sidebar">
    <?php foreach ($widgets as $widget): ?>
        <div class="widget">
            <?php if (!empty($widget['title'])): ?>
                <h3 class="widget-title"><?= htmlspecialchars($widget['title']) ?></h3>
            <?php endif; ?>
            <div class="widget-content">
                <?= process_shortcodes($widget['content']) ?>
            </div>
        </div>
    <?php endforeach; ?>
</aside>
<?php endif; ?>
