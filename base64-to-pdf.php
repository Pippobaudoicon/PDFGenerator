<!DOCTYPE html>
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
                <input type="text" id="filename" name="filename" value="test-dev-output.pdf" required>
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
</html>