<?php
$file = 'pages/history.php';
$c = file_get_contents($file);
$original = $c;

// ===== FIX 1: Remove the entire second set of duplicate handlers =====
// These start at "// --- Handle POST actions (Deny/Accept, Delete, etc.) ---" and end before "function getUserType"

$dup_start_marker = "// --- Handle POST actions (Deny/Accept, Delete, etc.) ---";
$dup_end_marker = "\nfunction getUserType";

$dup_start = strpos($c, $dup_start_marker);
$dup_end = strpos($c, $dup_end_marker);

if ($dup_start !== false && $dup_end !== false) {
    $c = substr_replace($c, "", $dup_start, $dup_end - $dup_start);
    echo "FIX 1: Removed duplicate handlers block\n";
} else {
    echo "FIX 1: Could not find duplicate block (start=$dup_start, end=$dup_end)\n";
}

// ===== FIX 2: Fix the Accept Service handler to also set hire_status = 'Hired' =====
$old_accept = '$stmt3 = $pdo->prepare("UPDATE event_service_hiring SET service_status = \'Accepted\' WHERE hire_id = ?");';
$new_accept = '$stmt3 = $pdo->prepare("UPDATE event_service_hiring SET hire_status = \'Hired\', service_status = \'Accepted\' WHERE hire_id = ?");';
$c = str_replace($old_accept, $new_accept, $c);
echo "FIX 2: Added hire_status = 'Hired' to Accept handler\n";

// ===== FIX 3: Remove duplicate getUserType call (keep only one) =====
// There should be only one getUserType function and one $userType call now
// Count occurrences
$func_count = substr_count($c, 'function getUserType');
$call_count = substr_count($c, '$userType = getUserType');
echo "FIX 3: getUserType function count: $func_count, call count: $call_count\n";

// ===== Verify =====
echo "\n=== Verification ===\n";
$remaining_dupes = substr_count($c, "// --- Handle POST actions");
echo "Duplicate handler comments remaining: $remaining_dupes\n";

// Check for any remaining duplicate POST handling patterns
if (preg_match_all('/if\s*\(isset\(\$_POST\[\'accept_rental\'\]\)/', $c, $m)) {
    echo "accept_rental handlers: " . count($m[0]) . "\n";
}
if (preg_match_all('/if\s*\(isset\(\$_POST\[\'deny_rental\'\]\)/', $c, $m)) {
    echo "deny_rental handlers: " . count($m[0]) . "\n";
}
if (preg_match_all('/if\s*\(isset\(\$_POST\[\'accept_service\'\]\)/', $c, $m)) {
    echo "accept_service handlers: " . count($m[0]) . "\n";
}
if (preg_match_all('/if\s*\(isset\(\$_POST\[\'deny_service\'\]\)/', $c, $m)) {
    echo "deny_service handlers: " . count($m[0]) . "\n";
}
if (preg_match_all('/if\s*\(isset\(\$_POST\[\'delete_asset\'\]\)/', $c, $m)) {
    echo "delete_asset handlers: " . count($m[0]) . "\n";
}
if (preg_match_all('/if\s*\(isset\(\$_POST\[\'delete_job\'\]\)/', $c, $m)) {
    echo "delete_job handlers: " . count($m[0]) . "\n";
}

file_put_contents($file, $c);
echo "\n=== Changes saved! File size: " . strlen($original) . " -> " . strlen($c) . " (diff: " . (strlen($c) - strlen($original)) . ") ===\n";
