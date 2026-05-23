<?php

defined('WP_UNINSTALL_PLUGIN') || exit;

delete_option('gls_label_migration_status');
delete_option('gls_label_orphan_cleanup_done');

while ($timestamp = wp_next_scheduled('gls_shipping_tracking_sync_event')) {
    wp_unschedule_event($timestamp, 'gls_shipping_tracking_sync_event');
}

if (function_exists('as_unschedule_all_actions')) {
    as_unschedule_all_actions('gls_migrate_labels_batch');
}

// Intentionally keep WooCommerce shipping method settings, order meta and stored label files.
// These records can be business-relevant for already created shipments and require a separate retention policy.