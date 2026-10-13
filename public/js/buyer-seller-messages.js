(() => {
    const shell = document.querySelector('[data-message-shell]');
    const fitChat = () => {
        if (!shell) return;
        const viewport = window.visualViewport;
        let bottom = viewport ? viewport.height + viewport.offsetTop : window.innerHeight;
        // Keep the Buyer mobile navigation accessible without covering the composer.
        for (const nav of document.querySelectorAll('body > nav')) {
            const rect = nav.getBoundingClientRect();
            if (getComputedStyle(nav).position === 'fixed' && rect.height && rect.bottom >= bottom - 1) bottom = Math.min(bottom, rect.top);
        }
        shell.style.setProperty('--chat-available-height', Math.max(200, bottom - shell.getBoundingClientRect().top - 20) + 'px');
    };
    fitChat();
    window.addEventListener('load', fitChat);
    window.addEventListener('resize', fitChat);
    window.visualViewport?.addEventListener('resize', fitChat);
    const workspace = document.querySelector('[data-chat-workspace]');
    if (!workspace) return;

    const scrollArea = workspace.querySelector('[data-message-scroll]');
    const form = workspace.querySelector('[data-message-form]');
    const input = form.querySelector('textarea');
    const photoInput = form.querySelector('[data-photo-input]');
    const choosePhoto = form.querySelector('[data-choose-photo]');
    const preview = workspace.querySelector('[data-photo-preview]');
    const thumbnails = workspace.querySelector('[data-photo-thumbnails]');
    const photoCount = workspace.querySelector('[data-photo-count]');
    const tokenInput = form.querySelector('[name="client_token"]');
    let selectedPhotos = [];
    let previewUrls = [];
    let sending = false;
    let draftSignature = null;
    const photoKey = (file) => [file.name, file.size, file.lastModified, file.type].join(':');
    const resetPhotos = () => {
        previewUrls.forEach(url => URL.revokeObjectURL(url));
        previewUrls = [];
        selectedPhotos = [];
        photoInput.value = '';
        thumbnails.replaceChildren();
        preview.hidden = true;
        photoCount.textContent = '';
    };
    choosePhoto.addEventListener('click', () => photoInput.click());
    photoInput.addEventListener('change', () => {
        const incoming = Array.from(photoInput.files || []);
        photoInput.value = '';
        if (!incoming.length) return;
        const invalid = incoming.find(file => !['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 5 * 1024 * 1024);
        if (invalid) {
            showSendError('Choose JPEG, PNG or WebP photos up to 5 MB each.');
            return;
        }
        const existing = new Set(selectedPhotos.map(photoKey));
        const additions = incoming.filter(file => !existing.has(photoKey(file)));
        if (selectedPhotos.length + additions.length > 5) {
            showSendError('You can attach up to 5 photos per message.');
            return;
        }
        selectedPhotos.push(...additions);
        renderPhotoPreview();
        clearSendError();
    });
    const renderPhotoPreview = () => {
        previewUrls.forEach(url => URL.revokeObjectURL(url));
        previewUrls = [];
        thumbnails.replaceChildren();
        selectedPhotos.forEach((file, index) => {
            const item = document.createElement('span');
            item.className = 'message-photo-preview-item';
            const image = document.createElement('img');
            image.alt = `Selected photo ${index + 1}`;
            const previewUrl = URL.createObjectURL(file);
            previewUrls.push(previewUrl);
            image.src = previewUrl;
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.textContent = '×';
            remove.title = 'Remove photo';
            remove.setAttribute('aria-label', `Remove photo ${index + 1}`);
            remove.addEventListener('click', () => {
                selectedPhotos.splice(index, 1);
                renderPhotoPreview();
            });
            item.append(image, remove);
            thumbnails.append(item);
        });
        photoCount.textContent = selectedPhotos.length ? `${selectedPhotos.length} ${selectedPhotos.length === 1 ? 'photo' : 'photos'} selected` : '';
        preview.hidden = selectedPhotos.length === 0;
    };
    const lightbox = document.querySelector('[data-photo-lightbox]');
    workspace.addEventListener('click', event => {
        const button = event.target.closest('[data-message-photo]');
        if (!button) return;
        lightbox.querySelector('img').src = button.dataset.messagePhoto;
        lightbox.showModal();
    });
    lightbox.querySelector('[data-close-photo]').addEventListener('click', () => lightbox.close());
    lightbox.addEventListener('click', event => { if (event.target === lightbox) lightbox.close(); });
    lightbox.addEventListener('close', () => lightbox.querySelector('img').removeAttribute('src'));
    const sendButton = form.querySelector('button[type="submit"]');
    const sendErrorBox = workspace.querySelector('[data-send-error]');
    const refreshErrorBox = workspace.querySelector('[data-refresh-error]');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const currentUserId = Number(workspace.dataset.currentUser);
    const showSendError = (message) => {
        sendErrorBox.textContent = message;
        sendErrorBox.style.display = 'block';
    };
    const clearSendError = () => {
        sendErrorBox.textContent = '';
        sendErrorBox.style.display = 'none';
    };
    const showRefreshError = (message) => {
        refreshErrorBox.textContent = message;
        refreshErrorBox.style.display = 'block';
    };
    const clearRefreshError = () => {
        refreshErrorBox.textContent = '';
        refreshErrorBox.style.display = 'none';
    };
    const readJson = async (response, failureMessage) => {
        if (response.redirected || !response.headers.get('content-type')?.includes('application/json')) {
            throw new Error(failureMessage);
        }

        try {
            return await response.json();
        } catch {
            throw new Error(failureMessage);
        }
    };
    const sendFailureMessage = (response, result) => {
        if (response.status === 401 || response.status === 403 || response.status === 404) {
            return 'You no longer have access to this conversation.';
        }
        if (response.status === 419) return 'Your session expired. Refresh the page to continue.';
        if (response.status === 422) {
            return result.errors?.photos?.[0] || result.errors?.photo?.[0] || result.errors?.body?.[0] || result.message || 'Please enter a valid message.';
        }
        return 'Message couldn’t be sent. Please try again.';
    };
    const nearBottom = () => scrollArea.scrollHeight - scrollArea.scrollTop - scrollArea.clientHeight < 140;
    const scrollToBottom = () => { scrollArea.scrollTop = scrollArea.scrollHeight; };

    const appendMessage = (message, prepend = false) => {
        const stickToBottom = !prepend && nearBottom();
        if (workspace.querySelector(`[data-message-id="${message.id}"]`)) return;
        const line = document.createElement('article');
        line.className = `message-line${Number(message.sender_id) === currentUserId ? ' mine' : ''}`;
        line.dataset.chatMessage = '';
        line.dataset.messageId = String(message.id);
        const attachments = message.attachments || [];
        const bubble = document.createElement('div');
        bubble.className = `message-bubble${attachments.length ? ' has-photos' : ''}`;

        if (message.context) {
            const context = message.context;
            const card = context.url ? document.createElement('a') : document.createElement('div');
            card.className = 'message-context';
            if (context.url) card.href = context.url;
            if (context.image) {
                const image = document.createElement('img');
                image.className = 'message-context-image';
                image.src = context.image;
                image.alt = '';
                card.append(image);
            }
            const detail = document.createElement('span');
            const title = document.createElement('strong');
            title.textContent = `${context.type === 'product' ? 'Product' : 'Order'}: ${context.title}`;
            detail.append(title);
            const subtitle = document.createElement('small');
            subtitle.textContent = context.type === 'product'
                ? (context.price ? `₱${context.price} · View Product` : 'Product unavailable')
                : (context.status ? `${context.status} · View Order` : 'Order unavailable');
            detail.append(subtitle);
            card.append(detail);
            bubble.append(card);
        }

        let gallery = null;
        if (attachments.length) {
            gallery = document.createElement('div');
            gallery.className = `message-photo-gallery${attachments.length === 1 ? ' single' : ''}`;
        }
        for (const attachment of attachments) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'message-photo';
            button.dataset.messagePhoto = attachment.url;
            button.setAttribute('aria-label', 'Open message photo');
            const image = document.createElement('img');
            image.alt = 'Photo attachment';
            image.src = attachment.url;
            image.decoding = 'async';
            image.addEventListener('load', () => { if (stickToBottom) scrollToBottom(); });
            button.append(image);
            gallery.append(button);
        }
        if (gallery) bubble.append(gallery);
        if (message.body !== null) {
            const text = document.createElement('div');
            text.className = 'message-text';
            text.textContent = message.body;
            bubble.append(text);
        }
        const time = document.createElement('time');
        time.className = 'message-time';
        time.dateTime = message.created_at;
        time.textContent = message.time;
        bubble.append(time);
        line.append(bubble);
        if (prepend) scrollArea.insertBefore(line, scrollArea.querySelector('[data-chat-message]'));
        else scrollArea.append(line);
    };

    const lastId = () => {
        const messages = scrollArea.querySelectorAll('[data-chat-message]');
        return messages.length ? Number(messages[messages.length - 1].dataset.messageId) : 0;
    };
    const firstId = () => scrollArea.querySelector('[data-chat-message]')?.dataset.messageId;

    scrollToBottom();
    let userScrolled = false;
    scrollArea.addEventListener('wheel', () => { userScrolled = true; }, {passive: true});
    scrollArea.addEventListener('touchmove', () => { userScrolled = true; }, {passive: true});
    scrollArea.querySelectorAll('.message-photo img').forEach(image => {
        image.addEventListener('load', () => { if (!userScrolled) scrollToBottom(); });
    });
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (sending) return;
        const body = input.value.trim();
        if (!body && !selectedPhotos.length) {
            showSendError('Write a message or choose a photo.');
            return;
        }
        const signature = JSON.stringify([body, selectedPhotos.map(file => photoKey(file))]);
        if (draftSignature !== null && signature !== draftSignature) tokenInput.value = crypto.randomUUID();
        draftSignature = signature;
        sending = true;
        sendButton.disabled = true;
        sendButton.textContent = 'Sending...';
        input.disabled = choosePhoto.disabled = true;
        clearSendError();
        try {
            const data = new FormData();
            data.set('body', body);
            data.set('client_token', tokenInput.value);
            selectedPhotos.forEach(file => data.append('photos[]', file, file.name));
            const response = await fetch(workspace.dataset.sendUrl, {
                method: 'POST',
                mode: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrf,
                },
                body: data,
                credentials: 'same-origin',
            });
            const result = await readJson(response, 'Message couldn’t be sent. Please try again.');
            if (!response.ok) throw new Error(sendFailureMessage(response, result));
            appendMessage(result.message);
            input.value = '';
            resetPhotos();
            tokenInput.value = crypto.randomUUID();
            draftSignature = null;
            const activePreview = document.querySelector('.message-conversation.active .message-preview');
            if (activePreview) activePreview.textContent = result.message.body || (result.message.attachments?.length > 1 ? `${result.message.attachments.length} photos` : 'Photo');
            scrollToBottom();
        } catch (error) {
            showSendError(error instanceof TypeError
                ? 'Message couldn’t be sent. Check your connection and try again.'
                : (error.message || 'Message couldn’t be sent. Please try again.'));
        } finally {
            sending = false;
            sendButton.disabled = false;
            sendButton.textContent = 'Send';
            input.disabled = choosePhoto.disabled = false;
            input.focus({preventScroll: true});
        }
    });

    const loadUpdates = async () => {
        if (document.visibilityState !== 'visible') return;
        const shouldScroll = nearBottom();
        try {
            const response = await fetch(`${workspace.dataset.updatesUrl}?after_id=${lastId()}`, {
                mode: 'same-origin',
                headers: {'Accept': 'application/json'},
                credentials: 'same-origin',
            });
            if (!response.ok) throw new Error('Messages couldn’t refresh. Retrying…');
            const result = await readJson(response, 'Messages couldn’t refresh. Retrying…');
            result.messages.forEach((message) => appendMessage(message));
            if (shouldScroll && result.messages.length) scrollToBottom();
            clearRefreshError();
        } catch (error) {
            showRefreshError(error.message || 'Messages couldn’t refresh. Retrying…');
        }
    };
    window.setInterval(loadUpdates, 4000);

    const olderButton = workspace.querySelector('[data-load-older]');
    if (olderButton) {
        olderButton.addEventListener('click', async () => {
            const beforeId = firstId();
            if (!beforeId) return;
            olderButton.disabled = true;
            const oldHeight = scrollArea.scrollHeight;
            try {
                const response = await fetch(`${workspace.dataset.olderUrl}?before_id=${beforeId}`, {
                    mode: 'same-origin',
                    headers: {'Accept': 'application/json'},
                    credentials: 'same-origin',
                });
                if (!response.ok) throw new Error('Older messages couldn’t be loaded.');
                const result = await readJson(response, 'Older messages couldn’t be loaded.');
                result.messages.slice().reverse().forEach((message) => appendMessage(message, true));
                scrollArea.scrollTop += scrollArea.scrollHeight - oldHeight;
                workspace.dataset.hasMore = result.has_more ? '1' : '0';
                if (!result.has_more) olderButton.remove();
            } catch (error) {
                showRefreshError(error.message || 'Older messages couldn’t be loaded.');
            } finally {
                if (olderButton.isConnected) olderButton.disabled = false;
            }
        });
    }
})();
