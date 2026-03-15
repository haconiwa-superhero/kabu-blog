<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * X (Twitter) API v2 投稿ヘルパー
 * OAuth 1.0a を使用
 */
function stock_x_post_tweet( string $text ): array|WP_Error {
    $api_key        = get_option( 'stock_x_api_key', '' );
    $api_secret     = get_option( 'stock_x_api_secret', '' );
    $access_token   = get_option( 'stock_x_access_token', '' );
    $access_secret  = get_option( 'stock_x_access_secret', '' );

    if ( ! $api_key || ! $api_secret || ! $access_token || ! $access_secret ) {
        return new WP_Error( 'no_x_credentials', 'X APIキーが設定されていません' );
    }

    $url    = 'https://api.twitter.com/2/tweets';
    $method = 'POST';

    // OAuth 1.0a ヘッダー生成
    $oauth_header = stock_x_oauth_header( $method, $url, [], $api_key, $api_secret, $access_token, $access_secret );

    $response = wp_remote_post( $url, [
        'timeout' => 30,
        'headers' => [
            'Authorization' => $oauth_header,
            'Content-Type'  => 'application/json',
        ],
        'body' => wp_json_encode( [ 'text' => $text ] ),
    ] );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    $status = wp_remote_retrieve_response_code( $response );
    $body   = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( $status !== 201 ) {
        $msg = $body['detail'] ?? $body['title'] ?? 'X API error';
        return new WP_Error( 'x_api_error', $msg );
    }

    return [
        'tweet_id' => $body['data']['id'] ?? '',
        'text'     => $body['data']['text'] ?? $text,
    ];
}

function stock_x_oauth_header( string $method, string $url, array $params, string $api_key, string $api_secret, string $access_token, string $access_secret ): string {
    $oauth = [
        'oauth_consumer_key'     => $api_key,
        'oauth_nonce'            => wp_generate_password( 32, false ),
        'oauth_signature_method' => 'HMAC-SHA1',
        'oauth_timestamp'        => time(),
        'oauth_token'            => $access_token,
        'oauth_version'          => '1.0',
    ];

    // 署名ベース文字列の生成
    $base_params = array_merge( $oauth, $params );
    ksort( $base_params );
    $param_string = http_build_query( $base_params, '', '&', PHP_QUERY_RFC3986 );
    $base_string  = strtoupper( $method ) . '&' . rawurlencode( $url ) . '&' . rawurlencode( $param_string );

    // 署名キーと署名生成
    $signing_key       = rawurlencode( $api_secret ) . '&' . rawurlencode( $access_secret );
    $oauth['oauth_signature'] = base64_encode( hash_hmac( 'sha1', $base_string, $signing_key, true ) );

    // Authorizationヘッダー組み立て
    ksort( $oauth );
    $parts = [];
    foreach ( $oauth as $k => $v ) {
        $parts[] = rawurlencode( $k ) . '="' . rawurlencode( $v ) . '"';
    }

    return 'OAuth ' . implode( ', ', $parts );
}
