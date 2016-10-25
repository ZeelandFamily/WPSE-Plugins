<?php
/**
 * Plugin Name: ZF Author Archive Disabler
 * Plugin URI: http://wordpress.stackexchange.com/q/74924/6035
 * Description: Disable author archives with the click of a checkbox.
 * Author: Christopher Davis, Daniel Koskinen, Zeeland Family
 * Author URI: http://zeelandfamily.fi
 * License: MIT.
 *
 * Copyright (c) 2012 Christopher Davis <http://christopherdavis.me>
 * Copyright (c) 2016 Daniel Koskinen <http://zeelandfamily.fi>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a
 * copy of this software and associated documentation files (the "Software"),
 * to deal in the Software without restriction, including without limitation
 * the rights to use, copy, modify, merge, publish, distribute, sublicense,
 * and/or sell copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER
 * DEALINGS IN THE SOFTWARE.
 *
 * @category    WordPress
 *
 * @author      Christopher Davis <http://christopherdavis.me>, Daniel Koskinen <http://zeelandfamily.fi>
 * @copyright   2012 Christopher Davis, 2016 Daniel Koskinen
 * @license     http://opensource.org/licenses/MIT MIT
 * @package 	ZF_Author_Archive_Disabler
 */

! defined( 'ABSPATH' ) && exit;

ZF_Author_Archive_Disabler::init();

/**
 * Main class for plugin
 */
class ZF_Author_Archive_Disabler {
	// meta key that will store the disabled status.
	const KEY = '_author_archive_disabled';

	// nonce name.
	const NONCE = 'author_archive_nonce';

	/**
	 * Instance
	 *
	 * @var null
	 */
	private static $ins = null;

	/**
	 * Get the instance
	 *
	 * @return object
	 */
	public static function instance() {
		is_null( self::$ins ) && self::$ins = new self();

		return self::$ins;
	}

	/**
	 * Setup
	 */
	public static function init() {
		add_action( 'plugins_loaded', array( self::instance(), '_setup' ) );
	}

	/**
	 * Helper to see if the archive is disabled
	 *
	 * @param  integer $user_id A user ID.
	 * @return boolean
	 */
	public static function is_disabled( $user_id ) {
		return 'on' == get_user_meta( $user_id, self::KEY, true );
	}

	/**
	 * Adds actions and such
	 */
	public function _setup() {
		add_action( 'edit_user_profile', array( $this, 'field' ) );
		add_action( 'show_user_profile', array( $this, 'field' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save' ) );
		add_action( 'personal_options_update', array( $this, 'save' ) );
		add_action( 'template_redirect', array( $this, 'maybe_disable' ) );
		add_filter( 'author_link', array( $this, 'change_link' ), 10, 2 );
	}

	/**
	 * Output the html for the Disable Archive field
	 * @param  object $user A User object.
	 */
	public function field( $user ) {
		// only let admins do this.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		echo '<h4>', __( 'Disable Archive', 'author-archive-disabler' ), '</h4>';

		wp_nonce_field( self::NONCE . $user->ID, self::NONCE, false );

		printf(
			'<label for="%1$s"><input type="checkbox" name="%1$s" id="%1$s" value="on" %2$s /> %3$s</label>',
			esc_attr( self::KEY ),
			checked( get_user_meta( $user->ID, self::KEY, true ), 'on', false ),
			__( 'Disable Author Archive', 'author-archive-disabler' )
		);
	}

	/**
	 * Hook into user save.
	 * @param  [type] $user_id [description]
	 * @return [type]          [description]
	 */
	public function save( $user_id ) {
		if (
			! isset( $_POST[ self::NONCE ] ) ||
			! wp_verify_nonce( $_POST[ self::NONCE ], self::NONCE . $user_id )
		) {
			return;
		} // nonce is no good, bail

		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		} // current user can't edit this user, bail

		update_user_meta( $user_id, self::KEY, ! empty( $_POST[ self::KEY ] ) ? 'on' : 'off' );
	}

	/**
	 * Maybe disable an author archive.
	 */
	public function maybe_disable() {
		global $wp_query;

		// not an author archive? bail.
		if ( ! is_author() ) {
			return;
		}

		if ( self::is_disabled( get_queried_object_id() ) ) {
			$wp_query->set_404();
		}
	}

	/**
	 * Link to home instead of author archive for disabled authors
	 *
	 * @param  string $link     Author archive link
	 * @param  int $author_id 	User ID
	 * @return string           Filtered archive link
	 */
	public function change_link( $link, $author_id ) {
		if ( self::is_disabled( $author_id ) ) {
			return apply_filters( 'author_archive_disabler_default_url', home_url(), $author_id );
		}

		return $link;
	}
}
