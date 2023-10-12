<?php
/*
Plugin Name: Media SHA1 Extension
Description: Adds a sha1 field to the media API endpoint and ensures all current files have generated SHA1 when the plugin is activated.
Version: 1.0.3
Author: Maciej Pondo
*/

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



// On activation, calculate and save SHA1 hashes for existing media files
register_activation_hook(__FILE__, 'save_sha1_for_all_existing_media');
function save_sha1_for_all_existing_media() {
    $args = [
        'post_type' => 'attachment',
        'posts_per_page' => -1,
        'post_status' => 'any',
    ];

    $attachments = get_posts($args);
    foreach ($attachments as $attachment) {
        save_sha1_for_media($attachment->ID);
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
add_action('add_attachment', 'save_sha1_for_media');
add_action('update_attached_file', 'save_sha1_for_media');