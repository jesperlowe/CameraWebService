<?php
/**
 * Plugin Name: Camera Snapshot
 * Description: Modtager kamerabilledfiler fra CameraWebService og eksponerer dem via shortcode.
 */
if (!defined('ABSPATH')) exit;

function cs_get_token() { return get_option('cs_bearer_token', ''); }
function cs_set_token($token) { update_option('cs_bearer_token', $token); }

add_action('admin_menu', function() {
  add_options_page('Camera Snapshot', 'Camera Snapshot', 'manage_options', 'camera-snapshot', 'cs_admin_page');
});

function cs_admin_page() {
  if (isset($_POST['cs_token'])) cs_set_token(sanitize_text_field($_POST['cs_token']));
  if (isset($_POST['cs_generate'])) cs_set_token(wp_generate_password(48, false, false));
  $token = esc_html(cs_get_token());
  echo '<div class="wrap"><h1>Camera Snapshot</h1><form method="post">';
  echo '<input type="text" name="cs_token" value="'.$token.'" size="60"> ';
  echo '<button class="button button-primary">Gem token</button> ';
  echo '<button class="button" name="cs_generate" value="1">Generér token</button>';
  echo '</form></div>';
}

add_action('rest_api_init', function() {
  register_rest_route('camera-snapshot/v1', '/upload', [
    'methods' => 'POST',
    'callback' => 'cs_upload_cb',
    'permission_callback' => '__return_true',
  ]);
});

function cs_upload_cb($request) {
  $hdr = $request->get_header('authorization');
  $expected = 'Bearer '.cs_get_token();
  if (!$hdr || !hash_equals($expected, $hdr)) return new WP_REST_Response(['error'=>'unauthorized'], 401);
  $body = $request->get_body();
  if (!$body) return new WP_REST_Response(['error'=>'empty body'], 400);

  // Extract filename from Content-Disposition header (sent by CameraWebService).
  // Falls back to latest.jpg so single-camera setups keep working unchanged.
  $disposition = $request->get_header('content-disposition');
  $filename = 'latest.jpg';
  if ($disposition && preg_match('/filename="([^"]+)"/', $disposition, $m)) {
    $candidate = sanitize_file_name($m[1]);
    // Only allow safe JPEG filenames: word chars, hyphens, digits + .jpg/.jpeg
    if (preg_match('/^[\w\-]+\.jpe?g$/i', $candidate)) {
      $filename = $candidate;
    }
  }

  $upload = wp_upload_dir();
  $dir = trailingslashit($upload['basedir']).'camera-snapshot';
  if (!file_exists($dir)) wp_mkdir_p($dir);
  file_put_contents(trailingslashit($dir).$filename, $body);
  return ['ok'=>true, 'url'=>trailingslashit($upload['baseurl']).'camera-snapshot/'.$filename];
}

/**
 * Shortcode: [camera_snapshot] or [camera_snapshot file="camera2.jpg"]
 *
 * The optional "file" attribute lets you display a specific camera's image
 * when using the multi-camera setup in CameraWebService. Defaults to latest.jpg.
 */
add_shortcode('camera_snapshot', function($atts) {
  $atts = shortcode_atts(['file' => 'latest.jpg'], $atts, 'camera_snapshot');
  $filename = sanitize_file_name($atts['file']);
  // Reject filenames that don't look like safe JPEG names
  if (!preg_match('/^[\w\-]+\.jpe?g$/i', $filename)) {
    $filename = 'latest.jpg';
  }
  $upload = wp_upload_dir();
  $url = trailingslashit($upload['baseurl']).'camera-snapshot/'.esc_attr($filename).'?t='.time();
  return '<img src="'.esc_url($url).'" alt="Camera Snapshot" style="max-width:100%;height:auto;" />';
});
