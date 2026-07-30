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

    <?php if (isset($data['platform']) && is_array($data['platform'])) : ?>
        <h2><?php echo esc_html('Platform foundation'); ?></h2>
        <table class="widefat striped">
            <tbody>
                <?php if (isset($data['platform']['versions']) && is_array($data['platform']['versions'])) : ?>
                    <?php foreach ($data['platform']['versions'] as $label => $value) : ?>
                        <tr>
                            <th scope="row"><?php echo esc_html(ucwords(str_replace('_', ' ', (string) $label))); ?></th>
                            <td><?php echo esc_html((string) $value); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                <tr>
                    <th scope="row"><?php echo esc_html('Loaded modules'); ?></th>
                    <td>
                        <?php
                        $moduleIds = isset($data['platform']['module_ids']) && is_array($data['platform']['module_ids'])
                            ? $data['platform']['module_ids']
                            : array();
                        echo esc_html(implode(', ', $moduleIds));
                        ?>
                    </td>
                </tr>
            </tbody>
        </table>

        <h2><?php echo esc_html('Declared capabilities'); ?></h2>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th scope="col"><?php echo esc_html('Capability'); ?></th>
                    <th scope="col"><?php echo esc_html('State'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                $capabilities = isset($data['platform']['capabilities']) && is_array($data['platform']['capabilities'])
                    ? $data['platform']['capabilities']
                    : array();
                foreach ($capabilities as $capability) :
                    $capabilityId = isset($capability['id']) ? (string) $capability['id'] : '';
                    $enabled = isset($capability['enabled']) && $capability['enabled'] === true;
                    ?>
                    <tr>
                        <td><?php echo esc_html($capabilityId); ?></td>
                        <td><?php echo esc_html($enabled ? 'ON' : 'OFF'); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h2><?php echo esc_html('Platform safety confirmations'); ?></h2>
        <table class="widefat striped">
            <tbody>
                <?php
                $platformConfirmations = isset($data['platform']['confirmations']) && is_array($data['platform']['confirmations'])
                    ? $data['platform']['confirmations']
                    : array();
                foreach ($platformConfirmations as $label => $value) :
                    ?>
                    <tr>
                        <th scope="row"><?php echo esc_html(ucwords(str_replace('_', ' ', (string) $label))); ?></th>
                        <td><?php echo esc_html((string) $value); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
