<?php
// ✅ MUST BE FIRST - Include CORS headers
require_once __DIR__ . '/../../utils/cors.php';

// Start output buffering
ob_start();

// Error handling
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// Include dependencies
require_once __DIR__ . '/../../connection/db.php';
require_once __DIR__ . '/../../utils/jwt.php';

// --- JWT Authentication ---
$headers = getallheaders();
if (!isset($headers['Authorization'])) {
    http_response_code(401);
    echo json_encode(["error" => "No authorization header"]);
    exit;
}

$auth_parts = explode(' ', $headers['Authorization']);
if (count($auth_parts) !== 2 || $auth_parts[0] !== 'Bearer') {
    http_response_code(401);
    echo json_encode(["error" => "Invalid token format"]);
    exit;
}

$jwt = $auth_parts[1];
$decoded = jwt_verify($jwt);

if (!$decoded) {
    http_response_code(401);
    echo json_encode(["error" => "Invalid or expired token"]);
    exit;
}

// Check if admin
if (!isset($decoded['role']) || (string)$decoded['role'] !== '1') {
    http_response_code(403);
    echo json_encode(["error" => "Access denied. Admins only."]);
    exit;
}

// --- Get Parameters ---
$report = $_GET['report'] ?? 'transactions';
$from = $_GET['from'] ?? null;
$to = $_GET['to'] ?? null;
$format = $_GET['format'] ?? 'csv';

if (!$from || !$to) {
    $to = (new DateTime('today'))->format('Y-m-d');
    $from = (new DateTime('today -30 days'))->format('Y-m-d');
}

// --- Fetch Data ---
try {
    $rows = [];
    
    if ($report === 'transactions') {
        // ✅ FIX: Use actual column names from database
        $stmt = $conn->prepare("
            SELECT 
                t.id,
                t.sender_id,
                t.recipient_id,
                t.amount,
                t.transaction_type,
                DATE_FORMAT(t.created_at, '%Y-%m-%d %H:%i:%s') as transaction_date
            FROM transactions t
            WHERE DATE(t.created_at) BETWEEN :from AND :to
            ORDER BY t.created_at DESC
        ");
        $stmt->execute([':from' => $from, ':to' => $to]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } elseif ($report === 'users') {
        $stmt = $conn->prepare("
            SELECT 
                u.id,
                u.email,
                u.role,
                DATE(u.created_at) as registration_date
            FROM users u
            WHERE DATE(u.created_at) BETWEEN :from AND :to
            ORDER BY u.created_at DESC
        ");
        $stmt->execute([':from' => $from, ':to' => $to]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
    exit;
}

// --- Format Response ---
if ($format === 'csv') {
    // Clean output buffer
    while (ob_get_level() > 0) { ob_end_clean(); }
    
    // ✅ Set proper CSV headers
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $report . '_' . $from . '_' . $to . '.csv"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // ✅ Output directly to php://output
    $output = fopen('php://output', 'w');
    
    // ✅ Add UTF-8 BOM for Excel compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    if (!empty($rows)) {
        // Write column headers
        fputcsv($output, array_keys($rows[0]));
        
        // Write data rows
        foreach ($rows as $row) {
            fputcsv($output, array_values($row));
        }
    } else {
        fputcsv($output, ['message']);
        fputcsv($output, ['No data found for the selected date range']);
    }
    
    fclose($output);
    exit;
}

// ---- PDF ----
if ($format === 'pdf') {
    $candidates = [
        __DIR__ . '/../../vendor/autoload.php',
        __DIR__ . '/../../../vendor/autoload.php'
    ];
    $autoload = null;
    foreach ($candidates as $p) {
        if (file_exists($p)) { $autoload = $p; break; }
    }
    if (!$autoload) {
        http_response_code(400);
        echo "PDF export requires dompdf. Autoload not found.";
        exit;
    }
    require_once $autoload;

    $title = strtoupper($report) . " REPORT ({$from} → {$to})";
    $thead = '';
    $tbody = '';
    if (!empty($rows)) {
        $cols = array_keys($rows[0]);
        $thead = '<tr>' . implode('', array_map(fn($c)=>"<th>".htmlspecialchars((string)$c)."</th>", $cols)) . '</tr>';
        foreach ($rows as $r) {
            $cells = '';
            foreach ($r as $v) $cells .= "<td>".htmlspecialchars((string)$v)."</td>";
            $tbody .= "<tr>{$cells}</tr>";
        }
    } else {
        $thead = '<tr><th>Info</th></tr>';
        $tbody = '<tr><td>No data for the selected range</td></tr>';
    }

    $html = "
      <html><head><meta charset='utf-8'>
      <style>
        body{font-family: DejaVu Sans, Arial, sans-serif; font-size:12px;}
        h1{font-size:16px;margin-bottom:10px}
        table{width:100%;border-collapse:collapse}
        th,td{border:1px solid #ccc;padding:6px;text-align:left;vertical-align:top}
        th{background:#f2f2f2}
      </style>
      </head><body>
        <h1>{$title}</h1>
        <table><thead>{$thead}</thead><tbody>{$tbody}</tbody></table>
      </body></html>
    ";

    $dompdf = new Dompdf\Dompdf();
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    // ✅ Clean all output buffers
    while (ob_get_level() > 0) { ob_end_clean(); }

    // ✅ Send headers
    $filename = "{$report}_{$from}_{$to}.pdf";
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');

    // ✅ Output PDF
    echo $dompdf->output();
    exit;
}

// Unknown format
http_response_code(400);
echo json_encode(["error" => "Unsupported format: $format"]);
?>