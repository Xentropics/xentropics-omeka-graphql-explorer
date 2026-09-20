/**
 * Mounts the vendored GraphiQL against the version this page selected, and
 * wires the sample queries beside it to its editor.
 *
 * The fetcher is written out here rather than taken from
 * `GraphiQL.createFetcher` so that two things stay explicit: the session cookie
 * travels with the request, which is what makes results carry the signed-in
 * identity, and the endpoint is the one the page chose, version pin included.
 */
(function () {
    'use strict';

    var mount = document.getElementById('graphiql');
    if (!mount) {
        return;
    }

    if (typeof React === 'undefined' || typeof ReactDOM === 'undefined' || typeof GraphiQL === 'undefined') {
        mount.textContent = 'The GraphiQL bundle did not load. Check that the module assets are being served.';
        return;
    }

    var endpoint = mount.getAttribute('data-endpoint');
    var samples = document.querySelector('.graphql-samples-list');
    var status = document.querySelector('.graphql-samples-status');
    // Keyed per version, so switching versions in the picker does not hand the
    // next schema a draft written against the previous one.
    var storage = makeStorage('omekaGraphQL.v' + (endpoint.split('/v')[1] || 'default') + '.');

    function fetcher(graphQLParams) {
        return fetch(endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json'
            },
            body: JSON.stringify(graphQLParams)
        }).then(function (response) {
            return response.text().then(function (text) {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    // A version pin the endpoint no longer serves answers 404
                    // with JSON, so anything unparseable here came from in
                    // front of the application. Say so rather than letting
                    // GraphiQL report a syntax error in its own parser.
                    return {
                        errors: [{
                            message: 'HTTP ' + response.status + ': the endpoint did not return JSON.',
                            extensions: { body: text.slice(0, 2000) }
                        }]
                    };
                }
            });
        });
    }

    mount.textContent = '';
    ReactDOM.createRoot(mount).render(
        React.createElement(GraphiQL, {
            fetcher: fetcher,
            defaultQuery: firstSample(),
            // The admin around this editor has no dark mode, so neither has the
            // editor. The stylesheet says the same thing for the frame; this
            // keeps a stored preference from overriding it.
            forcedTheme: 'light',
            storage: storage
        })
    );

    wireSamples();

    /**
     * The query the editor opens on, taken from the first sample so the page
     * has one source of examples rather than two that can disagree.
     */
    function firstSample() {
        var first = samples && samples.querySelector('.graphql-sample');
        return first
            ? first.getAttribute('data-query')
            : '# The schema is generated from your resource templates.\n'
                + '# Ctrl/Cmd + Enter runs; the Docs sidebar lists every type.\n'
                + '{\n  graphqlSchemaVersion\n}\n';
    }

    /**
     * Clicking a sample writes it into the query editor.
     *
     * Delegated, so it covers the samples whether or not the panel was open
     * when the page loaded, and bound after the render above because the
     * editor it writes into does not exist until React has mounted.
     */
    function wireSamples() {
        if (!samples) {
            return;
        }

        samples.addEventListener('click', function (event) {
            var button = event.target.closest('.graphql-sample');
            if (button) {
                load(button.getAttribute('data-query'), button.querySelector('.graphql-sample-title').textContent);
            }
        });

        var panel = samples.closest('.graphql-samples');
        if (panel) {
            // Whether the samples are worth their screen height is a judgement
            // about how well someone already knows the schema, so it is theirs
            // to make once rather than on every page load.
            if ('closed' === storage.getItem('samplesOpen')) {
                panel.open = false;
            }
            panel.addEventListener('toggle', function () {
                storage.setItem('samplesOpen', panel.open ? 'open' : 'closed');
            });
        }
    }

    function load(query, title) {
        // CodeMirror 5 hangs its instance off the element it wraps, which is
        // the only handle on the editor from outside React: the UMD bundle
        // exports the component and nothing of its context.
        var wrapper = mount.querySelector('.graphiql-query-editor .CodeMirror');
        var editor = wrapper && wrapper.CodeMirror;
        if (!editor) {
            announce(status && status.getAttribute('data-unavailable-message'), title);
            return;
        }

        var last = editor.lastLine();
        // An ordinary edit rather than setValue, so it goes into the undo
        // history: whatever was being written is one Ctrl/Cmd + Z away.
        editor.replaceRange(
            query,
            { line: 0, ch: 0 },
            { line: last, ch: editor.getLine(last).length }
        );
        editor.setCursor({ line: 0, ch: 0 });
        editor.focus();
        announce(status && status.getAttribute('data-loaded-message'), title);
    }

    /** Says what just happened, for anyone whose focus was not on the editor. */
    function announce(template, title) {
        if (status && template) {
            status.textContent = template.replace('%s', title);
        }
    }

    /**
     * localStorage behind a per-version prefix.
     *
     * GraphiQL keeps tabs, history and pane sizes in whatever storage it is
     * given, unprefixed, so two versions sharing one origin would otherwise
     * share one set of tabs.
     */
    function makeStorage(prefix) {
        return {
            getItem: function (key) {
                return localStorage.getItem(prefix + key);
            },
            setItem: function (key, value) {
                localStorage.setItem(prefix + key, value);
            },
            removeItem: function (key) {
                localStorage.removeItem(prefix + key);
            },
            clear: function () {
                Object.keys(localStorage)
                    .filter(function (key) {
                        return key.indexOf(prefix) === 0;
                    })
                    .forEach(function (key) {
                        localStorage.removeItem(key);
                    });
            },
            get length() {
                return Object.keys(localStorage).filter(function (key) {
                    return key.indexOf(prefix) === 0;
                }).length;
            }
        };
    }
}());
