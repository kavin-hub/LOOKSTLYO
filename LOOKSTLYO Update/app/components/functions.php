<?php
require_once __DIR__ . '/../../config/db.php';

function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(string $token): bool {
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function sanitizeInput(string $value): string {
    return trim(strip_tags($value));
}

// Simple login rate limiting (per session)
function recordFailedLogin(string $ip): void {
    if (!isset($_SESSION['failed_logins'])) $_SESSION['failed_logins'] = [];
    $_SESSION['failed_logins'][$ip] = ($_SESSION['failed_logins'][$ip] ?? 0) + 1;
}

function getFailedLoginCount(string $ip): int {
    return $_SESSION['failed_logins'][$ip] ?? 0;
}

function resetFailedLogin(string $ip): void {
    if (isset($_SESSION['failed_logins'][$ip])) unset($_SESSION['failed_logins'][$ip]);
}

function isLoginAllowed(string $ip): bool {
    // lock after 10 failed attempts in session
    return getFailedLoginCount($ip) < 10;
}

function isValidImageUpload(array $file, int $maxBytes = 3 * 1024 * 1024): bool {
    if (empty($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) return false;
    if ($file['size'] > $maxBytes) return false;
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    return isset($allowed[$mime]);
}

function createThumbnail(string $src, string $dest, int $maxW = 400, int $maxH = 400): bool {
    if (!file_exists($src)) return false;
    if (!extension_loaded('gd')) {
        return false;
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($src);
    switch ($mime) {
        case 'image/jpeg': $img = @imagecreatefromjpeg($src); break;
        case 'image/png': $img = @imagecreatefrompng($src); break;
        case 'image/webp': $img = @imagecreatefromwebp($src); break;
        default: return false;
    }
    if (!$img) return false;
    $w = imagesx($img); $h = imagesy($img);
    $ratio = min($maxW / $w, $maxH / $h, 1);
    $nw = (int)($w * $ratio); $nh = (int)($h * $ratio);
    $tmp = imagecreatetruecolor($nw, $nh);
    imagefill($tmp, 0, 0, imagecolorallocate($tmp, 255, 255, 255));
    imagecopyresampled($tmp, $img, 0,0,0,0, $nw, $nh, $w, $h);
    $ok = imagejpeg($tmp, $dest, 80);
    imagedestroy($img); imagedestroy($tmp);
    return $ok;
}

function getCartCount(PDO $pdo): int {
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(quantity), 0) AS total FROM cart WHERE session_id = ?');
    $stmt->execute([session_id()]);
    $row = $stmt->fetch();
    return (int) ($row['total'] ?? 0);
}

function getCartItems(PDO $pdo): array {
    $stmt = $pdo->prepare(
        'SELECT c.id AS cart_id, c.product_id, c.size, c.color, c.quantity, p.name, p.price, p.image, p.slug
         FROM cart c
         JOIN products p ON p.id = c.product_id
         WHERE c.session_id = ?
         ORDER BY c.created_at DESC'
    );
    $stmt->execute([session_id()]);
    return $stmt->fetchAll();
}

function addToCart(PDO $pdo, int $productId, string $size, string $color, int $quantity): void {
    $stmt = $pdo->prepare('SELECT id FROM cart WHERE session_id = ? AND product_id = ? AND size = ? AND color = ?');
    $stmt->execute([session_id(), $productId, $size, $color]);
    $row = $stmt->fetch();

    if ($row) {
        $update = $pdo->prepare('UPDATE cart SET quantity = quantity + ? WHERE id = ?');
        $update->execute([$quantity, $row['id']]);
        return;
    }

    $insert = $pdo->prepare('INSERT INTO cart (session_id, product_id, size, color, quantity) VALUES (?, ?, ?, ?, ?)');
    $insert->execute([session_id(), $productId, $size, $color, $quantity]);
}

function updateCartQuantity(PDO $pdo, int $cartId, int $quantity): void {
    if ($quantity <= 0) {
        removeCartItem($pdo, $cartId);
        return;
    }

    $stmt = $pdo->prepare('UPDATE cart SET quantity = ? WHERE id = ? AND session_id = ?');
    $stmt->execute([$quantity, $cartId, session_id()]);
}

function updateCartItem(PDO $pdo, int $cartId, int $quantity, string $size, string $color): void {
    if ($quantity <= 0) {
        removeCartItem($pdo, $cartId);
        return;
    }

    $stmt = $pdo->prepare('UPDATE cart SET quantity = ?, size = ?, color = ? WHERE id = ? AND session_id = ?');
    $stmt->execute([$quantity, $size, $color, $cartId, session_id()]);
}

function removeCartItem(PDO $pdo, int $cartId): void {
    $stmt = $pdo->prepare('DELETE FROM cart WHERE id = ? AND session_id = ?');
    $stmt->execute([$cartId, session_id()]);
}

// Session security: inactivity timeout, fingerprinting, periodic session id regen
function enforce_session_security(int $timeout = 1800, int $regenInterval = 900): void {
    if (session_status() !== PHP_SESSION_ACTIVE) return;
    $now = time();

    // Inactivity timeout
    if (!empty($_SESSION['last_activity']) && ($now - $_SESSION['last_activity']) > $timeout) {
        session_unset();
        session_destroy();
        return;
    }

    $_SESSION['last_activity'] = $now;

    // Simple fingerprint using IP and User-Agent
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $fingerprint = hash('sha256', $ip . '|' . $ua);

    if (empty($_SESSION['fingerprint'])) {
        $_SESSION['fingerprint'] = $fingerprint;
    } elseif (!hash_equals($_SESSION['fingerprint'], $fingerprint)) {
        // possible session hijack
        session_unset();
        session_destroy();
        return;
    }

    // Periodic session id regeneration
    if (empty($_SESSION['created'])) {
        $_SESSION['created'] = $now;
    } elseif (($now - $_SESSION['created']) > $regenInterval) {
        session_regenerate_id(true);
        $_SESSION['created'] = $now;
    }
}

// Enforce security on every include
enforce_session_security();

// --- TOTP / MFA helpers -------------------------------------------------
function base32_decode(string $b32): string {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $b32 = strtoupper(preg_replace('/[^A-Z2-7]/', '', $b32));
    $l = strlen($b32);
    $bits = '';
    for ($i = 0; $i < $l; $i++) {
        $val = strpos($alphabet, $b32[$i]);
        $bits .= str_pad(decbin($val), 5, '0', STR_PAD_LEFT);
    }
    $bytes = '';
    for ($i = 0; $i + 8 <= strlen($bits); $i += 8) {
        $bytes .= chr(bindec(substr($bits, $i, 8)));
    }
    return $bytes;
}

function base32_encode_random(int $length = 16): string {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bytes = random_bytes($length);
    $bits = '';
    for ($i = 0; $i < strlen($bytes); $i++) {
        $bits .= str_pad(decbin(ord($bytes[$i])), 8, '0', STR_PAD_LEFT);
    }
    $out = '';
    for ($i = 0; $i < strlen($bits); $i += 5) {
        $chunk = substr($bits, $i, 5);
        if (strlen($chunk) < 5) $chunk = str_pad($chunk, 5, '0');
        $out .= $alphabet[bindec($chunk)];
    }
    return $out;
}

function generate_totp_code(string $secret, int $time = null, int $digits = 6, int $timeStep = 30): string {
    $time = $time ?? time();
    $counter = floor($time / $timeStep);
    $secretKey = base32_decode($secret);
    $binaryCounter = pack('N*', 0) . pack('N*', $counter); // 64-bit
    $hash = hash_hmac('sha1', $binaryCounter, $secretKey, true);
    $offset = ord(substr($hash, -1)) & 0x0F;
    $binary = (ord($hash[$offset]) & 0x7F) << 24 |
              (ord($hash[$offset+1]) & 0xFF) << 16 |
              (ord($hash[$offset+2]) & 0xFF) << 8 |
              (ord($hash[$offset+3]) & 0xFF);
    $otp = $binary % pow(10, $digits);
    return str_pad((string)$otp, $digits, '0', STR_PAD_LEFT);
}

function verify_totp(string $secret, string $code, int $window = 1): bool {
    $code = preg_replace('/[^0-9]/', '', $code);
    if ($code === '') return false;
    $time = time();
    for ($i = -$window; $i <= $window; $i++) {
        if (hash_equals(generate_totp_code($secret, $time + ($i * 30)), $code)) return true;
    }
    return false;
}

function create_mfa_secret_for_admin(PDO $pdo, int $adminId): string {
    $secret = base32_encode_random(10);
    $stmt = $pdo->prepare('UPDATE admin SET mfa_secret = ? WHERE id = ?');
    $stmt->execute([$secret, $adminId]);
    return $secret;
}


function clearCart(PDO $pdo): void {
    $stmt = $pdo->prepare('DELETE FROM cart WHERE session_id = ?');
    $stmt->execute([session_id()]);
}

function getCartTotal(array $items): float {
    $total = 0.0;
    foreach ($items as $item) {
        $total += (float) $item['price'] * (int) $item['quantity'];
    }
    return $total;
}

function createOrderId(PDO $pdo): string {
    $count = (int) $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();
    return 'LS' . date('Y') . str_pad((string) ($count + 1), 5, '0', STR_PAD_LEFT);
}

function buildUpiQrData(?float $amount = null, string $note = ''): string {
    $pa = '9360232991@okbizaxis';
    $pn = 'LookStylo Clothing';
    $cu = 'INR';
    $params = [
        'pa' => $pa,
        'pn' => $pn,
        'cu' => $cu,
    ];
    if ($amount !== null && $amount > 0) {
        // format amount as two decimals
        $params['am'] = number_format($amount, 2, '.', '');
    }
    if ($note !== '') {
        $params['tn'] = $note;
    }
    $pairs = [];
    foreach ($params as $k => $v) {
        $pairs[] = $k . '=' . rawurlencode((string)$v);
    }
    return 'upi://pay?' . implode('&', $pairs);
}

// WhatsApp Notification Functions (Meta Cloud API)
function loadEnvConfig(): void {
    $envFile = __DIR__ . '/../../environments/.env';
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos($line, '#') === 0) continue; // Skip comments
            if (strpos($line, '=') === false) continue;
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if (!empty($key) && !empty($value)) {
                putenv("$key=$value");
            }
        }
    }
}

function testWhatsAppConnection(): bool {
    loadEnvConfig();
    $accessToken = getenv('META_ACCESS_TOKEN');
    $phoneNumberId = getenv('META_PHONE_NUMBER_ID');
    
    if (empty($accessToken) || empty($phoneNumberId)) {
        return false;
    }
    
    $url = "https://graph.instagram.com/v18.0/{$phoneNumberId}?access_token={$accessToken}";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $httpCode === 200;
}

function sendWhatsAppMessage(string $phoneNumber, string $message): bool {
    loadEnvConfig();
    
    $accessToken = getenv('META_ACCESS_TOKEN');
    $phoneNumberId = getenv('META_PHONE_NUMBER_ID');
    $apiVersion = getenv('META_WHATSAPP_API_VERSION') ?: 'v18.0';
    
    // Normalize phone number to E.164 format (+91XXXXXXXXXX)
    $cleanPhone = preg_replace('/[^0-9]/', '', $phoneNumber);
    if (strlen($cleanPhone) === 12 && strpos($cleanPhone, '91') === 0) {
        $cleanPhone = substr($cleanPhone, 2);
    }
    $cleanPhone = '91' . substr($cleanPhone, -10);
    $recipientPhone = '+' . $cleanPhone;
    
    // Log attempt
    $logFile = __DIR__ . '/../../uploads/notifications.log';
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] Sending WhatsApp to $recipientPhone\n";
    @file_put_contents($logFile, $logEntry, FILE_APPEND);
    
    // If credentials not set, log and return (fallback)
    if (empty($accessToken) || empty($phoneNumberId)) {
        $logEntry = "[$timestamp] ⚠️  Meta WhatsApp credentials not configured. Message logged only.\n";
        @file_put_contents($logFile, $logEntry, FILE_APPEND);
        return true;
    }
    
    // Prepare Meta API request
    $url = "https://graph.instagram.com/{$apiVersion}/{$phoneNumberId}/messages";
    
    $payload = [
        'messaging_product' => 'whatsapp',
        'recipient_type' => 'individual',
        'to' => $cleanPhone,
        'type' => 'text',
        'text' => [
            'body' => substr($message, 0, 4096) // Meta limits to 4096 chars
        ]
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $accessToken
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    // Log result
    if ($httpCode === 200) {
        $logEntry = "[$timestamp] ✅ Message sent successfully to $recipientPhone\n";
        @file_put_contents($logFile, $logEntry, FILE_APPEND);
        return true;
    } else {
        $responseData = json_decode($response, true);
        $errorMsg = $responseData['error']['message'] ?? $error ?? 'Unknown error';
        $logEntry = "[$timestamp] ❌ Failed to send to $recipientPhone (HTTP $httpCode): $errorMsg\n";
        @file_put_contents($logFile, $logEntry, FILE_APPEND);
        error_log("WhatsApp API Error: $errorMsg");
        return false;
    }
}

function sendOrderNotifications(PDO $pdo, int $orderId, string $orderNumber, string $customerName, string $customerPhone, string $customerEmail, float $totalAmount): void {
    // Store owner details
    $ownerPhone = '918680857511'; // Your WhatsApp number
    $ownerEmail = 'lookstlyoclothing@gmail.com';
    
    try {
        // Get order details
        $orderStmt = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
        $orderStmt->execute([$orderId]);
        $order = $orderStmt->fetch();
        
        if (!$order) return;
        
        // Get order items
        $itemsStmt = $pdo->prepare('SELECT product_name, size, color, quantity, price FROM order_items WHERE order_id = ?');
        $itemsStmt->execute([$orderId]);
        $items = $itemsStmt->fetchAll();
        
        // Format order items
        $itemsText = "📦 Order Items:\n";
        foreach ($items as $item) {
            $itemsText .= "• " . htmlspecialchars($item['product_name']) . " (Size: " . htmlspecialchars($item['size']) . ", Color: " . htmlspecialchars($item['color']) . ") × " . $item['quantity'] . " = ₹" . number_format($item['price'] * $item['quantity'], 2) . "\n";
        }
        
        // Notification to Store Owner
        $ownerMessage = "🎉 *New Order Received!*\n\n";
        $ownerMessage .= "*Order #:* " . htmlspecialchars($orderNumber) . "\n";
        $ownerMessage .= "*Customer:* " . htmlspecialchars($customerName) . "\n";
        $ownerMessage .= "*Phone:* " . htmlspecialchars($customerPhone) . "\n";
        $ownerMessage .= "*Email:* " . htmlspecialchars($customerEmail) . "\n";
        $ownerMessage .= "*Amount:* ₹" . number_format($totalAmount, 2) . "\n\n";
        $ownerMessage .= $itemsText . "\n";
        $ownerMessage .= "*Address:* " . htmlspecialchars($order['address']) . ", " . htmlspecialchars($order['city']) . ", " . htmlspecialchars($order['state']) . " - " . htmlspecialchars($order['pincode']) . "\n";
        $ownerMessage .= "*Status:* Payment Verification Pending\n\n";
        $ownerMessage .= "📊 Check admin dashboard for payment verification.";
        
        sendWhatsAppMessage($ownerPhone, $ownerMessage);
        
        // Notification to Customer
        $trackingUrl = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'lookstylo.in') . '/track-order.php?order=' . urlencode($orderNumber);
        $customerMessage = "✅ *Order Confirmed!*\n\n";
        $customerMessage .= "Thank you for your order, *" . htmlspecialchars($customerName) . "*!\n\n";
        $customerMessage .= "*Order #:* " . htmlspecialchars($orderNumber) . "\n";
        $customerMessage .= "*Total Amount:* ₹" . number_format($totalAmount, 2) . "\n\n";
        $customerMessage .= "🔍 *Track Your Order:* " . $trackingUrl . "\n\n";
        $customerMessage .= "📞 *Need Help?* Contact us on WhatsApp or call +91 86808 57511\n";
        $customerMessage .= "💌 We'll confirm your payment shortly!";
        
        sendWhatsAppMessage($customerPhone, $customerMessage);
        
    } catch (Throwable $e) {
        error_log('Order notification error: ' . $e->getMessage());
    }
}
