<?php
/**
 * generate-demo.php
 * ヒアリングデータからデモ用トップページを自動生成するAPI
 *
 * 【単体呼び出し】POST /api/generate-demo.php  hearing_id=1
 * 【include呼び出し】save-hearing.php から require_once で使用
 * レスポンス: JSON { success, url, company_name, error }
 */

// ─────────────────────────────────────────────
// 定数（二重定義防止）
// ─────────────────────────────────────────────
defined('DB_HOST')          || define('DB_HOST',          'mysql320.phy.lolipop.lan');
defined('DB_NAME')          || define('DB_NAME',          'LAA1380072-udswebgen');
defined('DB_USER')          || define('DB_USER',          'LAA1380072');
defined('DB_PASS')          || define('DB_PASS',          '');  // 要設定

defined('ANTHROPIC_API_KEY') || define('ANTHROPIC_API_KEY', '');  // 要設定
defined('CLAUDE_MODEL')      || define('CLAUDE_MODEL',      'claude-sonnet-4-5');
defined('CLAUDE_MAX_TOKENS') || define('CLAUDE_MAX_TOKENS', 8000);

defined('NOTIFY_EMAIL')     || define('NOTIFY_EMAIL',     'zumy8818@gmail.com');
defined('DEMO_BASE_PATH')   || define('DEMO_BASE_PATH',   '/home/hippy.jp-scarecrowman8818/ubuyama-digital-service.com/demo');
defined('DEMO_BASE_URL')    || define('DEMO_BASE_URL',    'https://ubuyama-digital-service.com/demo');

// ─────────────────────────────────────────────
// 単体呼び出し時のみ直接実行
// ─────────────────────────────────────────────
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    header('Content-Type: application/json; charset=utf-8');
    try {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            throw new Exception('POSTリクエストのみ受け付けます', 405);
        }
        $hearing_id = filter_input(INPUT_POST, 'hearing_id', FILTER_VALIDATE_INT);
        if (!$hearing_id || $hearing_id <= 0) {
            throw new Exception('hearing_id が不正です', 400);
        }
        $result = runGenerateDemo($hearing_id);
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        $code   = is_numeric($e->getCode()) ? (int)$e->getCode() : 0;
        $status = ($code >= 400 && $code < 600) ? $code : 500;
        http_response_code($status);
        echo json_encode([
            'success' => false,
            'error'   => $e->getMessage(),
        ], JSON_UNESCAPED_UNICODE);
    }
}

// ─────────────────────────────────────────────
// メイン関数（include時もここを呼ぶ）
// ─────────────────────────────────────────────
function runGenerateDemo(int $hearing_id): array
{
    $hearing = fetchHearingData($hearing_id);
    $html    = generateTopPageHTML($hearing);
    $slug    = makeSlug($hearing['company_name']);
    $url     = saveHTML($slug, $html);
    sendNotification($hearing['company_name'], $url);

    return [
        'success'      => true,
        'url'          => $url,
        'company_name' => $hearing['company_name'],
    ];
}

// ─────────────────────────────────────────────
// DBからヒアリングデータ取得
// ─────────────────────────────────────────────
function fetchHearingData(int $hearing_id): array
{
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $stmt = $pdo->prepare('SELECT * FROM hearing_data WHERE id = ? LIMIT 1');
    $stmt->execute([$hearing_id]);
    $row = $stmt->fetch();

    if (!$row) {
        throw new Exception("hearing_id={$hearing_id} のデータが見つかりません", 404);
    }

    $data = json_decode($row['hearing_json'], true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('ヒアリングデータのJSON解析に失敗しました');
    }

    return $data;
}

// ─────────────────────────────────────────────
// Claude APIでTopページHTML生成
// ─────────────────────────────────────────────
function generateTopPageHTML(array $data): string
{
    if (empty(ANTHROPIC_API_KEY)) {
        throw new Exception('ANTHROPIC_API_KEY が設定されていません');
    }

    $prompt = buildPrompt($data);

    $body = json_encode([
        'model'      => CLAUDE_MODEL,
        'max_tokens' => CLAUDE_MAX_TOKENS,
        'messages'   => [
            ['role' => 'user', 'content' => $prompt],
        ],
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 120,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'x-api-key: ' . ANTHROPIC_API_KEY,
            'anthropic-version: 2023-06-01',
        ],
    ]);

    $response = curl_exec($ch);
    $curl_err  = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        throw new Exception('Claude API通信エラー: ' . $curl_err);
    }

    $parsed = json_decode($response, true);
    if (!empty($parsed['error'])) {
        throw new Exception('Claude APIエラー: ' . $parsed['error']['message']);
    }

    $raw_text = $parsed['content'][0]['text'] ?? '';

    if (preg_match('/```html\s*([\s\S]*?)```/i', $raw_text, $m)) {
        return trim($m[1]);
    }
    if (preg_match('/(<!DOCTYPE[\s\S]*?<\/html>)/i', $raw_text, $m)) {
        return trim($m[1]);
    }

    return trim($raw_text);
}

// ─────────────────────────────────────────────
// プロンプト構築
// ─────────────────────────────────────────────
function buildPrompt(array $data): string
{
    $brand           = $data['brand'] ?? [];
    $keywords        = implode('・', $brand['keywords'] ?? []);
    $color_primary   = $brand['color_primary']   ?? '';
    $color_secondary = $brand['color_secondary'] ?? '';
    $site_style      = $brand['site_style']      ?? '';
    $json            = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    return <<<PROMPT
あなたはプロのWebデザイナーです。
以下のヒアリングデータをもとに、見込み客に見せる「デモ用トップページ」のHTMLを1ファイルで作成してください。

## ヒアリングデータ
{$json}

## 作成要件

### デザイン要件
- モダンで洗練されたデザイン（2024年のトレンドを意識）
- スマートフォン対応（レスポンシブデザイン）
- プライマリカラー: {$color_primary}
- セカンダリカラー: {$color_secondary}
- サイトスタイル: {$site_style}

### 構成セクション（この順番で）
1. ヘッダー - ロゴ（社名テキスト）、ナビゲーション
2. ヒーローセクション - 大きなキャッチコピー、サブコピー、CTAボタン
3. 強みセクション - 3つの強みをカード形式で
4. サービスセクション - サービス一覧
5. 会社概要セクション - 設立年、従業員数、所在地など
6. CTAセクション - お問い合わせへの誘導
7. フッター - 連絡先情報

### 技術要件
- 外部CSSフレームワーク不使用（純粋なHTML/CSS/JSのみ）
- Google Fonts（Noto Sans JPなど）使用可
- アニメーション効果を適度に使用（スクロールフェードインなど）
- 画像はUnsplash APIのURL形式で実際に表示できるものを使用

### コピーライティング
- キャッチコピー: 会社の特徴と強みを活かした魅力的なもの
- 各セクションの文章は実際のビジネスに即した具体的な内容
- キーワード「{$keywords}」を自然に盛り込む

### 重要
- コードは完全に動作するものを出力
- ```html で始まり ``` で終わるコードブロック形式で出力
- <!DOCTYPE html> から </html> まで完全なHTMLファイル
PROMPT;
}

// ─────────────────────────────────────────────
// HTMLファイルを保存
// ─────────────────────────────────────────────
function saveHTML(string $slug, string $html): string
{
    $dir = DEMO_BASE_PATH . '/' . $slug;
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        throw new Exception("ディレクトリの作成に失敗しました: {$dir}");
    }

    $path = $dir . '/index.html';
    if (file_put_contents($path, $html) === false) {
        throw new Exception("HTMLファイルの保存に失敗しました: {$path}");
    }

    return DEMO_BASE_URL . '/' . $slug . '/index.html';
}

// ─────────────────────────────────────────────
// 会社名 → URLスラグ変換
// ─────────────────────────────────────────────
function makeSlug(string $company_name): string
{
    $slug = preg_replace('/[^\w\-]/u', '-', $company_name);
    $slug = preg_replace('/-+/', '-', $slug);
    $slug = trim($slug, '-');
    $slug = strtolower($slug);
    return $slug ?: 'company-' . time();
}

// ─────────────────────────────────────────────
// メール通知
// ─────────────────────────────────────────────
function sendNotification(string $company_name, string $url): void
{
    $to      = NOTIFY_EMAIL;
    $subject = '=?UTF-8?B?' . base64_encode("[UDS] デモサイト生成完了: {$company_name}") . '?=';
    $body    = <<<BODY
デモサイトの生成が完了しました。

会社名: {$company_name}
URL: {$url}

このメールは自動送信です。
BODY;

    $headers = implode("\r\n", [
        'From: noreply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'),
        'Content-Type: text/plain; charset=UTF-8',
    ]);

    mail($to, $subject, $body, $headers);
}
