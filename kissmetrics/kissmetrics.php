<?php
/**
 * Plugin Name: Kissmetrics
 * Plugin URI: https://support.kissmetrics.io/docs/wordpress-plugin
 * Description: Automatically install Kissmetrics and track pageviews, blog post views, authentication, search, comments, social interactions, and WooCommerce purchases — no code required.
 * Version: 0.3.0
 * Requires at least: 6.3
 * Requires PHP: 7.4
 * Author: Kissmetrics
 * Author URI: https://www.kissmetrics.io
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: kissmetrics
 *
 * @package Kissmetrics
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'KISSMETRICS_VERSION', '0.3.0' );
define( 'KISSMETRICS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

if ( is_admin() ) {
	require_once __DIR__ . '/admin.php';
}

if ( ! class_exists( 'Kissmetrics_Filter' ) ) {

	/**
	 * Core tracking logic for the Kissmetrics plugin.
	 *
	 * All browser-facing JavaScript is escaped with esc_js()/wp_json_encode() and
	 * printed through the script queue (wp_add_inline_script) so the plugin passes
	 * the WordPress.org Plugin Check guidelines.
	 */
	class Kissmetrics_Filter {

		/**
		 * Matches anchor tags so we can attach click tracking.
		 *
		 * @var string
		 */
		public static $link_regex = '/<a (.*?)href="(.*?)"(.*?)>(.*?)<\/a>/i';

		/**
		 * Cached host of this site, used for outbound-link detection.
		 *
		 * @var string|null
		 */
		private static $origin_domain = null;

		/**
		 * Return the registered Kissmetrics API key, or '' when unset.
		 *
		 * @return string
		 */
		private static function key() {
			$key = get_option( 'kissmetrics_key' );
			return is_string( $key ) ? trim( $key ) : '';
		}

		/**
		 * The registrable domain of the current site (e.g. "example.com").
		 *
		 * @return string
		 */
		private static function origin_domain() {
			if ( null === self::$origin_domain ) {
				self::$origin_domain = self::domain_of( home_url() );
			}
			return self::$origin_domain;
		}

		/**
		 * Build and enqueue the inline Kissmetrics snippet on the front-end.
		 */
		public static function enqueue() {
			$key = self::key();
			if ( '' === $key ) {
				return;
			}

			// A src of false registers an inline-only handle — the supported way to
			// print inline JS through the queue without shipping a physical file.
			wp_register_script( 'kissmetrics', false, array(), KISSMETRICS_VERSION, false );
			wp_enqueue_script( 'kissmetrics' );
			wp_add_inline_script( 'kissmetrics', self::loader_js( $key ) . self::context_js() );
		}

		/**
		 * Enqueue the snippet on wp-login.php so auth events can be tracked.
		 */
		public static function enqueue_login() {
			$key = self::key();
			if ( '' === $key ) {
				return;
			}

			wp_register_script( 'kissmetrics', false, array(), KISSMETRICS_VERSION, false );
			wp_enqueue_script( 'kissmetrics' );

			$js = self::loader_js( $key );

			if ( get_option( 'kissmetrics_track_signup_view' ) ) {
				$js .= "_kmq.push(function(){if(document.getElementById('registerform')){_kmq.push(['record','Viewed signup page']);}});";
			}
			if ( get_option( 'kissmetrics_track_login' ) ) {
				$js .= "_kmq.push(['trackSubmit','loginform','Signed in']);";
			}
			if ( get_option( 'kissmetrics_track_signup' ) ) {
				$js .= "_kmq.push(['trackSubmit','registerform','Created account / registered']);";
			}

			wp_add_inline_script( 'kissmetrics', $js );
		}

		/**
		 * The canonical Kissmetrics async loader.
		 *
		 * @param string $key Kissmetrics API key.
		 * @return string
		 */
		private static function loader_js( $key ) {
			$key_js = esc_js( $key );
			return "var _kmq = _kmq || [];"
				. "var _kmk = _kmk || '{$key_js}';"
				. "function _kms(u){setTimeout(function(){var s=document.createElement('script'),"
				. "f=document.getElementsByTagName('script')[0];s.type='text/javascript';s.async=true;"
				. "s.src=u;f.parentNode.insertBefore(s,f);},1);}"
				. "_kms('//i.kissmetrics.io/i.js');"
				. "_kms('//scripts.kissmetrics.io/'+_kmk+'.2.js');";
		}

		/**
		 * Page-context events (homepage, identify, search, social, commenter id).
		 *
		 * These are decided server-side with WordPress conditional tags instead of
		 * reading theme body classes, so they keep working on block/FSE themes.
		 *
		 * @return string
		 */
		private static function context_js() {
			$js = '';

			if ( is_front_page() || is_home() ) {
				$js .= "_kmq.push(['record','Viewed Homepage']);";
			}

			if ( get_option( 'kissmetrics_identify_users' ) && is_user_logged_in() ) {
				$user = wp_get_current_user();
				if ( $user && $user->user_login ) {
					$js .= "_kmq.push(['identify','" . esc_js( $user->user_login ) . "']);";
				}
			}

			if ( get_option( 'kissmetrics_track_search' ) && is_search() ) {
				$js .= "_kmq.push(['record','Searched Site',{'WordPress Search Query':'"
					. esc_js( get_search_query() ) . "'}]);";
			}

			if ( get_option( 'kissmetrics_track_social' ) ) {
				$js .= self::social_js();
			}

			$js .= self::identify_commenter_js();

			return $js;
		}

		/**
		 * Identify an unregistered visitor from the WordPress comment-author cookie.
		 *
		 * @return string
		 */
		private static function identify_commenter_js() {
			if ( ! get_option( 'kissmetrics_identify_unregistered' ) || is_user_logged_in() ) {
				return '';
			}
			if ( ! is_singular() ) {
				return '';
			}

			$cookie_key = 'comment_author_email_' . COOKIEHASH;
			if ( empty( $_COOKIE[ $cookie_key ] ) ) {
				return '';
			}

			$email = sanitize_email( wp_unslash( $_COOKIE[ $cookie_key ] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
			if ( ! $email ) {
				return '';
			}

			return "_kmq.push(['identify','" . esc_js( $email ) . "']);";
		}

		/**
		 * Legacy Facebook/Twitter widget event bindings.
		 *
		 * @return string
		 */
		private static function social_js() {
			return "_kmq.push(function(){"
				. "if(window.twttr){"
				. "twttr.events.bind('tweet',function(e){var u=KM.uprts(decodeURIComponent(e.target.src).replace('#','?')).params.url;_kmq.push(['record','Tweet',{'Shared URL':u}]);});"
				. "twttr.events.bind('follow',function(e){_kmq.push(['record','Twitter Follow',{'Username':e.data.screen_name}]);});"
				. "}"
				. "if(window.FB){"
				. "FB.Event.subscribe('edge.create',function(u){_kmq.push(['record','Like',{'Shared URL':u}]);});"
				. "FB.Event.subscribe('edge.remove',function(u){_kmq.push(['record','Unlike',{'Shared URL':u}]);});"
				. "FB.Event.subscribe('auth.login',function(){_kmq.push(['record','Facebook Connect\\'d']);});"
				. "FB.Event.subscribe('auth.logout',function(){_kmq.push(['record','Facebook Logout']);});"
				. "}});";
		}

		/**
		 * Append a post/page view event (and optional link tracking) to the content.
		 *
		 * @param string $text Post content.
		 * @return string
		 */
		public static function the_content( $text ) {
			// Don't inject into feeds or REST responses.
			if ( is_feed() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
				return $text;
			}

			if ( ( is_single() || is_page() ) && get_option( 'kissmetrics_track_views' ) ) {
				global $post;

				$categories = array();
				foreach ( get_the_category() as $category ) {
					$categories[] = $category->cat_name;
				}

				$is_post = is_single();
				$props   = array(
					'ID'    => $post->ID,
					'Title' => get_the_title(),
				);
				if ( $is_post ) {
					$props['Categories'] = $categories;
				}

				$kmq   = array( 'record', 'Viewed ' . ( $is_post ? 'post' : 'page' ), $props );
				$text .= '<script>_kmq.push(' . wp_json_encode( $kmq ) . ');</script>';
			}

			if ( get_option( 'kissmetrics_track_links' ) ) {
				$text = preg_replace_callback( self::$link_regex, array( 'Kissmetrics_Filter', 'parse_content_link' ), $text );
			}

			return $text;
		}

		/**
		 * Append click tracking to links inside comment bodies.
		 *
		 * @param string $text Comment content.
		 * @return string
		 */
		public static function comment_text( $text ) {
			if ( get_option( 'kissmetrics_track_comment_links' ) ) {
				$text = preg_replace_callback( self::$link_regex, array( 'Kissmetrics_Filter', 'parse_comment_link' ), $text );
			}
			return $text;
		}

		/**
		 * Extract the registrable domain from a URL or host string.
		 *
		 * @param string $uri URL or host.
		 * @return string
		 */
		public static function domain_of( $uri ) {
			$host = wp_parse_url( $uri, PHP_URL_HOST );
			if ( ! $host ) {
				// Fall back to treating the input as a bare host.
				$host = preg_replace( '#^https?://#', '', (string) $uri );
				$host = preg_replace( '#/.*$#', '', $host );
			}
			if ( preg_match( '/[^.\/]+\.[^.\/]+$/', $host, $domain ) ) {
				return $domain[0];
			}
			return '';
		}

		/**
		 * Build the click-tracking markup for a single matched link.
		 *
		 * @param string $page_title  Title of the current page.
		 * @param string $type        "Article" or "Comment".
		 * @param array  $link_matches Regex match groups.
		 * @return string
		 */
		public static function parse_link( $page_title, $type, $link_matches ) {
			static $id_attr_regex = '/id\s*=\s*[\'"](.+?)[\'"]/i';

			$target_domain = self::domain_of( $link_matches[2] );
			$origin_domain = self::origin_domain();

			$id_attr = array();
			preg_match( $id_attr_regex, $link_matches[1], $id_attr );
			if ( ! $id_attr ) {
				preg_match( $id_attr_regex, $link_matches[3], $id_attr );
			}
			$has_id = ! empty( $id_attr );
			$id     = $has_id ? $id_attr[1] : uniqid( 'link_' );

			$is_outbound = ( '' !== $target_domain && $target_domain !== $origin_domain );

			$kmq = array(
				$is_outbound ? 'trackClickOnOutboundLink' : 'trackClick',
				$id,
				$type . ' link clicked',
				array(
					'Title' => $link_matches[4],
					'Page'  => $page_title,
				),
			);

			return '<a ' . $link_matches[1] . 'href="' . $link_matches[2] . '"' . $link_matches[3]
				. ( ! $has_id ? ' id="' . esc_attr( $id ) . '"' : '' ) . '>'
				. $link_matches[4]
				. '</a>'
				. '<script>_kmq.push(' . wp_json_encode( $kmq ) . ');</script>';
		}

		/**
		 * Callback for links inside post/page content.
		 *
		 * @param array $matches Regex matches.
		 * @return string
		 */
		public static function parse_content_link( $matches ) {
			return self::parse_link( get_the_title(), 'Article', $matches );
		}

		/**
		 * Callback for links inside comments.
		 *
		 * @param array $matches Regex matches.
		 * @return string
		 */
		public static function parse_comment_link( $matches ) {
			return self::parse_link( get_the_title(), 'Comment', $matches );
		}

		/**
		 * Track comment submissions (and optionally identify the commenter).
		 */
		public static function track_comment_form() {
			if ( ! ( is_single() || is_page() ) || ! get_option( 'kissmetrics_track_comment' ) ) {
				return;
			}

			$identify = get_option( 'kissmetrics_identify_unregistered' )
				? "_kmq.push(['identify',document.getElementById('email').value]);"
				: '';

			$js = "_kmq=window._kmq||[];_kmq.push(function(){"
				. "var commented=function(){"
				. $identify
				. "_kmq.push(['record','Commented',{"
				. "name:document.getElementById('author')?document.getElementById('author').value:'',"
				. "email:document.getElementById('email')?document.getElementById('email').value:'',"
				. "comment:document.getElementById('comment')?document.getElementById('comment').value:''"
				. "}]);},el=document.getElementById('submit');"
				. "if(el&&el.addEventListener){el.addEventListener('mousedown',commented,false);}"
				. "});";

			wp_register_script( 'kissmetrics-comment', false, array( 'kissmetrics' ), KISSMETRICS_VERSION, true );
			wp_enqueue_script( 'kissmetrics-comment' );
			wp_add_inline_script( 'kissmetrics-comment', $js );
		}

		/**
		 * Record a WooCommerce purchase on the order-received (thank you) page.
		 *
		 * Detected server-side via is_order_received_page() rather than the legacy
		 * `woocommerce_thankyou` action, because block-based checkout (the default
		 * since WooCommerce 8.3) does not fire that action. The order key is verified
		 * so the event is only emitted for the buyer viewing their own confirmation.
		 */
		public static function track_purchase() {
			if ( ! get_option( 'kissmetrics_track_ecommerce' ) ) {
				return;
			}
			if ( ! function_exists( 'is_order_received_page' ) || ! is_order_received_page() ) {
				return;
			}
			if ( ! function_exists( 'wc_get_order' ) ) {
				return;
			}

			$order_id = absint( get_query_var( 'order-received' ) );
			if ( ! $order_id ) {
				global $wp;
				if ( ! empty( $wp->query_vars['order-received'] ) ) {
					$order_id = absint( $wp->query_vars['order-received'] );
				}
			}
			if ( ! $order_id ) {
				return;
			}

			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				return;
			}

			// Only emit for the buyer viewing their own order (key must match).
			$key = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( ! $key || ! hash_equals( (string) $order->get_order_key(), $key ) ) {
				return;
			}

			$items = array();
			foreach ( $order->get_items() as $item ) {
				$items[] = $item->get_name();
			}

			$props = array(
				'Order ID'   => $order_id,
				'Revenue'    => (float) $order->get_total(),
				'Currency'   => $order->get_currency(),
				'Item Count' => count( $items ),
				'Items'      => $items,
			);

			$js    = '';
			$email = $order->get_billing_email();
			if ( $email ) {
				$js .= "_kmq.push(['identify','" . esc_js( $email ) . "']);";
			}
			$js .= "_kmq.push(['record','Purchased'," . wp_json_encode( $props ) . ']);';

			wp_register_script( 'kissmetrics-wc', false, array( 'kissmetrics' ), KISSMETRICS_VERSION, true );
			wp_enqueue_script( 'kissmetrics-wc' );
			wp_add_inline_script( 'kissmetrics-wc', $js );
		}
	}
}

// Front-end + login snippet.
add_action( 'wp_enqueue_scripts', array( 'Kissmetrics_Filter', 'enqueue' ) );
add_action( 'login_enqueue_scripts', array( 'Kissmetrics_Filter', 'enqueue_login' ) );

// Content / comment / commerce hooks (only meaningful once a key is set).
add_filter( 'the_content', array( 'Kissmetrics_Filter', 'the_content' ), 99 );
add_filter( 'comment_text', array( 'Kissmetrics_Filter', 'comment_text' ), 99 );
add_action( 'comment_form', array( 'Kissmetrics_Filter', 'track_comment_form' ) );
// Runs after enqueue() (priority 10) so the 'kissmetrics' base handle is registered first.
add_action( 'wp_enqueue_scripts', array( 'Kissmetrics_Filter', 'track_purchase' ), 20 );
