<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * 最適化前の元コンテンツに戻す
 * POST /wp-json/stock/v1/restore/{id}
 */
add_action( 'rest_api_init', function () {
    register_rest_route( 'stock/v1', '/restore/(?P<id>\d+)', [
        'methods'             => 'POST',
        'callback'            => 'stock_api_restore',
        'permission_callback' => 'stock_api_auth',
        'args'                => [
            'id' => [ 'validate_callback' => fn( $v ) => is_numeric( $v ) ],
        ],
    ] );
} );

function stock_api_restore( WP_REST_Request $request ): WP_REST_Response {
    $post_id = (int) $request->get_param( 'id' );
    $post    = get_post( $post_id );

    if ( ! $post ) {
        return new WP_REST_Response( [ 'error' => '投稿が見つかりません' ], 404 );
    }

    $backup = get_post_meta( $post_id, 'original_content_backup', true );

    if ( empty( $backup ) ) {
        return new WP_REST_Response( [ 'error' => 'バックアップが存在しません' ], 400 );
    }

    wp_update_post( [
        'ID'           => $post_id,
        'post_content' => $backup,
    ] );

    delete_post_meta( $post_id, 'blog_optimized_at' );

    return new WP_REST_Response( [
        'post_id' => $post_id,
        'status'  => 'restored',
    ], 200 );
}
