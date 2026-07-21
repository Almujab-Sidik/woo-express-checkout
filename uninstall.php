<?php
/**
 * Uninstall — bersihkan opsi plugin.
 *
 * Catatan: user & order yang sudah dibuat TIDAK dihapus.
 *
 * @package WEC
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'wec_version' );
delete_option( 'wec_block_notice_dismissed' );
