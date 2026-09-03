/* global Sortable */
(function (global) {
    'use strict';

    const TAB_SIZE = 32;
    const MAX_LEVEL = 2;

    function clampLevel(value) {
        const level = Number.parseInt(String(value ?? 0), 10);
        return Number.isFinite(level) ? Math.max(0, Math.min(MAX_LEVEL, level)) : 0;
    }

    function directRows(container) {
        return container
            ? Array.from(container.querySelectorAll(':scope > [data-item-key]'))
            : [];
    }

    function eventPoint(event) {
        const touch = event?.touches?.[0] || event?.changedTouches?.[0];
        const source = touch || event;

        return {
            x: Number.isFinite(source?.clientX) ? source.clientX : null,
            y: Number.isFinite(source?.clientY) ? source.clientY : null,
        };
    }

    global.tenantNavigationBuilder = function tenantNavigationBuilder(initialSections, saveUrl, csrfToken, labels) {
        return {
            sections: Array.isArray(initialSections) ? initialSections : [],
            saveUrl,
            csrfToken,
            labels: labels || {},
            saving: false,
            dragging: false,
            sortableInstances: [],
            morphUnhook: null,
            dragState: null,
            pointerTracker: null,
            pendingPointer: { x: null, y: null },
            tabSize: TAB_SIZE,
            maxLevels: MAX_LEVEL + 1,
            isTree: true,

            init() {
                this.sections = this.normalizeSections(this.sections);
                this.$nextTick(() => this.initSortables());

                if (this.$wire?.$hook) {
                    this.morphUnhook = this.$wire.$hook('morphed', () => {
                        this.$nextTick(() => this.initSortables());
                    });
                }
            },

            normalizeSections(source) {
                const seen = new Set();
                const normalizeNodes = (nodes, level) => (Array.isArray(nodes) ? nodes : []).flatMap((item) => {
                    const key = String(item?.key || '').trim();
                    if (!key || seen.has(key)) return [];
                    seen.add(key);

                    return [{
                        key,
                        label: item.label || key,
                        visible: item.visible !== false,
                        level,
                        parent: null,
                        parent_id: null,
                        children: normalizeNodes(item.children, level + 1),
                    }];
                });

                return (Array.isArray(source) ? source : []).flatMap((section) => {
                    const key = String(section?.key || '').trim();
                    if (!key) return [];

                    return [{
                        key,
                        label: section.label || key,
                        items: normalizeNodes(section.items, 0),
                    }];
                });
            },

            flattenedItems(section) {
                const flattened = [];
                const visit = (items, level, parentId) => {
                    (items || []).forEach((item) => {
                        item.level = clampLevel(level);
                        item.parent = parentId;
                        item.parent_id = parentId;
                        flattened.push(item);
                        visit(item.children || [], level + 1, item.key);
                    });
                };
                visit(section?.items || [], 0, null);

                return flattened;
            },

            rememberPointer(event) {
                this.pendingPointer = eventPoint(event);
            },

            rowStyle(level) {
                return `--nav-indent: ${clampLevel(level) * TAB_SIZE}px`;
            },

            levelLabel(level) {
                if (clampLevel(level) === 2) return this.labels.level2 || 'Sub-sub-menu';
                if (clampLevel(level) === 1) return this.labels.level1 || 'Sub-menu';
                return this.labels.level0 || 'Main menu';
            },

            initSortables() {
                if (typeof Sortable === 'undefined' || this.dragging || Sortable.active) return;

                const root = this.$root;
                if (!root?.isConnected) return;

                const sectionContainer = root.querySelector('#nav-sections-container');
                const itemContainers = Array.from(root.querySelectorAll('.nav-items-container'));
                const liveContainers = new Set([sectionContainer, ...itemContainers].filter(Boolean));

                this.sortableInstances.forEach((instance) => {
                    if (!instance?.el || liveContainers.has(instance.el)) return;
                    try { instance.destroy(); } catch (error) { /* already detached */ }
                });

                const nextInstances = [];
                const ensureSortable = (element, options) => {
                    if (!element) return;
                    const instance = Sortable.get(element) || Sortable.create(element, options);
                    if (!nextInstances.includes(instance)) nextInstances.push(instance);
                };

                ensureSortable(sectionContainer, {
                    animation: 180,
                    handle: '.nav-section-drag-handle',
                    draggable: '>[data-section-key]',
                    ghostClass: 'nav-section-placeholder',
                    onStart: () => { this.dragging = true; },
                    onEnd: () => this.finishSectionDrag(),
                });

                itemContainers.forEach((element) => {
                    ensureSortable(element, {
                        animation: 180,
                        group: 'tenant-nav-items',
                        maxLevels: MAX_LEVEL + 1,
                        tabSize: TAB_SIZE,
                        isTree: true,
                        handle: '.nav-item-drag-handle',
                        draggable: '>[data-item-key]',
                        ghostClass: 'nav-drop-placeholder',
                        chosenClass: 'nav-item-chosen',
                        emptyInsertThreshold: 24,
                        onChoose: (event) => this.beginItemDrag(event),
                        onStart: (event) => this.startItemDrag(event),
                        onMove: (event, originalEvent) => {
                            const point = eventPoint(originalEvent || event.originalEvent);
                            this.updateDepthPreview(point.x, point.y);
                            return true;
                        },
                        onEnd: (event) => this.finishItemDrag(event),
                    });
                });

                this.sortableInstances = nextInstances;
            },

            beginItemDrag(event) {
                const item = event.item;
                const rows = directRows(item.parentElement);
                const index = rows.indexOf(item);
                const startLevel = clampLevel(item.dataset.navLevel);
                const descendants = [];

                for (let cursor = index + 1; cursor < rows.length; cursor += 1) {
                    if (clampLevel(rows[cursor].dataset.navLevel) <= startLevel) break;
                    descendants.push(rows[cursor]);
                }

                const originalPoint = eventPoint(event.originalEvent);
                const startX = originalPoint.x ?? this.pendingPointer.x;
                const startY = originalPoint.y ?? this.pendingPointer.y;
                const subtreeHeight = descendants.reduce(
                    (height, row) => Math.max(height, clampLevel(row.dataset.navLevel) - startLevel),
                    0
                );

                this.dragState = {
                    item,
                    startLevel,
                    previewLevel: startLevel,
                    startX,
                    lastX: startX,
                    lastY: startY,
                    descendants,
                    descendantLevels: new Map(descendants.map((row) => [row, clampLevel(row.dataset.navLevel)])),
                    maxLevel: Math.max(0, MAX_LEVEL - subtreeHeight),
                };
                this.applyDepthPreview(startLevel);
            },

            startItemDrag(event) {
                if (!this.dragState || this.dragState.item !== event.item) this.beginItemDrag(event);
                this.dragging = true;
                this.installPointerTracker();
                this.applyDepthPreview(this.dragState?.previewLevel ?? 0);
            },

            installPointerTracker() {
                this.removePointerTracker();
                this.pointerTracker = (event) => {
                    if (!this.dragging || !this.dragState) return;
                    const point = eventPoint(event);
                    this.updateDepthPreview(point.x, point.y);
                };

                document.addEventListener('dragover', this.pointerTracker, true);
                document.addEventListener('pointermove', this.pointerTracker, true);
                document.addEventListener('touchmove', this.pointerTracker, { capture: true, passive: true });
            },

            removePointerTracker() {
                if (!this.pointerTracker) return;
                document.removeEventListener('dragover', this.pointerTracker, true);
                document.removeEventListener('pointermove', this.pointerTracker, true);
                document.removeEventListener('touchmove', this.pointerTracker, true);
                this.pointerTracker = null;
            },

            updateDepthPreview(clientX, clientY) {
                const state = this.dragState;
                if (!state?.item) return;

                if (Number.isFinite(clientX)) {
                    if (!Number.isFinite(state.startX)) state.startX = clientX;
                    state.lastX = clientX;
                }
                if (Number.isFinite(clientY)) state.lastY = clientY;

                const deltaX = Number.isFinite(state.lastX) && Number.isFinite(state.startX)
                    ? state.lastX - state.startX
                    : 0;
                const snappedOffset = Math.max(0, state.startLevel * TAB_SIZE + deltaX);
                const requestedLevel = Math.round(snappedOffset / TAB_SIZE);
                const level = this.constrainLevelAtCurrentPosition(
                    state.item,
                    Math.min(state.maxLevel, requestedLevel)
                );

                state.previewLevel = level;
                this.applyDepthPreview(level);
            },

            constrainLevelAtCurrentPosition(item, requestedLevel) {
                const list = item?.parentElement;
                if (!list?.classList.contains('nav-items-container')) return 0;

                const excluded = new Set(this.dragState?.descendants || []);
                const rows = directRows(list).filter((row) => !excluded.has(row));
                const index = rows.indexOf(item);
                if (index <= 0) return 0;

                const previousLevel = clampLevel(rows[index - 1].dataset.navLevel);
                return Math.max(0, Math.min(MAX_LEVEL, previousLevel + 1, requestedLevel));
            },

            applyDepthPreview(level) {
                const state = this.dragState;
                if (!state?.item) return;

                const safeLevel = clampLevel(level);
                state.item.dataset.previewLevel = String(safeLevel);
                state.item.dataset.navLevel = String(safeLevel);
                state.item.style.setProperty('--nav-indent', `${safeLevel * TAB_SIZE}px`);
                state.item.classList.add('nav-depth-preview');

                const guide = state.item.querySelector(':scope > .nav-item-row .nav-depth-guide');
                if (guide) guide.textContent = this.levelLabel(safeLevel);

                const delta = safeLevel - state.startLevel;
                state.descendants.forEach((row) => {
                    const originalLevel = state.descendantLevels.get(row) ?? safeLevel;
                    const nextLevel = clampLevel(originalLevel + delta);
                    row.dataset.navLevel = String(nextLevel);
                    row.style.setProperty('--nav-indent', `${nextLevel * TAB_SIZE}px`);
                });
            },

            finishItemDrag(event) {
                const state = this.dragState || {
                    item: event.item,
                    descendants: [],
                    descendantLevels: new Map(),
                    startLevel: clampLevel(event.item?.dataset.navLevel),
                    previewLevel: clampLevel(event.item?.dataset.navLevel),
                };
                this.removePointerTracker();

                this.whenSortableIdle(() => {
                    const item = state.item;
                    if (item?.isConnected) {
                        const list = item.parentElement;
                        let anchor = item;
                        state.descendants.forEach((row) => {
                            if (!row?.isConnected || !list) return;
                            list.insertBefore(row, anchor.nextElementSibling);
                            anchor = row;
                        });

                        const level = this.constrainLevelAtCurrentPosition(item, state.previewLevel);
                        const delta = level - state.startLevel;
                        item.dataset.navLevel = String(level);
                        item.style.setProperty('--nav-indent', `${level * TAB_SIZE}px`);
                        state.descendants.forEach((row) => {
                            const originalLevel = state.descendantLevels.get(row) ?? level;
                            const descendantLevel = clampLevel(originalLevel + delta);
                            row.dataset.navLevel = String(descendantLevel);
                            row.style.setProperty('--nav-indent', `${descendantLevel * TAB_SIZE}px`);
                        });
                    }

                    this.clearDepthPreview(state.item);
                    this.dragState = null;
                    this.dragging = false;
                    this.syncFromDom();
                });
            },

            finishSectionDrag() {
                this.whenSortableIdle(() => {
                    this.dragging = false;
                    this.syncFromDom();
                });
            },

            whenSortableIdle(callback, attempts = 0) {
                setTimeout(() => {
                    if (typeof Sortable !== 'undefined' && Sortable.active) {
                        this.whenSortableIdle(callback, attempts + 1);
                        return;
                    }
                    callback();
                }, attempts === 0 ? 0 : 16);
            },

            clearDepthPreview(item) {
                if (!item) return;
                item.classList.remove('nav-depth-preview');
                delete item.dataset.previewLevel;
            },

            itemLookup() {
                const lookup = new Map();
                const visit = (items) => (items || []).forEach((item) => {
                    lookup.set(item.key, item);
                    visit(item.children || []);
                });
                this.sections.forEach((section) => visit(section.items));
                return lookup;
            },

            syncFromDom() {
                const root = this.$root;
                const sectionContainer = root?.querySelector('#nav-sections-container');
                if (!sectionContainer) return;

                const currentSections = new Map(this.sections.map((section) => [section.key, section]));
                const lookup = this.itemLookup();
                const nextSections = [];

                sectionContainer.querySelectorAll(':scope > [data-section-key]').forEach((sectionElement) => {
                    const sectionKey = sectionElement.dataset.sectionKey;
                    const existingSection = currentSections.get(sectionKey);
                    if (!existingSection) return;

                    const roots = [];
                    const stack = [];
                    let previousLevel = 0;

                    directRows(sectionElement.querySelector(':scope > .nav-items-container')).forEach((row, index) => {
                        const existing = lookup.get(row.dataset.itemKey);
                        if (!existing) return;

                        let level = clampLevel(row.dataset.navLevel);
                        level = index === 0 ? 0 : Math.min(level, previousLevel + 1);
                        previousLevel = level;

                        const node = {
                            key: existing.key,
                            label: existing.label,
                            visible: existing.visible !== false,
                            level,
                            parent: null,
                            parent_id: null,
                            children: [],
                        };

                        if (level === 0 || !stack[level - 1]) {
                            node.level = 0;
                            roots.push(node);
                            stack.length = 1;
                            stack[0] = node;
                        } else {
                            const parent = stack[level - 1];
                            node.parent = parent.key;
                            node.parent_id = parent.key;
                            parent.children.push(node);
                            stack.length = level + 1;
                            stack[level] = node;
                        }
                    });

                    nextSections.push({
                        key: existingSection.key,
                        label: existingSection.label,
                        items: roots,
                    });
                });

                this.sections = nextSections;
                this.$nextTick(() => this.initSortables());
            },

            serialize() {
                const items = [];
                const tree = this.sections.map((section, sectionOrder) => {
                    const serializeNodes = (nodes, parentId, level) => nodes.map((item, order) => {
                        const safeLevel = clampLevel(level);
                        const node = {
                            key: item.key,
                            section: section.key,
                            parent: parentId,
                            parent_id: parentId,
                            level: safeLevel,
                            order,
                            visible: item.visible !== false,
                            children: serializeNodes(item.children || [], item.key, safeLevel + 1),
                        };
                        items.push({
                            key: node.key,
                            section: node.section,
                            parent: node.parent,
                            parent_id: node.parent_id,
                            level: node.level,
                            order: node.order,
                            visible: node.visible,
                        });
                        return node;
                    });

                    return {
                        key: section.key,
                        order: sectionOrder,
                        items: serializeNodes(section.items || [], null, 0),
                    };
                });

                return {
                    sections: tree.map((section) => ({ key: section.key, order: section.order })),
                    items,
                    tree,
                };
            },

            async save() {
                this.saving = true;
                try {
                    const response = await fetch(this.saveUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': this.csrfToken,
                        },
                        body: JSON.stringify(this.serialize()),
                    });
                    const payload = await response.json().catch(() => ({}));
                    if (!response.ok) {
                        throw new Error(payload.message || this.labels.saveError || 'The navigation menu could not be saved.');
                    }
                    global.dispatchEvent(new CustomEvent('tenant-navigation-updated', {
                        detail: { nav: payload.nav || this.serialize() },
                    }));
                    global.dispatchEvent(new CustomEvent('notify', {
                        detail: {
                            type: 'success',
                            message: payload.message || this.labels.saved || 'Navigation menu updated.',
                        },
                    }));
                } catch (error) {
                    global.dispatchEvent(new CustomEvent('notify', {
                        detail: {
                            type: 'error',
                            message: error.message || this.labels.saveError || 'The navigation menu could not be saved.',
                        },
                    }));
                } finally {
                    this.saving = false;
                }
            },

            destroy() {
                this.removePointerTracker();
                if (this.morphUnhook) {
                    this.morphUnhook();
                    this.morphUnhook = null;
                }

                const instances = this.sortableInstances.slice();
                this.sortableInstances = [];
                const destroyWhenIdle = (attempts = 0) => {
                    if (typeof Sortable !== 'undefined' && Sortable.active) {
                        setTimeout(() => destroyWhenIdle(attempts + 1), 16);
                        return;
                    }
                    instances.forEach((instance) => {
                        try { if (instance?.el) instance.destroy(); } catch (error) { /* already detached */ }
                    });
                };
                destroyWhenIdle();
            },
        };
    };
})(window);
