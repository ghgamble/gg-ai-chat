<?php
/**
 * Plugin Name: GG AI Chat
 * Description: Lightweight ChatGPT-style chatbot for WordPress.
 * Version: 1.0
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

function gg_ai_chat_response( $request ) {
    $body = json_decode( $request->get_body(), true );
    $message = sanitize_text_field( $body['message'] );

    $api_key = defined( 'OPENAI_API_KEY' ) ? OPENAI_API_KEY : '';
    $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
        'headers' => [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $api_key,
        ],
        'body' => json_encode([
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'system', 'content' => 'You are a friendly website assistant for GG Dev. Answer briefly, conversationally, and helpfully.'],
                ['role' => 'user', 'content' => $message],
            ],
            'temperature' => 0.7,
        ]),
    ]);

    if ( is_wp_error( $response ) ) {
        return wp_send_json_error( [ 'error' => $response->get_error_message() ] );
    }

    $data = json_decode( wp_remote_retrieve_body( $response ), true );
    $reply = $data['choices'][0]['message']['content'] ?? 'Sorry, I couldn’t get a response.';

    return wp_send_json_success( [ 'reply' => $reply ] );
}
