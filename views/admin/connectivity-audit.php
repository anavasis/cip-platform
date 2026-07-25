<?php

defined('ABSPATH') || exit;
?>
<div class="wrap">
    <h1><?php echo esc_html($data['title']); ?></h1>

    <div class="notice notice-warning inline">
        <p>
            <?php echo esc_html(
                'This audit performs explicit live network requests only for the sources selected below.'
            ); ?>
        </p>
        <p>
            <?php echo esc_html(
                'Results exist only in the current request and are not stored. No source is changed or activated.'
            ); ?>
        </p>
        <p>
            <?php echo esc_html(
                'At most ' . (int) $data['maximum_sources'] . ' sources may be selected per audit.'
            ); ?>
        </p>
    </div>

    <?php if ($data['request_error_message'] !== '') : ?>
        <div class="notice notice-error inline">
            <p><?php echo esc_html($data['request_error_message']); ?></p>
        </div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_attr($data['page_url']); ?>">
        <input type="hidden" name="smce_connectivity_audit" value="1">
        <?php wp_nonce_field($data['nonce_action'], $data['nonce_field']); ?>

        <?php if ($data['sources'] === array()) : ?>
            <p><?php echo esc_html('No sources are available.'); ?></p>
        <?php else : ?>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th scope="col"><?php echo esc_html('Choose'); ?></th>
                        <th scope="col"><?php echo esc_html('Source ID'); ?></th>
                        <th scope="col"><?php echo esc_html('Name'); ?></th>
                        <th scope="col"><?php echo esc_html('Source Type'); ?></th>
                        <th scope="col"><?php echo esc_html('Stored Hostname'); ?></th>
                        <th scope="col"><?php echo esc_html('Status'); ?></th>
                        <th scope="col"><?php echo esc_html('Manual Only'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['sources'] as $source) : ?>
                        <tr>
                            <td>
                                <label>
                                    <input
                                        type="checkbox"
                                        name="source_ids[]"
                                        value="<?php echo esc_attr((string) $source['id']); ?>"
                                        <?php echo $source['selected'] ? 'checked="checked"' : ''; ?>
                                    >
                                    <?php echo esc_html('Audit'); ?>
                                </label>
                            </td>
                            <td><?php echo esc_html((string) $source['id']); ?></td>
                            <td>
                                <?php echo esc_html($source['name'] !== '' ? $source['name'] : '—'); ?>
                            </td>
                            <td>
                                <?php echo esc_html(
                                    $source['source_type'] !== '' ? $source['source_type'] : '—'
                                ); ?>
                            </td>
                            <td>
                                <?php echo esc_html($source['host'] !== '' ? $source['host'] : '—'); ?>
                            </td>
                            <td><?php echo esc_html($source['status']); ?></td>
                            <td><?php echo esc_html($source['manual_only']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <p class="submit">
            <button type="submit" class="button button-primary">
                <?php echo esc_html('Run Audit'); ?>
            </button>
        </p>
    </form>

    <?php if ($data['results'] !== null) : ?>
        <hr>
        <h2><?php echo esc_html('Current Request Results'); ?></h2>

        <?php if ($data['results'] === array()) : ?>
            <p><?php echo esc_html('No audit results are available.'); ?></p>
        <?php else : ?>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th scope="col"><?php echo esc_html('ID'); ?></th>
                        <th scope="col"><?php echo esc_html('Source Name'); ?></th>
                        <th scope="col"><?php echo esc_html('Hostname'); ?></th>
                        <th scope="col"><?php echo esc_html('Result'); ?></th>
                        <th scope="col"><?php echo esc_html('HTTP Status'); ?></th>
                        <th scope="col"><?php echo esc_html('Content Type'); ?></th>
                        <th scope="col"><?php echo esc_html('Bytes'); ?></th>
                        <th scope="col"><?php echo esc_html('Elapsed (ms)'); ?></th>
                        <th scope="col"><?php echo esc_html('Redirects'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['results'] as $result) : ?>
                        <tr>
                            <td><?php echo esc_html((string) $result['source_id']); ?></td>
                            <td>
                                <?php echo esc_html($result['name'] !== '' ? $result['name'] : '—'); ?>
                            </td>
                            <td>
                                <?php echo esc_html($result['host'] !== '' ? $result['host'] : '—'); ?>
                            </td>
                            <td>
                                <?php echo esc_html($result['result_label']); ?>
                                <?php if (!empty($result['truncated'])) : ?>
                                    <span class="description">
                                        <?php echo esc_html(
                                            'Truncated response — connectivity confirmed from a bounded prefix, not the full remote document.'
                                        ); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo esc_html(
                                    $result['http_status'] > 0
                                        ? (string) $result['http_status']
                                        : '—'
                                ); ?>
                            </td>
                            <td>
                                <?php echo esc_html(
                                    $result['content_type'] !== ''
                                        ? $result['content_type']
                                        : '—'
                                ); ?>
                            </td>
                            <td><?php echo esc_html((string) $result['response_bytes']); ?></td>
                            <td><?php echo esc_html((string) $result['elapsed_ms']); ?></td>
                            <td><?php echo esc_html((string) $result['redirect_count']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php endif; ?>
</div>
