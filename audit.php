<?php
// audit.php
// Helper to write to audit_log table
// PREVENTS CRASHES: Uses try-catch to fail silently if DB is wrong

function audit($userId, $action, $entityType = null, $entityId = null, $details = null) {
    // --- ENTER YOUR LIVE DATABASE CREDENTIALS HERE ---
    $db_host = 'localhost';
    $db_user = 'u763865560_Mancave'; 
    $db_pass = 'ManCave2025'; 
    $db_name = 'u763865560_EmmanuelCafeDB'; 

    try {
        // Report errors as exceptions so we can catch them
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        
        $mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);
        
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;

        // Ensure JSON is a string
        if (is_array($details) || is_object($details)) {
            $details = json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        // Make sure the table name is correct (audit_log)
        $stmt = $mysqli->prepare("INSERT INTO audit_log (user_id, action, entity_type, entity_id, details, ip, user_agent) VALUES (?,?,?,?,?,?,?)");
        $stmt->bind_param('ississs', $userId, $action, $entityType, $entityId, $details, $ip, $ua);
        $stmt->execute();
        $stmt->close();
        $mysqli->close();

    } catch (Exception $e) {
        // SILENT FAILURE: If database connects fail, do NOTHING.
        // This prevents the HTTP 500 Error on recently_deleted.php
        return;
    }
}