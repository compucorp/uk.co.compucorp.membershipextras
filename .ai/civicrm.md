<!-- .ai/civicrm.md v1.5 | Last updated: 2026-01-28 -->

# CiviCRM Core Reference

This file provides CiviCRM-specific patterns and conventions for AI tools and developers.

> **Extension structure & testing:** See [extension.md](extension.md) for extension directory layout, schema changes, and testing patterns.

---

## 1. Namespaces

| Namespace | Directory | Purpose |
|-----------|-----------|---------|
| `CRM_*` | `CRM/` | Traditional CiviCRM (DAO, BAO, Pages, Forms) |
| `Civi\ExtensionName\*` | `Civi/` | Modern services, hooks, factories |

---

## 2. CiviCRM API Usage

### Prefer API4 Over API3

API4 is the modern CiviCRM API with better type safety and cleaner syntax. Use it for all new code.

```php
// API4 with permission bypass for internal/IPN operations
$contribution = \Civi\Api4\Contribution::get(FALSE)
  ->addSelect('id', 'contribution_page_id', 'contribution_status_id:name')
  ->addWhere('id', '=', $contributionId)
  ->execute()
  ->first();

// API3 (legacy - avoid in new code)
$contribution = civicrm_api3('Contribution', 'getsingle', [
  'id' => $contributionId,
  'return' => ['id', 'contribution_page_id'],
]);
```

### Key API4 Differences
- `FALSE` as first parameter bypasses permission checks
- API3 requires `'check_permissions' => 0` (easy to forget)
- API4 uses `snake_case` field names consistently
- API4 status fields use pseudoconstant syntax: `contribution_status_id:name`

### When to Bypass Permissions (API4 `FALSE`)
- IPN/webhook handlers (anonymous context)
- Return URLs from payment processors
- Internal service operations
- Background processing jobs

### When API3 Is Acceptable
- Entity not yet available in API4 (rare)
- Maintaining consistency with existing code in same method
- `Payment.create` API (commonly used, works well)

---

## 3. API4 Result Handling & PHPStan

API4's `->first()` and `->single()` return `mixed`. Use these patterns for PHPStan compliance:

### In Services - `is_array()` Guard
```php
$result = \Civi\Api4\PaymentToken::create(FALSE)
  ->setValues($tokenParams)
  ->execute()
  ->first();

if (!is_array($result) || empty($result['id'])) {
  throw new \CRM_Core_Exception('Failed to create payment token');
}
return $result;
```

### In Tests - `assertNotNull()` Before Accessing
```php
$updated = \Civi\Api4\ContributionRecur::get(FALSE)
  ->addSelect('payment_token_id')
  ->addWhere('id', '=', $recurId)
  ->execute()
  ->first();

$this->assertNotNull($updated);
$this->assertEquals($tokenId, $updated['payment_token_id']);
```

### Avoid These Patterns
```php
// BAD: Inline @var annotations - not the project pattern
/** @var array{id: int}|null $result */
$result = $api->execute()->first();

// BAD: ->single() throws if not exactly 1 result
// Use ->first() with is_array() check instead
$result = $api->execute()->single();
```

---

## 4. PHPStan Stub Files for Dynamic Classes

CiviCRM Api4 entity classes are **dynamically generated at runtime** and have no physical PHP files. Use stub files in `stubs/`:

```php
// stubs/CiviApi4.stub.php
namespace Civi\Api4;

use Civi\Api4\Generic\DAOGetAction;
use Civi\Api4\Generic\DAOCreateAction;
use Civi\Api4\Generic\DAOUpdateAction;
use Civi\Api4\Generic\DAODeleteAction;

/**
 * @method static DAOGetAction get(bool $checkPermissions = TRUE)
 * @method static DAOCreateAction create(bool $checkPermissions = TRUE)
 * @method static DAOUpdateAction update(bool $checkPermissions = TRUE)
 * @method static DAODeleteAction delete(bool $checkPermissions = TRUE)
 */
class Activity {

}
```

**Rules:**
- Do NOT use `extends AbstractEntity` in stubs (causes CI errors)
- Add new Api4 entities to the stub file when first used
- Referenced in both `phpstan.neon` and CI-generated `phpstan-ci.neon`

---

## 5. Hooks and Integration Points

Common CiviCRM hooks used in extensions:

| Hook | Purpose |
|------|---------|
| `civicrm_buildForm` | Inject scripts/templates into forms |
| `civicrm_validateForm` | Validate form submissions |
| `civicrm_permission` | Register custom permissions |
| `civicrm_permission_check` | Grant permissions dynamically |
| `civicrm_container` | Register services with DI container |
| `civicrm_postCommit` | Handle post-transaction tasks |
| `civicrm_check` | System status checks |

---

## 6. Service Registration

Services are registered via Symfony DI container in hook implementations:

```php
// In Civi/ExtensionName/Hook/Container/ServiceContainer.php
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class ServiceContainer {
  public function register(ContainerBuilder $container): void {
    $definition = new Definition(MyService::class);
    $definition->addArgument(new Reference('other.service'));
    $container->setDefinition('my.service', $definition);
  }
}
```

Usage:
```php
$service = \Civi::service('my.service');
```

---

## 7. Common CiviCRM Commands

```bash
# Enable extension
cv en extension_name

# Disable extension
cv dis extension_name

# Uninstall extension
cv ext:uninstall extension_name

# Upgrade extension
cv api Extension.upgrade

# Clear cache
cv flush

# Run cv commands via Docker
./scripts/run.sh cv api Contact.get
./scripts/run.sh shell    # Shell into container
```

---

## 8. PHPStan vs Linter Compatibility

The Drupal linter and PHPStan have conflicting requirements for array type annotations.

**Problem:** `@var array<string, string|int>` fails linter, but `@var array` fails PHPStan.

**Solution:** Use `@phpstan-var` / `@phpstan-param` which PHPStan reads but linter ignores:

```php
/**
 * @param array $params
 * @phpstan-param array<string, mixed> $params
 */
public function process(array $params): void {
  // ...
}

/**
 * {@inheritdoc}
 *
 * @phpstan-var array<string, string|int>
 */
protected static $defaultParams = [
  'financial_type_id' => 'Donation',
  'frequency_interval' => 1,
];
```

For inherited properties, use `{@inheritdoc}` plus `@phpstan-var` for the specific type.
