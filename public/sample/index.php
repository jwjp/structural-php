<main class="sample-page">
    <h1><?= htmlspecialchars($title ?? 'Sample', ENT_QUOTES, 'UTF-8') ?></h1>
    <p><?= htmlspecialchars($content ?? 'The sample route is working.', ENT_QUOTES, 'UTF-8') ?></p>
</main>
