<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$stock_meta_fields = [
    // 基本情報
    'ticker'        => 'string',
    'company'       => 'string',
    'sector'        => 'string',
    'analysis_date' => 'string',
    'judgment'      => 'string',
    // 分析コンテンツ（GPTsから）
    'summary'       => 'string',
    'thesis'        => 'string',
    'growth'        => 'string',
    'risk'          => 'string',
    'valuation'     => 'string',
    'story'         => 'string',   // 投資ストーリー（ナラティブ）
    'catalyst'      => 'string',   // 近期カタリスト
    'scenario'      => 'string',   // bull/base/bear（JSON文字列）
    'score'         => 'string',   // スコアリング（JSON文字列）
    // 生成済みコンテンツ
    'x_post'        => 'string',
    'x_thread'      => 'string',
    // 管理フラグ
    'blog_generated' => 'string',  // 記事生成済み日時
    'sns_generated'  => 'string',  // SNS生成済み日時
    'tweeted_at'     => 'string',  // X投稿済み日時
];

add_action( 'init', function () use ( $stock_meta_fields ) {
    foreach ( $stock_meta_fields as $key => $type ) {
        register_post_meta( 'stock_analysis', $key, [
            'type'         => $type,
            'single'       => true,
            'show_in_rest' => true,
        ] );
    }
} );
