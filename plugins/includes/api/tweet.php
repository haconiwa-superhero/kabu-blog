<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * STEP4: X（Twitter）に投稿
 * POST /wp-json/stock/v1/tweet/{id}
 * body: { "mode": "post" | "thread" }
 */
add_action( 'rest_api_init', function () {
    register_rest_route( 'stock/v1', '/tweet/(?P<id>\d+)', [
        'methods'             => 'POST',
        'callback'            => 'stock_api_tweet',
        'permission_callback' => 'stock_api_auth',
        'args'                => [
            'id' => [ 'validate_callback' => fn( $v ) => is_numeric( $v ) ],
        ],
    ] );
} );

function stock_api_tweet( WP_REST_Request $request ): WP_REST_Response {
    $source_id = (int) $request->get_param( 'id' );
    $source    = get_post( $source_id );

    if ( ! $source ) {
        return new WP_REST_Response( [ 'error' => '投稿が見つかりません' ], 404 );
    }

    // stock_sns 投稿から x_post を取得
    $sns_id = (int) get_post_meta( $source_id, 'sns_post_id', true );
    if ( ! $sns_id ) {
        return new WP_REST_Response( [ 'error' => 'SNS投稿文がまだ生成されていません。先に STEP 2 を実行してください' ], 400 );
    }

    // パターン指定があればそれを使う。なければ patterns[0] を使う
    $pattern_index = (int) ( $request->get_param( 'pattern' ) ?? 0 );
    $x_patterns_json = get_post_meta( $sns_id, 'x_patterns', true );
    $x_patterns = $x_patterns_json ? json_decode( $x_patterns_json, true ) : [];

    if ( ! empty( $x_patterns ) && isset( $x_patterns[ $pattern_index ] ) ) {
        $x_post = $x_patterns[ $pattern_index ]['text'];
    } else {
        $x_post = get_post_meta( $sns_id, 'x_post', true );
    }

    if ( empty( $x_post ) ) {
        return new WP_REST_Response( [ 'error' => 'SNS投稿文が見つかりません' ], 400 );
    }

    $mode = $request->get_param( 'mode' ) ?? 'post';
    $x_thread_json = get_post_meta( $sns_id, 'x_thread', true );

    $results = [];

    if ( $mode === 'thread' && $x_thread_json ) {
        $thread = json_decode( $x_thread_json, true );
        if ( ! empty( $thread ) && is_array( $thread ) ) {
            foreach ( $thread as $tweet_text ) {
                $result = stock_x_post_tweet( $tweet_text );
                if ( is_wp_error( $result ) ) {
                    return new WP_REST_Response( [ 'error' => $result->get_error_message() ], 500 );
                }
                $results[] = $result;
                sleep( 1 ); // レート制限対策
            }
        }
    } else {
        $result = stock_x_post_tweet( $x_post );
        if ( is_wp_error( $result ) ) {
            return new WP_REST_Response( [ 'error' => $result->get_error_message() ], 500 );
        }
        $results[] = $result;
    }

    $tweeted_at = current_time( 'mysql' );
    update_post_meta( $source_id, 'tweeted_at', $tweeted_at );
    update_post_meta( $sns_id, 'tweeted_at', $tweeted_at );

    return new WP_REST_Response( [
        'source_post_id' => $source_id,
        'sns_post_id'    => $sns_id,
        'mode'           => $mode,
        'tweets'         => $results,
        'tweeted_at'     => $tweeted_at,
        'status'         => 'tweeted',
    ], 200 );
}
