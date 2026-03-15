<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * OpenAI APIリクエスト共通ヘルパー
 * JSON形式でレスポンスを返す（SNS生成など短い出力向け）
 */
function stock_openai_request( string $prompt ): array|WP_Error {
    $api_key = get_option( 'stock_analysis_openai_key', '' );

    if ( empty( $api_key ) ) {
        return new WP_Error( 'no_api_key', 'OpenAI APIキーが設定されていません' );
    }

    $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
        'timeout' => 120,
        'headers' => [
            'Authorization' => "Bearer {$api_key}",
            'Content-Type'  => 'application/json',
        ],
        'body' => wp_json_encode( [
            'model'           => 'gpt-4o',
            'max_tokens'      => 4000,
            'response_format' => [ 'type' => 'json_object' ],
            'messages'        => [
                [ 'role' => 'user', 'content' => $prompt ],
            ],
        ] ),
    ] );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    $status = wp_remote_retrieve_response_code( $response );
    $body   = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( $status !== 200 ) {
        $msg = $body['error']['message'] ?? 'OpenAI API error';
        return new WP_Error( 'openai_error', $msg );
    }

    $content = $body['choices'][0]['message']['content'] ?? '';
    $decoded = json_decode( $content, true );

    if ( ! is_array( $decoded ) ) {
        return new WP_Error( 'parse_error', 'OpenAIレスポンスのパースに失敗しました' );
    }

    return $decoded;
}

/**
 * ブログ本文生成専用：JSONモードを使わず最大長のテキストを生成する
 */
function stock_openai_request_text( string $prompt ): string|WP_Error {
    $api_key = get_option( 'stock_analysis_openai_key', '' );

    if ( empty( $api_key ) ) {
        return new WP_Error( 'no_api_key', 'OpenAI APIキーが設定されていません' );
    }

    $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
        'timeout' => 180,
        'headers' => [
            'Authorization' => "Bearer {$api_key}",
            'Content-Type'  => 'application/json',
        ],
        'body' => wp_json_encode( [
            'model'      => 'gpt-4o',
            'max_tokens' => 16000,
            'messages'   => [
                [
                    'role'    => 'system',
                    'content' => 'あなたは株式投資が好きな30代の個人投資家です。ブログを趣味で書いており、専門家ぶらず、自分が感じたことをそのまま言葉にします。「ですます調」で書きますが、テンプレートの文章は絶対に書きません。AIっぽい文章・レポートっぽい文章・教科書っぽい文章は書きません。自分の言葉、自分の感情、自分の疑問を使って書いてください。出力はHTML形式（h2/h3/p/strong タグのみ）。Markdown禁止。ul/ol/li 禁止。',
                ],
                [ 'role' => 'user', 'content' => $prompt ],
            ],
        ] ),
    ] );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    $status = wp_remote_retrieve_response_code( $response );
    $body   = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( $status !== 200 ) {
        $msg = $body['error']['message'] ?? 'OpenAI API error';
        return new WP_Error( 'openai_error', $msg );
    }

    return $body['choices'][0]['message']['content'] ?? '';
}
