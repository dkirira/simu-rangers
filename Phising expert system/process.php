<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.html');
    exit;
}

$fields = [
    'sender_known' => 'Sender is known',
    'suspicious_link' => 'Contains a suspicious link',
    'sensitive_request' => 'Requests sensitive information',
    'urgent_language' => 'Uses urgent or threatening language',
    'suspicious_attachment' => 'Contains suspicious attachments',
];

$responses = [];
$facts = [];
$reasons = [];
$validationErrors = [];

foreach ($fields as $field => $label) {
    $value = $_POST[$field] ?? null;

    if ($value !== 'yes' && $value !== 'no') {
        $validationErrors[] = "Please provide a valid answer for {$label}.";
        continue;
    }

    $responses[$field] = $value;
    $facts[$field] = $value === 'yes';
}

if (!empty($validationErrors)) {
    $statusKey = 'suspicious';
    $statusLabel = 'Incomplete Submission';
    $summary = 'The analysis could not be completed because some answers were missing or invalid.';
    $displayReasons = $validationErrors;
    $derivedFacts = [];
} else {
    $derivedFacts = [];
    $knowledgeBase = [
        [
            'conditions' => ['sender_known' => false, 'suspicious_link' => true],
            'conclusion' => 'phishing',
            'derived_fact' => 'Unknown sender with a suspicious link suggests phishing.',
            'reason' => 'Sender is unknown and the email contains a suspicious link.',
        ],
        [
            'conditions' => ['sensitive_request' => true],
            'conclusion' => 'phishing',
            'derived_fact' => 'A request for sensitive information is a phishing indicator.',
            'reason' => 'The email requests sensitive information.',
        ],
        [
            'conditions' => ['urgent_language' => true, 'sender_known' => false],
            'conclusion' => 'suspicious',
            'derived_fact' => 'Urgent language from an unknown sender makes the email suspicious.',
            'reason' => 'The email uses urgent or threatening language and the sender is unknown.',
        ],
        [
            'conditions' => ['suspicious_attachment' => true],
            'conclusion' => 'suspicious',
            'derived_fact' => 'Unexpected or suspicious attachments increase risk.',
            'reason' => 'The email contains suspicious attachments.',
        ],
        [
            'conditions' => [
                'sender_known' => true,
                'suspicious_link' => false,
                'sensitive_request' => false,
                'urgent_language' => false,
                'suspicious_attachment' => false,
            ],
            'conclusion' => 'safe',
            'derived_fact' => 'Known sender with no warning indicators is considered safe.',
            'reason' => 'The sender is known and no suspicious indicators were detected.',
        ],
    ];

    $triggeredConclusions = [];
    $appliedRules = [];
    $newInference = true;

    // Repeatedly apply matching rules until no new conclusions can be drawn.
    while ($newInference) {
        $newInference = false;

        foreach ($knowledgeBase as $index => $rule) {
            if (isset($appliedRules[$index])) {
                continue;
            }

            $matches = true;

            foreach ($rule['conditions'] as $factName => $expectedValue) {
                if (!array_key_exists($factName, $facts) || $facts[$factName] !== $expectedValue) {
                    $matches = false;
                    break;
                }
            }

            if (!$matches) {
                continue;
            }

            $appliedRules[$index] = true;
            $triggeredConclusions[$rule['conclusion']] = true;
            $derivedFacts[] = $rule['derived_fact'];
            $reasons[] = $rule['reason'];
            $newInference = true;
        }
    }

    if (isset($triggeredConclusions['phishing'])) {
        $statusKey = 'phishing';
        $statusLabel = 'Phishing Email';
        $summary = 'The expert system found high-risk phishing indicators based on the submitted evidence.';
    } elseif (isset($triggeredConclusions['suspicious'])) {
        $statusKey = 'suspicious';
        $statusLabel = 'Suspicious Email';
        $summary = 'The expert system found warning signs that make this email suspicious and worth investigating further.';
    } elseif (isset($triggeredConclusions['safe'])) {
        $statusKey = 'safe';
        $statusLabel = 'Safe Email';
        $summary = 'The expert system found no phishing indicators in the provided answers.';
    } else {
        $statusKey = 'suspicious';
        $statusLabel = 'Suspicious Email';
        $summary = 'The provided indicators do not fully match a safe rule, so the email should be treated cautiously.';
        $reasons[] = 'The pattern does not satisfy any safe rule in the knowledge base.';
    }

    $displayReasons = array_values(array_unique($reasons));
    $derivedFacts = array_values(array_unique($derivedFacts));

    if (empty($derivedFacts)) {
        $derivedFacts[] = 'No additional inference facts were generated beyond the submitted inputs.';
    }
}

function formatAnswer(string $value): string
{
    return $value === 'yes' ? 'Yes' : 'No';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analysis Result | Phishing Detection Expert System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="page-shell">
        <main class="result-card">
            <section class="result-header">
                <span class="eyebrow">Analysis Result</span>
                <h1>Phishing Detection Expert System</h1>
                <p>The email has been analyzed using rule-based forward chaining.</p>
            </section>

            <section class="result-body">
                <div class="result-grid">
                    <article class="result-panel status-<?php echo htmlspecialchars($statusKey, ENT_QUOTES, 'UTF-8'); ?>-panel">
                        <span class="status-badge status-<?php echo htmlspecialchars($statusKey, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                        <h2 class="result-title"><?php echo htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?></h2>
                        <div class="result-copy">
                            <p><?php echo htmlspecialchars($summary, ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                    </article>

                    <article class="reason-panel">
                        <h2>Reason</h2>
                        <ul class="reason-list">
                            <?php foreach ($displayReasons as $reason): ?>
                                <li><?php echo htmlspecialchars($reason, ENT_QUOTES, 'UTF-8'); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </article>
                </div>

                <div class="result-grid result-grid-spaced">
                    <article class="facts-panel">
                        <h2>Submitted Indicators</h2>
                        <ul class="facts-list">
                            <?php foreach ($fields as $field => $label): ?>
                                <?php if (isset($responses[$field])): ?>
                                    <li>
                                        <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>:
                                        <?php echo htmlspecialchars(formatAnswer($responses[$field]), ENT_QUOTES, 'UTF-8'); ?>
                                    </li>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </ul>
                    </article>

                    <article class="facts-panel">
                        <h2>Forward-Chained Facts</h2>
                        <ul class="guidance-list">
                            <?php foreach ($derivedFacts as $fact): ?>
                                <li><?php echo htmlspecialchars($fact, ENT_QUOTES, 'UTF-8'); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </article>
                </div>

                <div class="action-row result-actions">
                    <a href="index.html" class="primary-button">Analyze Another Email</a>
                    <a href="index.html" class="link-button">Back to Form</a>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
