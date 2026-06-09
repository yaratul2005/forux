<div class="error-wrapper">
    <div class="card error-card">
        <h2><?= htmlspecialchars($title ?? 'Error') ?></h2>
        <div class="alert">
            <?= htmlspecialchars($message ?? 'An unexpected error occurred.') ?>
        </div>
        <div class="error-actions">
            <a href="javascript:history.back()" class="btn">Go Back</a>
            <a href="<?= $baseUrl ?>/" class="btn btn-secondary">Home</a>
        </div>
    </div>
</div>

<style>
    .error-wrapper {
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 50vh;
    }
    
    .error-card {
        width: 100%;
        max-width: 480px;
        text-align: center;
    }
    
    .error-card h2 {
        margin-top: 0;
        margin-bottom: 1.5rem;
        color: var(--error);
    }
    
    .error-actions {
        display: flex;
        gap: 1rem;
        margin-top: 2rem;
    }
    
    .error-actions .btn {
        flex: 1;
    }
</style>
