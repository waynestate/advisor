# Upgrading from WayneUI 1.x

2.0 is a hard break. 1.x was Bootstrap 3.2, jQuery 1.11 and Font Awesome 4.7,
distributed as a Laravel package with a deploy token pasted into its README. It
is archived. There is no compatibility shim and the two cannot run side by side.

The good news is that most of a 1.x application is a controller and a Blade view
full of Bootstrap classes, and the controller does not change. The work is the
layout, the navigation helper, the breadcrumb calls and a find-and-replace over
the class names.

Budget half a day for a small application and two or three days for a large one.

## Before you start

**1.x does not run on a supported Laravel.** Its `composer.json` claims
`^5.2 || ^6.0 || ^7.0 || ^8.0`, and that claim is false above 5.8. See
[navActive](#navactive) below. If your application is on Laravel 9 or later it is
already carrying a patched fork or a local copy of the helper.

**Your repository has forked copies.** 1.x told developers to commit
`public/vendor/wayne-ui` and `resources/views/vendor/wayne-ui/` after publishing.
Check for both. Anything under `resources/views/vendor/wayne-ui/` silently
overrides the package, so a layout you forgot about in 2018 is still what your
application renders.

**Rotate the deploy token.** 1.x's README asked developers to paste a VCS
repository URL with the credentials in it:

```json
"url": "https://deploy:<token>@git.wayne.edu/cit-web-infra/wayne-ui.git"
```

That token is in the git history of every application that followed the
instructions. Remove the `repositories` block along with the requirement, and
have the token revoked.

## Install

```diff
- "cit-web-infra/wayne-ui": "1.0.*"
+ "waynestate/wayne-ui": "^2.0"
```

Delete the `repositories` block. 2.0 is `waynestate/wayne-ui` on Packagist and
needs no VCS entry.

Delete the manual provider registration from `config/app.php`. 2.0 uses package
discovery.

```bash
composer require waynestate/wayne-ui
php artisan wayne-ui:install
```

Then delete the old artefacts:

```bash
rm -rf public/vendor/wayne-ui.old resources/views/vendor/wayne-ui
git rm -r --cached public/vendor/wayne-ui
```

## Layouts

1.x gave you two `@extends` targets and one section name. 2.0 gives you a
component that owns the document.

| 1.x | 2.0 |
| --- | --- |
| `@extends('wayne-ui::with-sidebar')` | `<x-wsu::app-shell>` with a sidebar configured |
| `@extends('wayne-ui::without-sidebar')` | `<x-wsu::app-shell layout="plain">` |
| `@section('wayne-ui::main-content')` | The component's default slot |
| `@stack('wayne-ui::html-head')` | `<x-slot:head>` |
| `@stack('wayne-ui::body-footer')` | `<x-slot:scripts>` |
| `@include('wayne-ui::layouts.wsu-header')` | `template="public"`, which switches the whole chrome |
| `partials/sidebar-nav.blade.php` | `wayne-ui.sidebar.items` in config, or `<x-slot:sidebar>` |
| `partials/sidebar-footer.blade.php` | `<x-wsu::footer>`, or `wayne-ui.footer.links` |
| `partials/html-head.blade.php` | `<x-slot:head>` |
| `partials/body-footer.blade.php` | `<x-slot:scripts>` |
| `$appUserId`, `$appUserName` view variables | Resolved from the auth user. `wayne-ui.user.attributes` says how |

The whole of a 1.x page:

```blade
@extends('wayne-ui::with-sidebar')

@section('wayne-ui::main-content')
    <p>Your content.</p>
@endsection
```

becomes:

```blade
<x-wsu::app-shell>
    <p>Your content.</p>
</x-wsu::app-shell>
```

Whether there is a sidebar is now decided by whether one is configured or passed,
rather than by which layout you extended.

The shell owns `<html>` through `</html>`. The `<head>` is where the pre-paint
theme script, the viewport meta, the stylesheet order and the title live, and
every one of those was something a 1.x consumer copied into their own published
layout and then had to keep in step by hand. If you genuinely need your own
document, `<x-wsu::app-bar>`, `<x-wsu::sidebar>` and the rest all work alone.

### Two bugs in the 1.x layouts you may have been living with

`layouts/sidebar-wsu-header.blade.php` starts with its own
`@extends('wayne-ui::layouts.master')`, and `with-sidebar.blade.php` `@include`s
it. So with `use-wsu-header => true`, the master layout was re-entered from
inside its own section. If your pages have ever rendered the header twice or the
footer in the wrong place, that is why.

The two layouts also disagreed about order. `without-sidebar` rendered the page
header above the breadcrumbs; `sidebar` rendered breadcrumbs above the page
header. 2.0 renders breadcrumbs first, always.

<!--
  #navactive is written out because the heading below slugs to
  navactive-is-gone, and the link near the top of this file predates it.
  A heading that already slugs to the id being linked needs no anchor: two
  elements with the same id send the fragment to whichever comes first, which
  is the empty anchor rather than the section.
-->
<a id="navactive"></a>

## navActive() is gone

1.x autoloaded a global helper from `helpers.php`:

```php
function navActive($baseName)
{
    return empty(Route::current()) || !starts_with(Route::current()->getName(), $baseName)
        ? ''
        : 'class="active"';
}
```

Two separate problems.

**It fatals.** `starts_with()` was one of the global string helpers Laravel
deprecated in 5.8 and removed from the framework in 6.0, relocating it to the
optional `laravel/helpers` package. 1.x never required that package. On Laravel 6
or later this is not a deprecation notice, it is
`Error: Call to undefined function starts_with()`, thrown while rendering the
navigation, which means on every page of the application. That one line is why
1.x cannot run on a supported framework version.

**It said nothing to a screen reader.** It emitted `class="active"`, so the
current page was conveyed by color alone, which is a WCAG 1.4.1 failure.

The replacement is a class and a Blade directive. Namespaced rather than global,
so two packages cannot collide, and mockable in tests:

```diff
- <li {!! navActive('courses') !!}>
-     <a href="/courses"><i class="fa fa-book"></i> Courses</a>
- </li>
+ <li>
+     <a href="/courses" @navActive('courses.*')>
+         <x-wsu::icon name="book-open" /> Courses
+     </a>
+ </li>
```

The directive emits `aria-current="page"` and emits nothing at all when the item
is not current. The stylesheet selects on that attribute, so the highlighted item
and the announced current page are one fact rather than two that can drift.

`WSU\WayneUi\Support\Nav::isActive()` takes a route name, a wildcard pattern, a
list of either, a path, a URL, a bool or null. 1.x matched on route name prefix
only, so `forum` matched `forum.show` and also `forumadmin.index`. Write the
wildcard you actually mean.

Most applications never call this directly. Put the items in
`config/wayne-ui.php` and the bar and sidebar work it out:

```php
'nav' => [
    ['label' => 'Courses', 'url' => '/courses', 'route' => 'courses.*', 'icon' => 'book-open'],
],
```

The other two globals from `helpers.php` are also gone. `cacheBustingUrl()` is
replaced by Vite's fingerprinting, or by the asset paths in
`wayne-ui.assets.*`. `showUserInfo()` is replaced by `wayne-ui.user.enabled`.

## Breadcrumbs

The call site barely changes. Everything behind it does.

```diff
- use WSU\WayneUi\Breadcrumbs;
+ use WSU\WayneUi\Facades\Breadcrumbs;

  Breadcrumbs::push('Home', null, '/');
  Breadcrumbs::push('Registration', null, route('register.index'));
```

| 1.x | 2.0 | Note |
| --- | --- | --- |
| `Breadcrumbs::push($name, $icon, $url)` | `Breadcrumbs::push($label, $url, $icon)` | **The argument order changed.** URL is second now, because it is supplied far more often than an icon |
| `Breadcrumbs::pushAsHeader($name)` | `Breadcrumbs::pushAsHeading($label, $url, $icon)` | Renamed. "Header" meant three different things in 1.x |
| `Breadcrumbs::pushAsSubHeader()` | `Breadcrumbs::setSubheading()` | It never pushed a crumb, so it is not a push |
| `Breadcrumbs::setHeader()` | `Breadcrumbs::setHeading()` | |
| `Breadcrumbs::setHeader(false)` | `<x-wsu::app-shell :page-header="false">` | Passing `false` to a string setter was never a real API |
| `Breadcrumbs::setSubHeader()` | `Breadcrumbs::setSubheading()` | |
| `Breadcrumbs::setTitle()` | `Breadcrumbs::setTitle()` | Same name. It now works. See below |
| `Breadcrumbs::setSubTitle()` | `Breadcrumbs::setSubtitle()` | |
| `Breadcrumbs::render()` | `<x-wsu::breadcrumbs />` or `@breadcrumbs` | Rendered by the shell already |
| `Breadcrumbs::pageHeader()` | `<x-wsu::page-header />` | Rendered by the shell already |
| `{!! Breadcrumbs::render() !!}` | Nothing | The shell does it |
| No equivalent | `Breadcrumbs::clear()` | |

### The static state

1.x's `Breadcrumbs` was a class of `protected static` properties with no reset
method. Nothing cleared `$crumbs`, `$header`, `$title` or `$subTitle` between
requests.

Under a traditional PHP-FPM request that is invisible, because the process dies.
Under anything long-lived it is not: Octane, Swoole, RoadRunner, a queue worker
rendering a view, or a test suite rendering many views in one process. Request
two's breadcrumb trail was request one's trail plus its own, and a `setHeader()`
from an earlier request stuck permanently.

The render template also destroyed its input:

```blade
@while($crumb = array_shift($crumbs))
```

That worked only because PHP copies an array on the way into a view. It was
correct by accident, not by design, and any refactor that passed a collection or
an object would have broken it.

2.0 binds the service into the container with `$this->app->scoped()`, so the
state is per request, swappable and spyable:

```php
Breadcrumbs::shouldReceive('push')->once();
```

The call site is identical, which is the only thing the statics had going for
them.

### setTitle() never worked

This is worth spelling out, because the 1.x documentation says it does and
several applications have a `setTitle()` call in a controller that has been doing
nothing for years.

```php
// 1.x, src/Breadcrumbs.php
public static function setTitle($title)
{
    static::$title = @(string) $title;
}

public static function getTitle()
{
    return (!empty(static::$title) ? static::$subTitle : config('wayne-ui.app-title'))
        . static::getSubTitle();
}
```

The condition tests `$title`. The branch returns `$subTitle`. `$title` is written
by the setter and never read anywhere except as that boolean test.

So calling `Breadcrumbs::setTitle('Quarterly Report')` and nothing else produced:

1. `!empty(static::$title)` is true, so the ternary returns `static::$subTitle`
2. `$subTitle` is null, so that is `''`
3. `getSubTitle()` with no crumbs is `''`
4. The page rendered `<title></title>`

Setting a title made the title disappear. With a crumb pushed you got
`<title>: Registration</title>`, a leading colon and no application name. With
`setSubTitle('Q3')` as well you got `<title>Q3: Q3</title>`.

It is not a timing or a section-ordering problem. `master.blade.php` called
`getTitle()` at the right moment. The getter returned the wrong property, from
the initial commit in 2017 onwards.

In 2.0, `setTitle()` sets the title and `getTitle()` returns it. If you had a
`setTitle()` call that appeared to do nothing, expect the browser tab to change
after you upgrade.

## Config

Every key was renamed. 1.x used kebab-case, which is not the Laravel convention
and made `config('wayne-ui.app-title')` easy to typo into a silent null.

| 1.x | 2.0 |
| --- | --- |
| `app-home` | `home` |
| `app-title` | `title`, falling back to `app_name`, falling back to `config('app.name')` |
| `favicon` | `assets.favicon` and `assets.favicon_public` |
| `use-page-header` | `page_header` |
| `use-wsu-header` | `template`, which is `'internal'` or `'public'` |
| `show-user-info` | `user.enabled` |
| `subheader-logo` | Removed |
| `subheader-logo-alt` | Removed |

`use-wsu-header` and `show-user-info` had to agree with each other in 1.x and
nothing enforced it. `template` is now the single switch and it selects the
chrome, the stylesheet and the favicon together, so the three cannot be set
inconsistently.

1.x also had no `mergeConfigFrom`, so `config('wayne-ui.app-title')` returned
null until you remembered to publish. The package's own views then read those
nulls and rendered blank titles and broken asset paths, and the failure looked
like a bug in the application. 2.0 merges at register time, so publishing is for
customisation rather than a prerequisite.

## Publish tags

1.x published under `config`, `public`, `layouts` and `partials`. Those are not
names, they are nouns: running `vendor:publish --tag=config` in an application
with five packages published all five, and there was no way to ask for only this
one.

| 1.x | 2.0 |
| --- | --- |
| `--tag=config` | `--tag=wayne-ui-config` |
| `--tag=public` | `--tag=wayne-ui-assets` |
| `--tag=layouts` | `--tag=wayne-ui-views` |
| `--tag=partials` | `--tag=wayne-ui-views` |

The 1.x README's command passed `--tag` three times, and only the last one was
honored on Laravel 5. Most applications ended up with a partial publish and
never found out.

You do not normally need any of these. `php artisan wayne-ui:install` does the
right ones.

## Bootstrap 3 to Tailwind

1.x shipped two full Bootstrap 3.2 builds (`wayne-ui.css` and
`bootstrap-plugins.min.css`), jQuery 1.11.1, Bootstrap's JavaScript,
bootstrap-select 1.12.2, Font Awesome 4.7 and the 200-icon Glyphicons Halflings
font. Roughly 1.5 MB of assets to draw an application bar.

2.0 is `wayne-ui.css` at about 102 kB, a 33 kB dependency-free module and a 13 kB
icon sprite. There is no jQuery.

### Class mapping

| 1.x (Bootstrap 3) | 2.0 |
| --- | --- |
| `.navbar .navbar-default .navbar-fixed-top` | `.wsu-app-bar` |
| `.navbar-brand` | `.wsu-app-bar__brand` |
| `.navbar-logo` | `.wsu-app-bar__mark` |
| `.navbar-toggle` with `data-toggle="collapse"` | `.wsu-app-bar__toggle--nav` with `data-wsu-drawer-toggle` |
| `.sidebar`, `.nav-sidebar` | `.wsu-sidebar`, `.wsu-sidebar__list`, `.wsu-sidebar__link` |
| `.wayne-ui-main`, `.wayne-ui-with-sidebar` | `.wsu-shell`, `.wsu-shell--sidebar`, `.wsu-shell__main` |
| `.wayne-ui-page-header`, `.page-header` | `.wsu-page-header`, `.wsu-page-header__title` |
| `.wayne-ui-footer`, `.footer` | `.wsu-footer` |
| `.breadcrumb` | `.wsu-breadcrumbs`, `.wsu-breadcrumbs__list` |
| `.btn .btn-primary` | `.wsu-btn .wsu-btn--primary` |
| `.btn-default` | `.wsu-btn--outline` |
| `.btn-link` | `.wsu-btn--ghost` |
| `.btn-outline` (a 1.x invention) | `.wsu-btn--outline` |
| `.btn-xs`, `.btn-sm` | `.wsu-btn--sm` |
| `.btn-lg` | `.wsu-btn--lg` |
| `.label .label-success` | `.wsu-badge .wsu-badge--success` |
| `.badge` (a count bubble) | `.wsu-badge` with text |
| `.alert .alert-warning` | `.wsu-alert .wsu-alert--warning`, plus an icon and a title |
| `.alert-dismissible` | `.wsu-toast` inside `.wsu-toasts` |
| `.panel .panel-default`, `.well`, `.thumbnail` | `.wsu-card` |
| `.panel-heading .panel-title` | `.wsu-card__title` |
| `.panel-footer` | `.wsu-card__footer` |
| `.table .table-striped` | `.wsu-table .wsu-table--zebra` inside `.wsu-table-wrap` |
| `.table-responsive` | `.wsu-table-wrap`, which is focusable and named |
| `.form-group` | `.wsu-field` |
| `.form-control` | `.wsu-input`, `.wsu-select`, `.wsu-textarea` |
| `.control-label` | `.wsu-field__label` |
| `.help-block` | `.wsu-field__hint` |
| `.has-error` | `aria-invalid="true"` on the control |
| `.has-error .help-block` | `.wsu-field__error` |
| `.checkbox`, `.radio` | `.wsu-checkbox`, `.wsu-radio` |
| `.pagination` | `.wsu-pagination`, `.wsu-pagination__list` |
| `.nav-tabs .tab-content` | `.wsu-tabs__list`, `.wsu-tabs__panel`, with real tab roles |
| `.modal .modal-dialog` | `<dialog class="wsu-dialog">` |
| `.sr-only` | `.wsu-sr-only` |
| `.sr-only-focusable` | `.wsu-sr-only-focusable` |
| `.non-production` body class | `.wsu-env-ribbon`, which also says which environment |
| `.container`, `.container-fluid` | `.wsu-container`, or the shell's own width |
| `.row`, `.col-sm-*` | CSS grid or flexbox. There is no 12-column grid |
| `.pull-left`, `.pull-right`, `.center-block` | Tailwind utilities, or your own CSS |
| `.hidden-xs`, `.visible-xs` | Container queries or a media query |
| `.text-danger`, `.bg-success` | Tailwind utilities over the semantic tokens |

There is no grid. Bootstrap's 12 columns were a workaround for a browser that did
not have CSS grid, and `col-sm-9 col-sm-offset-3 col-md-10 col-md-offset-2`,
which is what 1.x's own layout used, is not clearer than
`grid-template-columns`.

`data-toggle`, `data-target`, `data-dismiss` and `data-ride` are all Bootstrap
JavaScript hooks and none of them do anything now. The 2.0 equivalents are
`data-wsu-drawer-toggle`, `data-wsu-drawer-close` and
`data-wsu-drawer-backdrop`, listed in
[the static HTML guide](./apps/docs/guide/static-html.md).

### bootstrap-select

`.selectpicker` and bootstrap-select 1.12.2 are gone. Use a native `<select>`.
The platform control gets keyboard behavior, type-ahead, the mobile picker and
forced-colors support for free, and none of those survive a rebuild in divs.

## Font Awesome to Lucide

1.x shipped Font Awesome 4.7 as six font files, roughly 1.2 MB, plus the
Glyphicons Halflings font it never used. 2.0 ships a 13 kB SVG sprite subset to
the icons the components reference.

```diff
- <i class="fa fa-user fa-lg"></i>
+ <x-wsu::icon name="user" />
```

Icons inherit `currentColor`, so they follow the theme without a second
stylesheet. A decorative icon is `aria-hidden`. An icon that carries meaning on
its own takes a label.

| Font Awesome 4 | Lucide name |
| --- | --- |
| `fa-bars` | `menu` |
| `fa-times`, `fa-close` | `x` |
| `fa-search` | `search` |
| `fa-user` | `user` |
| `fa-sign-out` | `log-out` |
| `fa-cog`, `fa-gear` | `settings` |
| `fa-home` | `home` |
| `fa-angle-right`, `fa-chevron-right` | `chevron-right` |
| `fa-angle-left` | `chevron-left` |
| `fa-angle-down`, `fa-caret-down` | `chevron-down` |
| `fa-sort` | `arrow-up-down` |
| `fa-check` | `check` |
| `fa-check-circle` | `circle-check` |
| `fa-exclamation-circle` | `circle-alert` |
| `fa-exclamation-triangle`, `fa-warning` | `triangle-alert` |
| `fa-info-circle` | `info` |
| `fa-plus` | `plus` |
| `fa-pencil`, `fa-edit` | `pencil` |
| `fa-trash`, `fa-trash-o` | `trash-2` |
| `fa-download` | `download` |
| `fa-file-text-o` | `file-text` |
| `fa-users` | `users` |
| `fa-calendar` | `calendar` |
| `fa-bell` | `bell` |
| `fa-external-link` | `external-link` |
| `fa-spinner fa-spin` | `loader-circle` |
| `fa-book` | `book-open` |
| `fa-graduation-cap` | `graduation-cap` |
| `fa-building` | `building-2` |
| `fa-list-alt` | `clipboard-list` |
| `fa-heart` | Not in the subset |

The sprite is a subset on purpose. If you need an icon that is not in it, add it
to the `ICONS` list in `packages/css/build.mjs` and rebuild. Lucide has about
1,500 icons and shipping all of them would be the Font Awesome mistake again.

Glyphicons were shipped by 1.x and never used by any of its own markup. If your
application uses `.glyphicon-*`, map those to Lucide too.

## The color

1.x's primary green was `#006666`.

That is not a Wayne State color. The WSU primary is PMS 561c, `#0C5449`.
`#006666` is a teal that appears nowhere in the Identity Style Guide, and it
shipped on every button, every link and every footer rule in every application
that used 1.x since 2017.

It also appeared in two spellings. `wayne-ui.css` had `#006666` in 28 places and
`wayne-ui-custom.css` had the three-digit form `#066` in five more, so a
find-and-replace on the long form missed a third of them. Search for both.

| 1.x color | Where | 2.0 |
| --- | --- | --- |
| `#006666` | Buttons, links, footer rule, pagination | `--wsu-brand`, which is `#0c5449` |
| `#066` | `wayne-ui-custom.css`, the same color spelled short | `--wsu-brand` |
| `#007a7a` | Button hover | `--wsu-brand-hover`, `#0b4a40` |
| `#005757` | Button hover border | `--wsu-brand-active`, `#094038` |
| `#0c5449` | Footer background. The correct green, used in the wrong place | `--wsu-surface-inverse` where you want a dark block |
| `#cedddb` | Footer text | `--wsu-green-50`, or `--wsu-text-on-inverse` |
| `#cfdedc` | Active sidebar item | `--wsu-brand-subtle` |
| `#8B2145`, `#671931` | The non-production maroon | `--wsu-nonproduction`, with `--wsu-on-nonproduction` for text on it |
| `#093f39` | The pasted masthead gradient | Not yours. The official component owns it |

If your application defined its own colors in `wayne-ui-custom.css`, this is the
moment to move them onto semantic tokens rather than carrying the hexes across.
[Theming](./apps/docs/theming.md) covers extending the token layer.

Every color pairing in 2.0 is contrast tested in both themes by the token
suite, with the measured figures published on
[Tokens](./apps/docs/tokens.md).
`#006666` on white measures 6.79:1, which passes AA and misses the 7:1 AAA bar
2.0 holds body text to. `#0c5449` measures 8.82:1.

## The pasted masthead

`layouts/wsu-header.blade.php` was a verbatim copy of the wayne.edu header taken
in 2017, complete with an Illustrator 21.1 generator comment inside a base64
search icon and a `progid:dximagetransform` filter for Internet Explorer 9. The
only edits it ever received were Blade syntax fixes for Laravel 5.8. It was never
re-synced with wayne.edu, and by 2026 the official component had renamed its
wrapper from `.wsuwrap` to `.wsuheaderwrap`.

Delete it. 2.0 consumes `@waynestate/wsuheader` and `@waynestate/wsufooter` as
npm dependencies and reads their HTML fragments at render time. Set
`'template' => 'public'` and the masthead and footer arrive, and they update when
you update the package.

## Dark mode and the theme toggle

1.x had neither. 2.0 has both, and applications get them by upgrading.

There is one thing to know: WayneUI reads `prefers-color-scheme`, so anyone on
your existing user base who runs their machine dark will get a dark page on
their first visit after the upgrade, without touching anything. A stored choice
from the toggle overrides it. [Theming](./apps/docs/theming.md) has the full
reasoning.

If your application has its own colors in a stylesheet, they will be wrong in
dark until you move them onto semantic tokens. Check every page in both themes
before you ship the upgrade.

## The order to do it in

1. Rotate the deploy token and remove the `repositories` block.
2. Install 2.0 and run `php artisan wayne-ui:install`.
3. Delete `resources/views/vendor/wayne-ui/` and the old `public/vendor` copy.
4. Move `config/wayne-ui.php` across using the table above, and put your
   navigation into `nav` and `sidebar.items`.
5. Replace every `@extends`/`@section` pair with `<x-wsu::app-shell>`.
6. Replace every `navActive()` call with `@navActive()`, and check your wildcards.
7. Fix the `Breadcrumbs::push()` argument order. This one is silent: the old
   order puts your URL where the icon goes, and you get a crumb with no link and
   a broken icon reference rather than an error.
8. Find and replace the class names. Start with the layout classes, then buttons,
   then forms.
9. Replace the Font Awesome markup.
10. Search for `#006666` and `#066` in your own stylesheets.
11. Read every page in both themes, at 320px, and with the keyboard only.

## When you get stuck

- [Laravel and Blade](./apps/docs/guide/laravel-blade.md) is the 2.0 guide for
  the same stack
- [Components](./apps/docs/components/index.md) has every component with its
  markup
- [Accessibility](./apps/docs/accessibility.md) explains what the gates check, if
  one of them stops your upgrade
