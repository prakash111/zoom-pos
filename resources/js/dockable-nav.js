/**
 * Draggable & Dockable Multi-Position Navigation Menu
 * Supports:
 * - Default Compact 6-Item Preset vs. Full Menu Customization Logic
 * - Operating Mode Conditional Navigation (General Retail vs Food & Restaurant Mode)
 * - Docking Modes: Fixed Docked vs. Free Floating / Draggable
 * - Positions: Left, Right, Top, Bottom, Floating
 * - 4 Layout Structures: Slim Icon Rail, Expanded Full Sidebar, macOS-Style Floating Dock, Compact Speed-Dial
 * - 4 Visual Theme Styles: Signature Violet Glow, Glassmorphism Frosted Acrylic, High-Density Enterprise Flat Dark, Clean Neumorphic Soft Light
 * - Custom Dock Background (Gradients / Solid / Glass)
 * - Dynamic UI Theme Accent / Primary Color (Dashboard Hero, Action Buttons, Badges)
 * - Selectable & Pinnable Menu Items (Show/Hide specific navigation items with Select All, Default 6, and Reset)
 * - State persistence in localStorage with anti-flicker head initialization.
 */

export const ALL_DOCK_ITEMS = [
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

export const ADMIN_DOCK_ITEMS = [
    { key: 'dashboard', label: 'Dashboard Overview', icon: '📊', route: 'superadmin.dashboard' },
    { key: 'tenants', label: 'Tenant Stores', icon: '🏢', route: 'superadmin.tenants.index' },
    { key: 'plans', label: 'SaaS Plans & Pricing', icon: '👑', route: 'superadmin.plans.index' },
    { key: 'taxes', label: 'Global Tax Engine', icon: '⚖️', route: 'superadmin.tax.index' },
    { key: 'menus', label: 'Menu Builder', icon: '🧭', route: 'superadmin.menus.index' },
    { key: 'pages', label: 'CMS Custom Pages', icon: '📄', route: 'superadmin.pages.index' },
    { key: 'settings', label: 'Platform Settings', icon: '⚙️', route: 'superadmin.settings.index' },
    { key: 'smtp', label: 'SMTP & Mail Config', icon: '✉️', route: 'superadmin.smtp.index' }
];

export function dockableNav(storageKey = 'sa_dock_nav_state', defaultPosition = 'left', operatingMode = 'general') {
    return {
        storageKey: storageKey,
        operatingMode: operatingMode, // 'general' | 'restaurant' | 'food_restaurant' | 'admin'
        position: defaultPosition, // 'left' | 'right' | 'top' | 'bottom' | 'floating'
        mode: 'docked',            // 'docked' | 'floating'
        layout: 'slim',            // 'slim' | 'expanded' | 'macos-dock' | 'speed-dial'
        theme: 'violet',           // 'violet' | 'glass' | 'enterprise' | 'neumorphic'
        sticky: false,             // Sticky viewport pinning
        customBg: '',              // Custom background (gradient / solid / glass)
        uiAccentColor: '#2563eb',   // Dynamic UI Primary / Accent Color
        navTextColor: '#ffffff',   // Default Inactive Nav Text Color
        navTextActiveColor: '#60a5fa', // Active / Highlight Nav Text Color
        visibleItems: [],
        availableDockItems: [],
        adminDockItems: ADMIN_DOCK_ITEMS,
        visibleAdminItems: (function() {
            try {
                const raw = localStorage.getItem('nav_visible_items');
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
        snapZone: null,            // 'left' | 'right' | 'top' | 'bottom' | null
        showQuickMenu: false,
        showCustomizerModal: false,
        speedDialOpen: false,

        getDefaultKeys() {
            if (this.storageKey === 'sa_dock_nav_state' || this.operatingMode === 'admin' || this.operatingMode === 'superadmin') {
                return ['dashboard', 'tenants', 'plans', 'taxes', 'settings', 'smtp'];
            }
            const isRest = this.operatingMode === 'restaurant' || this.operatingMode === 'food_restaurant';
            return isRest
                ? ['home', 'restaurant_pos', 'tables', 'kds', 'sales', 'products']
                : ['home', 'pos', 'sales', 'quotes', 'products', 'categories'];
        },

        selectAllAdminItems() {
            this.visibleAdminItems = this.adminDockItems.map(i => i.key);
            this.persistAdminDock();
        },

        resetAdminDefaultItems() {
            this.visibleAdminItems = ['dashboard', 'tenants', 'plans', 'taxes', 'settings', 'smtp'];
            this.persistAdminDock();
        },

        persistAdminDock() {
            localStorage.setItem('nav_visible_items', JSON.stringify(this.visibleAdminItems));
            this.visibleItems = [...this.visibleAdminItems];
            this.saveState();
            window.dispatchEvent(new CustomEvent('dock-nav-update', { detail: { visibleItems: this.visibleAdminItems, visibleAdminItems: this.visibleAdminItems } }));
        },

        init() {
            const isAdmin = this.storageKey === 'sa_dock_nav_state' || this.operatingMode === 'admin' || this.operatingMode === 'superadmin';
            const platformDefaults = window.platformAppearanceDefaults || {};
            let useStoredState = true;
            try {
                const candidate = JSON.parse(localStorage.getItem(this.storageKey) || 'null');
                useStoredState = !platformDefaults.version || (candidate && String(candidate.defaultVersion || '') === String(platformDefaults.version));
            } catch (e) {
                useStoredState = false;
            }
            if (!useStoredState) {
                this.position = platformDefaults.position || defaultPosition;
                this.mode = platformDefaults.mode || 'docked';
                this.layout = platformDefaults.layout || 'slim';
                this.customBg = platformDefaults.customBg || '';
                this.uiAccentColor = platformDefaults.uiAccentColor || '#2563eb';
                this.navTextColor = platformDefaults.navTextColor || '#ffffff';
                this.navTextActiveColor = platformDefaults.navTextActiveColor || '#60a5fa';
                if (isAdmin && Array.isArray(platformDefaults.visibleItems)) this.visibleAdminItems = [...platformDefaults.visibleItems];
            }
            let validKeys = [];
            const defaultKeys = this.getDefaultKeys();

            try {
                if (isAdmin) {
                    this.availableDockItems = [...ADMIN_DOCK_ITEMS];
                    const validAdminKeys = this.adminDockItems.map(i => i.key);
                    try {
                        const storedAdmin = useStoredState ? localStorage.getItem('nav_visible_items') : null;
                        if (storedAdmin) {
                            const parsed = JSON.parse(storedAdmin);
                            if (Array.isArray(parsed)) {
                                this.visibleAdminItems = parsed.filter(k => validAdminKeys.includes(k));
                            }
                        }
                    } catch(e) {}
                    this.visibleItems = [...this.visibleAdminItems];
                } else {
                    // Compute available items strictly according to operating mode
                    const isRest = this.operatingMode === 'restaurant' || this.operatingMode === 'food_restaurant';
                    this.availableDockItems = ALL_DOCK_ITEMS.filter(item => {
                        if (item.mode === 'all') return true;
                        if (isRest) return item.mode === 'restaurant';
                        return item.mode === 'retail';
                    });

                    validKeys = this.availableDockItems.map(i => i.key);
                    this.visibleItems = [...defaultKeys];

                    // Read stored configuration immediately
                    try {
                        const storedItems = localStorage.getItem('dock_visible_items');
                        if (storedItems) {
                            const parsedItems = JSON.parse(storedItems);
                            if (Array.isArray(parsedItems)) {
                                this.visibleItems = parsedItems.filter(k => validKeys.includes(k));
                            }
                        }
                    } catch(e) {}
                }

                const raw = useStoredState ? localStorage.getItem(this.storageKey) : null;
                if (raw) {
                    const saved = JSON.parse(raw);
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
                            this.visibleItems = saved.visibleItems.filter(k => validKeys.includes(k));
                        }
                        this.x = typeof saved.x === 'number' ? saved.x : 24;
                        this.y = typeof saved.y === 'number' ? saved.y : 100;
                    }
                }

                const storedSticky = useStoredState ? localStorage.getItem('nav_sticky') : null;
                if (storedSticky !== null) {
                    this.sticky = storedSticky === 'true';
                }

                const storedBg = useStoredState ? localStorage.getItem('nav_dock_bg') : null;
                if (storedBg) {
                    this.customBg = storedBg;
                }

                const storedAccent = useStoredState ? localStorage.getItem('ui_accent_color') : null;
                if (storedAccent) {
                    this.uiAccentColor = storedAccent;
                }

                const storedNavTextColor = useStoredState ? localStorage.getItem('nav_text_color') : null;
                if (storedNavTextColor) {
                    this.navTextColor = storedNavTextColor;
                }

                const storedNavTextActive = useStoredState ? localStorage.getItem('nav_text_active_color') : null;
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
                this.visibleItems = [...defaultKeys];
            }

            this.applyDomAttributes();
            this.applyDynamicCssVars();

            // Handle window resize boundary clamping
            window.addEventListener('resize', () => {
                if (this.position === 'floating' || this.mode === 'floating') {
                    this.clampCoordinates();
                }
            });

            // Global event listeners
            window.addEventListener('reset-dock-nav', () => this.resetAll());
            window.addEventListener('open-dock-customizer', () => { this.showCustomizerModal = true; });
            window.addEventListener('set-dock-nav-mode', (e) => {
                if (e.detail && e.detail.mode) {
                    this.setMode(e.detail.mode);
                }
            });
            window.addEventListener('set-dock-nav-pos', (e) => {
                if (e.detail && e.detail.position) {
                    this.setPosition(e.detail.position);
                }
            });
            window.addEventListener('set-dock-nav-layout', (e) => {
                if (e.detail && e.detail.layout) {
                    this.setLayout(e.detail.layout);
                }
            });
            window.addEventListener('set-dock-nav-theme', (e) => {
                if (e.detail && e.detail.theme) {
                    this.setTheme(e.detail.theme);
                }
            });
            window.addEventListener('set-dock-nav-sticky', (e) => {
                if (e.detail && typeof e.detail.sticky !== 'undefined') {
                    this.setSticky(e.detail.sticky);
                }
            });
            window.addEventListener('set-dock-nav-bg', (e) => {
                if (e.detail && typeof e.detail.bg !== 'undefined') {
                    this.setCustomBg(e.detail.bg);
                }
            });
            window.addEventListener('set-ui-accent-color', (e) => {
                if (e.detail && e.detail.color) {
                    this.setUiAccentColor(e.detail.color);
                }
            });
            window.addEventListener('set-nav-text-color', (e) => {
                if (e.detail && e.detail.color) {
                    this.setNavTextColor(e.detail.color);
                }
            });
            window.addEventListener('set-nav-text-active-color', (e) => {
                if (e.detail && e.detail.color) {
                    this.setNavTextActiveColor(e.detail.color);
                }
            });
            window.addEventListener('operating-mode-updated', (e) => {
                if (e.detail && e.detail.mode) {
                    this.operatingMode = e.detail.mode;
                    const isRest = this.operatingMode === 'restaurant' || this.operatingMode === 'food_restaurant';
                    this.availableDockItems = ALL_DOCK_ITEMS.filter(item => {
                        if (item.mode === 'all') return true;
                        if (isRest) return item.mode === 'restaurant';
                        return item.mode === 'retail';
                    });
                    const validKeys = this.availableDockItems.map(i => i.key);
                    this.visibleItems = this.visibleItems.filter(k => validKeys.includes(k));
                    if (this.visibleItems.length === 0) {
                        this.visibleItems = [...this.getDefaultKeys()];
                    }
                    this.saveState();
                }
            });
        },

        applyDomAttributes() {
            document.documentElement.setAttribute('data-dock-pos', this.position);
            document.documentElement.setAttribute('data-dock-mode', this.mode);
            document.documentElement.setAttribute('data-nav-layout', this.layout);
            document.documentElement.setAttribute('data-nav-theme', this.theme);
            document.documentElement.setAttribute('data-nav-sticky', this.sticky ? 'true' : 'false');
            document.documentElement.setAttribute('data-pos-mode', this.operatingMode || 'general');
        },

        applyDynamicCssVars() {
            const root = document.documentElement;

            if (this.uiAccentColor) {
                const hex = this.uiAccentColor;
                const darkHex = this.adjustBrightness(hex, -20);
                const lightRgba = this.hexToRgba(hex, 0.12);

                root.style.setProperty('--color-primary', hex);
                root.style.setProperty('--color-primary-hover', darkHex);
                root.style.setProperty('--color-primary-light', lightRgba);
                root.style.setProperty('--color-primary-gradient', `linear-gradient(135deg, ${hex}, ${darkHex})`);
            }

            if (this.navTextColor) {
                root.style.setProperty('--nav-item-color', this.navTextColor);
            }
            if (this.navTextActiveColor) {
                root.style.setProperty('--nav-item-active-color', this.navTextActiveColor);
            }
        },

        hexToRgba(hex, alpha = 0.1) {
            const cleanHex = hex.replace('#', '');
            if (cleanHex.length === 3) {
                const r = parseInt(cleanHex[0] + cleanHex[0], 16);
                const g = parseInt(cleanHex[1] + cleanHex[1], 16);
                const b = parseInt(cleanHex[2] + cleanHex[2], 16);
                return `rgba(${r}, ${g}, ${b}, ${alpha})`;
            } else if (cleanHex.length === 6) {
                const r = parseInt(cleanHex.substring(0, 2), 16);
                const g = parseInt(cleanHex.substring(2, 4), 16);
                const b = parseInt(cleanHex.substring(4, 6), 16);
                return `rgba(${r}, ${g}, ${b}, ${alpha})`;
            }
            return `rgba(37, 99, 235, ${alpha})`;
        },

        adjustBrightness(hex, percent) {
            const num = parseInt(hex.replace('#', ''), 16);
            const amt = Math.round(2.55 * percent);
            const R = (num >> 16) + amt;
            const G = (num >> 8 & 0x00FF) + amt;
            const B = (num & 0x0000FF) + amt;
            return '#' + (0x1000000 + (R < 255 ? R < 1 ? 0 : R : 255) * 0x10000 +
                (G < 255 ? G < 1 ? 0 : G : 255) * 0x100 +
                (B < 255 ? B < 1 ? 0 : B : 255)).toString(16).slice(1);
        },

        scrollNav(amount) {
            const el = this.$refs.scrollNavContainer || (this.$el ? this.$el.querySelector('[data-dock-scroll-container]') : null);
            if (el) {
                el.scrollBy({ left: amount, behavior: 'smooth' });
            }
        },

        saveState() {
            this.applyDomAttributes();
            this.applyDynamicCssVars();
            try {
                localStorage.setItem(this.storageKey, JSON.stringify({
                    defaultVersion: String((window.platformAppearanceDefaults || {}).version || ''),
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

        setPosition(newPos) {
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

        setMode(newMode) {
            this.mode = newMode;
            if (newMode === 'floating') {
                this.position = 'floating';
                this.clampCoordinates();
            } else if (this.position === 'floating') {
                this.position = defaultPosition;
            }
            this.saveState();
        },

        setLayout(newLayout) {
            this.layout = newLayout;
            if (newLayout === 'macos-dock' && (this.position === 'left' || this.position === 'right')) {
                this.position = 'bottom';
            }
            this.saveState();
        },

        setTheme(newTheme) {
            this.theme = newTheme;
            this.saveState();
        },

        setSticky(val) {
            this.sticky = !!val;
            this.saveState();
        },

        setCustomBg(bg) {
            this.customBg = bg || '';
            this.saveState();
        },

        setUiAccentColor(color) {
            this.uiAccentColor = color || '#2563eb';
            this.applyDynamicCssVars();
            this.saveState();
        },

        setNavTextColor(color) {
            this.navTextColor = color || '#ffffff';
            this.applyDynamicCssVars();
            this.saveState();
        },

        setNavTextActiveColor(color) {
            this.navTextActiveColor = color || '#60a5fa';
            this.applyDynamicCssVars();
            this.saveState();
        },

        isItemVisible(itemKey) {
            const isRest = this.operatingMode === 'restaurant' || this.operatingMode === 'food_restaurant';
            const itemDef = ALL_DOCK_ITEMS.find(i => i.key === itemKey);
            if (itemDef) {
                if (!isRest && itemDef.mode === 'restaurant') return false;
                if (isRest && itemDef.mode === 'retail') return false;
            }
            return this.visibleItems.includes(itemKey);
        },

        toggleItem(itemKey) {
            if (this.visibleItems.includes(itemKey)) {
                this.visibleItems = this.visibleItems.filter(k => k !== itemKey);
            } else {
                this.visibleItems.push(itemKey);
            }
            this.saveState();
        },

        selectAllItems() {
            this.visibleItems = this.availableDockItems.map(i => i.key);
            this.saveState();
        },

        selectDefaultItems() {
            this.visibleItems = [...this.getDefaultKeys()];
            this.saveState();
        },

        uncheckAllItems() {
            this.visibleItems = [this.getDefaultKeys()[0] || 'home'];
            this.saveState();
        },

        resetItems() {
            this.selectDefaultItems();
        },

        resetAll() {
            this.position = 'left';
            this.mode = 'docked';
            this.layout = 'slim';
            this.theme = 'violet';
            this.sticky = false;
            this.customBg = '';
            this.uiAccentColor = '#2563eb';
            this.navTextColor = '#ffffff';
            this.navTextActiveColor = '#60a5fa';
            this.visibleItems = [...this.getDefaultKeys()];
            this.x = 24;
            this.y = 100;
            this.showQuickMenu = false;
            this.showCustomizerModal = false;
            this.speedDialOpen = false;
            this.snapZone = null;
            this.saveState();
        },

        toggleQuickMenu() {
            this.showQuickMenu = !this.showQuickMenu;
        },

        toggleCustomizerModal() {
            this.showCustomizerModal = !this.showCustomizerModal;
            this.showQuickMenu = false;
        },

        toggleSpeedDial() {
            this.speedDialOpen = !this.speedDialOpen;
        },

        clampCoordinates() {
            const menuEl = this.$refs.dockNavEl;
            const w = menuEl ? menuEl.offsetWidth : 80;
            const h = menuEl ? menuEl.offsetHeight : 300;
            const maxX = Math.max(10, window.innerWidth - w - 10);
            const maxY = Math.max(10, window.innerHeight - h - 10);

            this.x = Math.max(10, Math.min(maxX, this.x));
            this.y = Math.max(10, Math.min(maxY, this.y));
        },

        startDrag(event) {
            if (event.target.closest('button:not([data-drag-handle]), a, input, select, [data-prevent-drag]')) {
                return;
            }

            event.preventDefault();
            this.showQuickMenu = false;
            this.isDragging = true;
            this.snapZone = null;

            const clientX = event.touches ? event.touches[0].clientX : event.clientX;
            const clientY = event.touches ? event.touches[0].clientY : event.clientY;

            this.dragStartX = clientX;
            this.dragStartY = clientY;

            if (this.position !== 'floating') {
                const navEl = this.$refs.dockNavEl;
                const rect = navEl ? navEl.getBoundingClientRect() : { left: clientX - 40, top: clientY - 40 };
                this.startPosX = rect.left;
                this.startPosY = rect.top;
                this.x = rect.left;
                this.y = rect.top;
            } else {
                this.startPosX = this.x;
                this.startPosY = this.y;
            }

            const onPointerMove = (e) => {
                if (!this.isDragging) return;
                const currentX = e.touches ? e.touches[0].clientX : e.clientX;
                const currentY = e.touches ? e.touches[0].clientY : e.clientY;

                const deltaX = currentX - this.dragStartX;
                const deltaY = currentY - this.dragStartY;

                this.x = this.startPosX + deltaX;
                this.y = this.startPosY + deltaY;

                const screenW = window.innerWidth;
                const screenH = window.innerHeight;
                const threshold = 90;

                if (currentX <= threshold) {
                    this.snapZone = 'left';
                } else if (currentX >= screenW - threshold) {
                    this.snapZone = 'right';
                } else if (currentY <= threshold) {
                    this.snapZone = 'top';
                } else if (currentY >= screenH - threshold) {
                    this.snapZone = 'bottom';
                } else {
                    this.snapZone = null;
                }
            };

            const onPointerUp = (e) => {
                if (!this.isDragging) return;
                this.isDragging = false;

                window.removeEventListener('mousemove', onPointerMove);
                window.removeEventListener('mouseup', onPointerUp);
                window.removeEventListener('touchmove', onPointerMove);
                window.removeEventListener('touchend', onPointerUp);

                if (this.snapZone) {
                    this.position = this.snapZone;
                    this.mode = 'docked';
                } else {
                    this.position = 'floating';
                    this.mode = 'floating';
                    this.clampCoordinates();
                }

                this.snapZone = null;
                this.saveState();
            };

            window.addEventListener('mousemove', onPointerMove);
            window.addEventListener('mouseup', onPointerUp);
            window.addEventListener('touchmove', onPointerMove, { passive: false });
            window.addEventListener('touchend', onPointerUp);
        }
    };
}

if (typeof window !== 'undefined') {
    window.dockableNav = dockableNav;
    document.addEventListener('alpine:init', () => {
        if (window.Alpine) {
            window.Alpine.data('dockableNav', dockableNav);
        }
    });
}
