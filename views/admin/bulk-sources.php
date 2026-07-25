<?php

defined('ABSPATH') || exit;
?>
<div class="wrap">
    <h1><?php echo esc_html($data['title']); ?></h1>

    <p class="description">
        <?php echo esc_html(
            'Paste a strict JSON array of source catalog records. Preview performs zero writes. '
            . 'Confirm creates only new sources. Every created source is forced disabled and manual-only. '
            . 'This flow does not fetch, schedule, or connect any source.'
        ); ?>
    </p>

    <div class="notice notice-warning inline">
        <p>
            <?php echo esc_html(
                'Security default: all newly created sources are stored disabled (enabled = 0) and '
                . 'manual-only (manual_only = 1). Existing sources are never changed.'
            ); ?>
        </p>
    </div>

    <?php if ($data['notice_message'] !== '') : ?>
        <div class="notice notice-info is-dismissible">
            <p><?php echo esc_html($data['notice_message']); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($data['preview_error_message'] !== '') : ?>
        <div class="notice notice-error">
            <p><?php echo esc_html($data['preview_error_message']); ?></p>
        </div>
    <?php endif; ?>

    <h2><?php echo esc_html('Preview JSON'); ?></h2>
    <p class="description">
        <?php echo esc_html(
            'Required keys: slug, name, source_type, feed_url, allowed_domains. '
            . 'Optional keys: base_url, parser_profile. Maximum 80 records, maximum 100 KB. JSON only.'
        ); ?>
    </p>
    <pre><?php echo esc_html($data['json_example']); ?></pre>

    <form method="post" action="<?php echo esc_url($data['page_url']); ?>">
        <input type="hidden" name="smce_bulk_sources_preview" value="1">
        <?php wp_nonce_field($data['preview_nonce_action'], $data['preview_nonce_field']); ?>

        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">
                    <label for="smce-bulk-json"><?php echo esc_html('JSON Array'); ?></label>
                </th>
                <td>
                    <textarea name="smce_bulk_json" id="smce-bulk-json" class="large-text code" rows="14"
                        required><?php echo esc_textarea($data['submitted_raw_json']); ?></textarea>
                </td>
            </tr>
        </table>

        <p class="submit">
            <button type="submit" class="button button-primary"><?php echo esc_html('Preview'); ?></button>
        </p>
    </form>

    <?php if ($data['preview_result'] !== null) : ?>
        <?php $preview = $data['preview_result']; ?>
        <hr>
        <h2><?php echo esc_html('Preview Result'); ?></h2>

        <?php if ($preview['top_level_error'] !== '') : ?>
            <div class="notice notice-error inline">
                <p><?php echo esc_html($preview['top_level_error']); ?></p>
            </div>
        <?php else : ?>
            <?php if ($preview['summary'] !== '') : ?>
                <p><?php echo esc_html($preview['summary']); ?></p>
            <?php endif; ?>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th scope="col"><?php echo esc_html('#'); ?></th>
                        <th scope="col"><?php echo esc_html('Status'); ?></th>
                        <th scope="col"><?php echo esc_html('Slug'); ?></th>
                        <th scope="col"><?php echo esc_html('Name'); ?></th>
                        <th scope="col"><?php echo esc_html('Source Type'); ?></th>
                        <th scope="col"><?php echo esc_html('Feed URL'); ?></th>
                        <th scope="col"><?php echo esc_html('Allowed Domains'); ?></th>
                        <th scope="col"><?php echo esc_html('Parser Profile'); ?></th>
                        <th scope="col"><?php echo esc_html('Message'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($preview['rows'] as $row) : ?>
                        <tr>
                            <td><?php echo esc_html((string) $row['index']); ?></td>
                            <td><?php echo esc_html($row['status_label']); ?></td>
                            <td><?php echo esc_html($row['slug'] !== '' ? $row['slug'] : '—'); ?></td>
                            <td><?php echo esc_html($row['name'] !== '' ? $row['name'] : '—'); ?></td>
                            <td><?php echo esc_html($row['source_type'] !== '' ? $row['source_type'] : '—'); ?></td>
                            <td>
                                <?php if ($row['feed_url'] !== '') : ?>
                                    <a href="<?php echo esc_url($row['feed_url']); ?>" target="_blank" rel="noopener noreferrer">
                                        <?php echo esc_html($row['feed_url']); ?>
                                    </a>
                                <?php else : ?>
                                    <?php echo esc_html('—'); ?>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($row['allowed_domains'] !== '' ? $row['allowed_domains'] : '—'); ?></td>
                            <td><?php echo esc_html($row['parser_profile'] !== '' ? $row['parser_profile'] : '—'); ?></td>
                            <td><?php echo esc_html($row['message']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($preview['show_confirm']) : ?>
                <h3><?php echo esc_html('Confirm Create'); ?></h3>
                <p class="description">
                    <?php echo esc_html(
                        'All records passed validation. Confirming re-validates the original JSON from scratch '
                        . 'and creates only ready sources as disabled and manual-only. Duplicates are skipped.'
                    ); ?>
                </p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="smce_source_catalog_confirm">
                    <textarea name="smce_bulk_json" hidden><?php echo esc_textarea($data['submitted_raw_json']); ?></textarea>
                    <?php wp_nonce_field($data['confirm_nonce_action'], $data['confirm_nonce_field']); ?>
                    <p class="submit">
                        <button type="submit" class="button button-primary"><?php echo esc_html('Confirm Create'); ?></button>
                    </p>
                </form>
            <?php else : ?>
                <p>
                    <?php echo esc_html(
                        'Confirm is unavailable. Fix invalid records, or note that a duplicate-only batch '
                        . 'has nothing new to create.'
                    ); ?>
                </p>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>
