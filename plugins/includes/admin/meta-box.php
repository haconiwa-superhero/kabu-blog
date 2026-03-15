<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * 手書き分析投稿（post）のサイドバーにフロー操作パネルを追加
 */
add_action( 'add_meta_boxes', function () {
    add_meta_box(
        'stock_flow_panel',
        '📊 株分析フロー',
        'stock_meta_box_flow',
        'post',
        'side',
        'high'
    );
} );

function stock_meta_box_flow( WP_Post $post ): void {
    $blog_id       = (int) get_post_meta( $post->ID, 'blog_post_id', true );
    $sns_id        = (int) get_post_meta( $post->ID, 'sns_post_id', true );
    $nonce         = wp_create_nonce( 'wp_rest' );
    $api_key       = get_option( 'stock_analysis_api_key', 'dev-secret' );

    $blog_title    = $blog_id ? get_the_title( $blog_id ) : '';
    $blog_status   = $blog_id ? get_post_status( $blog_id ) : '';
    $blog_edit_url = $blog_id ? admin_url( "post.php?post={$blog_id}&action=edit" ) : '';

    $sns_title     = $sns_id ? get_the_title( $sns_id ) : '';
    $sns_status    = $sns_id ? get_post_status( $sns_id ) : '';
    $sns_edit_url  = $sns_id ? admin_url( "post.php?post={$sns_id}&action=edit" ) : '';
    ?>
    <style>
    .sf-btn {
        display:block; width:100%; margin:4px 0; padding:8px 10px;
        border:none; border-radius:3px; cursor:pointer;
        font-size:12px; font-weight:600; text-align:left; line-height:1.4;
    }
    .sf-btn:disabled { opacity:.4; cursor:not-allowed; }
    .sf-btn-blue   { background:#0073aa; color:#fff; }
    .sf-btn-teal   { background:#00a0d2; color:#fff; }
    .sf-result {
        margin:4px 0 10px; padding:6px 8px;
        background:#f0f6fc; border-left:3px solid #0073aa;
        font-size:11px; line-height:1.5;
    }
    .sf-result a { color:#0073aa; text-decoration:none; font-weight:600; }
    .sf-result a:hover { text-decoration:underline; }
    .sf-result .sf-badge {
        display:inline-block; padding:1px 5px; border-radius:2px;
        font-size:10px; margin-left:4px;
        background:#ddd; color:#555;
    }
    .sf-result .sf-badge.draft   { background:#f0e68c; color:#7a6500; }
    .sf-result .sf-badge.publish { background:#d4edda; color:#155724; }
    .sf-divider { border:none; border-top:1px solid #eee; margin:10px 0 8px; }
    </style>

    <p style="font-size:11px;color:#888;margin:0 0 10px;">
        STEP順にボタンを押してください
    </p>

    <?php /* ── STEP 1 ── */ ?>
    <strong style="font-size:11px;color:#333;">STEP 1 — ブログ記事を生成</strong>
    <button class="sf-btn sf-btn-blue" id="sf-optimize"
            data-post="<?php echo esc_attr( $post->ID ); ?>"
            data-nonce="<?php echo esc_attr( $nonce ); ?>"
            data-apikey="<?php echo esc_attr( $api_key ); ?>">
        📝 ブログ記事に最適化して生成
    </button>

    <?php if ( $blog_id ): ?>
    <div class="sf-result" id="sf-blog-result">
        <a href="<?php echo esc_url( $blog_edit_url ); ?>" target="_blank">
            <?php echo esc_html( $blog_title ); ?>
        </a>
        <span class="sf-badge <?php echo esc_attr( $blog_status ); ?>">
            <?php echo $blog_status === 'publish' ? '公開中' : '下書き'; ?>
        </span><br>
        <span style="color:#888;">（別ウィンドウで編集・公開）</span>
    </div>
    <?php else: ?>
    <div class="sf-result" id="sf-blog-result" style="display:none;"></div>
    <?php endif; ?>

    <hr class="sf-divider">

    <?php /* ── STEP 2 ── */ ?>
    <strong style="font-size:11px;color:#333;">STEP 2 — SNS投稿文を生成</strong>
    <button class="sf-btn sf-btn-teal" id="sf-sns"
            data-post="<?php echo esc_attr( $post->ID ); ?>"
            data-nonce="<?php echo esc_attr( $nonce ); ?>"
            data-apikey="<?php echo esc_attr( $api_key ); ?>">
        🐦 SNS投稿文を生成
    </button>

    <?php if ( $sns_id ): ?>
    <div class="sf-result" id="sf-sns-result">
        <a href="<?php echo esc_url( $sns_edit_url ); ?>" target="_blank">
            <?php echo esc_html( $sns_title ); ?>
        </a>
        <span class="sf-badge <?php echo esc_attr( $sns_status ); ?>">
            <?php echo $sns_status === 'publish' ? '公開中' : '下書き'; ?>
        </span><br>
        <span style="color:#888;">（別ウィンドウで確認・編集）</span>
    </div>
    <?php else: ?>
    <div class="sf-result" id="sf-sns-result" style="display:none;"></div>
    <?php endif; ?>

    <hr class="sf-divider">

    <?php /* ── STEP 3 ── */ ?>
    <strong style="font-size:11px;color:#333;">STEP 3 — X（Twitter）に投稿</strong>

    <?php
    $x_patterns = [];
    if ( $sns_id ) {
        $x_patterns_json = get_post_meta( $sns_id, 'x_patterns', true );
        if ( $x_patterns_json ) {
            $x_patterns = json_decode( $x_patterns_json, true ) ?: [];
        }
    }
    $tweeted_at = $sns_id ? get_post_meta( $post->ID, 'tweeted_at', true ) : '';
    ?>

    <?php if ( ! empty( $x_patterns ) ): ?>
    <div style="margin:6px 0;">
        <select id="sf-tweet-select" style="width:100%;font-size:11px;padding:4px;margin-bottom:4px;">
            <?php foreach ( $x_patterns as $i => $p ): ?>
            <option value="<?php echo esc_attr( $i ); ?>"><?php echo esc_html( $p['label'] ); ?></option>
            <?php endforeach; ?>
        </select>
        <div id="sf-tweet-preview" style="font-size:10px;color:#555;background:#f9f9f9;border:1px solid #ddd;padding:5px 6px;border-radius:2px;line-height:1.5;white-space:pre-wrap;word-break:break-all;max-height:80px;overflow-y:auto;">
            <?php echo esc_html( $x_patterns[0]['text'] ?? '' ); ?>
        </div>
    </div>
    <?php endif; ?>

    <button class="sf-btn" id="sf-tweet"
            style="background:#1da1f2;color:#fff;"
            data-post="<?php echo esc_attr( $post->ID ); ?>"
            data-nonce="<?php echo esc_attr( $nonce ); ?>"
            data-apikey="<?php echo esc_attr( $api_key ); ?>"
            <?php echo empty( $x_patterns ) ? 'disabled' : ''; ?>>
        🐦 X に投稿する
    </button>

    <div id="sf-tweet-result" style="margin:4px 0;">
        <?php if ( $tweeted_at ): ?>
        <span style="font-size:11px;color:#1da1f2;">✅ 投稿済み（<?php echo esc_html( $tweeted_at ); ?>）</span>
        <?php elseif ( empty( $x_patterns ) ): ?>
        <span style="font-size:11px;color:#888;">先に STEP 2 でSNS投稿文を生成してください</span>
        <?php endif; ?>
    </div>

    <script>
    (function() {
        function callApi(endpoint, postId, nonce, apiKey, body) {
            return fetch('/wp-json/stock/v1/' + endpoint + '/' + postId, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': nonce,
                    'X-Stock-Api-Key': apiKey,
                },
                body: JSON.stringify(body || {}),
            }).then(function(r) { return r.json(); });
        }

        // STEP 1: ブログ最適化
        document.getElementById('sf-optimize').addEventListener('click', function() {
            var btn = this;
            if (!confirm('この記事の内容をもとに、ブログ記事（stock_blog）を生成します。\nよろしいですか？')) return;
            btn.disabled = true;
            btn.textContent = '⏳ 生成中... (30〜60秒)';
            callApi('optimize', btn.dataset.post, btn.dataset.nonce, btn.dataset.apikey)
                .then(function(data) {
                    if (data.error) {
                        alert('エラー: ' + data.error);
                        return;
                    }
                    var result = document.getElementById('sf-blog-result');
                    result.style.display = 'block';
                    result.innerHTML =
                        '<a href="' + data.edit_url + '" target="_blank">' + data.title + '</a>' +
                        '<span class="sf-badge draft">下書き</span><br>' +
                        '<span style="color:#888;">（別ウィンドウで編集・公開）</span>';
                    btn.textContent = '✅ 生成済み（再生成）';
                    btn.style.background = '#4a9960';
                })
                .catch(function(e) { alert('通信エラー: ' + e); })
                .finally(function() { btn.disabled = false; });
        });

        // STEP 2: SNS生成
        document.getElementById('sf-sns').addEventListener('click', function() {
            var btn = this;
            if (!confirm('ブログ記事の内容をもとに、SNS投稿文（stock_sns）を生成します。\nよろしいですか？')) return;
            btn.disabled = true;
            btn.textContent = '⏳ 生成中...';
            callApi('sns', btn.dataset.post, btn.dataset.nonce, btn.dataset.apikey)
                .then(function(data) {
                    if (data.error) {
                        alert('エラー: ' + data.error);
                        return;
                    }
                    var result = document.getElementById('sf-sns-result');
                    result.style.display = 'block';
                    result.innerHTML =
                        '<a href="' + data.edit_url + '" target="_blank">SNS投稿文を確認・編集</a>' +
                        '<span class="sf-badge draft">下書き</span>';
                    btn.textContent = '✅ 生成済み（再生成）';
                    btn.style.background = '#006b8a';

                    // パターンをセレクトに反映
                    var patterns = data.patterns || [];
                    if (patterns.length > 0) {
                        var sel = document.getElementById('sf-tweet-select');
                        var preview = document.getElementById('sf-tweet-preview');
                        sel.innerHTML = '';
                        patterns.forEach(function(p, i) {
                            var opt = document.createElement('option');
                            opt.value = i;
                            opt.textContent = p.label;
                            sel.appendChild(opt);
                        });
                        preview.textContent = patterns[0].text || '';
                        sel.style.display = 'block';
                        preview.style.display = 'block';
                        document.getElementById('sf-tweet').disabled = false;
                        document.getElementById('sf-tweet-result').innerHTML =
                            '<span style="font-size:11px;color:#888;">パターンを選んで投稿してください</span>';
                        // パターン切り替え時にプレビュー更新
                        sel.onchange = function() {
                            preview.textContent = patterns[parseInt(sel.value)].text || '';
                        };
                    }
                })
                .catch(function(e) { alert('通信エラー: ' + e); })
                .finally(function() { btn.disabled = false; });
        });

        // STEP 3: Xに投稿
        var tweetBtn = document.getElementById('sf-tweet');
        if (tweetBtn) {
            // 既存パターンのプレビュー切り替え（ページ読み込み時）
            var sel = document.getElementById('sf-tweet-select');
            var preview = document.getElementById('sf-tweet-preview');
            if (sel && preview) {
                sel.addEventListener('change', function() {
                    var opts = sel.options;
                    // プレビューはサーバーサイドで描画済みのため切り替えはJS側で管理
                    // （再生成後は上のSNS生成ハンドラが更新する）
                });
            }

            tweetBtn.addEventListener('click', function() {
                var btn = this;
                var patternIndex = sel ? parseInt(sel.value) : 0;
                var previewText = preview ? preview.textContent : '';
                if (!confirm('以下の内容をXに投稿します。\n\n' + previewText.substring(0, 100) + '...\n\nよろしいですか？')) return;
                btn.disabled = true;
                btn.textContent = '⏳ 投稿中...';
                callApi('tweet', btn.dataset.post, btn.dataset.nonce, btn.dataset.apikey, { pattern: patternIndex })
                    .then(function(data) {
                        if (data.error) {
                            alert('エラー: ' + data.error);
                            return;
                        }
                        var result = document.getElementById('sf-tweet-result');
                        result.innerHTML = '<span style="font-size:11px;color:#1da1f2;">✅ 投稿しました！</span>';
                        btn.textContent = '✅ 投稿済み（再投稿）';
                        btn.style.background = '#0d8ecf';
                    })
                    .catch(function(e) { alert('通信エラー: ' + e); })
                    .finally(function() { btn.disabled = false; });
            });
        }
    })();
    </script>
    <?php
}

// ── stock_blog / stock_sns にも「元の投稿に戻る」リンクを表示 ──
add_action( 'add_meta_boxes', function () {
    foreach ( [ 'stock_blog', 'stock_sns' ] as $pt ) {
        add_meta_box(
            'stock_source_link',
            '🔗 元の分析投稿',
            'stock_meta_box_source_link',
            $pt,
            'side',
            'high'
        );
    }
    // stock_blog にタイトル候補ボックスを追加
    add_meta_box(
        'stock_title_candidates',
        '📌 タイトル候補',
        'stock_meta_box_title_candidates',
        'stock_blog',
        'side',
        'default'
    );
} );

function stock_meta_box_source_link( WP_Post $post ): void {
    $source_id = (int) get_post_meta( $post->ID, 'source_post_id', true );
    if ( ! $source_id ) {
        echo '<p style="font-size:12px;color:#888;">元の投稿が見つかりません</p>';
        return;
    }
    $source    = get_post( $source_id );
    $edit_url  = admin_url( "post.php?post={$source_id}&action=edit" );
    $blog_id   = (int) get_post_meta( $source_id, 'blog_post_id', true );
    $sns_id    = (int) get_post_meta( $source_id, 'sns_post_id', true );

    echo '<p style="font-size:12px;">';
    echo '📄 <a href="' . esc_url( $edit_url ) . '"><strong>' . esc_html( $source->post_title ) . '</strong></a>';
    echo '</p>';

    echo '<hr style="margin:8px 0;border-color:#eee;">';
    echo '<p style="font-size:11px;color:#888;margin:0 0 4px;">関連投稿</p>';

    if ( $blog_id ) {
        $blog_edit = admin_url( "post.php?post={$blog_id}&action=edit" );
        $status    = get_post_status( $blog_id ) === 'publish' ? '公開中' : '下書き';
        echo '<p style="font-size:11px;margin:2px 0;">📝 <a href="' . esc_url( $blog_edit ) . '">ブログ記事</a> <span style="color:#888;">（' . $status . '）</span></p>';
    }
    if ( $sns_id ) {
        $sns_edit = admin_url( "post.php?post={$sns_id}&action=edit" );
        $status   = get_post_status( $sns_id ) === 'publish' ? '公開中' : '下書き';
        echo '<p style="font-size:11px;margin:2px 0;">🐦 <a href="' . esc_url( $sns_edit ) . '">SNS投稿</a> <span style="color:#888;">（' . $status . '）</span></p>';
    }
}

// ── タイトル候補ボックス（stock_blog 編集画面）────────────
function stock_meta_box_title_candidates( WP_Post $post ): void {
    $json = get_post_meta( $post->ID, 'title_candidates', true );
    if ( ! $json ) {
        echo '<p style="font-size:12px;color:#888;">ブログ記事生成時に自動で追加されます</p>';
        return;
    }

    $candidates = json_decode( $json, true );
    if ( empty( $candidates ) ) return;

    echo '<p style="font-size:11px;color:#888;margin:0 0 6px;">クリックでタイトルをコピー</p>';
    echo '<ol style="margin:0;padding-left:16px;">';
    foreach ( $candidates as $i => $c ) {
        echo '<li style="margin-bottom:8px;font-size:12px;">';
        echo '<span style="cursor:pointer;color:#0073aa;" onclick="navigator.clipboard.writeText(this.dataset.title);this.style.color=\'#00a32a\';" data-title="' . esc_attr( $c ) . '">';
        echo esc_html( $c );
        echo '</span>';
        echo '</li>';
    }
    echo '</ol>';
}
