/*
 * KUSUG-PA searchable select
 * --------------------------------------------------------------------------
 * Makes every single-choice <select> in the system searchable, including ones
 * added later (Alpine x-for, SweetAlert popups, AJAX-filled week lists).
 *
 * The native <select> is never replaced or hidden: it keeps its value, styling,
 * `required` validation, `disabled` state, onchange handlers and Alpine x-model.
 * Only the dropdown is swapped: opening a select shows a search box + list
 * (an anchored popover on desktop, a bottom sheet on phones). Picking an item
 * sets the select's value and fires the usual `input` and `change` events.
 *
 * Opt out: add `data-native` to a <select> (or any ancestor) to keep the
 * browser's own dropdown. <select multiple> and <select size="n"> are skipped.
 */
(function () {
    'use strict';

    if (window.KpSelect) return;

    const MAX_RENDER = 400;          // options drawn at once; typing narrows the rest
    const TAP_SLOP = 10;             // px a finger may move and still count as a tap
    const SEARCH_ICON = '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="9" cy="9" r="6"/><path d="m14 14 4 4" stroke-linecap="round"/></svg>';

    let state = null;                // the one open dropdown
    let touch = null;                // in-progress tap on a select
    let lastTouchAt = 0;
    let focusingSelf = false;        // true while this script moves focus to a select
    let uid = 0;

    // ---------- Helpers ----------

    function isEnhanceable(el) {
        return el instanceof HTMLSelectElement &&
            !el.multiple &&
            !(el.size > 1) &&
            !el.disabled &&
            !el.matches(':disabled') &&
            !el.closest('[data-native]');
    }

    function focusSelect(select) {
        focusingSelf = true;
        try {
            select.focus({ preventScroll: true });
        } finally {
            focusingSelf = false;
        }
    }

    function selectFromEvent(e) {
        const t = e.target;
        return t && t.closest ? t.closest('select') : null;
    }

    function normalize(s) {
        return String(s || '')
            .normalize('NFD')
            .replace(/[̀-ͯ]/g, '')
            .toLowerCase()
            .replace(/\s+/g, ' ')
            .trim();
    }

    function useSheet() {
        return window.matchMedia('(max-width: 640px)').matches ||
            (window.matchMedia('(pointer: coarse)').matches && window.innerWidth < 900);
    }

    function labelFor(select) {
        let text = '';
        if (select.labels && select.labels.length) {
            text = select.labels[0].textContent;
        } else if (select.getAttribute('aria-label')) {
            text = select.getAttribute('aria-label');
        } else {
            const prev = select.previousElementSibling;
            const label = prev && prev.tagName === 'LABEL' ? prev : (select.parentElement && select.parentElement.querySelector(':scope > label'));
            if (label) text = label.textContent;
        }
        text = text.replace(/\*/g, '').replace(/\s+/g, ' ').trim();
        return text || 'Select an option';
    }

    function collectItems(select) {
        const items = [];
        const addOption = (opt, group) => {
            if (opt.hidden) return;
            const text = (opt.label || opt.text || '').replace(/\s+/g, ' ').trim();
            items.push({
                type: 'option',
                opt,
                text: text || ' ',
                group,
                disabled: opt.disabled || (group && group.disabled),
                search: normalize(text + ' ' + opt.value),
            });
        };
        Array.from(select.children).forEach(child => {
            if (child.tagName === 'OPTION') {
                addOption(child, null);
            } else if (child.tagName === 'OPTGROUP') {
                const group = { type: 'group', text: child.label, disabled: child.disabled };
                items.push(group);
                Array.from(child.children).forEach(opt => opt.tagName === 'OPTION' && addOption(opt, group));
            }
        });
        return items;
    }

    // ---------- Open / close ----------

    function open(select, initialQuery) {
        if (state && state.select === select) {
            close(true);
            return;
        }
        close(false);
        if (!isEnhanceable(select) || !select.isConnected) return;

        const sheet = useSheet();
        const id = 'kp-ss-' + (++uid);
        const host = select.closest('dialog[open]') || document.body;

        const root = document.createElement('div');
        root.className = 'kp-ss-pop' + (sheet ? ' kp-ss-sheet' : '');
        root.setAttribute('role', 'dialog');
        root.setAttribute('aria-label', labelFor(select));
        root.innerHTML =
            (sheet ? '<div class="kp-ss-head"><span></span><button type="button" class="kp-ss-close" aria-label="Close">&times;</button></div>' : '') +
            '<div class="kp-ss-search">' + SEARCH_ICON +
            '<input type="search" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" enterkeyhint="done" role="combobox" aria-autocomplete="list" aria-expanded="true">' +
            '</div>' +
            '<ul class="kp-ss-list" role="listbox"></ul>';

        const input = root.querySelector('input');
        const list = root.querySelector('.kp-ss-list');
        list.id = id + '-list';
        input.setAttribute('aria-controls', list.id);
        input.placeholder = 'Search ' + labelFor(select).toLowerCase() + '…';
        if (sheet) root.querySelector('.kp-ss-head span').textContent = labelFor(select);

        let backdrop = null;
        if (sheet) {
            backdrop = document.createElement('div');
            backdrop.className = 'kp-ss-backdrop';
            backdrop.addEventListener('touchmove', e => e.preventDefault(), { passive: false });
            backdrop.addEventListener('click', () => close(false));
            host.appendChild(backdrop);
        }
        host.appendChild(root);

        state = {
            select, root, input, list, backdrop, sheet, id,
            items: [], visible: [], active: -1,
            prevOverflow: document.documentElement.style.overflow,
            observer: null,
        };

        // Keep the list in step if options change while it is open (e.g. weeks loading)
        state.observer = new MutationObserver(() => {
            if (!state || state.select !== select) return;
            if (!select.isConnected || !isEnhanceable(select)) {
                close(false);
                return;
            }
            refresh();
        });
        state.observer.observe(select, { childList: true, subtree: true, characterData: true, attributes: true, attributeFilter: ['disabled', 'label', 'hidden'] });

        input.addEventListener('input', () => render(input.value));
        input.addEventListener('keydown', onSearchKeydown);
        list.addEventListener('click', onListClick);
        list.addEventListener('mousemove', onListHover);
        list.addEventListener('mousedown', e => e.preventDefault()); // keep focus in the search box
        if (sheet) {
            root.querySelector('.kp-ss-close').addEventListener('click', () => close(false));
            document.documentElement.style.overflow = 'hidden';
        }

        document.addEventListener('pointerdown', onOutsidePointer, true);
        window.addEventListener('resize', position);
        window.addEventListener('scroll', onScroll, true);
        if (window.visualViewport) {
            window.visualViewport.addEventListener('resize', position);
            window.visualViewport.addEventListener('scroll', position);
        }

        select.setAttribute('aria-expanded', 'true');
        if (initialQuery) input.value = initialQuery;
        refresh();
        position();

        // On phones, don't pop the keyboard over the list; a tap on the search box brings it up
        if (!sheet) input.focus({ preventScroll: true });
        scrollActiveIntoView();
    }

    function close(restoreFocus) {
        if (!state) return;
        const s = state;
        state = null;

        s.observer && s.observer.disconnect();
        s.root.remove();
        s.backdrop && s.backdrop.remove();
        if (s.sheet) document.documentElement.style.overflow = s.prevOverflow;

        document.removeEventListener('pointerdown', onOutsidePointer, true);
        window.removeEventListener('resize', position);
        window.removeEventListener('scroll', onScroll, true);
        if (window.visualViewport) {
            window.visualViewport.removeEventListener('resize', position);
            window.visualViewport.removeEventListener('scroll', position);
        }

        s.select.setAttribute('aria-expanded', 'false');
        if (restoreFocus && !s.sheet && s.select.isConnected) {
            focusSelect(s.select);
        }
    }

    // ---------- Rendering ----------

    function refresh() {
        state.items = collectItems(state.select);
        render(state.input.value);
    }

    function render(query) {
        const { list, items, select, id } = state;
        const terms = normalize(query).split(' ').filter(Boolean);
        const matches = item => terms.every(t => item.search.includes(t));

        const frag = document.createDocumentFragment();
        const visible = [];
        let shownGroup = null;
        let total = 0;

        for (const item of items) {
            if (item.type !== 'option' || !matches(item)) continue;
            total++;
            if (visible.length >= MAX_RENDER) continue;

            if (item.group && item.group !== shownGroup) {
                const g = document.createElement('li');
                g.className = 'kp-ss-group';
                g.setAttribute('role', 'presentation');
                g.textContent = item.group.text;
                frag.appendChild(g);
                shownGroup = item.group;
            } else if (!item.group) {
                shownGroup = null;
            }

            const li = document.createElement('li');
            li.className = 'kp-ss-opt';
            li.id = id + '-o' + visible.length;
            li.dataset.i = visible.length;
            li.setAttribute('role', 'option');
            li.setAttribute('aria-selected', item.opt.selected ? 'true' : 'false');
            if (item.disabled) li.setAttribute('aria-disabled', 'true');
            const span = document.createElement('span');
            span.textContent = item.text;
            li.appendChild(span);
            frag.appendChild(li);
            visible.push(item);
        }

        if (total === 0) {
            const empty = document.createElement('li');
            empty.className = 'kp-ss-empty';
            empty.textContent = items.some(i => i.type === 'option') ? 'No matches found' : 'No options available';
            frag.appendChild(empty);
        } else if (total > visible.length) {
            const more = document.createElement('li');
            more.className = 'kp-ss-more';
            more.textContent = 'Showing ' + visible.length + ' of ' + total + ' — keep typing to narrow down';
            frag.appendChild(more);
        }

        list.replaceChildren(frag);
        state.visible = visible;

        // Start on the current value when it is in view, otherwise the first choosable item
        let active = visible.findIndex(v => v.opt === select.options[select.selectedIndex] && !v.disabled);
        if (active === -1 || terms.length) active = visible.findIndex(v => !v.disabled);
        setActive(active);
    }

    function setActive(i) {
        const { list, input, id } = state;
        const prev = list.querySelector('.kp-ss-opt.is-active');
        prev && prev.classList.remove('is-active');
        state.active = i;
        if (i < 0) {
            input.removeAttribute('aria-activedescendant');
            return;
        }
        const el = list.querySelector('[data-i="' + i + '"]');
        if (el) {
            el.classList.add('is-active');
            input.setAttribute('aria-activedescendant', id + '-o' + i);
        }
    }

    function scrollActiveIntoView() {
        if (!state || state.active < 0) return;
        const el = state.list.querySelector('[data-i="' + state.active + '"]');
        el && el.scrollIntoView({ block: 'nearest' });
    }

    function moveActive(delta) {
        const { visible } = state;
        if (!visible.length) return;
        let i = state.active;
        for (let step = 0; step < visible.length; step++) {
            i = Math.min(visible.length - 1, Math.max(0, i + delta));
            if (!visible[i].disabled) break;
            if ((delta > 0 && i === visible.length - 1) || (delta < 0 && i === 0)) return;
        }
        if (!visible[i].disabled) {
            setActive(i);
            scrollActiveIntoView();
        }
    }

    // ---------- Choosing ----------

    function choose(item) {
        if (!state || !item || item.disabled) return;
        const select = state.select;
        const changed = select.selectedIndex !== item.opt.index;
        select.selectedIndex = item.opt.index;
        close(true);
        if (changed) {
            select.dispatchEvent(new Event('input', { bubbles: true }));
            select.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    function onListClick(e) {
        const li = e.target.closest('.kp-ss-opt');
        if (li) choose(state.visible[+li.dataset.i]);
    }

    function onListHover(e) {
        const li = e.target.closest('.kp-ss-opt');
        if (li && +li.dataset.i !== state.active && li.getAttribute('aria-disabled') !== 'true') {
            setActive(+li.dataset.i);
        }
    }

    function onSearchKeydown(e) {
        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault();
                moveActive(1);
                break;
            case 'ArrowUp':
                e.preventDefault();
                moveActive(-1);
                break;
            case 'PageDown':
                e.preventDefault();
                moveActive(8);
                break;
            case 'PageUp':
                e.preventDefault();
                moveActive(-8);
                break;
            case 'Enter':
                e.preventDefault(); // never submit the surrounding form from here
                e.stopPropagation();
                if (state.active >= 0) choose(state.visible[state.active]);
                break;
            case 'Escape':
                e.preventDefault();
                e.stopPropagation(); // don't also close the modal underneath
                close(true);
                break;
            case 'Tab':
                e.preventDefault();
                close(true);
                break;
        }
    }

    // ---------- Positioning ----------

    function position() {
        if (!state) return;
        const { select, root, sheet } = state;
        if (!select.isConnected) {
            close(false);
            return;
        }

        if (sheet) {
            const vv = window.visualViewport;
            const viewH = vv ? vv.height : window.innerHeight;
            const bottom = vv ? Math.max(0, window.innerHeight - vv.height - vv.offsetTop) : 0;
            root.style.bottom = bottom + 'px';
            root.style.maxHeight = Math.round(viewH * 0.8) + 'px';
            return;
        }

        const r = select.getBoundingClientRect();
        const vw = document.documentElement.clientWidth;
        const vh = window.innerHeight;
        if (r.bottom < 0 || r.top > vh || (r.width === 0 && r.height === 0)) {
            close(false);
            return;
        }

        const width = Math.min(Math.max(r.width, 240), vw - 16);
        const left = Math.min(Math.max(8, r.left), vw - width - 8);
        const below = vh - r.bottom - 12;
        const above = r.top - 12;
        const placeBelow = below >= 240 || below >= above;
        const maxH = Math.max(140, Math.min(360, placeBelow ? below : above));

        root.style.width = width + 'px';
        root.style.left = left + 'px';
        root.style.maxHeight = maxH + 'px';
        if (placeBelow) {
            root.style.top = (r.bottom + 4) + 'px';
            root.style.bottom = 'auto';
        } else {
            root.style.top = 'auto';
            root.style.bottom = (vh - r.top + 4) + 'px';
        }
    }

    function onScroll(e) {
        if (state && !state.root.contains(e.target)) position();
    }

    function onOutsidePointer(e) {
        if (!state) return;
        if (state.root.contains(e.target)) return;
        // A press on the same select is handled by its own toggle
        if (selectFromEvent(e) === state.select) return;
        if (state.backdrop && e.target === state.backdrop) return;
        close(false);
    }

    // ---------- Opening triggers (delegated, so new selects work automatically) ----------

    // Desktop mouse: stop the native list and show ours
    document.addEventListener('mousedown', e => {
        const select = selectFromEvent(e);
        if (!select || e.button !== 0 || !isEnhanceable(select)) return;
        if (Date.now() - lastTouchAt < 800) return; // compatibility mouse event after a tap
        e.preventDefault();
        if (document.activeElement !== select) focusSelect(select);
        open(select);
    }, true);

    // Phones/tablets: only a real tap opens it, so swiping over a select still scrolls the page
    document.addEventListener('touchstart', e => {
        const select = selectFromEvent(e);
        lastTouchAt = Date.now();
        if (!select || !isEnhanceable(select) || e.touches.length !== 1) {
            touch = null;
            return;
        }
        touch = { select, x: e.touches[0].clientX, y: e.touches[0].clientY, moved: false };
    }, { capture: true, passive: true });

    document.addEventListener('touchmove', e => {
        if (!touch) return;
        const t = e.touches[0];
        if (Math.abs(t.clientX - touch.x) > TAP_SLOP || Math.abs(t.clientY - touch.y) > TAP_SLOP) touch.moved = true;
    }, { capture: true, passive: true });

    document.addEventListener('touchend', e => {
        const t = touch;
        touch = null;
        lastTouchAt = Date.now();
        if (!t || t.moved || selectFromEvent(e) !== t.select || !isEnhanceable(t.select)) return;
        if (e.cancelable) e.preventDefault(); // blocks the native picker and the follow-up click
        open(t.select);
    }, { capture: true, passive: false });

    document.addEventListener('touchcancel', () => { touch = null; }, true);

    // Safety net: if a browser still focuses the select from a tap, close the native picker and use ours
    document.addEventListener('focusin', e => {
        const select = e.target;
        if (focusingSelf || !(select instanceof HTMLSelectElement) || !isEnhanceable(select)) return;
        if (Date.now() - lastTouchAt > 600) return;
        select.blur();
        if (!state || state.select !== select) open(select);
    }, true);

    // Keyboard: open with Enter / Space / arrows / F4, or start searching by typing
    document.addEventListener('keydown', e => {
        const select = e.target;
        if (!(select instanceof HTMLSelectElement) || !isEnhanceable(select)) return;
        if (e.ctrlKey || e.metaKey) return;

        const opens = ['Enter', ' ', 'ArrowDown', 'ArrowUp', 'F4'].includes(e.key);
        const typed = e.key.length === 1 && e.key !== ' ' && !e.altKey;
        if (!opens && !typed) return;

        e.preventDefault();
        open(select, typed ? e.key : '');
    }, true);

    window.KpSelect = {
        open: select => open(select),
        close: () => close(true),
    };
})();
