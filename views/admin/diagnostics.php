<?php

defined('ABSPATH') || exit;
?>
<div class="wrap">
    <h1><?php echo esc_html($data['title']); ?></h1>

    <h2><?php echo esc_html('Environment'); ?></h2>
    <table class="widefat striped">
        <tbody>
            <?php foreach ($data['environment'] as $label => $value) : ?>
                <tr>
                    <th scope="row"><?php echo esc_html($label); ?></th>
                    <td><?php echo esc_html($value); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

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

    <h2><?php echo esc_html('Safety confirmations'); ?></h2>
    <table class="widefat striped">
        <tbody>
            <?php foreach ($data['confirmations'] as $label => $value) : ?>
                <tr>
                    <th scope="row"><?php echo esc_html($label); ?></th>
                    <td><?php echo esc_html($value); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
