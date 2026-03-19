<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

if (isLoggedIn()) {
    redirectToHome();
}

$hasUsers = false;

try {
    $userCheckStatement = $pdo->prepare('SELECT EXISTS(SELECT 1 FROM users LIMIT 1)');
    $userCheckStatement->execute();
    $hasUsers = (bool) $userCheckStatement->fetchColumn();
} catch (Throwable $exception) {
    $hasUsers = false;
}

$primaryActionHref = $hasUsers ? 'login.php' : 'setup.php';
$primaryActionLabel = $hasUsers ? 'Doctor Or Patient Sign In' : 'Run Initial Setup';
$secondaryActionHref = $hasUsers ? 'admin_login.php' : 'setup.php';
$secondaryActionLabel = $hasUsers ? 'Administrator Sign In' : 'Create First Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medilink Emergency Clinic</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&family=Source+Sans+3:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
</head>
<body class="landing-page">
    <div class="pattern-overlay"></div>
    <main class="landing-shell container py-4 py-lg-5">
        <section class="landing-masthead">
            <div>
                <span class="landing-brand-mark">Medilink Emergency Clinic</span>
                <p class="landing-masthead-copy mb-0">Emergency coordination, appointment scheduling, and patient follow-up in one place.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-sm btn-outline-secondary" href="<?= $primaryActionHref; ?>"><?= $primaryActionLabel; ?></a>
                <a class="btn btn-sm btn-outline-dark" href="<?= $secondaryActionHref; ?>"><?= $secondaryActionLabel; ?></a>
            </div>
        </section>

        <nav class="landing-topnav" aria-label="Landing page navigation">
            <a href="#overview">Overview</a>
            <a href="#features">Features</a>
            <a href="#access">Access</a>
            <a href="#workflow">Workflow</a>
            <a href="#get-started">Get Started</a>
        </nav>

        <section class="landing-hero" id="overview">
            <div class="landing-hero-copy">
                <span class="eyebrow">Emergency Coordination Platform</span>
                <div class="landing-status-pill">Built for emergency clinics, triage teams, and patient follow-up workflows</div>
                <h1 class="landing-title">Coordinate emergency care, appointments, and patient follow-up from one Medilink workspace.</h1>
                <p class="landing-lead">
                    Medilink Emergency Clinic brings together doctor scheduling, patient records, emergency call tracking,
                    and appointment visibility in a single secure system designed for fast clinical decision-making.
                </p>

                <div class="d-flex flex-wrap gap-3 mt-4">
                    <a class="btn btn-primary btn-lg" href="<?= $primaryActionHref; ?>"><?= $primaryActionLabel; ?></a>
                    <a class="btn btn-outline-light btn-lg" href="<?= $secondaryActionHref; ?>"><?= $secondaryActionLabel; ?></a>
                </div>

                <div class="landing-mini-stats">
                    <div class="landing-stat-chip">
                        <strong>Doctors</strong>
                        <span>Schedule appointments and manage emergency queues</span>
                    </div>
                    <div class="landing-stat-chip">
                        <strong>Patients</strong>
                        <span>View appointments and linked emergency records</span>
                    </div>
                    <div class="landing-stat-chip">
                        <strong>Admins</strong>
                        <span>Create accounts, monitor activity, and oversee access</span>
                    </div>
                </div>
            </div>

            <aside class="landing-hero-panel">
                <div class="landing-panel-card landing-panel-primary">
                    <span class="landing-panel-label">Live Workflow</span>
                    <h2>One platform for intake to follow-up</h2>
                    <ul class="landing-panel-list">
                        <li>Capture emergency calls with patient-linked records</li>
                        <li>Create future-only appointments from the doctor workspace</li>
                        <li>Surface appointment records for both doctors and administrators</li>
                    </ul>
                </div>

                <div class="landing-panel-grid">
                    <div class="landing-panel-card">
                        <span class="landing-panel-label">Doctor Access</span>
                        <p>Review call queues, track patient context, and book appointments.</p>
                    </div>
                    <div class="landing-panel-card">
                        <span class="landing-panel-label">Patient Access</span>
                        <p>Check upcoming visits, account-linked records, and care history.</p>
                    </div>
                </div>
            </aside>
        </section>

        <section class="landing-band">
            <div class="landing-band-item">
                <span class="landing-band-number">01</span>
                <div>
                    <h2>Rapid intake</h2>
                    <p>Register emergencies quickly with patient-linked data and dispatch notes.</p>
                </div>
            </div>
            <div class="landing-band-item">
                <span class="landing-band-number">02</span>
                <div>
                    <h2>Clinical scheduling</h2>
                    <p>Doctors create future appointments directly inside the operational dashboard.</p>
                </div>
            </div>
            <div class="landing-band-item">
                <span class="landing-band-number">03</span>
                <div>
                    <h2>Shared visibility</h2>
                    <p>Admins and doctors can review appointment records without leaving the system.</p>
                </div>
            </div>
        </section>

        <section class="landing-section" id="access">
            <div class="landing-section-heading">
                <div>
                    <span class="eyebrow">Role Entry</span>
                    <h2>Each team enters the system through the workflow built for them.</h2>
                </div>
                <p class="mb-0">The landing page now works as a clear front door for care teams, patients, and administration without mixing up responsibilities.</p>
            </div>

            <div class="landing-access-grid">
                <article class="landing-access-card">
                    <span class="landing-feature-tag">Doctor</span>
                    <h3>Manage emergency workload</h3>
                    <p>Doctors can review assigned calls, inspect patient context, and book future appointments from the same workspace.</p>
                    <a class="btn btn-outline-primary" href="<?= $primaryActionHref; ?>">Open Doctor Sign In</a>
                </article>

                <article class="landing-access-card">
                    <span class="landing-feature-tag">Patient</span>
                    <h3>Check visits and records</h3>
                    <p>Patients can sign in to view their linked information, upcoming appointments, and emergency history.</p>
                    <a class="btn btn-outline-primary" href="<?= $primaryActionHref; ?>">Open Patient Sign In</a>
                </article>

                <article class="landing-access-card landing-access-card-accent">
                    <span class="landing-feature-tag">Admin</span>
                    <h3>Oversee the whole clinic</h3>
                    <p>Administrators can manage users, review appointment records, and keep operational access under control.</p>
                    <a class="btn btn-dark" href="<?= $secondaryActionHref; ?>">Open Admin Portal</a>
                </article>
            </div>
        </section>

        <section class="row g-4 mt-1" id="features">
            <div class="col-lg-4">
                <div class="feature-card landing-feature-card">
                    <span class="landing-feature-tag">Operations</span>
                    <h2>Emergency Response Board</h2>
                    <p>Track pending, assigned, and completed calls while keeping patient details close at hand.</p>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="feature-card landing-feature-card">
                    <span class="landing-feature-tag">Scheduling</span>
                    <h2>Appointments With Guardrails</h2>
                    <p>Schedule only forward dates, assign doctors, and maintain a clean appointment record trail.</p>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="feature-card landing-feature-card">
                    <span class="landing-feature-tag">Access Control</span>
                    <h2>Role-Based Entry</h2>
                    <p>Separate doctor, patient, and administrator access points keep workflows focused and secure.</p>
                </div>
            </div>
        </section>

        <section class="landing-section landing-operations-section" id="workflow">
            <div class="landing-section-heading">
                <div>
                    <span class="eyebrow">Clinic Workflow</span>
                    <h2>How Medilink moves a case through the system.</h2>
                </div>
                <p class="mb-0">The platform supports the handoff from first contact to follow-up, making it easier to keep both emergency and scheduled care visible.</p>
            </div>

            <div class="landing-operations-grid">
                <div class="landing-timeline-card">
                    <div class="landing-timeline-step">
                        <span>1</span>
                        <div>
                            <h3>Emergency intake</h3>
                            <p>Frontline staff capture emergency details and link the incident to the right patient record.</p>
                        </div>
                    </div>
                    <div class="landing-timeline-step">
                        <span>2</span>
                        <div>
                            <h3>Doctor action</h3>
                            <p>Doctors review queue items, update outcomes, and turn urgent cases into scheduled follow-up where needed.</p>
                        </div>
                    </div>
                    <div class="landing-timeline-step">
                        <span>3</span>
                        <div>
                            <h3>Patient visibility</h3>
                            <p>Patients log in to see appointments and the care history tied to their account.</p>
                        </div>
                    </div>
                </div>

                <div class="landing-readiness-card">
                    <span class="landing-feature-tag">System Readiness</span>
                    <h3><?= $hasUsers ? 'The clinic system is ready for sign-in.' : 'Finish setup to unlock the workspace.'; ?></h3>
                    <p><?= $hasUsers ? 'Accounts already exist, so the team can move straight into doctor, patient, or administrator workflows.' : 'Once the first admin account is created, Medilink will be ready for role-based login and clinic operations.'; ?></p>
                    <div class="landing-readiness-list">
                        <div><strong>Secure access</strong><span>Role-based entry for doctors, patients, and admins</span></div>
                        <div><strong>Appointment rules</strong><span>Doctor scheduling supports future dates only</span></div>
                        <div><strong>Shared records</strong><span>Appointment visibility for both doctor and admin pages</span></div>
                    </div>
                </div>
            </div>
        </section>

        <section class="landing-cta" id="get-started">
            <div>
                <span class="eyebrow">Ready To Start</span>
                <h2 class="mb-2">Open Medilink and get the clinic team moving.</h2>
                <p class="mb-0 text-muted">Sign in if accounts already exist, or complete the one-time setup to initialize the system.</p>
            </div>
            <div class="d-flex flex-wrap gap-3">
                <a class="btn btn-primary btn-lg" href="<?= $primaryActionHref; ?>">
                    <?= $hasUsers ? 'Open System' : 'Start Setup'; ?>
                </a>
                <?php if ($hasUsers): ?>
                    <a class="btn btn-outline-secondary btn-lg" href="<?= $secondaryActionHref; ?>">Admin Portal</a>
                <?php endif; ?>
            </div>
        </section>
    </main>
</body>
</html>
