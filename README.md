# GraphQL explorer for Omeka S

GraphiQL in the Omeka S admin, pointed at the endpoint served by
[xentropics-omeka-graphql](https://github.com/xentropics/xentropics-omeka-graphql).

## Why it is a separate module

The endpoint is a few hundred kilobytes of PHP that an installation may want to
run without ever opening a query editor. The editor is 1.6 MB of vendored
JavaScript with no reason to sit in a production image serving machine clients.
Splitting them lets an operator install the endpoint and leave the editor out,
which is the more common of the two deployments, and it keeps a CVE in a
JavaScript bundle away from the module that answers requests.

## Requirements

| | |
|---|---|
| Omeka S | 4.0 or later |
| PHP | 8.1 or later |
| Module | `OmekaGraphQL`, installed and active |

Installation refuses without the endpoint module. Omeka has no declarative
dependency between modules, so the check is made in `Module::install()` —
failing there is the kind moment to fail, because the alternative is an admin
page rendering an editor against a route that does not exist.

## Installing

The module namespace is `OmekaGraphQLExplorer`, and Omeka derives the autoload
path from the directory name, so the directory under `modules/` **must** be
named `OmekaGraphQLExplorer` — not the repository name.

```sh
cd /path/to/omeka-s/modules
git clone https://github.com/xentropics/xentropics-omeka-graphql-explorer.git OmekaGraphQLExplorer
```

No `composer install` is needed: the module has no runtime dependencies, and
the JavaScript it needs is committed under `asset/vendor/`. Install it from
**Admin → Modules**; it adds **GraphQL explorer** to the admin menu.

## Using it

Pick a served version and write a query. Queries go to the real endpoint —
`/graphql/v{n}` — from the browser, not through a server-side shortcut here.
That is the point: what an explorer is for is seeing what a client sees, and
that includes routing, version pinning and the status code.

Two consequences the page states rather than leaves to be discovered:

- **Results carry your identity.** An admin sees more here than an anonymous
  client will, because the endpoint filters per identity. Check a public query
  with an API key or a logged-out browser before concluding that something is
  visible.
- **Documentation and completions are introspection.** If introspection is
  disabled in the endpoint's configuration, queries still run but the Docs
  sidebar and autocompletion will not load.

Each version keeps its own tabs and history, per browser tab, so two versions
can be compared side by side without their drafts colliding.

## Sample queries

Beside the version picker is a **Sample** select, and the editor opens on the
first entry in it. They are generated from the version being explored rather
than kept as a file of fixtures, because there is nothing to hard-code: every
type and field name a client can write comes from this installation's resource
templates, so an example naming `Item` or `title` is an example that does not
run here.

What is stable is the shape of the questions, and that is what
[`SampleQueryFactory`](src/Sample/SampleQueryFactory.php) builds, filling in
names from the open version — a page of a type, a search, the language chain,
a link followed through the `Resource` interface, an item's media, an item
set's contents, which values were substituted, and what introspection answers.
A sample whose shape the version cannot answer is left out rather than shown
broken: no media-backed template, no media sample; introspection disabled, no
introspection sample. Which type they are written against comes from the
version's own generation report: a template the generator saw no resource
using is ranked last, because a sample built on it runs correctly and answers
`totalCount: 0`. Omeka ships a "Base Resource" template that most
installations never assign to anything and that carries more properties than
any template a curator writes, so without that it wins on richness and every
sample is written against the one type holding no data. Only the listing is
held to it — a sample about following links has to use a type that has links,
and demonstrating the feature against an empty type beats not demonstrating
it. Each declares any variable it needs with a default, so
it runs from the editor without the variables pane being filled in first.

Choosing one writes it into the editor as an ordinary edit rather than a
reset, so undo puts back whatever was being written, and the line under the
picker says what that sample is for. The descriptions are one line each on
demand for a reason: as a grid of cards they were a screenful to scroll past
on every visit, in a page whose whole point is the editor underneath them.

## Theming

GraphiQL 4 declares its whole palette as custom properties on its container,
so [`asset/css/graphql-explorer.css`](asset/css/graphql-explorer.css) restates
them in Omeka's colours — `$red` for the keywords, the buttons and the links,
`$blue` for the body text, Lato and Source Code Pro, which the admin already
loads — and the editor arrives in the admin's own clothes. That is the whole
of it: nothing reaches past those properties into GraphiQL's class names,
which would be a fork of a file this module does not own.

The five hues Omeka has no opinion on — the rest of the syntax palette — are
picked to stay apart from each other and to clear WCAG AA as text on white,
which the stock palette's pink does not. The editor is pinned to its light
theme, because the admin around it has no dark mode to follow it into one.

## Vendored JavaScript

`asset/vendor/` holds GraphiQL and React as committed files, with their
versions, source URLs and checksums in
[asset/vendor/README.md](asset/vendor/README.md). They are served from the
module rather than from a CDN because the deployment target sets a
`script-src 'self'` content security policy, under which a CDN-loaded editor is
blocked with no visible error.

GraphiQL is pinned to the 4.x line: 5 ships ESM only, which needs a bundler or
an import map, and this module has no build step and wants none. React is pinned
to 18 for the same reason — 19 dropped its UMD builds.

## Developing

```sh
composer install
composer cs-check    # php-cs-fixer, dry run
composer cs-fix
composer test        # phpunit
```

Laminas is pinned in `require-dev` so an editor can resolve it; `Omeka\…` and
`OmekaGraphQL\…` come from the workspace rather than from composer. See the
endpoint module's README for why Omeka S cannot be a composer dependency and
what to do instead.

The test suite is one question — do the generated samples still agree with the
schema the endpoint builds? — and answering it needs the endpoint module,
which is not a composer dependency for the reason above. So
[`test/bootstrap.php`](test/bootstrap.php) finds it the way Omeka does, next
door: `../OmekaGraphQL` where Omeka requires the directory to carry the
namespace, or `../xentropics-omeka-graphql` where the two repositories are
checked out side by side. `OMEKA_GRAPHQL_PATH` overrides both.

```sh
OMEKA_GRAPHQL_PATH=/path/to/modules/OmekaGraphQL composer test
```

Not finding it fails the run rather than skipping it. A suite whose only job
is to prove the samples still fit has nothing to report when it cannot see
what they are supposed to fit, and reporting that as green is worse than
reporting nothing.

`.php-cs-fixer.dist.php` is Omeka S's own ruleset copied verbatim, with a finder
for a module rather than for core.

The admin page uses Omeka's own components and is checked against WCAG 2.1 AA
with axe-core. GraphiQL's own interface is third-party and is not covered by
that check.

## Licence

MIT. See [LICENSE](LICENSE).
