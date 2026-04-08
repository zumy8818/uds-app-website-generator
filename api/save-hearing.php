<?php
/**
 * save-hearing.php
 * Replitヒアリングアプリからヒアリングデータを受け取りDBに保存し、
 * デモページを同一サーバー内で直接生成するAPI
 *
 * リクエスト: POST /api/save-hearing.php
 * パラメータ: hearing_data (JSON文字列)
 * レスポンス: JSON { success, hearing_id, demo_url, company_name, error }
 */

// ─────────────────────────────────────────────
// 定数（generate-demo.php と共有。二重定義防止）
// ─────────────────────────────────────────────
defined('DB_HOST') || define('DB_HOST', 'mysql320.phy.lolipop.lan');
defined('DB_NAME') || define('DB_NAME', 'LAA1380072-udswebgen');
defined('DB_USER') || define('DB_USER', 'LAA1380072');
defined('DB_PASS') || define('DB_PASS', '');  // 要設定

defined('ANTHROPIC_API_KEY') || define('ANTHROPIC_API_KEY', '');  // 要設定
defined('CLAUDE_MODEL')      || define('CLAUDE_MODEL',      'claude-sonnet-4-5');
defined('CLAUDE_MAX_TOKENS') || define('CLAUDE_MAX_TOKENS', 8000);

defined('NOTIFY_EMAIL')   || define('NOTIFY_EMAIL',   'zumy8818@gmail.com');
defined('DEMO_BASE_PATH') || define('DEMO_BASE_PATH', '/home/hippy.jp-scarecrowman8818/ubuyama-digital-service.com/demo');
defined('DEMO_BASE_URL')  || define('DEMO_BASE_URL',  'https://ubuyama-digital-service.com/demo');

// generate-demo.php の関数を読み込む（直接実行はしない）
require_once __DIR__ . '/generate-demo.php';

// ─────────────────────────────────────────────
// メイン処理
// ─────────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('POSTリクエストのみ受け付けます', 405);
    }

    // hearing_data を取得（フォームPOST または JSON bodyの両方に対応）
    $raw = $_POST['hearing_data'] ?? null;
    if ($raw === null) {
        $body = file_get_contents('php://input');
        $decoded = json_decode($body, true);
        $raw = $decoded['hearing_data'] ?? null;
    }

    if (empty($raw)) {
        throw new Exception('hearing_data が空です', 400);
    }

    // JSONとして正しいか検証
    $parsed = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('hearing_data のJSON形式が不正です: ' . json_last_error_msg(), 400);
    }

    // 1. DBに保存
    $hearing_id = saveHearing($raw);

    // 2. 同一サーバー内でデモページを直接生成
    $result = runGenerateDemo($hearing_id);

    echo json_encode([
        'success'      => true,
        'hearing_id'   => $hearing_id,
        'demo_url'     => $result['url'],
        'company_name' => $result['company_name'],
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    $status = $e->getCode() >= 400 ? $e->getCode() : 500;
    http_response_code($status);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}

// ─────────────────────────────────────────────
// DBにヒアリングデータを保存
// ─────────────────────────────────────────────
function saveHearing(string $hearing_data_json): int
{
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $stmt = $pdo->prepare(
        'INSERT INTO hearings (hearing_data, created_at) VALUES (?, NOW())'
    );
    $stmt->execute([$hearing_data_json]);

    return (int) $pdo->lastInsertId();
}
