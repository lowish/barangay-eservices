<?php
/**
 * Rebuilds the database and loads sample records.
 * Run from the project folder:  C:\xampp\php\php.exe database\seed.php
 * (The submission also includes database/barangay_eservices.sql, which is the export of this result
 *  and can be imported directly in phpMyAdmin instead.)
 */
if (PHP_SAPI !== 'cli') {
    exit('Run this script from the command line.');
}
require __DIR__ . '/../app/bootstrap.php';

const DEMO_PASSWORD = 'Demo@2026';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$server = new mysqli(config('db.host'), config('db.user'), config('db.password'), '', (int) config('db.port', 3306));
$server->set_charset('utf8mb4');
$server->multi_query(file_get_contents(__DIR__ . '/schema.sql'));
while ($server->more_results()) {
    $server->next_result();
}
$server->close();
echo "Schema created.\n";

mt_srand(20261028); // repeatable sample data
$hash = password_hash(DEMO_PASSWORD, PASSWORD_DEFAULT);
$now = time();
$ts = fn (int $t) => date('Y-m-d H:i:s', $t);

// ---------------------------------------------------------------- Puroks
$puroks = ['Purok 1 - Sampaguita', 'Purok 2 - Ilang-Ilang', 'Purok 3 - Rosal', 'Purok 4 - Camia', 'Purok 5 - Dahlia', 'Purok 6 - Gumamela'];
foreach ($puroks as $p) {
    db_query('INSERT INTO puroks (purok_name) VALUES (?)', [$p]);
}

// ---------------------------------------------------------------- Users
function add_user(string $role, string $first, string $last, string $email, ?int $purok, string $street, string $phone, ?string $verified, string $created, string $hash): int
{
    return db_insert(
        'INSERT INTO users (role_id, purok_id, first_name, last_name, email, contact_no, street_address, password_hash, email_verified_at, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [role_id($role), $purok, $first, $last, $email, $phone, $street, $hash, $verified, $created]
    );
}
$long = $ts($now - 90 * 86400);
$admin = add_user(ROLE_ADMIN, 'Maricel', 'Dela Cruz', 'admin@barangayservice.com', null, 'Barangay Hall, Rizal St.', '09171110001', $long, $long, $hash);
$staffA = add_user(ROLE_STAFF, 'Ramon', 'Villanueva', 'staff@barangayservice.com', 1, 'Barangay Hall, Rizal St.', '09171110002', $long, $long, $hash);
$staffB = add_user(ROLE_STAFF, 'Liza', 'Santos', 'staff2@barangayservice.com', 3, 'Barangay Hall, Rizal St.', '09171110003', $long, $long, $hash);
$staffC = add_user(ROLE_STAFF, 'Eduardo', 'Ramos', 'staff3@barangayservice.com', 5, 'Barangay Hall, Rizal St.', '09171110004', $long, $long, $hash);
$staff = [$staffA, $staffB, $staffC];

// No barangay profile is set: the system starts as a general "Barangay E-Services" system.
// The administrator can enter the barangay name, location, office hours and logo under Settings.
foreach (glob(APP_ROOT . '/uploads/logo/logo-*') ?: [] as $oldLogo) {
    unlink($oldLogo); // start without an uploaded logo
}

$residentData = [
    ['Juan', 'Dela Paz', 'resident@barangayservice.com', 2, '12 Mabini St.'],
    ['Ana', 'Reyes', 'ana.reyes@barangayservice.com', 1, '45 Rizal St.'],
    ['Jose', 'Mendoza', 'jose.mendoza@barangayservice.com', 3, '7 Bonifacio St.'],
    ['Grace', 'Bautista', 'grace.bautista@barangayservice.com', 4, '88 Luna St.'],
    ['Mark', 'Aquino', 'mark.aquino@barangayservice.com', 5, '3 Del Pilar St.'],
    ['Rosa', 'Garcia', 'rosa.garcia@barangayservice.com', 6, '21 Burgos St.'],
    ['Paolo', 'Torres', 'paolo.torres@barangayservice.com', 2, '64 Mabini St.'],
    ['Carmela', 'Navarro', 'carmela.navarro@barangayservice.com', 4, '9 Luna St.'],
];
$residents = [];
foreach ($residentData as $i => [$f, $l, $em, $pk, $st]) {
    $created = $ts($now - (60 - $i * 3) * 86400);
    $residents[] = add_user(ROLE_RESIDENT, $f, $l, $em, $pk, $st, '0918' . str_pad((string) (2220000 + $i), 7, '0', STR_PAD_LEFT), $created, $created, $hash);
}
// One unverified resident, to demonstrate the restriction on unverified accounts.
$unverified = add_user(ROLE_RESIDENT, 'Nina', 'Castillo', 'unverified@barangayservice.com', 3, '15 Bonifacio St.', '09182229999', null, $ts($now - 86400), $hash);
db_query("INSERT INTO email_verifications (user_id, token_hash, expires_at, created_at) VALUES (?, ?, ?, ?)",
    [$unverified, hash('sha256', bin2hex(random_bytes(32))), $ts($now - 82800), $ts($now - 86400)]);

// ---------------------------------------------------------------- Document types + requirements
$docTypes = [
    ['Barangay Clearance', 'Certifies that the resident has no derogatory record in the barangay. Commonly required for employment, business permits and loans.', 50, 1,
        ['Valid government-issued ID', 'Community Tax Certificate (cedula) for the current year', 'Proof of residency of at least 6 months']],
    ['Certificate of Residency', 'Certifies that the person is a bona fide resident of the barangay and states the length of residency.', 30, 1,
        ['Valid government-issued ID', 'Proof of address (utility bill or lease contract)']],
    ['Certificate of Indigency', 'Certifies that the resident belongs to a low-income household. Used for medical, burial, educational and legal assistance.', 0, 2,
        ['Valid ID of the applicant', 'Letter or form from the agency requesting the certificate, if any', 'Interview with the barangay social worker']],
    ['Barangay Business Clearance', 'Required before the municipality issues or renews a business permit for a business located in the barangay.', 300, 3,
        ['DTI or SEC registration', 'Lease contract or proof of ownership of the business location', 'Valid ID of the owner', 'Previous business clearance (for renewal)']],
    ['Certificate of Good Moral Character', 'Certifies the good standing of the resident in the community. Often required for school and scholarship applications.', 40, 2,
        ['Valid ID or school ID', 'Recent 1x1 photo']],
    ['First-Time Jobseeker Certificate', 'Certification under Republic Act No. 11261 that waives government fees for first-time jobseekers. Free of charge.', 0, 1,
        ['Valid ID showing the applicant is at least 18 years old', 'Signed oath of undertaking (available at the barangay hall)']],
];
$docTypeIds = [];
foreach ($docTypes as [$name, $desc, $fee, $days, $reqs]) {
    $id = db_insert('INSERT INTO document_types (name, description, fee, processing_days, created_at) VALUES (?, ?, ?, ?, ?)', [$name, $desc, (float) $fee, $days, $long]);
    foreach ($reqs as $i => $r) {
        db_query('INSERT INTO document_requirements (doc_type_id, requirement_text, sort_order) VALUES (?, ?, ?)', [$id, $r, $i + 1]);
    }
    $docTypeIds[] = ['id' => $id, 'fee' => (float) $fee, 'name' => $name];
}

// ---------------------------------------------------------------- Concern categories
$cats = [
    ['Improper Waste Disposal', 'Uncollected garbage, illegal dumping, burning of trash, or unsegregated waste.', 2],
    ['Damaged Road or Sidewalk', 'Potholes, cracks, eroded road shoulders or broken sidewalks.', 2],
    ['Broken Streetlight', 'Streetlights that are not working, flickering or damaged.', 2],
    ['Clogged Drainage or Flooding', 'Blocked canals and drains, or areas that flood after rain.', 3],
    ['Fallen Tree or Electric Post Hazard', 'Fallen or leaning trees, sagging wires or damaged electric posts that endanger people.', 4],
    ['Noise or Public Disturbance', 'Excessive noise, videoke past curfew, or disturbances in public places.', 1],
    ['Stray Animals', 'Stray or aggressive dogs and other animals roaming in public areas.', 2],
];
$catIds = [];
foreach ($cats as [$name, $desc, $prio]) {
    $catIds[] = ['id' => db_insert('INSERT INTO concern_categories (name, description, default_priority, created_at) VALUES (?, ?, ?, ?)', [$name, $desc, $prio, $long]), 'prio' => $prio];
}

// ---------------------------------------------------------------- Document requests across all workflow stages
$purposes = ['Employment requirement', 'Bank loan application', 'School enrollment', 'Scholarship application', 'Business permit renewal',
    'Medical assistance at the provincial hospital', 'Postal ID application', 'Police clearance requirement', 'Job application abroad'];
$pathTo = [
    'Pending' => ['Pending'],
    'Under Review' => ['Pending', 'Under Review'],
    'Approved' => ['Pending', 'Under Review', 'Approved'],
    'Processing' => ['Pending', 'Under Review', 'Approved', 'Processing'],
    'Ready for Pickup' => ['Pending', 'Under Review', 'Approved', 'Processing', 'Ready for Pickup'],
    'Released' => ['Pending', 'Under Review', 'Approved', 'Processing', 'Ready for Pickup', 'Released'],
    'Rejected' => ['Pending', 'Under Review', 'Rejected'],
    'Cancelled' => ['Pending', 'Cancelled'],
];
$requestPlan = [
    // [final status, days ago]
    ['Released', 40], ['Released', 35], ['Released', 28], ['Released', 21], ['Released', 14], ['Released', 9], ['Released', 5],
    ['Rejected', 30], ['Rejected', 12], ['Cancelled', 18], ['Cancelled', 3],
    ['Ready for Pickup', 6], ['Ready for Pickup', 4], ['Processing', 3], ['Processing', 2], ['Approved', 2],
    ['Under Review', 1], ['Under Review', 1], ['Pending', 1], ['Pending', 0], ['Pending', 0],
];
$used = []; // resident|doctype pairs with an open request (one open request per type)
foreach ($requestPlan as $n => [$final, $daysAgo]) {
    do {
        $res = $residents[mt_rand(0, count($residents) - 1)];
        $dt = $docTypeIds[mt_rand(0, count($docTypeIds) - 1)];
        $open = !in_array($final, ['Released', 'Rejected', 'Cancelled'], true);
    } while ($open && isset($used["$res|{$dt['id']}"]));
    if ($open) {
        $used["$res|{$dt['id']}"] = true;
    }
    $copies = mt_rand(1, 10) > 8 ? 2 : 1;
    $t = $now - $daysAgo * 86400 - mt_rand(3600, 8 * 3600);
    $handler = $staff[mt_rand(0, 2)];
    $id = db_insert(
        'INSERT INTO document_requests (resident_id, doc_type_id, purpose, copies, fee_amount, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        [$res, $dt['id'], $purposes[mt_rand(0, count($purposes) - 1)], $copies, $dt['fee'] * $copies, 'Pending', $ts($t), $ts($t)]
    );
    db_exec("UPDATE document_requests SET reference_no = CONCAT('REQ-', YEAR(created_at), '-', LPAD(request_id, 5, '0')) WHERE request_id = ?", [$id]);
    $prev = null;
    $remarks = null;
    foreach ($pathTo[$final] as $step => $status) {
        $by = $status === 'Pending' || $status === 'Cancelled' ? $res : $handler;
        $note = match ($status) {
            'Pending' => 'Request submitted',
            'Rejected' => 'The Community Tax Certificate presented is from the previous year. Please secure a current cedula and submit a new request.',
            'Cancelled' => 'No longer needed',
            'Ready for Pickup' => 'Signed by the Punong Barangay. Ready at the front desk.',
            default => null,
        };
        if ($note && $status !== 'Pending') {
            $remarks = $note;
        }
        db_query('INSERT INTO request_status_history (request_id, from_status, to_status, remarks, changed_by, changed_at) VALUES (?, ?, ?, ?, ?, ?)',
            [$id, $prev, $status, $note, $by, $ts($t)]);
        $prev = $status;
        $t += mt_rand(2, 20) * 3600;
        $t = min($t, $now - 600);
    }
    db_exec('UPDATE document_requests SET status = ?, remarks = ?, handled_by = ?, updated_at = ? WHERE request_id = ?',
        [$final, $remarks, $final === 'Pending' || $final === 'Cancelled' ? null : $handler, $ts(min($t, $now - 600)), $id]);
}
// Archive two of the oldest finished requests to show the archive feature.
db_exec("UPDATE document_requests SET is_archived = 1 WHERE status = 'Released' ORDER BY created_at LIMIT 2");

// ---------------------------------------------------------------- Community concerns
$concernPlan = [
    // [category index, purok, title, location, description, final status, days ago, priority override]
    [0, 2, 'Garbage not collected for a week', 'Beside the basketball court, Mabini St.', 'Garbage bags have piled up beside the court since last Monday. Dogs are tearing them open and the smell reaches the houses nearby.', 'Submitted', 7, null],
    [2, 1, 'Streetlight not working near the chapel', 'In front of the barangay chapel, Rizal St.', 'The streetlight in front of the chapel has been off for two weeks. The street is very dark at night and residents walking home from the terminal feel unsafe.', 'Submitted', 1, null],
    [4, 4, 'Leaning electric post after the storm', 'Corner of Luna St. and Del Pilar St.', 'An electric post is leaning toward the road after last night\'s strong winds. Wires are sagging low over the street where children pass on the way to school.', 'Submitted', 0, null],
    [1, 3, 'Deep pothole on Bonifacio St.', 'Near house no. 20, Bonifacio St.', 'There is a large pothole in the middle of the road that fills with water when it rains. Two tricycles have already had flat tires.', 'Under Review', 5, null],
    [3, 5, 'Clogged canal causes flooding', 'Canal behind Del Pilar Elementary School', 'The canal is full of plastic and silt. Even light rain floods the street in front of the school up to the ankles.', 'Under Review', 2, 3],
    [5, 6, 'Videoke past 10 PM every weekend', 'Burgos St., near the sari-sari store', 'A household plays videoke at very high volume until 2 AM every Saturday, past the barangay curfew on noise.', 'Submitted', 10, null],
    [6, 2, 'Aggressive stray dogs near the school', 'Mabini St. entrance of the elementary school', 'A pack of four stray dogs chases students in the morning. One child was nearly bitten yesterday.', 'Assigned', 4, 3],
    [0, 4, 'Illegal dumping on vacant lot', 'Vacant lot beside 90 Luna St.', 'People are throwing construction debris and household garbage on the vacant lot at night.', 'In Progress', 9, null],
    [2, 3, 'Flickering streetlights along Bonifacio St.', 'Bonifacio St., from no. 1 to no. 30', 'Three streetlights keep flickering on and off at night.', 'In Progress', 6, null],
    [1, 1, 'Broken sidewalk near the health center', 'In front of the Barangay Health Center', 'The sidewalk slabs are broken and lifted. Elderly patients have tripped while going to the health center.', 'Resolved', 15, 3],
    [3, 6, 'Drainage cover missing', 'Burgos St. corner Rizal St.', 'The concrete cover of the drainage is missing, leaving an open hole at the corner.', 'Closed', 25, 4],
    [0, 5, 'Burning of garbage in backyard', 'Behind 3 Del Pilar St.', 'A neighbor burns plastic garbage every morning and the smoke enters our house.', 'Closed', 20, null],
    [5, 2, 'Loud motorcycles at night', 'Mabini St.', 'Motorcycles with modified mufflers pass at high speed late at night.', 'Rejected', 13, null],
    [2, 4, 'Streetlight on Luna St. out', 'In front of 88 Luna St.', 'The streetlight is not working.', 'Withdrawn', 11, null],
];
$staffIdx = 0;
foreach ($concernPlan as [$ci, $pk, $title, $loc, $desc, $final, $daysAgo, $prioOverride]) {
    $res = $residents[mt_rand(0, count($residents) - 1)];
    $cat = $catIds[$ci];
    $t = $now - $daysAgo * 86400 - mt_rand(3600, 6 * 3600);
    $priority = $prioOverride ?? $cat['prio'];
    $assignee = $staff[$staffIdx++ % 3];
    $id = db_insert(
        'INSERT INTO concerns (resident_id, category_id, purok_id, title, description, location, priority, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$res, $cat['id'], $pk, $title, $desc, $loc, $cat['prio'], $ts($t), $ts($t)]
    );
    db_exec("UPDATE concerns SET reference_no = CONCAT('CON-', YEAR(created_at), '-', LPAD(concern_id, 5, '0')) WHERE concern_id = ?", [$id]);
    $path = match ($final) {
        'Submitted' => ['Submitted'],
        'Under Review' => ['Submitted', 'Under Review'],
        'Assigned' => ['Submitted', 'Under Review', 'Assigned'],
        'In Progress' => ['Submitted', 'Under Review', 'Assigned', 'In Progress'],
        'Resolved' => ['Submitted', 'Under Review', 'Assigned', 'In Progress', 'Resolved'],
        'Closed' => ['Submitted', 'Under Review', 'Assigned', 'In Progress', 'Resolved', 'Closed'],
        'Rejected' => ['Submitted', 'Under Review', 'Rejected'],
        'Withdrawn' => ['Submitted', 'Withdrawn'],
    };
    $resolution = null;
    $prev = null;
    foreach ($path as $status) {
        $by = in_array($status, ['Submitted', 'Withdrawn', 'Closed'], true) ? $res : $assignee;
        $note = match ($status) {
            'Submitted' => 'Concern reported',
            'Under Review' => 'Verified by phone with the resident.',
            'Assigned' => 'Assigned to ' . db_value("SELECT CONCAT(first_name, ' ', last_name) FROM users WHERE user_id = ?", [$assignee]),
            'In Progress' => 'Coordinated with the barangay maintenance crew.',
            'Resolved' => 'The barangay maintenance crew repaired the problem and cleaned the area. Photos are on file at the barangay hall.',
            'Rejected' => 'This is a traffic matter under the municipal traffic office. The report was forwarded to them.',
            'Withdrawn' => 'Already fixed by the electric cooperative.',
            'Closed' => 'Confirmed fixed by the resident.',
        };
        if ($status === 'Resolved') {
            $resolution = $note;
        }
        db_query('INSERT INTO concern_updates (concern_id, from_status, to_status, note, changed_by, changed_at) VALUES (?, ?, ?, ?, ?, ?)',
            [$id, $prev, $status, $note, $by, $ts($t)]);
        $prev = $status;
        $t = min($t + mt_rand(3, 30) * 3600, $now - 600);
    }
    $hasAssignee = in_array($final, ['Assigned', 'In Progress', 'Resolved', 'Closed'], true);
    db_exec('UPDATE concerns SET status = ?, priority = ?, assigned_to = ?, resolution = ?, updated_at = ? WHERE concern_id = ?',
        [$final, $priority, $hasAssignee ? $assignee : null, $resolution, $ts($t), $id]);
}

// ---------------------------------------------------------------- A few activity log entries for history
$logRows = [
    [$admin, 'user_created', 'user', $staffA, 'staff@barangayservice.com created as staff', 89],
    [$admin, 'user_created', 'user', $staffB, 'staff2@barangayservice.com created as staff', 89],
    [$admin, 'user_created', 'user', $staffC, 'staff3@barangayservice.com created as staff', 88],
    [$admin, 'document_type_created', 'document_type', $docTypeIds[5]['id'], 'First-Time Jobseeker Certificate', 80],
    [$staffA, 'login', 'user', $staffA, 'Signed in', 1],
    [$admin, 'login', 'user', $admin, 'Signed in', 0],
];
foreach ($logRows as [$uid, $action, $et, $eid, $det, $d]) {
    db_query('INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details, ip_address, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
        [$uid, $action, $et, $eid, $det, '127.0.0.1', $ts($now - $d * 86400)]);
}
foreach (db_all('SELECT request_id, reference_no, status, updated_at, handled_by FROM document_requests WHERE handled_by IS NOT NULL') as $r) {
    db_query('INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details, ip_address, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
        [$r['handled_by'], 'request_status_changed', 'document_request', $r['request_id'], "{$r['reference_no']}: -> {$r['status']}", '127.0.0.1', $r['updated_at']]);
}
foreach (db_all('SELECT concern_id, reference_no, status, updated_at, assigned_to FROM concerns WHERE assigned_to IS NOT NULL') as $c) {
    db_query('INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details, ip_address, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
        [$c['assigned_to'], 'concern_status_changed', 'concern', $c['concern_id'], "{$c['reference_no']}: -> {$c['status']}", '127.0.0.1', $c['updated_at']]);
}

echo 'Seeded: ' . db_value('SELECT COUNT(*) FROM users') . ' users, '
    . db_value('SELECT COUNT(*) FROM document_requests') . ' requests, '
    . db_value('SELECT COUNT(*) FROM concerns') . " concerns.\n";
echo 'All demo accounts use the password: ' . DEMO_PASSWORD . "\n";
