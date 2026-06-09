<div class="editor-container font-standard">
    <div class="card editor-card">
        <h2>Start a New Thread</h2>
        
        <?php if (!empty($error)): ?>
            <div class="alert">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form action="<?= $baseUrl ?>/thread/new/<?= (int)$categoryId ?>" method="POST" id="new-thread-form">
            <div class="form-group">
                <label for="title">Thread Title</label>
                <input type="text" id="title" name="title" class="form-control" placeholder="Enter an engaging title for your thread" required autofocus>
            </div>
            
            <!-- WYSIWYG Toolbar -->
            <div class="wysiwyg-toolbar" id="editor-toolbar">
                <button type="button" class="toolbar-btn" data-tag="b" title="Bold"><strong>B</strong></button>
                <button type="button" class="toolbar-btn" data-tag="i" title="Italic"><em>I</em></button>
                <button type="button" class="toolbar-btn" data-tag="blockquote" title="Quote">❝</button>
                <button type="button" class="toolbar-btn" data-tag="a" title="Insert Link">🔗</button>
                <button type="button" class="toolbar-btn" data-tag="img" title="Insert Image">🖼</button>
            </div>

            <!-- Editor Textarea wrapper -->
            <div class="form-group" style="position: relative;">
                <label for="reply-textarea">Post Body (Safe HTML allowed)</label>
                <textarea id="reply-textarea" name="body" class="form-control" rows="10" placeholder="Write your post here... (Safe HTML & Image paste enabled)" required></textarea>
                
                <!-- Autocomplete Overlay Container -->
                <div id="mention-autocomplete" class="mention-overlay" style="display: none;"></div>
            </div>
            
            <div class="editor-actions">
                <button type="submit" class="btn">Create Thread</button>
                <a href="<?= $baseUrl ?>/" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<style>
    .font-standard {
        font-family: var(--font-family);
    }
    
    .editor-container {
        display: flex;
        justify-content: center;
        margin: 1rem 0;
    }
    
    .editor-card {
        width: 100%;
        max-width: 720px;
        background: var(--card-bg);
        border: 1px solid var(--card-border);
        border-radius: 16px;
        padding: 2.5rem;
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
    }
    
    .editor-card h2 {
        margin-top: 0;
        margin-bottom: 2rem;
        font-size: 1.6rem;
        font-weight: 800;
        background: linear-gradient(135deg, var(--primary) 0%, #3b82f6 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        letter-spacing: -0.04em;
    }
    
    /* Editor Toolbar */
    .wysiwyg-toolbar {
        background: rgba(15, 23, 42, 0.6);
        border: 1px solid var(--glass-border);
        border-bottom: none;
        border-radius: 8px 8px 0 0;
        padding: 0.5rem;
        display: flex;
        gap: 0.25rem;
    }

    .toolbar-btn {
        background: none;
        border: none;
        color: var(--text-muted);
        padding: 0.35rem 0.65rem;
        border-radius: 4px;
        cursor: pointer;
        font-family: inherit;
        font-size: 0.95rem;
        transition: background-color 0.2s, color 0.2s;
    }

    .toolbar-btn:hover {
        background-color: rgba(31, 41, 55, 0.6);
        color: var(--primary);
    }

    #reply-textarea {
        border-radius: 0 0 8px 8px;
    }

    .editor-actions {
        display: flex;
        gap: 1rem;
        margin-top: 1.5rem;
    }
    
    .editor-actions .btn {
        flex: 1;
    }

    /* Autocomplete mention popup overlay */
    .mention-overlay {
        position: absolute;
        background: rgba(17, 24, 39, 0.95);
        border: 1px solid var(--card-border);
        border-radius: 8px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
        width: 200px;
        z-index: 1000;
        max-height: 160px;
        overflow-y: auto;
    }

    .mention-item {
        padding: 0.5rem 0.75rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.85rem;
        transition: background-color 0.15s;
    }

    .mention-item:hover, .mention-item.selected {
        background-color: var(--primary);
        color: white;
    }

    .mention-avatar {
        width: 20px;
        height: 20px;
        border-radius: 50%;
    }
</style>
