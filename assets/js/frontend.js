/**
 * Frontend JavaScript for Auto Daily Newsletter Subscription Form & Slide-in Popup
 * 100% Pure Vanilla JavaScript with Zero jQuery Dependency
 * (Compatible with LiteSpeed Cache JS Combine / Defer / Delay)
 */

(function() {
	'use strict';

	// Helper to send AJAX request safely
	function sendSubscribeRequest(form, callback) {
		var emailInput = form.querySelector('input[name="adnl_email"]');
		var nameInput  = form.querySelector('input[name="adnl_name"]');
		var email      = emailInput ? emailInput.value.trim() : '';
		var name       = nameInput ? nameInput.value.trim() : '';

		if (!email) {
			return;
		}

		var ajaxUrl   = (typeof adnl_ajax_obj !== 'undefined' && adnl_ajax_obj.ajax_url) ? adnl_ajax_obj.ajax_url : '/wp-admin/admin-ajax.php';
		var ajaxNonce = (typeof adnl_ajax_obj !== 'undefined' && adnl_ajax_obj.nonce) ? adnl_ajax_obj.nonce : '';

		var formData = new FormData();
		formData.append('action', 'adnl_subscribe');
		formData.append('nonce', ajaxNonce);
		formData.append('email', email);
		formData.append('name', name);

		if (window.fetch) {
			fetch(ajaxUrl, {
				method: 'POST',
				body: formData,
				credentials: 'same-origin'
			})
			.then(function(res) { return res.json(); })
			.then(function(data) { callback(null, data); })
			.catch(function(err) { callback(err); });
		} else {
			var xhr = new XMLHttpRequest();
			xhr.open('POST', ajaxUrl, true);
			xhr.onreadystatechange = function() {
				if (xhr.readyState === 4) {
					if (xhr.status >= 200 && xhr.status < 400) {
						try {
							var data = JSON.parse(xhr.responseText);
							callback(null, data);
						} catch (e) {
							callback(e);
						}
					} else {
						callback(new Error('Network error'));
					}
				}
			};
			xhr.send(formData);
		}
	}

	// Attach submit event to all subscribe forms on page
	function initSubscribeForms() {
		var forms = document.querySelectorAll('.adnl-subscribe-form');
		forms.forEach(function(form) {
			if (form.getAttribute('data-adnl-bound')) {
				return;
			}
			form.setAttribute('data-adnl-bound', '1');

			form.addEventListener('submit', function(e) {
				e.preventDefault();

				var btn  = form.querySelector('.adnl-submit-btn');
				var msg  = form.querySelector('.adnl-response-message');
				var spin = form.querySelector('.adnl-spinner');

				if (btn) btn.disabled = true;
				if (spin) spin.style.display = 'inline-block';
				if (msg) {
					msg.style.display = 'none';
					msg.className = 'adnl-response-message';
					msg.textContent = '';
				}

				sendSubscribeRequest(form, function(err, response) {
					if (btn) btn.disabled = false;
					if (spin) spin.style.display = 'none';

					if (!msg) {
						msg = document.createElement('div');
						msg.className = 'adnl-response-message';
						form.appendChild(msg);
					}

					msg.style.display = 'block';

					if (!err && response && response.success) {
						msg.className = 'adnl-response-message adnl-response-success';
						msg.textContent = (response.data && response.data.message) ? response.data.message : 'Thank you for subscribing!';
						
						var emailInput = form.querySelector('input[name="adnl_email"]');
						if (emailInput) emailInput.value = '';
						var nameInput = form.querySelector('input[name="adnl_name"]');
						if (nameInput) nameInput.value = '';

						// Remember subscription permanently
						try {
							localStorage.setItem('adnl_user_subscribed', '1');
							localStorage.setItem('adnl_subscribed', '1');
							sessionStorage.setItem('adnl_user_subscribed', '1');
							sessionStorage.setItem('adnl_subscribed', '1');
							var exp = new Date(Date.now() + 365 * 86400000).toUTCString();
							var sec = (location.protocol === 'https:') ? '; Secure' : '';
							document.cookie = "adnl_subscribed=1; path=/; expires=" + exp + "; max-age=" + (365 * 86400) + "; SameSite=Lax" + sec;
						} catch (e) {}

						// Auto-close popup and completely remove it from DOM
						var popupParent = form.closest('.adnl-slidein-popup');
						if (popupParent) {
							setTimeout(function() {
								popupParent.classList.remove('adnl-popup-visible');
								setTimeout(function() {
									popupParent.style.display = 'none';
									if (popupParent.parentNode) {
										popupParent.parentNode.removeChild(popupParent);
									}
								}, 450);
							}, 2000);
						}
					} else {
						var isAlreadySub = Boolean(response && response.data && (response.data.code === 'already_subscribed' || (typeof response.data.message === 'string' && response.data.message.toLowerCase().indexOf('already subscribed') !== -1)));
						var rawMsg = (response && response.data && response.data.message) ? response.data.message : 'Subscription failed. Please try again.';

						if (isAlreadySub) {
							msg.className = 'adnl-response-message adnl-response-warning';
							msg.innerHTML = '<span style="font-size:16px; margin-right:4px;">⚠️</span> <strong>Already Subscribed:</strong> ' + rawMsg.replace(/^[⚠️\s]+/, '');

							// Remember subscription permanently so popup NEVER shows again
							try {
								localStorage.setItem('adnl_user_subscribed', '1');
								localStorage.setItem('adnl_subscribed', '1');
								sessionStorage.setItem('adnl_user_subscribed', '1');
								sessionStorage.setItem('adnl_subscribed', '1');
								var exp = new Date(Date.now() + 365 * 86400000).toUTCString();
								var sec = (location.protocol === 'https:') ? '; Secure' : '';
								document.cookie = "adnl_subscribed=1; path=/; expires=" + exp + "; max-age=" + (365 * 86400) + "; SameSite=Lax" + sec;
							} catch (e) {}

							// If inside popup, auto-close and remove from DOM
							var popupParent = form.closest('.adnl-slidein-popup');
							if (popupParent) {
								setTimeout(function() {
									popupParent.classList.remove('adnl-popup-visible');
									setTimeout(function() {
										popupParent.style.display = 'none';
										if (popupParent.parentNode) {
											popupParent.parentNode.removeChild(popupParent);
										}
									}, 450);
								}, 2200);
							}
						} else {
							msg.className = 'adnl-response-message adnl-response-error';
							msg.innerHTML = '<span style="font-size:15px; margin-right:4px;">❌</span> ' + rawMsg;
						}
					}
				});
			});
		});
	}

	// Slide-in Popup Controller
	function initPopupController() {
		var popup = document.getElementById('adnl-slidein-popup');
		if (!popup) return;

		// Move directly to body to avoid clipping or theme overflow issues
		if (popup.parentNode !== document.body) {
			document.body.appendChild(popup);
		}

		var url = window.location.href;
		var isForceTest = url.indexOf('preview_popup=1') !== -1 || 
		                  url.indexOf('show_popup=1') !== -1;

		// If forced via URL query parameter, clear suppressions so tester can view immediately
		if (isForceTest) {
			try {
				localStorage.removeItem('adnl_popup_dismissed_time');
				sessionStorage.removeItem('adnl_popup_dismissed_time');
				document.cookie = "adnl_popup_dismissed=; path=/; max-age=0;";
			} catch (e) {}
		}

		// 1. If user already subscribed, NEVER show popup (completely remove it immediately)
		if (!isForceTest) {
			try {
				var isSubscribed = (
					localStorage.getItem('adnl_user_subscribed') === '1' ||
					localStorage.getItem('adnl_subscribed') === '1' ||
					sessionStorage.getItem('adnl_user_subscribed') === '1' ||
					sessionStorage.getItem('adnl_subscribed') === '1' ||
					document.cookie.indexOf('adnl_subscribed=1') !== -1
				);
				if (isSubscribed) {
					popup.style.display = 'none';
					if (popup.parentNode) popup.parentNode.removeChild(popup);
					return;
				}
			} catch (e) {}
		}

		// 2. Frequency / Re-appearance cooldown check
		var frequencyMin = parseInt(popup.getAttribute('data-frequency'), 10);
		if (isNaN(frequencyMin) || frequencyMin < 0) frequencyMin = 30;

		// If frequency > 0 (not "Every Page Load"), strictly enforce dismissal cooldown
		if (frequencyMin > 0 && !isForceTest) {
			try {
				// Fast-path: check dismissal cookie (auto-expires with browser precision)
				if (document.cookie.indexOf('adnl_popup_dismissed=1') !== -1) {
					if (popup.parentNode) popup.parentNode.removeChild(popup);
					return;
				}

				// Check exact millisecond timestamp in localStorage
				var dismissedTime = localStorage.getItem('adnl_popup_dismissed_time') || sessionStorage.getItem('adnl_popup_dismissed_time');
				if (dismissedTime) {
					var cooldownMs = frequencyMin * 60 * 1000;
					var elapsed = Date.now() - parseInt(dismissedTime, 10);
					if (elapsed < cooldownMs) {
						// Cooldown still active - stay completely hidden
						if (popup.parentNode) popup.parentNode.removeChild(popup);
						return;
					}
				}
			} catch (e) {}
		}

		// Calculate appearance delay
		var delaySec = parseInt(popup.getAttribute('data-delay'), 10);
		if (isNaN(delaySec) || delaySec < 0) delaySec = 2;
		var delayMs = delaySec * 1000;

		setTimeout(function() {
			popup.style.display = 'block';
			// Small RAF tick to trigger CSS entrance transition
			requestAnimationFrame(function() {
				popup.classList.add('adnl-popup-visible');
			});
		}, delayMs);

		// Close button handler: records dismissal timestamp and sets cooldown cookie
		var closeBtn = popup.querySelector('.adnl-popup-close');
		if (closeBtn && !closeBtn.getAttribute('data-adnl-bound')) {
			closeBtn.setAttribute('data-adnl-bound', '1');
			closeBtn.addEventListener('click', function(e) {
				e.preventDefault();
				popup.classList.remove('adnl-popup-visible');
				try {
					var nowStr = Date.now().toString();
					localStorage.setItem('adnl_popup_dismissed_time', nowStr);
					sessionStorage.setItem('adnl_popup_dismissed_time', nowStr);

					var freqMin = parseInt(popup.getAttribute('data-frequency'), 10);
					if (isNaN(freqMin) || freqMin < 0) freqMin = 30;
					if (freqMin > 0) {
						var maxAgeSec = freqMin * 60;
						document.cookie = "adnl_popup_dismissed=1; path=/; max-age=" + maxAgeSec;
					}
				} catch (err) {}
				setTimeout(function() {
					popup.style.display = 'none';
				}, 450);
			});
		}
	}

	// Lightweight background cron check (bypasses LiteSpeed static cache)
	function triggerCronPing() {
		try {
			var lastPing = sessionStorage.getItem('adnl_cron_last_ping');
			var nowTs = Date.now();
			if (!lastPing || (nowTs - parseInt(lastPing, 10)) > 60000) {
				sessionStorage.setItem('adnl_cron_last_ping', nowTs.toString());
				if (typeof adnl_ajax_obj !== 'undefined' && adnl_ajax_obj.ajax_url) {
					var xhr = new XMLHttpRequest();
					xhr.open('POST', adnl_ajax_obj.ajax_url, true);
					xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
					xhr.send('action=adnl_cron_ping');
				}
			}
		} catch(e) {}
	}

	function boot() {
		initSubscribeForms();
		initPopupController();
		triggerCronPing();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}

	// Also support jQuery $(document).ready if theme relies on it
	if (window.jQuery) {
		window.jQuery(document).ready(boot);
	}
})();
