<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * STEP1: 分析データをWordPressに保存
 * POST /wp-json/stock/v1/store
 *
 * GPTsからJSONを受け取り、生記事コンテンツとして保存する（AI生成はしない）
 */
add_action( 'rest_api_init', function () {
    register_rest_route( 'stock/v1', '/store', [
        'methods'             => 'POST',
        'callback'            => 'stock_api_store',
        'permission_callback' => 'stock_api_auth',
    ] );
} );

function stock_api_store( WP_REST_Request $request ): WP_REST_Response {
    $data = $request->get_json_params();

    if ( empty( $data['analysis_data'] ) ) {
        return new WP_REST_Response( [ 'error' => 'analysis_data is required' ], 400 );
    }

    $a = $data['analysis_data'];

    // 必須フィールドチェック
    foreach ( [ 'ticker', 'company' ] as $required ) {
        if ( empty( $a[ $required ] ) ) {
            return new WP_REST_Response( [ 'error' => "{$required} is required" ], 400 );
        }
    }

    // 既存の同一銘柄投稿を検索（同日更新 or 新規作成）
    $existing = get_posts( [
        'post_type'      => 'stock_analysis',
        'post_status'    => 'any',
        'posts_per_page' => 1,
        'meta_query'     => [
            [ 'key' => 'ticker', 'value' => sanitize_text_field( $a['ticker'] ) ],
        ],
    ] );

    $title   = "【分析データ】{$a['company']}（{$a['ticker']}）";
    $post_data = [
        'post_type'   => 'stock_analysis',
        'post_title'  => $title,
        'post_status' => 'draft',
    ];

    if ( ! empty( $existing ) ) {
        $post_data['ID'] = $existing[0]->ID;
        $post_id = wp_update_post( $post_data, true );
    } else {
        $post_id = wp_insert_post( $post_data, true );
    }

    if ( is_wp_error( $post_id ) ) {
        return new WP_REST_Response( [ 'error' => $post_id->get_error_message() ], 500 );
    }

    // メタフィールド保存
    $string_fields = [ 'ticker', 'company', 'sector', 'analysis_date', 'judgment',
                       'summary', 'thesis', 'growth', 'risk', 'valuation', 'story', 'catalyst' ];
    foreach ( $string_fields as $key ) {
        if ( isset( $a[ $key ] ) ) {
            update_post_meta( $post_id, $key, sanitize_textarea_field( $a[ $key ] ) );
        }
    }

    // JSON フィールド（scenario / score）
    foreach ( [ 'scenario', 'score' ] as $key ) {
        if ( isset( $a[ $key ] ) ) {
            $value = is_array( $a[ $key ] ) ? wp_json_encode( $a[ $key ], JSON_UNESCAPED_UNICODE ) : $a[ $key ];
            update_post_meta( $post_id, $key, $value );
        }
    }

    // 生成フラグをリセット（データ更新時は再生成が必要）
    delete_post_meta( $post_id, 'blog_generated' );
    delete_post_meta( $post_id, 'sns_generated' );

    return new WP_REST_Response( [
        'post_id'  => $post_id,
        'company'  => $a['company'],
        'ticker'   => $a['ticker'],
        'status'   => 'stored',
        'edit_url' => admin_url( "post.php?post={$post_id}&action=edit" ),
    ], 201 );
}
