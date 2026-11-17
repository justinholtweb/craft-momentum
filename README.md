# Momentum

Render Craft entries as Inertia pages (Vue, React or Svelte) straight from Craft routing — no headless API, no second router.

> **Status:** scaffold — nothing user-facing yet. See [PLAN.md](PLAN.md).

## Requirements

- Craft CMS 6.0.0-beta.1 or later (native Craft 6 plugin, no Yii 2 adapter)
- PHP 8.5
- Laravel integration: inertiajs/inertia-laravel. Already required by Craft 6 core (the CP is an Inertia app), so no new dependency.

## Editions and pricing

Lite / Pro — Lite free / Pro $79 (provisional).

## Development

```bash
composer install
composer test        # Pest
composer phpstan     # Larastan level 6
composer check-cs    # Pint
```

Tested in the Craft 6 harness at `~/Sites/plugin-testing-craft6` (`bin/plugin-wire craft-momentum --install`).
