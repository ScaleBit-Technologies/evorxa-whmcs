/*!
 * Evorxa Cloud - visual "operating system OR one-click app" picker for the order form.
 *
 * The importer creates two dropdowns ("Operating System" and "One-Click App"). This script
 * merges them into one picker with a switch: OS mode sets the app dropdown to "none",
 * App mode lets the client pick an app (which brings its own system). The dropdowns stay
 * in the form (hidden), so totals, validation and submission work with any order form.
 */
(function () {
    'use strict';

    var mapEl = document.getElementById('evx-of-map');
    if (!mapEl) {
        return;
    }
    var cfg;
    try {
        cfg = JSON.parse(mapEl.textContent || '{}');
    } catch (e) {
        return;
    }
    var T = cfg.t || {};
    var lastMode = null; // survives the order form re-drawing its options (e.g. billing cycle change)

    function el(tag, cls, text) {
        var node = document.createElement(tag);
        if (cls) {
            node.className = cls;
        }
        if (text !== undefined && text !== null && text !== '') {
            node.textContent = String(text);
        }
        return node;
    }

    function button(cls) {
        var b = el('button', cls);
        b.type = 'button';
        return b;
    }

    function badge(logo, letter, family) {
        var box = el('span', 'evx-of-logo' + (logo ? '' : ' evx-of-letter evx-of-' + (family || 'x')));
        if (logo) {
            var img = el('img');
            img.src = logo;
            img.alt = '';
            img.loading = 'lazy';
            box.appendChild(img);
        } else {
            box.textContent = (letter || '?').charAt(0).toUpperCase();
        }
        box.setAttribute('aria-hidden', 'true');
        return box;
    }

    /** Set the real dropdown and let the order form recalculate its totals. */
    function choose(select, subId) {
        if (!select || subId === null || subId === undefined || select.value === String(subId)) {
            return;
        }
        select.value = String(subId);
        // "input" for Livewire-bound selects (Paymenter), "change" for classic order forms.
        select.dispatchEvent(new Event('input', { bubbles: true }));
        select.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function mark(buttons, active) {
        buttons.forEach(function (b) {
            var on = b === active;
            b.classList.toggle('is-active', on);
            b.setAttribute('aria-checked', on ? 'true' : 'false');
        });
    }

    /** The wrapper the order form draws around one option (Lagom: .section, Standard Cart: .form-group). */
    function optionBlock(select, other) {
        var candidates = ['.section', '.form-group', '.panel', 'fieldset', 'tr'];
        for (var i = 0; i < candidates.length; i++) {
            var block = select.closest(candidates[i]);
            if (block && (!other || !block.contains(other))) {
                return block;
            }
        }
        return null;
    }

    /**
     * Grid column around an option block (Standard Cart puts options in half-width .col-sm-6).
     * Stops at the options container so the page layout itself is never touched.
     */
    function gridColumn(block) {
        var node = block;
        while (node && node.parentElement) {
            if (/\bcol-(xs-|sm-|md-|lg-|xl-)?\d+\b/.test(node.className || '')) {
                return node;
            }
            if (node.id === 'productConfigurableOptions' || /product-configurable-options/.test(node.className || '')) {
                return null;
            }
            node = node.parentElement;
        }
        return null;
    }

    // ------------------------------------------------------------------ operating system

    function buildOs(pane, select, def) {
        var families = [];
        var byFamily = {};
        Array.prototype.forEach.call(select.options, function (opt) {
            var c = def.choices[opt.value];
            if (!c) {
                return;
            }
            if (!byFamily[c.family]) {
                byFamily[c.family] = { key: c.family, name: c.familyName, logo: c.logo, items: [] };
                families.push(byFamily[c.family]);
            }
            byFamily[c.family].items.push({ id: opt.value, c: c });
        });

        var famRow = el('div', 'evx-of-grid evx-of-families');
        famRow.setAttribute('role', 'radiogroup');
        var verLabel = el('div', 'evx-of-label', T.version || 'Version');
        var verRow = el('div', 'evx-of-pills');
        verRow.setAttribute('role', 'radiogroup');
        pane.appendChild(famRow);
        pane.appendChild(verLabel);
        pane.appendChild(verRow);

        var famButtons = [];
        function showVersions(family) {
            verRow.innerHTML = '';
            var pills = [];
            family.items.forEach(function (item) {
                var pill = button('evx-of-pill');
                pill.setAttribute('role', 'radio');
                pill.appendChild(el('span', 'evx-of-pill-title', item.c.title));
                if (item.c.sub) {
                    pill.appendChild(el('span', 'evx-of-pill-sub', item.c.sub));
                }
                pill.addEventListener('click', function () {
                    choose(select, item.id);
                    mark(pills, pill);
                });
                pills.push(pill);
                verRow.appendChild(pill);
            });
            var ids = family.items.map(function (i) { return i.id; });
            mark(pills, pills[ids.indexOf(select.value)] || null);
        }

        families.forEach(function (family) {
            var card = button('evx-of-card');
            card.setAttribute('role', 'radio');
            card.appendChild(badge(family.logo, family.name, family.key));
            var text = el('span', 'evx-of-text');
            text.appendChild(el('span', 'evx-of-title', family.name));
            text.appendChild(el('span', 'evx-of-sub', family.items.length > 1
                ? (T.versions || ':n versions').replace(':n', family.items.length)
                : family.items[0].c.title));
            card.appendChild(text);
            card.addEventListener('click', function () {
                mark(famButtons, card);
                var ids = family.items.map(function (i) { return i.id; });
                if (ids.indexOf(select.value) === -1) {
                    choose(select, ids[0]); // first listed = recommended version of that family
                }
                showVersions(family);
            });
            famButtons.push(card);
            famRow.appendChild(card);
        });

        var start = families.filter(function (f) {
            return f.items.some(function (i) { return i.id === select.value; });
        })[0] || families[0];
        if (start) {
            mark(famButtons, famButtons[families.indexOf(start)]);
            showVersions(start);
        }
    }

    // ------------------------------------------------------------------ one-click apps

    /** Returns the id of the "none" choice (if any). */
    function buildApps(pane, select, def, withNone) {
        var noneId = null;
        if (!withNone) {
            pane.appendChild(el('p', 'evx-of-hint', T.appHint || ''));
        }
        var grid = el('div', 'evx-of-grid evx-of-apps');
        grid.setAttribute('role', 'radiogroup');
        pane.appendChild(grid);
        var cards = [];
        Array.prototype.forEach.call(select.options, function (opt) {
            var c = def.choices[opt.value];
            if (!c) {
                return;
            }
            var none = c.value === 'none';
            if (none) {
                noneId = opt.value;
                if (!withNone) {
                    return;
                }
            }
            var card = button('evx-of-card' + (none ? ' evx-of-none' : ''));
            card.setAttribute('role', 'radio');
            if (none) {
                var icon = el('span', 'evx-of-logo evx-of-letter evx-of-plain');
                icon.innerHTML = '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><rect x="3" y="4" width="18" height="12" rx="2" fill="none" stroke="currentColor" stroke-width="2"/><path d="M8 20h8M12 16v4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';
                card.appendChild(icon);
            } else {
                card.appendChild(badge(c.logo, c.title, 'app'));
            }
            var text = el('span', 'evx-of-text');
            text.appendChild(el('span', 'evx-of-title', none ? (T.none || 'No app') : c.title));
            text.appendChild(el('span', 'evx-of-sub', none ? (T.noneSub || '') : c.sub));
            card.appendChild(text);
            card.addEventListener('click', function () {
                choose(select, opt.value);
                mark(cards, card);
            });
            card.setAttribute('data-value', opt.value);
            cards.push(card);
            grid.appendChild(card);
        });
        mark(cards, cards.filter(function (c) { return c.getAttribute('data-value') === select.value; })[0] || null);
        pane.evxReset = function () {
            mark(cards, null);
        };
        return noneId;
    }

    // ------------------------------------------------------------------ start

    function init() {
        var os = null;
        var app = null;
        Object.keys(cfg.options || {}).forEach(function (optionId) {
            // The map can name the field (Paymenter: checkoutConfig.os); WHMCS uses configoption[<id>].
            var def = cfg.options[optionId];
            var select = document.querySelector('select[name="' + (def.name || 'configoption[' + optionId + ']') + '"]');
            if (!select || select.getAttribute('data-evx-of')) {
                return;
            }
            select.setAttribute('data-evx-of', '1');
            // A re-render that kept our old picker but reset the dropdown: drop the stale copy.
            var stale = select.parentNode.querySelector('.evx-of');
            if (stale) {
                stale.parentNode.removeChild(stale);
            }
            var entry = { select: select, def: def };
            if (entry.def.type === 'os') {
                os = entry;
            } else {
                app = entry;
            }
        });
        if (!os && !app) {
            return;
        }

        // Only one of the two exists: enhance it on its own.
        if (!os || !app) {
            var only = os || app;
            var single = el('div', 'evx-of');
            only.select.parentNode.insertBefore(single, only.select);
            only.select.classList.add('evx-of-native');
            if (os) {
                buildOs(single, os.select, os.def);
            } else {
                buildApps(single, app.select, app.def, true);
            }
            return;
        }

        // Both: one picker with an "Operating system | One-click app" switch inside the OS block.
        var host = el('div', 'evx-of');
        os.select.parentNode.insertBefore(host, os.select);
        os.select.classList.add('evx-of-native');
        app.select.classList.add('evx-of-native');
        var appBlock = optionBlock(app.select, os.select);
        if (appBlock) {
            appBlock.classList.add('evx-of-hidden'); // still submitted with the form
            var appCol = gridColumn(appBlock);
            if (appCol && !appCol.contains(os.select)) {
                appCol.classList.add('evx-of-hidden');
            }
        }
        var osBlock = optionBlock(os.select, app.select);
        var osCol = osBlock ? gridColumn(osBlock) : null;
        if (osCol && !osCol.contains(app.select)) {
            osCol.classList.add('evx-of-full'); // picker needs the whole row
        }
        var heading = osBlock ? osBlock.querySelector('.section-title, h2, h3, label') : null;
        if (heading && T.title) {
            heading.textContent = T.title;
        }

        var toggle = el('div', 'evx-of-switch');
        toggle.setAttribute('role', 'tablist');
        var osTab = button('evx-of-tab');
        var appTab = button('evx-of-tab');
        osTab.setAttribute('role', 'tab');
        appTab.setAttribute('role', 'tab');
        osTab.appendChild(el('span', null, T.modeOs || 'Operating system'));
        appTab.appendChild(el('span', null, T.modeApp || 'One-click app'));
        toggle.appendChild(osTab);
        toggle.appendChild(appTab);
        host.appendChild(toggle);

        var osPane = el('div', 'evx-of-pane');
        var appPane = el('div', 'evx-of-pane');
        host.appendChild(osPane);
        host.appendChild(appPane);
        buildOs(osPane, os.select, os.def);
        var noneId = buildApps(appPane, app.select, app.def, false);

        function setMode(mode, userAction) {
            var isApp = mode === 'app';
            lastMode = mode;
            osTab.classList.toggle('is-active', !isApp);
            appTab.classList.toggle('is-active', isApp);
            osTab.setAttribute('aria-selected', !isApp ? 'true' : 'false');
            appTab.setAttribute('aria-selected', isApp ? 'true' : 'false');
            osPane.hidden = isApp;
            appPane.hidden = !isApp;
            if (!isApp && userAction && noneId !== null) {
                choose(app.select, noneId); // back to a plain OS: drop the app
                if (appPane.evxReset) {
                    appPane.evxReset();
                }
            }
        }
        osTab.addEventListener('click', function () { setMode('os', true); });
        appTab.addEventListener('click', function () { setMode('app', true); });

        var appChosen = app.def.choices[app.select.value] && app.def.choices[app.select.value].value !== 'none';
        setMode(appChosen ? 'app' : (lastMode || 'os'), false);
    }

    /**
     * Order forms re-draw the options section over AJAX (billing cycle change, promo codes...),
     * which brings back fresh dropdowns. Watch for them and enhance again; init() only acts on
     * dropdowns it has not seen, so this is cheap and cannot loop.
     */
    function watch() {
        if (!window.MutationObserver || !document.body) {
            return;
        }
        var queued = false;
        new MutationObserver(function () {
            if (queued) {
                return;
            }
            queued = true;
            (window.requestAnimationFrame || setTimeout)(function () {
                queued = false;
                init();
            });
        }).observe(document.body, { childList: true, subtree: true });
    }

    function start() {
        init();
        watch();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
