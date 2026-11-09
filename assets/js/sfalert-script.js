jQuery(document).ready(function($) {
    if (typeof sfalert_ajax_object === 'undefined') {
        console.error('SFALERT Error: sfalert_ajax_object is not defined.');
        return;
    }

    const ajaxUrl = sfalert_ajax_object.ajax_url;
    const nonce = sfalert_ajax_object.nonce;
    const settings = sfalert_ajax_object.settings || {};
    const i18n = sfalert_ajax_object.i18n || {};
    const container = $('#sfalert-popup-container');
    if (!container.length) { return; }

    const animationDuration = 400;
    const initialDelay = 2500;
    let schedulerState = 'stopped';
    let requestTimer = null;
    let request = null;
    let popup = null;
    let popupSequence = 0;

    function clearRequestTimer() {
        if (requestTimer !== null) {
            clearTimeout(requestTimer);
            requestTimer = null;
        }
    }

    function removePopup(entry, callback) {
        if (!entry || entry.state === 'removed' || entry.state === 'exiting') {
            if (entry && callback) { entry.onRemoved = callback; }
            else if (!entry && callback) { callback(); }
            return;
        }

        entry.state = 'exiting';
        entry.onRemoved = callback || null;
        clearTimeout(entry.showTimer);
        clearTimeout(entry.dismissTimer);
        entry.element.removeClass('sfalert-show');

        entry.removeTimer = setTimeout(function() {
            entry.element.remove();
            entry.state = 'removed';
            if (popup === entry) { popup = null; }
            const onRemoved = entry.onRemoved;
            entry.onRemoved = null;
            if (onRemoved) { onRemoved(); }
        }, animationDuration + 50);
    }

    function renderPopup(data) {
        let messageHtml = data.message || '';
        const timeMatch = messageHtml.match(/(\d+\s+\S+\s+ago)/i);
        if (timeMatch && timeMatch[0]) {
            messageHtml = messageHtml.replace(timeMatch[0], `<span class="sfalert-time-ago">${timeMatch[0]}</span>`);
        }

        const popupInnerHtml = `
            ${settings.show_image && data.image_url ? `<div class="sfalert-popup-image"><img src="${data.image_url}" alt="${data.product_name || ''}" loading="lazy"></div>` : ''}
            <div class="sfalert-popup-content"><p class="sfalert-message">${messageHtml}</p></div>
            <button class="sfalert-close-btn" aria-label="${i18n.closeButton || 'Close Notification'}" title="${i18n.closeTitle || 'Close'}">×</button>
        `;
        const popupOuterHtml = settings.link_to_product && data.product_url
            ? `<div class="sfalert-popup"><a href="${data.product_url}" target="_blank" rel="noopener noreferrer" class="sfalert-link-wrapper">${popupInnerHtml}</a></div>`
            : `<div class="sfalert-popup">${popupInnerHtml}</div>`;
        const entry = {
            id: ++popupSequence,
            element: $(popupOuterHtml),
            state: 'entering',
            showTimer: null,
            dismissTimer: null,
            removeTimer: null,
            onRemoved: null
        };

        popup = entry;
        entry.element.find('.sfalert-close-btn').on('click', function(event) {
            event.preventDefault();
            event.stopPropagation();
            if (popup === entry) { removePopup(entry); }
        });
        container.append(entry.element);

        requestAnimationFrame(function() {
            entry.showTimer = setTimeout(function() {
                if (popup !== entry || entry.state !== 'entering' || schedulerState !== 'running') { return; }
                entry.state = 'visible';
                entry.element.addClass('sfalert-show');
            }, 20);
        });

        const displayDuration = Math.max(1, parseInt(settings.display_duration, 10) || 5000);
        entry.dismissTimer = setTimeout(function() {
            if (popup === entry && entry.state === 'visible') { removePopup(entry); }
        }, displayDuration);
    }

    function fetchNotification() {
        if (schedulerState !== 'running' || request) { return; }

        const activeRequest = $.ajax({
            url: ajaxUrl,
            method: 'POST',
            data: { action: 'sfalert_get_notification_data', nonce: nonce }
        });
        request = activeRequest;

        activeRequest.done(function(response) {
            if (schedulerState !== 'running' || request !== activeRequest) { return; }
            if (response && response.success && response.data) {
                const nextData = response.data;
                if (popup && popup.state !== 'removed') {
                    const previousPopup = popup;
                    removePopup(previousPopup, function() {
                        if (schedulerState === 'running' && popup === null) { renderPopup(nextData); }
                    });
                } else {
                    renderPopup(nextData);
                }
            } else {
                const message = response && response.data && response.data.message ? response.data.message : 'Unknown error';
                console.error('SFALERT Error:', message);
                if (response && response.data && response.data.code === 'plugin_inactive') {
                    stopScheduler();
                }
            }
        }).fail(function(xhr, status, error) {
            if (status !== 'abort' && schedulerState === 'running' && request === activeRequest) {
                console.error('SFALERT AJAX Error:', status, error);
            }
        }).always(function() {
            if (request === activeRequest) {
                request = null;
            }
            if (schedulerState === 'running' && request === null) {
                const interval = Math.max(1000, parseInt(settings.interval_time, 10) || 10000);
                requestTimer = setTimeout(fetchNotification, interval);
            }
        });
    }

    function stopScheduler() {
        if (schedulerState === 'stopped') { return; }
        schedulerState = 'stopped';
        clearRequestTimer();
        if (request) {
            const activeRequest = request;
            request = null;
            activeRequest.abort();
        }
        if (popup) { removePopup(popup); }
    }

    function startScheduler() {
        if (schedulerState === 'running' || document.hidden) { return; }
        schedulerState = 'running';
        clearRequestTimer();
        requestTimer = setTimeout(fetchNotification, initialDelay);
    }

    document.addEventListener('visibilitychange', function() {
        if (document.hidden) { stopScheduler(); }
        else { startScheduler(); }
    });

    startScheduler();
});
