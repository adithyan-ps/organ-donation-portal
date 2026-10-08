/**
 * Organ Donation – Recipient Matching Portal
 * Main JavaScript: Navigation, Accordions, Notifications & Modals
 */

document.addEventListener('DOMContentLoaded', () => {
  // 1. Mobile Navigation Toggle
  const navToggle = document.getElementById('navToggleBtn') || document.querySelector('.nav-toggle');
  const navMenuWrapper = document.getElementById('navMenuWrapper') || document.querySelector('.nav-menu');

  if (navToggle && navMenuWrapper) {
    navToggle.addEventListener('click', (e) => {
      e.stopPropagation();
      const isExpanded = navToggle.getAttribute('aria-expanded') === 'true';
      navToggle.setAttribute('aria-expanded', !isExpanded);
      navMenuWrapper.classList.toggle('show');
    });

    // Close menu when clicking outside
    document.addEventListener('click', (e) => {
      if (!navToggle.contains(e.target) && !navMenuWrapper.contains(e.target) && navMenuWrapper.classList.contains('show')) {
        navMenuWrapper.classList.remove('show');
        navToggle.setAttribute('aria-expanded', 'false');
      }
    });

    // Auto-close menu when tapping any navigation link
    const mobileLinks = navMenuWrapper.querySelectorAll('.nav-link, .nav-mobile-actions a');
    mobileLinks.forEach((link) => {
      link.addEventListener('click', () => {
        navMenuWrapper.classList.remove('show');
        navToggle.setAttribute('aria-expanded', 'false');
      });
    });

    // Close menu on Escape key
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && navMenuWrapper.classList.contains('show')) {
        navMenuWrapper.classList.remove('show');
        navToggle.setAttribute('aria-expanded', 'false');
      }
    });
  }

  // 1b. Admin Sidebar Mobile Toggle
  const adminSidebarToggle = document.getElementById('adminSidebarToggle');
  const adminSidebarMenu = document.getElementById('adminSidebarMenu');

  if (adminSidebarToggle && adminSidebarMenu) {
    adminSidebarToggle.addEventListener('click', (e) => {
      e.stopPropagation();
      const isExpanded = adminSidebarToggle.getAttribute('aria-expanded') === 'true';
      adminSidebarToggle.setAttribute('aria-expanded', !isExpanded);
      adminSidebarMenu.classList.toggle('show');
    });
  }

  // 2. Auto-dismiss Flash Alerts
  const flashAlerts = document.querySelectorAll('.flash-alert');
  flashAlerts.forEach((alert) => {
    // Auto remove after 6 seconds
    const timer = setTimeout(() => {
      fadeOutAndRemove(alert);
    }, 6000);

    const closeBtn = alert.querySelector('.flash-close');
    if (closeBtn) {
      closeBtn.addEventListener('click', () => {
        clearTimeout(timer);
        fadeOutAndRemove(alert);
      });
    }
  });

  function fadeOutAndRemove(element) {
    element.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
    element.style.opacity = '0';
    element.style.transform = 'translateX(20px)';
    setTimeout(() => {
      element.remove();
    }, 300);
  }

  // 3. FAQ Accordion
  const faqQuestions = document.querySelectorAll('.faq-question');
  faqQuestions.forEach((button) => {
    button.addEventListener('click', () => {
      const item = button.closest('.faq-item');
      const isActive = item.classList.contains('active');

      // Close all items first
      document.querySelectorAll('.faq-item').forEach((i) => {
        i.classList.remove('active');
        const btn = i.querySelector('.faq-question');
        if (btn) btn.setAttribute('aria-expanded', 'false');
      });

      // Toggle clicked item
      if (!isActive) {
        item.classList.add('active');
        button.setAttribute('aria-expanded', 'true');
      }
    });
  });

  // 4. Modal Handlers
  window.openModal = function(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
      modal.classList.add('show');
      modal.setAttribute('aria-hidden', 'false');
      document.body.style.overflow = 'hidden';
      // Focus first interactive element
      const firstFocusable = modal.querySelector('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
      if (firstFocusable) firstFocusable.focus();
    }
  };

  window.closeModal = function(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
      modal.classList.remove('show');
      modal.setAttribute('aria-hidden', 'true');
      document.body.style.overflow = '';
    }
  };

  // Close modals on clicking backdrop or pressing Escape
  document.querySelectorAll('.modal-backdrop').forEach((modal) => {
    modal.addEventListener('click', (e) => {
      if (e.target === modal) {
        window.closeModal(modal.id);
      }
    });
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      const openModals = document.querySelectorAll('.modal-backdrop.show');
      openModals.forEach((m) => window.closeModal(m.id));
    }
  });

  // 5. Interactive Form Chip Selectors (Blood Group & Organs)
  document.querySelectorAll('.chip-selector-grid').forEach((grid) => {
    const targetInputId = grid.getAttribute('data-target-input');
    const targetInput = document.getElementById(targetInputId);
    if (!targetInput) return;

    grid.querySelectorAll('.selector-chip').forEach((chip) => {
      chip.addEventListener('click', () => {
        grid.querySelectorAll('.selector-chip').forEach((c) => c.classList.remove('active'));
        chip.classList.add('active');
        const val = chip.getAttribute('data-value');
        targetInput.value = val;
        // Trigger change event if needed
        targetInput.dispatchEvent(new Event('change', { bubbles: true }));
      });
    });
  });
});

// --------------------------------------------------------------------------
// Quick Blood Compatibility Checker Widget (Hero Section)
// --------------------------------------------------------------------------
const bloodMatrix = {
  'O-':  { canGive: 'All blood groups (Universal Donor)', pct: '100% Match', badge: 'Universal Donor' },
  'O+':  { canGive: 'O+, A+, B+, AB+', pct: '85% Match', badge: 'Broad Compatibility' },
  'A-':  { canGive: 'A-, A+, AB-, AB+', pct: '50% Match', badge: '50% Population' },
  'A+':  { canGive: 'A+, AB+', pct: '40% Match', badge: 'Common Group' },
  'B-':  { canGive: 'B-, B+, AB-, AB+', pct: '50% Match', badge: '50% Population' },
  'B+':  { canGive: 'B+, AB+', pct: '40% Match', badge: 'Common Group' },
  'AB-': { canGive: 'AB-, AB+', pct: '20% Match', badge: 'Rare Group' },
  'AB+': { canGive: 'AB+ only (Universal Recipient)', pct: '15% Match', badge: 'Universal Recipient' }
};

window.checkHeroBlood = function(blood) {
  document.querySelectorAll('.hero-blood-chips .blood-chip').forEach((btn) => {
    btn.classList.toggle('active', btn.textContent.trim() === blood);
  });

  const res = bloodMatrix[blood];
  const container = document.getElementById('heroBloodResult');
  if (container && res) {
    container.innerHTML = `
      <div><strong>${blood}</strong>: Can donate to <strong>${res.canGive}</strong></div>
      <span class="badge badge-verified" style="font-size: 0.72rem;">${res.pct}</span>
    `;
  }
};

// --------------------------------------------------------------------------
// Interactive Organ Match Simulator (Homepage Lab Feature)
// --------------------------------------------------------------------------
const simState = {
  organ: 'Kidney',
  donorBlood: 'O-',
  recipBlood: 'A+',
  urgency: 'Critical'
};

const fullMatrix = {
  'O-':  ['O-', 'O+', 'A-', 'A+', 'B-', 'B+', 'AB-', 'AB+'],
  'O+':  ['O+', 'A+', 'B+', 'AB+'],
  'A-':  ['A-', 'A+', 'AB-', 'AB+'],
  'A+':  ['A+', 'AB+'],
  'B-':  ['B-', 'B+', 'AB-', 'AB+'],
  'B+':  ['B+', 'AB+'],
  'AB-': ['AB-', 'AB+'],
  'AB+': ['AB+']
};

function recalculateSimulator() {
  const isCompatible = fullMatrix[simState.donorBlood] && fullMatrix[simState.donorBlood].includes(simState.recipBlood);
  const isIdenticalBlood = simState.donorBlood === simState.recipBlood;

  let organPts = 40;
  let bloodPts = 0;
  let bloodLabel = 'Incompatible';

  if (isIdenticalBlood) {
    bloodPts = 35;
    bloodLabel = 'Identical Match';
  } else if (isCompatible) {
    bloodPts = 30;
    bloodLabel = 'Compatible Alt';
  }

  let urgencyPts = 10;
  if (simState.urgency === 'Critical') urgencyPts = 20;
  else if (simState.urgency === 'High') urgencyPts = 15;
  else if (simState.urgency === 'Medium') urgencyPts = 10;
  else if (simState.urgency === 'Low') urgencyPts = 5;

  let proximityPts = 5;

  let totalScore = 0;
  if (isCompatible) {
    totalScore = organPts + bloodPts + urgencyPts + proximityPts;
  }

  // Update DOM elements
  const scoreVal = document.getElementById('simScoreValue');
  const verdictEl = document.getElementById('simScoreVerdict');
  const badgeEl = document.getElementById('simStatusBadge');
  const ptsOrgan = document.getElementById('simPtsOrgan');
  const ptsBlood = document.getElementById('simPtsBlood');
  const ptsUrgency = document.getElementById('simPtsUrgency');
  const ptsProximity = document.getElementById('simPtsProximity');
  const bloodTypeLabel = document.getElementById('simBloodTypeLabel');
  const notesEl = document.getElementById('simClinicalNotes');

  const barOrgan = document.getElementById('simBarOrgan');
  const barBlood = document.getElementById('simBarBlood');
  const barUrgency = document.getElementById('simBarUrgency');
  const barProximity = document.getElementById('simBarProximity');

  if (scoreVal) scoreVal.textContent = isCompatible ? totalScore + '%' : '0%';
  
  if (verdictEl) {
    if (!isCompatible) {
      verdictEl.textContent = 'Biological Incompatibility';
      verdictEl.style.color = '#ef4444';
    } else if (totalScore >= 90) {
      verdictEl.textContent = 'High Clinical Match';
      verdictEl.style.color = '#34d399';
    } else {
      verdictEl.textContent = 'Moderate Compatibility';
      verdictEl.style.color = '#38bdf8';
    }
  }

  if (badgeEl) {
    if (!isCompatible) {
      badgeEl.className = 'badge badge-rejected';
      badgeEl.textContent = 'Incompatible Blood';
    } else if (totalScore >= 90) {
      badgeEl.className = 'badge badge-approved';
      badgeEl.textContent = 'Immediate Cross-Match Viable';
    } else {
      badgeEl.className = 'badge badge-review';
      badgeEl.textContent = 'Conditional Match';
    }
  }

  if (ptsOrgan) ptsOrgan.textContent = organPts + ' / 40 pts';
  if (barOrgan) barOrgan.style.width = '100%';

  if (ptsBlood) ptsBlood.textContent = bloodPts + ' / 35 pts';
  if (barBlood) barBlood.style.width = Math.round((bloodPts / 35) * 100) + '%';
  if (bloodTypeLabel) bloodTypeLabel.textContent = bloodLabel;

  if (ptsUrgency) ptsUrgency.textContent = urgencyPts + ' / 20 pts';
  if (barUrgency) barUrgency.style.width = Math.round((urgencyPts / 20) * 100) + '%';

  if (ptsProximity) ptsProximity.textContent = proximityPts + ' / 5 pts';
  if (barProximity) barProximity.style.width = '100%';

  if (notesEl) {
    if (!isCompatible) {
      notesEl.innerHTML = `⚠️ <strong>Incompatibility Flag:</strong> Donor blood type <strong>${simState.donorBlood}</strong> cannot be infused into recipient <strong>${simState.recipBlood}</strong> without severe hyperacute antibody rejection. Alternate compatible donors required.`;
    } else {
      notesEl.innerHTML = `💡 <strong>Clinical Assessment:</strong> Donor <strong>${simState.donorBlood}</strong> (${bloodLabel}) is compatible with recipient <strong>${simState.recipBlood}</strong>. Clinical priority set to <strong>${simState.urgency}</strong>. Pre-operative cross-match screening is indicated.`;
    }
  }
}

window.setSimOrgan = function(organ) {
  simState.organ = organ;
  document.querySelectorAll('.sim-organ-btn').forEach((btn) => {
    btn.classList.toggle('active', btn.textContent.includes(organ));
  });
  recalculateSimulator();
};

window.setSimDonorBlood = function(blood) {
  simState.donorBlood = blood;
  document.querySelectorAll('#simDonorBlood .sim-blood-btn').forEach((btn) => {
    btn.classList.toggle('active', btn.textContent.trim() === blood);
  });
  recalculateSimulator();
};

window.setSimRecipBlood = function(blood) {
  simState.recipBlood = blood;
  document.querySelectorAll('#simRecipBlood .sim-blood-btn').forEach((btn) => {
    btn.classList.toggle('active', btn.textContent.trim() === blood);
  });
  recalculateSimulator();
};

window.setSimUrgency = function(urgency) {
  simState.urgency = urgency;
  document.querySelectorAll('#simUrgency .sim-urgency-btn').forEach((btn) => {
    btn.classList.toggle('active', btn.textContent.includes(urgency));
  });
  recalculateSimulator();
};
