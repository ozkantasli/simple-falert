jQuery(document).ready(function($) {
    // console.log("--- SFALERT DEBUG START ---");
    // if (typeof sfalert_ajax_object !== 'undefined') { console.log("SFALERT Settings Received:", sfalert_ajax_object.settings); }

    if (typeof sfalert_ajax_object === 'undefined') {
        console.error('SFALERT Error: sfalert_ajax_object is not defined.');
        return;
     }
    const ajax_url = sfalert_ajax_object.ajax_url;
    const nonce = sfalert_ajax_object.nonce;
    const settings = sfalert_ajax_object.settings;
    const i18n = sfalert_ajax_object.i18n;
    const container = $('#sfalert-popup-container');

    if (!container.length) { return; }

    let intervalId = null;
    let currentPopup = null;
    let initialTimeout = null;
    // Animation duration fixed based on CSS
    const animDuration = 400; // Should match --sf-anim-duration in CSS (0.4s)

    function fetchAndShowNotification() {
        $.post(ajax_url, {
            action: 'sfalert_get_notification_data',
            nonce: nonce
        }).done(function(response) {
             if (response.success) {
                const data = response.data;
                if (currentPopup) {
                    hideAndRemovePopup(currentPopup, true, function() {
                         setTimeout(function() { displayPopup(data); }, 100);
                    });
                } else {
                    displayPopup(data);
                }
            } else { console.error('SFALERT Error:', response.data.message || 'Unknown error'); }
        }).fail(function(xhr, status, error) { console.error('SFALERT AJAX Error:', status, error); });
    }

    function displayPopup(data) {
        let messageHtml = data.message || '';
        try {
             const timeRegex = /(\d+\s+\S+\s+ago)/i;
             const timeMatch = messageHtml.match(timeRegex);
             if (timeMatch && timeMatch[0]) { messageHtml = messageHtml.replace(timeMatch[0], `<span class="sfalert-time-ago">${timeMatch[0]}</span>`); }
        } catch (e) { console.error("SFALERT: Error processing time_ago", e); }

        let popupInnerHtml = `
            ${settings.show_image && data.image_url ? `<div class="sfalert-popup-image"><img src="${data.image_url}" alt="${data.product_name || ''}" loading="lazy"></div>` : ''}
            <div class="sfalert-popup-content"><p class="sfalert-message">${messageHtml}</p></div>
            <button class="sfalert-close-btn" aria-label="${i18n.closeButton || 'Close Notification'}" title="${i18n.closeTitle || 'Close'}">×</button>
        `;
        let popupOuterHtml = settings.link_to_product && data.product_url ? `<div class="sfalert-popup"><a href="${data.product_url}" target="_blank" rel="noopener noreferrer" class="sfalert-link-wrapper">${popupInnerHtml}</a></div>` : `<div class="sfalert-popup">${popupInnerHtml}</div>`;

        const $popup = $(popupOuterHtml);
        currentPopup = $popup;

        // REMOVED specific animation class logic
        // $popup.css('transition-duration', ...); // Use CSS variable
        // $popup.addClass(animInClass);

        $popup.find('.sfalert-close-btn').on('click', function(e) {
             e.preventDefault(); e.stopPropagation();
             hideAndRemovePopup($popup);
        });

        container.append($popup);

        // Trigger entrance by adding .sfalert-show
        requestAnimationFrame(function() {
            setTimeout(function() {
                if ($popup && currentPopup && $popup.is(currentPopup)) {
                     $popup.addClass('sfalert-show');
                }
            }, 20);
        });

        const displayDuration = parseInt(settings.display_duration) || 5000;
        setTimeout(function() {
            if (currentPopup && currentPopup.is($popup)) {
                 hideAndRemovePopup($popup);
            }
        }, displayDuration);
    }

    function hideAndRemovePopup($popupElement, isReplacing = false, callback = null) {
        if (!$popupElement || !$popupElement.length) { if (callback) callback(); return; }

        // REMOVED specific exit animation class logic
        // const currentAnimInClass = ...;
        // const animOutClass = ...;

        // Trigger exit by removing .sfalert-show
        $popupElement.removeClass('sfalert-show');

        // REMOVED Adding specific exit class

        if (currentPopup && currentPopup.is($popupElement)) { currentPopup = null; }

        // Remove after fixed CSS transition duration
        setTimeout(function() {
            $popupElement.remove();
             if (callback) { callback(); }
        }, animDuration + 50);
    }

    function startInterval() {
        clearInterval(intervalId); clearTimeout(initialTimeout);
         const initialDelay = 2500;
         initialTimeout = setTimeout(() => {
             fetchAndShowNotification();
             const regularInterval = Math.max(1000, settings.interval_time || 10000);
             intervalId = setInterval(fetchAndShowNotification, regularInterval);
         }, initialDelay);
    }

    function stopInterval() {
         clearInterval(intervalId); clearTimeout(initialTimeout);
         if (currentPopup) { hideAndRemovePopup(currentPopup, false, null); currentPopup = null; }
    }

    startInterval();
    document.addEventListener("visibilitychange", function() {
        if (document.hidden) { stopInterval(); }
        else { startInterval(); }
    });

});