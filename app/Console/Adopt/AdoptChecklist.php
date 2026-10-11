<?php

declare(strict_types=1);

namespace App\Console\Adopt;

/**
 * DOCUMENT half of `modules:adopt` — the operator-owned steps the command may
 * never run itself (locks, reviewed baselines, nested pushes, judgment calls).
 * Text is the 12-step checklist of
 * docs/researches/module-extensibility-d1-validate-mode-pack.md §3, ordered
 * after the adoption-dryrun §2.2 runbook.
 */
final class AdoptChecklist
{
    /**
     * @return list<string>
     */
    public static function steps(AdoptModule $module): array
    {
        $dir = $module->dir;

        return [
            '[publish] Create/publish the nested repo github.com/bagart/'.$dir.' (branch main), then verify reachability: gh repo view bagart/'.$dir.' (an unreachable VCS source blocks lock-fresh:prod)',
            '[K21] Regenerate BOTH locks (user-owned; never invoke from this command): bash cmd/deps/update --mode=both',
            'Verify the gates (no --mode flag on check): bash cmd/deps/check — expected controls: deps-parity, module-layout:'.$dir.', provider-wiring, deps-mode-purity, lock-fresh:dev, lock-fresh:prod',
            '[K20/baselines] After ANY composer.json scripts change — review first, then regenerate the reviewed baselines: php tools/baseline/composer-policy.php --check=scripts --update && php tools/baseline/manifest.php --generate',
            '[K26/K27] Nested push: prepare a per-repo inventory, then the USER batch-pushes misc/BAGArt/'.$dir.' (nested repos have no git hooks)',
            '[K18] Prod-lock refresh cadence for nightly visibility: refresh composer.prod.lock iff the change touched package files',
            '[judgment] Mutation floor, only if the mutation job targets the package: add libraries["bagart/'.$dir.'"] to tools/baseline/mutation-baseline.json',
            '[judgment] If the module ships a daemon: add compose services (copy proxy-worker) and append '.$dir.' to docker/common/php-fpm/Dockerfile REPOS — no control checks this string',
            '[judgment] If CI/server must override enabled: pin TG_MODULE_ENABLED_'.$module->id.' in misc/BAGArt/telegram-platform-workflows/.github/workflows/nightly.yml',
            '[docs] Add the three host rows using the existing patterns: AGENTS.md:236 structure line ("- misc/BAGArt/'.$dir.'" — <name> module (…)), docs/INDEX.md:138 module table row ("| '.$dir.' | tasks | sdd | adr | arch |"), dev-ai.md:139 module coverage row ("| <Name> | '.$dir.'/ | … |"); if the module owns a domain skill edit docs/skills/<name>/ then run bash cmd/sync-skills (verify: bash cmd/sync-skills --check)',
            '[verify] cheapest first: cd misc/BAGArt/'.$dir.' && composer test · bash cmd/deps/check · php artisan tg:modules:validate · php artisan tg:modules:list · vendor/bin/pint --dirty --format agent · npm run build (frontend only) · bash cmd/dev/check',
            '[runtime] If the config entry declares routes: activate per bot, then php artisan tg:modules:routes:sync {botId} — drift check: php artisan tg:modules:routes:check',
        ];
    }
}
