# Resetting state between requests

In a persistent worker (FrankenPHP, Swoole, ReactPHP, Fiber) a single PHP
process handles many requests in sequence without restarting. Singletons
registered in the DI container live for the lifetime of the worker, so any state
left behind by request *N* bleeds into request *N+1* unless it is explicitly
reset. This module's `Resetter` runs between every request to undo that state.

There are two reset paths. Choosing the right one matters — on PHP 8.4+ the wrong
one silently fails.

## Path 1 — `ResetAfterRequestInterface` (preferred for classes you own)

When a class implements
`Magento\Framework\ObjectManager\ResetAfterRequestInterface`, the `Resetter`
calls its `_resetState()` as a **direct method call** after each request. This is
the correct, reliable path: the method runs in the object's own scope, so
whatever it writes lands in the live property slot.

```php
use Magento\Framework\ObjectManager\ResetAfterRequestInterface;

class SessionRegistry implements ResetAfterRequestInterface
{
    private WeakMap $sessions;

    public function _resetState(): void
    {
        $this->sessions = new WeakMap();
    }
}
```

Use this for every class you own that carries per-request state.

## Path 2 — `etc/reset.json` (third-party classes only)

For classes you cannot subclass, list the properties to reset in
`etc/reset.json`. The `Resetter` writes each value back via
`ReflectionProperty::setValue()`:

```json
{
  "\\Magento\\Framework\\View\\Layout\\ScheduledStructure\\Helper": {
    "counter": 0
  }
}
```

This is a reflection fallback. Prefer Path 1 whenever the class is yours.

## The PHP 8.4 lazy-ghost failure

On PHP 8.4+ the DI container creates Interceptors as **lazy ghosts**
(`ReflectionClass::newLazyGhost()`). The `Resetter` holds a `ReflectionProperty`
sourced from the **parent class**, not the Interceptor. On an uninitialized
ghost, `setValue()` from the parent's `ReflectionProperty` writes into the parent
scope — but running code reads the Interceptor scope, which is still
uninitialized. Reading it triggers the lazy initialiser, which **overwrites the
value the Resetter just wrote**.

**Symptom:** a property appears to reset correctly in isolation but keeps its
stale value in production worker mode. If it worked under PHP 8.3 and broke on
8.4, this is almost certainly the cause.

**Fix:** do not rely on `reset.json` for such a class. Subclass it, implement
`ResetAfterRequestInterface`, and reset the state in `_resetState()`. The
method-call path initialises the ghost before writing, so the reset lands in the
live property slot.

## Rules of thumb

- **Subclass what you own.** Classes in this module that carry per-request state
  implement `ResetAfterRequestInterface` (e.g. `SessionRegistry`,
  `CustomerVisitor`, `WebapiRequest`).
- **`reset.json` is for third-party classes only** — ones you cannot cleanly
  subclass.
- **Never list the same class in both.** The two mechanisms compete; pick one.
- **No DI wiring needed for Path 1.** The `Resetter` discovers every tracked
  `ResetAfterRequestInterface` singleton automatically — adding a
  `<preference>`/`reset.json` entry for it is redundant.
