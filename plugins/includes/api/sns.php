<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * stock_blog の内容 → stock_sns カスタム投稿にSNS投稿文を生成
 * POST /wp-json/stock/v1/sns/{id}
 *
 * {id} = 元の post（手書き分析）のID
 */
add_action( 'rest_api_init', function () {
    register_rest_route( 'stock/v1', '/sns/(?P<id>\d+)', [
        'methods'             => 'POST',
        'callback'            => 'stock_api_sns',
        'permission_callback' => 'stock_api_auth',
        'args'                => [
            'id' => [ 'validate_callback' => fn( $v ) => is_numeric( $v ) ],
        ],
    ] );
} );

function stock_api_sns( WP_REST_Request $request ): WP_REST_Response {
    $source_id = (int) $request->get_param( 'id' );
    $source    = get_post( $source_id );

    if ( ! $source ) {
        return new WP_REST_Response( [ 'error' => '元の投稿が見つかりません' ], 404 );
    }

    // stock_blog が必須（先にブログ記事を生成してください）
    $blog_id   = (int) get_post_meta( $source_id, 'blog_post_id', true );
    $blog_post = $blog_id ? get_post( $blog_id ) : null;

    if ( ! $blog_post ) {
        return new WP_REST_Response( [ 'error' => 'ブログ記事がまだ生成されていません。先にSTEP 1でブログ記事を生成してください。' ], 400 );
    }

    $title    = $blog_post->post_title;
    $content  = wp_strip_all_tags( $blog_post->post_content );
    $post_url = get_permalink( $blog_id );

    $content = preg_replace( '/<!--.*?-->/s', '', $content );
    $content = preg_replace( '/\n{3,}/', "\n\n", trim( $content ) );
    $content = mb_substr( $content, 0, 6000 );

    $prompt = <<<PROMPT
あなたは株式投資が好きな個人投資家です。
自分で銘柄を調べてXに投稿し続けている人間です。
メディアっぽい文章・広告っぽい文章は絶対に書きません。
自分が感じたこと、気になったこと、正直な反応をそのまま書いてください。

このブログは有価証券報告書・決算短信・業界ニュースなどの
一次情報をAI（GPTs）で解析した分析メディアです。

---

# 人間らしく書くための具体例

## NG例（メディアっぽい・広告っぽい）
× 「タツモ（6266）の財務健全性が急嵩し投資の可能性に注目が集まっています」
× 「AI分析で明らかになった成長可能性をぜひご確認ください」
× 「決算データから見える成長ストーリーを整理しました」

## OK例（人間らしい・思わず読んでしまう）
○ 「AI関連なのにPER10倍。なんで？と思って調べた」
○ 「利益率16%、ROE19%。この数字でなぜ評価されないのか正直謎」
○ 「受注残が減ってる。ここだけちょっと気になってる」
○ 「地味な会社だと思ってたけど、決算見たら全然地味じゃなかった」

---

# 投稿の構造

①最初の1行で引き込む（数字 or 素直な反応）
②データや事実を短く切り出す
③自分の率直な見解 or 疑問
④記事リンク（{{URL}}）

---

# 文章ルール

・120〜150文字
・改行を多く使う（スマホで縦に読める）
・1投稿1テーマ（詰め込まない）
・数字は必ず入れる
・「です・ます」調より「〜だ」「〜だった」「〜なのか？」の方が自然

---

# 投稿タイプ（5パターン）

## ①データ型
数字をそのまま並べてフックにする。コメントは短く。
例：
AI関連なのにPER10倍。

タツモ（6266）

利益率16%
ROE19%

なのに低評価。
受注残が減ってるのが原因っぽい。

調べた内容まとめた👇

## ②疑問型
「なんで？」という素直な疑問から入る。
例：
なんでこの株こんなに安いんだろう。

AI半導体関連
PER10倍
時価総額368億

答えを探して決算と業界データ
AIで分析してみた👇

## ③発見型
調べて気づいたことを「実は〜」で伝える。
自分が驚いた事実を正直に書く。

## ④議論型
自分の意見を出して「どう思う？」と聞く。
断言 + 疑問形の組み合わせ。

## ⑤学び型
この銘柄を調べて気づいた「投資の視点」を伝える。
例：
半導体装置株を見るとき

売上だけ見てたら騙される。

重要なのは「受注残」

タツモ（6266）を追いかけて
改めてそう思った。

詳しくはこちら👇

---

【タイトル】
{$title}

【記事内容】
{$content}

---

以下のJSON形式で返してください:
{{
  "patterns": [
    {{"label": "①データ型", "text": "投稿文（120〜150文字・末尾に{{URL}}）"}},
    {{"label": "②疑問型", "text": "投稿文（120〜150文字・末尾に{{URL}}）"}},
    {{"label": "③発見型", "text": "投稿文（120〜150文字・末尾に{{URL}}）"}},
    {{"label": "④議論型", "text": "投稿文（120〜150文字・末尾に{{URL}}）"}},
    {{"label": "⑤学び型", "text": "投稿文（120〜150文字・末尾に{{URL}}）"}}
  ]
}}
PROMPT;

    $generated = stock_openai_request( $prompt );

    if ( is_wp_error( $generated ) ) {
        return new WP_REST_Response( [ 'error' => $generated->get_error_message() ], 500 );
    }

    $patterns = $generated['patterns'] ?? [];

    // URLプレースホルダーを実際のURLに置換
    if ( $post_url ) {
        $patterns = array_map( function ( $p ) use ( $post_url ) {
            $p['text'] = str_replace( '{{URL}}', $post_url, $p['text'] );
            return $p;
        }, $patterns );
    }

    // 後方互換: 1つ目のパターンを x_post として扱う
    $x_post = ! empty( $patterns[0]['text'] ) ? $patterns[0]['text'] : '';

    // SNS投稿の本文（管理画面で見やすいよう整形）
    $sns_body = '';
    foreach ( $patterns as $p ) {
        $sns_body .= "【{$p['label']}】\n{$p['text']}\n\n---\n\n";
    }

    // 既存の stock_sns を検索
    $existing = get_posts( [
        'post_type'      => 'stock_sns',
        'post_status'    => 'any',
        'posts_per_page' => 1,
        'meta_query'     => [
            [ 'key' => 'source_post_id', 'value' => $source_id ],
        ],
    ] );

    $sns_data = [
        'post_type'    => 'stock_sns',
        'post_title'   => $title . '【SNS】',
        'post_content' => $sns_body,
        'post_status'  => 'draft',
    ];

    if ( ! empty( $existing ) ) {
        $sns_data['ID'] = $existing[0]->ID;
        $sns_id = wp_update_post( $sns_data, true );
    } else {
        $sns_id = wp_insert_post( $sns_data, true );
    }

    if ( is_wp_error( $sns_id ) ) {
        return new WP_REST_Response( [ 'error' => $sns_id->get_error_message() ], 500 );
    }

    // メタ保存
    update_post_meta( $sns_id, 'source_post_id', $source_id );
    update_post_meta( $sns_id, 'blog_post_id', $blog_id ?: '' );
    update_post_meta( $sns_id, 'x_post', $x_post );
    update_post_meta( $sns_id, 'x_patterns', wp_json_encode( $patterns, JSON_UNESCAPED_UNICODE ) );
    update_post_meta( $sns_id, 'sns_generated_at', current_time( 'mysql' ) );

    // 元投稿にも sns_post_id を紐付け
    update_post_meta( $source_id, 'sns_post_id', $sns_id );

    return new WP_REST_Response( [
        'source_post_id' => $source_id,
        'blog_post_id'   => $blog_id ?: null,
        'sns_post_id'    => $sns_id,
        'patterns'       => $patterns,
        'status'         => 'created',
        'edit_url'       => admin_url( "post.php?post={$sns_id}&action=edit" ),
    ], 201 );
}
