<!DOCTYPE html>
<html>
<head>
    <title>Network Tools - Ping Utility</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; }
        h1 { color: #333; }
        .tool-form { background: #f5f5f5; padding: 20px; border-radius: 5px; }
        input[type="text"] { width: 300px; padding: 8px; }
        button { padding: 8px 20px; background: #007bff; color: white; border: none; border-radius: 3px; cursor: pointer; }
        button:hover { background: #0056b3; }
        .output { background: #000; color: #0f0; padding: 15px; margin-top: 20px; font-family: monospace; white-space: pre-wrap; }
    </style>
</head>
<body>
    <h1>Network Diagnostic Tools</h1>
    <div class="tool-form">
        <h2>Ping Tool</h2>
        <form method="GET">
            <label>Enter IP or hostname:</label><br>
            <input type="text" name="host" value="<?php echo htmlspecialchars($_GET['host'] ?? ''); ?>" placeholder="e.g., 8.8.8.8">
            <button type="submit">Ping</button>
        </form>
    </div>

<?php
if (isset($_GET['host'])) {
    $host = $_GET['host'];
    
    echo '<div class="output">';
    echo "Pinging $host...\n\n";
    
    // VULNERABLE: Direct command execution without proper sanitization!
    // This is a realistic command injection vulnerability
    $cmd = "ping -c 3 " . $host;
    
    // Execute command - THIS IS THE VULNERABILITY
    system($cmd);
    
    echo '</div>';
}
?>

    <div style="margin-top: 30px; padding: 10px; background: #fff3cd; border-left: 4px solid #ffc107;">
        <strong>Note:</strong> This is a simple network diagnostic tool. Enter an IP address or hostname to test connectivity.
    </div>
</body>
</html>