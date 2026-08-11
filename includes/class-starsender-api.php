<?php
/**
 * Star Sender API Wrapper.
 *
 * @package WEC
 */

namespace WEC;

if (! defined('ABSPATH')) {
    exit;
}

class StarSender_API
{
    private $api_key;
    private $endpoint = 'https://api.starsender.online/api/send';

    public function __construct($api_key = '')
    {
        $this->api_key = $api_key ?: get_option('wec_starsender_api_key', '');
    }

    /**
     * Send WhatsApp message.
     *
     * @param string $to      Phone number (08xxx or 62xxx).
     * @param string $message Message body.
     * @param int    $delay   Delay in seconds.
     * @return array|\WP_Error
     */
    public function send_message($to, $message, $delay = 0)
    {
        if (empty($this->api_key)) {
            return new \WP_Error('no_api_key', __('Star Sender API key is not configured.', 'woo-express-checkout'));
        }

        // Format phone number
        $to = $this->format_phone($to);

        $body = array(
            'messageType' => 'text',
            'to'          => $to,
            'body'        => $message,
        );

        if ($delay > 0) {
            $body['delay'] = intval($delay);
        }

        $args = array(
            'method'  => 'POST',
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Authorization' => $this->api_key,
            ),
            'body'    => json_encode($body),
            'timeout' => 30,
        );

        $response = wp_remote_post($this->endpoint, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = json_decode(wp_remote_retrieve_body($response), true);

        if ($response_code !== 200) {
            return new \WP_Error(
                'api_error',
                sprintf(
                    __('Star Sender API error: %s', 'woo-express-checkout'),
                    isset($response_body['message']) ? $response_body['message'] : 'Unknown error'
                )
            );
        }

        return $response_body;
    }

    /**
     * Format phone number to international format.
     */
    private function format_phone($phone)
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Convert 08xxx to 628xxx
        if (substr($phone, 0, 1) === '0') {
            $phone = '62' . substr($phone, 1);
        }

        return $phone;
    }

    /**
     * Check if API is configured.
     */
    public function is_configured()
    {
        return ! empty($this->api_key);
    }
}
