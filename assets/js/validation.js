/**
 * Organ Donation – Recipient Matching Portal
 * Client-side Form Validation
 */

document.addEventListener('DOMContentLoaded', () => {
  const forms = document.querySelectorAll('form[data-validate="true"]');

  forms.forEach((form) => {
    // Real-time field validation on blur or input
    const inputs = form.querySelectorAll('input, select, textarea');
    inputs.forEach((input) => {
      input.addEventListener('blur', () => validateField(input));
      input.addEventListener('input', () => {
        if (input.classList.contains('is-invalid')) {
          validateField(input);
        }
      });
    });

    // Form submit validation
    form.addEventListener('submit', (e) => {
      let formValid = true;
      inputs.forEach((input) => {
        const isFieldValid = validateField(input);
        if (!isFieldValid) {
          formValid = false;
        }
      });

      if (!formValid) {
        e.preventDefault();
        // Focus first invalid element
        const firstInvalid = form.querySelector('.is-invalid');
        if (firstInvalid) {
          firstInvalid.focus();
          firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
      }
    });
  });

  function validateField(field) {
    const value = field.value.trim();
    const type = field.type;
    const required = field.hasAttribute('required');
    let isValid = true;
    let errorMessage = '';

    // Clear previous feedback
    clearFeedback(field);

    // Required check
    if (required) {
      if (type === 'checkbox') {
        if (!field.checked) {
          isValid = false;
          errorMessage = 'You must give your consent to continue.';
        }
      } else if (!value) {
        isValid = false;
        errorMessage = 'This field is required.';
      }
    }

    // Specific field validations
    if (isValid && value) {
      // Email format
      if (type === 'email' || field.name === 'email') {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(value)) {
          isValid = false;
          errorMessage = 'Please provide a valid email address.';
        }
      }

      // Mobile phone number (10 to 15 digits)
      if (field.name === 'mobile') {
        const digitsOnly = value.replace(/[^0-9]/g, '');
        if (digitsOnly.length < 10 || digitsOnly.length > 15) {
          isValid = false;
          errorMessage = 'Please provide a valid mobile number (at least 10 digits).';
        }
      }

      // Age validation
      if (field.name === 'age') {
        const ageNum = parseInt(value, 10);
        const minAge = parseInt(field.getAttribute('min') || '18', 10);
        const maxAge = parseInt(field.getAttribute('max') || '100', 10);

        if (isNaN(ageNum) || ageNum < minAge || ageNum > maxAge) {
          isValid = false;
          errorMessage = `Age must be between ${minAge} and ${maxAge} years.`;
        }
      }

      // Password length check
      if (field.name === 'password' && value.length < 6) {
        isValid = false;
        errorMessage = 'Password must contain at least 6 characters.';
      }

      // Password confirmation check
      if (field.name === 'confirm_password') {
        const pwd = field.form.querySelector('input[name="password"]');
        if (pwd && pwd.value !== value) {
          isValid = false;
          errorMessage = 'Passwords do not match.';
        }
      }
    }

    if (!isValid) {
      field.classList.add('is-invalid');
      showFeedback(field, errorMessage);
    } else {
      field.classList.remove('is-invalid');
    }

    return isValid;
  }

  function showFeedback(field, message) {
    let parent = field.closest('.form-group');
    if (!parent) parent = field.parentElement;

    let feedback = parent.querySelector('.invalid-feedback');
    if (!feedback) {
      feedback = document.createElement('div');
      feedback.className = 'invalid-feedback';
      parent.appendChild(feedback);
    }
    feedback.textContent = message;
    feedback.style.display = 'block';
  }

  function clearFeedback(field) {
    let parent = field.closest('.form-group');
    if (!parent) parent = field.parentElement;

    const feedback = parent.querySelector('.invalid-feedback');
    if (feedback) {
      feedback.style.display = 'none';
      feedback.textContent = '';
    }
  }
});
