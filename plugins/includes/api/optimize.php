<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * 手書き分析記事 → stock_blog カスタム投稿にブログ最適化版を生成
 * POST /wp-json/stock/v1/optimize/{id}
 *
 * {id} = 元の post（手書き分析）のID
 */
add_action( 'rest_api_init', function () {
    register_rest_route( 'stock/v1', '/optimize/(?P<id>\d+)', [
        'methods'             => 'POST',
        'callback'            => 'stock_api_optimize',
        'permission_callback' => 'stock_api_auth',
        'args'                => [
            'id' => [ 'validate_callback' => fn( $v ) => is_numeric( $v ) ],
        ],
    ] );
} );

function stock_api_optimize( WP_REST_Request $request ): WP_REST_Response {
    $source_id = (int) $request->get_param( 'id' );
    $source    = get_post( $source_id );

    if ( ! $source ) {
        return new WP_REST_Response( [ 'error' => '元の投稿が見つかりません' ], 404 );
    }

    // ブロックマークアップを除いてテキスト化
    $raw = wp_strip_all_tags( $source->post_content );
    $raw = preg_replace( '/<!--.*?-->/s', '', $raw );
    $raw = preg_replace( '/\n{3,}/', "\n\n", trim( $raw ) );

    if ( mb_strlen( $raw ) < 100 ) {
        return new WP_REST_Response( [ 'error' => '記事の内容が短すぎます' ], 400 );
    }

    // 長すぎる場合は先頭8000文字に絞る
    if ( mb_strlen( $raw ) > 8000 ) {
        $raw = mb_substr( $raw, 0, 8000 ) . "\n\n（以下省略）";
    }

    $title = $source->post_title;

    $prompt = <<<PROMPT
あなたは株式投資が好きな個人投資家ブロガーです。
毎週自分で銘柄を調べ、思ったことをそのままブログに書いています。
専門家ぶらず、AIっぽい文章は絶対に書かず、自分の言葉で語ってください。

---

## 人間らしく書くための具体例（これが「正解の文体」です）

### NG例（AIっぽい・レポートっぽい）
× 「同社の売上高は堅調な推移を見せており、成長性が期待されます。」
× 「このように財務基盤の強固な企業であることが確認できます。」
× 「投資判断においては各種リスクを十分に考慮することが重要です。」

### OK例（人間らしい・読みたくなる）
○ 「正直に言うと、最初この会社を見たときはピンと来なかったんです。」
○ 「でもこの数字を見て、あ、これ面白いかもと思いました。」
○ 「売上がここまで伸びているのに、なぜ株価がこんなに低いのか。そこが引っかかって調べ始めました。」
○ 「ここは少し心配です。受注が減っているのは、やっぱり気になります。」
○ 「PER10倍というのは、半導体装置株としてはかなり低い水準だと思います。」

---

## この記事の絶対条件

- 全体で8000文字以上書くこと（短くなったら書き直し）
- 財務データは1指標ごとに独立したh2セクションを作り、それぞれ500字以上で深掘りする
- 「説明の羅列」ではなく「フック → データ → 自分の解釈」の流れを各セクションで作る

---

## 文章スタイル

### 段落のリズム
- 1段落は2〜4文。短文と長文を交互に使う
- 「なぜか。」「それが〜。」「つまり〜。」という接続でテンポを作る
- 必ず一人称で語る：「正直に言うと」「個人的には」「私が気になるのは」「調べてみて驚いたのは」

### 強調の使い方
- 重要な数字・結論・意外な事実は必ず `<strong>` タグで強調
- 各セクションの核心となる一文を強調する

### 年別データの表示形式（必ずこの形式で書く）
年別の数字は以下のように1行ずつ `<p>` で書く：

```
<p><strong>2021年：220億円</strong></p>
<p><strong>2022年：243億円</strong></p>
<p><strong>2023年：281億円</strong></p>
```

そのあとに必ず「この推移が意味することは〜」という解釈の段落を続ける。

### 禁止事項
- 「このように〜です」「〜ことが重要です」「〜ことでしょう」「〜と言えるでしょう」は使わない
- ul/ol/li タグは使用禁止
- 免責文（「投資判断はご自身で」等）は不要

---

## 記事構成（セクションの数は多いほどよい）

### h2「導入」（600字以上）
インパクトのある一文から始める。
例：「AI関連なのに、PER10倍。」「この会社、知らない人が多すぎる。」
なぜこの銘柄に注目したのか、自分の動機と発見の経緯を書く。
読者が「続きを読みたい」と思う意外性を作る。

### h2「この銘柄のポイント」（500字以上）
結論から入る。「面白いのは〜です」と言い切ってから理由を語る。
意外性のある事実を `<strong>` で強調する。

### h2「何の会社か」（500字以上）
友達に話すように一言で説明する。「要するに〜な商売です」から入る。
事業の具体的な内容・顧客・どこで稼いでいるかを平易に書く。

### h2「売上の推移」（500字以上）
年別売上を前述の形式で表示する。
増収・減収の背景を自分の解釈で語る。「この数字、正直〜です」と反応を入れる。

### h2「利益率を見る」（500字以上）
営業利益率・純利益率の年別推移を表示する。
業界平均と比較して高いか低いかを言い切る。

### h2「ROEと資本効率」（400字以上）
ROEの年別推移を表示する。
「この数字が示すのは〜」という解釈を書く。

### h2「財務の健全性」（400字以上）
自己資本比率・キャッシュフローなどを表示する。
「財務的に安心できるか」を自分の言葉で評価する。

### h2「受注・バックログ（該当する場合）」（400字以上）
受注高・受注残など将来の業績を示す指標があれば詳しく書く。
「これが増えている/減っている意味は〜」と解釈する。

### h2「なぜ今後も伸びると思うか」（600字以上）
業界の追い風・企業固有の強み・参入障壁を語る。
根拠のある話と、自分の直感・仮説を混ぜていい。

### h2「正直なリスク」（500字以上）
都合の悪いことを隠さない。「ここが崩れたら話が変わる」と率直に書く。
リスクを過小評価も過大評価もせず、自分なりの見方で評価する。

### h2「株価は割安か」（500字以上）
PER・時価総額などを表示する。「安い」「高い」を言い切る。
過去の水準・競合比較・成長期待を織り交ぜて根拠を示す。

### h2「個人的にこの銘柄を面白いと思う理由」（400字以上）
感情的な部分も含めて語る。なぜ調べようと思ったか。
「この3点が揃っている銘柄はそう多くない」のような語りかけ方でいい。

### h2「今後チェックするポイント」（400字以上）
次の決算・受注・発表で何を確認したいか具体的に書く。
「ここが回復すれば話が変わる」という視点を入れる。

### h2「結論」（400字以上）
「今すぐ買いか」「監視銘柄か」「様子見か」を自分の言葉で言い切る。
読者が「読んでよかった」と思える締めにする。

---

## データの扱い
- 数字・結論・分析ロジックは原文のまま使う（改変しない）
- 分析テキストにないことは書かない
- 各数字に「自分はどう思うか」を必ず添える

---

【銘柄名】
{$title}

【企業分析テキスト】
{$raw}

---

HTMLのみ出力してください。JSONではありません。
使えるタグ: h2, h3, p, strong のみ。
ul/ol/li タグは使用禁止。
PROMPT;

    // STEP1: 本文をテキストモードで生成（JSONモード制限を回避）
    $body_html = stock_openai_request_text( $prompt );

    if ( is_wp_error( $body_html ) ) {
        return new WP_REST_Response( [ 'error' => $body_html->get_error_message() ], 500 );
    }

    // markdownコードブロック（```html ... ``` 等）を除去
    $body_html = preg_replace( '/^```[a-z]*\n?/m', '', $body_html );
    $body_html = preg_replace( '/\n?```$/m', '', $body_html );
    $body_html = trim( $body_html );

    // STEP2: タイトル・要約だけをJSON形式で生成
    $meta_prompt = <<<META
以下のHTML形式のブログ記事本文から、タイトルと要約を生成してください。

【記事本文（抜粋）】
{$body_html}

以下のJSON形式で返してください:
{
  "title": "SEOを意識した記事タイトル（銘柄名・ティッカーを含む）",
  "title_candidates": ["タイトル案1", "タイトル案2", "タイトル案3"],
  "excerpt": "SNSシェア・メタdescription用の要約（120字以内）"
}
META;

    $meta = stock_openai_request( $meta_prompt );

    if ( is_wp_error( $meta ) ) {
        return new WP_REST_Response( [ 'error' => $meta->get_error_message() ], 500 );
    }

    $post_title   = $meta['title'] ?? $title;
    $post_excerpt = $meta['excerpt'] ?? '';
    $title_cands  = $meta['title_candidates'] ?? [];

    // 既存のstock_blogを検索（同じ元投稿から生成済みか）
    $existing = get_posts( [
        'post_type'      => 'stock_blog',
        'post_status'    => 'any',
        'posts_per_page' => 1,
        'meta_query'     => [
            [ 'key' => 'source_post_id', 'value' => $source_id ],
        ],
    ] );

    $blog_data = [
        'post_type'    => 'stock_blog',
        'post_title'   => $post_title,
        'post_content' => $body_html,
        'post_excerpt' => $post_excerpt,
        'post_status'  => 'draft',
    ];

    if ( ! empty( $existing ) ) {
        $blog_data['ID'] = $existing[0]->ID;
        $blog_id = wp_update_post( $blog_data, true );
    } else {
        $blog_id = wp_insert_post( $blog_data, true );
    }

    if ( is_wp_error( $blog_id ) ) {
        return new WP_REST_Response( [ 'error' => $blog_id->get_error_message() ], 500 );
    }

    // stock_blog に元投稿IDを紐付け
    update_post_meta( $blog_id, 'source_post_id', $source_id );
    update_post_meta( $blog_id, 'blog_generated_at', current_time( 'mysql' ) );

    // タイトル候補を保存
    if ( ! empty( $title_cands ) ) {
        update_post_meta( $blog_id, 'title_candidates', wp_json_encode( $title_cands, JSON_UNESCAPED_UNICODE ) );
    }

    // 元投稿に stock_blog の ID を紐付け
    update_post_meta( $source_id, 'blog_post_id', $blog_id );

    return new WP_REST_Response( [
        'source_post_id'   => $source_id,
        'blog_post_id'     => $blog_id,
        'title'            => $post_title,
        'title_candidates' => $title_cands,
        'excerpt'          => $post_excerpt,
        'status'           => 'created',
        'edit_url'         => admin_url( "post.php?post={$blog_id}&action=edit" ),
    ], 201 );
}
