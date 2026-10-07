<?php
/**
 * Organ Donation – Recipient Matching Portal
 * Bespoke Modern Landing Page with Real-Time Compatibility Simulator
 */

$page_title = "LifeBridge - Life-Saving Organ Matching Network";
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/matching_engine.php';

// Fetch live dynamic statistics from database
$stats = get_portal_summary_statistics();

// Recent active matches for live preview terminal
$previewMatches = array_slice(find_all_potential_matches(), 0, 3);

// If no active matches in DB, provide realistic demonstration stream
if (empty($previewMatches)) {
    $previewMatches = [
        [
            'organ' => 'Kidney',
            'score' => 95,
            'donor_blood' => 'O+',
            'recipient_blood' => 'O+',
            'recipient_urgency' => 'High',
            'status' => 'Under Review'
        ],
        [
            'organ' => 'Liver',
            'score' => 90,
            'donor_blood' => 'O-',
            'recipient_blood' => 'A+',
            'recipient_urgency' => 'Critical',
            'status' => 'Under Review'
        ],
        [
            'organ' => 'Heart',
            'score' => 98,
            'donor_blood' => 'B+',
            'recipient_blood' => 'B+',
            'recipient_urgency' => 'Critical',
            'status' => 'Approved'
        ]
    ];
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- ======================================================= -->
<!-- HERO SECTION                                            -->
<!-- ======================================================= -->
<section class="hero" id="home">
  <div class="container">
    <div class="hero-grid">
      
      <!-- Hero Left: Mission & Action Triggers -->
      <div>
        <div class="hero-pill">
          <span style="font-size: 1rem;">⚡</span>
          <span>Next-Gen Immuno Matching Network &bull; Live Clinical Stream</span>
        </div>

        <h1 class="hero-title">
          Connecting Hope with 
          <span class="gradient-text-teal">Life-Saving</span> 
          <span class="gradient-text-rose">Possibilities</span>
        </h1>

        <p class="hero-lead">
          A centralized clinical matching platform uniting selfless organ donors, priority recipients, and hospital surgical teams. Powered by transparent immunohematological algorithms to accelerate preliminary life-saving evaluations.
        </p>

        <div class="hero-cta-group">
          <a href="<?php echo BASE_URL; ?>/donor_register.php" class="btn btn-rose btn-lg">
            <span>❤️ Register as Donor</span>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
          </a>
          <a href="<?php echo BASE_URL; ?>/recipient_register.php" class="btn btn-secondary btn-lg">
            <span>🔍 Find Recipient Match</span>
          </a>
          <a href="#simulator" class="btn btn-outline btn-lg">
            <span>⚡ Test Simulator</span>
          </a>
        </div>

        <!-- Trust Badges Strip -->
        <div class="hero-trust-badge">
          <div class="hero-trust-item">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="#0d9488"><path d="M12 2L3 7v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V7l-9-5zm-2 16l-4-4 1.41-1.41L10 15.17l6.59-6.59L18 10l-8 8z"/></svg>
            <span>100% Confidential</span>
          </div>
          <div class="hero-trust-item">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="#0284c7"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" fill="none"/><path d="M12 6v6l4 2" stroke="currentColor" stroke-width="2"/></svg>
            <span>Doctor-Verified Records</span>
          </div>
          <div class="hero-trust-item">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="#10b981"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
            <span>Zero Commercial Bias</span>
          </div>
        </div>
      </div>

      <!-- Hero Right: Interactive Live Compatibility Terminal -->
      <div>
        <div class="hero-visual-card">
          <div class="hero-visual-header">
            <div>
              <h3 style="margin-bottom: 0.15rem; font-size: 1.15rem;">Live Compatibility Terminal</h3>
              <span style="font-size: 0.82rem; color: var(--text-muted);">Real-time algorithmic stream</span>
            </div>
            <div class="live-pulse">Active Stream</div>
          </div>

          <!-- Quick Interactive Blood Compatibility Widget -->
          <div class="hero-blood-widget">
            <div class="hero-blood-label">
              <span>Quick Blood Compatibility Check</span>
              <span style="color: var(--primary); font-size: 0.76rem;">Select blood type:</span>
            </div>
            <div class="hero-blood-chips">
              <button type="button" class="blood-chip active" onclick="checkHeroBlood('O-')">O-</button>
              <button type="button" class="blood-chip" onclick="checkHeroBlood('O+')">O+</button>
              <button type="button" class="blood-chip" onclick="checkHeroBlood('A-')">A-</button>
              <button type="button" class="blood-chip" onclick="checkHeroBlood('A+')">A+</button>
              <button type="button" class="blood-chip" onclick="checkHeroBlood('B-')">B-</button>
              <button type="button" class="blood-chip" onclick="checkHeroBlood('B+')">B+</button>
              <button type="button" class="blood-chip" onclick="checkHeroBlood('AB-')">AB-</button>
              <button type="button" class="blood-chip" onclick="checkHeroBlood('AB+')">AB+</button>
            </div>
            <div class="hero-blood-result" id="heroBloodResult">
              <div><strong>O- (Universal Donor)</strong>: Can give to all blood groups</div>
              <span class="badge badge-verified" style="font-size: 0.72rem;">100% Compatible</span>
            </div>
          </div>

          <!-- Real-Time Matches Ticker -->
          <div style="display: flex; flex-direction: column; gap: 0.75rem;">
            <?php foreach ($previewMatches as $pm): ?>
              <div style="background: var(--bg-subtle); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 0.85rem 1rem; display: flex; justify-content: space-between; align-items: center;">
                <div>
                  <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
                    <strong style="font-size: 0.95rem; color: var(--text-main);">
                      <?php 
                        $organIcon = match(strtolower($pm['organ'])) {
                          'heart' => '🫀',
                          'kidney', 'kidneys' => '🫘',
                          'liver' => '🩸',
                          'lungs', 'lung' => '🫁',
                          'pancreas' => '🩺',
                          default => '👁️'
                        };
                        echo $organIcon . ' ' . htmlspecialchars($pm['organ']); 
                      ?> Match
                    </strong>
                    <span class="badge <?php echo ($pm['score'] >= 95) ? 'badge-approved' : 'badge-potential'; ?>" style="font-size: 0.74rem;">
                      <?php echo $pm['score']; ?>% Score
                    </span>
                  </div>
                  <div style="font-size: 0.8rem; color: var(--text-muted);">
                    Donor <strong><?php echo htmlspecialchars($pm['donor_blood']); ?></strong> &rarr; Recipient <strong><?php echo htmlspecialchars($pm['recipient_blood']); ?></strong> (<?php echo htmlspecialchars($pm['recipient_urgency']); ?> Urgency)
                  </div>
                </div>
                <span class="badge <?php echo ($pm['status'] === 'Approved') ? 'badge-approved' : (($pm['status'] === 'Under Review') ? 'badge-review' : 'badge-potential'); ?>" style="font-size: 0.74rem;">
                  <?php echo htmlspecialchars($pm['status']); ?>
                </span>
              </div>
            <?php endforeach; ?>
          </div>

          <!-- Terminal Footer -->
          <div style="margin-top: 1.25rem; padding-top: 0.85rem; border-top: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; font-size: 0.8rem; color: var(--text-muted);">
            <span>Immunohematology Rule Set v2.4</span>
            <a href="<?php echo BASE_URL; ?>/login.php" style="font-weight: 700; color: var(--primary);">View Dashboard &rarr;</a>
          </div>
        </div>
      </div>

    </div>
  </div>
</section>

<!-- ======================================================= -->
<!-- LIVE PORTAL METRICS (LUMINOUS GLASS CARDS)              -->
<!-- ======================================================= -->
<section class="stats-section" aria-label="Portal Metrics">
  <div class="container">
    <div class="stats-grid">
      
      <!-- Donors -->
      <div class="stat-card card-donors">
        <div class="stat-icon donors">
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>
        </div>
        <div>
          <div class="stat-number"><?php echo number_format($stats['total_donors']); ?></div>
          <div class="stat-label">Registered Donors (<?php echo $stats['verified_donors']; ?> Verified)</div>
        </div>
      </div>

      <!-- Recipients -->
      <div class="stat-card card-recipients">
        <div class="stat-icon recipients">
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
        </div>
        <div>
          <div class="stat-number"><?php echo number_format($stats['total_recipients']); ?></div>
          <div class="stat-label">Recipients on Waitlist</div>
        </div>
      </div>

      <!-- Matches Evaluated -->
      <div class="stat-card card-matches">
        <div class="stat-icon matches">
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
        </div>
        <div>
          <div class="stat-number"><?php echo number_format($stats['total_matches']); ?></div>
          <div class="stat-label">Matches Evaluated (<?php echo $stats['approved_matches']; ?> Approved)</div>
        </div>
      </div>

      <!-- Lives Supported -->
      <div class="stat-card card-lives">
        <div class="stat-icon lives">
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
        </div>
        <div>
          <div class="stat-number"><?php echo number_format($stats['lives_supported']); ?>+</div>
          <div class="stat-label">Lives Guided &amp; Supported</div>
        </div>
      </div>

    </div>
  </div>
</section>

<!-- Academic & Medical Disclaimer -->
<div class="container" style="margin-top: 1rem; margin-bottom: 2rem;">
  <div class="disclaimer-banner">
    <div class="icon">🛡️</div>
    <div>
      <h4>Academic Demonstration &amp; Clinical Safety Protocol</h4>
      <p><?php echo MEDICAL_DISCLAIMER; ?> Digital match scores represent preliminary evaluations for clinical coordinators and do not bypass authorized hospital tissue-typing procedures.</p>
    </div>
  </div>
</div>

<!-- ======================================================= -->
<!-- INTERACTIVE ORGAN MATCH SIMULATOR (HOMEPAGE LAB FEATURE) -->
<!-- ======================================================= -->
<section class="section simulator-section" id="simulator">
  <div class="container">
    <div class="section-header">
      <div class="section-subtitle">Interactive Clinical Lab</div>
      <h2 class="section-title">Live Organ Match Simulator</h2>
      <p class="section-desc">
        Test our 100-point preliminary matching algorithm in real-time. Select organ type, donor &amp; recipient blood groups, and medical urgency priority to view instant compatibility calculations.
      </p>
    </div>

    <div class="simulator-card">
      
      <!-- Left: Interactive Controls -->
      <div class="simulator-controls">
        
        <!-- Organ Selection -->
        <span class="sim-group-label">1. Select Organ Type</span>
        <div class="sim-organ-chips">
          <button type="button" class="sim-organ-btn active" onclick="setSimOrgan('Kidney')">🫘 Kidney</button>
          <button type="button" class="sim-organ-btn" onclick="setSimOrgan('Liver')">🩸 Liver</button>
          <button type="button" class="sim-organ-btn" onclick="setSimOrgan('Heart')">🫀 Heart</button>
          <button type="button" class="sim-organ-btn" onclick="setSimOrgan('Lungs')">🫁 Lungs</button>
          <button type="button" class="sim-organ-btn" onclick="setSimOrgan('Pancreas')">🩺 Pancreas</button>
          <button type="button" class="sim-organ-btn" onclick="setSimOrgan('Cornea')">👁️ Cornea</button>
        </div>

        <!-- Donor Blood Selection -->
        <span class="sim-group-label">2. Donor Blood Group</span>
        <div class="sim-blood-grid" id="simDonorBlood">
          <button type="button" class="sim-blood-btn active" onclick="setSimDonorBlood('O-')">O-</button>
          <button type="button" class="sim-blood-btn" onclick="setSimDonorBlood('O+')">O+</button>
          <button type="button" class="sim-blood-btn" onclick="setSimDonorBlood('A-')">A-</button>
          <button type="button" class="sim-blood-btn" onclick="setSimDonorBlood('A+')">A+</button>
          <button type="button" class="sim-blood-btn" onclick="setSimDonorBlood('B-')">B-</button>
          <button type="button" class="sim-blood-btn" onclick="setSimDonorBlood('B+')">B+</button>
          <button type="button" class="sim-blood-btn" onclick="setSimDonorBlood('AB-')">AB-</button>
          <button type="button" class="sim-blood-btn" onclick="setSimDonorBlood('AB+')">AB+</button>
        </div>

        <!-- Recipient Blood Selection -->
        <span class="sim-group-label">3. Recipient Blood Group</span>
        <div class="sim-blood-grid" id="simRecipBlood">
          <button type="button" class="sim-blood-btn" onclick="setSimRecipBlood('O-')">O-</button>
          <button type="button" class="sim-blood-btn" onclick="setSimRecipBlood('O+')">O+</button>
          <button type="button" class="sim-blood-btn" onclick="setSimRecipBlood('A-')">A-</button>
          <button type="button" class="sim-blood-btn active" onclick="setSimRecipBlood('A+')">A+</button>
          <button type="button" class="sim-blood-btn" onclick="setSimRecipBlood('B-')">B-</button>
          <button type="button" class="sim-blood-btn" onclick="setSimRecipBlood('B+')">B+</button>
          <button type="button" class="sim-blood-btn" onclick="setSimRecipBlood('AB-')">AB-</button>
          <button type="button" class="sim-blood-btn" onclick="setSimRecipBlood('AB+')">AB+</button>
        </div>

        <!-- Recipient Urgency Selection -->
        <span class="sim-group-label">4. Clinical Urgency Priority</span>
        <div class="sim-urgency-row" id="simUrgency">
          <button type="button" class="sim-urgency-btn active" onclick="setSimUrgency('Critical')">🚨 Critical</button>
          <button type="button" class="sim-urgency-btn" onclick="setSimUrgency('High')">⚡ High</button>
          <button type="button" class="sim-urgency-btn" onclick="setSimUrgency('Medium')">⏳ Medium</button>
          <button type="button" class="sim-urgency-btn" onclick="setSimUrgency('Low')">📋 Low</button>
        </div>

      </div>

      <!-- Right: Live Diagnostic Gauge & Score Breakdown -->
      <div class="simulator-result">
        <div>
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <span style="font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #38bdf8;">
              Preliminary Compatibility Score
            </span>
            <span class="badge badge-approved" id="simStatusBadge" style="font-size: 0.76rem;">Compatible Match</span>
          </div>

          <div class="sim-gauge-wrapper">
            <div class="sim-score-number" id="simScoreValue">95%</div>
            <div class="sim-score-label" id="simScoreVerdict">High Clinical Match</div>
          </div>

          <!-- Breakdown Bars -->
          <div class="sim-breakdown-list">
            
            <div>
              <div class="sim-breakdown-row">
                <span>Organ Match (Identical Type)</span>
                <strong id="simPtsOrgan">40 / 40 pts</strong>
              </div>
              <div class="sim-breakdown-bar">
                <div class="sim-breakdown-fill" id="simBarOrgan" style="width: 100%;"></div>
              </div>
            </div>

            <div>
              <div class="sim-breakdown-row">
                <span>Blood Compatibility (<span id="simBloodTypeLabel">Compatible Alt</span>)</span>
                <strong id="simPtsBlood">30 / 35 pts</strong>
              </div>
              <div class="sim-breakdown-bar">
                <div class="sim-breakdown-fill" id="simBarBlood" style="width: 85%;"></div>
              </div>
            </div>

            <div>
              <div class="sim-breakdown-row">
                <span>Clinical Urgency Weight</span>
                <strong id="simPtsUrgency">20 / 20 pts</strong>
              </div>
              <div class="sim-breakdown-bar">
                <div class="sim-breakdown-fill" id="simBarUrgency" style="width: 100%;"></div>
              </div>
            </div>

            <div>
              <div class="sim-breakdown-row">
                <span>Regional Proximity Buffer</span>
                <strong id="simPtsProximity">5 / 5 pts</strong>
              </div>
              <div class="sim-breakdown-bar">
                <div class="sim-breakdown-fill" id="simBarProximity" style="width: 100%;"></div>
              </div>
            </div>

          </div>

          <div style="font-size: 0.82rem; color: #94a3b8; line-height: 1.5; margin-bottom: 1.5rem;" id="simClinicalNotes">
            💡 <strong>Clinical Assessment:</strong> O- is a universal donor compatible with A+ recipient. Recipient is prioritized under critical ICU urgency protocol. Immediate immuno cross-match is viable.
          </div>
        </div>

        <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
          <a href="<?php echo BASE_URL; ?>/donor_register.php" class="btn btn-primary" style="flex: 1; font-size: 0.88rem;">Pledge This Organ</a>
          <a href="<?php echo BASE_URL; ?>/recipient_register.php" class="btn btn-secondary" style="flex: 1; font-size: 0.88rem;">Register Requirement</a>
        </div>
      </div>

    </div>
  </div>
</section>

<!-- ======================================================= -->
<!-- STREAMLINED WORKFLOW: HOW IT WORKS                      -->
<!-- ======================================================= -->
<section class="section" id="how-it-works" style="background-color: #ffffff; border-bottom: 1px solid var(--border);">
  <div class="container">
    <div class="section-header">
      <div class="section-subtitle">Standardized Protocol</div>
      <h2 class="section-title">How The Matching Portal Operates</h2>
      <p class="section-desc">
        From voluntary pledge registration to verified clinical allocation, our 3-tier workflow ensures maximum speed, ethical fairness, and data privacy.
      </p>
    </div>

    <div class="steps-grid">
      
      <!-- Step 1 -->
      <div class="step-card">
        <div class="step-badge">01</div>
        <h3>Register &amp; Consent</h3>
        <p>
          Donors record their voluntary pledge with basic health declarations. Recipients register medical requirements, attending hospital, and physician-assigned urgency levels.
        </p>
        <div>
          <span class="badge badge-low">Bcrypt Hashed &bull; 256-Bit SSL</span>
        </div>
      </div>

      <!-- Step 2 -->
      <div class="step-card">
        <div class="step-badge">02</div>
        <h3>Medical Verification</h3>
        <p>
          Transplant administrators examine hospital referrals, medical clearance records, and identity consent before approving registrations from <em>Pending</em> to <em>Verified</em>.
        </p>
        <div>
          <span class="badge badge-pending">Coordinator Review Queue</span>
        </div>
      </div>

      <!-- Step 3 -->
      <div class="step-card">
        <div class="step-badge">03</div>
        <h3>Automated Allocation</h3>
        <p>
          The matching engine continuously computes ABO blood compatibility, organ eligibility, and urgency weighting to trigger actionable preliminary match alerts for surgeons.
        </p>
        <div>
          <span class="badge badge-verified">100-Point Scoring Algorithm</span>
        </div>
      </div>

    </div>
  </div>
</section>

<!-- ======================================================= -->
<!-- AWARENESS: ORGANS & MYTHS VS FACTS                      -->
<!-- ======================================================= -->
<section class="section" id="awareness">
  <div class="container">
    <div class="section-header">
      <div class="section-subtitle">Public Education</div>
      <h2 class="section-title">Organ Donation Awareness</h2>
      <p class="section-desc">
        One organ donor can save up to 8 lives and heal over 75 people through tissue restoration. Learn about eligible organs and verified clinical facts.
      </p>
    </div>

    <!-- Organs Eligible Grid -->
    <h3 style="font-size: 1.3rem; margin-bottom: 1.5rem;">Organs &amp; Tissues Eligible For Donation</h3>
    <div class="awareness-grid">
      
      <div class="organ-card">
        <div class="organ-icon">🫘</div>
        <div>
          <h4>Kidneys (2)</h4>
          <span>Viability: 24–36 hrs &bull; Living or deceased donation. Most common life-saving procedure.</span>
        </div>
      </div>

      <div class="organ-card">
        <div class="organ-icon">🫁</div>
        <div>
          <h4>Lungs (2)</h4>
          <span>Viability: 4–6 hrs &bull; Can be allocated singly or bilaterally for respiratory failure.</span>
        </div>
      </div>

      <div class="organ-card">
        <div class="organ-icon">🫀</div>
        <div>
          <h4>Heart</h4>
          <span>Viability: 4–6 hrs &bull; Critical allocation for end-stage cardiac failure patients.</span>
        </div>
      </div>

      <div class="organ-card">
        <div class="organ-icon">🩸</div>
        <div>
          <h4>Liver</h4>
          <span>Viability: 8–12 hrs &bull; Regenerative organ; split-liver donation can save two lives.</span>
        </div>
      </div>

      <div class="organ-card">
        <div class="organ-icon">🩺</div>
        <div>
          <h4>Pancreas</h4>
          <span>Viability: 12–18 hrs &bull; Restores natural insulin balance for severe type-1 diabetics.</span>
        </div>
      </div>

      <div class="organ-card">
        <div class="organ-icon">👁️</div>
        <div>
          <h4>Corneas (2)</h4>
          <span>Viability: Up to 14 days &bull; Restores sight to corneal blindness patients.</span>
        </div>
      </div>

    </div>

    <!-- Myths vs Facts Grid -->
    <h3 style="font-size: 1.3rem; margin-bottom: 1.5rem;">Debunking Common Myths with Clinical Facts</h3>
    <div class="myths-grid" style="margin-bottom: 4rem;">
      
      <div class="myth-card">
        <div class="myth-item">
          <span class="myth-tag">MYTH</span>
          <p style="font-weight: 700; color: #0f172a;">"If doctors know I'm a registered donor, they won't try as hard to save my life."</p>
        </div>
        <div class="myth-item" style="margin-bottom: 0;">
          <span class="fact-tag">FACT</span>
          <p style="color: var(--text-muted); font-size: 0.94rem;">
            Emergency doctors have a strict medical duty exclusively focused on saving your life. The transplant coordination team is completely separate and is only notified after death is confirmed by multiple independent neurologists.
          </p>
        </div>
      </div>

      <div class="myth-card">
        <div class="myth-item">
          <span class="myth-tag">MYTH</span>
          <p style="font-weight: 700; color: #0f172a;">"I am too old or have medical conditions to be an organ donor."</p>
        </div>
        <div class="myth-item" style="margin-bottom: 0;">
          <span class="fact-tag">FACT</span>
          <p style="color: var(--text-muted); font-size: 0.94rem;">
            There is no strict age cutoff for organ and tissue donation. Individuals in their 70s and 80s have saved lives. Doctors evaluate organ health individually at the time of donation.
          </p>
        </div>
      </div>

      <div class="myth-card">
        <div class="myth-item">
          <span class="myth-tag">MYTH</span>
          <p style="font-weight: 700; color: #0f172a;">"Wealthy or celebrity patients get transplanted faster."</p>
        </div>
        <div class="myth-item" style="margin-bottom: 0;">
          <span class="fact-tag">FACT</span>
          <p style="color: var(--text-muted); font-size: 0.94rem;">
            Allocation algorithms operate strictly on biological compatibility, severity of illness, and waitlist time. Financial status or social background have zero impact on matching rankings.
          </p>
        </div>
      </div>

      <div class="myth-card">
        <div class="myth-item">
          <span class="myth-tag">MYTH</span>
          <p style="font-weight: 700; color: #0f172a;">"My religion opposes organ donation."</p>
        </div>
        <div class="myth-item" style="margin-bottom: 0;">
          <span class="fact-tag">FACT</span>
          <p style="color: var(--text-muted); font-size: 0.94rem;">
            All major world religions—including Christianity, Islam, Hinduism, Buddhism, and Judaism—regard organ donation as an act of selfless charity, love, and compassionate service to human life.
          </p>
        </div>
      </div>

    </div>

    <!-- FAQ Accordion -->
    <h3 style="font-size: 1.3rem; margin-bottom: 1.5rem; text-align: center;">Frequently Asked Questions</h3>
    <div class="faq-list">
      
      <div class="faq-item">
        <button type="button" class="faq-question" aria-expanded="false">
          <span>Who can register as an organ donor on this portal?</span>
          <span style="font-size: 1.3rem;">+</span>
        </button>
        <div class="faq-answer">
          Any individual aged 18 years or older can record their voluntary pledge. Basic health parameters are documented for preliminary matching, while full clinical evaluations take place through accredited transplant hospitals.
        </div>
      </div>

      <div class="faq-item">
        <button type="button" class="faq-question" aria-expanded="false">
          <span>How does the 100-point compatibility score work?</span>
          <span style="font-size: 1.3rem;">+</span>
        </button>
        <div class="faq-answer">
          Our algorithm awards: 40 points for exact organ requirement match, 35 points for identical blood group (30 points for compatible alternative e.g., O- to A+), up to 20 points for clinical urgency (Critical: 20, High: 15, Medium: 10, Low: 5), and a 5-point regional proximity bonus.
        </div>
      </div>

      <div class="faq-item">
        <button type="button" class="faq-question" aria-expanded="false">
          <span>Can I pause my availability or update my details?</span>
          <span style="font-size: 1.3rem;">+</span>
        </button>
        <div class="faq-answer">
          Yes. Donors can log into their private dashboard at any time to set their status to "Temporarily Unavailable" (e.g. during illness or travel) or update contact information.
        </div>
      </div>

      <div class="faq-item">
        <button type="button" class="faq-question" aria-expanded="false">
          <span>Is my personal and medical data kept confidential?</span>
          <span style="font-size: 1.3rem;">+</span>
        </button>
        <div class="faq-answer">
          Yes. Strict pseudonymization ensures neither donors nor recipients see identifying contact details. All communication is brokered securely through authorized clinical coordinators.
        </div>
      </div>

    </div>

  </div>
</section>

<!-- ======================================================= -->
<!-- UN SUSTAINABLE DEVELOPMENT GOALS (SDG 3 & 10)           -->
<!-- ======================================================= -->
<section class="section sdg-section" id="sdgs">
  <div class="container">
    <div class="section-header">
      <div class="section-subtitle">Global Humanitarian Impact</div>
      <h2 class="section-title">Supporting UN Sustainable Development Goals</h2>
      <p class="section-desc">
        Demonstrating how open digital health technologies contribute to universal healthcare equity and non-discriminatory medical access.
      </p>
    </div>

    <div class="sdg-grid">
      
      <!-- SDG 3 -->
      <div class="sdg-card">
        <div class="sdg-badge sdg-3">
          <span class="sdg-num">3</span>
          <span class="sdg-text">Health</span>
        </div>
        <div class="sdg-content">
          <h3>SDG 3: Good Health and Well-Being</h3>
          <p>
            Target 3.4 &amp; 3.8: Reducing premature mortality from non-communicable organ failure by accelerating the identification of viable, immunologically compatible organs.
          </p>
          <ul style="margin-top: 0.75rem; padding-left: 1.25rem; font-size: 0.88rem; color: var(--text-muted); line-height: 1.6;">
            <li>Minimizes delays for ICU patients requiring emergency organ allocation.</li>
            <li>Optimizes allocation transparency to prevent wasted organ windows.</li>
          </ul>
        </div>
      </div>

      <!-- SDG 10 -->
      <div class="sdg-card">
        <div class="sdg-badge sdg-10">
          <span class="sdg-num">10</span>
          <span class="sdg-text">Equality</span>
        </div>
        <div class="sdg-content">
          <h3>SDG 10: Reduced Inequalities</h3>
          <p>
            Target 10.2 &amp; 10.3: Eliminating socioeconomic bias in organ allocation through an objective, algorithmic matching pipeline that prioritizes clinical urgency and biological compatibility above all else.
          </p>
          <ul style="margin-top: 0.75rem; padding-left: 1.25rem; font-size: 0.88rem; color: var(--text-muted); line-height: 1.6;">
            <li>Fair waitlist triage irrespective of financial standing or background.</li>
            <li>Open public registration with standardized criteria and audit logs.</li>
          </ul>
        </div>
      </div>

    </div>
  </div>
</section>

<!-- ======================================================= -->
<!-- HIGH-IMPACT CALL TO ACTION BANNER                       -->
<!-- ======================================================= -->
<section style="background: linear-gradient(135deg, #090d16 0%, #042f2e 50%, #0f172a 100%); color: #ffffff; padding: 5rem 0; text-align: center; position: relative; overflow: hidden;">
  <div class="container" style="position: relative; z-index: 2;">
    <span class="badge" style="background: rgba(13, 148, 136, 0.25); border: 1px solid rgba(13, 148, 136, 0.4); color: #5eead4; margin-bottom: 1rem; font-size: 0.85rem;">
      ✨ Be The Reason Someone Lives
    </span>
    <h2 style="color: #ffffff; font-size: 2.6rem; margin-bottom: 1rem; letter-spacing: -0.03em;">
      Ready to Make a Life-Saving Difference?
    </h2>
    <p style="max-width: 640px; margin: 0 auto 2.5rem auto; font-size: 1.15rem; color: #cbd5e1; line-height: 1.65;">
      Register your generous pledge as a donor today or submit a recipient requirement for preliminary compatibility assessment.
    </p>
    <div style="display: flex; justify-content: center; gap: 1rem; flex-wrap: wrap;">
      <a href="<?php echo BASE_URL; ?>/donor_register.php" class="btn btn-rose btn-lg">
        ❤️ Register as Donor Today
      </a>
      <a href="<?php echo BASE_URL; ?>/recipient_register.php" class="btn btn-secondary btn-lg">
        🔍 Find a Recipient Match
      </a>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
