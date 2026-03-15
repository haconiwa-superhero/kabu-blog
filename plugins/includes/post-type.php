<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'init', function () {

    // ── 既存: 旧stock_analysis（後方互換で残す）──────────────────
    register_post_type( 'stock_analysis', [
        'labels'       => [ 'name' => '株分析（旧）', 'singular_name' => '株分析（旧）' ],
        'public'       => false,
        'show_ui'      => true,
        'show_in_rest' => true,
        'supports'     => [ 'title', 'editor', 'excerpt' ],
        'menu_icon'    => 'dashicons-archive',
    ] );

    // ── stock_blog: ブログ記事（最適化済み）──────────────────────
    register_post_type( 'stock_blog', [
        'labels' => [
            'name'          => 'ブログ記事',
            'singular_name' => 'ブログ記事',
            'add_new_item'  => 'ブログ記事を新規追加',
            'edit_item'     => 'ブログ記事を編集',
            'all_items'     => 'ブログ記事一覧',
        ],
        'public'       => true,
        'show_in_rest' => true,
        'supports'     => [ 'title', 'editor', 'excerpt', 'thumbnail' ],
        'rewrite'      => [ 'slug' => 'blog' ],
        'menu_icon'    => 'dashicons-media-text',
        'menu_position' => 5,
    ] );

    // ── stock_sns: SNS投稿文────────────────────────────────────
    register_post_type( 'stock_sns', [
        'labels' => [
            'name'          => 'SNS投稿',
            'singular_name' => 'SNS投稿',
            'add_new_item'  => 'SNS投稿を新規追加',
            'edit_item'     => 'SNS投稿を編集',
            'all_items'     => 'SNS投稿一覧',
        ],
        'public'       => false,
        'show_ui'      => true,
        'show_in_rest' => true,
        'supports'     => [ 'title', 'editor' ],
        'menu_icon'    => 'dashicons-twitter',
        'menu_position' => 6,
    ] );
} );
