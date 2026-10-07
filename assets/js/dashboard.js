/**
 * Organ Donation – Recipient Matching Portal
 * Dynamic Admin Dashboard JavaScript
 * Features:
 *  - Live Toast Notification Engine (showToast)
 *  - Dynamic AJAX Admin Action Interceptor (In-place live updates without reload)
 *  - Real-time Table Live Search & Multi-criteria Filtering
 *  - Dynamic Badge & Data Attribute Mutation
 */

// Universal Floating Toast Notification Handler
window.showToast = function(type, message) {
  let container = document.querySelector('.flash-message-container');
  if (!container) {
    container = document.createElement('div');
    container.className = 'flash-message-container';
    container.setAttribute('role', 'alert');
    container.setAttribute('aria-live', 'assertive');
    document.body.appendChild(container);
  }

  const alert = document.createElement('div');
  alert.className = `flash-alert ${type}`;
  alert.innerHTML = `
    <span>${message}</span>
    <button type="button" class="flash-close" aria-label="Dismiss">&times;</button>
  `;

  container.appendChild(alert);

  const closeBtn = alert.querySelector('.flash-close');
  const timer = setTimeout(() => fadeOutToast(alert), 5000);

  if (closeBtn) {
    closeBtn.addEventListener('click', () => {
      clearTimeout(timer);
      fadeOutToast(alert);
    });
  }

  function fadeOutToast(el) {
    el.style.transition = 'opacity 0.35s ease, transform 0.35s ease';
    el.style.opacity = '0';
    el.style.transform = 'translateY(-10px)';
    setTimeout(() => el.remove(), 350);
  }
};

document.addEventListener('DOMContentLoaded', () => {
  // =========================================================
  // 1. DYNAMIC TABLE LIVE SEARCH & MULTI-FILTER ENGINE
  // =========================================================
  const searchInput  = document.getElementById('tableSearch');
  const bloodFilter  = document.getElementById('filterBlood');
  const organFilter  = document.getElementById('filterOrgan');
  const statusFilter = document.getElementById('filterStatus');
  const urgencyFilter = document.getElementById('filterUrgency');
  const clearBtn     = document.getElementById('clearFilters');

  function filterTable() {
    const tableRows = document.querySelectorAll('.data-table tbody tr[data-searchable="true"]');
    const searchTerm     = (searchInput ? searchInput.value.toLowerCase().trim() : '');
    const selectedBlood  = (bloodFilter ? bloodFilter.value : '');
    const selectedOrgan  = (organFilter ? organFilter.value : '');
    const selectedStatus = (statusFilter ? statusFilter.value : '');
    const selectedUrgency = (urgencyFilter ? urgencyFilter.value : '');

    let visibleCount = 0;

    tableRows.forEach((row) => {
      const rowText    = row.textContent.toLowerCase();
      const rowBlood   = row.getAttribute('data-blood') || '';
      const rowOrgan   = row.getAttribute('data-organ') || '';
      const rowStatus  = row.getAttribute('data-status') || '';
      const rowUrgency = row.getAttribute('data-urgency') || '';

      const matchesSearch  = !searchTerm || rowText.includes(searchTerm);
      const matchesBlood   = !selectedBlood || rowBlood === selectedBlood;
      const matchesOrgan   = !selectedOrgan || rowOrgan === selectedOrgan;
      const matchesStatus  = !selectedStatus || rowStatus === selectedStatus;
      const matchesUrgency = !selectedUrgency || rowUrgency === selectedUrgency;

      if (matchesSearch && matchesBlood && matchesOrgan && matchesStatus && matchesUrgency) {
        row.style.display = '';
        visibleCount++;
      } else {
        row.style.display = 'none';
      }
    });

    const emptyRow = document.getElementById('noResultsRow');
    if (emptyRow) {
      emptyRow.style.display = (visibleCount === 0) ? '' : 'none';
    }
  }

  if (searchInput)  searchInput.addEventListener('input', filterTable);
  if (bloodFilter)  bloodFilter.addEventListener('change', filterTable);
  if (organFilter)  organFilter.addEventListener('change', filterTable);
  if (statusFilter) statusFilter.addEventListener('change', filterTable);
  if (urgencyFilter) urgencyFilter.addEventListener('change', filterTable);

  if (clearBtn) {
    clearBtn.addEventListener('click', (e) => {
      e.preventDefault();
      if (searchInput)  searchInput.value = '';
      if (bloodFilter)  bloodFilter.value = '';
      if (organFilter)  organFilter.value = '';
      if (statusFilter) statusFilter.value = '';
      if (urgencyFilter) urgencyFilter.value = '';
      filterTable();
    });
  }

  // =========================================================
  // 2. DYNAMIC AJAX HANDLER FOR ALL ADMIN ACTIONS & MODALS
  // =========================================================
  document.addEventListener('submit', async (e) => {
    const form = e.target;
    if (!form || !form.action || !form.action.includes('update_status.php')) {
      return;
    }

    const actionInput = form.querySelector('input[name="action"]');
    const action = actionInput ? actionInput.value : '';

    // File downloads (CSV & DB SQL) require native browser navigation
    if (action === 'export_csv' || action === 'download_db_backup') {
      return;
    }

    e.preventDefault();

    // Identify submit button and enter loading state
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalBtnText = submitBtn ? submitBtn.innerHTML : '';
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.innerHTML = '⏳ Processing...';
    }

    try {
      const formData = new FormData(form);
      formData.append('ajax', '1');

      const response = await fetch(form.action, {
        method: 'POST',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json'
        },
        body: formData
      });

      const result = await response.json().catch(() => null);

      if (response.ok && result && result.success) {
        // Show success notification
        window.showToast('success', '✓ ' + (result.message || 'Action executed successfully!'));

        // Close enclosing modal if any
        const modalBackdrop = form.closest('.modal-backdrop');
        if (modalBackdrop && typeof closeModal === 'function') {
          closeModal(modalBackdrop.id);
        }

        // Apply Dynamic Real-time DOM Mutations
        handleDynamicDomUpdate(action, formData, form);

      } else {
        const errorMsg = (result && result.message) ? result.message : 'Server returned an error. Please try again.';
        window.showToast('error', '✕ ' + errorMsg);
      }
    } catch (err) {
      console.error('AJAX admin request error:', err);
      window.showToast('error', '✕ Network connection error. Please try again.');
    } finally {
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalBtnText;
      }
    }
  });

  /**
   * Dispatches live DOM mutations based on administrative action
   */
  function handleDynamicDomUpdate(action, formData, form) {
    // -------------------------------------------------------------
    // A. Donor Verification Status Update
    // -------------------------------------------------------------
    if (action === 'update_donor_status') {
      const donorId = formData.get('donor_id');
      const newStatus = formData.get('new_status');
      const notes = formData.get('admin_notes') || '';

      // Check on admin/donors.php
      const donorRow = document.getElementById('donor-row-' + donorId);
      if (donorRow) {
        donorRow.setAttribute('data-status', newStatus);
        const statusBadge = donorRow.querySelector('.badge-pending, .badge-verified, .badge-rejected');
        if (statusBadge) {
          statusBadge.className = `badge badge-${newStatus.toLowerCase()}`;
          statusBadge.textContent = newStatus;
        }
        // Update Status button onclick
        const statusBtn = donorRow.querySelector('button[onclick*="openActionModal"]');
        if (statusBtn) {
          const donorName = donorRow.querySelector('.user-cell-name')?.textContent.trim() || '';
          statusBtn.setAttribute('onclick', `openActionModal('donor', ${donorId}, '${escapeJs(donorName)}', '${newStatus}')`);
        }
      }

      // Check on admin/index.php pending queue
      const queueRow = document.getElementById('queue-donor-row-' + donorId);
      if (queueRow && newStatus !== 'Pending') {
        removeQueueRow(queueRow);
      }
    }

    // -------------------------------------------------------------
    // B. Recipient Verification Status Update
    // -------------------------------------------------------------
    else if (action === 'update_recipient_status') {
      const recipId = formData.get('recipient_id');
      const newStatus = formData.get('new_status');

      // Check on admin/recipients.php
      const recipRow = document.getElementById('recipient-row-' + recipId);
      if (recipRow) {
        recipRow.setAttribute('data-status', newStatus);
        const statusBadge = recipRow.querySelector('.badge-pending, .badge-verified, .badge-rejected');
        if (statusBadge) {
          statusBadge.className = `badge badge-${newStatus.toLowerCase()}`;
          statusBadge.textContent = newStatus;
        }
        // Update Status button onclick
        const statusBtn = recipRow.querySelector('button[onclick*="openActionModal"]');
        if (statusBtn) {
          const recipName = recipRow.querySelector('.user-cell-name')?.textContent.trim() || '';
          statusBtn.setAttribute('onclick', `openActionModal('recipient', ${recipId}, '${escapeJs(recipName)}', '${newStatus}')`);
        }
      }

      // Check on admin/index.php pending queue
      const queueRow = document.getElementById('queue-recipient-row-' + recipId);
      if (queueRow && newStatus !== 'Pending') {
        removeQueueRow(queueRow);
      }
    }

    // -------------------------------------------------------------
    // C. 1-Click Quick Verify / Quick Reject (Overview Queue)
    // -------------------------------------------------------------
    else if (action === 'quick_verify' || action === 'quick_reject') {
      const type = formData.get('type');
      const id = formData.get('id');
      const queueRow = document.getElementById(`queue-${type}-row-${id}`) || form.closest('tr');
      if (queueRow) {
        removeQueueRow(queueRow);
      }
    }

    // -------------------------------------------------------------
    // D. Edit Donor Profile
    // -------------------------------------------------------------
    else if (action === 'edit_donor') {
      const id = formData.get('donor_id');
      const fullName = formData.get('full_name');
      const age = formData.get('age');
      const gender = formData.get('gender');
      const email = formData.get('email');
      const mobile = formData.get('mobile');
      const city = formData.get('address_city');
      const blood = formData.get('blood_group');
      const organ = formData.get('organ_donated');
      const availability = formData.get('availability_status');
      const verification = formData.get('verification_status');
      const medNotes = formData.get('medical_notes') || '';
      const adminNotes = formData.get('admin_notes') || '';

      const row = document.getElementById('donor-row-' + id);
      if (row) {
        // Update search & filter attributes
        row.setAttribute('data-blood', blood);
        row.setAttribute('data-organ', organ);
        row.setAttribute('data-status', verification);

        // Update Name & Contact
        const nameEl = row.querySelector('.user-cell-name');
        if (nameEl) nameEl.textContent = fullName;
        const metaEl = row.querySelector('.user-cell-meta');
        if (metaEl) metaEl.textContent = `${city} • ${mobile}`;

        // Update Age/Gender
        const cells = row.querySelectorAll('td');
        if (cells[3]) cells[3].textContent = `${age}y / ${gender}`;

        // Update Blood Group
        const bloodBadge = cells[4]?.querySelector('.badge');
        if (bloodBadge) bloodBadge.textContent = blood;

        // Update Organ
        const organEl = cells[5]?.querySelector('strong');
        if (organEl) organEl.textContent = organ;

        // Update Availability Badge
        if (cells[6]) {
          if (availability === 'Available') {
            cells[6].innerHTML = '<span class="badge badge-verified">Available</span>';
          } else if (availability === 'Donated') {
            cells[6].innerHTML = '<span class="badge badge-low">Donated</span>';
          } else {
            cells[6].innerHTML = '<span class="badge badge-pending">Unavailable</span>';
          }
        }

        // Update Verification Badge
        if (cells[7]) {
          cells[7].innerHTML = `<span class="badge badge-${verification.toLowerCase()}">${verification}</span>`;
        }

        // Prepare updated donor object for modal triggers
        const updatedDonor = {
          id: parseInt(id),
          full_name: fullName,
          age: parseInt(age),
          gender: gender,
          email: email,
          mobile: mobile,
          address_city: city,
          blood_group: blood,
          organ_donated: organ,
          availability_status: availability,
          verification_status: verification,
          medical_notes: medNotes,
          admin_notes: adminNotes,
          consent_given: 1,
          created_at: cells[8]?.textContent.trim() || 'Just now'
        };

        const jsonStr = escapeJsonAttr(JSON.stringify(updatedDonor));

        // Update Edit button
        const editBtn = row.querySelector('button[onclick*="openEditDonorModal"]');
        if (editBtn) editBtn.setAttribute('onclick', `openEditDonorModal(${jsonStr})`);

        // Update View Dossier button
        const viewBtn = row.querySelector('button[onclick*="viewDonorDetails"]');
        if (viewBtn) viewBtn.setAttribute('onclick', `viewDonorDetails(${jsonStr})`);

        // Update Status button
        const statusBtn = row.querySelector('button[onclick*="openActionModal"]');
        if (statusBtn) statusBtn.setAttribute('onclick', `openActionModal('donor', ${id}, '${escapeJs(fullName)}', '${verification}')`);

        // Update Delete button
        const delBtn = row.querySelector('button[onclick*="confirmDeleteDonor"]');
        if (delBtn) delBtn.setAttribute('onclick', `confirmDeleteDonor(${id}, '${escapeJs(fullName)}')`);
      }
    }

    // -------------------------------------------------------------
    // E. Edit Recipient Profile
    // -------------------------------------------------------------
    else if (action === 'edit_recipient') {
      const id = formData.get('recipient_id');
      const fullName = formData.get('full_name');
      const age = formData.get('age');
      const gender = formData.get('gender');
      const email = formData.get('email');
      const mobile = formData.get('mobile');
      const hospitalCity = formData.get('hospital_city');
      const blood = formData.get('blood_group');
      const organ = formData.get('organ_needed');
      const urgency = formData.get('urgency_level');
      const verification = formData.get('verification_status');
      const medNotes = formData.get('medical_notes') || '';
      const adminNotes = formData.get('admin_notes') || '';

      const row = document.getElementById('recipient-row-' + id);
      if (row) {
        // Update attributes
        row.setAttribute('data-blood', blood);
        row.setAttribute('data-organ', organ);
        row.setAttribute('data-urgency', urgency);
        row.setAttribute('data-status', verification);

        // Update Name & Contact
        const nameEl = row.querySelector('.user-cell-name');
        if (nameEl) nameEl.textContent = fullName;
        const metaEl = row.querySelector('.user-cell-meta');
        if (metaEl) metaEl.textContent = `${hospitalCity} • ${mobile}`;

        // Update Age/Gender
        const cells = row.querySelectorAll('td');
        if (cells[3]) cells[3].textContent = `${age}y / ${gender}`;

        // Update Blood Group
        const bloodBadge = cells[4]?.querySelector('.badge');
        if (bloodBadge) bloodBadge.textContent = blood;

        // Update Organ
        const organEl = cells[5]?.querySelector('strong');
        if (organEl) organEl.textContent = organ;

        // Update Urgency Badge
        if (cells[6]) {
          const urgClass = (urgency === 'Critical') ? 'badge-critical' : ((urgency === 'High') ? 'badge-high' : ((urgency === 'Medium') ? 'badge-medium' : 'badge-low'));
          cells[6].innerHTML = `<span class="badge ${urgClass}" style="font-weight: 600;">${urgency}</span>`;
        }

        // Update Verification Badge
        if (cells[7]) {
          cells[7].innerHTML = `<span class="badge badge-${verification.toLowerCase()}">${verification}</span>`;
        }

        // Prepare updated recipient object
        const updatedRecip = {
          id: parseInt(id),
          full_name: fullName,
          age: parseInt(age),
          gender: gender,
          email: email,
          mobile: mobile,
          hospital_city: hospitalCity,
          blood_group: blood,
          organ_needed: organ,
          urgency_level: urgency,
          verification_status: verification,
          medical_notes: medNotes,
          admin_notes: adminNotes,
          consent_given: 1,
          created_at: cells[8]?.textContent.trim() || 'Just now'
        };

        const jsonStr = escapeJsonAttr(JSON.stringify(updatedRecip));

        const editBtn = row.querySelector('button[onclick*="openEditRecipientModal"]');
        if (editBtn) editBtn.setAttribute('onclick', `openEditRecipientModal(${jsonStr})`);

        const viewBtn = row.querySelector('button[onclick*="viewRecipientDetails"]');
        if (viewBtn) viewBtn.setAttribute('onclick', `viewRecipientDetails(${jsonStr})`);

        const statusBtn = row.querySelector('button[onclick*="openActionModal"]');
        if (statusBtn) statusBtn.setAttribute('onclick', `openActionModal('recipient', ${id}, '${escapeJs(fullName)}', '${verification}')`);

        const delBtn = row.querySelector('button[onclick*="confirmDeleteRecipient"]');
        if (delBtn) delBtn.setAttribute('onclick', `confirmDeleteRecipient(${id}, '${escapeJs(fullName)}')`);
      }
    }

    // -------------------------------------------------------------
    // F. Match Clinical Status Update
    // -------------------------------------------------------------
    else if (action === 'update_match_status') {
      const matchId = formData.get('match_id');
      const newStatus = formData.get('new_status');
      const notes = formData.get('notes') || '';
      const autoAllocate = formData.get('auto_allocate') === '1';

      const matchRow = document.getElementById('match-row-' + matchId);
      if (matchRow) {
        matchRow.setAttribute('data-status', newStatus);

        const badgeClassMap = {
          'Potential': 'badge-potential',
          'Under Review': 'badge-pending',
          'Contacted': 'badge-contacted',
          'Approved': 'badge-verified',
          'Closed': 'badge-closed'
        };

        const badgeClass = badgeClassMap[newStatus] || 'badge-potential';
        const cells = matchRow.querySelectorAll('td');
        if (cells[4]) {
          cells[4].innerHTML = `<span class="badge ${badgeClass}" style="font-size: 0.8rem;">${newStatus}</span>`;
        }

        const updateBtn = matchRow.querySelector('button[onclick*="openMatchStatusModal"]');
        if (updateBtn) {
          const donorName = matchRow.querySelector('.user-cell-meta')?.textContent.trim() || '';
          const recipientName = matchRow.querySelector('.user-cell-name')?.textContent.trim() || '';
          const organ = cells[1]?.textContent.trim() || '';
          updateBtn.setAttribute('onclick', `openMatchStatusModal(${matchId}, '${escapeJs(donorName)}', '${escapeJs(recipientName)}', '${escapeJs(organ)}', '${newStatus}', '${escapeJs(notes)}')`);
        }

        if (autoAllocate && newStatus === 'Approved') {
          window.showToast('info', 'ℹ️ Organ allocated: Donor availability marked as Donated.');
        }
      }
    }

    // -------------------------------------------------------------
    // G. Record Deletions (Donor, Recipient, Match)
    // -------------------------------------------------------------
    else if (action === 'delete_donor') {
      const id = formData.get('donor_id');
      const row = document.getElementById('donor-row-' + id);
      if (row) {
        removeTableRow(row);
      }
    } else if (action === 'delete_recipient') {
      const id = formData.get('recipient_id');
      const row = document.getElementById('recipient-row-' + id);
      if (row) {
        removeTableRow(row);
      }
    } else if (action === 'delete_match') {
      const id = formData.get('match_id');
      const row = document.getElementById('match-row-' + id);
      if (row) {
        removeTableRow(row);
      }
    } else if (action === 'delete_user') {
      const id = formData.get('user_id');
      const row = document.getElementById('user-row-' + id);
      if (row) {
        removeTableRow(row);
      }
    }

    // -------------------------------------------------------------
    // H. Direct Entity Creation & Maintenance Reloads
    // -------------------------------------------------------------
    else if (action === 'create_donor' || action === 'create_recipient' || action === 'create_admin_user' || action === 'create_manual_match' || action === 'bulk_donor_action' || action === 'bulk_recipient_action' || action === 'recalculate_matches' || action === 'reset_demo_data' || action === 'clear_logs') {
      setTimeout(() => {
        window.location.reload();
      }, 750);
    }
  }

  // Smooth removal helper for pending queue rows
  function removeQueueRow(row) {
    row.style.transition = 'opacity 0.35s ease, transform 0.35s ease';
    row.style.opacity = '0';
    row.style.transform = 'scale(0.96)';
    setTimeout(() => {
      row.remove();
      const queueBadge = document.querySelector('.card-header .badge-pending');
      if (queueBadge) {
        const count = Math.max(0, (parseInt(queueBadge.textContent) || 1) - 1);
        queueBadge.textContent = count + ' In Queue';
      }
      const tbody = document.querySelector('.data-table tbody');
      if (tbody && tbody.querySelectorAll('tr').length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 2rem;">✅ All submitted profiles are currently verified. No pending items in queue.</td></tr>';
      }
    }, 350);
  }

  // Smooth removal helper for table rows
  function removeTableRow(row) {
    row.style.transition = 'opacity 0.35s ease, transform 0.35s ease';
    row.style.opacity = '0';
    row.style.transform = 'scale(0.96)';
    setTimeout(() => {
      row.remove();
      filterTable();
    }, 350);
  }

  function escapeJs(str) {
    if (!str) return '';
    return String(str).replace(/\\/g, '\\\\').replace(/'/g, "\\'");
  }

  function escapeJsonAttr(jsonStr) {
    return jsonStr.replace(/"/g, '&quot;');
  }

  // =========================================================
  // 3. SAFE FALLBACK MODAL HANDLERS (If not defined on page)
  // =========================================================
  if (!window.openActionModal) {
    window.openActionModal = function(type, id, name, currentStatus) {
      const modal = document.getElementById('statusActionModal');
      if (!modal) return;

      const donorInput = document.getElementById('modalDonorId');
      if (donorInput && type === 'donor') donorInput.value = id;

      const recipInput = document.getElementById('modalRecipientId');
      if (recipInput && type === 'recipient') recipInput.value = id;

      const targetName = document.getElementById('actionTargetName');
      if (targetName) targetName.textContent = name;
      
      const select = document.getElementById('actionNewStatus');
      if (select && currentStatus) {
        select.value = currentStatus;
      }

      if (typeof openModal === 'function') {
        openModal('statusActionModal');
      }
    };
  }

  if (!window.openMatchStatusModal) {
    window.openMatchStatusModal = function(matchId, donorName, recipientName, organ, currentStatus, notes) {
      const modal = document.getElementById('matchStatusModal');
      if (!modal) return;

      const idField = document.getElementById('modalMatchId');
      if (idField) idField.value = matchId;

      const donorSpan = document.getElementById('modalMatchDonor');
      if (donorSpan) donorSpan.textContent = donorName;

      const recipSpan = document.getElementById('modalMatchRecipient');
      if (recipSpan) recipSpan.textContent = recipientName;

      const organSpan = document.getElementById('modalMatchOrgan');
      if (organSpan) organSpan.textContent = organ;
      
      const select = document.getElementById('matchNewStatus');
      if (select && currentStatus) {
        select.value = currentStatus;
      }

      const notesField = document.getElementById('matchNotes');
      if (notesField && notes) {
        notesField.value = notes;
      }

      if (typeof openModal === 'function') {
        openModal('matchStatusModal');
      }
    };
  }
});
