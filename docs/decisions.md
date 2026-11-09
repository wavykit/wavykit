# Direction and decisions

Decisions about what WavyKit contains and how it grows. Recorded 2026-11-09 by
the maintainers. Plans below are intentions, not release promises; the
[monorepo guide](monorepo.md) owns layout, versioning, and the release process.

## Package boundaries

**Pages is the foundation.** Other WavyKit components depend on
`wavykit/pages`; it is a separate package so that a component needing page
facts does not also install menus or anything else it does not use. Pages on
its own provides breadcrumbs, but most applications install it through a
component such as Navigation.

**Pages holds facts about a page; components use them.** A capability belongs
in Pages when it describes a page: identity, hierarchy, label, title, icon,
route association, URL, request context, or breadcrumbs, which follow directly
from the hierarchy. A capability that uses those facts belongs in its own
package: menus, metadata tags, search indexing, framework integrations, and
actions. When a component needs a new page fact, the fact is added to Pages
and the behavior that uses it stays in the component.

**Each package owns its own tools.** Inspection commands and test assertions
for a package's concepts ship with that package, not in a shared testing or
development package.

**The split is reviewed before 1.0.** If no component other than Navigation
depends on Pages by then, the maintainers will consider combining the two
packages. Changes like that are acceptable during 0.x and costly after 1.0.

## Supported versions

Packages support Laravel 13 and PHP 8.3 or later. Support for earlier Laravel
versions is not planned and will be considered only if adopters ask for it;
widening support means adding those versions to CI.

## Plan

### v0.1

The first release, `v0.1.0`, publishes Pages and Navigation together with the
behavior they have today. Its preparation is tracked in each package's
release checklist.

### v0.2

Planned for the next minor release:

- **`wavykit/inertia`**, a new package that shares breadcrumbs and the current
  page with Inertia, plus menus when Navigation is installed. Applications opt
  in with one explicit call in their `HandleInertiaRequests::share()` method,
  so they choose prop names, which menus to send, and lazy evaluation. The
  package ships hand-written TypeScript types for the shared data.
- **Inspection commands and test assertions** in Pages and Navigation, such as
  listing and checking registered pages and menus, and asserting the
  breadcrumbs, current page, or menu contents for a request.

### Later

These are candidates without a release target:

- generated TypeScript types for an application's page IDs;
- a check that every named route has a page or is deliberately excluded;
- optional route and view declarations alongside page definitions.

Other components, such as an actions component, will be named and scoped when
they are planned.
