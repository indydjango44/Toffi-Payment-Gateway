<?php
/**
 * WC_Toffi_Onramp_Gateway class
 *
 * @author   Kagami
 * @package  WooCommerce Toffi Onramp Gatway
 * @since    1.0.0
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}
class WC_toffi_onramp_gateway extends WC_Payment_Gateway
{
	public function __construct()
	{
		$this->setup_properties();
		$this->init_form_fields();
		$this->init_settings();

		$this->title = $this->get_option('title');
		$this->description = $this->get_option('description');

		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
	}

	protected function setup_properties() {
		$this->id = 'toffi-onramp-source';

		$image_path = plugin_dir_path(__FILE__) . 'assets/toffi_icon_onramp.png';

		// Upload the image to the Media Library and get the URL
		$image_url = toffi_onramp_upload_image_to_media_library($image_path, 'toffi_icon_onramp.png');

		if ($image_url) {
			// Use the image URL for the icon
			$this->icon = $image_url;
		}
		$this->has_fields         = false;
		$this->method_title = 'Toffi Onramp Payment Gateway';
		$this->method_description = 'Payment Gateway for processing payments through Crypto Onramps and more';
		$this->enabled = 'yes';

		$this->supports = array(
			'products'
		);
	}

	public function init_form_fields()
	{

		$this->form_fields = array(
			'enabled' => array(
				'title' => __('Enable/Disable', 'toffi-onramp-source'),
				'type' => 'checkbox',
				'label' => __('Enable Toffi Payment Gateway', 'toffi-onramp-source'),
				'default' => 'yes'
			),
			'title' => array(
				'title' => __('Title', 'toffi-onramp-source'),
				'type' => 'safe_text',
				'description' => __('This controls the title which the user sees during checkout.', 'toffi-onramp-source'),
				'default' => _x('Credit Cards through Toffi Crypto Onramps', 'Credit Cards through Toffi Crypto Onramps', 'toffi-onramp-source'),
				'desc_tip' => true,
			),
			'description' => array(
				'title' => __('Description', 'toffi-onramp-source'),
				'type' => 'textarea',
				'description' => __('Payment method description which the user sees during checkout.', 'toffi-onramp-source'),
				'default' => __('You will be redirected to a payment page where you will choose a Crypto Onramp to pay through.', 'toffi-onramp-source'),
				'desc_tip' => true,
			)
		);
	}

	public function payment_fields()
	{
		if ($this->description) {
			echo wpautop(wp_kses_post($this->description));
		}
		echo '<div><image src="data:image/png;base64,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" style="max-width:200px;"></div>';
	}

	public function process_payment($order_id)
	{
		$order = wc_get_order($order_id);
		wc_reduce_stock_levels($order_id);
		WC()->cart->empty_cart();
		return array(
			'result' => 'success',
			'redirect' => $this->get_return_url($order)
		);
	}

	public function thankyou_page()
	{
		if ($this->instructions) {
			echo wpautop(wptexturize($this->instructions));
		}
	}
}

function toffi_onramp_check_image_in_media_library($image_name)
{
	// Query the media library to check if the image exists
	$query = new WP_Query(array(
		'post_type' => 'attachment',
		'post_status' => 'inherit',
		'meta_query' => array(
			array(
				'key' => '_wp_attached_file',
				'value' => $image_name,
				'compare' => 'LIKE'
			)
		)
	));

	if ($query->have_posts()) {
		// Return the URL of the existing image
		return wp_get_attachment_url($query->posts[0]->ID);
	}

	return false;
}

function toffi_onramp_upload_image_to_media_library($image_path, $image_name)
{
	// First, check if the image already exists in the media library
	$existing_image_url = toffi_onramp_check_image_in_media_library($image_name);

	if ($existing_image_url) {
		// Return the existing image URL if it's already in the media library
		return $existing_image_url;
	}

	// If the image does not exist, proceed with the upload
	if (!file_exists($image_path)) {
		return false;
	}

	// Get the file type (MIME type)
	$filetype = wp_check_filetype(basename($image_path), null);

	// Prepare an array of file attributes
	$attachment = array(
		'guid' => wp_upload_dir()['url'] . '/' . basename($image_name),
		'post_mime_type' => $filetype['type'],
		'post_title' => sanitize_file_name($image_name),
		'post_content' => '',
		'post_status' => 'inherit'
	);

	// Upload the file to the uploads directory
	$upload_dir = wp_upload_dir();
	$upload_file = $upload_dir['path'] . '/' . basename($image_name);

	// Copy the image to the uploads directory
	if (!copy($image_path, $upload_file)) {
		return false;
	}

	// Insert the attachment into the WordPress Media Library
	$attach_id = wp_insert_attachment($attachment, $upload_file);

	// Generate metadata for the attachment and update it
	require_once(ABSPATH . 'wp-admin/includes/image.php');
	$attach_data = wp_generate_attachment_metadata($attach_id, $upload_file);
	wp_update_attachment_metadata($attach_id, $attach_data);

	// Return the attachment URL
	return wp_get_attachment_url($attach_id);
}
