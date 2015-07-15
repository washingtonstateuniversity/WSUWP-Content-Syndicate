<?php

/**
 * A base class for WSU syndicate shortcodes.
 *
 * Class WSU_Syndicate_Shortcode_Base
 */
class WSU_Syndicate_Shortcode_Base {
	/**
	 * A common constructor that initiates the shortcode.
	 */
	public function construct() {
		$this->add_shortcode();
	}

	/**
	 * Required to add a shortcode definition.
	 */
	public function add_shortcode() {}

	/**
	 * Required to display the content of a shortcode.
	 *
	 * @param array $atts A list of attributes assigned to the shortcode.
	 *
	 * @return string Final output for the shortcode.
	 */
	public function display_shortcode( $atts ) {
		return '';
	}
}