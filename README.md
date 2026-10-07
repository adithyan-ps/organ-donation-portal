# Organ Donation – Recipient Matching Portal (LifeBridge)

> **Academic Demonstration & Microproject**  
> An ethical, explainable preliminary donor-recipient matching portal inspired by Google & Material Design principles, built with **HTML5, CSS3, Vanilla JavaScript, PHP, and MySQL**.

---

## ⚠️ Academic & Medical Disclaimer

> **IMPORTANT NOTICE**:  
> Potential matches shown by this portal are **preliminary digital comparisons only** based on ABO blood group compatibility, required organ, donor availability, and clinical urgency.  
> **Final immunological compatibility testing** (human leukocyte antigen [HLA] tissue typing, cross-matching, panel reactive antibody [PRA] screening, viral markers), legal authorization, organ procurement allocation rules, and surgical decisions **must be performed by certified transplant centers, licensed medical professionals, and authorized government agencies (e.g., NOTTO, UNOS, NHSBT)**.

---

## Key Features

1. **Modern Google / Material Design 3 Interface**:
   - Clean, trustworthy healthcare aesthetic with medical blue (`#1a73e8`), teal (`#00897b`), and vital green accents.
   - Fully responsive on desktop, tablet, and mobile devices.
   - High contrast, accessible forms, keyboard navigation, and semantic HTML5.

2. **Public Awareness & UN SDG Alignment**:
   - **Hero Section**: Dynamic live counter cards (registered donors, recipients, verified matches, lives guided).
   - **How It Works**: 3-step visual workflow (Register &rarr; Verify &rarr; Match).
   - **UN SDG Highlights**: Dedicated sections for **SDG 3 (Good Health and Well-Being)** and **SDG 10 (Reduced Inequalities)**.
   - **Awareness & Education**: Organ survival windows, myths vs. facts cards, and accordion FAQs.

3. **Donor Registration**:
   - Comprehensive donor form: Full Name, Age (18+), Gender, Email, Mobile, Address/City, Blood Group, Organ Willing to Donate, Availability Status, Medical Notes/Health Declaration, Password, and Voluntary Consent Checkbox.
   - Dual-tier validation (instant client-side hints via Vanilla JS + server-side sanitization).
   - Friendly notification indicating that registrations start in **Pending** verification status.

4. **Recipient Registration**:
   - Patient registration: Full Name, Age, Gender, Email, Mobile, Attending Hospital/City, Blood Group, Required Organ, Clinical Urgency Level (`Critical`, `High`, `Medium`, `Low`), Diagnostic Summary, Password, and Consent Checkbox.
   - Auto-assigned initial status: **Pending**.

5. **Multi-Role Authentication (Sessions & Bcrypt)**:
   - Unified login with role-based routing (`admin`, `donor`, `recipient`).
   - Secure `password_hash()` and `password_verify()` cryptography.
   - One-click demo login buttons for frictionless academic evaluation.

6. **Explainable Preliminary Matching Engine**:
   - Automatic multi-criteria evaluation:
     - **Organ Identity**: Donor organ must strictly equal Recipient required organ (Mandatory prerequisite).
     - **Verification**: Both donor and recipient must be verified by administrators.
     - **Availability**: Donor status must be `Available`.
     - **Immunohematology Matrix**: Predefined ABO red blood cell compatibility (Universal donor $O^-$ to all; $AB^+$ universal recipient).
     - **Urgency Weighting**: Priority queue ranks `Critical` recipients ahead of `High`, `Medium`, and `Low`.
     - **100-Point Formula**: Organ (40 pts) + Blood (35/30 pts) + Urgency (up to 20 pts) + Location Proximity (5 pts).
   - Administrator workflow actions: update status to `Under Review`, `Contacted`, `Approved`, or `Closed`.

7. **Privacy-Preserving User Dashboards**:
   - **Donor Dashboard**: View profile, edit contact/city/availability, monitor verification state, and view anonymous match notices without exposing recipient confidential identifiers.
   - **Recipient Dashboard**: View requirement profile, update contact/hospital details, and track coordination progress anonymously.

8. **Comprehensive Administrator Console**:
   - KPI metric summary cards.
   - Donor management table with multi-filter (blood group, organ, status, availability) and search.
   - Recipient management table with urgency filters and search.
   - Modal verification review and rejection reason recording.
   - System audit trail (`admin_logs`) tracking administrative actions.

---

## Directory Structure

```
organ_donation_portal/
├── database.sql               # MySQL schema + initial seed data
├── config.php                 # Global PDO connection, app settings & compatibility matrix
├── index.php                  # Landing page with hero, live stats, How It Works, SDGs, Awareness
├── donor_register.php         # Donor pledge registration form with validation
├── recipient_register.php     # Recipient requirement form with urgency levels
├── login.php                  # Authentication page with demo quick-fill buttons
├── logout.php                 # Safe session termination
├── donor_dashboard.php        # Donor profile, availability toggle & match alerts
├── recipient_dashboard.php    # Recipient profile, status & coordination tracking
├── admin/
│   ├── index.php              # Admin overview with KPI cards and pending review queue
│   ├── donors.php             # Review donors, search, filter & verification modals
│   ├── recipients.php         # Review recipients, filter by urgency & verification
│   ├── matches.php            # Algorithmic matching workstation with score breakdown
│   ├── logs.php               # System audit log trail
│   ├── sidebar.php            # Reusable admin navigation component
│   └── update_status.php      # CSRF-protected action endpoint for admin updates
├── includes/
│   ├── auth.php               # Session security, role guards & CSRF verification
│   ├── matching_engine.php    # Explainable matching logic & mathematical scoring
│   ├── header.php             # Semantic HTML5 header & sticky navbar
│   └── footer.php             # Modern healthcare footer with disclaimers & quick links
└── assets/
    ├── css/
    │   ├── style.css          # Google/Material Design system tokens & public styles
    │   └── dashboard.css      # Portal dashboards, KPI cards, tables & modals
    ├── js/
    │   ├── main.js            # Mobile drawer, accordions, toast notifications, modals
    │   ├── validation.js      # Client-side input validation with friendly error hints
    │   └── dashboard.js       # Table live search, multi-filters & modal data binding
    └── images/
        └── logo.svg           # Scalable vector healthcare logo
```

---

## Installation & Setup Instructions

### Prerequisites
- **XAMPP** (recommended) or **WAMP** / **MAMP** with PHP 7.4+ or PHP 8.0+ and MySQL 5.7+ / MariaDB 10.3+.

---

### Method A: Running via XAMPP (Standard)

1. **Move Project Folder**:
   Copy the `organ_donation_portal` folder to your XAMPP `htdocs` directory:
   ```
   C:\xampp\htdocs\organ_donation_portal
   ```

2. **Start Services**:
   Open **XAMPP Control Panel** and start **Apache** and **MySQL**.

3. **Import Database**:
   - Open your browser and navigate to: `http://localhost/phpmyadmin`
   - Click on the **Import** tab in the top menu.
   - Select the file `database.sql` located inside `organ_donation_portal/`.
   - Click **Go** (or **Import**) at the bottom.
   - *Note: `database.sql` automatically creates the database `organ_donation_db` and inserts sample donors, recipients, matches, and admin logs.*

4. **Open in Browser**:
   Navigate to:
   ```
   http://localhost/organ_donation_portal/
   ```

---

### Method B: Running with `npm run dev` (Instant Launch)

A `package.json` file is pre-configured so you can use standard dev tooling:

1. Open PowerShell or Terminal in the project folder:
   ```bash
   cd "C:\Users\ADITHYAN P S\OneDrive\Desktop\organ_donation_portal"
   ```

2. Start the local server:
   ```bash
   npm run dev
   ```

3. Open your browser at:
   ```
   http://localhost:8000
   ```

---

### Method C: Running via Direct PHP CLI

---

## Pre-seeded Demo Accounts

Use these accounts to test all user perspectives. (You can also use the **1-Click Quick Demo Login** buttons on the login page):

| Role | Email | Password | Access / Dashboard |
| :--- | :--- | :--- | :--- |
| **Administrator** | `admin@organportal.com` | `admin123` | Full Admin Console, Verifications, Matching Engine, Audit Logs |
| **Verified Donor** | `donor@demo.com` | `donor123` | Pledged Kidney ($O^+$), Kerala, view match progress |
| **Critical Recipient** | `recipient@demo.com` | `recipient123` | Needs Kidney ($O^+$), Critical Priority, view match progress |

---

## Explainable Matching Algorithm (How It Works)

The portal implements an explainable, transparent algorithm designed for clarity in academic evaluation:

$$\text{Total Score} = \text{Organ Match (40 pts)} + \text{Blood Compatibility (35/30 pts)} + \text{Urgency (5–20 pts)} + \text{Proximity (5 pts)}$$

### 1. Hard Biological Prerequisites
1. $\text{Organ}_{\text{donor}} = \text{Organ}_{\text{recipient}}$
2. $\text{Status}_{\text{donor}} = \text{'Verified'} \land \text{Status}_{\text{recipient}} = \text{'Verified'}$
3. $\text{Availability}_{\text{donor}} = \text{'Available'}$
4. $\text{BloodCompatibility}(\text{Donor}, \text{Recipient}) = \text{true}$

### 2. Immunohematological Compatibility Rules
| Donor Blood Group | Compatible Recipient Blood Groups |
| :--- | :--- |
| **$O^-$** | $O^-, O^+, A^-, A^+, B^-, B^+, AB^-, AB^+$ *(Universal Red Blood Cell Donor)* |
| **$O^+$** | $O^+, A^+, B^+, AB^+$ |
| **$A^-$** | $A^-, A^+, AB^-, AB^+$ |
| **$A^+$** | $A^+, AB^+$ |
| **$B^-$** | $B^-, B^+, AB^-, AB^+$ |
| **$B^+$** | $B^+, AB^+$ |
| **$AB^-$**| $AB^-, AB^+$ |
| **$AB^+$**| $AB^+$ only *(Universal Recipient)* |

### 3. Point Weighting Distribution
- **Organ Match**: **40 Points** (Prerequisite).
- **Blood Group Compatibility**:
  - Exact match (e.g., $O^+ \to O^+$): **35 Points**.
  - Compatible alternative (e.g., $O^- \to A^+$): **30 Points**.
- **Clinical Urgency Bonus**:
  - `Critical`: **+20 Points** (Immediate ICU / decompensated organ failure).
  - `High`: **+15 Points** (Rapid clinical deterioration).
  - `Medium`: **+10 Points** (Stable maintenance on dialysis or therapy).
  - `Low`: **+5 Points** (Early elective listing).
- **Proximity Match**: **+5 Points** if donor city matches recipient hospital location.
- **Maximum Possible Score**: **100%**.

### 4. Ranking Order
Matches are presented in strict **Urgency First Order** (`Critical` &rarr; `High` &rarr; `Medium` &rarr; `Low`), sorted secondarily by **Compatibility Score Descending**.

---

## Security & Best Practices

- **SQL Injection Prevention**: All queries use **PDO prepared statements** with parameter binding (`?` placeholders).
- **Cross-Site Scripting (XSS) Prevention**: All dynamic values rendered in HTML are sanitized with `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.
- **Password Security**: Passwords are never stored in plain text; they are hashed using PHP's industry-standard `password_hash($pwd, PASSWORD_DEFAULT)` (Bcrypt) and verified using `password_verify()`.
- **Session Security**: Configured with `HttpOnly`, `SameSite=Lax`, and `session_regenerate_id(true)` upon authentication.
- **CSRF Protection**: Critical mutation requests (verification changes, status updates) require a valid cryptographic `csrf_token`.
- **Privacy By Design**: User dashboards hide confidential medical records and identities of counter-parties to uphold medical ethics.

---

## Viva / Presentation Talking Points

1. **Why is preliminary digital matching beneficial in organ donation?**  
   *Answer*: Transplants are time-critical (e.g., cold ischemic time: hearts have 4–6 hours, kidneys have 24–36 hours). Automating initial ABO compatibility and availability screening eliminates hours of manual searching, allowing transplant teams to initiate immunological cross-matching immediately.

2. **How does this project address ethical and legal guidelines?**  
   *Answer*: The platform strictly separates preliminary administrative screening from clinical clearance. It displays prominent disclaimers on every page, anonymizes user views to prevent direct coercion, and maintains an immutable audit log (`admin_logs`) for accountability.

3. **How does this align with UN Sustainable Development Goals?**  
   *Answer*: It advances **SDG 3 (Good Health and Well-Being)** by reducing organ failure mortality through rapid match identification, and **SDG 10 (Reduced Inequalities)** by applying an objective, transparent algorithm where medical urgency and biological suitability take precedence over wealth or social influence.

---

## License & Academic Attribution
Developed as an academic demonstration microproject. Free for educational study, presentations, and non-commercial learning.
