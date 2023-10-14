<?php
/*
Plugin Name: Media SHA1 Extension
Description: Adds a SHA1 field to the media API endpoint and allows recalculation from the media edit page.
Version: 1.0.5
Author: Maciej Pondo
*/

// Add the SHA1 meta box to the media edit page
function add_sha1_meta_box() {
    add_meta_box(
        'sha1_meta_box', 
        'SHA1 Hash', 
        'display_sha1_meta_box', 
        'attachment', 
        'side', 
        'high'
    );
}
add_action('add_meta_boxes', 'add_sha1_meta_box');

// Populate the SHA1 meta box with the value and recalculate button
function display_sha1_meta_box($post) {
    $sha1 = get_post_meta($post->ID, 'sha1_hash', true);
    echo '<p><strong>SHA1:</strong></p>';
    echo '<p id="sha1_value">' . esc_html($sha1) . '</p>';
    echo '<button type="button" id="recalculate_sha1" data-postid="' . $post->ID . '">Recalculate SHA1</button>';
    echo '<span id="recalculate_status"></span>';
}

// Register SHA1 field for media endpoint
add_action('rest_api_init', 'register_sha1_for_media');
function register_sha1_for_media() {
    register_rest_field('attachment', 'sha1', [
        'get_callback' => 'get_media_sha1',
        'schema' => [
            'description' => 'SHA1 hash of the media file.',
            'type' => 'string',
            'context' => ['view', 'edit']
        ]
    ]);
}

function get_media_sha1($object) {
    return get_post_meta($object['id'], 'sha1_hash', true);
}

// Add SHA1 as a recognized public query variable
add_filter('query_vars', 'add_sha1_query_var');
function add_sha1_query_var($vars) {
    $vars[] = 'sha1';
    return $vars;
}

// Allow querying media by SHA1 hash using posts_clauses
add_action('pre_get_posts', 'extend_media_query_by_sha1');
function extend_media_query_by_sha1($query) {
    // Check if it's a REST API request
    if (defined('REST_REQUEST') && REST_REQUEST) {
        // Get the current route
        $current_route = $GLOBALS['wp']->query_vars['rest_route'];
        // Check if it's a request to the media endpoint
        if (strpos($current_route, '/wp/v2/media') !== false) {
            $sha1 = $query->get('sha1');
            if ($sha1) {
                $meta_query = [
                    [
                        'key' => 'sha1_hash',
                        'value' => $sha1,
                        'compare' => '='
                    ]
                ];
                $query->set('meta_query', $meta_query);
            }
        }
    }
}

// Utility function to calculate and save SHA1 hash for a specific media item
function save_sha1_for_media($post_id) {
    $file_path = get_attached_file($post_id);
    if ($file_path && file_exists($file_path)) {
        $hash = sha1_file($file_path);
        update_post_meta($post_id, 'sha1_hash', $hash);
    }
}

// On each media upload, calculate and save SHA1 hash
add_filter('wp_generate_attachment_metadata', 'save_sha1_for_media_after_upload', 10, 2);
function save_sha1_for_media_after_upload($metadata, $attachment_id) {
    save_sha1_for_media($attachment_id);
    return $metadata;
}

// JavaScript to handle the SHA1 recalculation from media edit page
add_action('admin_footer', 'add_admin_footer_script');
function add_admin_footer_script() {
    ?>
    <script>
        jQuery(document).ready(function($) {
            $('#recalculate_sha1').on('click', function() {
                var postID = $(this).data('postid');
                $('#recalculate_status').text('Recalculating...');
                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'recalculate_sha1',
                        post_id: postID
                    },
                    success: function(response) {
                        $('#sha1_value').text(response.data);
                        $('#recalculate_status').text('Recalculated successfully!');
                    },
                    error: function() {
                        $('#recalculate_status').text('Error recalculating SHA1.');
                    }
                });
            });
        });
    </script>
    <?php
}

// Handle the AJAX request to recalculate SHA1
add_action('wp_ajax_recalculate_sha1', 'handle_recalculate_sha1_ajax');
function handle_recalculate_sha1_ajax() {
    if (isset($_POST['post_id'])) {
        $post_id = intval($_POST['post_id']);
        save_sha1_for_media($post_id);
        $sha1 = get_post_meta($post_id, 'sha1_hash', true);
        wp_send_json_success($sha1);
    } else {
        wp_send_json_error();
    }
}

?>
