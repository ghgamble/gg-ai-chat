<?php
/**
 * Plugin Name: GG AI Chat
 * Description: Lightweight ChatGPT-style chatbot for WordPress (with conversation history).
 * Version: 1.1
 * Author: Grace Gamble
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Register scripts & styles
function gg_ai_chat_enqueue() {
    wp_enqueue_style( 'gg-ai-chat-css', plugin_dir_url( __FILE__ ) . 'css/gg-ai-chat.css' );
    wp_enqueue_script( 'gg-ai-chat-js', plugin_dir_url( __FILE__ ) . 'js/gg-ai-chat.js', [], false, true );

    wp_localize_script( 'gg-ai-chat-js', 'ggAiChat', [
        'restUrl' => esc_url( rest_url( 'gg-ai-chat/v1/message' ) ),
        'nonce'   => wp_create_nonce( 'wp_rest' )
    ]);
}
add_action( 'wp_enqueue_scripts', 'gg_ai_chat_enqueue' );

// Register REST endpoint
add_action( 'rest_api_init', function() {
    register_rest_route( 'gg-ai-chat/v1', '/message', [
        'methods'  => 'POST',
        'callback' => 'gg_ai_chat_response',
        'permission_callback' => '__return_true',
    ]);
});

/**
 * Build OpenAI chat request with sanitized persistent history.
 */
function gg_ai_chat_response( WP_REST_Request $request ) {
    // Optional nonce check (keeps your previous permissive callback)
    $nonce = $request->get_header( 'X-WP-Nonce' );
    if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
        // Comment this out if you truly want it public:
        return wp_send_json_error( [ 'error' => 'Invalid nonce.' ], 403 );
    }

    $body    = json_decode( $request->get_body(), true ) ?: [];
    $message = isset( $body['message'] ) ? sanitize_text_field( $body['message'] ) : '';
    $history = isset( $body['history'] ) && is_array( $body['history'] ) ? $body['history'] : [];

    if ( $message === '' ) {
        return wp_send_json_error( [ 'error' => 'Empty message.' ], 400 );
    }

    // Sanitize history: allow only user/assistant roles and plain text content
    $clean = [];
    foreach ( $history as $turn ) {
        $role = isset( $turn['role'] ) ? strtolower( (string) $turn['role'] ) : '';
        if ( $role !== 'user' && $role !== 'assistant' ) continue;

        $content = isset( $turn['content'] ) ? (string) $turn['content'] : '';
        // Strip tags to avoid HTML injection (front-end can render safely)
        $content = wp_strip_all_tags( $content );
        if ( $content === '' ) continue;

        $clean[] = [ 'role' => $role, 'content' => $content ];
    }

    // Keep last N turns to control token usage
    $clean = array_slice( $clean, -20 );

    // Build messages: system + sanitized history + new user message
    $messages = array_merge(
        [
            [
                'role'    => 'system',
                'content' => 'You are a friendly website assistant for GG Dev. Answer briefly, conversationally, and helpfully.'
            ]
        ],
        $clean,
        [
            [ 'role' => 'user', 'content' => $message ]
        ]
    );

    // API key
    $api_key = defined( 'OPENAI_API_KEY' ) ? OPENAI_API_KEY : '';
    if ( empty( $api_key ) ) {
        return wp_send_json_error( [ 'error' => 'Missing OPENAI_API_KEY in wp-config.php.' ], 500 );
    }

    // Call OpenAI
    $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
        'headers' => [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $api_key,
        ],
        'body'    => wp_json_encode( [
            'model'       => 'gpt-4o-mini',
            'messages'    => $messages,
            'temperature' => 0.7,
            // 'max_tokens' => 400, // optional
        ] ),
        'timeout' => 30,
    ] );

    if ( is_wp_error( $response ) ) {
        return wp_send_json_error( [ 'error' => $response->get_error_message() ], 500 );
    }

    $code = wp_remote_retrieve_response_code( $response );
    $data = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( $code < 200 || $code >= 300 ) {
        $msg = isset( $data['error']['message'] ) ? $data['error']['message'] : 'OpenAI error (HTTP ' . $code . ').';
        return wp_send_json_error( [ 'error' => $msg ], $code );
    }

    $reply = $data['choices'][0]['message']['content'] ?? 'Sorry, I couldn’t get a response.';
    return wp_send_json_success( [ 'reply' => $reply ] );
}
