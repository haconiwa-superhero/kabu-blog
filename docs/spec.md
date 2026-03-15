# 開発仕様書

## WordPress構成

### カスタム投稿タイプ

```
stock_analysis
```

1投稿 = 1銘柄分析

### 必須プラグイン候補

- ACF（Advanced Custom Fields）
- Custom Post Type UI

---

## カスタムフィールド一覧

| フィールド名 | 型 | 内容 |
|---|---|---|
| ticker | string | 銘柄コード（例: 6266） |
| company | string | 企業名 |
| sector | string | セクター |
| analysis_date | date | 分析日 |
| investment_thesis | string | 投資判断 |
| summary | text | サマリー |
| thesis | text | 投資仮説 |
| growth | text | 成長要因 |
| risk | text | リスク |
| valuation | text | バリュエーション |
| post_content | longtext | 記事本文 |
| x_post | text | X投稿文 |
| x_thread | longtext | Xスレッド |
| published | boolean | 公開状態 |

---

## DB構造

WordPress標準テーブルを使用：

```
wp_posts      — 記事データ
wp_postmeta   — カスタムフィールドデータ
```

---

## API設計

### エンドポイント

```
POST /wp-json/stock/v1/create
```

### リクエスト

```json
{
  "analysis_data": {
    "ticker": "6266",
    "company": "タツモ",
    "sector": "半導体装置",
    "summary": "...",
    "thesis": "...",
    "growth": "...",
    "risk": "...",
    "valuation": "..."
  }
}
```

### 処理フロー

1. JSON受信
2. OpenAI APIで記事・X投稿文を生成
3. wp_posts / wp_postmeta に保存
4. WordPress下書きとして作成

---

## 記事生成（OpenAI API）

生成項目：
- タイトル
- 本文
- 要約

---

## X投稿生成（OpenAI API）

| 項目 | 内容 |
|---|---|
| 文字数 | 120〜140文字 |
| 目的 | クリック誘導（ブログへの流入） |

### 投稿例

```
半導体装置銘柄「タツモ」

・営業利益過去最高
・受注残が急増
・AI需要の恩恵

ただしPERはすでに高水準

今後の焦点は
中国市場と設備投資

詳しい分析はこちら👇
URL
```

---

## X API

```
POST /tweets

{
  "text": "..."
}
```

cron設定：記事公開時にX投稿を実行（Phase2）

---

## URL設計

| パス | 内容 |
|---|---|
| `/analysis/{ticker}` | 銘柄分析ページ（例: `/analysis/6266`） |
| `/stocks` | 銘柄一覧（将来） |
| `/sector/{name}` | セクター別（将来） |
| `/ranking` | ランキング（将来） |

---

## SEOタイトル形式

```
【銘柄分析】{企業名}（{ticker}）
```

例：`【銘柄分析】タツモ（6266）`

---

## セキュリティ

- APIキー認証
- WordPress nonce

---

## MVP開発範囲

- [ ] WordPress構築
- [ ] カスタム投稿タイプ（stock_analysis）設定
- [ ] カスタムフィールド設定
- [ ] JSON登録APIエンドポイント実装
- [ ] OpenAI APIによる記事生成
- [ ] X投稿文生成
