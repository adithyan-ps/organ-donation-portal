<?php
/**
 * Organ Donation – Recipient Matching Portal
 * Modern Healthcare Footer with Disclaimers & Quick Links
 */
?>
  </main>
  <!-- Main Content Wrapper Ends -->

  <!-- Healthcare Footer -->
  <footer class="footer" role="contentinfo">
    <div class="container">
      <!-- Legal & Medical Notice -->
      <div class="footer-disclaimer-card">
        <strong style="color: #38bdf8;">Academic Demonstration & Medical Disclaimer:</strong>
        <p style="margin-top: 0.25rem; font-size: 0.8rem; color: #cbd5e1;">
          <?php echo MEDICAL_DISCLAIMER; ?>
        </p>
      </div>

      <div class="footer-grid">
        <!-- Brand Summary -->
        <div class="footer-brand">
          <h3><?php echo APP_SHORT_NAME; ?></h3>
          <p>
            An ethical, technology-driven preliminary donor-recipient matching portal bridging the gap between life-saving pledges and patients in critical need.
          </p>
          <div style="margin-top: 1.25rem; display: flex; gap: 0.75rem;">
            <span class="badge" style="background: #1e3a8a; color: #bfdbfe;">Academic Project</span>
            <span class="badge" style="background: #064e3b; color: #a7f3d0;">UN SDG Aligned</span>
          </div>
        </div>

        <!-- Quick Links -->
        <div class="footer-column">
          <h4>Navigation</h4>
          <ul class="footer-links">
            <li><a href="<?php echo BASE_URL; ?>/index.php">Home Page</a></li>
            <li><a href="<?php echo BASE_URL; ?>/index.php#about">About Project</a></li>
            <li><a href="<?php echo BASE_URL; ?>/index.php#how-it-works">How It Works</a></li>
            <li><a href="<?php echo BASE_URL; ?>/index.php#sdgs">UN SDGs 3 & 10</a></li>
            <li><a href="<?php echo BASE_URL; ?>/index.php#awareness">Awareness & FAQs</a></li>
          </ul>
        </div>

        <!-- Portals -->
        <div class="footer-column">
          <h4>Portals & Actions</h4>
          <ul class="footer-links">
            <li><a href="<?php echo BASE_URL; ?>/donor_register.php">Donor Registration</a></li>
            <li><a href="<?php echo BASE_URL; ?>/recipient_register.php">Recipient Registration</a></li>
            <li><a href="<?php echo BASE_URL; ?>/login.php">Portal Sign In</a></li>
            <li><a href="<?php echo BASE_URL; ?>/admin/index.php">Admin Dashboard</a></li>
            <li><a href="<?php echo BASE_URL; ?>/admin/matches.php">Preliminary Matches</a></li>
          </ul>
        </div>

        <!-- Contact / Helpline -->
        <div class="footer-column" id="contact">
          <h4>Assistance & Helpline</h4>
          <ul class="footer-links">
            <li><strong>Helpline:</strong> 1800-11-4770 (Toll Free)</li>
            <li><strong>Support:</strong> <a href="mailto:admin@organportal.com">admin@organportal.com</a></li>
            <li><strong>Hours:</strong> 24x7 Emergency Transplant Desk</li>
            <li><strong>Address:</strong> Health Sciences Tech Hub, Medical District</li>
          </ul>
        </div>
      </div>

      <!-- Bottom Bar -->
      <div class="footer-bottom">
        <p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. Built for Academic Demonstration.</p>
        <p>Engineered with Next-Gen Immuno Matching Architecture &amp; LifePulse Clinical Design System</p>
      </div>
    </div>
  </footer>

  <!-- Core Scripts -->
  <script src="<?php echo BASE_URL; ?>/assets/js/main.js"></script>
  <script src="<?php echo BASE_URL; ?>/assets/js/validation.js"></script>
  <?php if (isset($include_dashboard_js) && $include_dashboard_js): ?>
  <script src="<?php echo BASE_URL; ?>/assets/js/dashboard.js"></script>
  <?php endif; ?>
</body>
</html>
