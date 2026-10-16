const selector = 'select:not([multiple]):not([data-native-select])';

const createCheckIcon = () => {
    const icon = document.createElement('span');
    icon.className = 'ui-daisy-select-check';
    icon.setAttribute('aria-hidden', 'true');
    return icon;
};

const enhanceSelect = (select) => {
    if (!(select instanceof HTMLSelectElement) || select.dataset.daisySelectInitialized === 'true') return;
    if (select.closest('.ui-searchable-select, .ui-daisy-select')) return;

    const wrapper = document.createElement('div');
    wrapper.className = 'ui-daisy-select relative';
    select.parentNode?.insertBefore(wrapper, select);
    wrapper.appendChild(select);
    select.dataset.daisySelectInitialized = 'true';
    select.classList.add('ui-daisy-select-native');

    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'ui-searchable-select-trigger ui-daisy-select-trigger';
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-expanded', 'false');
    trigger.setAttribute('aria-label', select.getAttribute('aria-label') || select.name || 'Pilih opsi');
    trigger.disabled = select.disabled;

    const triggerLabel = document.createElement('span');
    triggerLabel.className = 'truncate text-left';
    const chevron = document.createElement('span');
    chevron.className = 'ui-daisy-select-chevron';
    chevron.setAttribute('aria-hidden', 'true');
    trigger.append(triggerLabel, chevron);

    const menu = document.createElement('div');
    menu.className = 'ui-searchable-select-menu ui-daisy-select-menu';
    menu.hidden = true;
    menu.setAttribute('role', 'listbox');

    const searchWrap = document.createElement('div');
    searchWrap.className = 'ui-searchable-select-search-wrap';
    const searchIcon = document.createElement('span');
    searchIcon.className = 'ui-daisy-select-search-icon';
    searchIcon.setAttribute('aria-hidden', 'true');
    const search = document.createElement('input');
    search.type = 'search';
    search.className = 'ui-searchable-select-search';
    search.placeholder = 'Cari opsi...';
    search.setAttribute('aria-label', 'Cari opsi');
    searchWrap.append(searchIcon, search);

    const options = document.createElement('div');
    options.className = 'ui-searchable-select-options';
    menu.append(searchWrap, options);
    wrapper.append(trigger, menu);

    const positionMenu = () => {
        const bounds = trigger.getBoundingClientRect();
        const gap = 6;
        const margin = 16;

        // Ukur tinggi natural menu (tanpa batas maxHeight) agar keputusan arah
        // buka akurat, termasuk saat opsi tinggal sedikit setelah pencarian.
        const prevMaxHeight = menu.style.maxHeight;
        const wasHidden = menu.hidden;
        menu.style.maxHeight = 'none';
        menu.style.visibility = 'hidden';
        if (wasHidden) menu.hidden = false;
        const naturalHeight = menu.offsetHeight || 0;
        if (wasHidden) menu.hidden = true;
        menu.style.visibility = '';
        menu.style.maxHeight = prevMaxHeight;

        const spaceBelow = window.innerHeight - bounds.bottom - margin;
        const spaceAbove = bounds.top - margin;
        // Buka ke atas hanya bila ruang bawah tak muat dan ruang atas lebih lega.
        const openUp = spaceBelow < naturalHeight && spaceAbove > spaceBelow;

        menu.style.left = `${Math.round(bounds.left)}px`;
        menu.style.width = `${Math.round(bounds.width)}px`;
        if (openUp) {
            menu.dataset.placement = 'up';
            menu.style.top = '';
            menu.style.bottom = `${Math.round(window.innerHeight - bounds.top + gap)}px`;
            menu.style.maxHeight = `${Math.round(Math.max(160, spaceAbove - gap))}px`;
        } else {
            delete menu.dataset.placement;
            menu.style.bottom = '';
            menu.style.top = `${Math.round(bounds.bottom + gap)}px`;
            menu.style.maxHeight = `${Math.round(Math.max(160, spaceBelow - gap))}px`;
        }
    };

    const optionButtons = [];
    const renderLabel = () => {
        const selected = select.options[select.selectedIndex];
        triggerLabel.textContent = selected?.textContent?.trim() || 'Pilih opsi';
        optionButtons.forEach(({ button, option }) => {
            const isSelected = option.value === select.value;
            button.setAttribute('aria-selected', String(isSelected));
            button.classList.toggle('is-selected', isSelected);
        });
    };

    const renderOptions = () => {
        options.replaceChildren();
        optionButtons.length = 0;

        Array.from(select.options).forEach((option) => {
            if (option.disabled) return;
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'ui-searchable-select-option';
            button.setAttribute('role', 'option');
            button.dataset.value = option.value;
            const label = document.createElement('span');
            label.textContent = option.textContent?.trim() || option.value;
            button.append(label, createCheckIcon());
            button.addEventListener('click', () => {
                select.value = option.value;
                select.dispatchEvent(new Event('input', { bubbles: true }));
                select.dispatchEvent(new Event('change', { bubbles: true }));
                close();
                trigger.focus();
            });
            options.appendChild(button);
            optionButtons.push({ button, option });
        });

        renderLabel();
    };

    const close = () => {
        menu.hidden = true;
        trigger.setAttribute('aria-expanded', 'false');
        search.value = '';
        optionButtons.forEach(({ button }) => { button.hidden = false; });
        if (menu.parentNode !== wrapper) {
            wrapper.appendChild(menu);
            menu.classList.remove('ui-daisy-select-menu-portal');
            delete menu.dataset.placement;
            menu.style.top = '';
            menu.style.bottom = '';
            menu.style.left = '';
            menu.style.width = '';
            menu.style.maxHeight = '';
        }
    };

    const open = () => {
        if (select.disabled) return;
        document.body.appendChild(menu);
        menu.classList.add('ui-daisy-select-menu-portal');
        positionMenu();
        menu.hidden = false;
        trigger.setAttribute('aria-expanded', 'true');
        search.focus();
    };

    trigger.addEventListener('click', () => (menu.hidden ? open() : close()));
    trigger.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowDown' || event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            open();
        }
        if (event.key === 'Escape') close();
    });
    search.addEventListener('input', () => {
        const query = search.value.trim().toLowerCase();
        optionButtons.forEach(({ button, option }) => {
            button.hidden = !option.textContent.toLowerCase().includes(query);
        });
    });
    select.addEventListener('change', renderLabel);
    document.addEventListener('click', (event) => {
        if (!wrapper.contains(event.target) && !menu.contains(event.target)) close();
    });
    window.addEventListener('resize', () => {
        if (!menu.hidden) positionMenu();
    });
    window.addEventListener('scroll', () => {
        if (!menu.hidden) positionMenu();
    }, true);

    renderOptions();
    new MutationObserver(renderOptions).observe(select, { childList: true });
};

export const initializeDaisySelectDropdowns = (root = document) => {
    root.querySelectorAll(selector).forEach(enhanceSelect);
};
