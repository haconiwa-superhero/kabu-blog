<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * STEP2: 保存済み分析データからブログ記事を生成
 * POST /wp-json/stock/v1/generate/{id}
 */
add_action( 'rest_api_init', function () {
    register_rest_route( 'stock/v1', '/generate/(?P<id>\d+)', [
        'methods'             => 'POST',
        'callback'            => 'stock_api_generate',
        'permission_callback' => 'stock_api_auth',
        'args'                => [
            'id' => [ 'validate_callback' => fn( $v ) => is_numeric( $v ) ],
        ],
    ] );
} );

function stock_api_generate( WP_REST_Request $request ): WP_REST_Response {
    $post_id = (int) $request->get_param( 'id' );
    $post    = get_post( $post_id );

    if ( ! $post || $post->post_type !== 'stock_analysis' ) {
        return new WP_REST_Response( [ 'error' => '投稿が見つかりません' ], 404 );
    }

    // メタデータ取得
    $fields = [ 'ticker', 'company', 'sector', 'analysis_date', 'judgment',
                'summary', 'thesis', 'growth', 'risk', 'valuation', 'story', 'catalyst', 'scenario', 'score' ];
    $meta = [];
    foreach ( $fields as $f ) {
        $meta[ $f ] = get_post_meta( $post_id, $f, true );
    }

    $company  = $meta['company'];
    $ticker   = $meta['ticker'];
    $scenario = $meta['scenario'] ? json_decode( $meta['scenario'], true ) : [];
    $score    = $meta['score'] ? json_decode( $meta['score'], true ) : [];

    $scenario_text = '';
    if ( $scenario ) {
        $scenario_text = "\n【シナリオ分析】\n";
        $scenario_text .= "強気: {$scenario['bull']}\n";
        $scenario_text .= "中立: {$scenario['base']}\n";
        $scenario_text .= "弱気: {$scenario['bear']}\n";
    }

    $score_text = '';
    if ( $score ) {
        $score_text = "\n【スコアリング】\n";
        foreach ( $score as $k => $v ) {
            $score_text .= "{$k}: {$v}\n";
        }
    }

    $prompt = <<<PROMPT
あなたは株式投資メディアのライターです。
以下の分析データを元に、個人投資家向けのブログ記事を日本語で生成してください。

---
銘柄: {$company}（{$ticker}）
セクター: {$meta['sector']}
投資判断: {$meta['judgment']}
分析日: {$meta['analysis_date']}

【投資ストーリー】
{$meta['story']}

【サマリー】
{$meta['summary']}

【投資仮説】
{$meta['thesis']}

【成長要因】
{$meta['growth']}

【リスク】
{$meta['risk']}

【バリュエーション】
{$meta['valuation']}

【カタリスト】
{$meta['catalyst']}
{$scenario_text}
{$score_text}
---

記事の要件:
- 読者は個人投資家（初級〜中級）
- 投資ストーリーとシナリオを中心に、わかりやすく解説する
- 見出しはH2/H3を使い、構造化する
- 結論（投資判断）は最後にまとめる
- 文体は丁寧だが読みやすく
- HTML形式で出力する

以下のJSON形式で返してください:
{
  "title": "SEOを意識した魅力的な記事タイトル",
  "body": "HTML形式の本文（h2/h3/p/ul/liタグ使用）",
  "excerpt": "120字以内の要約（SNSシェア用）"
}
PROMPT;

    $generated = stock_openai_request( $prompt );

    if ( is_wp_error( $generated ) ) {
        return new WP_REST_Response( [ 'error' => $generated->get_error_message() ], 500 );
    }

    // 記事を更新
    wp_update_post( [
        'ID'           => $post_id,
        'post_title'   => $generated['title'],
        'post_content' => $generated['body'],
        'post_excerpt' => $generated['excerpt'],
    ] );

    update_post_meta( $post_id, 'blog_generated', current_time( 'mysql' ) );

    return new WP_REST_Response( [
        'post_id'  => $post_id,
        'title'    => $generated['title'],
        'excerpt'  => $generated['excerpt'],
        'status'   => 'generated',
        'edit_url' => admin_url( "post.php?post={$post_id}&action=edit" ),
    ], 200 );
}
