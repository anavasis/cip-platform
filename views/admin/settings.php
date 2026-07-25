<?php

defined('ABSPATH') || exit;
?>
<div class="wrap">
    <h1><?php echo esc_html($data['title']); ?></h1>
    <ul>
        <?php foreach ($data['statements'] as $statement) : ?>
            <li><?php echo esc_html($statement); ?></li>
        <?php endforeach; ?>
    </ul>
</div>
