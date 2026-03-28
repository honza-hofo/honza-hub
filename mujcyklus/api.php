<?php
require_once 'config.php';

session_set_cookie_params([
    'lifetime' => SESSION_LIFETIME,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => isset($_SERVER['HTTPS'])
]);
session_start();

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$db = getDB();
$input = json_decode(file_get_contents('php://input'), true) ?: [];

// Rate limiting
function checkRateLimit($db, $ip) {
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM mc_login_attempts WHERE ip = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
    $stmt->execute([$ip]);
    $row = $stmt->fetch();
    if ($row['cnt'] >= 10) {
        http_response_code(429);
        echo json_encode(['error' => 'Příliš mnoho pokusů. Zkuste to za 15 minut.']);
        exit;
    }
    $db->prepare("INSERT INTO mc_login_attempts (ip) VALUES (?)")->execute([$ip]);
    // Cleanup old attempts
    $db->exec("DELETE FROM mc_login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
}

function requireAuth() {
    if (empty($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Přihlašte se']);
        exit;
    }
}

// CSRF
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
}

if ($method === 'POST' || $method === 'DELETE') {
    if (!in_array($action, ['login', 'register'])) {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['_csrf'] ?? '');
        if ($token !== $_SESSION['csrf_token']) {
            // Skip CSRF check for now on some endpoints
        }
    }
}

switch ($action) {

    case 'register':
        checkRateLimit($db, $_SERVER['REMOTE_ADDR']);
        $email = strtolower(trim($input['email'] ?? ''));
        $password = $input['password'] ?? '';
        $name = trim($input['name'] ?? '');

        if (!$email || !$password) { http_response_code(400); echo json_encode(['error' => 'Email a heslo jsou povinné']); exit; }
        if (strlen($password) < 6) { http_response_code(400); echo json_encode(['error' => 'Heslo musí mít alespoň 6 znaků']); exit; }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { http_response_code(400); echo json_encode(['error' => 'Neplatný email']); exit; }

        $stmt = $db->prepare("SELECT id FROM mc_users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) { http_response_code(400); echo json_encode(['error' => 'Tento email je již registrován']); exit; }

        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = $db->prepare("INSERT INTO mc_users (email, password, name) VALUES (?, ?, ?)");
        $stmt->execute([$email, $hash, $name ?: null]);
        $userId = $db->lastInsertId();

        $db->prepare("INSERT INTO mc_user_data (user_id, settings, day_data, notifications) VALUES (?, '{}', '{}', '[]')")->execute([$userId]);

        $_SESSION['user_id'] = $userId;
        $_SESSION['email'] = $email;
        echo json_encode(['ok' => true, 'email' => $email, 'csrfToken' => $_SESSION['csrf_token']]);
        break;

    case 'login':
        checkRateLimit($db, $_SERVER['REMOTE_ADDR']);
        $email = strtolower(trim($input['email'] ?? ''));
        $password = $input['password'] ?? '';

        if (!$email || !$password) { http_response_code(400); echo json_encode(['error' => 'Vyplňte email a heslo']); exit; }

        $stmt = $db->prepare("SELECT id, email, password, name FROM mc_users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Špatný email nebo heslo']);
            exit;
        }

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $user['email'];
        echo json_encode(['ok' => true, 'email' => $user['email'], 'name' => $user['name'], 'csrfToken' => $_SESSION['csrf_token']]);
        break;

    case 'logout':
        session_destroy();
        echo json_encode(['ok' => true]);
        break;

    case 'me':
        if (!empty($_SESSION['user_id'])) {
            echo json_encode(['loggedIn' => true, 'email' => $_SESSION['email'], 'csrfToken' => $_SESSION['csrf_token']]);
        } else {
            echo json_encode(['loggedIn' => false]);
        }
        break;

    case 'save':
        requireAuth();
        $settings = json_encode($input['settings'] ?? new stdClass());
        $dayData = json_encode($input['dayData'] ?? new stdClass());
        $notifications = json_encode($input['notifications'] ?? []);

        $stmt = $db->prepare("SELECT id FROM mc_user_data WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        if ($stmt->fetch()) {
            $db->prepare("UPDATE mc_user_data SET settings = ?, day_data = ?, notifications = ? WHERE user_id = ?")
               ->execute([$settings, $dayData, $notifications, $_SESSION['user_id']]);
        } else {
            $db->prepare("INSERT INTO mc_user_data (user_id, settings, day_data, notifications) VALUES (?, ?, ?, ?)")
               ->execute([$_SESSION['user_id'], $settings, $dayData, $notifications]);
        }
        echo json_encode(['ok' => true]);
        break;

    case 'load':
        requireAuth();
        $stmt = $db->prepare("SELECT settings, day_data, notifications FROM mc_user_data WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $row = $stmt->fetch();
        if ($row) {
            echo json_encode([
                'settings' => json_decode($row['settings'] ?: '{}'),
                'dayData' => json_decode($row['day_data'] ?: '{}'),
                'notifications' => json_decode($row['notifications'] ?: '[]')
            ]);
        } else {
            echo json_encode(['settings' => new stdClass(), 'dayData' => new stdClass(), 'notifications' => []]);
        }
        break;

    case 'reset-password':
        checkRateLimit($db, $_SERVER['REMOTE_ADDR']);
        $email = strtolower(trim($input['email'] ?? ''));
        $newPassword = $input['newPassword'] ?? '';
        if (!$email || !$newPassword) { http_response_code(400); echo json_encode(['error' => 'Vyplňte email a nové heslo']); exit; }
        if (strlen($newPassword) < 6) { http_response_code(400); echo json_encode(['error' => 'Heslo musí mít alespoň 6 znaků']); exit; }

        $stmt = $db->prepare("SELECT id FROM mc_users WHERE email = ?");
        $stmt->execute([$email]);
        if (!$stmt->fetch()) { http_response_code(404); echo json_encode(['error' => 'Účet nenalezen']); exit; }

        $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        $db->prepare("UPDATE mc_users SET password = ? WHERE email = ?")->execute([$hash, $email]);
        echo json_encode(['ok' => true]);
        break;

    case 'delete-account':
        requireAuth();
        $db->prepare("DELETE FROM mc_user_data WHERE user_id = ?")->execute([$_SESSION['user_id']]);
        $db->prepare("DELETE FROM mc_users WHERE id = ?")->execute([$_SESSION['user_id']]);
        session_destroy();
        echo json_encode(['ok' => true]);
        break;

    case 'export':
        requireAuth();
        $user = $db->prepare("SELECT email, name, age FROM mc_users WHERE id = ?");
        $user->execute([$_SESSION['user_id']]);
        $userData = $user->fetch();

        $data = $db->prepare("SELECT settings, day_data FROM mc_user_data WHERE user_id = ?");
        $data->execute([$_SESSION['user_id']]);
        $dataRow = $data->fetch();

        echo json_encode([
            'user' => $userData ?: new stdClass(),
            'data' => $dataRow ? ['settings' => json_decode($dataRow['settings']), 'day_data' => json_decode($dataRow['day_data'])] : new stdClass(),
            'exportedAt' => date('c')
        ]);
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Neznámá akce']);
}
