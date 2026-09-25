(function () {
    'use strict';

    const DAY_NAMES = ['Ahad', 'Isnin', 'Selasa', 'Rabu', 'Khamis', 'Jumaat', 'Sabtu'];
    const MONTH_NAMES = [
        'Januari', 'Februari', 'Mac', 'April', 'Mei', 'Jun',
        'Julai', 'Ogos', 'September', 'Oktober', 'November', 'Disember'
    ];
    const HIJRI_MONTHS = [
        'Muharam', 'Safar', 'Rabiulawal', 'Rabiulakhir', 'Jamadilawal', 'Jamadilakhir',
        'Rejab', 'Syaaban', 'Ramadan', 'Syawal', 'Zulkaedah', 'Zulhijjah'
    ];
    const JAKIM_CALENDAR_URL = document.currentScript?.dataset.calendarUrl || '';

    async function requestJakimCalendar(parameters) {
        if (!JAKIM_CALENDAR_URL) {
            throw new Error('JAKIM calendar URL is unavailable.');
        }

        const url = new URL(JAKIM_CALENDAR_URL, window.location.origin);
        Object.entries(parameters).forEach(function ([key, value]) {
            url.searchParams.set(key, value);
        });

        const response = await fetch(url, {
            headers: {
                'Accept': 'application/json',
                'X-KKK-Calendar-Request': '1'
            },
            credentials: 'same-origin'
        });

        if (!response.ok) {
            throw new Error('JAKIM calendar request failed.');
        }

        return response.json();
    }

    async function gregorianToHijri(date) {
        const result = await requestJakimCalendar({
            calendar: 'gregorian',
            date: isoDate(date),
            return_to: window.location.pathname + window.location.search
        });
        const output = parseDateParts(result.output);

        return output ? {
            day: output.day,
            month: output.month,
            year: output.year
        } : null;
    }

    function pad(value) {
        return String(value).padStart(2, '0');
    }

    function parseIsoDate(value) {
        const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value || '');

        if (!match) {
            return null;
        }

        const date = new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));

        return Number.isNaN(date.getTime()) ? null : date;
    }

    function parseDateParts(value) {
        const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value || '');

        return match ? {
            year: Number(match[1]),
            month: Number(match[2]),
            day: Number(match[3])
        } : null;
    }

    function isoDate(date) {
        return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate());
    }

    function displayDate(date) {
        return pad(date.getDate()) + '/' + pad(date.getMonth() + 1) + '/' + date.getFullYear();
    }

    function emitChange(control) {
        control.dispatchEvent(new Event('input', { bubbles: true }));
        control.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function button(label, className) {
        const element = document.createElement('button');
        element.type = 'button';
        element.className = className;
        element.textContent = label;
        return element;
    }

    function closeOtherPickers(current) {
        document.querySelectorAll('.event-date-picker.is-open, .hijri-date-picker.is-open').forEach(function (picker) {
            if (picker !== current) {
                picker.classList.remove('is-open');
                picker.querySelector('[data-picker-panel]')?.setAttribute('hidden', '');
                picker.querySelector('[aria-expanded="true"]')?.setAttribute('aria-expanded', 'false');
            }
        });
    }

    function setupGregorianPicker(input) {
        const container = input.closest('.grid > div');
        const fieldset = input.closest('[data-package-side]');
        const daySelect = fieldset?.querySelector('[data-event-day]');
        const help = container?.querySelector('[data-event-date-help]');

        if (!container || !daySelect || !help) {
            return;
        }

        const picker = document.createElement('div');
        picker.className = 'event-date-picker';
        picker.dataset.eventDatePicker = '';

        const trigger = button('', 'date-picker-trigger');
        trigger.setAttribute('aria-haspopup', 'dialog');
        trigger.setAttribute('aria-expanded', 'false');
        const display = document.createElement('span');
        const icon = document.createElement('span');
        icon.className = 'date-picker-icon';
        icon.setAttribute('aria-hidden', 'true');
        icon.textContent = '▣';
        trigger.append(display, icon);

        const panel = document.createElement('div');
        panel.className = 'date-picker-panel';
        panel.dataset.pickerPanel = '';
        panel.setAttribute('role', 'dialog');
        panel.setAttribute('aria-label', 'Pilih tarikh majlis');
        panel.hidden = true;

        const header = document.createElement('div');
        header.className = 'date-picker-heading';
        const previous = button('‹', 'date-picker-nav');
        previous.setAttribute('aria-label', 'Bulan sebelumnya');
        const title = document.createElement('strong');
        const next = button('›', 'date-picker-nav');
        next.setAttribute('aria-label', 'Bulan seterusnya');
        header.append(previous, title, next);

        const weekdayHeader = document.createElement('div');
        weekdayHeader.className = 'date-picker-weekdays';
        ['A', 'I', 'S', 'R', 'K', 'J', 'S'].forEach(function (day, index) {
            const label = document.createElement('span');
            label.textContent = day;
            label.title = DAY_NAMES[index];
            weekdayHeader.append(label);
        });

        const calendar = document.createElement('div');
        calendar.className = 'date-picker-calendar';
        const note = document.createElement('p');
        note.className = 'date-picker-note';
        panel.append(header, weekdayHeader, calendar, note);
        picker.append(trigger, panel);

        input.insertAdjacentElement('afterend', picker);
        input.type = 'hidden';

        let selected = parseIsoDate(input.value);
        const today = new Date();
        let visibleYear = (selected || today).getFullYear();
        let visibleMonth = (selected || today).getMonth();

        function selectedWeekday() {
            return DAY_NAMES.indexOf(daySelect.value);
        }

        function updateDisplay() {
            display.textContent = selected ? displayDate(selected) : 'Pilih tarikh';
            trigger.classList.toggle('has-value', Boolean(selected));
        }

        function render() {
            calendar.replaceChildren();
            title.textContent = MONTH_NAMES[visibleMonth] + ' ' + visibleYear;

            const allowedWeekday = selectedWeekday();
            const firstWeekday = new Date(visibleYear, visibleMonth, 1).getDay();
            const daysInMonth = new Date(visibleYear, visibleMonth + 1, 0).getDate();

            for (let index = 0; index < firstWeekday; index += 1) {
                const blank = document.createElement('span');
                blank.className = 'date-picker-blank';
                calendar.append(blank);
            }

            for (let day = 1; day <= daysInMonth; day += 1) {
                const date = new Date(visibleYear, visibleMonth, day);
                const dateButton = button(String(day), 'date-picker-day');
                const matchesDay = allowedWeekday === -1 || date.getDay() === allowedWeekday;
                dateButton.disabled = !matchesDay;
                dateButton.setAttribute('aria-label', DAY_NAMES[date.getDay()] + ', ' + displayDate(date));

                if (selected && isoDate(selected) === isoDate(date)) {
                    dateButton.classList.add('is-selected');
                    dateButton.setAttribute('aria-current', 'date');
                }

                dateButton.addEventListener('click', function () {
                    selected = date;
                    input.value = isoDate(date);
                    daySelect.value = DAY_NAMES[date.getDay()];
                    updateDisplay();
                    render();
                    close();
                    emitChange(input);
                    emitChange(daySelect);
                    fieldset.dispatchEvent(new CustomEvent('customer:gregorian-date-selected', {
                        detail: { date: isoDate(date) }
                    }));
                    trigger.focus();
                });
                calendar.append(dateButton);
            }

            note.textContent = allowedWeekday === -1
                ? 'Pilih mana-mana tarikh. Hari akan diisi secara automatik.'
                : 'Hanya tarikh hari ' + daySelect.value + ' boleh dipilih.';
            help.textContent = note.textContent;
        }

        function open() {
            closeOtherPickers(picker);
            picker.classList.add('is-open');
            panel.hidden = false;
            trigger.setAttribute('aria-expanded', 'true');
            render();
        }

        function close() {
            picker.classList.remove('is-open');
            panel.hidden = true;
            trigger.setAttribute('aria-expanded', 'false');
        }

        trigger.addEventListener('click', function () {
            picker.classList.contains('is-open') ? close() : open();
        });
        previous.addEventListener('click', function () {
            visibleMonth -= 1;
            if (visibleMonth < 0) {
                visibleMonth = 11;
                visibleYear -= 1;
            }
            render();
        });
        next.addEventListener('click', function () {
            visibleMonth += 1;
            if (visibleMonth > 11) {
                visibleMonth = 0;
                visibleYear += 1;
            }
            render();
        });
        daySelect.addEventListener('change', function () {
            const allowedWeekday = selectedWeekday();

            if (selected && allowedWeekday !== -1 && selected.getDay() !== allowedWeekday) {
                selected = null;
                input.value = '';
                updateDisplay();
                emitChange(input);
            }

            render();
        });
        updateDisplay();
        render();
    }

    function setupHijriPicker(input) {
        const container = input.closest('.grid > div');
        const fieldset = input.closest('[data-package-side]');
        const daySelect = fieldset?.querySelector('[data-event-day]');
        const gregorianInput = fieldset?.querySelector('[data-event-date]');
        const help = container?.querySelector('[data-hijri-date-help]');

        if (!daySelect || !gregorianInput || !help) {
            return;
        }

        const picker = document.createElement('div');
        picker.className = 'hijri-date-picker';
        picker.dataset.hijriDatePicker = '';

        const trigger = button('', 'date-picker-trigger');
        trigger.setAttribute('aria-haspopup', 'dialog');
        trigger.setAttribute('aria-expanded', 'false');
        const display = document.createElement('span');
        const icon = document.createElement('span');
        icon.className = 'date-picker-icon';
        icon.setAttribute('aria-hidden', 'true');
        icon.textContent = '☾';
        trigger.append(display, icon);

        const panel = document.createElement('div');
        panel.className = 'hijri-picker-panel';
        panel.dataset.pickerPanel = '';
        panel.setAttribute('role', 'dialog');
        panel.setAttribute('aria-label', 'Pilih tarikh Hijri');
        panel.hidden = true;

        const option = button('Pilih Tarikh Miladi dahulu', 'hijri-date-option');
        option.disabled = true;
        const clear = button('Kosongkan', 'hijri-picker-clear');
        panel.append(option, clear);
        picker.append(trigger, panel);
        input.insertAdjacentElement('afterend', picker);
        input.type = 'hidden';

        let availableHijriDate = null;
        let requestId = 0;

        function updateDisplay() {
            display.textContent = input.value || 'Pilih tarikh Hijri';
            trigger.classList.toggle('has-value', Boolean(input.value));
        }

        function formatHijriDate(value) {
            return value.day + ' ' + HIJRI_MONTHS[value.month - 1] + ' ' + value.year + 'H';
        }

        function clearSelection(shouldEmit) {
            const hadValue = Boolean(input.value);
            input.value = '';
            updateDisplay();

            if (shouldEmit && hadValue) {
                emitChange(input);
            }
        }

        async function loadForGregorian(rawDate, preserveExisting) {
            const currentRequestId = ++requestId;
            const gregorianDate = parseIsoDate(rawDate);

            availableHijriDate = null;
            option.disabled = true;

            if (!gregorianDate) {
                option.textContent = 'Pilih Tarikh Miladi dahulu';
                help.textContent = 'Pilih Tarikh Miladi dahulu untuk mendapatkan tarikh Hijri rasmi JAKIM.';
                clearSelection(!preserveExisting);
                return;
            }

            const previousValue = input.value;
            option.textContent = 'Memuatkan tarikh JAKIM...';
            help.textContent = 'Mendapatkan tarikh Hijri rasmi daripada JAKIM e-Solat...';

            if (!preserveExisting) {
                clearSelection(true);
            }

            try {
                const hijri = await gregorianToHijri(gregorianDate);

                if (currentRequestId !== requestId) {
                    return;
                }

                if (!hijri || !HIJRI_MONTHS[hijri.month - 1]) {
                    throw new Error('Invalid JAKIM calendar response.');
                }

                availableHijriDate = hijri;
                const label = formatHijriDate(hijri);
                option.textContent = label;
                option.disabled = false;
                help.textContent = 'Hanya ' + label + ', yang sepadan dengan ' + displayDate(gregorianDate) + ', boleh dipilih.';

                if (previousValue !== label) {
                    input.value = label;
                    updateDisplay();
                    emitChange(input);
                }
            } catch (error) {
                if (currentRequestId === requestId) {
                    availableHijriDate = null;
                    option.textContent = 'Tarikh tidak tersedia';
                    option.disabled = true;
                    help.textContent = 'Kalendar rasmi JAKIM tidak dapat dicapai. Sila cuba lagi.';
                    clearSelection(Boolean(input.value));
                }
            }
        }

        function open() {
            closeOtherPickers(picker);
            picker.classList.add('is-open');
            panel.hidden = false;
            trigger.setAttribute('aria-expanded', 'true');
        }

        function close() {
            picker.classList.remove('is-open');
            panel.hidden = true;
            trigger.setAttribute('aria-expanded', 'false');
        }

        updateDisplay();

        trigger.addEventListener('click', function () {
            picker.classList.contains('is-open') ? close() : open();
        });
        option.addEventListener('click', function () {
            if (!availableHijriDate) {
                return;
            }

            input.value = formatHijriDate(availableHijriDate);
            updateDisplay();
            emitChange(input);
            close();
            trigger.focus();
        });
        daySelect.addEventListener('change', function () {
            loadForGregorian(gregorianInput.value, false);
        });
        fieldset.addEventListener('customer:gregorian-date-selected', function (event) {
            loadForGregorian(event.detail?.date, false);
        });
        clear.addEventListener('click', function () {
            clearSelection(true);
            close();
            trigger.focus();
        });

        if (gregorianInput.value) {
            loadForGregorian(gregorianInput.value, true);
        } else {
            loadForGregorian('', true);
        }
    }

    document.addEventListener('click', function (event) {
        document.querySelectorAll('.event-date-picker.is-open, .hijri-date-picker.is-open').forEach(function (picker) {
            if (!picker.contains(event.target)) {
                picker.classList.remove('is-open');
                picker.querySelector('[data-picker-panel]')?.setAttribute('hidden', '');
                picker.querySelector('[aria-expanded="true"]')?.setAttribute('aria-expanded', 'false');
            }
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeOtherPickers(null);
        }
    });

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-event-date]').forEach(setupGregorianPicker);
        document.querySelectorAll('[data-hijri-date]').forEach(setupHijriPicker);
    });
}());
