<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * 設定ページ（APIキー管理）
 */
add_action( 'admin_menu', function () {
    add_options_page(
        'AI株分析 設定',
        'AI株分析',
        'manage_options',
        'stock-analysis-settings',
        'stock_settings_page'
    );
} );

add_action( 'admin_init', function () {
    register_setting( 'stock_analysis_settings', 'stock_analysis_openai_key' );
    register_setting( 'stock_analysis_settings', 'stock_analysis_api_key' );
    register_setting( 'stock_analysis_settings', 'stock_x_api_key' );
    register_setting( 'stock_analysis_settings', 'stock_x_api_secret' );
    register_setting( 'stock_analysis_settings', 'stock_x_access_token' );
    register_setting( 'stock_analysis_settings', 'stock_x_access_secret' );
} );

function stock_settings_page(): void {
    ?>
    <div class="wrap">
        <h1>AI株分析 設定</h1>
        <form method="post" action="options.php">
            <?php settings_fields( 'stock_analysis_settings' ); ?>
            <h2>OpenAI</h2>
            <table class="form-table">
                <tr>
                    <th>OpenAI API キー</th>
                    <td>
                        <input type="password" name="stock_analysis_openai_key" style="width:400px;"
                            value="<?php echo esc_attr( get_option( 'stock_analysis_openai_key', '' ) ); ?>">
                        <p class="description">記事生成・SNS文生成に使用します</p>
                    </td>
                </tr>
            </table>

            <h2>Stock Analysis REST API</h2>
            <table class="form-table">
                <tr>
                    <th>APIキー（X-Stock-Api-Key）</th>
                    <td>
                        <input type="text" name="stock_analysis_api_key" style="width:400px;"
                            value="<?php echo esc_attr( get_option( 'stock_analysis_api_key', 'dev-secret' ) ); ?>">
                        <p class="description">GPTsからのリクエスト認証に使います</p>
                    </td>
                </tr>
            </table>

            <h2>X (Twitter) API</h2>
            <table class="form-table">
                <?php
                $x_fields = [
                    'stock_x_api_key'       => 'API Key (Consumer Key)',
                    'stock_x_api_secret'    => 'API Key Secret',
                    'stock_x_access_token'  => 'Access Token',
                    'stock_x_access_secret' => 'Access Token Secret',
                ];
                foreach ( $x_fields as $option => $label ) :
                    $value = get_option( $option, '' );
                ?>
                <tr>
                    <th><?php echo esc_html( $label ); ?></th>
                    <td>
                        <input type="password" name="<?php echo esc_attr( $option ); ?>" style="width:400px;"
                            value="<?php echo esc_attr( $value ); ?>">
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>

            <?php submit_button( '保存' ); ?>
        </form>

        <h2>APIエンドポイント一覧</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead><tr><th>メソッド</th><th>エンドポイント</th><th>用途</th></tr></thead>
            <tbody>
                <tr><td>POST</td><td><code>/wp-json/stock/v1/store</code></td><td>分析データ保存（GPTsから送信）</td></tr>
                <tr><td>POST</td><td><code>/wp-json/stock/v1/generate/{id}</code></td><td>ブログ記事生成（OpenAI）</td></tr>
                <tr><td>POST</td><td><code>/wp-json/stock/v1/sns/{id}</code></td><td>SNS投稿文生成（OpenAI）</td></tr>
                <tr><td>POST</td><td><code>/wp-json/stock/v1/tweet/{id}</code></td><td>X投稿（mode: post or thread）</td></tr>
            </tbody>
        </table>
    </div>
    <?php
}
