# Vendored explorer bundles

Third-party JavaScript, committed verbatim. The admin explorer is GraphiQL, and
these are the files it needs.

| File | Package | Version | Source |
|---|---|---|---|
| `graphiql.min.js` | [graphiql](https://www.npmjs.com/package/graphiql) | 4.1.2 | `https://cdn.jsdelivr.net/npm/graphiql@4.1.2/dist/index.umd.js` |
| `graphiql.min.css` | graphiql | 4.1.2 | `https://cdn.jsdelivr.net/npm/graphiql@4.1.2/dist/style.css` |
| `react.production.min.js` | [react](https://www.npmjs.com/package/react) | 18.3.1 | `https://cdn.jsdelivr.net/npm/react@18.3.1/umd/react.production.min.js` |
| `react-dom.production.min.js` | react-dom | 18.3.1 | `https://cdn.jsdelivr.net/npm/react-dom@18.3.1/umd/react-dom.production.min.js` |

```
867226ba8af5ce7949903fc53932f78dac9f4bfc6cc8da0fd3869b8bc6c5256b  graphiql.min.js
30487e07635d312a63f647b140d37740a73c53332b0975bf4b1fe985ca6ec885  graphiql.min.css
d949f1c3687aedadcedac85261865f29b17cd273997e7f6b2bfc53b2f9d4c4dd  react.production.min.js
35f4f974f4b2bcd44da73963347f8952e341f83909e4498227d4e26b98f66f0d  react-dom.production.min.js
```

## Why these versions, and why files rather than a CDN

The image this module is deployed on serves a CSP of `script-src 'self'
'unsafe-inline' https://code.jquery.com; connect-src 'self'`. A CDN-loaded
editor is simply blocked there, with no visible error, so the bundles are
served from the module or not at all.

**GraphiQL 4.1.2 is the newest release with a UMD build.** GraphiQL 5 ships ESM
only, which needs a bundler or an import map — a build step this module does
not have and does not want. React is pinned to 18 for the same reason: React 19
dropped its UMD builds, and GraphiQL 4 accepts `^18 || ^19`.

The `.map` files are deliberately not vendored. Nothing fetches them unless a
developer opens the devtools sourcemap panel, and they are larger than the
bundles they describe.

## Updating

```sh
cd asset/vendor
curl -sSLO https://cdn.jsdelivr.net/npm/react@18.3.1/umd/react.production.min.js
curl -sSLO https://cdn.jsdelivr.net/npm/react-dom@18.3.1/umd/react-dom.production.min.js
curl -sSL -o graphiql.min.js  https://cdn.jsdelivr.net/npm/graphiql@4.1.2/dist/index.umd.js
curl -sSL -o graphiql.min.css https://cdn.jsdelivr.net/npm/graphiql@4.1.2/dist/style.css
shasum -a 256 *.js *.css
```

Bump the versions in the commands, the table and the checksums together, and
open the explorer afterwards: the UMD global (`window.GraphiQL`, a component
function) and the `fetcher`/`storage` props in `asset/js/graphql-explorer.js`
are the contract that a major release breaks.
