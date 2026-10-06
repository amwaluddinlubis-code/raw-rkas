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
    };

    const open = () => {
        if (select.disabled) return;
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
        if (!wrapper.contains(event.target)) close();
    });

    renderOptions();
    new MutationObserver(renderOptions).observe(select, { childList: true });
};

export const initializeDaisySelectDropdowns = (root = document) => {
    root.querySelectorAll(selector).forEach(enhanceSelect);
};
