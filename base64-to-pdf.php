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
} else {    
    // Check if parameters are passed in URL (GET method)
    if (isset($_GET['base64']) && isset($_GET['filename'])) {
        $base64 = $_GET['base64'];
        $filename = $_GET['filename'];
    } else {
        // Display a simple form
        echo '<!DOCTYPE html>
        <html>
        <head>
            <title>Base64 to PDF Converter</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                textarea { width: 100%; height: 200px; }
                .container { max-width: 800px; margin: 0 auto; }
                button { margin-top: 10px; padding: 8px 15px; }
            </style>
        </head>
        <body>
            <div class="container">
                <h1>Base64 to PDF Converter</h1>
                <form method="post" action="base64-to-pdf.php" id="convertForm">
                    <div>
                        <label for="filename">Output Filename:</label>
                        <input type="text" id="filename" name="filename" value="output.pdf" required>
                    </div>
                    <div>
                        <label for="base64data">Paste Base64 Data:</label>
                        <textarea id="base64data" name="base64data" required></textarea>
                    </div>
                    <button type="button" onclick="convertToPdf()">Convert to PDF</button>
                </form>
                <div id="result"></div>
            </div>
            
            <script>
                function convertToPdf() {
                    const base64 = document.getElementById("base64data").value;
                    const filename = document.getElementById("filename").value;
                    
                    fetch("base64-to-pdf.php", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json"
                        },
                        body: JSON.stringify({
                            base64: base64,
                            filename: filename
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            document.getElementById("result").innerHTML = 
                                `<p>Success! <a href="${data.file_url}" target="_blank">Open PDF</a></p>`;
                        } else {
                            document.getElementById("result").innerHTML = 
                                `<p>Error: ${data.error}</p>`;
                        }
                    })
                    .catch(error => {
                        document.getElementById("result").innerHTML = 
                            `<p>Error: ${error.message}</p>`;
                    });
                }
            </script>
        </body>
        </html>';
        exit();
    }
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
?>