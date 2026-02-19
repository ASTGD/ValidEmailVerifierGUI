# Admin UI Plan

Status: defer UI polish until core logic is stable.

## Styling Strategy

Option 1 (current, no build step)
- Use Filament components and `fi-*` classes.
- Keep admin styles in `resources/css/filament/admin/admin-overrides.css`.
- Avoid custom Tailwind utility classes in custom Blade partials, since the admin panel uses Filament's precompiled CSS.

Option 2 (future, recommended for full Tailwind control)
- Build a Filament theme so Tailwind utilities used in admin views are compiled.
- Steps (when ready):
  1) `./vendor/bin/sail artisan make:filament-theme` (admin panel).
  2) Register in `app/Providers/Filament/AdminPanelProvider.php` using `->viteTheme('resources/css/filament/admin/theme.css')`.
  3) Ensure Tailwind `content` includes admin Blade/Livewire files so utilities are compiled.
  4) Build assets with Sail (`./vendor/bin/sail npm run build` or `./vendor/bin/sail npm run dev`).
- Result: custom Tailwind classes in admin partials render correctly.

## Notes
- Keep the provisioning UI functional first; revisit styling once the theme decision is made.
