#!/usr/bin/env node

/**
 * =============================================================
 * 5枚テンプレートページ 一括自動生成スクリプト
 * =============================================================
 * 
 * 【使い方】
 *   node generate-full-site.js data/sample-hearing.json
 * 
 * 【生成されるページ】
 *   1. index.html    - トップページ（デモ版より高品質）
 *   2. about.html    - 会社・事業紹介ページ
 *   3. service.html  - サービス紹介ページ
 *   4. works.html    - 実績・事例ページ
 *   5. contact.html  - お問い合わせページ
 * 
 * 【注意】
 *   API呼び出しが5回発生するため、5〜10分かかります
 * =============================================================
 */

const fs = require('fs');
const path = require('path');
const https = require('https');

const ANTHROPIC_API_KEY = process.env.ANTHROPIC_API_KEY;
const MODEL = 'claude-sonnet-4-5';
const OUTPUT_DIR = path.join(__dirname, '..', 'output', 'full');

// ─────────────────────────────────────────────
// 生成するページの定義
// ─────────────────────────────────────────────
const PAGES = [
  {
    filename: 'index.html',
    name: 'トップページ',
    type: 'top',
    description: '訪問者が最初に見る、会社の顔となるページ'
  },
  {
    filename: 'about.html',
    name: '会社・事業紹介ページ',
    type: 'about',
    description: '会社のビジョン、歴史、スタッフ紹介など'
  },
  {
    filename: 'service.html',
    name: 'サービス紹介ページ',
    type: 'service',
    description: '各サービスの詳細な説明と料金・流れ'
  },
  {
    filename: 'works.html',
    name: '実績・事例ページ',
    type: 'works',
    description: '過去の実績や事例をわかりやすく掲載'
  },
  {
    filename: 'contact.html',
    name: 'お問い合わせページ',
    type: 'contact',
    description: 'フォーム付きの問い合わせページ'
  }
];

// ─────────────────────────────────────────────
// メイン処理
// ─────────────────────────────────────────────
async function main() {
  const jsonPath = process.argv[2];
  if (!jsonPath) {
    console.error('❌ エラー: JSONファイルのパスを指定してください');
    process.exit(1);
  }

  if (!ANTHROPIC_API_KEY) {
    console.error('❌ エラー: ANTHROPIC_API_KEY 環境変数が設定されていません');
    process.exit(1);
  }

  const hearingData = JSON.parse(fs.readFileSync(jsonPath, 'utf-8'));
  console.log(`\n🏢 ${hearingData.company.name} のWebサイトを生成します`);
  console.log(`📄 生成ページ数: ${PAGES.length}枚\n`);

  if (!fs.existsSync(OUTPUT_DIR)) {
    fs.mkdirSync(OUTPUT_DIR, { recursive: true });
  }

  // 共通ナビゲーションHTML（全ページで統一）
  const navHTML = buildNavHTML(hearingData);

  // 各ページを順番に生成
  for (let i = 0; i < PAGES.length; i++) {
    const page = PAGES[i];
    console.log(`\n[${i + 1}/${PAGES.length}] ${page.name} を生成中...`);
    
    try {
      const html = await generatePage(page, hearingData, navHTML);
      const outputPath = path.join(OUTPUT_DIR, page.filename);
      fs.writeFileSync(outputPath, html, 'utf-8');
      console.log(`  ✅ 保存完了: ${outputPath}`);
    } catch (err) {
      console.error(`  ❌ エラー: ${err.message}`);
    }

    // API制限対策（連続呼び出しに少し間隔を空ける）
    if (i < PAGES.length - 1) {
      console.log('  ⏳ 次のページの生成まで3秒待機...');
      await sleep(3000);
    }
  }

  // 完了レポート
  console.log('\n' + '='.repeat(50));
  console.log('🎉 全ページの生成が完了しました！');
  console.log('='.repeat(50));
  console.log(`\n📁 出力フォルダ: ${OUTPUT_DIR}\n`);
  PAGES.forEach(page => {
    console.log(`  ✓ ${page.filename.padEnd(15)} - ${page.name}`);
  });
  console.log('\n💡 次のステップ:');
  console.log('   1. ブラウザで各ページを開いて確認');
  console.log('   2. 必要に応じてテキスト・画像を調整');
  console.log('   3. サーバーにアップロードして公開\n');
}

// ─────────────────────────────────────────────
// 各ページのHTML生成
// ─────────────────────────────────────────────
async function generatePage(page, data, navHTML) {
  const prompt = buildPagePrompt(page, data, navHTML);
  const response = await callClaudeAPI(prompt);
  
  // コードブロックからHTMLを抽出（複数パターンに対応）
  const htmlMatch = response.match(/```html\n?([\s\S]*?)```/);
  if (htmlMatch) return htmlMatch[1].trim();
  
  // <!DOCTYPE html> から </html> までを直接抽出
  const doctypeMatch = response.match(/(<!DOCTYPE[\s\S]*?<\/html>)/i);
  if (doctypeMatch) return doctypeMatch[1].trim();
  
  return response.trim();
}

// ─────────────────────────────────────────────
// ナビゲーション共通HTML
// ─────────────────────────────────────────────
function buildNavHTML(data) {
  return `
<nav>
  <div class="nav-logo">${data.company.name}</div>
  <ul class="nav-links">
    <li><a href="index.html">ホーム</a></li>
    <li><a href="about.html">会社情報</a></li>
    <li><a href="service.html">サービス</a></li>
    <li><a href="works.html">実績</a></li>
    <li><a href="contact.html">お問い合わせ</a></li>
  </ul>
</nav>
`.trim();
}

// ─────────────────────────────────────────────
// ページ別プロンプト生成
// ─────────────────────────────────────────────
function buildPagePrompt(page, data, navHTML) {
  const commonRequirements = `
## 共通要件
- プライマリカラー: ${data.brand.color_primary}
- セカンダリカラー: ${data.brand.color_secondary}
- スマートフォン対応（レスポンシブ）
- 外部CSSフレームワーク不使用
- Google Fonts（Noto Sans JP）使用可
- 以下のナビゲーションを必ず使用: 
  ${navHTML}
- フッターには連絡先を記載: ${data.contact.phone} / ${data.contact.email}
- \`\`\`html で始まり \`\`\` で終わるコードブロックで出力
`;

  const pagePrompts = {
    top: `
トップページ（index.html）を作成してください。
会社: ${data.company.name}
キャッチコピー: 「${data.company.tagline}」を元に、より魅力的なキャッチコピーを考えてください
強み: ${data.strengths.join(' / ')}
ターゲット: ${data.target_customers}
CTA文言: 「${data.cta_message}」

構成: ヒーロー → 強み3つ → サービス概要 → 会社概要 → CTA → フッター
`,
    about: `
会社・事業紹介ページ（about.html）を作成してください。
会社名: ${data.company.name}
説明: ${data.company.description}
創業: ${data.company.founding_year}年
従業員: ${data.company.employees}名
所在地: ${data.company.location}

構成: ページヘッダー → 会社概要 → ミッション・ビジョン → 沿革（創業からの歴史をフィクションで） → スタッフ紹介（フィクション3名） → アクセスマップエリア → フッター
`,
    service: `
サービス紹介ページ（service.html）を作成してください。
サービス一覧:
${data.services.map(s => `- ${s.name}: ${s.description}`).join('\n')}

構成: ページヘッダー → サービス概要 → 各サービス詳細（料金・流れ・特徴） → よくある質問（FAQ）5つ → CTA → フッター
FAQは業種に合わせた実際によくある質問を考えて記載してください。
`,
    works: `
実績・事例ページ（works.html）を作成してください。
業種: ${data.company.description}

構成: ページヘッダー → 実績数字（件数・年数など） → 事例カード6件（フィクション、画像はUnsplash使用） → お客様の声3件 → CTA → フッター
事例は具体的で信頼感のある内容にしてください。
`,
    contact: `
お問い合わせページ（contact.html）を作成してください。
電話: ${data.contact.phone}
メール: ${data.contact.email}
営業時間: ${data.contact.hours}
所在地: ${data.company.location}

構成: ページヘッダー → 連絡先情報 → お問い合わせフォーム（名前・メール・電話・お問い合わせ種別・メッセージ・送信ボタン） → アクセス情報 → フッター
フォームは見た目が美しいものにしてください（実際の送信機能は不要）。
`
  };

  return `あなたはプロのWebデザイナーです。以下の要件でHTMLページを作成してください。

## 会社情報
${JSON.stringify(data.company, null, 2)}

## ブランド情報
${JSON.stringify(data.brand, null, 2)}

## 作成するページ
${pagePrompts[page.type]}

${commonRequirements}
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
      messages: [{ role: 'user', content: prompt }]
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

const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));

main().catch(err => {
  console.error('❌ エラー:', err.message);
  process.exit(1);
});
