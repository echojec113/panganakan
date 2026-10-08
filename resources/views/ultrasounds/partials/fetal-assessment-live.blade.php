@inject('fwaLive', 'App\Services\FetalWeightAssessmentService')
@inject('fhrLive', 'App\Services\FetalHeartRateAssessmentService')

<script data-fetal-assessment-live>
    (function () {
        var FWA = @json($fwaLive->clientConfig());
        var FHR = @json($fhrLive->clientConfig());

        function parseNumber(value) {
            if (value === null || value === undefined) {
                return null;
            }
            var text = String(value).trim();
            if (text === '') {
                return null;
            }
            var number = Number(text);
            return Number.isFinite(number) ? number : null;
        }

        function formatGrams(value) {
            return Math.round(value).toLocaleString('en-US');
        }

        function formatWeight(raw) {
            var text = String(raw).trim();
            if (text === '' || !Number.isFinite(Number(text))) {
                return text === '' ? FWA.messages.notRecorded : text;
            }
            var number = Number(text);
            if (Math.floor(number) === number) {
                return number.toLocaleString('en-US');
            }
            var parts = text.split('.');
            parts[1] = parts[1].replace(/0+$/, '');
            if (parts[1] === '') {
                parts.pop();
            }
            parts[0] = Number(parts[0]).toLocaleString('en-US');
            return parts.join('.');
        }

        function formatGa(raw) {
            var text = String(raw).trim();
            if (text.indexOf('.') !== -1) {
                text = text.replace(/0+$/, '');
                if (text.charAt(text.length - 1) === '.') {
                    text = text.slice(0, -1);
                }
            }
            return text;
        }

        function formatThreshold(value) {
            var fixed = Number(value).toFixed(2);
            if (fixed.indexOf('.') !== -1) {
                fixed = fixed.replace(/0+$/, '');
                if (fixed.charAt(fixed.length - 1) === '.') {
                    fixed = fixed.slice(0, -1);
                }
            }
            var parts = fixed.split('.');
            parts[0] = Number(parts[0]).toLocaleString('en-US');
            return parts.join('.');
        }

        function fill(template, variables) {
            var output = template;
            Object.keys(variables).forEach(function (key) {
                output = output.split('{' + key + '}').join(variables[key]);
            });
            return output;
        }

        function slot(root, name) {
            return root.querySelector('[data-slot="' + name + '"]');
        }

        function setSlotText(root, name, text, hidden) {
            var element = slot(root, name);
            if (!element) {
                return;
            }
            element.textContent = text;
            element.classList.toggle('hidden', !!hidden);
        }

        function setRowValue(root, key, text) {
            var value = root.querySelector('[data-row="' + key + '"] [data-value]');
            if (value) {
                value.textContent = text;
            }
        }

        function setChip(root, status) {
            var chip = slot(root, 'chip');
            if (!chip) {
                return;
            }
            chip.textContent = (FWA.labels[status] || FHR.labels[status] || status);
            chip.className = FWA.chipClass[status] || FWA.chipClass.unable;
        }

        function setFhrChip(root, status) {
            var chip = slot(root, 'chip');
            if (!chip) {
                return;
            }
            chip.textContent = FHR.labels[status] || status;
            chip.className = FHR.chipClass[status] || FHR.chipClass.unable;
        }

        function interpolate(table, ga, percentile) {
            var lower = Math.floor(ga);
            var upper = Math.ceil(ga);
            if (lower === upper) {
                return table[lower][percentile];
            }
            var fraction = ga - lower;
            return table[lower][percentile]
                + fraction * (table[upper][percentile] - table[lower][percentile]);
        }

        function updateFwa() {
            var root = document.querySelector('[data-assessment="fwa"]');
            if (!root) {
                return;
            }

            var gaField = document.getElementById('gestational_age');
            var weightField = document.getElementById('fetal_weight');
            var gaRaw = gaField ? gaField.value : '';
            var weightRaw = weightField ? weightField.value : '';
            var ga = parseNumber(gaRaw);
            var weight = parseNumber(weightRaw);

            setRowValue(root, 'weight', weight === null
                ? FWA.messages.notRecorded
                : formatWeight(weightRaw) + FWA.messages.weightUnit);
            setRowValue(root, 'ga', ga === null
                ? FWA.messages.notRecorded
                : formatGa(gaRaw) + FWA.messages.gaUnit);

            var reasons = [];
            if (ga === null) {
                reasons.push(FWA.messages.reasons.gaMissing);
            } else if (ga < FWA.window.min || ga > FWA.window.max) {
                reasons.push(fill(FWA.messages.reasons.gaOutside, { ga: formatGa(gaRaw) }));
            }
            if (weight === null) {
                reasons.push(FWA.messages.reasons.weightMissing);
            }

            var summary = '';
            if (ga === null && weight === null) {
                summary = 'Enter fetal weight and gestational age to view the assessment.';
            } else {
                summary = reasons.join(' ');
            }

            if (reasons.length > 0) {
                setRowValue(root, 'range', FWA.messages.notAvailable);
                setRowValue(root, 'median', FWA.messages.notAvailable);
                setChip(root, 'unable');
                setSlotText(root, 'reason', reasons.join(' '), false);
                setSlotText(root, 'recommendation', FWA.messages.unable, false);
                setSlotText(root, 'clarification', '', true);
                setSlotText(root, 'summary', summary, false);
                root.setAttribute('data-status', 'unable');
                return;
            }

            var p10 = interpolate(FWA.table, ga, 10);
            var p50 = interpolate(FWA.table, ga, 50);
            var p90 = interpolate(FWA.table, ga, 90);

            setRowValue(root, 'range', formatGrams(p10) + ' g – ' + formatGrams(p90) + ' g');
            setRowValue(root, 'median', formatGrams(p50) + ' g');

            var status;
            var recommendation;
            var clarification = '';

            if (weight < p10) {
                status = 'below';
                recommendation = fill(FWA.messages.below, { ga: formatGa(gaRaw) });
                if (p10 !== Math.round(p10) && weight === Math.round(p10)) {
                    clarification = fill(FWA.messages.clarify.below, {
                        p10: formatThreshold(p10),
                        p10r: formatGrams(p10),
                        weight: formatGrams(weight)
                    });
                }
            } else if (weight < p90) {
                status = 'within';
                recommendation = fill(FWA.messages.within, { ga: formatGa(gaRaw) });
            } else {
                status = 'above';
                recommendation = fill(FWA.messages.above, { ga: formatGa(gaRaw) });
                if (p90 !== Math.round(p90) && weight === Math.round(p90)) {
                    clarification = fill(FWA.messages.clarify.aboveRound, {
                        p90: formatThreshold(p90),
                        p90r: formatGrams(p90),
                        weight: formatGrams(weight)
                    });
                } else if (weight === p90) {
                    clarification = fill(FWA.messages.clarify.aboveEq, {
                        p90: formatThreshold(p90),
                        weight: formatGrams(weight)
                    });
                }
            }

            setChip(root, status);
            setSlotText(root, 'reason', '', true);
            setSlotText(root, 'recommendation', recommendation, false);
            setSlotText(root, 'clarification', clarification, clarification === '');
            setSlotText(root, 'summary', formatWeight(weightRaw) + FWA.messages.weightUnit
                + ' at ' + formatGa(gaRaw) + FWA.messages.gaUnit
                + ' — ' + (FWA.labels[status] || status), false);
            root.setAttribute('data-status', status);
        }

        function updateFhr() {
            var root = document.querySelector('[data-assessment="fhr"]');
            if (!root) {
                return;
            }

            var categoryField = document.getElementById('fetal_heartbeat');
            var gaField = document.getElementById('gestational_age');
            var category = categoryField ? String(categoryField.value).trim() : '';
            var gaRaw = gaField ? gaField.value : '';
            var ga = parseNumber(gaRaw);

            setRowValue(root, 'category', category === ''
                ? FHR.messages.notRecorded
                : category);
            setRowValue(root, 'ga', ga === null
                ? FHR.messages.notRecorded
                : formatGa(gaRaw) + FHR.messages.gaUnit);

            var reasons = [];
            if (category === '') {
                reasons.push(FHR.messages.reasons.noCategory);
            } else if (!FHR.categories[category]) {
                reasons.push(FHR.messages.reasons.unknown);
            }

            if (ga === null) {
                reasons.push(FHR.messages.reasons.gaMissing);
            } else if (ga < FHR.window.min || ga >= FHR.window.maxExclusive) {
                reasons.push(fill(FHR.messages.reasons.gaOutside, { ga: formatGa(gaRaw) }));
            }

            var summary = '';
            if (category === '') {
                summary = 'Not recorded — select a fetal heartbeat finding to view the assessment.';
            } else if (category === 'Absent') {
                summary = 'Absent — Prompt clinical assessment required. Confirm promptly with a qualified healthcare professional.';
            } else if (reasons.length > 0) {
                summary = category + ' — ' + reasons.join(' ');
            }

            if (reasons.length > 0) {
                var safety = FHR.messages.safety[category] || '';
                setFhrChip(root, 'unable');
                setSlotText(root, 'reason', reasons.join(' '), false);
                setSlotText(root, 'recommendation', FHR.messages.unableBase
                    + (safety === '' ? '' : ' ' + safety), false);
                setSlotText(root, 'summary', summary, false);
                root.setAttribute('data-status', 'unable');
                return;
            }

            var entry = FHR.categories[category];
            if (summary === '') {
                summary = category + ' at ' + formatGa(gaRaw) + FHR.messages.gaUnit
                    + ' — ' + (entry.status === 'within'
                        ? 'within the applicable reference window.'
                        : 'needs clinical review with a qualified healthcare professional.');
            }
            setFhrChip(root, entry.status);
            setSlotText(root, 'reason', '', true);
            setSlotText(root, 'recommendation', entry.recommendation, false);
            setSlotText(root, 'summary', summary, false);
            root.setAttribute('data-status', entry.status);
        }

        function updateAll() {
            updateFwa();
            updateFhr();
        }

        function scheduleUpdate() {
            window.setTimeout(updateAll, 0);
        }

        function bind(element, events) {
            if (!element) {
                return;
            }
            events.forEach(function (eventName) {
                element.addEventListener(eventName, scheduleUpdate);
            });
        }

        bind(document.getElementById('gestational_age'), ['input', 'change']);
        bind(document.getElementById('fetal_weight'), ['input', 'change']);
        bind(document.getElementById('fetal_heartbeat'), ['change']);
        bind(document.getElementById('scan_date'), ['change']);
        bind(document.querySelector('select[name="patient_id"]'), ['change']);
    })();
</script>
