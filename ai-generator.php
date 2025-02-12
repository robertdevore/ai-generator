<?php
/**
 * Plugin Name: AI-Powered Plugin
 * Description: A custom WordPress plugin that integrates AI APIs (like GPT‑4o) to generate post content via a button in the editor.
 * Version: 1.2.5
 * Author: Your Name
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Generate content using the GPT‑4o chat API.
 */
function ai_generate_content_via_api( $prompt ) {
    // Retrieve and sanitize the API key.
    $api_key = sanitize_text_field( get_option( 'ai_plugin_api_key' ) );
    if ( empty( $api_key ) ) {
        return 'API key not set. Please configure the API key in the AI Plugin Settings.';
    }

    // Use the chat completions endpoint.
    $endpoint = 'https://api.openai.com/v1/chat/completions';

    // Append instructions so the response is in Markdown format.
    $full_prompt = $prompt . "\n\nPlease respond in Markdown format using proper headings, bullet points, and other Markdown conventions. Do not wrap the answer in a code block.";

    // Prepare the API request with an increased timeout.
    $response = wp_remote_post( $endpoint, array(
        'timeout' => 30, // Timeout set to 30 seconds.
        'headers' => array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type'  => 'application/json',
        ),
        'body' => json_encode( array(
            'model'       => 'gpt-4o', // Adjust the model if needed.
            'messages'    => array(
                array(
                    'role'    => 'user',
                    'content' => $full_prompt,
                ),
            ),
            'max_tokens'  => 2048, // Increased token size.
        ) ),
    ) );

    if ( is_wp_error( $response ) ) {
        return 'Error: ' . $response->get_error_message();
    }

    $body = json_decode( wp_remote_retrieve_body( $response ), true );

    // Check if the API returned the expected structure.
    if ( ! isset( $body['choices'] ) || ! is_array( $body['choices'] ) || empty( $body['choices'] ) ) {
        $error_message = isset( $body['error']['message'] )
            ? $body['error']['message']
            : 'Unexpected API response: ' . print_r( $body, true );
        return 'Error: ' . $error_message;
    }

    // For chat completions, the generated text is in the "message" object.
    $generated_text = isset( $body['choices'][0]['message']['content'] )
        ? trim( $body['choices'][0]['message']['content'] )
        : '';

    return $generated_text;
}

/**
 * AJAX handler for generating AI content.
 */
function ai_ajax_generate_content() {
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( 'Unauthorized' );
    }

    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'ai_generate_content_nonce' ) ) {
        wp_send_json_error( 'Invalid nonce' );
    }

    $prompt = isset( $_POST['prompt'] ) ? sanitize_text_field( $_POST['prompt'] ) : '';
    if ( empty( $prompt ) ) {
        wp_send_json_error( 'No prompt provided' );
    }

    $generated = ai_generate_content_via_api( $prompt );
    if ( empty( $generated ) || strpos( $generated, 'Error:' ) === 0 ) {
        wp_send_json_error( $generated );
    }

    wp_send_json_success( array( 'content' => $generated ) );
}
add_action( 'wp_ajax_generate_ai_content', 'ai_ajax_generate_content' );

/**
 * Add meta box to the post editing screen.
 */
function ai_add_meta_box() {
    add_meta_box(
        'ai_content_generator',
        'AI Content Generator',
        'ai_meta_box_callback',
        'post',
        'side',
        'default'
    );
}
add_action( 'add_meta_boxes', 'ai_add_meta_box' );

/**
 * Render the meta box.
 */
function ai_meta_box_callback( $post ) {
    // Use nonce for security.
    wp_nonce_field( 'ai_generate_content_nonce', 'ai_generate_content_nonce_field' );

    // Set a default prompt based on the post title.
    $default_prompt = 'Write a blog post about ' . $post->post_title;
    // Allow the user to customize the prompt.
    $prompt = get_post_meta( $post->ID, '_ai_generate_prompt', true );
    if ( empty( $prompt ) ) {
        $prompt = $default_prompt;
    }
    ?>
    <p>
        <label for="ai_generate_prompt"><strong>Prompt:</strong></label>
        <input type="text" id="ai_generate_prompt" name="ai_generate_prompt" value="<?php echo esc_attr( $prompt ); ?>" style="width:100%;" />
    </p>
    <p>
        <button type="button" id="ai_generate_button" class="button button-primary">Generate AI Content</button>
    </p>
    <p id="ai_generate_status"></p>
    <?php
}

/**
 * Enqueue admin scripts for our meta box.
 */
function ai_plugin_enqueue_admin_scripts( $hook ) {
    // Only enqueue on post editing screens.
    if ( 'post.php' != $hook && 'post-new.php' != $hook ) {
        return;
    }
    // Enqueue Marked.js for Markdown parsing.
    wp_enqueue_script( 'marked', 'https://cdn.jsdelivr.net/npm/marked/marked.min.js', array(), '4.0.12', true );
    wp_enqueue_script( 'ai-plugin-script', plugin_dir_url( __FILE__ ) . 'ai-plugin.js', array( 'jquery', 'marked' ), '1.0', true );
    wp_localize_script( 'ai-plugin-script', 'aiPlugin', array(
        'ajaxurl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'ai_generate_content_nonce' ),
    ) );
}
add_action( 'admin_enqueue_scripts', 'ai_plugin_enqueue_admin_scripts' );

/**
 * Plugin Settings Page for API Key management.
 */
function ai_plugin_settings_page() {
    ?>
    <div class="wrap">
        <h1>AI Plugin Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields( 'ai_plugin_options_group' );
            do_settings_sections( 'ai-plugin' );
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

function ai_plugin_register_settings() {
    register_setting( 'ai_plugin_options_group', 'ai_plugin_api_key' );

    add_settings_section(
        'ai_plugin_main_section',
        'API Settings',
        null,
        'ai-plugin'
    );

    add_settings_field(
        'ai_plugin_api_key',
        'API Key',
        'ai_plugin_api_key_callback',
        'ai-plugin',
        'ai_plugin_main_section'
    );
}
add_action( 'admin_init', 'ai_plugin_register_settings' );

function ai_plugin_api_key_callback() {
    $api_key = get_option( 'ai_plugin_api_key' );
    echo '<input type="text" name="ai_plugin_api_key" value="' . esc_attr( $api_key ) . '" class="regular-text">';
    echo '<style>.spin {
  animation: spin 2s infinite linear;
}
@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}
</style>';
}

function ai_plugin_add_admin_menu() {
    add_menu_page( 'AI Plugin Settings', 'AI Plugin', 'manage_options', 'ai-plugin', 'ai_plugin_settings_page' );
}
add_action( 'admin_menu', 'ai_plugin_add_admin_menu' );
