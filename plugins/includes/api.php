<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'rest_api_init', function () {
    register_rest_route( 'stock/v1', '/create', [
        'methods'             => 'POST',
        'callback'            => 'stock_analysis_create',
        'permission_callback' => 'stock_analysis_auth',
    ] );
} );

function stock_analysis_auth( WP_REST_Request $request ): bool {
    $key = $request->get_header( 'X-Stock-Api-Key' );
    return $key === get_option( 'stock_analysis_api_key', 'dev-secret' );
}

function stock_analysis_create( WP_REST_Request $request ): WP_REST_Response {
    $data = $request->get_json_params();

    if ( empty( $data['analysis_data'] ) ) {
        return new WP_REST_Response( [ 'error' => 'analysis_data is required' ], 400 );
    }

    $analysis = $data['analysis_data'];

    // 記事生成（OpenAI）
    $generated = stock_analysis_generate( $analysis );
    if ( is_wp_error( $generated ) ) {
        return new WP_REST_Response( [ 'error' => $generated->get_error_message() ], 500 );
    }

    // 投稿作成（下書き）
    $post_id = wp_insert_post( [
        'post_type'    => 'stock_analysis',
        'post_title'   => $generated['title'],
        'post_content' => $generated['body'],
        'post_excerpt' => $generated['excerpt'],
        'post_status'  => 'draft',
    ] );

    if ( is_wp_error( $post_id ) ) {
        return new WP_REST_Response( [ 'error' => $post_id->get_error_message() ], 500 );
    }

    // メタフィールド保存
    $meta_keys = [ 'ticker', 'company', 'sector', 'analysis_date', 'judgment',
                   'summary', 'thesis', 'growth', 'risk', 'valuation' ];
    foreach ( $meta_keys as $key ) {
        if ( isset( $analysis[ $key ] ) ) {
            update_post_meta( $post_id, $key, sanitize_text_field( $analysis[ $key ] ) );
        }
    }
    update_post_meta( $post_id, 'x_post', $generated['x_post'] );
    update_post_meta( $post_id, 'x_thread', $generated['x_thread'] );

    return new WP_REST_Response( [
        'post_id'  => $post_id,
        'title'    => $generated['title'],
        'x_post'   => $generated['x_post'],
        'edit_url' => admin_url( "post.php?post={$post_id}&action=edit" ),
    ], 201 );
}

function stock_analysis_generate( array $analysis ): array|WP_Error {
    $api_key = get_option( 'stock_analysis_openai_key', '' );

    if ( empty( $api_key ) ) {
        // APIキー未設定時はダミーデータを返す（開発用）
        return stock_analysis_dummy( $analysis );
    }

    $company = $analysis['company'] ?? '';
    $ticker  = $analysis['ticker'] ?? '';

    $article_prompt = <<<PROMPT
以下の株分析データを元に、投資家向けブログ記事を日本語で生成してください。

銘柄: {$company}（{$ticker}）
セクター: {$analysis['sector']}
投資判断: {$analysis['judgment']}
サマリー: {$analysis['summary']}
投資仮説: {$analysis['thesis']}
成長要因: {$analysis['growth']}
リスク: {$analysis['risk']}
バリュエーション: {$analysis['valuation']}

出力形式（JSON）:
{
  "title": "記事タイトル",
  "body": "HTML形式の本文（見出し・箇条書き含む）",
  "excerpt": "120字以内の要約"
}
PROMPT;

    $x_prompt = <<<PROMPT
以下の株分析を元に、Xへの投稿文を生成してください。
条件: 120〜140文字、クリック誘導、URLはプレースホルダー{{URL}}

銘柄: {$company}（{$ticker}）
判断: {$analysis['judgment']}
サマリー: {$analysis['summary']}

JSON形式で返してください: {"x_post": "投稿文"}
PROMPT;

    $article = stock_analysis_openai_request( $api_key, $article_prompt );
    if ( is_wp_error( $article ) ) return $article;

    $x = stock_analysis_openai_request( $api_key, $x_prompt );
    if ( is_wp_error( $x ) ) return $x;

    return array_merge( $article, $x, [ 'x_thread' => '' ] );
}

function stock_analysis_openai_request( string $api_key, string $prompt ): array|WP_Error {
    $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
        'timeout' => 60,
        'headers' => [
            'Authorization' => "Bearer {$api_key}",
            'Content-Type'  => 'application/json',
        ],
        'body' => wp_json_encode( [
            'model'           => 'gpt-4o',
            'response_format' => [ 'type' => 'json_object' ],
            'messages'        => [
                [ 'role' => 'user', 'content' => $prompt ],
            ],
        ] ),
    ] );

    if ( is_wp_error( $response ) ) return $response;

    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    $content = $body['choices'][0]['message']['content'] ?? '';

    return json_decode( $content, true ) ?? new WP_Error( 'parse_error', 'OpenAI response parse failed' );
}

// 開発用ダミー生成
function stock_analysis_dummy( array $analysis ): array {
    $company = $analysis['company'] ?? '不明';
    $ticker  = $analysis['ticker'] ?? '0000';
    return [
        'title'    => "【銘柄分析】{$company}（{$ticker}）",
        'body'     => "<h2>投資仮説</h2><p>{$analysis['thesis']}</p><h2>成長要因</h2><p>{$analysis['growth']}</p><h2>リスク</h2><p>{$analysis['risk']}</p>",
        'excerpt'  => $analysis['summary'] ?? '',
        'x_post'   => "{$company}（{$ticker}）の分析を公開しました。\n詳しくはこちら👇\n{{URL}}",
        'x_thread' => '',
    ];
}
