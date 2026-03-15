<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * 親テーマのスタイルを読み込み、子テーマのスタイルを追加
 */
add_action( 'wp_enqueue_scripts', function () {
    wp_enqueue_style(
        'twentytwentyfour-style',
        get_template_directory_uri() . '/style.css'
    );
    wp_enqueue_style(
        'twentytwentyfour-child-style',
        get_stylesheet_directory_uri() . '/style.css',
        [ 'twentytwentyfour-style' ],
        wp_get_theme()->get( 'Version' )
    );
} );
