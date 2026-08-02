<?php

defined('ABSPATH') || exit;
?>
<div class="wrap">
    <h1><?php echo esc_html($data['title']); ?></h1>

    <p>
        <a href="<?php echo esc_url($data['workspace_url']); ?>">
            <?php echo esc_html('← Editorial Workspace'); ?>
        </a>
    </p>

    <?php foreach ($data['error_messages'] as $errorMessage) : ?>
        <div class="notice notice-error">
            <p><?php echo esc_html($errorMessage); ?></p>
        </div>
    <?php endforeach; ?>

    <?php if (isset($data['success_messages']) && is_array($data['success_messages'])) : ?>
        <?php foreach ($data['success_messages'] as $successMessage) : ?>
            <div class="notice notice-success">
                <p><?php echo esc_html($successMessage); ?></p>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if ($data['mode'] === 'detail') : ?>
        <p>
            <a href="<?php echo esc_url($data['back_url']); ?>">
                <?php echo esc_html('← Back to Announcements'); ?>
            </a>
        </p>

        <?php if (is_array($data['detail_item'])) : ?>
            <?php $item = $data['detail_item']; ?>
            <h2><?php echo esc_html($item['raw_title']); ?></h2>

            <style>
                .smce-status-new {
                    display: inline-block;
                    padding: 2px 8px;
                    background: #edfaef;
                    color: #1e4620;
                    border: 1px solid #68de7c;
                }
                .smce-status-updated {
                    display: inline-block;
                    padding: 2px 8px;
                    background: #f0f6fc;
                    color: #1d2327;
                    border: 1px solid #72aee6;
                }
            </style>

            <table class="widefat striped">
                <tbody>
                    <tr>
                        <th scope="row"><?php echo esc_html('Title'); ?></th>
                        <td><?php echo esc_html($item['raw_title']); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html('Source'); ?></th>
                        <td><?php echo esc_html(
                            $item['source_name'] . ' (#' . $item['source_id'] . ')'
                        ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html('Status'); ?></th>
                        <td>
                            <?php if ($item['status'] === 'NEW') : ?>
                                <span class="smce-status-new"><?php echo esc_html('NEW'); ?></span>
                            <?php else : ?>
                                <span class="smce-status-updated"><?php echo esc_html('UPDATED'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html('Revision'); ?></th>
                        <td><?php echo esc_html($item['revision_no']); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html('Identity Hash'); ?></th>
                        <td><code><?php echo esc_html($item['identity_hash']); ?></code></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html('Content Hash'); ?></th>
                        <td><code><?php echo esc_html($item['content_hash']); ?></code></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html('First Seen (UTC)'); ?></th>
                        <td><?php echo esc_html($item['first_seen_at_utc']); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html('Last Seen (UTC)'); ?></th>
                        <td><?php echo esc_html($item['last_seen_at_utc']); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html('Updated (UTC)'); ?></th>
                        <td><?php echo esc_html($item['updated_at_utc']); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html('Raw Payload Bytes'); ?></th>
                        <td><?php echo esc_html((string) $item['raw_payload_bytes']); ?></td>
                    </tr>
                </tbody>
            </table>

            <h2><?php echo esc_html('Raw Payload'); ?></h2>
            <?php if ($item['json_is_valid']) : ?>
                <pre><?php echo esc_html($item['pretty_payload']); ?></pre>
            <?php else : ?>
                <div class="notice notice-warning inline">
                    <p>
                        <?php echo esc_html(
                            $item['payload_truncated']
                                ? 'The displayed payload segment is truncated and cannot be decoded as complete JSON.'
                                : 'The stored payload is not valid JSON.'
                        ); ?>
                    </p>
                </div>
                <pre><?php echo esc_html($item['raw_payload']); ?></pre>
            <?php endif; ?>

            <h2><?php echo esc_html('Generate Article Preview'); ?></h2>
            <p>
                <?php echo esc_html(
                    'Runs the BUILD-001…005 pipeline with StubAiProvider. '
                    . 'Produces an in-memory Article Preview only — no WordPress publishing.'
                ); ?>
            </p>
            <form method="post" action="<?php echo esc_url($data['generate_form_url']); ?>">
                <input type="hidden" name="smce_editorial_generate" value="1">
                <input type="hidden" name="item_id" value="<?php echo esc_attr((string) $item['id']); ?>">
                <?php
                wp_nonce_field(
                    $data['generate_nonce_action'],
                    'smce_editorial_generate_nonce'
                );
                ?>
                <p>
                    <button type="submit" class="button button-primary">
                        <?php echo esc_html('Generate'); ?>
                    </button>
                </p>
            </form>

            <?php if (isset($data['article_preview']) && is_array($data['article_preview'])) : ?>
                <div class="smce-article-preview-panel">
                    <h2><?php echo esc_html('Article Preview'); ?></h2>
                    <table class="widefat striped">
                        <tbody>
                            <tr>
                                <th scope="row"><?php echo esc_html('Preview ID'); ?></th>
                                <td><code><?php echo esc_html(
                                    isset($data['article_preview']['preview_id'])
                                        ? (string) $data['article_preview']['preview_id']
                                        : '—'
                                ); ?></code></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php echo esc_html('Result ID'); ?></th>
                                <td><code><?php echo esc_html(
                                    isset($data['article_preview']['result_id'])
                                        ? (string) $data['article_preview']['result_id']
                                        : '—'
                                ); ?></code></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php echo esc_html('Title'); ?></th>
                                <td><?php echo esc_html(
                                    isset($data['article_preview']['title'])
                                        ? (string) $data['article_preview']['title']
                                        : '—'
                                ); ?></td>
                            </tr>
                        </tbody>
                    </table>
                    <h3><?php echo esc_html('Body'); ?></h3>
                    <pre class="smce-article-preview-body"><?php echo esc_html(
                        isset($data['article_preview']['body'])
                            ? (string) $data['article_preview']['body']
                            : ''
                    ); ?></pre>
                </div>
            <?php endif; ?>

            <?php if (isset($data['generation_result']) && is_array($data['generation_result'])) : ?>
                <h2><?php echo esc_html('Generation Meta'); ?></h2>
                <pre><?php echo esc_html(wp_json_encode($data['generation_result'])); ?></pre>
            <?php endif; ?>

            <h2><?php echo esc_html('Lifecycle Diagnostics'); ?></h2>
            <table class="widefat striped">
                <tbody>
                    <tr>
                        <th scope="row"><?php echo esc_html('Spine Ready'); ?></th>
                        <td><?php echo esc_html($data['spine_ready']); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html('Lifecycle Status'); ?></th>
                        <td><?php echo esc_html(
                            isset($data['lifecycle_diagnostics']['status'])
                                ? (string) $data['lifecycle_diagnostics']['status']
                                : '—'
                        ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html('Store'); ?></th>
                        <td><?php echo esc_html(
                            isset($data['lifecycle_diagnostics']['store'])
                                ? (string) $data['lifecycle_diagnostics']['store']
                                : '—'
                        ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html('Last Batch'); ?></th>
                        <td>
                            <?php if (
                                isset($data['lifecycle_diagnostics']['last_batch'])
                                && is_array($data['lifecycle_diagnostics']['last_batch'])
                            ) : ?>
                                <?php
                                $batch = $data['lifecycle_diagnostics']['last_batch'];
                                echo esc_html(
                                    'new=' . (isset($batch['new_count']) ? (string) $batch['new_count'] : '0')
                                    . ', updated=' . (isset($batch['updated_count']) ? (string) $batch['updated_count'] : '0')
                                    . ', unchanged=' . (isset($batch['unchanged_count']) ? (string) $batch['unchanged_count'] : '0')
                                    . ', duplicate=' . (isset($batch['duplicate_count']) ? (string) $batch['duplicate_count'] : '0')
                                );
                                ?>
                            <?php else : ?>
                                <?php echo esc_html('None in this request (ephemeral).'); ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html('last_generation'); ?></th>
                        <td>
                            <?php if (isset($data['last_generation']) && is_array($data['last_generation'])) : ?>
                                <code><?php echo esc_html(wp_json_encode($data['last_generation'])); ?></code>
                            <?php else : ?>
                                <?php echo esc_html('None in this request.'); ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        <?php endif; ?>
    <?php else : ?>
        <div class="smce-editorial-announcements">
            <style>
                .smce-editorial-announcements .smce-editorial-filters {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 12px 16px;
                    align-items: flex-end;
                    margin: 0 0 16px;
                }
                .smce-editorial-announcements .smce-editorial-filter-field {
                    display: flex;
                    flex-direction: column;
                    gap: 4px;
                }
                .smce-editorial-announcements .smce-editorial-filter-field label {
                    font-weight: 600;
                }
                .smce-editorial-announcements .smce-editorial-table-wrap {
                    width: 100%;
                    overflow-x: auto;
                    margin-bottom: 12px;
                }
                .smce-editorial-announcements .smce-editorial-table {
                    width: 100%;
                    min-width: 1100px;
                }
                .smce-status-new {
                    display: inline-block;
                    padding: 2px 8px;
                    background: #edfaef;
                    color: #1e4620;
                    border: 1px solid #68de7c;
                }
                .smce-status-updated {
                    display: inline-block;
                    padding: 2px 8px;
                    background: #f0f6fc;
                    color: #1d2327;
                    border: 1px solid #72aee6;
                }
            </style>

            <form method="get" action="<?php echo esc_url($data['form_url']); ?>">
                <input type="hidden" name="page" value="<?php echo esc_attr('smce-editorial-announcements'); ?>">

                <div class="smce-editorial-filters">
                    <div class="smce-editorial-filter-field">
                        <label for="smce-editorial-search"><?php echo esc_html('Search'); ?></label>
                        <input type="search" id="smce-editorial-search" name="s"
                            value="<?php echo esc_attr($data['filters']['search']); ?>" maxlength="100">
                    </div>
                    <div class="smce-editorial-filter-field">
                        <label for="smce-editorial-source"><?php echo esc_html('Source'); ?></label>
                        <select id="smce-editorial-source" name="source_id">
                            <option value=""><?php echo esc_html('All sources'); ?></option>
                            <?php foreach ($data['source_options'] as $source) : ?>
                                <option value="<?php echo esc_attr((string) $source['id']); ?>"
                                    <?php selected($data['filters']['source_id'], (string) $source['id']); ?>>
                                    <?php echo esc_html(
                                        $source['name'] . ' (#' . (string) $source['id'] . ')'
                                    ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="smce-editorial-filter-field">
                        <label for="smce-editorial-status"><?php echo esc_html('Status'); ?></label>
                        <select id="smce-editorial-status" name="status">
                            <option value=""><?php echo esc_html('All'); ?></option>
                            <option value="<?php echo esc_attr('new'); ?>"
                                <?php selected($data['filters']['status'], 'new'); ?>>
                                <?php echo esc_html('NEW'); ?>
                            </option>
                            <option value="<?php echo esc_attr('updated'); ?>"
                                <?php selected($data['filters']['status'], 'updated'); ?>>
                                <?php echo esc_html('UPDATED'); ?>
                            </option>
                        </select>
                    </div>
                    <div class="smce-editorial-filter-field">
                        <label for="smce-editorial-date-from"><?php echo esc_html('Date From'); ?></label>
                        <input type="date" id="smce-editorial-date-from" name="date_from"
                            value="<?php echo esc_attr($data['filters']['date_from']); ?>">
                    </div>
                    <div class="smce-editorial-filter-field">
                        <label for="smce-editorial-date-to"><?php echo esc_html('Date To'); ?></label>
                        <input type="date" id="smce-editorial-date-to" name="date_to"
                            value="<?php echo esc_attr($data['filters']['date_to']); ?>">
                    </div>
                    <div class="smce-editorial-filter-field">
                        <label for="smce-editorial-sort"><?php echo esc_html('Sort By'); ?></label>
                        <select id="smce-editorial-sort" name="sort">
                            <option value="<?php echo esc_attr('updated'); ?>"
                                <?php selected($data['filters']['sort'], 'updated'); ?>>
                                <?php echo esc_html('Updated'); ?>
                            </option>
                            <option value="<?php echo esc_attr('last_seen'); ?>"
                                <?php selected($data['filters']['sort'], 'last_seen'); ?>>
                                <?php echo esc_html('Last Seen'); ?>
                            </option>
                            <option value="<?php echo esc_attr('published'); ?>"
                                <?php selected($data['filters']['sort'], 'published'); ?>>
                                <?php echo esc_html('Publication Date'); ?>
                            </option>
                            <option value="<?php echo esc_attr('created'); ?>"
                                <?php selected($data['filters']['sort'], 'created'); ?>>
                                <?php echo esc_html('Created Date'); ?>
                            </option>
                            <option value="<?php echo esc_attr('title'); ?>"
                                <?php selected($data['filters']['sort'], 'title'); ?>>
                                <?php echo esc_html('Title'); ?>
                            </option>
                            <option value="<?php echo esc_attr('id'); ?>"
                                <?php selected($data['filters']['sort'], 'id'); ?>>
                                <?php echo esc_html('ID'); ?>
                            </option>
                        </select>
                    </div>
                    <div class="smce-editorial-filter-field">
                        <label for="smce-editorial-direction"><?php echo esc_html('Direction'); ?></label>
                        <select id="smce-editorial-direction" name="direction">
                            <option value="<?php echo esc_attr('desc'); ?>"
                                <?php selected($data['filters']['direction'], 'desc'); ?>>
                                <?php echo esc_html('Descending'); ?>
                            </option>
                            <option value="<?php echo esc_attr('asc'); ?>"
                                <?php selected($data['filters']['direction'], 'asc'); ?>>
                                <?php echo esc_html('Ascending'); ?>
                            </option>
                        </select>
                    </div>
                    <div class="smce-editorial-filter-field">
                        <button type="submit" class="button button-primary"><?php echo esc_html('Apply Filters'); ?></button>
                        <a class="button" href="<?php echo esc_url($data['reset_url']); ?>">
                            <?php echo esc_html('Reset'); ?>
                        </a>
                    </div>
                </div>
            </form>

            <div class="smce-editorial-table-wrap">
                <table class="widefat striped smce-editorial-table">
                    <thead>
                        <tr>
                            <th scope="col"><?php echo esc_html('Source'); ?></th>
                            <th scope="col"><?php echo esc_html('Title'); ?></th>
                            <th scope="col"><?php echo esc_html('Status'); ?></th>
                            <th scope="col"><?php echo esc_html('Revision'); ?></th>
                            <th scope="col"><?php echo esc_html('First Seen'); ?></th>
                            <th scope="col"><?php echo esc_html('Last Seen'); ?></th>
                            <th scope="col"><?php echo esc_html('Updated'); ?></th>
                            <th scope="col"><?php echo esc_html('Actions'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($data['items'] === array()) : ?>
                            <tr>
                                <td colspan="<?php echo esc_attr('8'); ?>">
                                    <?php echo esc_html('No announcements matched the selected filters.'); ?>
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
                                    <td><?php echo esc_html($item['first_seen_at_utc']); ?></td>
                                    <td><?php echo esc_html($item['last_seen_at_utc']); ?></td>
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
            </div>

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

            <?php if ($data['limit_reached']) : ?>
                <div class="notice notice-warning inline">
                    <p><?php echo esc_html('The maximum browse depth of 200 pages has been reached.'); ?></p>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
