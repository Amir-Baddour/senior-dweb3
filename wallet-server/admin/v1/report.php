<?php
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

    // ✅ Clean all output buffers so no stray data corrupts the PDF
    while (ob_get_level() > 0) { ob_end_clean(); }

    // ✅ Send headers explicitly
    $filename = "{$report}_{$from}_{$to}.pdf";
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');

    // ✅ Echo only the PDF bytes
    echo $dompdf->output();
    exit;
}
