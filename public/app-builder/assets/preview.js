/**
 * Enhanced Real-Time White Label Preview Engine for App Builder
 * Multi-Platform Device Profiles, Orientation, Scaling & Aspect Ratio Preservation
 */

const WhitelabelPreview = {
    storageKey: 'zen_whitelabel_draft_v3',

    devices: {
        ios: {
            'iphone-17-pro': {
                name: 'iPhone 17 Pro',
                width: 393,
                height: 852,
                type: 'ios',
                notch: 'dynamic-island',
                radius: 48,
                bezel: 4,
                dpi: 460
            },
            'iphone-17-pro-max': {
                name: 'iPhone 17 Pro Max',
                width: 440,
                height: 956,
                type: 'ios',
                notch: 'dynamic-island',
                radius: 52,
                bezel: 4,
                dpi: 460
            },
            'iphone-17': {
                name: 'iPhone 17',
                width: 393,
                height: 852,
                type: 'ios',
                notch: 'dynamic-island',
                radius: 46,
                bezel: 5,
                dpi: 460
            },
            'iphone-16-pro': {
                name: 'iPhone 16 Pro',
                width: 393,
                height: 852,
                type: 'ios',
                notch: 'dynamic-island',
                radius: 48,
                bezel: 4,
                dpi: 460
            },
            'iphone-16-pro-max': {
                name: 'iPhone 16 Pro Max',
                width: 440,
                height: 956,
                type: 'ios',
                notch: 'dynamic-island',
                radius: 52,
                bezel: 4,
                dpi: 460
            },
            'iphone-16': {
                name: 'iPhone 16',
                width: 393,
                height: 852,
                type: 'ios',
                notch: 'dynamic-island',
                radius: 46,
                bezel: 5,
                dpi: 460
            },
            'iphone-se': {
                name: 'iPhone SE (3rd Gen)',
                width: 375,
                height: 667,
                type: 'ios',
                notch: 'se-bezel',
                radius: 28,
                bezel: 10,
                dpi: 326
            }
        },
        android: {
            'pixel-10': {
                name: 'Google Pixel 10',
                width: 412,
                height: 915,
                type: 'android',
                notch: 'punch-hole-center',
                radius: 36,
                bezel: 4,
                dpi: 440
            },
            'pixel-10-pro': {
                name: 'Google Pixel 10 Pro',
                width: 412,
                height: 915,
                type: 'android',
                notch: 'punch-hole-center',
                radius: 36,
                bezel: 4,
                dpi: 480
            },
            'galaxy-s25': {
                name: 'Samsung Galaxy S25',
                width: 412,
                height: 915,
                type: 'android',
                notch: 'punch-hole-center',
                radius: 38,
                bezel: 4,
                dpi: 440
            },
            'galaxy-s25-ultra': {
                name: 'Samsung Galaxy S25 Ultra',
                width: 412,
                height: 915,
                type: 'android',
                notch: 'punch-hole-center',
                radius: 16,
                bezel: 3,
                dpi: 500
            },
            'galaxy-a56': {
                name: 'Samsung Galaxy A56',
                width: 412,
                height: 892,
                type: 'android',
                notch: 'punch-hole-center',
                radius: 34,
                bezel: 5,
                dpi: 390
            },
            'oneplus-13': {
                name: 'OnePlus 13',
                width: 412,
                height: 919,
                type: 'android',
                notch: 'punch-hole-center',
                radius: 36,
                bezel: 4,
                dpi: 450
            },
            'custom-android': {
                name: 'Custom Android Device',
                width: 412,
                height: 915,
                type: 'android',
                notch: 'punch-hole-center',
                radius: 32,
                bezel: 5,
                dpi: 420,
                isCustom: true
            }
        },
        windows: {
            'win-full': { name: 'Full Desktop (1366 × 768)', width: 1100, height: 720 },
            'win-standard': { name: 'Standard Window (1024 × 700)', width: 960, height: 660 },
            'win-compact': { name: 'Compact POS (800 × 600)', width: 800, height: 600 }
        },
        web: {
            'web-desktop': { name: 'Desktop (1440 × 900)', width: 1100, height: 720 },
            'web-laptop': { name: 'Laptop (1280 × 800)', width: 960, height: 660 },
            'web-tablet': { name: 'Tablet (768 × 1024)', width: 680, height: 860 },
            'web-mobile': { name: 'Mobile (390 × 844)', width: 390, height: 780 },
            'web-custom': { name: 'Custom Dimensions', width: 960, height: 660, isCustom: true }
        }
    },

    state: {
        platform: 'android',
        selectedDevices: {
            android: 'pixel-10',
            ios: 'iphone-17-pro',
            windows: 'win-standard',
            web: 'web-desktop'
        },
        orientation: 'portrait', // 'portrait' | 'landscape'
        screen: 'live',          // 'live' | 'dashboard' | 'pos' | 'cart' | 'login' | 'splash'
        themeMode: 'light',      // 'light' | 'dark'
        zoom: 1.0,
        isFullscreen: false,

        customDimensions: {
            width: 412,
            height: 915,
            dpi: 420
        },

        // Brand Identity
        appName: 'Zoom Sales POS',
        shortName: 'ZoomPOS',
        displayName: 'Zoom Sales POS',
        companyName: 'Zoom Technologies',
        productName: 'Zoom Sales CRM',
        packageId: 'com.zoomnearby.zoompos',
        serverUrl: 'https://saas.zoomnearby.com',
        websiteUrl: 'https://saas.zoomnearby.com/pos-web/',
        supportEmail: 'support@zoomnearby.com',
        supportPhone: '+918535075196',
        copyright: 'Copyright (C) 2026 Zoom Nearby. All rights reserved.',

        // Color Palette
        primaryColor: '#4F46E5',
        secondaryColor: '#06B6D4',
        accentColor: '#10B981',
        bgColor: '#F8FAFC',
        sidebarColor: '#1E293B',
        textColor: '#0F172A',

        // Assets
        logoUrl: '',
        lightLogoUrl: '',
        darkLogoUrl: '',
        splashLogoUrl: '',
        faviconUrl: '',
        windowsIconUrl: '',
        androidIconUrl: '',
        iosIconUrl: ''
    },

    init: function() {
        this.loadDraft();
        this.bindFormEvents();
        this.syncStateFromForm();
        this.renderToolbarControls();
        this.render();
        this.validate();

        // Responsive auto-fit scaling on resize
        window.addEventListener('resize', () => {
            this.applyCalculatedScale();
        });
    },

    loadDraft: function() {
        try {
            const prefill = window.PREFILLED_BRANDING || null;
            const currentLicense = (prefill && prefill.license_key) ? prefill.license_key.toUpperCase() : '';

            // Apply prefilled branding from server into initial state
            if (prefill) {
                this.applyPrefillState(prefill);
            }

            const raw = localStorage.getItem(this.storageKey);
            if (raw) {
                const parsed = JSON.parse(raw);
                // If saved draft belongs to the same license and has user customization, allow user's in-progress edits
                if (!currentLicense || !parsed._savedLicenseKey || parsed._savedLicenseKey === currentLicense) {
                    Object.assign(this.state, parsed);
                    this.populateFormFromState();
                } else {
                    // Stored draft is from a different license/account -> discard and use current prefill
                    localStorage.removeItem(this.storageKey);
                    if (prefill) {
                        this.applyPrefillState(prefill);
                    }
                }
            }
        } catch (e) {
            console.warn('Could not load draft from localStorage:', e);
        }
    },

    applyPrefillState: function(prefill) {
        if (!prefill) return;
        if (prefill.company_name) this.state.companyName = prefill.company_name;
        if (prefill.product_name) this.state.productName = prefill.product_name;
        if (prefill.app_name) this.state.appName = prefill.app_name;
        if (prefill.short_name) this.state.shortName = prefill.short_name;
        if (prefill.display_name) this.state.displayName = prefill.display_name;
        if (prefill.package_id) this.state.packageId = prefill.package_id;
        if (prefill.server_url) this.state.serverUrl = prefill.server_url;
        if (prefill.website_url && prefill.website_url !== 'https://zoomnearby.com' && prefill.website_url !== 'https://saas.zoomnearby.com') {
            this.state.websiteUrl = prefill.website_url;
        } else {
            this.state.websiteUrl = 'https://saas.zoomnearby.com/pos-web/';
        }
        if (prefill.support_email) this.state.supportEmail = prefill.support_email;
        if (prefill.support_phone) this.state.supportPhone = prefill.support_phone;
        if (prefill.copyright) this.state.copyright = prefill.copyright;
        if (prefill.primary_color) this.state.primaryColor = prefill.primary_color;
        this.populateFormFromState();
    },

    saveDraft: function() {
        try {
            const toSave = Object.assign({}, this.state);
            toSave._savedLicenseKey = (window.PREFILLED_BRANDING && window.PREFILLED_BRANDING.license_key) ? window.PREFILLED_BRANDING.license_key.toUpperCase() : '';
            toSave._userCustomized = true;
            localStorage.setItem(this.storageKey, JSON.stringify(toSave));
        } catch (e) {
            console.warn('Draft save error:', e);
        }
    },

    resetToDefaults: function() {
        if (!confirm('Are you sure you want to reset all branding settings to defaults?')) return;
        localStorage.removeItem(this.storageKey);

        const prefill = window.PREFILLED_BRANDING || {};
        this.state.appName = prefill.app_name || 'Zoom Sales POS';
        this.state.shortName = prefill.short_name || 'ZoomPOS';
        this.state.displayName = prefill.display_name || 'Zoom Sales POS';
        this.state.companyName = prefill.company_name || 'Zoom Technologies';
        this.state.productName = prefill.product_name || 'Zoom Sales CRM';
        this.state.packageId = prefill.package_id || 'com.zoomnearby.zoompos';
        this.state.serverUrl = prefill.server_url || 'https://saas.zoomnearby.com';
        this.state.websiteUrl = (prefill.website_url && prefill.website_url !== 'https://zoomnearby.com' && prefill.website_url !== 'https://saas.zoomnearby.com') ? prefill.website_url : 'https://saas.zoomnearby.com/pos-web/';
        this.state.supportEmail = prefill.support_email || 'support@zoomnearby.com';
        this.state.supportPhone = prefill.support_phone || '+918535075196';
        this.state.copyright = prefill.copyright || ('Copyright (C) ' + new Date().getFullYear() + ' Zoom Nearby. All rights reserved.');
        this.state.primaryColor = prefill.primary_color || '#4F46E5';
        this.state.secondaryColor = '#06B6D4';
        this.state.accentColor = '#10B981';
        this.state.bgColor = '#F8FAFC';
        this.state.sidebarColor = '#1E293B';
        this.state.textColor = '#0F172A';
        this.state.orientation = 'portrait';
        this.state.zoom = 1.0;
        this.state.logoUrl = '';
        this.state.lightLogoUrl = '';
        this.state.darkLogoUrl = '';
        this.state.splashLogoUrl = '';
        this.state.faviconUrl = '';
        this.state.windowsIconUrl = '';
        this.state.androidIconUrl = '';
        this.state.iosIconUrl = '';

        this.populateFormFromState();
        this.renderToolbarControls();
        this.render();
        this.validate();
    },

    populateFormFromState: function() {
        const s = this.state;
        const setVal = (id, val) => {
            const el = document.getElementById(id);
            if (el && val !== undefined) el.value = val;
        };

        setVal('inp_app_name', s.appName);
        setVal('inp_short_name', s.shortName);
        setVal('inp_display_name', s.displayName);
        setVal('inp_company_name', s.companyName);
        setVal('inp_product_name', s.productName);
        setVal('inp_package_id', s.packageId);
        setVal('inp_server_url', s.serverUrl);
        setVal('inp_website_url', s.websiteUrl);
        setVal('inp_support_email', s.supportEmail);
        setVal('inp_support_phone', s.supportPhone);
        setVal('inp_copyright', s.copyright);

        // Colors
        setVal('inp_primary_color', s.primaryColor);
        setVal('native_primary_color', s.primaryColor);
        setVal('inp_secondary_color', s.secondaryColor);
        setVal('native_secondary_color', s.secondaryColor);
        setVal('inp_accent_color', s.accentColor);
        setVal('native_accent_color', s.accentColor);
        setVal('inp_bg_color', s.bgColor);
        setVal('native_bg_color', s.bgColor);
        setVal('inp_sidebar_color', s.sidebarColor);
        setVal('native_sidebar_color', s.sidebarColor);
        setVal('inp_text_color', s.textColor);
        setVal('native_text_color', s.textColor);
    },

    syncStateFromForm: function() {
        const getVal = (id, fallback = '') => {
            const el = document.getElementById(id);
            return el ? el.value.trim() : fallback;
        };

        this.state.appName = getVal('inp_app_name', this.state.appName);
        this.state.shortName = getVal('inp_short_name', this.state.shortName);
        this.state.displayName = getVal('inp_display_name', this.state.displayName || this.state.appName);
        this.state.companyName = getVal('inp_company_name', this.state.companyName);
        this.state.productName = getVal('inp_product_name', this.state.productName);
        this.state.packageId = getVal('inp_package_id', this.state.packageId);
        this.state.serverUrl = getVal('inp_server_url', this.state.serverUrl);
        this.state.websiteUrl = getVal('inp_website_url', this.state.websiteUrl);
        this.state.supportEmail = getVal('inp_support_email', this.state.supportEmail);
        this.state.supportPhone = getVal('inp_support_phone', this.state.supportPhone);
        this.state.copyright = getVal('inp_copyright', this.state.copyright);

        // Colors
        this.state.primaryColor = getVal('inp_primary_color', this.state.primaryColor);
        this.state.secondaryColor = getVal('inp_secondary_color', this.state.secondaryColor);
        this.state.accentColor = getVal('inp_accent_color', this.state.accentColor);
        this.state.bgColor = getVal('inp_bg_color', this.state.bgColor);
        this.state.sidebarColor = getVal('inp_sidebar_color', this.state.sidebarColor);
        this.state.textColor = getVal('inp_text_color', this.state.textColor);

        // Update live Flutter Web URL labels and iframe dynamically
        const webDisplay = document.getElementById('preview_web_url_display') || document.getElementById('preview_server_url_display');
        if (webDisplay) {
            const curUrl = this.state.websiteUrl || 'https://saas.zoomnearby.com/pos-web/';
            webDisplay.innerText = curUrl;
            webDisplay.title = curUrl;
        }
        const webFrame = document.getElementById('preview_web_frame') || document.getElementById('preview_server_frame');
        if (webFrame && (this.state.screen === 'live' || this.state.screen === 'server')) {
            const raw = this.state.websiteUrl || 'https://saas.zoomnearby.com/pos-web/';
            const norm = raw.startsWith('http') ? raw : 'https://' + raw;
            if (webFrame.src !== norm && norm.length > 8) {
                webFrame.src = norm;
            }
        }
    },

    reloadWebFrame: function() {
        const frame = document.getElementById('preview_web_frame') || document.getElementById('preview_server_frame');
        if (frame) {
            const rawUrl = this.state.websiteUrl || (document.getElementById('inp_website_url') ? document.getElementById('inp_website_url').value : '') || 'https://saas.zoomnearby.com/pos-web/';
            const webUrl = rawUrl.startsWith('http') ? rawUrl : 'https://' + rawUrl;
            frame.src = webUrl;
        }
    },

    reloadServerFrame: function() {
        this.reloadWebFrame();
    },

    bindFormEvents: function() {
        const self = this;
        const inputs = [
            'inp_app_name', 'inp_short_name', 'inp_display_name',
            'inp_company_name', 'inp_product_name', 'inp_package_id',
            'inp_server_url', 'inp_website_url', 'inp_support_email',
            'inp_support_phone', 'inp_copyright',
            'inp_primary_color', 'inp_secondary_color', 'inp_accent_color',
            'inp_bg_color', 'inp_sidebar_color', 'inp_text_color'
        ];

        inputs.forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.addEventListener('input', () => {
                    self.syncStateFromForm();
                    self.render();
                    self.validate();
                    self.saveDraft();
                });
            }
        });

        // Color pickers
        ['primary_color', 'secondary_color', 'accent_color', 'bg_color', 'sidebar_color', 'text_color'].forEach(k => {
            const nativeEl = document.getElementById('native_' + k);
            const textEl = document.getElementById('inp_' + k);
            if (nativeEl && textEl) {
                nativeEl.addEventListener('input', (e) => {
                    textEl.value = e.target.value.toUpperCase();
                    self.syncStateFromForm();
                    self.render();
                    self.validate();
                    self.saveDraft();
                });
                textEl.addEventListener('input', (e) => {
                    if (/^#[0-9A-Fa-f]{6}$/.test(e.target.value)) {
                        nativeEl.value = e.target.value;
                    }
                    self.syncStateFromForm();
                    self.render();
                    self.validate();
                    self.saveDraft();
                });
            }
        });

        // File upload bindings
        const fileBindings = [
            { inputId: 'main_logo_input', stateKey: 'logoUrl' },
            { inputId: 'file_main_logo', stateKey: 'logoUrl' },
            { inputId: 'file_app_icon', stateKey: 'androidIconUrl' },
            { inputId: 'file_android_icon', stateKey: 'androidIconUrl' },
            { inputId: 'file_favicon', stateKey: 'faviconUrl' },
            { inputId: 'file_windows_icon', stateKey: 'windowsIconUrl' },
            { inputId: 'file_ios_icon', stateKey: 'iosIconUrl' },
            { inputId: 'file_light_logo', stateKey: 'lightLogoUrl' },
            { inputId: 'file_dark_logo', stateKey: 'darkLogoUrl' },
            { inputId: 'file_splash_logo', stateKey: 'splashLogoUrl' },
            { inputId: 'file_login_logo', stateKey: 'logoUrl' }
        ];

        fileBindings.forEach(b => {
            const input = document.getElementById(b.inputId);
            if (input) {
                input.addEventListener('change', function(e) {
                    if (this.files && this.files[0]) {
                        const reader = new FileReader();
                        reader.onload = function(evt) {
                            self.state[b.stateKey] = evt.target.result;
                            if (b.stateKey === 'logoUrl') {
                                if (!self.state.androidIconUrl) self.state.androidIconUrl = evt.target.result;
                                if (!self.state.faviconUrl) self.state.faviconUrl = evt.target.result;
                                if (!self.state.windowsIconUrl) self.state.windowsIconUrl = evt.target.result;
                            }
                            self.render();
                            self.validate();
                            self.saveDraft();
                        };
                        reader.readAsDataURL(this.files[0]);
                    }
                });
            }
        });
    },

    setPlatform: function(plat) {
        this.state.platform = plat;
        document.querySelectorAll('.platform-pill-btn-item').forEach(el => {
            el.classList.toggle('active', el.getAttribute('data-platform') === plat);
        });

        this.renderToolbarControls();
        this.render();
        this.saveDraft();
    },

    setDevice: function(deviceKey) {
        this.state.selectedDevices[this.state.platform] = deviceKey;
        this.renderToolbarControls();
        this.render();
        this.saveDraft();
    },

    setOrientation: function(orient) {
        this.state.orientation = orient;
        document.querySelectorAll('.orientation-btn').forEach(el => {
            el.classList.toggle('active', el.getAttribute('data-orientation') === orient);
        });
        this.render();
        this.saveDraft();
    },

    setCustomDimensions: function(w, h, dpi) {
        if (w) this.state.customDimensions.width = Math.max(300, Math.min(2560, parseInt(w) || 412));
        if (h) this.state.customDimensions.height = Math.max(400, Math.min(2560, parseInt(h) || 915));
        if (dpi) this.state.customDimensions.dpi = Math.max(120, Math.min(800, parseInt(dpi) || 420));
        this.render();
        this.saveDraft();
    },

    setScreen: function(scr) {
        this.state.screen = scr;
        document.querySelectorAll('.screen-mode-btn').forEach(el => {
            const elScr = el.getAttribute('data-screen');
            const isActive = (elScr === scr) || (scr === 'live' && elScr === 'server') || (scr === 'server' && elScr === 'live');
            el.classList.toggle('active', isActive);
        });
        this.render();
    },

    setZoom: function(delta) {
        if (delta === 0) {
            this.state.zoom = 1.0;
        } else {
            this.state.zoom = Math.min(1.5, Math.max(0.5, Math.round((this.state.zoom + delta) * 10) / 10));
        }
        const zoomText = document.getElementById('preview_zoom_label');
        if (zoomText) zoomText.innerText = Math.round(this.state.zoom * 100) + '%';
        this.applyCalculatedScale();
        this.saveDraft();
    },

    toggleTheme: function() {
        this.state.themeMode = this.state.themeMode === 'light' ? 'dark' : 'light';
        const btn = document.getElementById('preview_theme_toggle_btn');
        if (btn) btn.innerHTML = this.state.themeMode === 'light' ? '🌙' : '☀️';
        this.render();
    },

    toggleFullscreen: function() {
        this.state.isFullscreen = !this.state.isFullscreen;
        const modal = document.getElementById('preview_fullscreen_modal');
        if (modal) {
            modal.classList.toggle('active', this.state.isFullscreen);
            if (this.state.isFullscreen) {
                document.getElementById('modal_canvas_container').appendChild(document.getElementById('preview_viewport_wrapper'));
            } else {
                document.getElementById('regular_canvas_container').appendChild(document.getElementById('preview_viewport_wrapper'));
            }
            this.applyCalculatedScale();
        }
    },

    getActiveProfile: function() {
        const p = this.state.platform;
        const key = this.state.selectedDevices[p] || Object.keys(this.devices[p])[0];
        const profile = this.devices[p][key] || Object.values(this.devices[p])[0];

        if (profile.isCustom) {
            return Object.assign({}, profile, {
                width: this.state.customDimensions.width,
                height: this.state.customDimensions.height,
                dpi: this.state.customDimensions.dpi
            });
        }
        return profile;
    },

    renderToolbarControls: function() {
        const p = this.state.platform;
        const container = document.getElementById('platform_adaptive_controls');
        if (!container) return;

        const activeKey = this.state.selectedDevices[p];
        const profileList = this.devices[p] || {};

        let html = '';

        if (p === 'android' || p === 'ios') {
            // Dropdown + Orientation
            html += `
                <div class="device-select-wrap">
                    <span class="device-select-label">${p === 'ios' ? 'Model:' : 'Device:'}</span>
                    <select class="device-dropdown" onchange="WhitelabelPreview.setDevice(this.value)">
            `;
            for (const [k, prof] of Object.entries(profileList)) {
                const sel = (k === activeKey) ? 'selected' : '';
                html += `<option value="${k}" ${sel}>${prof.name} (${prof.width} × ${prof.height})</option>`;
            }
            html += `</select></div>`;

            // Orientation buttons
            html += `
                <div class="orientation-pill-group">
                    <button type="button" class="orientation-btn ${this.state.orientation === 'portrait' ? 'active' : ''}" data-orientation="portrait" onclick="WhitelabelPreview.setOrientation('portrait')">
                        📱 Portrait
                    </button>
                    <button type="button" class="orientation-btn ${this.state.orientation === 'landscape' ? 'active' : ''}" data-orientation="landscape" onclick="WhitelabelPreview.setOrientation('landscape')">
                        🔄 Landscape
                    </button>
                </div>
            `;

            // Custom inputs if selected
            if (activeKey === 'custom-android') {
                html += `
                    <div class="custom-dim-panel">
                        <span style="font-size:10.5px;color:#94a3b8;">W:</span>
                        <input type="number" class="custom-dim-input" value="${this.state.customDimensions.width}" onchange="WhitelabelPreview.setCustomDimensions(this.value, null, null)">
                        <span style="font-size:10.5px;color:#94a3b8;">H:</span>
                        <input type="number" class="custom-dim-input" value="${this.state.customDimensions.height}" onchange="WhitelabelPreview.setCustomDimensions(null, this.value, null)">
                        <span style="font-size:10.5px;color:#94a3b8;">DPI:</span>
                        <input type="number" class="custom-dim-input" value="${this.state.customDimensions.dpi}" onchange="WhitelabelPreview.setCustomDimensions(null, null, this.value)">
                    </div>
                `;
            }
        } else if (p === 'windows') {
            // Window Size Selector
            html += `
                <div class="device-select-wrap">
                    <span class="device-select-label">Window Size:</span>
                    <select class="device-dropdown" onchange="WhitelabelPreview.setDevice(this.value)">
            `;
            for (const [k, prof] of Object.entries(profileList)) {
                const sel = (k === activeKey) ? 'selected' : '';
                html += `<option value="${k}" ${sel}>${prof.name}</option>`;
            }
            html += `</select></div>`;
        } else if (p === 'web') {
            // Viewport Presets
            html += `
                <div class="device-select-wrap">
                    <span class="device-select-label">Viewport Preset:</span>
                    <select class="device-dropdown" onchange="WhitelabelPreview.setDevice(this.value)">
            `;
            for (const [k, prof] of Object.entries(profileList)) {
                const sel = (k === activeKey) ? 'selected' : '';
                html += `<option value="${k}" ${sel}>${prof.name}</option>`;
            }
            html += `</select></div>`;

            if (activeKey === 'web-custom') {
                html += `
                    <div class="custom-dim-panel">
                        <span style="font-size:10.5px;color:#94a3b8;">W:</span>
                        <input type="number" class="custom-dim-input" value="${this.state.customDimensions.width}" onchange="WhitelabelPreview.setCustomDimensions(this.value, null, null)">
                        <span style="font-size:10.5px;color:#94a3b8;">H:</span>
                        <input type="number" class="custom-dim-input" value="${this.state.customDimensions.height}" onchange="WhitelabelPreview.setCustomDimensions(null, this.value, null)">
                    </div>
                `;
            }
        }

        container.innerHTML = html;
    },

    render: function() {
        const s = this.state;
        const profile = this.getActiveProfile();
        const frame = document.getElementById('active_device_frame');
        if (!frame) return;

        // Determine Frame Dimensions based on orientation
        let targetW, targetH;
        if (s.platform === 'android' || s.platform === 'ios') {
            if (s.orientation === 'landscape') {
                targetW = profile.height;
                targetH = profile.width;
            } else {
                targetW = profile.width;
                targetH = profile.height;
            }
        } else {
            targetW = profile.width;
            targetH = profile.height;
        }

        // Apply Frame Styles
        frame.style.width = targetW + 'px';
        frame.style.height = targetH + 'px';
        frame.style.borderRadius = (profile.radius || 12) + 'px';

        // Apply dynamic brand colors to CSS variables
        frame.style.setProperty('--brand-primary', s.primaryColor || '#4F46E5');
        frame.style.setProperty('--brand-secondary', s.secondaryColor || '#06B6D4');
        frame.style.setProperty('--brand-accent', s.accentColor || '#10B981');
        frame.style.setProperty('--brand-bg', s.bgColor || '#F8FAFC');
        frame.style.setProperty('--brand-sidebar', s.sidebarColor || '#1E293B');
        frame.style.setProperty('--brand-text', s.textColor || '#0F172A');

        // Configure Frame Classes
        frame.className = `device-chassis type-${s.platform} orientation-${s.orientation}`;

        // Top Chrome & Frame Elements
        this.renderFrameChrome(frame, profile);

        // Screen Body Content
        const screenBody = frame.querySelector('.device-screen-body');
        if (screenBody) {
            screenBody.className = `device-screen-body ${s.themeMode === 'dark' ? 'theme-dark' : ''}`;
            const existingWebFrame = screenBody.querySelector('#preview_web_frame') || screenBody.querySelector('#preview_server_frame');
            if ((s.screen === 'live' || s.screen === 'server') && existingWebFrame) {
                const webDisplay = screenBody.querySelector('#preview_web_url_display') || screenBody.querySelector('#preview_server_url_display');
                const curUrl = s.websiteUrl || 'https://saas.zoomnearby.com/pos-web/';
                if (webDisplay) {
                    webDisplay.innerText = curUrl;
                    webDisplay.title = curUrl;
                }
            } else {
                screenBody.innerHTML = this.getScreenHTML();
            }
        }

        // Apply scale fitting
        this.applyCalculatedScale();
        requestAnimationFrame(() => this.applyCalculatedScale());

        // Update artifact preview chips on new-build page
        this.updateArtifactChips();
    },

    applyCalculatedScale: function() {
        const wrapper = document.getElementById('preview_viewport_wrapper');
        const frame = document.getElementById('active_device_frame');
        if (!wrapper || !frame) return;

        const canvas = wrapper.parentElement;
        if (!canvas) return;

        const padX = 40;
        const padY = 40;
        const rawCanvasW = canvas.clientWidth || canvas.offsetWidth || 500;
        const rawCanvasH = canvas.clientHeight || canvas.offsetHeight || 760;
        const canvasW = Math.max(120, rawCanvasW - padX);
        const canvasH = Math.max(120, rawCanvasH - padY);

        const profile = this.getActiveProfile();
        let unscaledW, unscaledH;
        if (this.state.platform === 'android' || this.state.platform === 'ios') {
            if (this.state.orientation === 'landscape') {
                unscaledW = profile.height;
                unscaledH = profile.width;
            } else {
                unscaledW = profile.width;
                unscaledH = profile.height;
            }
        } else {
            unscaledW = profile.width;
            unscaledH = profile.height;
        }

        if (unscaledW <= 0 || unscaledH <= 0) return;

        const scaleX = canvasW / unscaledW;
        const scaleY = canvasH / unscaledH;
        let fitScale = Math.min(scaleX, scaleY);
        // Do not artificially scale up beyond 1.0 on gigantic screens unless user zoomed
        if (fitScale > 1.0) fitScale = 1.0;

        const userZoom = (typeof this.state.zoom === 'number') ? this.state.zoom : 1.0;
        const finalScale = Math.round(fitScale * userZoom * 1000) / 1000;

        const scaledW = Math.round(unscaledW * finalScale);
        const scaledH = Math.round(unscaledH * finalScale);

        // Explicitly set wrapper to the exact scaled bounding box
        wrapper.style.width = scaledW + 'px';
        wrapper.style.height = scaledH + 'px';
        wrapper.style.transform = 'none';

        // Anchor frame at top-left of wrapper and scale from 0 0
        frame.style.width = unscaledW + 'px';
        frame.style.height = unscaledH + 'px';
        frame.style.transform = `scale(${finalScale})`;
        frame.style.transformOrigin = '0 0';

        const zoomText = document.getElementById('preview_zoom_label');
        if (zoomText) {
            zoomText.innerText = Math.round(finalScale * 100) + '%';
        }
    },

    renderFrameChrome: function(frame, profile) {
        const s = this.state;
        const iconSrc = s.windowsIconUrl || s.faviconUrl || s.androidIconUrl || s.logoUrl || '';

        // Android Chrome
        const androidStatus = frame.querySelector('.android-status-bar');
        const androidNav = frame.querySelector('.android-nav-bar');
        if (androidStatus) androidStatus.style.display = (s.platform === 'android') ? 'flex' : 'none';
        if (androidNav) androidNav.style.display = (s.platform === 'android') ? 'flex' : 'none';

        // iOS Chrome (Dynamic Island vs SE Classic Bezel)
        const iosStatus = frame.querySelector('.ios-status-bar');
        const iosHome = frame.querySelector('.ios-home-indicator');
        const iosSeTop = frame.querySelector('.ios-se-topbar');
        const iosSeBottom = frame.querySelector('.ios-se-bottombar');

        const isIos = (s.platform === 'ios');
        const isSe = isIos && (profile.notch === 'se-bezel');

        if (iosStatus) iosStatus.style.display = (isIos && !isSe) ? 'flex' : 'none';
        if (iosHome) iosHome.style.display = (isIos && !isSe) ? 'flex' : 'none';
        if (iosSeTop) iosSeTop.style.display = (isIos && isSe) ? 'flex' : 'none';
        if (iosSeBottom) iosSeBottom.style.display = (isIos && isSe) ? 'flex' : 'none';

        // Windows Chrome
        const winTitlebar = frame.querySelector('.windows-titlebar');
        const winMenubar = frame.querySelector('.windows-menubar');
        const winStatusbar = frame.querySelector('.windows-statusbar');
        if (winTitlebar) {
            winTitlebar.style.display = (s.platform === 'windows') ? 'flex' : 'none';
            const winTitleText = frame.querySelector('.windows-title-text');
            const winIcon = frame.querySelector('.windows-titlebar-icon');
            if (winTitleText) winTitleText.innerText = `${s.displayName || s.appName} - Point of Sale (POS Desktop)`;
            if (winIcon) {
                if (iconSrc) {
                    winIcon.src = iconSrc;
                    winIcon.style.display = 'block';
                } else {
                    winIcon.style.display = 'none';
                }
            }
        }
        if (winMenubar) winMenubar.style.display = (s.platform === 'windows') ? 'flex' : 'none';
        if (winStatusbar) {
            winStatusbar.style.display = (s.platform === 'windows') ? 'flex' : 'none';
            const winStatusUrl = frame.querySelector('.windows-status-server');
            if (winStatusUrl) winStatusUrl.innerText = `Connected: ${s.websiteUrl || s.serverUrl}`;
        }

        // Web Chrome
        const webTabstrip = frame.querySelector('.web-tabstrip');
        const webOmnibox = frame.querySelector('.web-omnibox');
        if (webTabstrip) {
            webTabstrip.style.display = (s.platform === 'web') ? 'flex' : 'none';
            const webTabTitle = frame.querySelector('.web-tab-title-text');
            const webFavicon = frame.querySelector('.web-tab-favicon');
            if (webTabTitle) webTabTitle.innerText = s.displayName || s.appName;
            if (webFavicon) {
                if (iconSrc) {
                    webFavicon.src = iconSrc;
                    webFavicon.style.display = 'block';
                } else {
                    webFavicon.style.display = 'none';
                }
            }
        }
        if (webOmnibox) {
            webOmnibox.style.display = (s.platform === 'web') ? 'flex' : 'none';
            const webUrlText = frame.querySelector('.web-address-text');
            if (webUrlText) webUrlText.innerText = s.websiteUrl || `${s.serverUrl}/pos-web/`;
        }
    },

    getScreenHTML: function() {
        const s = this.state;
        const profile = this.getActiveProfile();
        let targetW = profile.width;
        if ((s.platform === 'android' || s.platform === 'ios') && s.orientation === 'landscape') {
            targetW = profile.height;
        }
        const isWide = (s.orientation === 'landscape' || s.platform === 'windows' || s.platform === 'web' || targetW >= 640);

        const logoSrc = s.logoUrl || s.androidIconUrl || s.windowsIconUrl || '';
        const logoAvatarLetter = (s.shortName || s.appName || 'Z').charAt(0).toUpperCase();
        const primaryCol = s.primaryColor || '#10b981';
        const secCol = s.secondaryColor || '#0ea5e9';
        const brandTitle = s.displayName || s.appName || 'Zoom POS';
        const company = s.companyName || 'ZoomNearby Enterprise';

        // -------------------------------------------------------------
        // SCREEN 0: LIVE FLUTTER WEB APP SIMULATOR (Loads Flutter Web URL)
        // -------------------------------------------------------------
        if (s.screen === 'live' || s.screen === 'server') {
            const rawUrl = s.websiteUrl || (document.getElementById('inp_website_url') ? document.getElementById('inp_website_url').value : '') || 'https://saas.zoomnearby.com/pos-web/';
            const webUrl = rawUrl.startsWith('http') ? rawUrl : 'https://' + rawUrl;
            return `
                <div class="live-server-preview-container" style="width:100%;height:100%;min-height:100%;display:flex;flex-direction:column;background:#ffffff;position:relative;overflow:hidden;flex:1;">
                    <!-- Mini In-App Header Bar -->
                    <div class="live-server-app-bar" style="height:36px;background:#ffffff;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between;padding:0 12px;font-size:11px;color:#64748b;flex-shrink:0;z-index:10;box-shadow:0 1px 2px rgba(0,0,0,0.03);">
                        <div style="display:flex;align-items:center;gap:6px;min-width:0;flex:1;">
                            <span style="font-size:11px;">🔒</span>
                            <span id="preview_web_url_display" style="font-family:monospace;font-size:10.5px;color:#334155;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="${webUrl}">${webUrl}</span>
                        </div>
                        <div style="display:flex;align-items:center;gap:6px;flex-shrink:0;">
                            <button type="button" onclick="WhitelabelPreview.reloadWebFrame()" title="Reload Web App" style="border:none;background:#f1f5f9;color:#475569;width:24px;height:24px;border-radius:6px;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:12px;transition:background 0.15s;">↻</button>
                            <a href="${webUrl}" target="_blank" title="Open Web App in New Tab" style="border:none;background:#f1f5f9;color:#475569;width:24px;height:24px;border-radius:6px;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:11px;text-decoration:none;transition:background 0.15s;">↗</a>
                        </div>
                    </div>
                    <!-- Live Web Iframe -->
                    <div style="flex:1;height:calc(100% - 36px);position:relative;overflow:hidden;background:#f8fafc;">
                        <iframe id="preview_web_frame" src="${webUrl}" style="width:100%;height:100%;border:none;background:#ffffff;" allow="fullscreen; camera; geolocation; microphone; clipboard-read; clipboard-write" loading="lazy"></iframe>
                    </div>
                </div>
            `;
        }

        // -------------------------------------------------------------
        // SCREEN 1: REAL METRO DASHBOARD (Screenshots 1 & 2 in /read)
        // -------------------------------------------------------------
        if (s.screen === 'dashboard') {
            return `
                <div class="real-dashboard-layout">
                    <!-- Top Navigation Bar -->
                    <div class="real-dash-topbar">
                        <div style="display:flex;align-items:center;gap:8px;overflow:hidden;">
                            <span class="real-menu-btn" title="Navigation Drawer">☰</span>
                            <div class="real-brand-badge-box" style="background:${primaryCol};">
                                ${logoSrc ? `<img src="${logoSrc}" class="real-brand-logo-img">` : `<span>${logoAvatarLetter}</span>`}
                            </div>
                            <div class="real-store-dropdown" title="Switch Store">
                                <span class="real-store-name">${brandTitle} Demo</span>
                                <span style="font-size:10px;opacity:0.7;">▾</span>
                            </div>
                        </div>
                        <div class="real-dash-actions">
                            <span title="Sync Offline State" style="cursor:pointer;">🔄</span>
                            <span title="Toggle Dark/Light" style="cursor:pointer;" onclick="WhitelabelPreview.toggleTheme()">🌙</span>
                            <span title="Reload" style="cursor:pointer;" onclick="WhitelabelPreview.render()">↻</span>
                            <span title="Alerts" style="position:relative;cursor:pointer;">
                                🔔<span class="real-notif-dot"></span>
                            </span>
                            <div class="real-user-avatar" style="background:${secCol};" title="Signed in as Admin">
                                ${logoAvatarLetter}E
                            </div>
                        </div>
                    </div>

                    <!-- Dashboard Body -->
                    <div class="real-dash-body-wrap ${isWide ? 'with-sidebar' : ''}">
                        ${isWide ? `
                        <!-- Left Navigation Sidebar -->
                        <div class="real-sidebar">
                            <div class="real-sidebar-item active" style="color:${primaryCol};background:rgba(16,185,129,0.12);">
                                <span>🏠</span> <span>Home</span>
                            </div>
                            <div class="real-sidebar-section">RETAIL & CASHIER</div>
                            <div class="real-sidebar-item" onclick="WhitelabelPreview.setScreen('pos')" style="cursor:pointer;"><span>📠</span> <span>Point of Sale</span></div>
                            <div class="real-sidebar-item"><span>🧾</span> <span>Sales & Invoices</span></div>
                            <div class="real-sidebar-item"><span>📄</span> <span>Quotations & Proposals</span></div>
                            <div class="real-sidebar-item"><span>🚚</span> <span>Consignments</span></div>
                            <div class="real-sidebar-item"><span>👥</span> <span>Customers & CRM</span></div>
                            
                            <div class="real-sidebar-section">PRODUCTS & INVENTORY</div>
                            <div class="real-sidebar-item"><span>📦</span> <span>Product Catalog</span></div>
                            <div class="real-sidebar-item"><span>🏷️</span> <span>Categories</span></div>
                            <div class="real-sidebar-item"><span>✨</span> <span>Brands & Manufacturers</span></div>
                            <div class="real-sidebar-item"><span>📏</span> <span>Units of Measure</span></div>
                            <div class="real-sidebar-item"><span>🚛</span> <span>Suppliers & Vendors</span></div>
                            <div class="real-sidebar-item"><span>％</span> <span>Taxes & Compliance</span></div>
                            
                            <div class="real-sidebar-section">FINANCE & TARGETS</div>
                            <div class="real-sidebar-item"><span>🔔</span> <span>Accounts Receivable</span></div>

                            <div class="real-sidebar-footer">
                                <div style="font-size:9.5px;color:#94a3b8;">Help & Support</div>
                                <div style="font-size:10.5px;font-weight:700;color:${primaryCol};white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                    ${s.supportEmail || '+918535075196'}
                                </div>
                            </div>
                        </div>
                        ` : ''}

                        <div class="real-dash-content">
                            <!-- Greeting Banner -->
                            <div class="real-greeting-banner">
                                <div>
                                    <h3 class="real-greeting-title">Good Evening, ${brandTitle} Admin!</h3>
                                    <p class="real-greeting-sub">Here's what's happening at your store today.</p>
                                </div>
                                <div class="real-greeting-pills">
                                    <span class="real-info-pill">📅 26 Sep 2026</span>
                                    <span class="real-info-pill">☀️ 28°C Sunny</span>
                                </div>
                            </div>

                            <!-- 4 KPI Cards -->
                            <div class="real-kpi-grid">
                                <!-- Total Sales -->
                                <div class="real-kpi-card">
                                    <div class="real-kpi-header">
                                        <span class="real-kpi-icon-box sales" style="color:${primaryCol};">💵</span>
                                        <span class="real-kpi-badge green">+714.2%</span>
                                    </div>
                                    <div class="real-kpi-value">₹7,591.38</div>
                                    <div class="real-kpi-label">Total Sales</div>
                                    <svg class="real-kpi-sparkline" viewBox="0 0 120 28" preserveAspectRatio="none">
                                        <defs>
                                            <linearGradient id="gradSales_${brandTitle.replace(/[^a-zA-Z]/g, '')}" x1="0" y1="0" x2="0" y2="1">
                                                <stop offset="0%" stop-color="${primaryCol}" stop-opacity="0.45"/>
                                                <stop offset="100%" stop-color="${primaryCol}" stop-opacity="0.0"/>
                                            </linearGradient>
                                        </defs>
                                        <path d="M 0 24 Q 25 23 45 22 T 70 20 T 80 6 T 90 22 T 120 23 L 120 28 L 0 28 Z" fill="url(#gradSales_${brandTitle.replace(/[^a-zA-Z]/g, '')})"/>
                                        <path d="M 0 24 Q 25 23 45 22 T 70 20 T 80 6 T 90 22 T 120 23" fill="none" stroke="${primaryCol}" stroke-width="2"/>
                                    </svg>
                                </div>

                                <!-- Total Orders -->
                                <div class="real-kpi-card">
                                    <div class="real-kpi-header">
                                        <span class="real-kpi-icon-box orders">🛍️</span>
                                        <span class="real-kpi-badge teal">+968.8%</span>
                                    </div>
                                    <div class="real-kpi-value">171</div>
                                    <div class="real-kpi-label">Total Orders</div>
                                    <svg class="real-kpi-sparkline" viewBox="0 0 120 28" preserveAspectRatio="none">
                                        <defs>
                                            <linearGradient id="gradOrders_${brandTitle.replace(/[^a-zA-Z]/g, '')}" x1="0" y1="0" x2="0" y2="1">
                                                <stop offset="0%" stop-color="#0284c7" stop-opacity="0.45"/>
                                                <stop offset="100%" stop-color="#0284c7" stop-opacity="0.0"/>
                                            </linearGradient>
                                        </defs>
                                        <path d="M 0 24 Q 35 22 65 18 T 100 12 T 120 8 L 120 28 L 0 28 Z" fill="url(#gradOrders_${brandTitle.replace(/[^a-zA-Z]/g, '')})"/>
                                        <path d="M 0 24 Q 35 22 65 18 T 100 12 T 120 8" fill="none" stroke="#38bdf8" stroke-width="2"/>
                                    </svg>
                                </div>

                                <!-- Total Customers -->
                                <div class="real-kpi-card">
                                    <div class="real-kpi-header">
                                        <span class="real-kpi-icon-box customers">👥</span>
                                        <span class="real-kpi-badge purple">+12.0%</span>
                                    </div>
                                    <div class="real-kpi-value">13</div>
                                    <div class="real-kpi-label">Total Customers</div>
                                    <svg class="real-kpi-sparkline" viewBox="0 0 120 28" preserveAspectRatio="none">
                                        <defs>
                                            <linearGradient id="gradCust_${brandTitle.replace(/[^a-zA-Z]/g, '')}" x1="0" y1="0" x2="0" y2="1">
                                                <stop offset="0%" stop-color="#8b5cf6" stop-opacity="0.45"/>
                                                <stop offset="100%" stop-color="#8b5cf6" stop-opacity="0.0"/>
                                            </linearGradient>
                                        </defs>
                                        <path d="M 0 24 Q 40 22 75 16 T 105 14 T 120 24 L 120 28 L 0 28 Z" fill="url(#gradCust_${brandTitle.replace(/[^a-zA-Z]/g, '')})"/>
                                        <path d="M 0 24 Q 40 22 75 16 T 105 14 T 120 24" fill="none" stroke="#a78bfa" stroke-width="2"/>
                                    </svg>
                                </div>

                                <!-- Low Stock Items -->
                                <div class="real-kpi-card">
                                    <div class="real-kpi-header">
                                        <span class="real-kpi-icon-box stock">📦</span>
                                        <span class="real-kpi-badge amber">↓ 4</span>
                                    </div>
                                    <div class="real-kpi-value">4</div>
                                    <div class="real-kpi-label">Low Stock Items</div>
                                    <svg class="real-kpi-sparkline" viewBox="0 0 120 28" preserveAspectRatio="none">
                                        <defs>
                                            <linearGradient id="gradStock_${brandTitle.replace(/[^a-zA-Z]/g, '')}" x1="0" y1="0" x2="0" y2="1">
                                                <stop offset="0%" stop-color="#f59e0b" stop-opacity="0.45"/>
                                                <stop offset="100%" stop-color="#f59e0b" stop-opacity="0.0"/>
                                            </linearGradient>
                                        </defs>
                                        <path d="M 0 24 Q 30 25 50 18 T 80 10 T 100 22 T 120 24 L 120 28 L 0 28 Z" fill="url(#gradStock_${brandTitle.replace(/[^a-zA-Z]/g, '')})"/>
                                        <path d="M 0 24 Q 30 25 50 18 T 80 10 T 100 22 T 120 24" fill="none" stroke="#fbbf24" stroke-width="2"/>
                                    </svg>
                                </div>
                            </div>

                            <!-- Mid Section: Sales Overview & Receivable -->
                            <div class="real-mid-grid">
                                <!-- Sales Overview Card -->
                                <div class="real-sales-chart-card">
                                    <div class="real-chart-header">
                                        <div>
                                            <div class="real-chart-title">Sales Overview</div>
                                            <div class="real-chart-sub">Revenue trend with area gradient</div>
                                        </div>
                                        <div class="real-chart-pills">
                                            <button type="button" class="real-chart-pill active" style="background:${primaryCol};color:#fff;">Last 7 Days</button>
                                            <button type="button" class="real-chart-pill">This Month</button>
                                            <button type="button" class="real-chart-pill">Quarter</button>
                                        </div>
                                    </div>

                                    <div class="real-chart-area">
                                        <svg viewBox="0 0 420 160" class="real-main-chart-svg" preserveAspectRatio="none">
                                            <defs>
                                                <linearGradient id="mainAreaGrad_${brandTitle.replace(/[^a-zA-Z]/g, '')}" x1="0" y1="0" x2="0" y2="1">
                                                    <stop offset="0%" stop-color="${primaryCol}" stop-opacity="0.45"/>
                                                    <stop offset="70%" stop-color="${primaryCol}" stop-opacity="0.08"/>
                                                    <stop offset="100%" stop-color="${primaryCol}" stop-opacity="0.0"/>
                                                </linearGradient>
                                            </defs>
                                            <!-- Grid Lines -->
                                            <line x1="25" y1="25" x2="410" y2="25" stroke="#1e293b" stroke-dasharray="3 3" stroke-width="1"/>
                                            <line x1="25" y1="65" x2="410" y2="65" stroke="#1e293b" stroke-dasharray="3 3" stroke-width="1"/>
                                            <line x1="25" y1="105" x2="410" y2="105" stroke="#1e293b" stroke-dasharray="3 3" stroke-width="1"/>
                                            <line x1="25" y1="140" x2="410" y2="140" stroke="#334155" stroke-width="1"/>

                                            <!-- Y-Labels -->
                                            <text x="20" y="28" fill="#64748b" font-size="8" text-anchor="end">3.2K</text>
                                            <text x="20" y="68" fill="#64748b" font-size="8" text-anchor="end">2K</text>
                                            <text x="20" y="108" fill="#64748b" font-size="8" text-anchor="end">1K</text>
                                            <text x="20" y="143" fill="#64748b" font-size="8" text-anchor="end">0</text>

                                            <!-- Smooth Curve with sharp peak on day 16 (matching screenshot) -->
                                            <path d="M 25 138 C 55 137, 75 136, 105 135 C 135 134, 160 133, 185 125 C 200 115, 215 90, 225 25 C 235 95, 245 138, 260 138 C 290 137, 330 138, 370 137 C 390 138, 405 138, 410 138 L 410 140 L 25 140 Z" fill="url(#mainAreaGrad_${brandTitle.replace(/[^a-zA-Z]/g, '')})"/>
                                            <path d="M 25 138 C 55 137, 75 136, 105 135 C 135 134, 160 133, 185 125 C 200 115, 215 90, 225 25 C 235 95, 245 138, 260 138 C 290 137, 330 138, 370 137 C 390 138, 405 138, 410 138" fill="none" stroke="${primaryCol}" stroke-width="2.5" stroke-linecap="round"/>
                                            <circle cx="225" cy="25" r="4.5" fill="#ffffff" stroke="${primaryCol}" stroke-width="3"/>
                                        </svg>
                                        <div class="real-chart-x-labels">
                                            <span>Tue</span><span>Fri</span><span>Mon</span><span>Thu</span><span>Sun</span>
                                            <span style="font-weight:800;color:${primaryCol};">Wed</span>
                                            <span>Sat</span><span>Tue</span><span>Fri</span><span>Mon</span><span>Wed</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Amount Receivable Card -->
                                <div class="real-receivable-card">
                                    <div style="display:flex;align-items:center;justify-content:space-between;">
                                        <div style="font-weight:700;font-size:12.5px;color:#f8fafc;">Amount Receivable</div>
                                        <span class="real-kpi-badge red">5 Invoices</span>
                                    </div>
                                    <div class="real-rec-amount">₹542.09</div>
                                    <div style="font-size:10px;color:#64748b;margin-bottom:12px;">Total outstanding pending customer payment</div>

                                    <div class="real-rec-item">
                                        <div style="display:flex;align-items:center;gap:6px;">
                                            <span style="color:#ef4444;">⚠️</span>
                                            <span>Overdue Amount</span>
                                        </div>
                                        <span style="font-weight:700;color:#f8fafc;">₹216.84 ›</span>
                                    </div>
                                    <div class="real-rec-item">
                                        <div style="display:flex;align-items:center;gap:6px;">
                                            <span style="color:#f59e0b;">🕒</span>
                                            <span>Due Today</span>
                                        </div>
                                        <span style="font-weight:700;color:#f8fafc;">₹81.31 ›</span>
                                    </div>

                                    <button type="button" class="real-reminder-btn" style="border-color:${primaryCol};color:${primaryCol};">
                                        ➤ Send Payment Reminders
                                    </button>
                                </div>
                            </div>

                            ${isWide ? `
                            <!-- Quick Actions (Desktop/Landscape) -->
                            <div class="real-quick-actions-row">
                                <div class="real-qa-card">
                                    <span class="real-qa-icon" style="background:#0284c7;">➕</span>
                                    <span class="real-qa-label">Add Product</span>
                                </div>
                                <div class="real-qa-card" onclick="WhitelabelPreview.setScreen('pos')">
                                    <span class="real-qa-icon" style="background:${primaryCol};">📠</span>
                                    <span class="real-qa-label">Create Order</span>
                                </div>
                                <div class="real-qa-card">
                                    <span class="real-qa-icon" style="background:#8b5cf6;">👤</span>
                                    <span class="real-qa-label">Add Customer</span>
                                </div>
                                <div class="real-qa-card">
                                    <span class="real-qa-icon" style="background:#f59e0b;">📊</span>
                                    <span class="real-qa-label">View Reports</span>
                                </div>
                            </div>
                            ` : ''}
                        </div>
                    </div>

                    <!-- Floating Curved Dock Navigation Bar (Mobile / Portrait) -->
                    <div class="real-dock-navbar">
                        <div class="real-dock-item active" style="color:${primaryCol};">
                            <span class="real-dock-icon">🏠</span>
                            <span class="real-dock-text">Home</span>
                        </div>
                        <div class="real-dock-item" onclick="WhitelabelPreview.setScreen('pos')">
                            <span class="real-dock-icon">🧾</span>
                            <span class="real-dock-text">Sales</span>
                        </div>
                        <div class="real-dock-fab" style="background:${primaryCol};" onclick="WhitelabelPreview.setScreen('pos')">
                            <span>+</span>
                        </div>
                        <div class="real-dock-item" onclick="WhitelabelPreview.setScreen('cart')">
                            <span class="real-dock-icon">🛍️</span>
                            <span class="real-dock-text">Orders</span>
                        </div>
                        <div class="real-dock-item">
                            <span class="real-dock-icon">⊞</span>
                            <span class="real-dock-text">More</span>
                        </div>
                    </div>
                </div>
            `;
        }

        // -------------------------------------------------------------
        // SCREEN 2: REAL RETAIL POS REGISTER (Screenshot 3 in /read)
        // -------------------------------------------------------------
        if (s.screen === 'pos') {
            return `
                <div class="real-retail-screen">
                    <!-- Top Bar -->
                    <div class="real-retail-topbar">
                        <button type="button" class="real-back-btn" onclick="WhitelabelPreview.setScreen('dashboard')" title="Back to Dashboard">←</button>
                        <span class="real-retail-title">Retail</span>
                    </div>

                    <!-- Search Bar with Scanner -->
                    <div class="real-search-wrap">
                        <div class="real-search-input-box">
                            <span style="color:#94a3b8;font-size:14px;">🔍</span>
                            <input type="text" placeholder="Search products, SKU, or barcode" class="real-search-inp" readonly>
                            <span style="color:#94a3b8;font-size:16px;">⛶</span>
                        </div>
                    </div>

                    <!-- Category Filter Pills -->
                    <div class="real-cats-scroll">
                        <span class="real-cat-pill">All</span>
                        <span class="real-cat-pill active" style="background:#134e4a;color:${primaryCol};border-color:${primaryCol};">✓ Antibiotics</span>
                        <span class="real-cat-pill">Beverages</span>
                        <span class="real-cat-pill">Carbohydrate</span>
                        <span class="real-cat-pill">Dessert</span>
                        <span class="real-cat-pill">Personal Care</span>
                    </div>

                    <!-- 2-Column Product Grid (Matches Screenshot 3) -->
                    <div class="real-product-grid">
                        <!-- Product 1: Amoxicillin (Out of Stock) -->
                        <div class="real-prod-card">
                            <div class="real-prod-img-box" style="background:linear-gradient(135deg, #1e3a5f, #0f172a);">
                                <div class="med-blister-graphic" title="Capsules Blister">
                                    <span>💊</span>
                                    <span>💊</span>
                                </div>
                            </div>
                            <div class="real-prod-body">
                                <div class="real-prod-name">Amoxicillin 500mg Capsules (10pk)</div>
                                <div class="real-prod-footer">
                                    <span class="real-prod-price" style="color:${primaryCol};">₹9.50</span>
                                    <span class="real-prod-badge out">Out of stock</span>
                                </div>
                            </div>
                        </div>

                        <!-- Product 2: Azithromycin (In Stock) -->
                        <div class="real-prod-card">
                            <div class="real-prod-img-box" style="background:linear-gradient(135deg, #c2410c, #7c2d12);">
                                <div class="med-bottle-graphic" title="Orange Tablets Bottle">
                                    <span>💊</span>
                                    <span>💊</span>
                                </div>
                            </div>
                            <div class="real-prod-body">
                                <div class="real-prod-name">Azithromycin 250mg Tablets (6pk)</div>
                                <div class="real-prod-footer">
                                    <span class="real-prod-price" style="color:${primaryCol};">₹13.00</span>
                                    <span class="real-prod-badge in" style="color:${primaryCol};">In Stock</span>
                                </div>
                            </div>
                        </div>

                        <!-- Product 3: Paracetamol -->
                        <div class="real-prod-card">
                            <div class="real-prod-img-box" style="background:linear-gradient(135deg, #0369a1, #0c4a6e);">
                                <div class="med-blister-graphic">
                                    <span>⚪</span>
                                    <span>⚪</span>
                                </div>
                            </div>
                            <div class="real-prod-body">
                                <div class="real-prod-name">Paracetamol 650mg Fast Relief (15s)</div>
                                <div class="real-prod-footer">
                                    <span class="real-prod-price" style="color:${primaryCol};">₹4.20</span>
                                    <span class="real-prod-badge in" style="color:${primaryCol};">In Stock</span>
                                </div>
                            </div>
                        </div>

                        <!-- Product 4: Cough Syrup -->
                        <div class="real-prod-card">
                            <div class="real-prod-img-box" style="background:linear-gradient(135deg, #6d28d9, #4c1d95);">
                                <div class="med-bottle-graphic">
                                    <span>🧪</span>
                                </div>
                            </div>
                            <div class="real-prod-body">
                                <div class="real-prod-name">Cough Syrup Relief 100ml</div>
                                <div class="real-prod-footer">
                                    <span class="real-prod-price" style="color:${primaryCol};">₹8.50</span>
                                    <span class="real-prod-badge in" style="color:${primaryCol};">In Stock</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Floating Bottom Cart Button -->
                    <div class="real-retail-cart-bar-wrap">
                        <button type="button" class="real-retail-cart-bar" style="background:${primaryCol};" onclick="WhitelabelPreview.setScreen('cart')">
                            🛒 View Cart • 3 items • ₹20.03
                        </button>
                    </div>
                </div>
            `;
        }

        // -------------------------------------------------------------
        // SCREEN 3: REAL ORDER CART & CHECKOUT (Screenshot 4 in /read)
        // -------------------------------------------------------------
        if (s.screen === 'cart') {
            return `
                <div class="real-cart-screen">
                    <!-- Bottom Sheet Handle -->
                    <div class="real-sheet-handle"></div>

                    <!-- Header -->
                    <div class="real-cart-header">
                        <div style="display:flex;align-items:center;gap:8px;">
                            <span class="real-cart-title">Order Cart</span>
                            <span class="real-cart-count-pill">3 items</span>
                        </div>
                        <button type="button" class="real-cart-clear-btn">🗑️ Clear Cart</button>
                    </div>

                    <!-- Action Chips -->
                    <div class="real-chips-container">
                        <div class="real-chips-row">
                            <button type="button" class="real-action-chip">👤 Add Customer</button>
                            <button type="button" class="real-action-chip">⏸ Hold</button>
                            <button type="button" class="real-action-chip">📝 Note</button>
                            <button type="button" class="real-action-chip">🏷️ Discount</button>
                        </div>
                        <div class="real-chips-row">
                            <button type="button" class="real-action-chip">🔀 Split Payment</button>
                            <button type="button" class="real-action-chip">💲 Amount Paid</button>
                        </div>
                    </div>

                    <!-- Payment Method Selector -->
                    <div class="real-pay-section">
                        <label class="real-pay-label">Payment Method</label>
                        <div class="real-pay-methods-grid">
                            <button type="button" class="real-pay-btn active" style="border-color:${primaryCol};color:${primaryCol};background:rgba(16, 185, 129, 0.1);">
                                <span>💵</span> Cash
                            </button>
                            <button type="button" class="real-pay-btn">
                                <span>💳</span> Card
                            </button>
                            <button type="button" class="real-pay-btn">
                                <span>🏛️</span> Transfer
                            </button>
                        </div>
                    </div>

                    <!-- Cash Tendered -->
                    <div class="real-tender-section">
                        <label class="real-pay-label">Cash Tendered by Customer</label>
                        <div class="real-tender-input-box">
                            <span>₹20.03</span>
                        </div>
                    </div>

                    <!-- Change Due Highlight Banner -->
                    <div class="real-change-banner" style="background:#064e3b;border-left:4px solid ${primaryCol};">
                        <span style="font-size:11px;font-weight:700;color:#a7f3d0;">CHANGE DUE TO CUSTOMER</span>
                        <span style="font-size:15px;font-weight:900;color:${primaryCol};">₹0.00</span>
                    </div>

                    <!-- Quick Cash Denominations -->
                    <div class="real-denom-row">
                        <button type="button" class="real-denom-btn">Exact</button>
                        <button type="button" class="real-denom-btn">₹21.00</button>
                        <button type="button" class="real-denom-btn">₹25.00</button>
                        <button type="button" class="real-denom-btn">₹30.00</button>
                        <button type="button" class="real-denom-btn">₹50.00</button>
                    </div>

                    <!-- Subtotal & GSTIN Breakdown -->
                    <div class="real-summary-box">
                        <div class="real-sum-row">
                            <span>Subtotal</span>
                            <span style="font-weight:700;">₹18.50</span>
                        </div>
                        <div class="real-sum-row sub">
                            <span>└ CGST</span>
                            <span>+₹0.76</span>
                        </div>
                        <div class="real-sum-row sub">
                            <span>└ SGST</span>
                            <span>+₹0.76</span>
                        </div>
                        <div class="real-sum-row total" style="border-top:1px solid #334155;margin-top:6px;padding-top:8px;">
                            <div>
                                <div style="font-size:13px;font-weight:800;color:#f8fafc;">Grand Total</div>
                                <div style="font-size:9.5px;color:#94a3b8;">GSTIN: 29ABCDE1234F1Z5</div>
                            </div>
                            <span style="font-size:18px;font-weight:900;color:${primaryCol};">₹20.03</span>
                        </div>
                    </div>

                    <!-- Complete Sale Action Button -->
                    <div style="margin-top:auto;padding-top:8px;">
                        <button type="button" class="real-complete-sale-btn" style="background:${primaryCol};" onclick="WhitelabelPreview.setScreen('dashboard')">
                            ✓ Complete Sale • ₹20.03
                        </button>
                    </div>
                </div>
            `;
        }

        // -------------------------------------------------------------
        // SCREEN 4: REAL SIGN IN / AUTH (Screenshot 5 in /read)
        // -------------------------------------------------------------
        if (s.screen === 'login') {
            return `
                <div class="real-login-wrapper ${isWide ? 'is-split' : ''}">
                    <div class="real-login-card">
                        <!-- App Brand Header -->
                        <div style="display:flex;align-items:center;gap:10px;margin-bottom:18px;">
                            ${logoSrc ? `<img src="${logoSrc}" style="width:34px;height:34px;border-radius:8px;object-fit:contain;">` : `
                                <div style="width:34px;height:34px;border-radius:8px;background:${primaryCol};color:#fff;display:flex;align-items:center;justify-content:center;font-weight:900;font-size:16px;">
                                    ${logoAvatarLetter}
                                </div>
                            `}
                            <h3 style="margin:0;font-size:16px;font-weight:800;color:#0f172a;">${brandTitle} CRM & Inventory</h3>
                        </div>

                        <h2 style="margin:0 0 4px;font-size:19px;font-weight:800;color:#0f172a;">Welcome back</h2>
                        <p style="margin:0 0 16px;font-size:12px;color:#64748b;">Sign in to your account</p>

                        <!-- Email Input -->
                        <div style="margin-bottom:11px;">
                            <label style="display:block;font-size:11px;font-weight:700;color:#334155;margin-bottom:4px;">Email or login</label>
                            <div class="real-login-input-wrap">
                                <span>👤</span>
                                <input type="text" value="admin@${company.toLowerCase().replace(/[^a-z0-9]/g, '') || 'company'}.com" class="real-login-input" readonly>
                            </div>
                        </div>

                        <!-- Password Input -->
                        <div style="margin-bottom:14px;">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
                                <label style="font-size:11px;font-weight:700;color:#334155;">Password</label>
                                <a href="#" style="font-size:11px;font-weight:700;color:${primaryCol};text-decoration:none;">Forgot password?</a>
                            </div>
                            <div class="real-login-input-wrap">
                                <span>🔒</span>
                                <input type="password" value="••••••••••••" class="real-login-input" readonly>
                                <span style="cursor:pointer;color:#94a3b8;">👁️</span>
                            </div>
                        </div>

                        <button type="button" class="real-signin-btn" style="background:${primaryCol};color:#fff;">
                            Sign in →
                        </button>

                        <div class="real-or-divider">
                            <span>Or continue with</span>
                        </div>

                        <div style="display:flex;flex-direction:column;gap:7px;margin-bottom:12px;">
                            <button type="button" class="real-social-btn">
                                <svg width="15" height="15" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/></svg>
                                Continue with Google
                            </button>
                            <button type="button" class="real-social-btn">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="#1877F2"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                                Continue with Facebook
                            </button>
                        </div>

                        <!-- Store ID Link Card -->
                        <div class="real-store-id-banner">
                            <div style="display:flex;align-items:center;gap:8px;">
                                <span style="font-size:16px;">🏪</span>
                                <div>
                                    <div style="font-size:11px;font-weight:700;color:#0f172a;">Have a store account ID?</div>
                                    <div style="font-size:9.5px;color:#64748b;">Connect your store to get started</div>
                                </div>
                            </div>
                            <span style="font-size:13px;color:#64748b;">›</span>
                        </div>

                        <div style="text-align:center;font-size:10.5px;color:#64748b;margin-top:12px;">
                            Don't have a store yet? <a href="#" style="color:${primaryCol};font-weight:700;text-decoration:none;">Create one</a> &bull; <a href="#" style="color:#64748b;text-decoration:none;">Back to Home</a>
                        </div>
                    </div>

                    ${isWide ? `
                    <div class="real-login-showcase">
                        <img src="assets/hero-devices.png" style="width:100%;max-width:480px;height:auto;object-fit:contain;filter:drop-shadow(0 20px 30px rgba(0,0,0,0.15));" alt="POS Hardware System">
                    </div>
                    ` : ''}
                </div>
            `;
        }

        // -------------------------------------------------------------
        // SCREEN 5: REAL BRANDED SPLASH SCREEN
        // -------------------------------------------------------------
        return `
            <div class="splash-screen-layout" style="background:radial-gradient(circle at 50% 40%, ${primaryCol} 0%, #0b1329 100%);">
                <div></div>
                <div>
                    <div class="splash-logo-squircle">
                        ${logoSrc ? `<img src="${logoSrc}" class="splash-logo-img">` : `
                            <span class="splash-logo-letter" style="color:${primaryCol};">${logoAvatarLetter}</span>
                        `}
                    </div>
                    <h2 class="splash-title">${brandTitle}</h2>
                    <p class="splash-tagline">${s.productName || 'Point of Sale & Enterprise CRM'}</p>

                    <div class="splash-loader-bar">
                        <div class="splash-loader-progress" style="background:#ffffff;"></div>
                    </div>
                </div>
                <div class="splash-footer-info">
                    Connected to ${s.serverUrl}<br>
                    ${s.copyright}
                </div>
            </div>
        `;
    },

    updateArtifactChips: function() {
        const s = this.state;
        const name = s.shortName || 'App';
        const setChip = (id, ext) => {
            const el = document.getElementById(id);
            if (el) el.innerText = `${name}-${ext}`;
        };
        setChip('chip_apk', 'android.apk');
        setChip('chip_web', 'web.zip');
        setChip('chip_win', 'windows.zip');
        setChip('chip_ios', 'ios.ipa');
    },

    validate: function() {
        const s = this.state;
        const checks = [
            {
                id: 'chk_app_name',
                label: 'Application Name',
                valid: !!s.appName && s.appName.length >= 3,
                warn: 'App Name is required (minimum 3 characters)'
            },
            {
                id: 'chk_company_name',
                label: 'Company Name',
                valid: !!s.companyName && s.companyName.length >= 2,
                warn: 'Company Name is required'
            },
            {
                id: 'chk_logo',
                label: 'Logo & Visual Identity',
                valid: !!s.logoUrl || true,
                warn: 'Recommended to upload high-res brand logo'
            },
            {
                id: 'chk_app_icon',
                label: 'App Launcher Icon',
                valid: !!s.androidIconUrl || !!s.logoUrl || true,
                warn: 'Auto-generated from master logo'
            },
            {
                id: 'chk_favicon',
                label: 'Favicon & Web Manifest',
                valid: !!s.faviconUrl || !!s.logoUrl || true,
                warn: 'Auto-generated from master logo'
            },
            {
                id: 'chk_primary_color',
                label: 'Primary Brand Color',
                valid: /^#[0-9A-Fa-f]{6}$/.test(s.primaryColor),
                warn: 'Valid 6-digit hex color required (e.g. #4F46E5)'
            },
            {
                id: 'chk_secondary_color',
                label: 'Secondary Component Color',
                valid: /^#[0-9A-Fa-f]{6}$/.test(s.secondaryColor),
                warn: 'Valid 6-digit hex color required (e.g. #06B6D4)'
            },
            {
                id: 'chk_package_name',
                label: 'Package Name / App ID',
                valid: /^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$/i.test(s.packageId),
                warn: 'Must follow reverse-domain format (e.g. com.brand.pos)'
            },
            {
                id: 'chk_server_url',
                label: 'Backend Server URL',
                valid: /^https?:\/\/.+/i.test(s.serverUrl),
                warn: 'Must be a valid HTTP or HTTPS URL'
            },
            {
                id: 'chk_android',
                label: 'Android White Label',
                valid: !!s.appName && /^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$/i.test(s.packageId),
                warn: 'Android manifest values ready'
            },
            {
                id: 'chk_ios',
                label: 'iOS White Label',
                valid: !!s.displayName && /^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$/i.test(s.packageId),
                warn: 'iOS bundle identifier ready'
            },
            {
                id: 'chk_windows_web',
                label: 'Windows & Web Manifest',
                valid: !!s.companyName && !!s.serverUrl,
                warn: 'Desktop runner and web manifest ready'
            }
        ];

        let passCount = 0;
        checks.forEach(c => {
            if (c.valid) passCount++;
            const el = document.getElementById(c.id);
            if (el) {
                el.className = `check-item ${c.valid ? 'ready' : 'warning'}`;
                el.innerHTML = `
                    <span class="check-icon">${c.valid ? '✓' : '⚠'}</span>
                    <span style="font-weight:700;">${c.label}</span>
                `;
                el.title = c.valid ? 'Ready' : c.warn;
            }
        });

        const percent = Math.round((passCount / checks.length) * 100);
        const meterFill = document.getElementById('completeness_meter_fill');
        const meterLabel = document.getElementById('completeness_meter_label');
        const statusBadge = document.getElementById('preview_completeness_badge');

        if (meterFill) meterFill.style.width = percent + '%';
        if (meterLabel) {
            meterLabel.innerText = `${percent}% White Label Ready (${passCount}/${checks.length} criteria met)`;
        }
        if (statusBadge) {
            if (percent === 100) {
                statusBadge.className = 'status-pill active';
                statusBadge.innerText = '✓ White Label Configuration Ready';
            } else {
                statusBadge.className = 'status-pill pending';
                statusBadge.innerText = `⚠ ${checks.length - passCount} items need attention`;
            }
        }
    }
};

document.addEventListener('DOMContentLoaded', function() {
    WhitelabelPreview.init();
});
