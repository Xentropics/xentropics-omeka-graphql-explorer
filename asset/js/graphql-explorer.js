/**
 * Mounts the vendored GraphiQL against the version this page selected, and
 * wires the sample picker above it to its editor.
 *
 * The fetcher is written out here rather than taken from
 * `GraphiQL.createFetcher` so that two things stay explicit: the session cookie
 * travels with the request, which is what makes results carry the signed-in
 * identity, and the endpoint is the one the page chose, version pin included.
 */
(function () {
    'use strict';

    // Wired before the editor, and outside its guard: switching version is how
    // you get off a version whose schema failed to load.
    wireVersionPicker();

    var mount = document.getElementById('graphiql');
    if (!mount) {
        return;
    }

    if (typeof React === 'undefined' || typeof ReactDOM === 'undefined' || typeof GraphiQL === 'undefined') {
        mount.textContent = 'The GraphiQL bundle did not load. Check that the module assets are being served.';
        return;
    }

    var endpoint = mount.getAttribute('data-endpoint');
    var samples = document.getElementById('graphql-sample');
    var format = document.getElementById('graphql-format');
    var status = document.querySelector('.graphql-samples-status');
    // Keyed per version, so switching versions in the picker does not hand the
    // next schema a draft written against the previous one.
    var storage = makeStorage('omekaGraphQL.v' + (endpoint.split('/v')[1] || 'default') + '.');

    /**
     * Which URL a query goes to, which is where the answer's format is asked
     * for.
     *
     * Never for introspection. GraphiQL's documentation sidebar and its
     * completions are introspection queries sent through this same fetcher,
     * and they read a GraphQL result: answered as a graph they would leave the
     * editor with no schema to check against, which looks like the endpoint
     * being broken rather than like a format having been chosen.
     */
    function urlFor(graphQLParams) {
        var wantsGraph = format && 'jsonld' === format.value;
        var introspection = /\b__schema\b|\b__type\b/.test(graphQLParams.query || '');
        return wantsGraph && !introspection ? endpoint + '?format=jsonld' : endpoint;
    }

    function fetcher(graphQLParams) {
        return fetch(urlFor(graphQLParams), {
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
    wireFormat();

    /**
     * The answer format is a property of the next request, not of the one
     * already shown, so choosing one says so rather than silently changing
     * what the pane below means.
     */
    function wireFormat() {
        if (!format) {
            return;
        }
        format.addEventListener('change', function () {
            var message = status && status.getAttribute(
                'jsonld' === format.value ? 'data-jsonld-message' : 'data-json-message'
            );
            if (status && message) {
                status.textContent = message;
            }
        });
    }

    /**
     * The query the editor opens on, taken from the first sample so the page
     * has one source of examples rather than two that can disagree.
     */
    function firstSample() {
        var first = samples && samples.querySelector('option[data-query]');
        return first
            ? first.getAttribute('data-query')
            : '# The schema is generated from your resource templates.\n'
                + '# Ctrl/Cmd + Enter runs; the Docs sidebar lists every type.\n'
                + '{\n  graphqlSchemaVersion\n}\n';
    }

    /**
     * Choosing a sample writes it into the query editor.
     *
     * On change rather than behind a button: there is nothing to confirm, and
     * what the choice did is visible in the editor a line below. Bound after
     * the render above, because the editor it writes into does not exist until
     * React has mounted.
     *
     * The editor opens on the first sample, so the picker opens showing it.
     */
    function wireSamples() {
        if (!samples) {
            return;
        }

        var first = samples.querySelector('option[data-query]');
        if (first) {
            first.selected = true;
        }

        samples.addEventListener('change', function () {
            var option = samples.options[samples.selectedIndex];
            var query = option && option.getAttribute('data-query');
            if (query) {
                load(query, option.textContent.trim(), option.getAttribute('data-description'));
            }
        });
    }

    function load(query, title, description) {
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
        describe(title, description);
    }

    /**
     * Says what the sample is for, where a screen reader will read it too.
     *
     * This is where the descriptions live now. One of them beside the editor
     * when it is the one you just chose is worth reading; all ten at once,
     * above the editor, were a wall to scroll past on every visit.
     */
    function describe(title, description) {
        if (!status) {
            return;
        }
        status.textContent = '';
        var name = document.createElement('b');
        name.textContent = title;
        status.appendChild(name);
        if (description) {
            status.appendChild(document.createTextNode(' — ' + description));
        }
    }

    /** Says that the editor was not there to write into. */
    function announce(template, title) {
        if (status && template) {
            status.textContent = template.replace('%s', title);
        }
    }

    /**
     * The version picker navigates on choice, so its submit button goes.
     *
     * Removed here rather than left out of the markup: the form is a plain GET
     * that works without any of this, and the button is the only thing that
     * makes it work. Nothing else on the page survives without JavaScript, but
     * that is a reason to keep the one part that does, not to break it. It is
     * removed rather than hidden because Omeka styles `.button` with an
     * explicit `display`, which beats the `hidden` attribute.
     *
     * Choosing a version changes the page. WCAG 3.2.2 allows that on input
     * only where the behaviour is advised beforehand, and the hint that did
     * so was removed from the view. The keyboard path needs more than a
     * `change` listener: arrowing through a closed select fires `change` on
     * every option it passes, so a reader would be navigated away from the
     * one they were heading for. Arrowing is therefore treated as browsing,
     * and Enter or leaving the field as choosing.
     */
    function wireVersionPicker() {
        var picker = document.getElementById('graphql-version');
        if (!picker || !picker.form) {
            return;
        }

        var submitButton = picker.form.querySelector('button[type="submit"]');
        if (submitButton) {
            submitButton.remove();
        }

        var current = picker.value;
        var browsing = false;
        var submitted = false;

        function go() {
            browsing = false;
            if (!submitted && picker.value !== current) {
                submitted = true;
                picker.form.submit();
            }
        }

        picker.addEventListener('keydown', function (event) {
            if ('ArrowUp' === event.key || 'ArrowDown' === event.key) {
                browsing = true;
            } else if ('Enter' === event.key) {
                event.preventDefault();
                go();
            }
        });

        picker.addEventListener('change', function () {
            if (!browsing) {
                go();
            }
        });

        // Tabbing away commits what was arrowed to. Without this the choice
        // would be silently dropped, the button that used to catch it being
        // gone.
        picker.addEventListener('blur', function () {
            if (browsing) {
                go();
            }
        });
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
