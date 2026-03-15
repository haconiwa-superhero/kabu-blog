<?php
/**
 * Plugin Name: Stock Analysis
 * Description: AI株分析ブログシステム - GPTs連携・記事生成・SNS投稿
 * Version: 0.2.0
 * Author: superhero
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// 基盤
require_once __DIR__ . '/includes/post-type.php';
require_once __DIR__ . '/includes/meta-fields.php';

// ライブラリ
require_once __DIR__ . '/includes/lib/openai.php';
require_once __DIR__ . '/includes/lib/x-api.php';

// API認証（共通）
function stock_api_auth( WP_REST_Request $request ): bool {
    $key = $request->get_header( 'X-Stock-Api-Key' );
    return $key === get_option( 'stock_analysis_api_key', 'dev-secret' );
}

// APIエンドポイント
require_once __DIR__ . '/includes/api/store.php';
require_once __DIR__ . '/includes/api/optimize.php';
require_once __DIR__ . '/includes/api/generate.php';
require_once __DIR__ . '/includes/api/sns.php';
require_once __DIR__ . '/includes/api/tweet.php';
require_once __DIR__ . '/includes/api/restore.php';

// 管理画面
if ( is_admin() ) {
    require_once __DIR__ . '/includes/admin/meta-box.php';
    require_once __DIR__ . '/includes/admin/settings.php';
}
