<?php

/**
 * Shared order summary content for the desktop sidebar and mobile accordion.
 *
 * Renders the standard WooCommerce review order table (product lines + totals)
 * which is required for AJAX fragment updates. The surrounding card/heading is
 * owned by the calling template.
 *
 * @package WEC
 */

if (! defined('ABSPATH')) {
    exit;
}

woocommerce_order_review();
