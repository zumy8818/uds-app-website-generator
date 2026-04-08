# uds-app-website-generator

ヒアリングデータ（JSON）からWebサイトのHTMLを自動生成するシステム。
Claude APIを使ってプロ品質のWebページを生成し、ロリポップサーバーへの納品データを作成する。

## システム概要

```
Replitヒアリングアプリ
       ↓ (ヒアリングJSON出力)
  このシステム（Node.js）
       ↓ (Claude API呼び出し)
   生成HTML（output/）
       ↓ (手動アップロード)
  ロリポップサーバー (MySQL連携予定)
```

## ディレクトリ構成

```
uds-app-website-generator/
├── data/                        # ヒアリングデータ（JSON）
│   └── sample-hearing.json      # サンプルデータ（不動産業）
├── scripts/                     # 生成スクリプト
│   ├── generate-demo-top.js     # デモ用トップページ1枚生成
│   └── generate-full-site.js    # 5ページ一括生成
├── output/                      # 生成されたHTML（.gitignore対象）
│   ├── demo/index.html
│   └── full/*.html
├── .env                         # 環境変数（.gitignore対象）
├── .gitignore
└── CLAUDE.md
```

## 環境変数

`.env` ファイルをルートに作成して設定する：

```
ANTHROPIC_API_KEY=sk-ant-xxxxxxxxxxxx
```

または直接エクスポート：

```bash
export ANTHROPIC_API_KEY=sk-ant-xxxxxxxxxxxx
```

## 実行方法

### デモ用トップページ生成（1ページ、約30〜60秒）

```bash
node scripts/generate-demo-top.js data/sample-hearing.json
```

出力先: `output/demo/index.html`

使用モデル: `claude-opus-4-5`（高品質）

### 5ページ一括生成（約5〜10分）

```bash
node scripts/generate-full-site.js data/sample-hearing.json
```

出力先: `output/full/`

| ファイル | 内容 |
|---|---|
| index.html | トップページ |
| about.html | 会社・事業紹介 |
| service.html | サービス紹介 |
| works.html | 実績・事例 |
| contact.html | お問い合わせ |

使用モデル: `claude-sonnet-4-5`（速度・品質バランス）

## ヒアリングJSONの形式

`data/sample-hearing.json` を参照。主なフィールド：

- `company` - 会社名・説明・創業年・従業員数・所在地
- `brand` - カラーコード・フォントスタイル・キーワード
- `services` - サービス一覧（名前・説明・アイコン）
- `strengths` - 強み（3項目）
- `target_customers` - ターゲット顧客
- `contact` - 電話・メール・営業時間

## 外部連携

### Replitヒアリングアプリ
- 顧客へのヒアリングを実施するアプリ
- 結果をJSON形式で出力し、このシステムに渡す

### ロリポップサーバー（MySQL）
- 生成したHTMLのホスティング先
- 今後、MySQL連携による動的なデータ管理を予定

## 注意事項

- `output/` は `.gitignore` 対象のため、生成HTMLはGit管理しない
- API呼び出しごとにトークン消費が発生するため、不要な再生成は避ける
- 5ページ一括生成はAPI制限対策として各ページ間に3秒の待機を挟む
