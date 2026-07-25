<?php

defined('ABSPATH') || exit;
?>
<div class="wrap">
    <h1><?php echo esc_html($data['title']); ?></h1>

    <?php foreach ($data['error_messages'] as $errorMessage) : ?>
        <div class="notice notice-error">
            <p><?php echo esc_html($errorMessage); ?></p>
        </div>
    <?php endforeach; ?>

    <?php if ($data['mode'] === 'detail') : ?>
        <p>
            <a href="<?php echo esc_url($data['back_url']); ?>">
                <?php echo esc_html('← Back to Imported Items'); ?>
            </a>
        </p>

        <?php if (is_array($data['detail_item'])) : ?>
            <?php $item = $data['detail_item']; ?>
            <h2><?php echo esc_html('Imported Item Details'); ?></h2>

            <table class="widefat striped">
                <tbody>
                    <tr>
                        <th scope="row"><?php echo esc_html('ID'); ?></th>
                        <td><?php echo esc_html($item['id']); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html('Source Name'); ?></th>
                        <td><?php echo esc_html($item['source_name']); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html('Source ID'); ?></th>
                        <td><?php echo esc_html($item['source_id']); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html('Source Slug'); ?></th>
                        <td><?php echo esc_html($item['source_slug']); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html('Identity Hash'); ?></th>
                        <td><code><?php echo esc_html($item['identity_hash']); ?></code></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html('Identity Basis'); ?></th>
                        <td><?php echo esc_html($item['identity_basis']); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html('Source GUID'); ?></th>
                        <td><?php echo esc_html($item['source_guid']); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html('Canonical URL'); ?></th>
                        <td>
                            <?php $canonicalHref = esc_url($item['canonical_url']); ?>
                            <?php if ($canonicalHref !== '') : ?>
                                <a href="<?php echo esc_url($canonicalHref); ?>" target="_blank" rel="noopener noreferrer">
                                    <?php echo esc_html($item['canonical_url_display']); ?>
                                </a>
                            <?php else : ?>
                                <?php echo esc_html($item['canonical_url_display']); ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html('Publication Date (UTC)'); ?></th>
                        <td><?php echo esc_html($item['source_published_at_utc']); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html('Title'); ?></th>
                        <td><?php echo esc_html($item['raw_title']); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html('Category'); ?></th>
                        <td><?php echo esc_html($item['category']); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html('Content Hash'); ?></th>
                        <td><code><?php echo esc_html($item['content_hash']); ?></code></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html('Revision'); ?></th>
                        <td><?php echo esc_html($item['revision_no']); ?></td>
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
                        <th scope="row"><?php echo esc_html('Created (UTC)'); ?></th>
                        <td><?php echo esc_html($item['created_at_utc']); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html('Updated (UTC)'); ?></th>
                        <td><?php echo esc_html($item['updated_at_utc']); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html('Raw Payload Bytes'); ?></th>
                        <td><?php echo esc_html((string) $item['raw_payload_bytes']); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html('Display Payload Truncated'); ?></th>
                        <td><?php echo esc_html($item['payload_truncated'] ? 'Yes' : 'No'); ?></td>
                    </tr>
                </tbody>
            </table>

            <h2><?php echo esc_html('Pretty JSON'); ?></h2>
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
            <?php endif; ?>

            <h2><?php echo esc_html('Stored Payload Representation'); ?></h2>
            <pre><?php echo esc_html($item['raw_payload']); ?></pre>
        <?php endif; ?>
    <?php else : ?>
        <div class="smce-imported-items">
            <style>
                .smce-imported-items .smce-imported-items-filters {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 12px 16px;
                    align-items: flex-end;
                    margin: 0 0 16px;
                }
                .smce-imported-items .smce-imported-items-filter-field {
                    display: flex;
                    flex-direction: column;
                    gap: 4px;
                    min-width: 0;
                }
                .smce-imported-items .smce-imported-items-filter-field label {
                    font-weight: 600;
                }
                .smce-imported-items .smce-imported-items-filter-field input[type="search"],
                .smce-imported-items .smce-imported-items-filter-field input[type="date"],
                .smce-imported-items .smce-imported-items-filter-field select {
                    max-width: 100%;
                }
                .smce-imported-items .smce-imported-items-filter-actions {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 8px;
                    align-items: center;
                }
                .smce-imported-items .smce-imported-items-table-wrap {
                    width: 100%;
                    max-width: 100%;
                    overflow-x: auto;
                    -webkit-overflow-scrolling: touch;
                    margin-bottom: 12px;
                }
                .smce-imported-items .smce-imported-items-table {
                    width: 100%;
                    min-width: 1200px;
                    table-layout: auto;
                }
                .smce-imported-items .smce-col-id,
                .smce-imported-items .smce-col-revision,
                .smce-imported-items .smce-col-details {
                    min-width: 48px;
                    white-space: nowrap;
                }
                .smce-imported-items .smce-col-source {
                    min-width: 200px;
                }
                .smce-imported-items .smce-col-publication {
                    min-width: 112px;
                    white-space: nowrap;
                }
                .smce-imported-items .smce-col-category {
                    min-width: 165px;
                }
                .smce-imported-items .smce-col-title {
                    min-width: 250px;
                }
                .smce-imported-items .smce-col-url {
                    min-width: 230px;
                    overflow-wrap: anywhere;
                }
                .smce-imported-items .smce-col-identity {
                    min-width: 120px;
                }
                .smce-imported-items .smce-col-created {
                    min-width: 145px;
                    white-space: nowrap;
                }
            </style>

            <form method="get" action="<?php echo esc_url($data['form_url']); ?>">
                <input type="hidden" name="page" value="<?php echo esc_attr('smce-imported-items'); ?>">

                <div class="smce-imported-items-filters">
                    <div class="smce-imported-items-filter-field">
                        <label for="smce-imported-search"><?php echo esc_html('Search'); ?></label>
                        <input type="search" id="smce-imported-search" name="s"
                            value="<?php echo esc_attr($data['filters']['search']); ?>" maxlength="100">
                    </div>
                    <div class="smce-imported-items-filter-field">
                        <label for="smce-imported-source"><?php echo esc_html('Source'); ?></label>
                        <select id="smce-imported-source" name="source_id">
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
                    <div class="smce-imported-items-filter-field">
                        <label for="smce-imported-date-from"><?php echo esc_html('Date From'); ?></label>
                        <input type="date" id="smce-imported-date-from" name="date_from"
                            value="<?php echo esc_attr($data['filters']['date_from']); ?>">
                    </div>
                    <div class="smce-imported-items-filter-field">
                        <label for="smce-imported-date-to"><?php echo esc_html('Date To'); ?></label>
                        <input type="date" id="smce-imported-date-to" name="date_to"
                            value="<?php echo esc_attr($data['filters']['date_to']); ?>">
                    </div>
                    <div class="smce-imported-items-filter-field">
                        <label for="smce-imported-sort"><?php echo esc_html('Sort By'); ?></label>
                        <select id="smce-imported-sort" name="sort">
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
                    <div class="smce-imported-items-filter-field">
                        <label for="smce-imported-direction"><?php echo esc_html('Direction'); ?></label>
                        <select id="smce-imported-direction" name="direction">
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
                    <div class="smce-imported-items-filter-actions">
                        <button type="submit" class="button button-primary"><?php echo esc_html('Apply Filters'); ?></button>
                        <a class="button" href="<?php echo esc_url($data['reset_url']); ?>">
                            <?php echo esc_html('Reset'); ?>
                        </a>
                    </div>
                </div>
                <?php if ($data['source_options_truncated']) : ?>
                    <p class="description">
                        <?php echo esc_html('Only the first 200 sources are available in this filter.'); ?>
                    </p>
                <?php endif; ?>
            </form>

            <div class="smce-imported-items-table-wrap">
                <table class="widefat striped smce-imported-items-table">
                    <thead>
                        <tr>
                            <th scope="col" class="smce-col-id"><?php echo esc_html('ID'); ?></th>
                            <th scope="col" class="smce-col-source"><?php echo esc_html('Source'); ?></th>
                            <th scope="col" class="smce-col-publication"><?php echo esc_html('Publication Date'); ?></th>
                            <th scope="col" class="smce-col-category"><?php echo esc_html('Category'); ?></th>
                            <th scope="col" class="smce-col-title"><?php echo esc_html('Title'); ?></th>
                            <th scope="col" class="smce-col-url"><?php echo esc_html('Canonical URL'); ?></th>
                            <th scope="col" class="smce-col-identity"><?php echo esc_html('Identity Basis'); ?></th>
                            <th scope="col" class="smce-col-revision"><?php echo esc_html('Revision'); ?></th>
                            <th scope="col" class="smce-col-created"><?php echo esc_html('Created'); ?></th>
                            <th scope="col" class="smce-col-details"><?php echo esc_html('Details'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($data['items'] === array()) : ?>
                            <tr>
                                <td colspan="<?php echo esc_attr('10'); ?>">
                                    <?php echo esc_html('No imported items matched the selected filters.'); ?>
                                </td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ($data['items'] as $item) : ?>
                                <tr>
                                    <td class="smce-col-id"><?php echo esc_html((string) $item['id']); ?></td>
                                    <td class="smce-col-source">
                                        <?php echo esc_html(
                                            $item['source_name'] . ' (#' . (string) $item['source_id'] . ')'
                                        ); ?>
                                    </td>
                                    <td class="smce-col-publication">
                                        <?php
                                        $publicationDate = (string) $item['publication_date'];
                                        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $publicationDate, $publicationMatch) === 1) {
                                            echo esc_html($publicationMatch[1]);
                                        } else {
                                            echo esc_html($publicationDate);
                                        }
                                        ?>
                                    </td>
                                    <td class="smce-col-category"><?php echo esc_html($item['category']); ?></td>
                                    <td class="smce-col-title"><?php echo esc_html($item['title']); ?></td>
                                    <td class="smce-col-url">
                                        <?php
                                        $canonicalUrl = (string) $item['canonical_url'];
                                        $canonicalHref = esc_url($canonicalUrl);
                                        $urlLabelMaxLength = 62;
                                        $urlVisibleLabel = $canonicalUrl;
                                        if ($canonicalUrl !== '') {
                                            $parsedUrl = wp_parse_url($canonicalUrl);
                                            if (is_array($parsedUrl)) {
                                                $urlHost = isset($parsedUrl['host']) ? (string) $parsedUrl['host'] : '';
                                                $urlPath = isset($parsedUrl['path']) ? (string) $parsedUrl['path'] : '';
                                                $urlQuery = isset($parsedUrl['query']) ? '?' . (string) $parsedUrl['query'] : '';
                                                $urlScheme = isset($parsedUrl['scheme']) ? (string) $parsedUrl['scheme'] . '://' : '';
                                                $urlVisibleLabel = $urlScheme . $urlHost . $urlPath . $urlQuery;
                                            }
                                            if (strlen($urlVisibleLabel) > $urlLabelMaxLength) {
                                                $urlVisibleLabel = substr($urlVisibleLabel, 0, $urlLabelMaxLength) . '…';
                                            }
                                        }
                                        ?>
                                        <?php if ($canonicalHref !== '') : ?>
                                            <a href="<?php echo esc_url($canonicalHref); ?>"
                                                title="<?php echo esc_attr($canonicalUrl); ?>"
                                                target="_blank" rel="noopener noreferrer">
                                                <?php echo esc_html($urlVisibleLabel); ?>
                                            </a>
                                        <?php else : ?>
                                            <?php echo esc_html($urlVisibleLabel); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="smce-col-identity"><?php echo esc_html($item['identity_basis']); ?></td>
                                    <td class="smce-col-revision"><?php echo esc_html($item['revision_no']); ?></td>
                                    <td class="smce-col-created">
                                        <?php
                                        $createdAtUtc = (string) $item['created_at_utc'];
                                        if (preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}):\d{2}$/', $createdAtUtc, $createdMatch) === 1) {
                                            echo esc_html($createdMatch[1]);
                                        } else {
                                            echo esc_html($createdAtUtc);
                                        }
                                        ?>
                                    </td>
                                    <td class="smce-col-details">
                                        <a href="<?php echo esc_url($item['details_url']); ?>">
                                            <?php echo esc_html('Details'); ?>
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
