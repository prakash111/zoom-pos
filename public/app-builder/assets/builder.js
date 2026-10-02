// ZoomNearby App Builder Client Engine

function togglePlatform(platform, element) {
    const cb = document.getElementById('cb_platform_' + platform);
    if (!cb) return;

    // Toggle checked status
    cb.checked = !cb.checked;
    
    if (cb.checked) {
        element.classList.add('selected');
    } else {
        element.classList.remove('selected');
    }

    updateSelectedPlatformsSummary();
}

function selectPlatform(platform, element) {
    togglePlatform(platform, element);
}

function selectAllPlatforms(select) {
    document.querySelectorAll('.platform-card').forEach(card => {
        const plat = card.getAttribute('data-platform');
        const cb = document.getElementById('cb_platform_' + plat);
        if (cb) {
            cb.checked = select;
            if (select) {
                card.classList.add('selected');
            } else {
                card.classList.remove('selected');
            }
        }
    });
    updateSelectedPlatformsSummary();
}

function updateSelectedPlatformsSummary() {
    const checked = Array.from(document.querySelectorAll('input[name="platforms[]"]:checked')).map(el => el.value);
    const countEl = document.getElementById('selected_platforms_count');
    const submitBtn = document.getElementById('btn_submit_build');
    
    if (countEl) {
        countEl.textContent = checked.length;
    }
    if (submitBtn) {
        if (checked.length === 0) {
            submitBtn.disabled = true;
            submitBtn.style.opacity = '0.5';
            submitBtn.innerHTML = '⚠️ Please select at least one platform';
        } else {
            submitBtn.disabled = false;
            submitBtn.style.opacity = '1';
            const s = checked.length > 1 ? `(${checked.length} Platforms in Parallel)` : `(${checked[0].toUpperCase()})`;
            submitBtn.innerHTML = `🚀 Start Cloud Build Now ${s}`;
        }
    }
}

function selectSourceType(type) {
    document.querySelectorAll('.source-card').forEach(el => el.classList.remove('selected'));
    const target = document.getElementById('source-card-' + type);
    if (target) {
        target.classList.add('selected');
    }
    const input = document.getElementById('selected_source_type');
    if (input) {
        input.value = type;
    }

    const uploadArea = document.getElementById('upload-zip-container');
    if (uploadArea) {
        uploadArea.style.display = (type === 'uploaded_zip') ? 'block' : 'none';
    }
}

function updateColorPreset(hex) {
    const input = document.getElementById('primary_color_input');
    const preview = document.getElementById('primary_color_preview');
    const nativePicker = document.getElementById('primary_color_native');
    if (input) input.value = hex;
    if (preview) preview.style.backgroundColor = hex;
    if (nativePicker) nativePicker.value = hex;
}

function previewLogo(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('logo_preview_img');
            const placeholder = document.getElementById('logo_placeholder');
            if (preview) {
                preview.src = e.target.result;
                preview.style.display = 'block';
            }
            if (placeholder) {
                placeholder.style.display = 'none';
            }
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function copyToClipboard(text, btn) {
    navigator.clipboard.writeText(text).then(() => {
        const orig = btn.innerHTML;
        btn.innerHTML = '✓ Copied';
        setTimeout(() => { btn.innerHTML = orig; }, 1800);
    });
}

// Live polling for running builds
function initBuildStatusPoller(buildUid, redirectOnComplete = false) {
    const interval = setInterval(() => {
        fetch('api/build-status.php?id=' + encodeURIComponent(buildUid))
            .then(res => res.json())
            .then(data => {
                if (data && data.build) {
                    const status = data.build.status;
                    const statusBadge = document.getElementById('build-status-badge');
                    if (statusBadge) {
                        statusBadge.className = 'status-pill ' + status;
                        statusBadge.innerText = '● ' + status.charAt(0).toUpperCase() + status.slice(1);
                    }

                    const progressText = document.getElementById('build-step-text');
                    if (progressText && data.step_description) {
                        progressText.innerText = data.step_description;
                    }

                    if (status === 'completed') {
                        clearInterval(interval);
                        if (redirectOnComplete) {
                            window.location.reload();
                        } else {
                            const dlContainer = document.getElementById('download-action-container');
                            if (dlContainer) {
                                dlContainer.style.display = 'block';
                            }
                        }
                    } else if (status === 'failed' || status === 'cancelled') {
                        clearInterval(interval);
                        const errBox = document.getElementById('build-error-box');
                        if (errBox) {
                            errBox.innerText = data.build.error_message || 'Compilation failed in cloud build runner.';
                            errBox.style.display = 'block';
                        }
                    }
                }
            })
            .catch(err => console.error('Poller error:', err));
    }, 4000);
}
