# 41c - Test Specification: Feature Flag Resolution

> **Component:** `src/Services/FeatureFlagService.php`  
> **Test File:** `tests/php/Unit/Services/FeatureFlagServiceTest.php`  
> **Priority:** High (Affects all feature availability)

---

## 📋 Test Coverage Summary

| Method | Test Cases | Edge Cases |
|--------|------------|------------|
| `isEnabled()` | 10 | 5 |
| `resolveFlag()` | 8 | 4 |
| `passesRollout()` | 6 | 3 |
| `isOverrideValid()` | 4 | 2 |
| `clearCache()` | 3 | 1 |
| `getFlagsByCategory()` | 2 | 1 |

---

## 🧪 Resolution Hierarchy Tests

### Test Case 1: User override takes precedence over all

```php
/**
 * @test
 * @covers FeatureFlagService::isEnabled
 */
public function isEnabled_userOverrideTakesPrecedence(): void
{
    // Arrange
    $flagKey = 'dark_mode';
    $userId = 42;
    
    // Base flag is disabled
    $this->repository->method('findByKey')
        ->willReturn($this->createFlag($flagKey, false, true, 100));
    
    // User override enables it
    $this->repository->method('findOverride')
        ->with($flagKey, 'user', $userId)
        ->willReturn($this->createOverride($flagKey, true, null));
    
    // Act
    $result = $this->service->isEnabled($flagKey, $userId);
    
    // Assert
    $this->assertTrue($result);
}
```

### Test Case 2: Exam override takes precedence over role

```php
/**
 * @test
 * @covers FeatureFlagService::isEnabled
 */
public function isEnabled_examOverrideTakesPrecedenceOverRole(): void
{
    // Arrange
    $flagKey = 'webhooks';
    $examId = 10;
    $role = 'ADMIN';
    
    // No user override
    $this->repository->method('findOverride')
        ->willReturnCallback(function($key, $type, $id) use ($flagKey, $examId) {
            if ($type === 'exam' && $id === $examId) {
                return $this->createOverride($flagKey, true, null);
            }
            return null;
        });
    
    // Role override would disable
    $this->repository->method('findOverrideByRole')
        ->willReturn($this->createOverride($flagKey, false, null));
    
    // Act
    $result = $this->service->isEnabled($flagKey, null, $examId, $role);
    
    // Assert
    $this->assertTrue($result); // Exam override wins
}
```

### Test Case 3: Role override applies when no user/exam override

```php
/**
 * @test
 * @covers FeatureFlagService::isEnabled
 */
public function isEnabled_roleOverrideApplies_whenNoHigherOverride(): void
{
    // Arrange
    $flagKey = 'certificate_generation';
    $role = 'EXAM_EDITOR';
    
    // No user or exam overrides
    $this->repository->method('findOverride')->willReturn(null);
    
    // Role override enables
    $this->repository->method('findOverrideByRole')
        ->with($flagKey, $role)
        ->willReturn($this->createOverride($flagKey, true, null));
    
    // Act
    $result = $this->service->isEnabled($flagKey, null, null, $role);
    
    // Assert
    $this->assertTrue($result);
}
```

### Test Case 4: Base flag used when no overrides exist

```php
/**
 * @test
 * @covers FeatureFlagService::isEnabled
 */
public function isEnabled_usesBaseFlag_whenNoOverrides(): void
{
    // Arrange
    $flagKey = 'analytics_tracking';
    
    // No overrides
    $this->repository->method('findOverride')->willReturn(null);
    $this->repository->method('findOverrideByRole')->willReturn(null);
    
    // Base flag is enabled
    $this->repository->method('findByKey')
        ->with($flagKey)
        ->willReturn($this->createFlag($flagKey, true, true, 100));
    
    // Act
    $result = $this->service->isEnabled($flagKey);
    
    // Assert
    $this->assertTrue($result);
}
```

### Test Case 5: Unknown flag returns false (fail closed)

```php
/**
 * @test
 * @covers FeatureFlagService::isEnabled
 */
public function isEnabled_returnsFalse_forUnknownFlag(): void
{
    // Arrange
    $this->repository->method('findByKey')->willReturn(null);
    
    // Expect warning log
    $this->mockLogger->expects($this->once())
        ->method('warning')
        ->with($this->stringContains('Unknown feature flag'));
    
    // Act
    $result = $this->service->isEnabled('nonexistent_flag');
    
    // Assert
    $this->assertFalse($result);
}
```

---

## 🧪 Override Expiration Tests

### Test Case 6: Expired override is ignored

```php
/**
 * @test
 * @covers FeatureFlagService::isOverrideValid
 */
public function isEnabled_ignoresExpiredOverride(): void
{
    // Arrange
    $flagKey = 'debug_mode';
    $userId = 99;
    
    // Expired user override
    $expiredOverride = $this->createOverride($flagKey, true, 
        date('Y-m-d H:i:s', strtotime('-1 day'))
    );
    
    $this->repository->method('findOverride')
        ->with($flagKey, 'user', $userId)
        ->willReturn($expiredOverride);
    
    // Base flag is disabled
    $this->repository->method('findByKey')
        ->willReturn($this->createFlag($flagKey, false, true, 100));
    
    // Act
    $result = $this->service->isEnabled($flagKey, $userId);
    
    // Assert - falls through to base flag (disabled)
    $this->assertFalse($result);
}
```

### Test Case 7: Non-expired override is used

```php
/**
 * @test
 * @covers FeatureFlagService::isOverrideValid
 */
public function isEnabled_usesNonExpiredOverride(): void
{
    // Arrange
    $flagKey = 'page_caching';
    $userId = 55;
    
    // Future expiration
    $validOverride = $this->createOverride($flagKey, true,
        date('Y-m-d H:i:s', strtotime('+7 days'))
    );
    
    $this->repository->method('findOverride')
        ->willReturn($validOverride);
    
    // Act
    $result = $this->service->isEnabled($flagKey, $userId);
    
    // Assert
    $this->assertTrue($result);
}
```

### Test Case 8: Null expiration means never expires

```php
/**
 * @test
 * @covers FeatureFlagService::isOverrideValid
 */
public function isEnabled_nullExpirationNeverExpires(): void
{
    // Arrange
    $override = $this->createOverride('feature', true, null);
    
    // Act
    $result = $this->invokePrivateMethod(
        $this->service, 
        'isOverrideValid', 
        [$override]
    );
    
    // Assert
    $this->assertTrue($result);
}
```

---

## 🧪 Rollout Percentage Tests

### Test Case 9: 100% rollout always passes

```php
/**
 * @test
 * @covers FeatureFlagService::passesRollout
 */
public function passesRollout_alwaysPasses_at100Percent(): void
{
    // Arrange
    $flag = $this->createFlag('feature', true, true, 100);
    
    // Test with multiple user IDs
    $results = [];
    for ($userId = 1; $userId <= 100; $userId++) {
        $results[] = $this->invokePrivateMethod(
            $this->service,
            'passesRollout',
            [$flag, $userId]
        );
    }
    
    // Assert - all should pass
    $this->assertEquals(100, array_sum($results));
}
```

### Test Case 10: 0% rollout always fails

```php
/**
 * @test
 * @covers FeatureFlagService::passesRollout
 */
public function passesRollout_alwaysFails_at0Percent(): void
{
    // Arrange
    $flag = $this->createFlag('feature', true, true, 0);
    
    // Test with multiple user IDs
    for ($userId = 1; $userId <= 50; $userId++) {
        $result = $this->invokePrivateMethod(
            $this->service,
            'passesRollout',
            [$flag, $userId]
        );
        
        // Assert
        $this->assertFalse($result);
    }
}
```

### Test Case 11: Rollout is deterministic per user

```php
/**
 * @test
 * @covers FeatureFlagService::passesRollout
 */
public function passesRollout_isDeterministic_forSameUser(): void
{
    // Arrange
    $flag = $this->createFlag('feature', true, true, 50);
    $userId = 42;
    
    // Act - call multiple times
    $results = [];
    for ($i = 0; $i < 10; $i++) {
        $results[] = $this->invokePrivateMethod(
            $this->service,
            'passesRollout',
            [$flag, $userId]
        );
    }
    
    // Assert - all results should be identical
    $this->assertCount(1, array_unique($results));
}
```

### Test Case 12: Different users get different rollout buckets

```php
/**
 * @test
 * @covers FeatureFlagService::passesRollout
 */
public function passesRollout_distributesDifferentUsers(): void
{
    // Arrange
    $flag = $this->createFlag('feature', true, true, 50);
    
    // Test 1000 users
    $results = [];
    for ($userId = 1; $userId <= 1000; $userId++) {
        $results[] = $this->invokePrivateMethod(
            $this->service,
            'passesRollout',
            [$flag, $userId]
        ) ? 1 : 0;
    }
    
    // Assert - should be roughly 50% (within 10% tolerance)
    $enabledCount = array_sum($results);
    $this->assertGreaterThan(400, $enabledCount);
    $this->assertLessThan(600, $enabledCount);
}
```

### Test Case 13: Anonymous users use consistent bucketing

```php
/**
 * @test
 * @covers FeatureFlagService::passesRollout
 */
public function passesRollout_handlesNullUserId(): void
{
    // Arrange
    $flag = $this->createFlag('feature', true, true, 50);
    
    // Act - null user ID should use 'anonymous' string
    $result1 = $this->invokePrivateMethod($this->service, 'passesRollout', [$flag, null]);
    $result2 = $this->invokePrivateMethod($this->service, 'passesRollout', [$flag, null]);
    
    // Assert - should be deterministic even for null
    $this->assertEquals($result1, $result2);
}
```

---

## 🧪 Caching Tests

### Test Case 14: Results are cached

```php
/**
 * @test
 * @covers FeatureFlagService::isEnabled
 */
public function isEnabled_cachesResults(): void
{
    // Arrange
    $flagKey = 'cached_feature';
    
    $this->repository->expects($this->once()) // Only called once!
        ->method('findByKey')
        ->willReturn($this->createFlag($flagKey, true, true, 100));
    
    $this->repository->method('findOverride')->willReturn(null);
    
    // Act - call twice
    $result1 = $this->service->isEnabled($flagKey);
    $result2 = $this->service->isEnabled($flagKey);
    
    // Assert - same result, repository only called once
    $this->assertEquals($result1, $result2);
}
```

### Test Case 15: Cache key includes user/exam/role

```php
/**
 * @test
 * @covers FeatureFlagService::buildCacheKey
 */
public function buildCacheKey_includesAllParameters(): void
{
    // Arrange & Act
    $key1 = $this->invokePrivateMethod($this->service, 'buildCacheKey', 
        ['feature', 1, 10, 'ADMIN']);
    $key2 = $this->invokePrivateMethod($this->service, 'buildCacheKey', 
        ['feature', 1, 20, 'ADMIN']);
    $key3 = $this->invokePrivateMethod($this->service, 'buildCacheKey', 
        ['feature', 2, 10, 'ADMIN']);
    
    // Assert - all different
    $this->assertNotEquals($key1, $key2);
    $this->assertNotEquals($key1, $key3);
}
```

### Test Case 16: clearCache removes cached results

```php
/**
 * @test
 * @covers FeatureFlagService::clearCache
 */
public function clearCache_removesSpecificFlag(): void
{
    // Arrange - populate cache
    $this->repository->method('findByKey')
        ->willReturn($this->createFlag('feature', true, true, 100));
    $this->repository->method('findOverride')->willReturn(null);
    
    $this->service->isEnabled('feature');
    
    // Act
    $this->service->clearCache('feature');
    
    // Repository should be called again after cache clear
    $this->repository->expects($this->once())
        ->method('findByKey');
    
    $this->service->isEnabled('feature');
}
```

---

## 🔧 Test Helper Methods

```php
private function createFlag(
    string $key,
    bool $defaultValue,
    bool $isEnabled,
    int $rolloutPercentage
): FeatureFlag {
    return new FeatureFlag(
        id: 1,
        flagKey: $key,
        displayName: ucfirst(str_replace('_', ' ', $key)),
        description: 'Test flag',
        defaultValue: $defaultValue,
        isEnabled: $isEnabled,
        category: 'test',
        rolloutPercentage: $rolloutPercentage,
        createdAt: new \DateTime(),
        updatedAt: new \DateTime()
    );
}

private function createOverride(
    string $flagKey,
    bool $isEnabled,
    ?string $expiresAt
): object {
    return (object) [
        'flagKey' => $flagKey,
        'isEnabled' => $isEnabled,
        'expiresAt' => $expiresAt,
    ];
}

private function invokePrivateMethod(
    object $object,
    string $methodName,
    array $parameters = []
): mixed {
    $reflection = new \ReflectionClass($object);
    $method = $reflection->getMethod($methodName);
    $method->setAccessible(true);
    return $method->invokeArgs($object, $parameters);
}
```

---

## ✅ Acceptance Criteria

- [ ] Resolution hierarchy: User > Exam > Role > Base > Seed
- [ ] Expired overrides are ignored
- [ ] Unknown flags return false (fail closed)
- [ ] Rollout percentage is deterministic per user
- [ ] 0% rollout always fails, 100% always passes
- [ ] Results are cached to avoid repeated DB queries
- [ ] Cache can be cleared per-flag or globally
- [ ] 95%+ line coverage for FeatureFlagService

---

*Next: `41d-test-spec-deadline-engine.md`*
