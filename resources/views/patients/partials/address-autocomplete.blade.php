{{--
    Shared PSGC address autocomplete for the Add/Edit Patient forms (Phase 3).

    Additive only: it never renames inputs, never writes to address_line, and
    never adds hidden form fields - the selected PSGC code stays client-side
    because server-side Patient validation is explicitly deferred to Phase 4.

    Decisions:
    - Plain inline <style>/<script> (the existing pattern on these pages) so
      no Vite rebuild or package install is required.
    - Inputs are found via [name=...] so no id assumptions are baked in.
    - Barangay suggestions are filtered to level Bgy and city suggestions to
      City/Mun. The city input is autofilled from the response's approved
      `city` field - never by splitting `label`.
    - The barangay field is cleared only when this script autofilled it from
      a different city than the one now committed; user-typed values are
      never cleared.
    - Fetch uses an AbortController plus a per-field sequence token so a slow
      response can never overwrite a newer keystroke's result.
    - DOM is built with textContent only (no innerHTML).
    - ARIA combobox/listbox keyboard support: Arrow keys, Enter, Escape.
--}}
<style>
    .psgc-ac { position: relative; }
    .psgc-ac-panel {
        position: absolute;
        left: 0;
        right: 0;
        top: calc(100% + 4px);
        z-index: 40;
        display: none;
        max-height: 260px;
        overflow-y: auto;
        background: #fff;
        border: 1px solid #E7E9E5;
        border-radius: .5rem;
        box-shadow: 0 10px 24px rgba(25, 53, 95, .14);
    }
    .psgc-ac-panel.psgc-ac-open { display: block; }
    .psgc-ac-list { margin: 0; padding: 4px; list-style: none; }
    .psgc-ac-option { padding: 8px 10px; border-radius: .375rem; cursor: pointer; }
    .psgc-ac-option:hover { background: #F6F4EE; }
    .psgc-ac-option.psgc-ac-active { background: #EDF5EC; }
    .psgc-ac-option-name { font-size: .875rem; line-height: 1.25rem; color: #19355F; }
    .psgc-ac-option-label { font-size: .75rem; line-height: 1rem; color: #657083; margin-top: 2px; }
    .psgc-ac-note { padding: 10px 12px; font-size: .8125rem; color: #657083; }
    .psgc-ac-note.psgc-ac-busy { color: #4aa04c; }
    .psgc-ac-note.psgc-ac-fail { color: #dc2626; }
    .psgc-ac-hidden { display: none; }
</style>
<script>
(function () {
    'use strict';

    var ENDPOINT = @json(route('locations.search'));
    var MIN_QUERY = 2;
    var MAX_QUERY = 100;
    var DEBOUNCE_MS = 350;
    var uid = 0;

    function boot() {
        var barangayInput = document.querySelector('[name="barangay"]');
        var cityInput = document.querySelector('[name="city_municipality"]');
        if (!barangayInput || !cityInput) return;
        if (barangayInput.dataset.psgcAc === '1' || cityInput.dataset.psgcAc === '1') return;

        // Only ever set by this script's own barangay selection; user-typed
        // barangay values are never claimed and therefore never cleared.
        var barangayAutoFilled = false;
        var autoFilledCity = null;

        function resetPanel(state) {
            state.note.textContent = '';
            state.note.className = 'psgc-ac-note psgc-ac-hidden';
            state.ul.textContent = '';
            state.optionEls = [];
        }

        function openList(state) {
            state.panel.classList.add('psgc-ac-open');
            state.input.setAttribute('aria-expanded', 'true');
            state.open = true;
        }

        function hideList(state) {
            if (state.timer) {
                clearTimeout(state.timer);
                state.timer = null;
            }
            state.seq += 1;
            if (state.controller) {
                state.controller.abort();
                state.controller = null;
            }
            setActive(state, -1);
            state.panel.classList.remove('psgc-ac-open');
            state.input.setAttribute('aria-expanded', 'false');
            state.input.removeAttribute('aria-activedescendant');
            state.open = false;
        }

        function setActive(state, index) {
            var previous = state.optionEls[state.active];
            if (previous) {
                previous.classList.remove('psgc-ac-active');
                previous.setAttribute('aria-selected', 'false');
            }
            state.active = index;
            var current = state.optionEls[index];
            if (current) {
                current.classList.add('psgc-ac-active');
                current.setAttribute('aria-selected', 'true');
                state.input.setAttribute('aria-activedescendant', current.id);
                current.scrollIntoView({ block: 'nearest' });
            } else {
                state.input.removeAttribute('aria-activedescendant');
            }
        }

        function showNote(state, text, modifier) {
            resetPanel(state);
            state.note.textContent = text;
            state.note.className = 'psgc-ac-note' + (modifier ? ' ' + modifier : '');
            state.items = [];
            openList(state);
        }

        function showOptions(state) {
            resetPanel(state);
            state.items.forEach(function (row, index) {
                var option = document.createElement('li');
                option.className = 'psgc-ac-option';
                option.id = state.ul.id + '-opt-' + index;
                option.setAttribute('role', 'option');
                option.setAttribute('aria-selected', 'false');

                var name = document.createElement('div');
                name.className = 'psgc-ac-option-name';
                name.textContent = row.name;
                option.appendChild(name);

                if (row.label && row.label !== row.name) {
                    var hint = document.createElement('div');
                    hint.className = 'psgc-ac-option-label';
                    hint.textContent = row.label;
                    option.appendChild(hint);
                }

                option.addEventListener('mousedown', function (event) {
                    event.preventDefault();
                    select(state, index);
                });

                state.ul.appendChild(option);
                state.optionEls.push(option);
            });
            state.active = -1;
            openList(state);
        }

        function reconcileBarangayWithCity() {
            if (!barangayAutoFilled || autoFilledCity === null) return;
            if (cityInput.value.trim() === autoFilledCity) return;
            barangayInput.value = '';
            barangayAutoFilled = false;
            autoFilledCity = null;
            barangayInput.dispatchEvent(new Event('blur'));
        }

        function select(state, index) {
            var row = state.items[index];
            if (!row) return;

            state.input.value = row.name;
            hideList(state);

            if (state.kind === 'barangay') {
                if (row.city) {
                    cityInput.value = row.city;
                    barangayAutoFilled = true;
                    autoFilledCity = row.city;
                    cityInput.dispatchEvent(new Event('blur'));
                } else {
                    barangayAutoFilled = false;
                    autoFilledCity = null;
                }
            } else {
                reconcileBarangayWithCity();
            }
            state.input.focus();
        }

        function runSearch(state) {
            var value = state.input.value.trim();
            if (value.length < MIN_QUERY || value.length > MAX_QUERY) {
                hideList(state);
                return;
            }
            if (state.controller) state.controller.abort();
            var controller = new AbortController();
            state.controller = controller;
            var seq = state.seq;

            showNote(state, 'Searching...', 'psgc-ac-busy');

            fetch(ENDPOINT + '?q=' + encodeURIComponent(value), {
                signal: controller.signal,
                headers: { 'Accept': 'application/json' }
            })
                .then(function (response) {
                    if (!response.ok) throw new Error('search request failed');
                    return response.json();
                })
                .then(function (data) {
                    if (seq !== state.seq) return;
                    state.controller = null;
                    var rows = data && Array.isArray(data.results) ? data.results : [];
                    state.items = rows.filter(function (row) {
                        return state.levels.indexOf(row.level) !== -1;
                    });
                    if (!state.items.length) {
                        showNote(state, 'No locations found.', '');
                        return;
                    }
                    showOptions(state);
                })
                .catch(function (error) {
                    if (error && error.name === 'AbortError') return;
                    if (seq !== state.seq) return;
                    state.controller = null;
                    showNote(state, 'Location search is temporarily unavailable.', 'psgc-ac-fail');
                });
        }

        function attach(input, levels, kind) {
            input.dataset.psgcAc = '1';
            input.autocomplete = 'off';

            var wrap = document.createElement('div');
            wrap.className = 'psgc-ac';
            input.parentNode.insertBefore(wrap, input);
            wrap.appendChild(input);

            var panel = document.createElement('div');
            panel.className = 'psgc-ac-panel';
            var note = document.createElement('div');
            note.className = 'psgc-ac-note psgc-ac-hidden';
            note.setAttribute('aria-live', 'polite');
            var ul = document.createElement('ul');
            ul.className = 'psgc-ac-list';
            ul.id = 'psgc-ac-list-' + (++uid);
            ul.setAttribute('role', 'listbox');
            panel.appendChild(note);
            panel.appendChild(ul);
            wrap.appendChild(panel);

            input.setAttribute('role', 'combobox');
            input.setAttribute('aria-autocomplete', 'list');
            input.setAttribute('aria-controls', ul.id);
            input.setAttribute('aria-expanded', 'false');
            input.setAttribute('aria-haspopup', 'listbox');

            var state = {
                input: input,
                kind: kind,
                levels: levels,
                wrap: wrap,
                panel: panel,
                note: note,
                ul: ul,
                items: [],
                optionEls: [],
                active: -1,
                open: false,
                timer: null,
                controller: null,
                seq: 0
            };

            input.addEventListener('input', function () {
                if (state.timer) {
                    clearTimeout(state.timer);
                    state.timer = null;
                }
                state.seq += 1;
                if (state.controller) {
                    state.controller.abort();
                    state.controller = null;
                }
                var value = state.input.value.trim();
                if (value.length < MIN_QUERY || value.length > MAX_QUERY) {
                    state.items = [];
                    resetPanel(state);
                    hideList(state);
                    return;
                }
                state.timer = setTimeout(function () {
                    state.timer = null;
                    runSearch(state);
                }, DEBOUNCE_MS);
            });

            input.addEventListener('keydown', function (event) {
                if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                    if (!state.open) {
                        var value = state.input.value.trim();
                        if (!state.items.length || value.length < MIN_QUERY || value.length > MAX_QUERY) return;
                        showOptions(state);
                    }
                    event.preventDefault();
                    var next = state.active + (event.key === 'ArrowDown' ? 1 : -1);
                    if (next < -1) next = -1;
                    if (next > state.items.length - 1) next = state.items.length - 1;
                    setActive(state, next);
                    return;
                }
                if (event.key === 'Enter') {
                    if (!state.open) return;
                    event.preventDefault();
                    if (state.active >= 0) select(state, state.active);
                    return;
                }
                if (event.key === 'Escape') {
                    if (!state.open) return;
                    event.preventDefault();
                    hideList(state);
                    return;
                }
                if (event.key === 'Tab' && state.open) hideList(state);
            });

            input.addEventListener('focus', function () {
                if (!state.items.length) return;
                var value = state.input.value.trim();
                if (value.length < MIN_QUERY || value.length > MAX_QUERY) return;
                showOptions(state);
            });

            input.addEventListener('blur', function () {
                hideList(state);
            });

            return state;
        }

        var barangayState = attach(barangayInput, ['Bgy'], 'barangay');
        var cityState = attach(cityInput, ['City', 'Mun'], 'city');
        var states = [barangayState, cityState];

        // Manual edits drop the autofill claim so user-typed barangay values
        // are never cleared by a later city change.
        barangayInput.addEventListener('input', function () {
            barangayAutoFilled = false;
            autoFilledCity = null;
        });

        cityInput.addEventListener('change', function () {
            reconcileBarangayWithCity();
        });

        document.addEventListener('mousedown', function (event) {
            for (var i = 0; i < states.length; i++) {
                if (!states[i].wrap.contains(event.target)) hideList(states[i]);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
</script>
