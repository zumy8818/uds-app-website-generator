<?php
/**
 * save-hearing.php
 * Replitヒアリングアプリからヒアリングデータを受け取りDBに保存するAPI
 *
 * リクエスト: POST /api/save-hearing.php
 * パラメータ: hearing_data (JSON文字列)
 * レスポンス: JSON { success, hearing_id, error }
 */

// ─────────────────────────────────────────────
// 定数
// ─────────────────────────────────────────────
define('DB_HOST', 'mysql320.phy.lolipop.lan');
define('DB_NAME', 'LAA1380072-udswebgen');
define('DB_USER', 'LAA1380072');
define('DB_PASS', '');  // 要設定

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

    // DBに保存
    $hearing_id = saveHearing($raw);

    echo json_encode([
        'success'    => true,
        'hearing_id' => $hearing_id,
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
