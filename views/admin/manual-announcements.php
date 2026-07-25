<?php

defined('ABSPATH') || exit;
?>
<div class="wrap">
    <h1><?php echo esc_html($data['title']); ?></h1>

    <p class="description">
        <?php echo esc_html(
            'Paste a strict JSON array of public official announcements for an existing manual-only source. '
            . 'Preview performs zero writes. Confirm Import performs a separate, explicit insert-only submission.'
        ); ?>
    </p>

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

    <?php if ($data['manual_sources'] === array()) : ?>
        <p><?php echo esc_html('No manual-only sources are available. Create a manual-only source first.'); ?></p>
    <?php else : ?>
        <h2><?php echo esc_html('Preview JSON'); ?></h2>
        <form method="post" action="<?php echo esc_url($data['page_url']); ?>">
            <input type="hidden" name="smce_manual_preview" value="1">
            <?php wp_nonce_field($data['preview_nonce_action'], $data['preview_nonce_field']); ?>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="smce-manual-source-id"><?php echo esc_html('Source'); ?></label></th>
                    <td>
                        <select name="source_id" id="smce-manual-source-id" required>
                            <option value=""><?php echo esc_html('Select a manual-only source'); ?></option>
                            <?php foreach ($data['manual_sources'] as $source) : ?>
                                <option value="<?php echo esc_attr((string) $source['id']); ?>"
                                    <?php selected($data['submitted_source_id'], $source['id']); ?>>
                                    <?php echo esc_html($source['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="smce-manual-json"><?php echo esc_html('JSON Array'); ?></label></th>
                    <td>
                        <textarea name="smce_manual_json" id="smce-manual-json" class="large-text code" rows="12"
                            required><?php echo esc_textarea($data['submitted_raw_json']); ?></textarea>
                        <p class="description">
                            <?php echo esc_html(
                                'A JSON array of objects. Required keys: title, url, date (YYYY-MM-DD). '
                                . 'Optional key: category. Maximum 25 records, maximum 100 KB.'
                            ); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <button type="submit" class="button button-primary"><?php echo esc_html('Preview'); ?></button>
            </p>
        </form>
    <?php endif; ?>

    <?php if ($data['preview_result'] !== null) : ?>
        <?php $preview = $data['preview_result']; ?>
        <hr>
        <h2><?php echo esc_html('Preview Result'); ?></h2>

        <?php if ($preview['top_level_error'] !== '') : ?>
            <div class="notice notice-error inline">
                <p><?php echo esc_html($preview['top_level_error']); ?></p>
            </div>
        <?php else : ?>
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col"><?php echo esc_html('#'); ?></th>
                        <th scope="col"><?php echo esc_html('Status'); ?></th>
                        <th scope="col"><?php echo esc_html('Date'); ?></th>
                        <th scope="col"><?php echo esc_html('Category'); ?></th>
                        <th scope="col"><?php echo esc_html('Title'); ?></th>
                        <th scope="col"><?php echo esc_html('Canonical URL'); ?></th>
                        <th scope="col"><?php echo esc_html('Message'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($preview['rows'] as $row) : ?>
                        <tr>
                            <td><?php echo esc_html((string) $row['index']); ?></td>
                            <td><?php echo esc_html($row['status_label']); ?></td>
                            <td><?php echo esc_html($row['date']); ?></td>
                            <td><?php echo esc_html($row['category'] !== '' ? $row['category'] : '—'); ?></td>
                            <td><?php echo esc_html($row['title']); ?></td>
                            <td>
                                <?php if ($row['canonical_url'] !== '') : ?>
                                    <a href="<?php echo esc_url($row['canonical_url']); ?>" target="_blank" rel="noopener noreferrer">
                                        <?php echo esc_html($row['canonical_url']); ?>
                                    </a>
                                <?php else : ?>
                                    <?php echo esc_html('—'); ?>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($row['message']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($preview['show_confirm']) : ?>
                <h3><?php echo esc_html('Confirm Import'); ?></h3>
                <p class="description">
                    <?php echo esc_html(
                        'All records passed validation. Confirming re-validates everything from scratch and '
                        . 'inserts only new items; duplicates are skipped automatically.'
                    ); ?>
                </p>
                <form method="post" action="<?php echo esc_url($data['confirm_action_url']); ?>">
                    <input type="hidden" name="action" value="smce_source_item_confirm">
                    <input type="hidden" name="source_id" value="<?php echo esc_attr((string) $preview['source_id']); ?>">
                    <textarea name="smce_manual_json" hidden><?php echo esc_textarea($data['submitted_raw_json']); ?></textarea>
                    <?php wp_nonce_field($data['confirm_nonce_action'], $data['confirm_nonce_field']); ?>
                    <p class="submit">
                        <button type="submit" class="button button-primary"><?php echo esc_html('Confirm Import'); ?></button>
                    </p>
                </form>
            <?php else : ?>
                <p>
                    <?php echo esc_html(
                        'One or more records are invalid. Fix the JSON above and preview again before a Confirm '
                        . 'Import form is offered.'
                    ); ?>
                </p>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>
