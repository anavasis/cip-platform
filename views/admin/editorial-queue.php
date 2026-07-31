<?php

defined('ABSPATH') || exit;
?>
<div class="wrap smce-editorial-queue">
    <h1><?php echo esc_html($data['title']); ?></h1>

    <p>
        <a href="<?php echo esc_url($data['workspace_url']); ?>">
            <?php echo esc_html('← Editorial Workspace'); ?>
        </a>
        |
        <a href="<?php echo esc_url($data['announcements_url']); ?>">
            <?php echo esc_html('Announcements'); ?>
        </a>
    </p>

    <?php foreach ($data['error_messages'] as $errorMessage) : ?>
        <div class="notice notice-error">
            <p><?php echo esc_html($errorMessage); ?></p>
        </div>
    <?php endforeach; ?>

    <style>
        .smce-editorial-queue .smce-status-new {
            display: inline-block;
            padding: 2px 8px;
            background: #edfaef;
            color: #1e4620;
            border: 1px solid #68de7c;
        }
        .smce-editorial-queue .smce-status-updated {
            display: inline-block;
            padding: 2px 8px;
            background: #f0f6fc;
            color: #1d2327;
            border: 1px solid #72aee6;
        }
        .smce-editorial-queue .smce-queue-tabs {
            margin: 12px 0 16px;
        }
    </style>

    <div class="smce-queue-tabs">
        <a class="button <?php echo $data['active_status'] === 'new' ? 'button-primary' : ''; ?>"
            href="<?php echo esc_url($data['new_url']); ?>">
            <?php echo esc_html('NEW'); ?>
        </a>
        <a class="button <?php echo $data['active_status'] === 'updated' ? 'button-primary' : ''; ?>"
            href="<?php echo esc_url($data['updated_url']); ?>">
            <?php echo esc_html('UPDATED'); ?>
        </a>
    </div>

    <p>
        <?php echo esc_html('Spine Ready: '); ?>
        <strong><?php echo esc_html($data['spine_ready']); ?></strong>
    </p>

    <div class="notice notice-info inline">
        <p>
            <?php echo esc_html(
                'UNCHANGED and DUPLICATE are ephemeral lifecycle outcomes from Editorial Spine Phase 1. '
                . 'They are not durable row states and cannot power a persistent queue. '
                . 'This queue shows durable NEW (revision_no = 1) and UPDATED (revision_no > 1) announcements only.'
            ); ?>
        </p>
    </div>

    <?php if (is_array($data['last_batch'])) : ?>
        <div class="notice notice-warning inline">
            <p>
                <?php
                $batch = $data['last_batch'];
                echo esc_html(
                    'Ephemeral last-batch panel: unchanged='
                    . (isset($batch['unchanged_count']) ? (string) $batch['unchanged_count'] : '0')
                    . ', duplicate='
                    . (isset($batch['duplicate_count']) ? (string) $batch['duplicate_count'] : '0')
                    . ' (available only for the current process after a lifecycle apply).'
                );
                ?>
            </p>
        </div>
    <?php else : ?>
        <div class="notice notice-warning inline">
            <p>
                <?php echo esc_html(
                    'No ephemeral UNCHANGED/DUPLICATE batch is available on this request.'
                ); ?>
            </p>
        </div>
    <?php endif; ?>

    <h2>
        <?php echo esc_html($data['active_status'] === 'updated' ? 'UPDATED queue' : 'NEW queue'); ?>
    </h2>

    <table class="widefat striped">
        <thead>
            <tr>
                <th scope="col"><?php echo esc_html('Source'); ?></th>
                <th scope="col"><?php echo esc_html('Title'); ?></th>
                <th scope="col"><?php echo esc_html('Status'); ?></th>
                <th scope="col"><?php echo esc_html('Revision'); ?></th>
                <th scope="col"><?php echo esc_html('Updated'); ?></th>
                <th scope="col"><?php echo esc_html('Actions'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ($data['items'] === array()) : ?>
                <tr>
                    <td colspan="<?php echo esc_attr('6'); ?>">
                        <?php echo esc_html('No announcements in this queue.'); ?>
                    </td>
                </tr>
            <?php else : ?>
                <?php foreach ($data['items'] as $item) : ?>
                    <tr>
                        <td><?php echo esc_html(
                            $item['source_name'] . ' (#' . (string) $item['source_id'] . ')'
                        ); ?></td>
                        <td><?php echo esc_html($item['title']); ?></td>
                        <td>
                            <?php if ($item['status'] === 'NEW') : ?>
                                <span class="smce-status-new"><?php echo esc_html('NEW'); ?></span>
                            <?php else : ?>
                                <span class="smce-status-updated"><?php echo esc_html('UPDATED'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($item['revision_no']); ?></td>
                        <td><?php echo esc_html($item['updated_at_utc']); ?></td>
                        <td>
                            <a href="<?php echo esc_url($item['details_url']); ?>">
                                <?php echo esc_html('View'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <p>
        <?php if ($data['has_previous']) : ?>
            <a class="button" href="<?php echo esc_url($data['previous_url']); ?>">
                <?php echo esc_html('Previous'); ?>
            </a>
        <?php endif; ?>

        <span><?php echo esc_html('Page ' . (string) $data['page']); ?></span>

        <?php if ($data['has_next']) : ?>
            <a class="button" href="<?php echo esc_url($data['next_url']); ?>">
                <?php echo esc_html('Next'); ?>
            </a>
        <?php endif; ?>
    </p>
</div>
