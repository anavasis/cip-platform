<?php

defined('ABSPATH') || exit;
?>
<div class="wrap smce-editorial-workspace">
    <h1><?php echo esc_html($data['title']); ?></h1>

    <?php foreach ($data['error_messages'] as $errorMessage) : ?>
        <div class="notice notice-error">
            <p><?php echo esc_html($errorMessage); ?></p>
        </div>
    <?php endforeach; ?>

    <style>
        .smce-editorial-workspace .smce-editorial-cards {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin: 16px 0;
        }
        .smce-editorial-workspace .smce-editorial-card {
            min-width: 160px;
            padding: 12px 16px;
            background: #fff;
            border: 1px solid #c3c4c7;
        }
        .smce-editorial-workspace .smce-editorial-card strong {
            display: block;
            font-size: 1.4em;
            margin-top: 4px;
        }
        .smce-editorial-workspace .smce-status-new {
            display: inline-block;
            padding: 2px 8px;
            background: #edfaef;
            color: #1e4620;
            border: 1px solid #68de7c;
        }
        .smce-editorial-workspace .smce-status-updated {
            display: inline-block;
            padding: 2px 8px;
            background: #f0f6fc;
            color: #1d2327;
            border: 1px solid #72aee6;
        }
    </style>

    <div class="smce-editorial-cards">
        <div class="smce-editorial-card">
            <?php echo esc_html('Total announcements'); ?>
            <strong><?php echo esc_html((string) $data['total']); ?></strong>
        </div>
        <div class="smce-editorial-card">
            <?php echo esc_html('New'); ?>
            <strong>
                <span class="smce-status-new"><?php echo esc_html((string) $data['new_count']); ?></span>
            </strong>
        </div>
        <div class="smce-editorial-card">
            <?php echo esc_html('Updated'); ?>
            <strong>
                <span class="smce-status-updated"><?php echo esc_html((string) $data['updated_count']); ?></span>
            </strong>
        </div>
        <div class="smce-editorial-card">
            <?php echo esc_html('Last ingestion'); ?>
            <strong><?php echo esc_html($data['last_ingestion_at_utc']); ?></strong>
        </div>
        <div class="smce-editorial-card">
            <?php echo esc_html('Spine Ready'); ?>
            <strong><?php echo esc_html($data['spine_ready']); ?></strong>
        </div>
    </div>

    <p>
        <a class="button button-primary" href="<?php echo esc_url($data['announcements_url']); ?>">
            <?php echo esc_html('Open Announcements'); ?>
        </a>
        <a class="button" href="<?php echo esc_url($data['queue_url']); ?>">
            <?php echo esc_html('Open Editorial Queue'); ?>
        </a>
        <a class="button" href="<?php echo esc_url($data['queue_new_url']); ?>">
            <?php echo esc_html('Queue: New'); ?>
        </a>
        <a class="button" href="<?php echo esc_url($data['queue_updated_url']); ?>">
            <?php echo esc_html('Queue: Updated'); ?>
        </a>
    </p>

    <div class="notice notice-info inline">
        <p>
            <?php echo esc_html(
                'Status labels NEW and UPDATED are durable proxies from revision_no '
                . '(NEW = revision 1, UPDATED = revision greater than 1). '
                . 'UNCHANGED and DUPLICATE remain ephemeral lifecycle outcomes and are not stored on rows.'
            ); ?>
        </p>
    </div>

    <?php if (is_array($data['last_batch'])) : ?>
        <h2><?php echo esc_html('Last lifecycle batch (ephemeral)'); ?></h2>
        <table class="widefat striped">
            <tbody>
                <tr>
                    <th scope="row"><?php echo esc_html('Candidates'); ?></th>
                    <td><?php echo esc_html(
                        isset($data['last_batch']['candidates'])
                            ? (string) $data['last_batch']['candidates']
                            : '—'
                    ); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html('New'); ?></th>
                    <td><?php echo esc_html(
                        isset($data['last_batch']['new_count'])
                            ? (string) $data['last_batch']['new_count']
                            : '—'
                    ); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html('Updated'); ?></th>
                    <td><?php echo esc_html(
                        isset($data['last_batch']['updated_count'])
                            ? (string) $data['last_batch']['updated_count']
                            : '—'
                    ); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html('Unchanged'); ?></th>
                    <td><?php echo esc_html(
                        isset($data['last_batch']['unchanged_count'])
                            ? (string) $data['last_batch']['unchanged_count']
                            : '—'
                    ); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html('Duplicate'); ?></th>
                    <td><?php echo esc_html(
                        isset($data['last_batch']['duplicate_count'])
                            ? (string) $data['last_batch']['duplicate_count']
                            : '—'
                    ); ?></td>
                </tr>
            </tbody>
        </table>
    <?php else : ?>
        <div class="notice notice-warning inline">
            <p>
                <?php echo esc_html(
                    'No in-memory lifecycle batch is available on this request. '
                    . 'UNCHANGED and DUPLICATE counts appear only for the current process after a lifecycle apply.'
                ); ?>
            </p>
        </div>
    <?php endif; ?>
</div>
