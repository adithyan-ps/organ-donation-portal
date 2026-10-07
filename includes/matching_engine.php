<?php
/**
 * Organ Donation – Recipient Matching Portal
 * Explainable Preliminary Matching Engine
 */

require_once __DIR__ . '/../config.php';

/**
 * Check if donor's blood group is immunologically compatible with recipient
 * Based on Red Blood Cell / ABO & Rh compatibility rules
 */
function is_blood_compatible($donor_blood, $recipient_blood) {
    $matrix = get_blood_compatibility();
    if (!isset($matrix[$donor_blood])) {
        return false;
    }
    return in_array($recipient_blood, $matrix[$donor_blood], true);
}

/**
 * Calculate transparent, explainable compatibility score (0 to 100)
 * 
 * Scoring Formula:
 * 1. Organ Match (Prerequisite): 40 Points
 * 2. Blood Group Compatibility:
 *    - Identical match (e.g., O+ to O+): 35 Points
 *    - Compatible alternative (e.g., O- to A+): 30 Points
 * 3. Recipient Urgency Weight:
 *    - Critical: 20 Points
 *    - High: 15 Points
 *    - Medium: 10 Points
 *    - Low: 5 Points
 * 4. Location Proximity (Same city or region): 5 Points
 */
function calculate_match_details($donor, $recipient) {
    $details = [
        'is_match'          => false,
        'score'             => 0,
        'organ_points'      => 0,
        'blood_points'      => 0,
        'urgency_points'    => 0,
        'proximity_points'  => 0,
        'blood_match_type'  => 'Incompatible',
        'breakdown_summary' => '',
        'reasons'           => []
    ];

    // 1. Organ Match check
    if (strcasecmp($donor['organ_donated'], $recipient['organ_needed']) !== 0) {
        $details['breakdown_summary'] = 'Organ mismatch';
        return $details;
    }
    $details['organ_points'] = 40;
    $details['reasons'][] = "Identical Organ Type ({$donor['organ_donated']}): 40pts";

    // 2. Blood Compatibility check
    $donor_blood = $donor['blood_group'];
    $recipient_blood = $recipient['blood_group'];

    if (!is_blood_compatible($donor_blood, $recipient_blood)) {
        $details['breakdown_summary'] = "Blood type incompatible ({$donor_blood} cannot donate to {$recipient_blood})";
        return $details;
    }

    if ($donor_blood === $recipient_blood) {
        $details['blood_points'] = 35;
        $details['blood_match_type'] = 'Identical Match';
        $details['reasons'][] = "Exact Blood Group ({$donor_blood} &rarr; {$recipient_blood}): 35pts";
    } else {
        $details['blood_points'] = 30;
        $details['blood_match_type'] = 'Compatible Alternative';
        $details['reasons'][] = "Compatible Blood Group ({$donor_blood} &rarr; {$recipient_blood}): 30pts";
    }

    // 3. Recipient Urgency weight
    $urgency = $recipient['urgency_level'];
    $urgency_points = 10;
    if ($urgency === 'Critical') {
        $urgency_points = 20;
    } elseif ($urgency === 'High') {
        $urgency_points = 15;
    } elseif ($urgency === 'Medium') {
        $urgency_points = 10;
    } elseif ($urgency === 'Low') {
        $urgency_points = 5;
    }
    $details['urgency_points'] = $urgency_points;
    $details['reasons'][] = "Recipient Urgency ({$urgency}): {$urgency_points}pts";

    // 4. Proximity / City Match bonus
    $donor_city = trim(strtolower($donor['address_city'] ?? ''));
    $recipient_city = trim(strtolower($recipient['hospital_city'] ?? ''));
    $proximity_bonus = 0;

    if (!empty($donor_city) && !empty($recipient_city)) {
        // Check if either string contains the other or identical
        $d_parts = array_map('trim', explode(',', $donor_city));
        $r_parts = array_map('trim', explode(',', $recipient_city));
        
        $common = array_intersect($d_parts, $r_parts);
        if (!empty($common) || stripos($donor_city, $recipient_city) !== false || stripos($recipient_city, $donor_city) !== false) {
            $proximity_bonus = 5;
            $details['reasons'][] = "Location Proximity Match: 5pts";
        }
    }
    $details['proximity_points'] = $proximity_bonus;

    // Total score calculation (max 100)
    $total_score = $details['organ_points'] + $details['blood_points'] + $details['urgency_points'] + $details['proximity_points'];
    $total_score = min(100, $total_score);

    $details['is_match'] = true;
    $details['score'] = $total_score;
    $details['breakdown_summary'] = implode(' | ', $details['reasons']);

    return $details;
}

/**
 * Generate and retrieve all preliminary matches between verified donors and verified recipients
 * Optionally filtered by organ, blood group, urgency, or minimum score
 */
function find_all_potential_matches($filters = []) {
    $pdo = get_db_connection();

    // Query all verified and available donors
    $donorQuery = "SELECT * FROM donors WHERE verification_status = 'Verified' AND availability_status = 'Available'";
    $donorParams = [];
    if (!empty($filters['organ'])) {
        $donorQuery .= " AND organ_donated = ?";
        $donorParams[] = $filters['organ'];
    }
    if (!empty($filters['donor_blood'])) {
        $donorQuery .= " AND blood_group = ?";
        $donorParams[] = $filters['donor_blood'];
    }
    $donorStmt = $pdo->prepare($donorQuery);
    $donorStmt->execute($donorParams);
    $donors = $donorStmt->fetchAll();

    // Query all verified recipients
    $recipQuery = "SELECT * FROM recipients WHERE verification_status = 'Verified'";
    $recipParams = [];
    if (!empty($filters['organ'])) {
        $recipQuery .= " AND organ_needed = ?";
        $recipParams[] = $filters['organ'];
    }
    if (!empty($filters['recipient_blood'])) {
        $recipQuery .= " AND blood_group = ?";
        $recipParams[] = $filters['recipient_blood'];
    }
    if (!empty($filters['urgency'])) {
        $recipQuery .= " AND urgency_level = ?";
        $recipParams[] = $filters['urgency'];
    }
    $recipStmt = $pdo->prepare($recipQuery);
    $recipStmt->execute($recipParams);
    $recipients = $recipStmt->fetchAll();

    // Fetch existing matches from the database to retain status ('Under Review', 'Contacted', 'Approved', etc.)
    $matchMap = [];
    $existingStmt = $pdo->query("SELECT * FROM matches");
    while ($row = $existingStmt->fetch()) {
        $key = $row['donor_id'] . '_' . $row['recipient_id'];
        $matchMap[$key] = $row;
    }

    $results = [];
    $urgency_weight = ['Critical' => 4, 'High' => 3, 'Medium' => 2, 'Low' => 1];

    foreach ($donors as $d) {
        foreach ($recipients as $r) {
            $analysis = calculate_match_details($d, $r);
            if ($analysis['is_match']) {
                $key = $d['id'] . '_' . $r['id'];
                $status = 'Potential';
                $match_id = null;
                $notes = '';
                $matched_at = date('Y-m-d H:i:s');

                if (isset($matchMap[$key])) {
                    $match_id   = $matchMap[$key]['id'];
                    $status     = $matchMap[$key]['status'];
                    $notes      = $matchMap[$key]['notes'];
                    $matched_at = $matchMap[$key]['matched_at'];
                } else {
                    // Automatically record new potential match in the database
                    try {
                        $ins = $pdo->prepare("INSERT INTO matches (donor_id, recipient_id, organ, compatibility_score, score_breakdown, status, notes, matched_at) 
                                              VALUES (?, ?, ?, ?, ?, 'Potential', 'Identified automatically by matching engine.', NOW())");
                        $ins->execute([$d['id'], $r['id'], $d['organ_donated'], $analysis['score'], $analysis['breakdown_summary']]);
                        $match_id = $pdo->lastInsertId();
                    } catch (Exception $e) {
                        // In case of unique constraint race
                    }
                }

                // Check optional filter by match status
                if (!empty($filters['status']) && $status !== $filters['status']) {
                    continue;
                }

                $results[] = [
                    'match_id'            => $match_id,
                    'donor_id'            => $d['id'],
                    'donor_name'          => $d['full_name'],
                    'donor_blood'         => $d['blood_group'],
                    'donor_city'          => $d['address_city'],
                    'donor_availability'  => $d['availability_status'],
                    'recipient_id'        => $r['id'],
                    'recipient_name'      => $r['full_name'],
                    'recipient_blood'     => $r['blood_group'],
                    'recipient_hospital'  => $r['hospital_city'],
                    'recipient_urgency'   => $r['urgency_level'],
                    'organ'               => $d['organ_donated'],
                    'score'               => $analysis['score'],
                    'blood_match_type'    => $analysis['blood_match_type'],
                    'breakdown_summary'   => $analysis['breakdown_summary'],
                    'status'              => $status,
                    'notes'               => $notes,
                    'matched_at'          => $matched_at,
                    'urgency_order'       => $urgency_weight[$r['urgency_level']] ?? 0
                ];
            }
        }
    }

    // Sort by Recipient Urgency (Critical first), then Score DESC
    usort($results, function ($a, $b) {
        if ($a['urgency_order'] !== $b['urgency_order']) {
            return $b['urgency_order'] <=> $a['urgency_order'];
        }
        return $b['score'] <=> $a['score'];
    });

    return $results;
}

/**
 * Get aggregate statistics for dashboards
 */
function get_portal_summary_statistics() {
    $pdo = get_db_connection();

    $stats = [
        'total_donors'          => 0,
        'verified_donors'       => 0,
        'pending_donors'        => 0,
        'total_recipients'      => 0,
        'verified_recipients'   => 0,
        'pending_recipients'    => 0,
        'pending_verifications' => 0,
        'total_matches'         => 0,
        'approved_matches'      => 0,
        'under_review_matches'  => 0,
        'lives_supported'       => 0
    ];

    try {
        // Donors count
        $stmt = $pdo->query("SELECT 
            COUNT(*) AS total,
            SUM(CASE WHEN verification_status = 'Verified' THEN 1 ELSE 0 END) AS verified,
            SUM(CASE WHEN verification_status = 'Pending' THEN 1 ELSE 0 END) AS pending
            FROM donors");
        $donorData = $stmt->fetch();
        $stats['total_donors'] = (int)($donorData['total'] ?? 0);
        $stats['verified_donors'] = (int)($donorData['verified'] ?? 0);
        $stats['pending_donors'] = (int)($donorData['pending'] ?? 0);

        // Recipients count
        $stmt = $pdo->query("SELECT 
            COUNT(*) AS total,
            SUM(CASE WHEN verification_status = 'Verified' THEN 1 ELSE 0 END) AS verified,
            SUM(CASE WHEN verification_status = 'Pending' THEN 1 ELSE 0 END) AS pending
            FROM recipients");
        $recipData = $stmt->fetch();
        $stats['total_recipients'] = (int)($recipData['total'] ?? 0);
        $stats['verified_recipients'] = (int)($recipData['verified'] ?? 0);
        $stats['pending_recipients'] = (int)($recipData['pending'] ?? 0);

        $stats['pending_verifications'] = $stats['pending_donors'] + $stats['pending_recipients'];

        // Matches count
        $stmt = $pdo->query("SELECT 
            COUNT(*) AS total,
            SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) AS approved,
            SUM(CASE WHEN status = 'Under Review' THEN 1 ELSE 0 END) AS under_review
            FROM matches");
        $matchData = $stmt->fetch();
        $stats['total_matches'] = (int)($matchData['total'] ?? 0);
        $stats['approved_matches'] = (int)($matchData['approved'] ?? 0);
        $stats['under_review_matches'] = (int)($matchData['under_review'] ?? 0);

        $stats['lives_supported'] = $stats['approved_matches'] + max(1, (int)($stats['under_review_matches'] / 2));

    } catch (Exception $e) {
        error_log("Error calculating portal statistics: " . $e->getMessage());
    }

    return $stats;
}

/**
 * Convenience alias for finding preliminary matches
 */
function run_preliminary_matching($pdo = null, $filters = []) {
    return find_all_potential_matches($filters);
}

/**
 * Convenience alias for evaluating compatibility
 */
function evaluate_compatibility($donor, $recipient) {
    return calculate_match_details($donor, $recipient);
}
