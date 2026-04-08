#!/usr/bin/env node

/**
 * =============================================================
 * デモ用Topページ 自動生成スクリプト
 * =============================================================
 * 
 * 【使い方】
 *   node generate-demo-top.js data/sample-hearing.json
 * 
 * 【Claude Codeでの使い方】
 *   Claude Codeのターミナルで上記コマンドを実行するだけ！
 * 
 * 【何をするか】
 *   1. ヒアリングJSONを読み込む
 *   2. Claude APIにHTML生成を依頼する
 *   3. output/demo/index.html として保存する
 * =============================================================
 */

const fs = require('fs');
const path = require('path');
const https = require('https');

// ─────────────────────────────────────────────
// 設定
// ─────────────────────────────────────────────
const ANTHROPIC_API_KEY = process.env.ANTHROPIC_API_KEY;
const MODEL = 'claude-opus-4-5-20251101'; // 最新の高品質モデル
const OUTPUT_DIR = path.join(__dirname, '..', 'output', 'demo');

// ─────────────────────────────────────────────
// メイン処理
// ─────────────────────────────────────────────
async function main() {
  // 引数チェック
  const jsonPath = process.argv[2];
  if (!jsonPath) {
    console.error('❌ エラー: JSONファイルのパスを指定してください');
    console.error('   使い方: node generate-demo-top.js data/sample-hearing.json');
    process.exit(1);
  }

  if (!ANTHROPIC_API_KEY) {
    console.error('❌ エラー: ANTHROPIC_API_KEY 環境変数が設定されていません');
    console.error('   設定方法: export ANTHROPIC_API_KEY=sk-ant-...');
    process.exit(1);
  }

  // JSONを読み込む
  console.log('📂 ヒアリングデータを読み込み中...');
  const hearingData = JSON.parse(fs.readFileSync(jsonPath, 'utf-8'));
  console.log(`✅ 読み込み完了: ${hearingData.company.name}`);

  // Claude APIでHTML生成
  console.log('\n🤖 Claude AIがTopページを生成中... (30〜60秒かかります)');
  const html = await generateTopPage(hearingData);

  // ファイル保存
  if (!fs.existsSync(OUTPUT_DIR)) {
    fs.mkdirSync(OUTPUT_DIR, { recursive: true });
  }
  const outputPath = path.join(OUTPUT_DIR, 'index.html');
  fs.writeFileSync(outputPath, html, 'utf-8');

  console.log('\n✨ 完成！');
  console.log(`📄 保存先: ${outputPath}`);
  console.log('🌐 ブラウザで開いて確認してください');
}

// ─────────────────────────────────────────────
// Topページ生成（Claude API呼び出し）
// ─────────────────────────────────────────────
async function generateTopPage(data) {
  const prompt = buildPrompt(data);
  
  const response = await callClaudeAPI(prompt);
  
  // HTMLを抽出（```html ... ``` の中身を取り出す）
  const htmlMatch = response.match(/```html\n?([\s\S]*?)```/);
  if (htmlMatch) {
    return htmlMatch[1];
  }
  
  // コードブロックがなければそのまま返す
  return response;
}

// ─────────────────────────────────────────────
// プロンプト構築（ここが品質の核心！）
// ─────────────────────────────────────────────
function buildPrompt(data) {
  return `
あなたはプロのWebデザイナーです。
以下のヒアリングデータをもとに、見込み客に見せる「デモ用トップページ」のHTMLを1ファイルで作成してください。

## ヒアリングデータ
${JSON.stringify(data, null, 2)}

## 作成要件

### デザイン要件
- モダンで洗練されたデザイン（2024年のトレンドを意識）
- スマートフォン対応（レスポンシブデザイン）
- プライマリカラー: ${data.brand.color_primary}
- セカンダリカラー: ${data.brand.color_secondary}
- サイトスタイル: ${data.brand.site_style}

### 構成セクション（この順番で）
1. **ヘッダー** - ロゴ（社名テキスト）、ナビゲーション
2. **ヒーローセクション** - 大きなキャッチコピー、サブコピー、CTAボタン
3. **強みセクション** - 3つの強みをカード形式で
4. **サービスセクション** - サービス一覧
5. **会社概要セクション** - 設立年、従業員数、所在地など
6. **CTAセクション** - お問い合わせへの誘導
7. **フッター** - 連絡先情報

### 技術要件
- 外部CSSフレームワーク不使用（純粋なHTML/CSS/JSのみ）
- Google Fontsは使用可（Noto Sans JPなど）
- アニメーション効果を適度に使用（スクロールフェードインなど）
- 画像はUnsplash APIのURL形式で実際に表示できるものを使用
  例: https://images.unsplash.com/photo-XXXXX?w=1200&q=80
  不動産業なら: photo-1560518883-ce09059eeffa など

### コピーライティング
- キャッチコピー: 会社の特徴と強みを活かした魅力的なもの
- 各セクションの文章は実際のビジネスに即した具体的な内容
- キーワード「${data.brand.keywords.join('・')}」を自然に盛り込む

### 重要
- コードは完全に動作するものを出力
- \`\`\`html で始まり \`\`\` で終わるコードブロック形式で出力
- <!DOCTYPE html> から </html> まで完全なHTMLファイル
`.trim();
}

// ─────────────────────────────────────────────
// Claude API 呼び出し
// ─────────────────────────────────────────────
function callClaudeAPI(prompt) {
  return new Promise((resolve, reject) => {
    const body = JSON.stringify({
      model: MODEL,
      max_tokens: 8000,
      messages: [
        { role: 'user', content: prompt }
      ]
    });

    const options = {
      hostname: 'api.anthropic.com',
      path: '/v1/messages',
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'x-api-key': ANTHROPIC_API_KEY,
        'anthropic-version': '2023-06-01',
        'Content-Length': Buffer.byteLength(body)
      }
    };

    const req = https.request(options, (res) => {
      let data = '';
      res.on('data', chunk => data += chunk);
      res.on('end', () => {
        try {
          const parsed = JSON.parse(data);
          if (parsed.error) {
            reject(new Error(`API エラー: ${parsed.error.message}`));
          } else {
            resolve(parsed.content[0].text);
          }
        } catch (e) {
          reject(new Error(`レスポンス解析エラー: ${e.message}`));
        }
      });
    });

    req.on('error', reject);
    req.write(body);
    req.end();
  });
}

// 実行
main().catch(err => {
  console.error('❌ エラーが発生しました:', err.message);
  process.exit(1);
});
