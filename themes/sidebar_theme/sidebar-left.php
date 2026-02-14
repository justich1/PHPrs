<?php
// Nový soubor pro levý postranní panel.
$widgets = get_sidebar_widgets('left');

if (!empty($widgets)):
?>
<aside id="sidebar-left" class="sidebar">
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
