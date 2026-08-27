/**
 * Draggable & Dockable Multi-Position Navigation Menu
 * Standalone Script for Immediate Synchronous Loading in <head>
 */

(function(window, document) {
    'use strict';

    var ALL_DOCK_ITEMS = [
        { key: 'home', label: 'Home / Dashboard', icon: '🏠', route: 'tenant.dashboard', mode: 'all' },
        { key: 'pos', label: 'Retail Counter POS', icon: '🛒', route: 'tenant.sales.create', mode: 'retail' },
        { key: 'restaurant_pos', label: 'Food & Restaurant POS', icon: '🍽️', route: 'tenant.restaurant.pos', mode: 'restaurant' },
        { key: 'tables', label: 'Dining Tables & Floors', icon: '🪑', route: 'tenant.restaurant.tables', mode: 'restaurant' },
        { key: 'kds', label: 'Kitchen KDS Display', icon: '🍳', route: 'tenant.restaurant.kds', mode: 'restaurant' },
        { key: 'sales', label: 'Invoices & Sales', icon: '🧾', route: 'tenant.sales.index', mode: 'all' },
        { key: 'quotes', label: 'Quotations & Proposals', icon: '📄', route: 'tenant.quotes.index', mode: 'retail' },
        { key: 'products', label: 'Products & Inventory', icon: '📦', route: 'tenant.products.index', mode: 'all' },
        { key: 'categories', label: 'Categories & Brands', icon: '🏷️', route: 'tenant.categories.index', mode: 'all' },
        { key: 'customers', label: 'Customer Directory', icon: '👥', route: 'tenant.customers.index', mode: 'all' },
        { key: 'register', label: 'Cash Register Shifts', icon: '💵', route: 'tenant.financials.cash_register', mode: 'all' },
        { key: 'receivables', label: 'Accounts Receivable', icon: '💳', route: 'tenant.financials.receivables', mode: 'all' },
        { key: 'reports', label: 'Analytics & Reports', icon: '📊', route: 'tenant.reports.index', mode: 'all' },
        { key: 'settings', label: 'Store Settings', icon: '⚙️', route: 'tenant.settings.index', mode: 'all' },
        { key: 'billing', label: 'Billing & SaaS Plans', icon: '👑', route: 'tenant.billing.index', mode: 'all' }
    ];

    var ADMIN_DOCK_ITEMS = [
        { key: 'dashboard', label: 'Dashboard Overview', icon: '📊', route: 'superadmin.dashboard' },
        { key: 'tenants', label: 'Tenant Stores', icon: '🏢', route: 'superadmin.tenants.index' },
        { key: 'plans', label: 'SaaS Plans & Pricing', icon: '👑', route: 'superadmin.plans.index' },
        { key: 'taxes', label: 'Global Tax Engine', icon: '⚖️', route: 'superadmin.tax.index' },
        { key: 'menus', label: 'Menu Builder', icon: '🧭', route: 'superadmin.menus.index' },
        { key: 'pages', label: 'CMS Custom Pages', icon: '📄', route: 'superadmin.pages.index' },
        { key: 'settings', label: 'Platform Settings', icon: '⚙️', route: 'superadmin.settings.index' },
        { key: 'smtp', label: 'SMTP & Mail Config', icon: '✉️', route: 'superadmin.smtp.index' }
    ];

    function dockableNav(storageKey, defaultPosition, operatingMode) {
        storageKey = storageKey || 'sa_dock_nav_state';
        defaultPosition = defaultPosition || 'left';
        operatingMode = operatingMode || 'general';

        return {
            storageKey: storageKey,
            operatingMode: operatingMode,
            position: defaultPosition,
            mode: 'docked',
            layout: 'slim',
            theme: 'violet',
            sticky: false,
            customBg: '',
            uiAccentColor: '#2563eb',
            navTextColor: '#ffffff',
            navTextActiveColor: '#60a5fa',
            visibleItems: [],
            availableDockItems: [],
            adminDockItems: ADMIN_DOCK_ITEMS,
            visibleAdminItems: (function() {
                try {
                    var raw = localStorage.getItem('nav_visible_items');
                    return raw ? JSON.parse(raw) : ['dashboard', 'tenants', 'plans', 'taxes', 'menus', 'pages', 'settings', 'smtp'];
                } catch(e) {
                    return ['dashboard', 'tenants', 'plans', 'taxes', 'menus', 'pages', 'settings', 'smtp'];
                }
            })(),
            x: 24,
            y: 100,
            isDragging: false,
            dragStartX: 0,
            dragStartY: 0,
            startPosX: 0,
            startPosY: 0,
            snapZone: null,
            showQuickMenu: false,
            showCustomizerModal: false,
            speedDialOpen: false,

            getDefaultKeys: function() {
                if (this.storageKey === 'sa_dock_nav_state' || this.operatingMode === 'admin' || this.operatingMode === 'superadmin') {
                    return ['dashboard', 'tenants', 'plans', 'taxes', 'settings', 'smtp'];
                }
                var isRest = this.operatingMode === 'restaurant' || this.operatingMode === 'food_restaurant';
                return isRest
                    ? ['home', 'restaurant_pos', 'tables', 'kds', 'sales', 'products']
                    : ['home', 'pos', 'sales', 'quotes', 'products', 'categories'];
            },

            selectAllAdminItems: function() {
                this.visibleAdminItems = this.adminDockItems.map(function(i) { return i.key; });
                this.persistAdminDock();
            },

            resetAdminDefaultItems: function() {
                this.visibleAdminItems = ['dashboard', 'tenants', 'plans', 'taxes', 'settings', 'smtp'];
                this.persistAdminDock();
            },

            persistAdminDock: function() {
                localStorage.setItem('nav_visible_items', JSON.stringify(this.visibleAdminItems));
                this.visibleItems = this.visibleAdminItems.slice();
                this.saveState();
                window.dispatchEvent(new CustomEvent('dock-nav-update', { detail: { visibleItems: this.visibleAdminItems, visibleAdminItems: this.visibleAdminItems } }));
            },

            init: function() {
                var self = this;
                var isAdmin = this.storageKey === 'sa_dock_nav_state' || this.operatingMode === 'admin' || this.operatingMode === 'superadmin';
                var validKeys = [];
                var defaultKeys = this.getDefaultKeys();

                try {
                    if (isAdmin) {
                        this.availableDockItems = ADMIN_DOCK_ITEMS.slice();
                        var validAdminKeys = this.adminDockItems.map(function(i) { return i.key; });
                        try {
                            var storedAdmin = localStorage.getItem('nav_visible_items');
                            if (storedAdmin) {
                                var parsedAdmin = JSON.parse(storedAdmin);
                                if (Array.isArray(parsedAdmin)) {
                                    this.visibleAdminItems = parsedAdmin.filter(function(k) { return validAdminKeys.indexOf(k) !== -1; });
                                }
                            }
                        } catch(e) {}
                        this.visibleItems = this.visibleAdminItems.slice();
                    } else {
                        var isRest = this.operatingMode === 'restaurant' || this.operatingMode === 'food_restaurant';
                        this.availableDockItems = ALL_DOCK_ITEMS.filter(function(item) {
                            if (item.mode === 'all') return true;
                            if (isRest) return item.mode === 'restaurant';
                            return item.mode === 'retail';
                        });

                        validKeys = this.availableDockItems.map(function(i) { return i.key; });
                        this.visibleItems = defaultKeys.slice();

                        try {
                            var storedItems = localStorage.getItem('dock_visible_items');
                            if (storedItems) {
                                var parsedItems = JSON.parse(storedItems);
                                if (Array.isArray(parsedItems)) {
                                    this.visibleItems = parsedItems.filter(function(k) { return validKeys.indexOf(k) !== -1; });
                                }
                            }
                        } catch(e) {}
                    }

                    var raw = localStorage.getItem(this.storageKey);
                    if (raw) {
                        var saved = JSON.parse(raw);
                        if (saved) {
                            if (saved.position) this.position = saved.position;
                            if (saved.mode) this.mode = saved.mode;
                            if (saved.layout) this.layout = saved.layout;
                            if (saved.theme) this.theme = saved.theme;
                            if (typeof saved.sticky !== 'undefined') this.sticky = !!saved.sticky;
                            if (typeof saved.customBg !== 'undefined') this.customBg = saved.customBg;
                            if (saved.uiAccentColor) this.uiAccentColor = saved.uiAccentColor;
                            if (saved.navTextColor) this.navTextColor = saved.navTextColor;
                            if (saved.navTextActiveColor) this.navTextActiveColor = saved.navTextActiveColor;
                            if (Array.isArray(saved.visibleItems) && saved.visibleItems.length > 0 && !isAdmin) {
                                this.visibleItems = saved.visibleItems.filter(function(k) { return validKeys.indexOf(k) !== -1; });
                            }
                            this.x = typeof saved.x === 'number' ? saved.x : 24;
                            this.y = typeof saved.y === 'number' ? saved.y : 100;
                        }
                    }

                    var storedSticky = localStorage.getItem('nav_sticky');
                    if (storedSticky !== null) {
                        this.sticky = storedSticky === 'true';
                    }
                    var storedBg = localStorage.getItem('nav_dock_bg');
                    if (storedBg) {
                        this.customBg = storedBg;
                    }
                    var storedAccent = localStorage.getItem('ui_accent_color');
                    if (storedAccent) {
                        this.uiAccentColor = storedAccent;
                    }
                    var storedNavTextColor = localStorage.getItem('nav_text_color');
                    if (storedNavTextColor) {
                        this.navTextColor = storedNavTextColor;
                    }
                    var storedNavTextActive = localStorage.getItem('nav_text_active_color');
                    if (storedNavTextActive) {
                        this.navTextActiveColor = storedNavTextActive;
                    }
                } catch (e) {
                    this.position = defaultPosition;
                    this.mode = 'docked';
                    this.layout = 'slim';
                    this.theme = 'violet';
                    this.sticky = false;
                    this.customBg = '';
                    this.uiAccentColor = '#2563eb';
                    this.navTextColor = '#ffffff';
                    this.navTextActiveColor = '#60a5fa';
                    this.visibleItems = defaultKeys.slice();
                }

                this.applyDomAttributes();
                this.applyDynamicCssVars();

                window.addEventListener('resize', function() {
                    if (self.position === 'floating' || self.mode === 'floating') {
                        self.clampCoordinates();
                    }
                });

                window.addEventListener('reset-dock-nav', function() { self.resetAll(); });
                window.addEventListener('open-dock-customizer', function() { self.showCustomizerModal = true; });
                window.addEventListener('set-dock-nav-mode', function(e) {
                    if (e.detail && e.detail.mode) self.setMode(e.detail.mode);
                });
                window.addEventListener('set-dock-nav-pos', function(e) {
                    if (e.detail && e.detail.position) self.setPosition(e.detail.position);
                });
                window.addEventListener('set-dock-nav-layout', function(e) {
                    if (e.detail && e.detail.layout) self.setLayout(e.detail.layout);
                });
                window.addEventListener('set-dock-nav-theme', function(e) {
                    if (e.detail && e.detail.theme) self.setTheme(e.detail.theme);
                });
                window.addEventListener('set-dock-nav-sticky', function(e) {
                    if (e.detail && typeof e.detail.sticky !== 'undefined') self.setSticky(e.detail.sticky);
                });
                window.addEventListener('set-dock-nav-bg', function(e) {
                    if (e.detail && typeof e.detail.bg !== 'undefined') self.setCustomBg(e.detail.bg);
                });
                window.addEventListener('set-ui-accent-color', function(e) {
                    if (e.detail && e.detail.color) self.setUiAccentColor(e.detail.color);
                });
                window.addEventListener('set-nav-text-color', function(e) {
                    if (e.detail && e.detail.color) self.setNavTextColor(e.detail.color);
                });
                window.addEventListener('set-nav-text-active-color', function(e) {
                    if (e.detail && e.detail.color) self.setNavTextActiveColor(e.detail.color);
                });
                window.addEventListener('operating-mode-updated', function(e) {
                    if (e.detail && e.detail.mode) {
                        self.operatingMode = e.detail.mode;
                        var isRestNow = self.operatingMode === 'restaurant' || self.operatingMode === 'food_restaurant';
                        self.availableDockItems = ALL_DOCK_ITEMS.filter(function(item) {
                            if (item.mode === 'all') return true;
                            if (isRestNow) return item.mode === 'restaurant';
                            return item.mode === 'retail';
                        });
                        var validNow = self.availableDockItems.map(function(i) { return i.key; });
                        self.visibleItems = self.visibleItems.filter(function(k) { return validNow.indexOf(k) !== -1; });
                        if (self.visibleItems.length === 0) {
                            self.visibleItems = self.getDefaultKeys();
                        }
                        self.saveState();
                    }
                });
            },

            applyDomAttributes: function() {
                document.documentElement.setAttribute('data-dock-pos', this.position);
                document.documentElement.setAttribute('data-dock-mode', this.mode);
                document.documentElement.setAttribute('data-nav-layout', this.layout);
                document.documentElement.setAttribute('data-nav-theme', this.theme);
                document.documentElement.setAttribute('data-nav-sticky', this.sticky ? 'true' : 'false');
                document.documentElement.setAttribute('data-pos-mode', this.operatingMode || 'general');
            },

            applyDynamicCssVars: function() {
                var root = document.documentElement;
                if (this.uiAccentColor) {
                    var hex = this.uiAccentColor;
                    var darkHex = this.adjustBrightness(hex, -20);
                    var lightRgba = this.hexToRgba(hex, 0.12);
                    root.style.setProperty('--color-primary', hex);
                    root.style.setProperty('--color-primary-hover', darkHex);
                    root.style.setProperty('--color-primary-light', lightRgba);
                    root.style.setProperty('--color-primary-gradient', 'linear-gradient(135deg, ' + hex + ', ' + darkHex + ')');
                }
                if (this.navTextColor) {
                    root.style.setProperty('--nav-item-color', this.navTextColor);
                }
                if (this.navTextActiveColor) {
                    root.style.setProperty('--nav-item-active-color', this.navTextActiveColor);
                }
            },

            hexToRgba: function(hex, alpha) {
                alpha = (alpha !== undefined) ? alpha : 0.1;
                var cleanHex = hex.replace('#', '');
                if (cleanHex.length === 3) {
                    var r = parseInt(cleanHex[0] + cleanHex[0], 16);
                    var g = parseInt(cleanHex[1] + cleanHex[1], 16);
                    var b = parseInt(cleanHex[2] + cleanHex[2], 16);
                    return 'rgba(' + r + ', ' + g + ', ' + b + ', ' + alpha + ')';
                } else if (cleanHex.length === 6) {
                    var r = parseInt(cleanHex.substring(0, 2), 16);
                    var g = parseInt(cleanHex.substring(2, 4), 16);
                    var b = parseInt(cleanHex.substring(4, 6), 16);
                    return 'rgba(' + r + ', ' + g + ', ' + b + ', ' + alpha + ')';
                }
                return 'rgba(37, 99, 235, ' + alpha + ')';
            },

            adjustBrightness: function(hex, percent) {
                var num = parseInt(hex.replace('#', ''), 16);
                var amt = Math.round(2.55 * percent);
                var R = (num >> 16) + amt;
                var G = (num >> 8 & 0x00FF) + amt;
                var B = (num & 0x0000FF) + amt;
                return '#' + (0x1000000 + (R < 255 ? R < 1 ? 0 : R : 255) * 0x10000 +
                    (G < 255 ? G < 1 ? 0 : G : 255) * 0x100 +
                    (B < 255 ? B < 1 ? 0 : B : 255)).toString(16).slice(1);
            },

            scrollNav: function(amount) {
                var el = (this.$refs && this.$refs.scrollNavContainer) || (this.$el ? this.$el.querySelector('[data-dock-scroll-container]') : null);
                if (el) {
                    el.scrollBy({ left: amount, behavior: 'smooth' });
                }
            },

            saveState: function() {
                this.applyDomAttributes();
                this.applyDynamicCssVars();
                try {
                    localStorage.setItem(this.storageKey, JSON.stringify({
                        position: this.position,
                        mode: this.mode,
                        layout: this.layout,
                        theme: this.theme,
                        sticky: this.sticky,
                        customBg: this.customBg,
                        uiAccentColor: this.uiAccentColor,
                        navTextColor: this.navTextColor,
                        navTextActiveColor: this.navTextActiveColor,
                        visibleItems: this.visibleItems,
                        x: Math.round(this.x),
                        y: Math.round(this.y)
                    }));
                    localStorage.setItem('nav_sticky', this.sticky ? 'true' : 'false');
                    localStorage.setItem('nav_dock_bg', this.customBg || '');
                    localStorage.setItem('ui_accent_color', this.uiAccentColor || '#2563eb');
                    localStorage.setItem('nav_text_color', this.navTextColor || '#ffffff');
                    localStorage.setItem('nav_text_active_color', this.navTextActiveColor || '#60a5fa');
                    localStorage.setItem('dock_visible_items', JSON.stringify(this.visibleItems));
                } catch (e) {}

                window.dispatchEvent(new CustomEvent('dock-nav-changed', {
                    detail: {
                        position: this.position,
                        mode: this.mode,
                        layout: this.layout,
                        theme: this.theme,
                        sticky: this.sticky,
                        customBg: this.customBg,
                        uiAccentColor: this.uiAccentColor,
                        navTextColor: this.navTextColor,
                        navTextActiveColor: this.navTextActiveColor,
                        visibleItems: this.visibleItems,
                        x: this.x,
                        y: this.y
                    }
                }));
            },

            setPosition: function(newPos) {
                this.position = newPos;
                if (newPos === 'floating') {
                    this.mode = 'floating';
                    if (!this.x || this.x < 10) this.x = 24;
                    if (!this.y || this.y < 10) this.y = 100;
                    this.clampCoordinates();
                } else {
                    this.mode = 'docked';
                }
                this.showQuickMenu = false;
                this.snapZone = null;
                this.saveState();
            },

            setMode: function(newMode) {
                this.mode = newMode;
                if (newMode === 'floating') {
                    this.position = 'floating';
                    this.clampCoordinates();
                } else if (this.position === 'floating') {
                    this.position = defaultPosition;
                }
                this.saveState();
            },

            setLayout: function(newLayout) {
                this.layout = newLayout;
                if (newLayout === 'macos-dock' && (this.position === 'left' || this.position === 'right')) {
                    this.position = 'bottom';
                }
                this.saveState();
            },

            setTheme: function(newTheme) {
                this.theme = newTheme;
                this.saveState();
            },

            setSticky: function(val) {
                this.sticky = !!val;
                this.saveState();
            },

            setCustomBg: function(bg) {
                this.customBg = bg || '';
                this.saveState();
            },

            setUiAccentColor: function(color) {
                this.uiAccentColor = color || '#2563eb';
                this.applyDynamicCssVars();
                this.saveState();
            },

            setNavTextColor: function(color) {
                this.navTextColor = color || '#ffffff';
                this.applyDynamicCssVars();
                this.saveState();
            },

            setNavTextActiveColor: function(color) {
                this.navTextActiveColor = color || '#60a5fa';
                this.applyDynamicCssVars();
                this.saveState();
            },

            isItemVisible: function(itemKey) {
                var isRest = this.operatingMode === 'restaurant' || this.operatingMode === 'food_restaurant';
                var itemDef = ALL_DOCK_ITEMS.find(function(i) { return i.key === itemKey; });
                if (itemDef) {
                    if (!isRest && itemDef.mode === 'restaurant') return false;
                    if (isRest && itemDef.mode === 'retail') return false;
                }
                return this.visibleItems.indexOf(itemKey) !== -1;
            },

            toggleItem: function(itemKey) {
                var idx = this.visibleItems.indexOf(itemKey);
                if (idx !== -1) {
                    this.visibleItems.splice(idx, 1);
                } else {
                    this.visibleItems.push(itemKey);
                }
                this.saveState();
            },

            selectAllItems: function() {
                this.visibleItems = this.availableDockItems.map(function(i) { return i.key; });
                this.saveState();
            },

            selectDefaultItems: function() {
                this.visibleItems = this.getDefaultKeys();
                this.saveState();
            },

            uncheckAllItems: function() {
                this.visibleItems = [this.getDefaultKeys()[0] || 'home'];
                this.saveState();
            },

            resetItems: function() {
                this.selectDefaultItems();
            },

            resetAll: function() {
                this.position = 'left';
                this.mode = 'docked';
                this.layout = 'slim';
                this.theme = 'violet';
                this.sticky = false;
                this.customBg = '';
                this.uiAccentColor = '#2563eb';
                this.navTextColor = '#ffffff';
                this.navTextActiveColor = '#60a5fa';
                this.visibleItems = this.getDefaultKeys();
                this.x = 24;
                this.y = 100;
                this.showQuickMenu = false;
                this.showCustomizerModal = false;
                this.speedDialOpen = false;
                this.snapZone = null;
                this.saveState();
            },

            toggleQuickMenu: function() {
                this.showQuickMenu = !this.showQuickMenu;
            },

            toggleCustomizerModal: function() {
                this.showCustomizerModal = !this.showCustomizerModal;
                this.showQuickMenu = false;
            },

            toggleSpeedDial: function() {
                this.speedDialOpen = !this.speedDialOpen;
            },

            clampCoordinates: function() {
                var menuEl = this.$refs && this.$refs.dockNavEl;
                var w = menuEl ? menuEl.offsetWidth : 80;
                var h = menuEl ? menuEl.offsetHeight : 300;
                var maxX = Math.max(10, window.innerWidth - w - 10);
                var maxY = Math.max(10, window.innerHeight - h - 10);
                this.x = Math.max(10, Math.min(maxX, this.x));
                this.y = Math.max(10, Math.min(maxY, this.y));
            },

            startDrag: function(event) {
                var self = this;
                if (event.target.closest('button:not([data-drag-handle]), a, input, select, [data-prevent-drag]')) {
                    return;
                }

                event.preventDefault();
                this.showQuickMenu = false;
                this.isDragging = true;
                this.snapZone = null;

                var clientX = event.touches ? event.touches[0].clientX : event.clientX;
                var clientY = event.touches ? event.touches[0].clientY : event.clientY;

                this.dragStartX = clientX;
                this.dragStartY = clientY;

                if (this.position !== 'floating') {
                    var navEl = this.$refs && this.$refs.dockNavEl;
                    var rect = navEl ? navEl.getBoundingClientRect() : { left: clientX - 40, top: clientY - 40 };
                    this.startPosX = rect.left;
                    this.startPosY = rect.top;
                    this.x = rect.left;
                    this.y = rect.top;
                } else {
                    this.startPosX = this.x;
                    this.startPosY = this.y;
                }

                var onPointerMove = function(e) {
                    if (!self.isDragging) return;
                    var curX = e.touches ? e.touches[0].clientX : e.clientX;
                    var curY = e.touches ? e.touches[0].clientY : e.clientY;
                    var deltaX = curX - self.dragStartX;
                    var deltaY = curY - self.dragStartY;

                    self.x = self.startPosX + deltaX;
                    self.y = self.startPosY + deltaY;

                    var screenW = window.innerWidth;
                    var screenH = window.innerHeight;
                    var threshold = 90;

                    if (curX <= threshold) {
                        self.snapZone = 'left';
                    } else if (curX >= screenW - threshold) {
                        self.snapZone = 'right';
                    } else if (curY <= threshold) {
                        self.snapZone = 'top';
                    } else if (curY >= screenH - threshold) {
                        self.snapZone = 'bottom';
                    } else {
                        self.snapZone = null;
                    }
                };

                var onPointerUp = function(e) {
                    if (!self.isDragging) return;
                    self.isDragging = false;

                    window.removeEventListener('mousemove', onPointerMove);
                    window.removeEventListener('mouseup', onPointerUp);
                    window.removeEventListener('touchmove', onPointerMove);
                    window.removeEventListener('touchend', onPointerUp);

                    if (self.snapZone) {
                        self.position = self.snapZone;
                        self.mode = 'docked';
                    } else {
                        self.position = 'floating';
                        self.mode = 'floating';
                        self.clampCoordinates();
                    }

                    self.snapZone = null;
                    self.saveState();
                };

                window.addEventListener('mousemove', onPointerMove);
                window.addEventListener('mouseup', onPointerUp);
                window.addEventListener('touchmove', onPointerMove, { passive: false });
                window.addEventListener('touchend', onPointerUp);
            }
        };
    }

    function scrollTabToCenter(element) {
        if (!element) return;
        var container = element.parentElement;
        if (!container) return;

        var containerWidth = container.clientWidth;
        var itemLeft = element.offsetLeft;

        if (element.offsetParent && element.offsetParent !== container) {
            var containerRect = container.getBoundingClientRect();
            var itemRect = element.getBoundingClientRect();
            itemLeft = itemRect.left - containerRect.left + container.scrollLeft;
        }

        var itemWidth = element.offsetWidth;
        var targetScrollLeft = itemLeft - (containerWidth / 2) + (itemWidth / 2);

        container.scrollTo({
            left: targetScrollLeft,
            behavior: 'smooth'
        });
    }

    function syncActiveTabs() {
        setTimeout(function() {
            var containers = document.querySelectorAll('.dockable-nav-container, .pos-categories-row, .pos-categories-container, .tab-scroll-container, [data-dock-scroll-container]');
            containers.forEach(function(container) {
                var activeElement = container.querySelector('[aria-selected="true"]');
                if (!activeElement) {
                    var currentPath = window.location.pathname;
                    var links = container.querySelectorAll('a.dockable-nav-item, a[href]');
                    for (var i = 0; i < links.length; i++) {
                        try {
                            var linkUrl = new URL(links[i].href, window.location.origin);
                            if (linkUrl.pathname === currentPath) {
                                activeElement = links[i];
                                break;
                            }
                        } catch (e) {}
                    }
                }
                if (!activeElement) {
                    activeElement = container.querySelector('.active, [data-active="true"], .bg-blue-600, .bg-\\[\\#a3e635\\]');
                }
                if (activeElement) {
                    scrollTabToCenter(activeElement);
                }
            });
        }, 60);
    }

    function initMobileNavController() {
        document.addEventListener('click', function(e) {
            var targetTab = e.target.closest('.dockable-nav-item, .pos-category-btn, .pos-categories-container button, button[role="tab"]');
            if (targetTab) {
                scrollTabToCenter(targetTab);
            }
        });

        window.addEventListener('DOMContentLoaded', syncActiveTabs);
        document.addEventListener('livewire:navigated', syncActiveTabs);
        document.addEventListener('turbo:load', syncActiveTabs);
        window.addEventListener('popstate', syncActiveTabs);
        document.addEventListener('alpine:init', syncActiveTabs);
        window.addEventListener('sync-active-tabs', syncActiveTabs);
        window.addEventListener('dock-nav-changed', syncActiveTabs);
    }

    // Expose immediately to global scope
    window.ALL_DOCK_ITEMS = ALL_DOCK_ITEMS;
    window.dockableNav = dockableNav;
    window.scrollTabToCenter = scrollTabToCenter;
    window.syncActiveTabs = syncActiveTabs;
    initMobileNavController();

    var registerAlpineData = function() {
        if (window.Alpine) {
            window.Alpine.data('dockableNav', dockableNav);
        }
    };

    document.addEventListener('alpine:init', registerAlpineData);
    document.addEventListener('livewire:init', registerAlpineData);
    if (window.Alpine) {
        registerAlpineData();
    }

})(window, document);
