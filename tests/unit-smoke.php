<?php
/**
 * Standalone smoke test for the Kissmetrics WP plugin v0.3.0.
 * Stubs the WordPress functions the plugin uses, loads the real plugin file,
 * drives Kissmetrics_Filter, and asserts the emitted JS is correct + escaped.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

$PLUGIN = __DIR__ . '/../kissmetrics/kissmetrics.php';

// ---- test harness state -------------------------------------------------
$GLOBALS['wp_options']   = array();
$GLOBALS['wp_state']     = array();
$GLOBALS['captured_js']  = '';

define('ABSPATH', '/tmp/');            // plugin exits without this
define('COOKIEHASH', 'testhash');

// ---- minimal WordPress stubs -------------------------------------------
function is_admin() { return false; }
function add_action(...$a) {}
function add_filter(...$a) {}
function plugin_dir_url($f) { return 'http://example.com/wp-content/plugins/kissmetrics/'; }

function get_option($k) { return $GLOBALS['wp_options'][$k] ?? false; }
function home_url() { return 'https://example.com'; }
function wp_parse_url($url, $component = -1) { return parse_url($url, $component); }

function is_front_page()    { return !empty($GLOBALS['wp_state']['front_page']); }
function is_home()          { return !empty($GLOBALS['wp_state']['home']); }
function is_search()        { return !empty($GLOBALS['wp_state']['search']); }
function is_singular()      { return !empty($GLOBALS['wp_state']['singular']); }
function is_single()        { return !empty($GLOBALS['wp_state']['single']); }
function is_page()          { return !empty($GLOBALS['wp_state']['page']); }
function is_feed()          { return false; }
function is_user_logged_in(){ return !empty($GLOBALS['wp_state']['logged_in']); }
function get_search_query() { return $GLOBALS['wp_state']['search_query'] ?? ''; }
function get_the_title()    { return $GLOBALS['wp_state']['title'] ?? 'Test Title'; }
function get_the_category() { return array(); }

function wp_get_current_user() {
    return (object) array('user_login' => $GLOBALS['wp_state']['user_login'] ?? '');
}

function wp_unslash($v)         { return $v; }
function sanitize_email($e)     { return filter_var($e, FILTER_VALIDATE_EMAIL) ?: ''; }
function sanitize_text_field($s) { return trim(strip_tags((string) $s)); }

function wp_register_script(...$a) {}
function wp_enqueue_script(...$a) {}
function wp_add_inline_script($handle, $js) { $GLOBALS['captured_js'] .= $js; }

function is_order_received_page() { return !empty($GLOBALS['wp_state']['order_received']); }
function get_query_var($k) { return ($k === 'order-received') ? ($GLOBALS['wp_state']['order_id'] ?? 0) : ''; }
function absint($v) { return abs((int) $v); }

// Faithful-enough esc_js: escape backslash, quotes, newlines.
function esc_js($text) {
    $s = str_replace('\\', '\\\\', (string) $text);
    $s = str_replace("'", "\\'", $s);
    $s = str_replace('"', '\\"', $s);
    $s = str_replace(array("\r", "\n"), array('', '\\n'), $s);
    return $s;
}
function wp_json_encode($data) { return json_encode($data); }
function esc_attr($t) { return htmlspecialchars((string) $t, ENT_QUOTES); }

// ---- load the real plugin ----------------------------------------------
require $PLUGIN;

// ---- tiny assert framework ---------------------------------------------
$pass = 0; $fail = 0;
function check($name, $cond) {
    global $pass, $fail;
    if ($cond) { $pass++; echo "  \033[32mPASS\033[0m  $name\n"; }
    else       { $fail++; echo "  \033[31mFAIL\033[0m  $name\n"; }
}
function reset_capture() { $GLOBALS['captured_js'] = ''; }

echo "Kissmetrics plugin v0.3.0 — smoke test\n\n";

// 1. No key -> no output
reset_capture();
$GLOBALS['wp_options'] = array();
Kissmetrics_Filter::enqueue();
check('no API key => emits nothing', $GLOBALS['captured_js'] === '');

// 2. Loader + homepage + escaped identify
reset_capture();
$GLOBALS['wp_options'] = array(
    'kissmetrics_key'             => 'TESTKEY123',
    'kissmetrics_identify_users'  => 1,
);
$GLOBALS['wp_state'] = array(
    'front_page' => true,
    'logged_in'  => true,
    'user_login' => "o'brien",   // quote MUST be escaped or the JS breaks
);
Kissmetrics_Filter::enqueue();
$js = $GLOBALS['captured_js'];
check('loader contains escaped API key', strpos($js, "_kmk = _kmk || 'TESTKEY123'") !== false);
check('loads i.js + .2.js',
    strpos($js, "//i.kissmetrics.io/i.js") !== false &&
    strpos($js, "//scripts.kissmetrics.io/'+_kmk+'.2.js") !== false);
check('records Viewed Homepage (server-side, no body-class)', strpos($js, "'Viewed Homepage'") !== false);
check("identify single-quote is escaped (o\\'brien)", strpos($js, "o\\'brien") !== false);
check('no raw unescaped quote breakout', strpos($js, "['identify','o'brien']") === false);

// 3. Search event from server-side is_search()
reset_capture();
$GLOBALS['wp_options'] = array('kissmetrics_key' => 'K', 'kissmetrics_track_search' => 1);
$GLOBALS['wp_state']   = array('search' => true, 'search_query' => 'red shoes');
Kissmetrics_Filter::enqueue();
check('Searched Site event with query', strpos($GLOBALS['captured_js'], "'Searched Site',{'WordPress Search Query':'red shoes'}") !== false);

// 4. Outbound-link detection (THE $origin bug fix)
$internal = array('<a href="x">t</a>', '', 'https://example.com/post', '', 'Internal');
$external = array('<a href="x">t</a>', '', 'https://google.com/x',    '', 'External');
$in  = Kissmetrics_Filter::parse_link('Page', 'Article', $internal);
$out = Kissmetrics_Filter::parse_link('Page', 'Article', $external);
check('internal link => trackClick',                 strpos($in, '"trackClick"') !== false && strpos($in, 'OutboundLink') === false);
check('external link => trackClickOnOutboundLink',   strpos($out, 'trackClickOnOutboundLink') !== false);

// 5. Post view event appended to content
$GLOBALS['wp_options'] = array('kissmetrics_track_views' => 1);
$GLOBALS['wp_state']   = array('single' => true, 'title' => 'Hello');
$GLOBALS['post']       = (object) array('ID' => 42);
$content = Kissmetrics_Filter::the_content('<p>body</p>');
check('the_content appends Viewed post event with ID',
    strpos($content, '"Viewed post"') !== false && strpos($content, '"ID":42') !== false);

// 6. WooCommerce purchase event (stubbed WC order, server-side order-received detection)
function wc_get_order($id) {
    return new class {
        public function get_items() {
            $a = new class { public function get_name() { return 'Test Widget'; } };
            $b = new class { public function get_name() { return 'Gadget'; } };
            return array( $a, $b );
        }
        public function get_total()         { return '50.00'; }
        public function get_currency()       { return 'USD'; }
        public function get_billing_email()  { return 'buyer@example.com'; }
        public function get_order_key()      { return 'wc_order_KEY123'; }
    };
}
reset_capture();
$GLOBALS['wp_options'] = array( 'kissmetrics_track_ecommerce' => 1 );
$GLOBALS['wp_state']   = array( 'order_received' => true, 'order_id' => 1234 );
$_GET['key'] = 'wc_order_KEY123';
Kissmetrics_Filter::track_purchase();
$wc = $GLOBALS['captured_js'];
check('WooCommerce: records Purchased event',      strpos($wc, "['record','Purchased',") !== false);
check('WooCommerce: revenue is numeric (50)',      strpos($wc, '"Revenue":50') !== false);
check('WooCommerce: currency + order id present',  strpos($wc, '"Currency":"USD"') !== false && strpos($wc, '"Order ID":1234') !== false);
check('WooCommerce: item names captured',          strpos($wc, '"Items":["Test Widget","Gadget"]') !== false);
check('WooCommerce: buyer identified by email',    strpos($wc, "['identify','buyer@example.com']") !== false);

// 7. ecommerce option OFF => no purchase event
reset_capture();
$GLOBALS['wp_options'] = array( 'kissmetrics_track_ecommerce' => 0 );
Kissmetrics_Filter::track_purchase();
check('WooCommerce: nothing emitted when option off', $GLOBALS['captured_js'] === '');

// 8. Wrong order key => no event (don't leak another buyer's order data)
reset_capture();
$GLOBALS['wp_options'] = array( 'kissmetrics_track_ecommerce' => 1 );
$GLOBALS['wp_state']   = array( 'order_received' => true, 'order_id' => 1234 );
$_GET['key'] = 'wrong-key';
Kissmetrics_Filter::track_purchase();
check('WooCommerce: no event when order key mismatches', $GLOBALS['captured_js'] === '');

// 9. Not on order-received page => no event
reset_capture();
$GLOBALS['wp_options'] = array( 'kissmetrics_track_ecommerce' => 1 );
$GLOBALS['wp_state']   = array( 'order_received' => false, 'order_id' => 1234 );
$_GET['key'] = 'wc_order_KEY123';
Kissmetrics_Filter::track_purchase();
check('WooCommerce: no event off the order-received page', $GLOBALS['captured_js'] === '');

echo "\n----------------------------------------\n";
echo "  $pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
