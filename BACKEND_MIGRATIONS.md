# Backend migrations

Base: upstream Axolotl `stable`, commit `7831952d6a970d1700ca82742ab2ac4dbe61ff80`.
This branch adds two features without importing the former FallenTech engine's other patches.

## Runtime crash reporting and recovery

Reimplements FallenTech commit `1f01babab6df70978b45dfe907c6101b31addbd3`.

Exceptions escaping a tick or the tick sleeper produce a crash dump and allow the next tick to run.
The server waits one tick before retrying, preventing a tight exception loop. Consecutive identical
exceptions are reported at most once per minute. Dump filenames include a random suffix so separate
errors within one second do not overwrite each other.

`pocketmine\event\server\CrashDumpEvent::getPath()` exposes the written dump to plugins. A reporting
listener's exception is logged without aborting recovery. Crash dumping restores the previous PHP
error-reporting and memory-limit settings. A startup failure still shuts down because there is no
completed server startup to resume.

To rebuild this feature, separate dump creation from process termination in `Server::crashDump()`;
dispatch the event only after writing the file; restore temporary INI settings in `finally`; add the
fingerprint/time guard in `exceptionHandler()`; and catch exceptions around both `tick()` and the
sleeper in `tickProcessor()`. Preserve explicit shutdown in the constructor's startup catch.

When profiling is enabled, recovery resets the timings session to clear timers
left open by the interrupted tick. Profiling stays enabled, but samples collected
before that recovery are discarded.

This resumes execution; it does not roll back partially completed ticks, repair broken plugin state,
or recover from a killed process or unrecoverable PHP fatal error. Alternating errors are not covered
by the consecutive-error suppression.

## Absorption and protected damage

Adapted from [Quark commit 972f7a7](https://github.com/Bedrock-Phanatics/Quark/commit/972f7a7483324b4c482fb4f98fdc5fe68da82ad3).

Normal attacks consume absorption before changing health, including lethal attacks. Absorption is
recalculated after damage listeners run, so changes to damage or absorption do not use a stale
pre-event reduction. Cancelled attacks change neither value. Suicide damage continues to bypass
absorption. `Living::damageWithAbsorption(float)` also allows direct, already-resolved damage;
it bypasses events, defensive reductions and attack cooldowns.

`EntityDamageEvent` provides `addProtectedDamage()`, `setProtectedDamage()`, `getProtectedDamage()`,
`clearProtectedDamage()` and `getFinalDamageWithoutProtected()`. Protected damage bypasses the
engine's armour, resistance and Protection-enchantment reductions, but still consumes absorption.
The separate `MODIFIER_PROTECTED_DAMAGE` modifier adjusts that protected amount. Clearing the
protected field does not clear this independently supplied modifier. Negative protected totals
are clamped to zero. Ordinary over-reduction cannot subtract from the protected amount.

To rebuild:

1. Route `Entity::attack()` through protected `applyDamage(EntityDamageEvent $source)` after event
   cancellation is checked. Keep the event argument: Skyblock and Factions override this hook for
   duel statistics. Quark's float argument would break those subclasses.
2. Override the hook in `Living`. Clear the old absorption modifier, calculate the current total,
   record the new absorption modifier, then use `damageWithAbsorption()` to consume absorption
   before health. Keep suicide on the base implementation. Remove absorption consumption from
   `applyPostDamageEffects()` to avoid consuming it twice or skipping it on death.
3. Keep the pre-event absorption estimate for plugin compatibility, after helmet reduction.
4. Preserve all existing modifier IDs, including absorption at 5. Allocate protected damage at 12,
   rather than renumbering the existing IDs as Quark did.
5. Sum ordinary modifiers without absorption or the protected modifier, clamp the ordinary total,
   add the clamped protected field plus its modifier once, then apply absorption to the combined
   total. Use `getFinalDamageWithoutProtected()` when calculating engine defensive reductions.
   Plugin-defined reductions must also use this method if they should exclude protected damage.

## Validation

From the workspace root in PowerShell:

```powershell
bin/nethernet-php84/bin/php/php.exe tests/regression/crash-recovery.php
bin/nethernet-php84/bin/php/php.exe vendor/phpunit/phpunit/phpunit --bootstrap vendor/autoload.php --fail-on-warning tests/phpunit
bin/nethernet-php84/bin/php/php.exe plugins/Factions-Core/tests/combat-damage.php
```

The crash regression executes the production handler/loop with isolated dump and reporting I/O.
`tests/phpunit/entity/BackendDamageTest.php` exercises production attack and modifier methods with
mocked entity attributes and world-facing effects. These checks do not verify Bedrock client rendering.
