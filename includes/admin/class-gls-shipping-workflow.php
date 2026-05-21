<?php

if (!defined('ABSPATH')) {
    exit;
}

class GLS_Shipping_Workflow
{
    public const CRON_HOOK = 'gls_shipping_tracking_sync_event';
    public const CRON_SCHEDULE = 'gls_shipping_every_thirty_minutes';
    public const RETURN_STATUS = ARD_WORKFLOW_STATUS_RETURN;
    public const READY_TO_SHIP_STATUS = ARD_WORKFLOW_STATUS_READY_TO_SHIP;
    public const IN_TRANSIT_STATUS = ARD_WORKFLOW_STATUS_IN_TRANSIT;
    public const MANUAL_REVIEW_STATUS = ARD_WORKFLOW_STATUS_MANUAL_REVIEW;
    public const CURRENT_STATUS_META_KEY = '_gls_tracking_current_status';
    public const CURRENT_STATUS_CODE_META_KEY = '_gls_tracking_current_status_code';
    public const CURRENT_LABEL_META_KEY = '_gls_tracking_current_label';
    public const CURRENT_DESCRIPTION_META_KEY = '_gls_tracking_current_description';
    public const CURRENT_DATE_META_KEY = '_gls_tracking_current_date';
    public const CURRENT_LOCATION_META_KEY = '_gls_tracking_current_location';
    public const LAST_SYNC_AT_META_KEY = '_gls_tracking_last_sync_at';
    public const HISTORY_META_KEY = '_gls_tracking_history';

    private const MANAGED_STATUSES = array(
        self::READY_TO_SHIP_STATUS,
        self::IN_TRANSIT_STATUS,
        self::RETURN_STATUS,
    );

    public function __construct()
    {
        add_action('init', array($this, 'register_workflow_statuses'));
        add_filter('wc_order_statuses', array($this, 'register_workflow_statuses_in_lists'));
        add_filter('cron_schedules', array($this, 'register_cron_schedules'));
        add_action('init', array($this, 'maybe_schedule_tracking_cron'));
        add_action(self::CRON_HOOK, array($this, 'sync_open_shipments'));
        add_action('wp_ajax_gls_mark_label_printed', array($this, 'ajax_mark_label_printed'));
    }

    public function register_workflow_statuses()
    {
        ard_workflow_register_post_statuses(self::MANAGED_STATUSES, 'gls-shipping-for-woocommerce');
    }

    public function register_workflow_statuses_in_lists($statuses)
    {
        $required_status_keys = array_map(static function ($status) {
            return ard_workflow_wc_status_key($status);
        }, self::MANAGED_STATUSES);
        $missing_status_keys = array_diff($required_status_keys, array_keys($statuses));

        if ($missing_status_keys === array()) {
            return $statuses;
        }

        $statuses = ard_workflow_insert_statuses_after(
            $statuses,
            array(self::READY_TO_SHIP_STATUS, self::IN_TRANSIT_STATUS),
            'gls-shipping-for-woocommerce',
            'wc-processing'
        );

        return ard_workflow_insert_statuses_after(
            $statuses,
            array(self::RETURN_STATUS),
            'gls-shipping-for-woocommerce',
            'wc-completed'
        );
    }

    public function register_cron_schedules($schedules)
    {
        $schedules[self::CRON_SCHEDULE] = array(
            'interval' => 30 * MINUTE_IN_SECONDS,
            'display' => __('Every 30 minutes (GLS tracking sync)', 'gls-shipping-for-woocommerce'),
        );

        return $schedules;
    }

    public function maybe_schedule_tracking_cron()
    {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + (5 * MINUTE_IN_SECONDS), self::CRON_SCHEDULE, self::CRON_HOOK);
        }
    }

    public function ajax_mark_label_printed()
    {
        if (!current_user_can('edit_shop_orders')) {
            wp_send_json_error(array('message' => __('You do not have permission to update this order.', 'gls-shipping-for-woocommerce')), 403);
        }

        check_ajax_referer('import-nonce', 'postNonce');

        $order_id = isset($_POST['orderId']) ? absint(wp_unslash($_POST['orderId'])) : 0;
        $order = $order_id > 0 ? wc_get_order($order_id) : false;
        if (!$order) {
            wp_send_json_error(array('message' => __('Order not found.', 'gls-shipping-for-woocommerce')), 404);
        }

        $this->mark_label_printed($order);
        $order->save();

        wp_send_json_success(array('message' => __('Order status was changed to Na odoslanie.', 'gls-shipping-for-woocommerce')));
    }

    public function mark_label_printed($order)
    {
        if (!$order instanceof \WC_Order) {
            return false;
        }

        return $this->transition_order_status(
            $order,
            self::READY_TO_SHIP_STATUS,
            __('Shipping label was downloaded and confirmed as printed. Order moved to Na odoslanie.', 'gls-shipping-for-woocommerce'),
            '',
            false,
            array('cancelled', 'refunded', 'failed', 'completed', self::RETURN_STATUS, self::MANUAL_REVIEW_STATUS, self::IN_TRANSIT_STATUS)
        );
    }

    public function sync_open_shipments()
    {
        if (!$this->is_api_configured()) {
            return;
        }

        $orders = wc_get_orders(array(
            'limit' => 100,
            'return' => 'objects',
            'status' => array('pending', 'processing', 'on-hold', 'na-odoslanie', 'zabalena', 'v-preprave'),
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => '_gls_tracking_codes',
                    'compare' => 'EXISTS',
                ),
                array(
                    'key' => '_gls_tracking_code',
                    'compare' => 'EXISTS',
                ),
            ),
        ));

        if (empty($orders)) {
            return;
        }

        $api_service = new \GLS_Shipping_API_Service();

        foreach ($orders as $order) {
            if (!$order instanceof \WC_Order) {
                continue;
            }

            $tracking_numbers = $this->get_tracking_numbers($order);
            if (empty($tracking_numbers)) {
                continue;
            }

            $order_events = array();
            foreach ($tracking_numbers as $tracking_number) {
                try {
                    $tracking_data = $api_service->get_parcel_status($tracking_number);
                    $event = $this->extract_latest_tracking_event($tracking_data);
                    if (!empty($event)) {
                        $event['tracking_number'] = $tracking_number;
                        $order_events[] = $event;
                    }
                } catch (Exception $exception) {
                    continue;
                }
            }

            if (empty($order_events)) {
                continue;
            }

            $aggregate_event = $this->aggregate_order_events($order_events);
            if (empty($aggregate_event)) {
                continue;
            }

            $this->store_tracking_event($order, $aggregate_event);
            $order->save();
        }
    }

    public function process_tracking_response($order, $tracking_data)
    {
        if (!$order instanceof \WC_Order || !is_array($tracking_data)) {
            return;
        }

        $event = $this->extract_latest_tracking_event($tracking_data);
        if (empty($event)) {
            return;
        }

        $this->store_tracking_event($order, $event);
        $order->save();
    }

    private function store_tracking_event($order, $event)
    {
        $previous_status = (string) $order->get_meta(self::CURRENT_STATUS_META_KEY, true);
        $current_status = (string) ($event['status'] ?? '');
        $current_status_code = (string) ($event['status_code'] ?? '');
        $current_label = (string) ($event['label'] ?? $current_status);
        $current_description = (string) ($event['description'] ?? '');
        $current_date = (string) ($event['date'] ?? current_time('mysql'));
        $current_location = (string) ($event['location'] ?? '');

        $order->update_meta_data(self::CURRENT_STATUS_META_KEY, $current_status);
        $order->update_meta_data(self::CURRENT_STATUS_CODE_META_KEY, $current_status_code);
        $order->update_meta_data(self::CURRENT_LABEL_META_KEY, $current_label);
        $order->update_meta_data(self::CURRENT_DESCRIPTION_META_KEY, $current_description);
        $order->update_meta_data(self::CURRENT_DATE_META_KEY, $current_date);
        $order->update_meta_data(self::CURRENT_LOCATION_META_KEY, $current_location);
        $order->update_meta_data(self::LAST_SYNC_AT_META_KEY, current_time('mysql'));
        $order->update_meta_data(self::HISTORY_META_KEY, $this->merge_tracking_history($order, $event));

        if ($current_status !== '' && $current_status !== $previous_status) {
            $order->add_order_note(sprintf(
                /* translators: 1: current GLS tracking label, 2: optional tracking location. */
                __('GLS tracking update: %1$s%2$s', 'gls-shipping-for-woocommerce'),
                $current_label,
                $current_location ? ' (' . $current_location . ')' : ''
            ));
        }

        $this->apply_business_transition($order, $event);
    }

    private function merge_tracking_history($order, $event)
    {
        $history = $order->get_meta(self::HISTORY_META_KEY, true);
        $history = is_array($history) ? $history : array();
        $history[] = array(
            'status' => (string) ($event['status'] ?? ''),
            'status_code' => (string) ($event['status_code'] ?? ''),
            'label' => (string) ($event['label'] ?? ''),
            'description' => (string) ($event['description'] ?? ''),
            'date' => (string) ($event['date'] ?? current_time('mysql')),
            'location' => (string) ($event['location'] ?? ''),
            'tracking_number' => (string) ($event['tracking_number'] ?? ''),
        );

        $history = array_values(array_unique($history, SORT_REGULAR));
        usort($history, function ($left, $right) {
            return strcmp((string) ($left['date'] ?? ''), (string) ($right['date'] ?? ''));
        });

        return $history;
    }

    private function apply_business_transition($order, $event)
    {
        $status = sanitize_key((string) ($event['status'] ?? ''));

        if (in_array($status, array('picked_up', 'in_transit', 'out_for_delivery'), true)) {
            $this->transition_order_status(
                $order,
                self::IN_TRANSIT_STATUS,
                __('Carrier confirmed that the shipment is in transit.', 'gls-shipping-for-woocommerce'),
                __('Your shipment has been handed over to the carrier and is now in transit.', 'gls-shipping-for-woocommerce'),
                true,
                array('cancelled', 'refunded', 'failed', 'completed', self::RETURN_STATUS, self::MANUAL_REVIEW_STATUS)
            );

            return;
        }

        if ($status === 'delivered') {
            $this->transition_order_status(
                $order,
                'completed',
                __('Carrier confirmed successful delivery.', 'gls-shipping-for-woocommerce'),
                '',
                false,
                array('cancelled', 'refunded', 'failed', self::MANUAL_REVIEW_STATUS)
            );

            return;
        }

        if ($status === 'returning_to_sender') {
            $this->transition_order_status(
                $order,
                self::RETURN_STATUS,
                __('Carrier reported that the shipment is returning to the sender.', 'gls-shipping-for-woocommerce'),
                '',
                false,
                array('cancelled', 'refunded', 'failed', 'completed', self::MANUAL_REVIEW_STATUS)
            );

            return;
        }

        if ($status === 'sender_received_return') {
            $this->transition_order_status(
                $order,
                self::MANUAL_REVIEW_STATUS,
                __('Returned shipment was received back and now requires manual review.', 'gls-shipping-for-woocommerce'),
                '',
                false,
                array('cancelled', 'refunded', 'failed', 'completed')
            );
        }
    }

    private function transition_order_status($order, $target_status, $internal_note, $customer_note = '', $notify_customer = false, $blocked_statuses = array())
    {
        if (!$order instanceof \WC_Order) {
            return false;
        }

        $target_status = sanitize_key($target_status);
        if ($target_status === '') {
            return false;
        }

        if (!$this->is_status_available($target_status)) {
            $order->add_order_note(sprintf(
                /* translators: %s: requested WooCommerce order status slug. */
                __('Requested workflow status "%s" is not available, so the order status was not changed.', 'gls-shipping-for-woocommerce'),
                $target_status
            ));

            return false;
        }

        if ($order->has_status(array($target_status))) {
            return false;
        }

        if (!empty($blocked_statuses) && $order->has_status($blocked_statuses)) {
            return false;
        }

        $order->update_status($target_status, $internal_note);
        if ($notify_customer && $customer_note !== '') {
            $order->add_order_note($customer_note, true, false);
        }

        return true;
    }

    private function is_status_available($status)
    {
        $statuses = function_exists('wc_get_order_statuses') ? wc_get_order_statuses() : array();

        return isset($statuses['wc-' . $status]);
    }

    private function is_api_configured()
    {
        $settings = get_option('woocommerce_gls_shipping_method_settings', array());

        return !empty($settings['username']) && !empty($settings['password']);
    }

    private function get_tracking_numbers($order)
    {
        if (!$order instanceof \WC_Order) {
            return array();
        }

        $tracking_numbers = array();
        $tracking_codes = $order->get_meta('_gls_tracking_codes', true);
        if (is_array($tracking_codes)) {
            $tracking_numbers = array_merge($tracking_numbers, $tracking_codes);
        } elseif (!empty($tracking_codes)) {
            $tracking_numbers[] = $tracking_codes;
        }

        $legacy_tracking_code = $order->get_meta('_gls_tracking_code', true);
        if (!empty($legacy_tracking_code)) {
            $tracking_numbers[] = $legacy_tracking_code;
        }

        $tracking_numbers = array_values(array_unique(array_filter(array_map('strval', $tracking_numbers))));

        return $tracking_numbers;
    }

    private function aggregate_order_events($events)
    {
        $priorities = array(
            'sender_received_return' => 100,
            'returning_to_sender' => 90,
            'delivered' => 80,
            'out_for_delivery' => 70,
            'in_transit' => 60,
            'picked_up' => 50,
            'data_sent' => 10,
        );

        $all_delivered = true;
        $selected_event = array();
        $selected_priority = -1;
        $selected_date = '';

        foreach ($events as $event) {
            $status = (string) ($event['status'] ?? '');
            if ($status !== 'delivered') {
                $all_delivered = false;
            }

            $priority = isset($priorities[$status]) ? (int) $priorities[$status] : 0;
            $event_date = (string) ($event['date'] ?? '');
            if ($priority > $selected_priority || ($priority === $selected_priority && strcmp($event_date, $selected_date) > 0)) {
                $selected_priority = $priority;
                $selected_date = $event_date;
                $selected_event = $event;
            }
        }

        if ($all_delivered && !empty($events)) {
            usort($events, function ($left, $right) {
                return strcmp((string) ($right['date'] ?? ''), (string) ($left['date'] ?? ''));
            });
            $selected_event = $events[0];
            $selected_event['status'] = 'delivered';
            $selected_event['label'] = __('Delivered', 'gls-shipping-for-woocommerce');
        }

        return $selected_event;
    }

    private function extract_latest_tracking_event($tracking_data)
    {
        $status_list = isset($tracking_data['ParcelStatusList']) && is_array($tracking_data['ParcelStatusList'])
            ? $tracking_data['ParcelStatusList']
            : array();

        if (empty($status_list)) {
            return array();
        }

        usort($status_list, function ($left, $right) {
            return strcmp($this->normalize_status_date((string) ($left['StatusDate'] ?? '')), $this->normalize_status_date((string) ($right['StatusDate'] ?? '')));
        });

        $latest = end($status_list);
        if (!is_array($latest)) {
            return array();
        }

        return $this->map_tracking_event($latest);
    }

    private function map_tracking_event($event)
    {
        $status_description = sanitize_text_field((string) ($event['StatusDescription'] ?? ''));
        $status_info = sanitize_text_field((string) ($event['StatusInfo'] ?? ''));
        $status_code = sanitize_text_field((string) ($event['StatusCode'] ?? ''));
        $location = sanitize_text_field((string) ($event['DepotCity'] ?? ''));
        $normalized = $this->normalize_text($status_description . ' ' . $status_info . ' ' . $status_code);

        $status = 'tracking_update';
        $label = $status_description !== '' ? $status_description : __('Tracking update', 'gls-shipping-for-woocommerce');

        $status_from_code = $this->map_tracking_status_code($status_code);
        if (!empty($status_from_code)) {
            $status = $status_from_code['status'];
            $label = $status_from_code['label'];
        } elseif ($this->contains_any($normalized, array('sender received', 'returned to sender completed', 'return completed', 'shipper received', 'received by sender'))) {
            $status = 'sender_received_return';
            $label = __('Returned to sender', 'gls-shipping-for-woocommerce');
        } elseif ($this->contains_any($normalized, array('return to sender', 'returning to sender', 'returned to sender', 'undeliverable', 'delivery failed', 'refused', 'back to sender'))) {
            $status = 'returning_to_sender';
            $label = __('Returning to sender', 'gls-shipping-for-woocommerce');
        } elseif ($this->contains_any($normalized, array('delivered', 'successful delivery', 'successfully delivered', 'signed by consignee', 'handed over to consignee'))) {
            $status = 'delivered';
            $label = __('Delivered', 'gls-shipping-for-woocommerce');
        } elseif ($this->contains_any($normalized, array('out for delivery', 'courier delivery', 'delivery route'))) {
            $status = 'out_for_delivery';
            $label = __('Out for delivery', 'gls-shipping-for-woocommerce');
        } elseif ($this->contains_any($normalized, array('in transit', 'transit', 'sorting', 'depot', 'hub', 'linehaul', 'transport'))) {
            $status = 'in_transit';
            $label = __('In transit', 'gls-shipping-for-woocommerce');
        } elseif ($this->contains_any($normalized, array('picked up', 'pickup', 'collected', 'accepted by courier', 'taken over'))) {
            $status = 'picked_up';
            $label = __('Picked up by courier', 'gls-shipping-for-woocommerce');
        }

        return array(
            'status' => $status,
            'status_code' => $status_code,
            'label' => $label,
            'description' => $status_info !== '' ? $status_info : $status_description,
            'date' => $this->normalize_status_date((string) ($event['StatusDate'] ?? '')),
            'location' => $location,
        );
    }

    private function map_tracking_status_code($status_code)
    {
        $status_code = trim((string) $status_code);
        if ($status_code === '') {
            return array();
        }

        $status_map = apply_filters('gls_shipping_tracking_status_code_map', array(
            'sender_received_return' => array(),
            'returning_to_sender' => array(),
            'delivered' => array(),
            'out_for_delivery' => array(),
            'in_transit' => array(),
            'picked_up' => array(),
            'data_sent' => array('51', '52'),
        ));

        foreach ($status_map as $mapped_status => $codes) {
            $codes = array_map('strval', is_array($codes) ? $codes : array());
            if (!in_array($status_code, $codes, true)) {
                continue;
            }

            return array(
                'status' => $mapped_status,
                'label' => $this->get_tracking_status_label($mapped_status),
            );
        }

        return array();
    }

    private function get_tracking_status_label($status)
    {
        switch ((string) $status) {
            case 'sender_received_return':
                return __('Returned to sender', 'gls-shipping-for-woocommerce');
            case 'returning_to_sender':
                return __('Returning to sender', 'gls-shipping-for-woocommerce');
            case 'delivered':
                return __('Delivered', 'gls-shipping-for-woocommerce');
            case 'out_for_delivery':
                return __('Out for delivery', 'gls-shipping-for-woocommerce');
            case 'in_transit':
                return __('In transit', 'gls-shipping-for-woocommerce');
            case 'picked_up':
                return __('Picked up by courier', 'gls-shipping-for-woocommerce');
            case 'data_sent':
                return __('Shipment data sent to GLS', 'gls-shipping-for-woocommerce');
            default:
                return __('Tracking update', 'gls-shipping-for-woocommerce');
        }
    }

    private function normalize_status_date($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return current_time('mysql');
        }

        if (preg_match('/Date\((\d+)/', $value, $matches)) {
            $timestamp = isset($matches[1]) ? (int) floor(((int) $matches[1]) / 1000) : 0;
            if ($timestamp > 0) {
                return gmdate('Y-m-d H:i:s', $timestamp);
            }
        }

        $timestamp = strtotime($value);
        if ($timestamp) {
            return gmdate('Y-m-d H:i:s', $timestamp);
        }

        return sanitize_text_field($value);
    }

    private function normalize_text($value)
    {
        $value = remove_accents(wp_strip_all_tags((string) $value));
        $value = strtolower($value);

        return trim((string) preg_replace('/\s+/', ' ', $value));
    }

    private function contains_any($haystack, $needles)
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && strpos($haystack, $this->normalize_text($needle)) !== false) {
                return true;
            }
        }

        return false;
    }
}

new GLS_Shipping_Workflow();
