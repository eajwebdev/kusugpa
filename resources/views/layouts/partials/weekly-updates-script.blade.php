        const weeklyCropYears = @json(App\Models\CropYear::orderByDesc('crop_year')->pluck('crop_year')->values());
        const weeklyUpdateUserId = @json(auth()->id());

        function weeklyField(id, label, control, hint = '') {
            return `<div class="kp-weekly-field">
                <label for="${id}">${label}</label>
                ${control}
                ${hint ? `<p class="kp-weekly-help">${hint}</p>` : ''}
            </div>`;
        }

        function weeklyYearSelect() {
            return '<select id="weekly_crop_year" autocomplete="off"><option value="">Select crop year</option></select>';
        }

        function weeklyWeekSelect() {
            return '<select id="weekly_week_no" disabled><option value="">Choose a crop year first</option></select>';
        }

        async function loadWeeklyWeeks(cropYear) {
            const weekSelect = document.getElementById('weekly_week_no');
            if (!weekSelect) return;
            weekSelect.replaceChildren(new Option(cropYear ? 'Loading weeks...' : 'Choose a crop year first', ''));
            weekSelect.disabled = true;
            if (!cropYear) return;
            try {
                const url = new URL(@json(route('get.weeks')), window.location.origin);
                url.searchParams.set('crop_year', cropYear);
                const response = await fetch(url, { headers: { Accept: 'application/json' } });
                if (!response.ok) throw new Error('Could not load weeks');
                const data = await response.json();
                weekSelect.replaceChildren(new Option(data.weeks.length ? 'Select week number' : 'No weeks found for this crop year', ''));
                data.weeks.forEach(week => weekSelect.add(new Option(`Week ${week}`, week)));
                weekSelect.disabled = !data.weeks.length;
            } catch (error) {
                weekSelect.replaceChildren(new Option('Could not load weeks - choose the year again', ''));
                Swal.showValidationMessage('Weeks could not be loaded. Please select the crop year again.');
            }
        }

        function weeklyUpdateConfig(type) {
            const year = weeklyField('weekly_crop_year', 'Crop year', weeklyYearSelect());
            const week = weeklyField('weekly_week_no', 'Week number', weeklyWeekSelect());
            if (type === 'quedan') return {
                title: 'Add Quedan Price',
                description: 'Set a price for one quedan type and milling week.',
                button: 'Save price',
                url: @json(route('updates.addQuedanPrice')),
                fields: `<div class="kp-weekly-grid">${weeklyField('weekly_quedan_type', 'Quedan type', '<input id="weekly_quedan_type" type="text" maxlength="20" placeholder="e.g. A, B or C" autocomplete="off">')}${weeklyField('weekly_quedan_price', 'Price per Lkg', '<div class="kp-weekly-money"><span>PHP</span><input id="weekly_quedan_price" type="number" min="0" step="0.01" inputmode="decimal" placeholder="0.00"></div>')}</div><div class="kp-weekly-grid">${year}${week}</div>`,
            };
            if (type === 'molasses') return {
                title: 'Add Molasses Price',
                description: 'Record the molasses rate for a milling week.',
                button: 'Save price',
                url: @json(route('updates.addMolassesPrice')),
                fields: `${weeklyField('weekly_mol_price', 'Molasses price', '<div class="kp-weekly-money"><span>PHP</span><input id="weekly_mol_price" type="number" min="0" step="0.01" inputmode="decimal" placeholder="0.00"></div>')}<div class="kp-weekly-grid">${year}${week}</div>`,
            };
            if (type === 'crop-year') return {
                title: 'Add Crop Year',
                description: 'Create a new milling season for weekly records.',
                button: 'Add crop year',
                url: @json(route('updates.addCropYear')),
                fields: weeklyField('weekly_new_crop_year', 'Crop year code', '<input id="weekly_new_crop_year" type="text" inputmode="numeric" pattern="[0-9]{8}" maxlength="8" placeholder="e.g. 20252026" autocomplete="off">', 'Enter two four-digit years together, such as 20252026.'),
            };
            return {
                title: 'Add Week Number',
                description: 'Define the start and end of a milling week.',
                button: 'Save week',
                url: @json(route('updates.addWeekNumber')),
                fields: `<div class="kp-weekly-grid">${year}${weeklyField('weekly_new_week_no', 'Week number', '<input id="weekly_new_week_no" type="number" min="1" max="52" placeholder="1">')}</div>
                    <div class="kp-weekly-period"><p>Week starts</p><div class="kp-weekly-grid">${weeklyField('weekly_start_date', 'Date', '<input id="weekly_start_date" type="date">')}${weeklyField('weekly_start_time', 'Time', '<input id="weekly_start_time" type="time" step="1" value="00:00:00">')}</div></div>
                    <div class="kp-weekly-period"><p>Week ends</p><div class="kp-weekly-grid">${weeklyField('weekly_end_date', 'Date', '<input id="weekly_end_date" type="date">')}${weeklyField('weekly_end_time', 'Time', '<input id="weekly_end_time" type="time" step="1" value="23:59:59">')}</div></div>`,
            };
        }

        function weeklyUpdatePayload(type) {
            const value = id => document.getElementById(id)?.value.trim() || '';
            const payload = { user_id: weeklyUpdateUserId };
            if (type === 'crop-year') {
                payload.crop_year = value('weekly_new_crop_year');
                if (!/^\d{8}$/.test(payload.crop_year)) throw new Error('Enter an eight-digit crop year, such as 20252026.');
                return payload;
            }
            payload.crop_year = value('weekly_crop_year');
            if (!payload.crop_year) throw new Error('Select a crop year.');
            if (type === 'week') {
                payload.week_no = value('weekly_new_week_no');
                if (!payload.week_no || Number(payload.week_no) < 1 || Number(payload.week_no) > 52) throw new Error('Enter a week number from 1 to 52.');
                const startDate = value('weekly_start_date');
                const endDate = value('weekly_end_date');
                if (!startDate || !endDate) throw new Error('Enter both start and end dates.');
                payload.week_start_date = `${startDate} ${value('weekly_start_time') || '00:00:00'}`;
                payload.week_end_date = `${endDate} ${value('weekly_end_time') || '23:59:59'}`;
                if (payload.week_end_date <= payload.week_start_date) throw new Error('The week must end after it starts.');
                return payload;
            }
            payload.week_no = value('weekly_week_no');
            if (!payload.week_no) throw new Error('Select a week number.');
            if (type === 'quedan') {
                payload.quedan_type = value('weekly_quedan_type');
                payload.quedan_price = value('weekly_quedan_price');
                if (!payload.quedan_type) throw new Error('Enter a quedan type.');
                if (payload.quedan_price === '' || Number(payload.quedan_price) < 0) throw new Error('Enter a valid quedan price.');
            } else {
                payload.mol_price = value('weekly_mol_price');
                if (payload.mol_price === '' || Number(payload.mol_price) < 0) throw new Error('Enter a valid molasses price.');
            }
            return payload;
        }

        function openWeeklyUpdateModal(type) {
            const config = weeklyUpdateConfig(type);
            Swal.fire({
                title: config.title,
                html: `<p class="kp-weekly-intro">${config.description}</p><div class="kp-weekly-fields">${config.fields}</div>`,
                width: 560,
                showCancelButton: true,
                showLoaderOnConfirm: true,
                focusConfirm: false,
                confirmButtonText: `<i class="fas fa-check" aria-hidden="true"></i> ${config.button}`,
                cancelButtonText: 'Cancel',
                buttonsStyling: false,
                customClass: {
                    popup: 'kp-weekly-popup',
                    title: 'kp-weekly-title',
                    htmlContainer: 'kp-weekly-content',
                    actions: 'kp-weekly-actions',
                    confirmButton: 'kp-weekly-save',
                    cancelButton: 'kp-weekly-cancel',
                    validationMessage: 'kp-weekly-error',
                },
                didOpen: () => {
                    const yearSelect = document.getElementById('weekly_crop_year');
                    if (yearSelect) {
                        weeklyCropYears.forEach(year => yearSelect.add(new Option(year, year)));
                        if (type === 'quedan' || type === 'molasses') yearSelect.addEventListener('change', event => loadWeeklyWeeks(event.target.value));
                    }
                    Swal.getHtmlContainer().querySelector('input, select')?.focus();
                },
                preConfirm: async () => {
                    try {
                        const payload = weeklyUpdatePayload(type);
                        const response = await fetch(config.url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            },
                            body: JSON.stringify(payload),
                        });
                        const data = await response.json();
                        if (!response.ok) throw new Error(Object.values(data.errors || {})[0]?.[0] || data.message || 'Could not save this update.');
                        return data;
                    } catch (error) {
                        Swal.showValidationMessage(error.message || 'Could not save this update. Please try again.');
                        return false;
                    }
                },
            }).then(result => {
                if (!result.isConfirmed || !result.value) return;
                refreshDashboardData();
                Swal.fire({ icon: 'success', title: 'Update saved', text: result.value.message || 'The weekly update was saved.', timer: 1400, showConfirmButton: false })
                    .then(() => window.location.reload());
            });
        }

        function openQuedanPriceModal() { openWeeklyUpdateModal('quedan'); }
        function openMolassesPriceModal() { openWeeklyUpdateModal('molasses'); }
        function openCropYearModal() { openWeeklyUpdateModal('crop-year'); }
        function openWeekNumberModal() { openWeeklyUpdateModal('week'); }
