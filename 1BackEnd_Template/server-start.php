<?php
// Simple server start script
echo "Consumer Loan Admin Template Server Starting...\n";
echo "Visit: http://localhost:8080\n";

// Start the built-in PHP server
$command = "php -S 0.0.0.0:8080 -t . index.php";
echo "Running: $command\n";
exec($command);
?>