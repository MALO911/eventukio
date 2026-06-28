<?php
$file = 'pages/manage-event.php';
$c = file_get_contents($file);
$original = $c;

// ===== FIX 1: PHP handler - $user_id variable overwrite =====
$pos1 = strpos($c, 'foreach ($services as $service)');
if ($pos1 !== false) {
    $chunk = substr($c, $pos1, 600);
    $chunk = str_replace(
        "$" . "user_id = clean($" . "service['user_id'] ?? '');",
        "$" . "service_user_id = clean($" . "service['user_id'] ?? '');",
        $chunk, $count1
    );
    $chunk = str_replace(
        '$stmt->execute([$hire_id, $user_id, $profile_id, $event_id, $hire_amount, $invitee_id]);',
        '$stmt->execute([$hire_id, $service_user_id, $profile_id, $event_id, $hire_amount, $invitee_id]);',
        $chunk, $count2
    );
    $chunk = str_replace(
        '$stmt->execute([$invitee_id, $event_id, $user_id, $profession_title]);',
        '$stmt->execute([$invitee_id, $event_id, $service_user_id, $profession_title]);',
        $chunk, $count3
    );
    $c = substr_replace($c, $chunk, $pos1, 600);
    echo "FIX 1: PHP handler - var=$count1 exec1=$count2 exec2=$count3\n";
}

// ===== FIX 2: openHireModal =====
$old = "function openHireModal(eventId) {\n" .
       "    document.getElementById('hireEventId').value = eventId;\n" .
       "    hiredServices = [];\n" .
       "    updateHiredList();\n" .
       "    updateTotalBudget();\n" .
       "    document.getElementById('hireModal').classList.add('active');\n" .
       "    loadProfessions();\n" .
       "    resetHireForm();\n" .
       "}";
$new = "function openHireModal(eventId) {\n" .
       "    document.getElementById('hireEventId').value = eventId;\n" .
       "    document.getElementById('hireModal').classList.add('active');\n" .
       "    loadProfessions();\n" .
       "    resetHireForm();\n" .
       "}";
$c = str_replace($old, $new, $c);
echo "FIX 2: openHireModal done\n";

// ===== FIX 3: Remove functions between openHireModal and submitHiredServices =====
// We'll do this by finding and removing each function individually

// Remove getPendingHireEntry
$start = strpos($c, 'function getPendingHireEntry()');
if ($start !== false) {
    $after = substr($c, $start + 10);
    if (preg_match('/\nfunction\s/', $after, $m, PREG_OFFSET_CAPTURE)) {
        $end = $start + 10 + $m[0][1] + 1;
        $c = substr_replace($c, "\n", $start, $end - $start);
        echo "FIX 3a: Removed getPendingHireEntry\n";
    }
}

// Remove hiredServices declaration
$c = str_replace("let hiredServices = [];\n\n", "", $c);
echo "FIX 3b: Removed hiredServices\n";

// Remove addHireEntry
$start = strpos($c, 'function addHireEntry()');
if ($start !== false) {
    $after = substr($c, $start + 10);
    if (preg_match('/\nfunction\s/', $after, $m, PREG_OFFSET_CAPTURE)) {
        $end = $start + 10 + $m[0][1] + 1;
        $c = substr_replace($c, "\n", $start, $end - $start);
        echo "FIX 3c: Removed addHireEntry\n";
    }
}

// Remove updateHiredList
$start = strpos($c, 'function updateHiredList()');
if ($start !== false) {
    $after = substr($c, $start + 10);
    if (preg_match('/\nfunction\s/', $after, $m, PREG_OFFSET_CAPTURE)) {
        $end = $start + 10 + $m[0][1] + 1;
        $c = substr_replace($c, "\n", $start, $end - $start);
        echo "FIX 3d: Removed updateHiredList\n";
    }
}

// Remove removeHireEntry
$start = strpos($c, 'function removeHireEntry(');
if ($start !== false) {
    $after = substr($c, $start + 10);
    if (preg_match('/\nfunction\s/', $after, $m, PREG_OFFSET_CAPTURE)) {
        $end = $start + 10 + $m[0][1] + 1;
        $c = substr_replace($c, "\n", $start, $end - $start);
        echo "FIX 3e: Removed removeHireEntry\n";
    }
}

// Remove updateTotalBudget
$start = strpos($c, 'function updateTotalBudget()');
if ($start !== false) {
    $after = substr($c, $start + 10);
    if (preg_match('/\nfunction\s/', $after, $m, PREG_OFFSET_CAPTURE)) {
        $end = $start + 10 + $m[0][1] + 1;
        $c = substr_replace($c, "\n", $start, $end - $start);
        echo "FIX 3f: Removed updateTotalBudget\n";
    }
}

// ===== FIX 4: Replace validateHireForm =====
$old_vf = "function validateHireForm() {\n" .
          "    const profession = document.getElementById('professionSelect').value;\n" .
          "    const amount = document.getElementById('hireAmount').value;\n" .
          "    const userId = document.getElementById('userId').value;\n" .
          "    \n" .
          "    const isValid = profession && amount && userId;\n" .
          "    const addBtn = document.getElementById('addHireBtn');\n" .
          "    if (addBtn) {\n" .
          "        addBtn.disabled = !isValid;\n" .
          "    }\n" .
          "}";
$new_vf = "function validateHireForm() {\n" .
          "    // Validation is handled in submitHiredServices()\n" .
          "}";
$c = str_replace($old_vf, $new_vf, $c);

// Alt version without null check
$old_vf2 = "function validateHireForm() {\n" .
           "    const profession = document.getElementById('professionSelect').value;\n" .
           "    const amount = document.getElementById('hireAmount').value;\n" .
           "    const userId = document.getElementById('userId').value;\n" .
           "    \n" .
           "    const isValid = profession && amount && userId;\n" .
           "    document.getElementById('addHireBtn').disabled = !isValid;\n" .
           "}";
$c = str_replace($old_vf2, $new_vf, $c);
echo "FIX 4: validateHireForm done\n";

// ===== FIX 5: Replace submitHiredServices =====
$start = strpos($c, 'function submitHiredServices()');
$end_marker = strpos($c, '// Add event listeners for form validation');
if ($start !== false && $end_marker !== false) {
    $new_submit = "function submitHiredServices() {\n" .
        "    const profession = document.getElementById('professionSelect').value;\n" .
        "    const amount = parseFloat(document.getElementById('hireAmount').value);\n" .
        "    const userId = document.getElementById('userId').value;\n" .
        "    const profileId = document.getElementById('profileId').value;\n" .
        "    \n" .
        "    if (!profession || !amount || amount <= 0 || !userId || !profileId) {\n" .
        "        alert('Please select a profession, choose a professional from the list, and enter a valid hire amount.');\n" .
        "        return;\n" .
        "    }\n" .
        "    \n" .
        "    const eventId = document.getElementById('hireEventId').value;\n" .
        "    const services = [{\n" .
        "        profession_title: profession,\n" .
        "        hire_amount: amount,\n" .
        "        user_id: userId,\n" .
        "        profile_id: profileId\n" .
        "    }];\n" .
        "    \n" .
        "    fetch('manage-event.php', {\n" .
        "        method: 'POST',\n" .
        "        headers: {\n" .
        "            'Content-Type': 'application/x-www-form-urlencoded',\n" .
        "        },\n" .
        "        body: new URLSearchParams({\n" .
        "            'submit_hired_services': '1',\n" .
        "            'event_id': eventId,\n" .
        "            'is_ajax': '1',\n" .
        "            'services': JSON.stringify(services)\n" .
        "        })\n" .
        "    })\n" .
        "    .then(response => response.json())\n" .
        "    .then(data => {\n" .
        "        if (data.success) {\n" .
        "            alert(data.message || 'Service provider hired successfully!');\n" .
        "            closeHireModal();\n" .
        "            location.reload();\n" .
        "        } else {\n" .
        "            alert(data.error || 'Failed to hire service provider.');\n" .
        "        }\n" .
        "    })\n" .
        "    .catch(err => {\n" .
        "        alert('Error submitting hired services: ' + err);\n" .
        "        console.error(err);\n" .
        "    });\n" .
        "}\n\n";
    $c = substr_replace($c, $new_submit, $start, $end_marker - $start);
    echo "FIX 5: submitHiredServices replaced\n";
}

// ===== FIX 6: Replace resetHireForm =====
$old_rf = "function resetHireForm() {\n" .
          "    document.getElementById('professionSelect').value = '';\n" .
          "    document.getElementById('hireAmount').value = '';\n" .
          "    document.getElementById('userName').value = '';\n" .
          "    document.getElementById('userId').value = '';\n" .
          "    document.getElementById('profileId').value = '';\n" .
          "    document.getElementById('professionalsList').innerHTML = '<p class=\"text-gray-500 text-center py-8\">Select a profession to see available professionals</p>';\n" .
          "    document.getElementById('addHireBtn').disabled = true;\n" .
          "}";
$new_rf = "function resetHireForm() {\n" .
          "    document.getElementById('professionSelect').value = '';\n" .
          "    document.getElementById('hireAmount').value = '';\n" .
          "    document.getElementById('userName').value = '';\n" .
          "    document.getElementById('userId').value = '';\n" .
          "    document.getElementById('profileId').value = '';\n" .
          "    document.getElementById('professionalsList').innerHTML = '<p class=\"text-gray-500 text-center py-8\">Select a profession to see available professionals</p>';\n" .
          "}";
$c = str_replace($old_rf, $new_rf, $c);

// Alt version with null check
$old_rf2 = "function resetHireForm() {\n" .
           "    document.getElementById('professionSelect').value = '';\n" .
           "    document.getElementById('hireAmount').value = '';\n" .
           "    document.getElementById('userName').value = '';\n" .
           "    document.getElementById('userId').value = '';\n" .
           "    document.getElementById('profileId').value = '';\n" .
           "    document.getElementById('professionalsList').innerHTML = '<p class=\"text-gray-500 text-center py-8\">Select a profession to see available professionals</p>';\n" .
           "    const addBtn = document.getElementById('addHireBtn');\n" .
           "    if (addBtn) {\n" .
           "        addBtn.disabled = true;\n" .
           "    }\n" .
           "}";
$c = str_replace($old_rf2, $new_rf, $c);
echo "FIX 6: resetHireForm done\n";

// ===== FIX 7: Remove modal HTML hiredList and addHireBtn =====
$old_hl = "                <!-- Hired Services List -->\n" .
          "                <div id=\"hiredList\" class=\"mt-4 space-y-2\">\n" .
          "                    <!-- Dynamically populated -->\n" .
          "                </div>\n" .
          "                \n" .
          "                <!-- Submit Button -->";
$new_hl = "                <!-- Submit Button -->";
$c = str_replace($old_hl, $new_hl, $c);
echo "FIX 7a: hiredList HTML removed\n";

$old_ab = "                <!-- Add to List Button -->\n" .
          "                <button type=\"button\" id=\"addHireBtn\" onclick=\"addHireEntry()\" disabled\n" .
          "                        class=\"w-full bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed text-white py-3 rounded-2xl font-semibold transition\">\n" .
          "                    Add to List\n" .
          "                </button>\n\n";
$c = str_replace($old_ab, '', $c);
echo "FIX 7b: addHireBtn HTML removed\n";

// ===== FIX 8: Remove hireAmount event listener =====
$c = str_replace(
    "document.getElementById('hireAmount')?.addEventListener('input', validateHireForm);",
    "",
    $c
);
echo "FIX 8: hireAmount listener removed\n";

// ===== Verify =====
$checks = ['hiredServices', 'addHireBtn', 'addHireEntry', 'updateHiredList', 'removeHireEntry', 'updateTotalBudget', 'getPendingHireEntry'];
foreach ($checks as $ref) {
    $lines = explode("\n", $c);
    $found = false;
    foreach ($lines as $i => $line) {
        if (strpos($line, $ref) !== false) {
            // Skip SQL/comments
            if (strpos($line, 'event_service_hiring') !== false) continue;
            if (strpos($line, '// ') !== false && strpos($line, $ref) > strpos($line, '// ')) continue;
            if (strpos($line, 'function ') !== false) {
                echo "WARNING: '$ref' function still found at line " . ($i+1) . "\n";
                $found = true;
                break;
            }
        }
    }
    if (!$found) {
        echo "OK: '$ref' removed\n";
    }
}

file_put_contents($file, $c);
echo "\n=== Changes saved! File size: " . strlen($original) . " -> " . strlen($c) . " (diff: " . (strlen($c) - strlen($original)) . ") ===\n";
