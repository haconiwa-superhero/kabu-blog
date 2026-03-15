# システムアーキテクチャ

## 全体構成図

```
GPTs（分析）
      │
      │ JSON
      ▼
   API受信
      │
      ▼
 WordPress
  （MySQL）
      │
      ├── 記事生成（OpenAI API）
      │
      ├── X投稿生成（OpenAI API）
      │
      └── X投稿（X API）
```

---

## 技術スタック

| 役割 | 技術 | 理由 |
|---|---|---|
| CMS | WordPress | ロリポップで運用可能・MySQL・REST API・拡張性 |
| DB | MySQL（ロリポップ） | WordPress標準 |
| AI生成 | OpenAI API | 記事生成・SNS投稿生成 |
| SNS連携 | X API | 投稿・将来的に反応取得 |

---

## 公開フロー

### Phase1（半自動 / MVP）

```
JSON登録 → 記事下書き生成 → 管理画面で確認 → 手動公開 → X投稿
```

### Phase2（自動化）

```
JSON登録 → 記事生成 → 自動公開 → 自動X投稿（cron）
```
