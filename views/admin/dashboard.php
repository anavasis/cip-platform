<?php

defined('ABSPATH') || exit;
?>
<div class="wrap">
    <h1><?php echo esc_html($data['plugin_name']); ?></h1>
    <p>
        <?php echo esc_html('Version ' . $data['version']); ?>
    </p>
    <h2><?php echo esc_html($data['phase']); ?></h2>
    <p><?php echo esc_html($data['inactive_statement']); ?></p>

    <h2><?php echo esc_html('Feature flags'); ?></h2>
    <table class="widefat striped">
        <thead>
            <tr>
                <th scope="col"><?php echo esc_html('Feature'); ?></th>
                <th scope="col"><?php echo esc_html('State'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($data['flags'] as $flag) : ?>
                <tr>
                    <td><?php echo esc_html($flag['name']); ?></td>
                    <td><?php echo esc_html($flag['state']); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
