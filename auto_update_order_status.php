<?php
/*
Plugin Name: Auto Update Order Status
Plugin URI:  https://alexandretouzet.com
Description: Automatically switches order status from "Pending" to "Processing" based on the delivery date.
Version:     1.2
Author:      Alexandre Touzet
Author URI:  https://alexandretouzet.com/
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

/**
 * Register the cron job to automatically update order status
 */
add_action('wp', 'register_auto_update_order_status_cron');
function register_auto_update_order_status_cron() {
    if (!wp_next_scheduled('auto_update_order_status_cron')) {
        wp_schedule_event(time(), 'hourly', 'auto_update_order_status_cron');
    }
}

add_action('auto_update_order_status_cron', 'auto_update_order_status_cron_callback');
/**
 * Callback function for the cron job to update order status
 */
function auto_update_order_status_cron_callback() {
    // Get all pending orders directly using wc_get_orders
    $args = array(
        'status' => 'pending',
        'limit' => -1,
        'return' => 'ids',
        'type' => 'shop_order',
    );

    $order_ids = wc_get_orders($args);

    // Iterate through each pending order
    foreach ($order_ids as $order_id) {
        $order = wc_get_order($order_id);

        // Check if order status is "Pending"
        $order_status = $order->get_status();
        if ('pending' === $order_status) {
            $orddd_class = new orddd_common();
            $delivery_date = $orddd_class->orddd_get_order_delivery_date($order_id);
            $time_slot = $orddd_class->orddd_get_order_timeslot($order_id);

            // Log delivery_date and time_slot values
            log_to_error_log('Order ID: ' . $order_id . ', Delivery Date: ' . $delivery_date . ', Time Slot: ' . $time_slot);

            // Check if delivery date and time slot are available
            if ($delivery_date && $time_slot) {
                // Define the replacement time range for "As Soon As Possible"
                $asap_time_range = '01:00 - 01:30';

                // Parse the delivery date
                $delivery_date_parts = explode(' ', $delivery_date);
                $day = intval($delivery_date_parts[1]);
                $month = convertFrenchMonthToNumber($delivery_date_parts[2]);
                $year = intval($delivery_date_parts[3]);

                // Parse the time slot
                $time_slot_parts = strpos($time_slot, '-') !== false ? explode(' - ', $time_slot) : explode(' - ', $asap_time_range);
                $start_time_parts = explode(':', $time_slot_parts[0]);
                $start_hour = intval($start_time_parts[0]);
                $start_minute = intval($start_time_parts[1]);

                // Create a new DateTime object with the parsed date and time
                $delivery_datetime = new DateTime();
                $delivery_datetime->setDate($year, $month, $day);
                $delivery_datetime->setTime($start_hour, $start_minute);

                // Get the delivery timestamp
                $delivery_timestamp = $delivery_datetime->getTimestamp();

                // Log the calculated delivery date
                log_to_error_log('Order ID: ' . $order_id . ', Calculated Delivery Date: ' . date('Y-m-d H:i:s', $delivery_timestamp));

                // If the time difference is less than or equal to 24 hours (86400 seconds) and the delivery time is in the future, update order status to "Processing"
                $time_difference = $delivery_timestamp - time();
                if ((($time_difference <= 86400 && $time_difference > 0) || ($time_difference <= 0 && $time_difference > -86400)) && 'pending' === $order_status) {
                    if ('pending' === $order_status) {
                        $order->update_status('processing');
                    }
                }
            }
        }
    }
}

// Logging function to log messages to the error log
function log_to_error_log($message)
{
    error_log('[Auto Update Order Status Plugin] ' . $message);
}


// Helper function to convert French month name to a number
function convertFrenchMonthToNumber($month)
{
    $frenchMonths = array(
        'janvier' => 1,
        'février' => 2,
        'mars' => 3,
        'avril' => 4,
        'mai' => 5,
        'juin' => 6,
        'juillet' => 7,
        'août' => 8,
        'septembre' => 9,
        'octobre' => 10,
        'novembre' => 11,
        'décembre' => 12,
    );

    return $frenchMonths[strtolower($month)] ?? 1; // Default to 1 (January) if month not found
}
