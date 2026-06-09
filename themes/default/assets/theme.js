/**
 * Forux default theme JavaScript handler
 * Zero-dependency modern Vanilla JS interactions
 */
document.addEventListener('DOMContentLoaded', () => {
    const baseUrl = window.FORUX_BASE_URL || '';
    const textarea = document.getElementById('reply-textarea');
    const toolbar = document.getElementById('editor-toolbar');

    // ==========================================
    // 1. WYSIWYG Toolbar Insertion
    // ==========================================
    if (toolbar && textarea) {
        toolbar.querySelectorAll('.toolbar-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const tag = btn.getAttribute('data-tag');
                let openTag = '';
                let closeTag = '';

                switch (tag) {
                    case 'b':
                        openTag = '<strong>';
                        closeTag = '</strong>';
                        break;
                    case 'i':
                        openTag = '<em>';
                        closeTag = '</em>';
                        break;
                    case 'blockquote':
                        openTag = '<blockquote>';
                        closeTag = '</blockquote>';
                        break;
                    case 'a':
                        const url = prompt('Enter link URL:', 'https://');
                        if (url) {
                            openTag = `<a href="${url}" target="_blank">`;
                            closeTag = '</a>';
                        } else {
                            return;
                        }
                        break;
                    case 'img':
                        const imageUrl = prompt('Enter image URL:', 'https://');
                        if (imageUrl) {
                            openTag = `<img src="${imageUrl}" alt="image" style="max-width: 100%; border-radius: 8px;">`;
                            closeTag = '';
                        } else {
                            return;
                        }
                        break;
                }

                insertTag(textarea, openTag, closeTag);
            });
        });
    }

    function insertTag(field, openTag, closeTag) {
        const start = field.selectionStart;
        const end = field.selectionEnd;
        const val = field.value;
        const selected = val.substring(start, end);
        const replacement = openTag + selected + closeTag;
        
        field.value = val.substring(0, start) + replacement + val.substring(end);
        field.selectionStart = start + openTag.length;
        field.selectionEnd = field.selectionStart + selected.length;
        field.focus();
    }

    // ==========================================
    // 2. Paste-to-Upload Integration
    // ==========================================
    if (textarea) {
        textarea.addEventListener('paste', (e) => {
            const items = (e.clipboardData || e.originalEvent.clipboardData).items;
            let imageFile = null;

            for (let i = 0; i < items.length; i++) {
                if (items[i].type.indexOf('image') === 0) {
                    imageFile = items[i].getAsFile();
                    break;
                }
            }

            if (imageFile) {
                e.preventDefault(); // Stop default raw paste
                uploadImageFile(imageFile);
            }
        });
    }

    function uploadImageFile(file) {
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const val = textarea.value;
        const placeholder = '\n[Uploading image...]\n';
        
        // Insert placeholder
        textarea.value = val.substring(0, start) + placeholder + val.substring(end);
        const placeholderStart = start;
        const placeholderEnd = start + placeholder.length;

        const formData = new FormData();
        formData.append('image', file);

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const headers = { 'X-Requested-With': 'XMLHttpRequest' };
        if (csrfToken) {
            headers['X-CSRF-Token'] = csrfToken;
        }

        fetch(`${baseUrl}/api/upload`, {
            method: 'POST',
            body: formData,
            headers: headers
        })
        .then(res => res.json())
        .then(data => {
            if (data.url) {
                const imgTag = `\n<img src="${data.url}" alt="image" style="max-width: 100%; border-radius: 8px;">\n`;
                textarea.value = textarea.value.replace(placeholder, imgTag);
            } else {
                alert(data.error || 'Failed to upload image.');
                textarea.value = textarea.value.replace(placeholder, '');
            }
        })
        .catch(err => {
            alert('An error occurred during file upload.');
            textarea.value = textarea.value.replace(placeholder, '');
        });
    }

    // ==========================================
    // 3. @mention Autocomplete Search Overlay
    // ==========================================
    const mentionOverlay = document.getElementById('mention-autocomplete');
    let mentionStartIndex = -1;

    if (textarea && mentionOverlay) {
        textarea.addEventListener('input', (e) => {
            const val = textarea.value;
            const caretPos = textarea.selectionStart;
            
            // Look backward from caret to find a word starting with @
            const lastAtIdx = val.lastIndexOf('@', caretPos - 1);
            
            if (lastAtIdx !== -1 && lastAtIdx >= val.lastIndexOf(' ', caretPos - 1)) {
                const query = val.substring(lastAtIdx + 1, caretPos);
                
                // Allow search starting from 1 character after @
                if (query.length >= 1 && /^[a-zA-Z0-9_\-]+$/.test(query)) {
                    mentionStartIndex = lastAtIdx;
                    fetchMentionUsers(query);
                } else {
                    hideMentionOverlay();
                }
            } else {
                hideMentionOverlay();
            }
        });

        // Close overlay on clicking outside
        document.addEventListener('click', (e) => {
            if (e.target !== textarea && e.target !== mentionOverlay && !mentionOverlay.contains(e.target)) {
                hideMentionOverlay();
            }
        });
    }

    function fetchMentionUsers(query) {
        fetch(`${baseUrl}/api/users/search?q=${encodeURIComponent(query)}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(users => {
            if (users && users.length > 0) {
                showMentionOverlay(users);
            } else {
                hideMentionOverlay();
            }
        })
        .catch(() => hideMentionOverlay());
    }

    function showMentionOverlay(users) {
        mentionOverlay.innerHTML = '';
        
        users.forEach((user, index) => {
            const item = document.createElement('div');
            item.className = 'mention-item';
            item.innerHTML = `
                <img src="${user.avatar}" alt="Avatar" class="mention-avatar">
                <span>@${user.username}</span>
            `;
            
            item.addEventListener('click', () => {
                insertMention(user.username);
            });
            
            mentionOverlay.appendChild(item);
        });

        // Position overlay dynamically below caret / editor box
        const caretCoords = getCaretCoordinates(textarea, textarea.selectionStart);
        const rect = textarea.getBoundingClientRect();
        
        mentionOverlay.style.display = 'block';
        mentionOverlay.style.left = `${Math.min(rect.width - 200, caretCoords.left)}px`;
        mentionOverlay.style.top = `${caretCoords.top + 20}px`;
    }

    function insertMention(username) {
        const val = textarea.value;
        const caretPos = textarea.selectionStart;
        const before = val.substring(0, mentionStartIndex);
        const after = val.substring(caretPos);
        
        textarea.value = before + '@' + username + ' ' + after;
        textarea.focus();
        textarea.selectionStart = textarea.selectionEnd = mentionStartIndex + username.length + 2;
        hideMentionOverlay();
    }

    function hideMentionOverlay() {
        if (mentionOverlay) {
            mentionOverlay.style.display = 'none';
        }
    }

    // Helper to calculate caret absolute positioning coordinates inside textarea
    function getCaretCoordinates(element, position) {
        const div = document.createElement('div');
        const style = window.getComputedStyle(element);
        
        for (const prop of style) {
            div.style[prop] = style[prop];
        }
        
        div.style.position = 'absolute';
        div.style.visibility = 'hidden';
        div.style.whiteSpace = 'pre-wrap';
        div.style.wordWrap = 'break-word';
        
        const val = element.value.substring(0, position);
        div.textContent = val;
        
        const span = document.createElement('span');
        span.textContent = element.value.substring(position) || '.';
        div.appendChild(span);
        
        document.body.appendChild(div);
        const rect = span.getBoundingClientRect();
        const elementRect = element.getBoundingClientRect();
        document.body.removeChild(div);
        
        return {
            left: rect.left - elementRect.left + element.scrollLeft,
            top: rect.top - elementRect.top + element.scrollTop
        };
    }

    // ==========================================
    // 4. AJAX Notifications Badge Polling
    // ==========================================
    const badge = document.getElementById('notification-badge');
    if (badge) {
        function pollNotifications() {
            fetch(`${baseUrl}/api/notifications/unread`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (typeof data.count !== 'undefined') {
                    if (data.count > 0) {
                        badge.textContent = data.count;
                        badge.style.display = 'inline-block';
                    } else {
                        badge.style.display = 'none';
                    }
                }
            })
            .catch(() => {});
        }

        // Poll every 30 seconds
        setInterval(pollNotifications, 30000);
    }

    // ==========================================
    // 5. Reactions Click Micro-animations
    // ==========================================
    document.querySelectorAll('.react-submit-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            btn.style.transform = 'scale(1.6) rotate(-10deg)';
            btn.style.transition = 'transform 0.15s cubic-bezier(0.175, 0.885, 0.32, 1.275)';
        });
    });
});
