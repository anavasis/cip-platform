<?php

defined('ABSPATH') || exit;
?>
<div class="wrap">
    <h1><?php echo esc_html($data['title']); ?></h1>

    <?php if ($data['notice_message'] !== '') : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html($data['notice_message']); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($data['error_message'] !== '') : ?>
        <div class="notice notice-error">
            <p><?php echo esc_html($data['error_message']); ?></p>
        </div>
    <?php endif; ?>

    <h2><?php echo esc_html('Existing Sources'); ?></h2>

    <?php if ($data['sources'] === array()) : ?>
        <p><?php echo esc_html('No sources have been registered yet.'); ?></p>
    <?php else : ?>
        <table class="widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col"><?php echo esc_html('ID'); ?></th>
                    <th scope="col"><?php echo esc_html('Slug'); ?></th>
                    <th scope="col"><?php echo esc_html('Name'); ?></th>
                    <th scope="col"><?php echo esc_html('Type'); ?></th>
                    <th scope="col"><?php echo esc_html('Feed URL'); ?></th>
                    <th scope="col"><?php echo esc_html('Status'); ?></th>
                    <th scope="col"><?php echo esc_html('Actions'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['sources'] as $source) : ?>
                    <tr>
                        <td><?php echo esc_html((string) $source['id']); ?></td>
                        <td><?php echo esc_html($source['slug']); ?></td>
                        <td><?php echo esc_html($source['name']); ?></td>
                        <td><?php echo esc_html($source['source_type']); ?></td>
                        <td>
                            <a href="<?php echo esc_url($source['feed_url']); ?>" target="_blank" rel="noopener noreferrer">
                                <?php echo esc_html($source['feed_url']); ?>
                            </a>
                        </td>
                        <td>
                            <?php echo esc_html($source['enabled'] ? 'Enabled' : 'Disabled'); ?>
                        </td>
                        <td>
                            <a class="button button-small" href="<?php echo esc_url($source['edit_url']); ?>">
                                <?php echo esc_html('Edit'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <?php if ($data['edit_source'] !== null) : ?>
        <?php $edit = $data['edit_source']; ?>
        <hr>
        <h2><?php echo esc_html('Edit Source'); ?></h2>
        <form method="post" action="<?php echo esc_url($edit['update_action']); ?>">
            <input type="hidden" name="action" value="smce_source_update">
            <input type="hidden" name="source_id" value="<?php echo esc_attr((string) $edit['id']); ?>">
            <?php wp_nonce_field($edit['nonce_action'], 'smce_source_nonce'); ?>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php echo esc_html('Slug'); ?></th>
                    <td>
                        <code><?php echo esc_html($edit['slug']); ?></code>
                        <p class="description"><?php echo esc_html('The slug cannot be changed after creation.'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="smce-edit-name"><?php echo esc_html('Name'); ?></label></th>
                    <td>
                        <input name="name" id="smce-edit-name" type="text" class="regular-text" required
                            value="<?php echo esc_attr($edit['name']); ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="smce-edit-source-type"><?php echo esc_html('Source Type'); ?></label></th>
                    <td>
                        <select name="source_type" id="smce-edit-source-type" required>
                            <?php foreach ($data['source_types'] as $sourceType) : ?>
                                <option value="<?php echo esc_attr($sourceType); ?>"<?php selected($edit['source_type'], $sourceType); ?>>
                                    <?php echo esc_html($sourceType); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="smce-edit-base-url"><?php echo esc_html('Base URL'); ?></label></th>
                    <td>
                        <input name="base_url" id="smce-edit-base-url" type="url" class="regular-text"
                            value="<?php echo esc_attr($edit['base_url']); ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="smce-edit-feed-url"><?php echo esc_html('Feed URL'); ?></label></th>
                    <td>
                        <input name="feed_url" id="smce-edit-feed-url" type="url" class="regular-text" required
                            value="<?php echo esc_attr($edit['feed_url']); ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="smce-edit-allowed-domains"><?php echo esc_html('Allowed Domains'); ?></label></th>
                    <td>
                        <textarea name="allowed_domains" id="smce-edit-allowed-domains" class="large-text" rows="5"><?php echo esc_textarea($edit['allowed_domains']); ?></textarea>
                        <p class="description"><?php echo esc_html('Enter one domain per line. Wildcards are not permitted.'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="smce-edit-parser-profile"><?php echo esc_html('Parser Profile'); ?></label></th>
                    <td>
                        <input name="parser_profile" id="smce-edit-parser-profile" type="text" class="regular-text"
                            value="<?php echo esc_attr($edit['parser_profile']); ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html('Status'); ?></th>
                    <td>
                        <p>
                            <?php echo esc_html($edit['enabled'] ? 'Enabled' : 'Disabled'); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <button type="submit" class="button button-primary"><?php echo esc_html('Save Changes'); ?></button>
                <a class="button" href="<?php echo esc_url($data['list_url']); ?>"><?php echo esc_html('Back to List'); ?></a>
            </p>
        </form>

        <form method="post" action="<?php echo esc_url($edit['toggle_action']); ?>">
            <input type="hidden" name="action" value="smce_source_toggle">
            <input type="hidden" name="source_id" value="<?php echo esc_attr((string) $edit['id']); ?>">
            <input type="hidden" name="enabled" value="<?php echo esc_attr($edit['enabled'] ? '0' : '1'); ?>">
            <?php wp_nonce_field($edit['toggle_nonce_action'], 'smce_source_nonce'); ?>
            <p>
                <button type="submit" class="button">
                    <?php echo esc_html($edit['enabled'] ? 'Disable Source' : 'Enable Source'); ?>
                </button>
            </p>
        </form>

        <hr>
        <h2><?php echo esc_html('Check Source'); ?></h2>
        <p class="description">
            <?php echo esc_html('Run one synchronous guarded request and preview RSS or Atom items. Nothing is saved.'); ?>
        </p>
        <form method="post" action="<?php echo esc_url($edit['check_action']); ?>">
            <input type="hidden" name="smce_source_check" value="1">
            <input type="hidden" name="source_id" value="<?php echo esc_attr((string) $edit['id']); ?>">
            <?php wp_nonce_field($edit['check_nonce_action'], 'smce_source_nonce'); ?>
            <p>
                <button type="submit" class="button"><?php echo esc_html('Check Source'); ?></button>
            </p>
        </form>

        <?php if ($data['check_result'] !== null) : ?>
            <?php $result = $data['check_result']; ?>
            <div class="smce-source-check-result" style="margin-top: 1em;">
                <h3><?php echo esc_html('Source Check Result'); ?></h3>

                <?php if ($result['success'] !== true) : ?>
                    <div class="notice notice-error inline">
                        <p><?php echo esc_html((string) $result['error_message']); ?></p>
                    </div>
                <?php else : ?>
                    <div class="notice notice-success inline">
                        <p><?php echo esc_html('Feed preview completed successfully.'); ?></p>
                    </div>
                <?php endif; ?>

                <table class="widefat fixed striped" style="max-width: 960px;">
                    <tbody>
                        <tr>
                            <th scope="row"><?php echo esc_html('Requested URL'); ?></th>
                            <td>
                                <?php if ($result['requested_url'] !== '') : ?>
                                    <a href="<?php echo esc_url($result['requested_url']); ?>" target="_blank" rel="noopener noreferrer">
                                        <?php echo esc_html($result['requested_url']); ?>
                                    </a>
                                <?php else : ?>
                                    <?php echo esc_html('—'); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo esc_html('Final URL'); ?></th>
                            <td>
                                <?php if ($result['final_url'] !== '') : ?>
                                    <a href="<?php echo esc_url($result['final_url']); ?>" target="_blank" rel="noopener noreferrer">
                                        <?php echo esc_html($result['final_url']); ?>
                                    </a>
                                <?php else : ?>
                                    <?php echo esc_html('—'); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo esc_html('HTTP Status'); ?></th>
                            <td><?php echo esc_html($result['http_status'] > 0 ? (string) $result['http_status'] : '—'); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo esc_html('Content Type'); ?></th>
                            <td><?php echo esc_html($result['content_type'] !== '' ? $result['content_type'] : '—'); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo esc_html('Response Size'); ?></th>
                            <td><?php echo esc_html((string) (int) $result['response_size']); ?> <?php echo esc_html('bytes'); ?></td>
                        </tr>
                        <?php if ($result['success'] === true) : ?>
                            <tr>
                                <th scope="row"><?php echo esc_html('Detected Format'); ?></th>
                                <td><?php echo esc_html(strtoupper((string) $result['format'])); ?></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php echo esc_html('Total Items'); ?></th>
                                <td><?php echo esc_html((string) (int) $result['item_count']); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <?php if ($result['success'] === true && !empty($result['preview_items'])) : ?>
                    <h4><?php echo esc_html('Preview Items'); ?></h4>
                    <table class="widefat fixed striped" style="max-width: 960px;">
                        <thead>
                            <tr>
                                <th scope="col"><?php echo esc_html('Title'); ?></th>
                                <th scope="col"><?php echo esc_html('Link'); ?></th>
                                <th scope="col"><?php echo esc_html('Published / Updated'); ?></th>
                                <th scope="col"><?php echo esc_html('Category'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($result['preview_items'] as $item) : ?>
                                <tr>
                                    <td><?php echo esc_html(isset($item['title']) ? (string) $item['title'] : ''); ?></td>
                                    <td>
                                        <?php if (!empty($item['link'])) : ?>
                                            <a href="<?php echo esc_url((string) $item['link']); ?>" target="_blank" rel="noopener noreferrer">
                                                <?php echo esc_html((string) $item['link']); ?>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html(isset($item['date']) ? (string) $item['date'] : ''); ?></td>
                                    <td>
                                        <?php echo esc_html(!empty($item['category']) ? (string) $item['category'] : '—'); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php else : ?>
        <hr>
        <h2><?php echo esc_html('Add Source'); ?></h2>
        <form method="post" action="<?php echo esc_url($data['create_action']); ?>">
            <input type="hidden" name="action" value="smce_source_create">
            <?php wp_nonce_field($data['create_nonce_action'], 'smce_source_nonce'); ?>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="smce-create-slug"><?php echo esc_html('Slug'); ?></label></th>
                    <td>
                        <input name="slug" id="smce-create-slug" type="text" class="regular-text" required
                            pattern="[a-z0-9]+(?:-[a-z0-9]+)*" maxlength="128">
                        <p class="description"><?php echo esc_html('Lowercase letters, numbers, and hyphens only.'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="smce-create-name"><?php echo esc_html('Name'); ?></label></th>
                    <td>
                        <input name="name" id="smce-create-name" type="text" class="regular-text" required maxlength="191">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="smce-create-source-type"><?php echo esc_html('Source Type'); ?></label></th>
                    <td>
                        <select name="source_type" id="smce-create-source-type" required>
                            <option value=""><?php echo esc_html('Select a type'); ?></option>
                            <?php foreach ($data['source_types'] as $sourceType) : ?>
                                <option value="<?php echo esc_attr($sourceType); ?>">
                                    <?php echo esc_html($sourceType); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="smce-create-base-url"><?php echo esc_html('Base URL'); ?></label></th>
                    <td>
                        <input name="base_url" id="smce-create-base-url" type="url" class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="smce-create-feed-url"><?php echo esc_html('Feed URL'); ?></label></th>
                    <td>
                        <input name="feed_url" id="smce-create-feed-url" type="url" class="regular-text" required>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="smce-create-allowed-domains"><?php echo esc_html('Allowed Domains'); ?></label></th>
                    <td>
                        <textarea name="allowed_domains" id="smce-create-allowed-domains" class="large-text" rows="5"></textarea>
                        <p class="description"><?php echo esc_html('Enter one domain per line. Wildcards are not permitted.'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="smce-create-parser-profile"><?php echo esc_html('Parser Profile'); ?></label></th>
                    <td>
                        <input name="parser_profile" id="smce-create-parser-profile" type="text" class="regular-text">
                    </td>
                </tr>
            </table>

            <p class="submit">
                <button type="submit" class="button button-primary"><?php echo esc_html('Create Source'); ?></button>
            </p>
            <p class="description">
                <?php echo esc_html('New sources are created disabled and must be enabled separately.'); ?>
            </p>
        </form>
    <?php endif; ?>
</div>
