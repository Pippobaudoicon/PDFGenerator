<?php
// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get JSON data
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    // Validate input
    if (!$data || !isset($data['base64']) || !isset($data['filename'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid input. Required fields: base64, filename']);
        exit();
    }

    $base64 = $data['base64'];
    $filename = $data['filename'];
} else if(isset($_GET['base64']) && isset($_GET['filename'])) {
    // Check if parameters are passed in URL (GET method)
    $base64 = $_GET['base64'];
    $filename = $_GET['filename'];
} else {
    http_response_code(400);
    echo json_encode(['error' => 'No base64 data or filename provided']);
    exit();
}


try {
    // Make sure the filename has .pdf extension
    if (!preg_match('/\.pdf$/i', $filename)) {
        $filename .= '.pdf';
    }

    // Sanitize filename to prevent directory traversal attacks
    $filename = basename($filename);

    // Directory to save files (create if it doesn't exist)
    $dir = 'pdf_output';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $filepath = $dir . '/' . $filename;

    // Decode base64 data
    $pdf_data = base64_decode($base64);

    // Check if data was properly decoded
    if ($pdf_data === false) {
        throw new Exception('Invalid base64 data');
    }

    // Write data to file
    if (file_put_contents($filepath, $pdf_data) === false) {
        throw new Exception('Failed to write PDF file');
    }

    // Return success response
    $file_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") .
        "://$_SERVER[HTTP_HOST]" . dirname($_SERVER['REQUEST_URI']) . "/$filepath";

    echo json_encode([
        'success' => true,
        'message' => 'PDF file created successfully',
        'file_path' => $filepath,
        'file_url' => $file_url
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
