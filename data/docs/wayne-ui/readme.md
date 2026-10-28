# WayneUI

The Wayne State University design system. One set of brand tokens, one set of
components, four ways to use them, and an accessibility bar that is a command
anyone can run rather than a claim a README makes.

> **2.0 is a hard break from [`cit-web-infra/wayne-ui`](https://git.wayne.edu/cit-web-infra/wayne-ui) (1.x).**
> 1.x was Bootstrap 3 and jQuery. It is archived and there is no compatibility
> shim. See [MIGRATING.md](./MIGRATING.md) for the 1.x to 2.0 mapping.

## Pick your path

WayneUI meets you where you already are. All four paths render the same markup
and carry the same accessibility guarantees. Vue is one of the four either way;
which guide you want depends on whether Laravel is already wiring the chrome's
data for you.

| You are building | Start here |
|---|---|
| A static site, no build step | [docs/static-html](./apps/docs/guide/static-html.md) |
| Raw PHP, no framework | [docs/raw-php](./apps/docs/guide/raw-php.md) |
| Laravel with Blade | [docs/laravel-blade](./apps/docs/guide/laravel-blade.md) |
| Laravel with Inertia and Vue | [docs/laravel-inertia](./apps/docs/guide/laravel-inertia.md) |
| Vue with no Laravel | [docs/vue](./apps/docs/guide/vue.md) |

### Static site, with no build step

Two tags, and write HTML:

```html
<link rel="stylesheet" href="https://wayneui.apps.wayne.edu/v2/wayne-ui.css">
<script type="module" src="https://wayneui.apps.wayne.edu/v2/wayne-ui-theme.js"></script>
```

The fonts, the marks and the icon sprite follow from those two, so there is no
folder to copy unless the machine has no network at all.

### Laravel

```bash
composer require waynestate/wayne-ui
php artisan wayne-ui:install
```

```blade
<x-wsu::app-shell title="Course Registration">
    <x-wsu::page-header>Select your courses</x-wsu::page-header>
    {{-- your content --}}
</x-wsu::app-shell>
```

### Vue

```bash
pnpm add @waynestate/wayne-ui-vue @waynestate/wayne-ui-css
```

```vue
<AppShell title="Course Registration">
  <PageHeader>Select your courses</PageHeader>
</AppShell>
```

## daisyUI or shadcn-vue, your choice

WayneUI does not fork its components per framework. WSU components are built on
one layer of semantic `--wsu-*` tokens, and daisyUI and shadcn-vue are thin
adapters over that layer:

```
primitives  ->  --wsu-green-*, --wsu-gold-*, --wsu-neutral-*
semantics   ->  --wsu-brand, --wsu-surface, --wsu-text, --wsu-focus-ring
adapters    ->  daisyUI @plugin theme  |  shadcn-vue :root / .dark
```

So `<button class="btn btn-primary">` and `<Button>` come out in the same WSU
green, from the same source of truth. Use daisyUI if you want Tailwind without a
JavaScript pipeline. Use shadcn-vue if you have Vite. Neither is required. The
WSU chrome works with `wayne-ui.css` alone.

## Two templates

Both are application templates. The only difference is who can reach them, and
therefore how much university identity they carry.

```
internal application   compact bar, shield only, full width
publicly accessible    official wayne.edu header and footer, 1200px
```

## Accessibility

WayneUI targets WCAG 2.2 Level AA as a floor, and AAA wherever the brand
palette allows. Each of these is a command that fails on a violation, and
`pnpm verify` runs all of them:

- Every semantic color pair is contrast-tested in both light and dark themes
- axe-core runs across every component and example at three viewports, in both themes
- Reflow at 320px, 200% and 400% zoom, and text-spacing overrides are all tested
- Lighthouse accessibility must score 100

`.gitlab-ci.yml` runs the same commands with nothing marked `allow_failure`, but
this GitLab instance has no runners and no pipeline here has ever executed. The
checks are real; running them is still something a person does.

See [Accessibility](./apps/docs/accessibility.md) and the shipped VPAT.

The theme follows the operating system. An explicit choice, stored under
`wsu-theme`, wins over it; light is the fallback when there is neither. Someone
who has set their machine to dark has already said what they want, and should
not have to say it again in every application. Dark is a first-class theme, not
a courtesy: every color pairing is contrast tested in both.

## Repository layout

```
packages/tokens     brand tokens, built to CSS, SCSS, JS and JSON
packages/css        semantic layer, adapters, prebuilt bundles, icon sprite and marks
packages/vue        Vue 3 components
packages/registry   shadcn-vue registry
php/src             Laravel provider, Blade components, breadcrumbs, Inertia
php/standalone      framework-free PHP helpers
apps/docs           documentation site, served at /docs on the showcase
apps/site           the showcase site, and the deployment both are built into
apps/a11y           the accessibility suite
apps/examples       one working app per consumer path
```

## Development

```bash
pnpm install && composer install
pnpm verify          # lint, build, unit tests, accessibility gates
pnpm dev             # docs site with live component previews
```

## Brand

Colors and type come from the WSU
[Identity Style Guide](https://universityrelations.wayne.edu/ucam/identity-guidelines),
and the ramps match [`waynestate/base-site`](https://github.com/waynestate/base-site)
stop for stop, so `green-400` means the same thing in both. Primary green is
PMS 561c `#0C5449` and primary gold is PMS 1225c `#FFCC33`. If the guide changes,
change the tokens rather than a component.

## Questions

Open an issue for anything reproducible, so the next person to hit it finds the
answer already there. For adopting WayneUI in an application, a component that
does not exist yet, or an accessibility finding you would rather not file in
public, write to the WayneUI maintainers.

## License

MIT for the code. Wayne State trademarks are not covered by it. See
[LICENSE.md](./LICENSE.md).
