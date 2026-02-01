# 41e - Test Specification: Progress Calculation

> **Component:** `src/Services/ProgressCalculator.php`  
> **Test File:** `tests/php/Unit/Services/ProgressCalculatorTest.php`  
> **Priority:** High (Core user-facing metric)  
> **Source:** `COMMON-IMPLEMENTATION-PITFALLS.md` Section 2

---

## 📋 Test Coverage Summary

| Method | Test Cases | Edge Cases |
|--------|------------|------------|
| `calculateProgress()` | 8 | 4 |
| `calculateWeightedProgress()` | 6 | 3 |
| `normalizeWeights()` | 3 | 2 |

---

## 🧪 Basic Progress Calculation Tests

### Test Case 1: Uses floor() not round()

```php
/**
 * @test
 * @covers ProgressCalculator::calculateProgress
 * @see COMMON-IMPLEMENTATION-PITFALLS.md Section 2.1
 */
public function calculateProgress_usesFloor_notRound(): void
{
    // Arrange - 99 of 100 complete = 99%
    $items = $this->createChecklistItems(total: 100, completed: 99);
    
    // Act
    $progress = $this->calculator->calculateProgress($items);
    
    // Assert - MUST be 99, not 100
    $this->assertEquals(99, $progress);
}
```

### Test Case 2: Never shows 100% unless truly complete

```php
/**
 * @test
 * @covers ProgressCalculator::calculateProgress
 * @see COMMON-IMPLEMENTATION-PITFALLS.md Section 2.1
 */
public function calculateProgress_never100_unlessTrulyComplete(): void
{
    // Arrange - 999 of 1000 complete = 99.9%
    $items = $this->createChecklistItems(total: 1000, completed: 999);
    
    // Act
    $progress = $this->calculator->calculateProgress($items);
    
    // Assert - floor(99.9) = 99, not 100
    $this->assertEquals(99, $progress);
}
```

### Test Case 3: Shows 100% when fully complete

```php
/**
 * @test
 * @covers ProgressCalculator::calculateProgress
 */
public function calculateProgress_shows100_whenAllComplete(): void
{
    // Arrange
    $items = $this->createChecklistItems(total: 10, completed: 10);
    
    // Act
    $progress = $this->calculator->calculateProgress($items);
    
    // Assert
    $this->assertEquals(100, $progress);
}
```

### Test Case 4: Shows 0% when none complete

```php
/**
 * @test
 * @covers ProgressCalculator::calculateProgress
 */
public function calculateProgress_shows0_whenNoneComplete(): void
{
    // Arrange
    $items = $this->createChecklistItems(total: 10, completed: 0);
    
    // Act
    $progress = $this->calculator->calculateProgress($items);
    
    // Assert
    $this->assertEquals(0, $progress);
}
```

---

## 🧪 SKIPPED Items Tests

### Test Case 5: SKIPPED items excluded from total

```php
/**
 * @test
 * @covers ProgressCalculator::calculateProgress
 * @see COMMON-IMPLEMENTATION-PITFALLS.md Section 2.2
 */
public function calculateProgress_excludesSkippedFromTotal(): void
{
    // Arrange
    $items = [
        $this->createItem(ChecklistStatus::COMPLETED),
        $this->createItem(ChecklistStatus::COMPLETED),
        $this->createItem(ChecklistStatus::SKIPPED), // Excluded
        $this->createItem(ChecklistStatus::PENDING),
    ];
    
    // Act
    $progress = $this->calculator->calculateProgress($items);
    
    // Assert - 2 of 3 (not 2 of 4) = 66%
    $this->assertEquals(66, $progress);
}
```

### Test Case 6: All SKIPPED returns 100% (nothing required)

```php
/**
 * @test
 * @covers ProgressCalculator::calculateProgress
 */
public function calculateProgress_returns100_whenAllSkipped(): void
{
    // Arrange
    $items = [
        $this->createItem(ChecklistStatus::SKIPPED),
        $this->createItem(ChecklistStatus::SKIPPED),
    ];
    
    // Act
    $progress = $this->calculator->calculateProgress($items);
    
    // Assert - nothing required = complete
    $this->assertEquals(100, $progress);
}
```

### Test Case 7: SKIPPED items excluded from completed count

```php
/**
 * @test
 * @covers ProgressCalculator::calculateProgress
 * @see COMMON-IMPLEMENTATION-PITFALLS.md Section 2.2
 */
public function calculateProgress_skippedNotCountedAsCompleted(): void
{
    // Arrange
    $items = [
        $this->createItem(ChecklistStatus::COMPLETED),
        $this->createItem(ChecklistStatus::SKIPPED),
        $this->createItem(ChecklistStatus::PENDING),
    ];
    
    // Act
    $progress = $this->calculator->calculateProgress($items);
    
    // Assert - 1 of 2 (skipped excluded) = 50%
    $this->assertEquals(50, $progress);
}
```

---

## 🧪 Division by Zero Tests

### Test Case 8: Empty items array returns 0

```php
/**
 * @test
 * @covers ProgressCalculator::calculateProgress
 * @see COMMON-IMPLEMENTATION-PITFALLS.md Section 2.4
 */
public function calculateProgress_returns0_whenNoItems(): void
{
    // Arrange
    $items = [];
    
    // Act
    $progress = $this->calculator->calculateProgress($items);
    
    // Assert - no items = 0% (or 100% depending on policy)
    $this->assertEquals(0, $progress);
}
```

### Test Case 9: All items filtered out returns 100

```php
/**
 * @test
 * @covers ProgressCalculator::calculateProgress
 * @see COMMON-IMPLEMENTATION-PITFALLS.md Section 2.4
 */
public function calculateProgress_handles_allFilteredOut(): void
{
    // Arrange - all items are SKIPPED (filtered)
    $items = [
        $this->createItem(ChecklistStatus::SKIPPED),
    ];
    
    // Act - should not divide by zero
    $progress = $this->calculator->calculateProgress($items);
    
    // Assert - no division by zero error
    $this->assertEquals(100, $progress);
}
```

---

## 🧪 Phase-Weighted Progress Tests

### Test Case 10: Weights sum to 1.0

```php
/**
 * @test
 * @covers ProgressCalculator::calculateWeightedProgress
 * @see COMMON-IMPLEMENTATION-PITFALLS.md Section 2.3
 */
public function calculateWeightedProgress_weightsSum1(): void
{
    // Arrange
    $phases = [
        'PRE' => ['total' => 2, 'completed' => 2, 'weight' => 0.20],
        'IN_EXAM' => ['total' => 5, 'completed' => 5, 'weight' => 0.60],
        'POST' => ['total' => 2, 'completed' => 2, 'weight' => 0.20],
    ];
    
    // Act
    $progress = $this->calculator->calculateWeightedProgress($phases);
    
    // Assert - 100% on all phases = 100% total
    $this->assertEquals(100, $progress);
}
```

### Test Case 11: Invalid weights normalized

```php
/**
 * @test
 * @covers ProgressCalculator::normalizeWeights
 * @see COMMON-IMPLEMENTATION-PITFALLS.md Section 2.3
 */
public function normalizeWeights_fixesIncorrectSum(): void
{
    // Arrange - weights sum to 1.10 (wrong!)
    $phases = [
        'PRE' => ['weight' => 0.20],
        'IN_EXAM' => ['weight' => 0.70], // Should be 0.60
        'POST' => ['weight' => 0.20],
    ];
    
    // Act
    $normalized = $this->calculator->normalizeWeights($phases);
    
    // Assert
    $totalWeight = array_sum(array_column($normalized, 'weight'));
    $this->assertEquals(1.0, $totalWeight, '', 0.001);
}
```

### Test Case 12: Partial phase completion weighted correctly

```php
/**
 * @test
 * @covers ProgressCalculator::calculateWeightedProgress
 */
public function calculateWeightedProgress_partialPhases(): void
{
    // Arrange
    $phases = [
        'PRE' => ['total' => 2, 'completed' => 2, 'weight' => 0.20],     // 100% * 0.20 = 20%
        'IN_EXAM' => ['total' => 10, 'completed' => 5, 'weight' => 0.60], // 50% * 0.60 = 30%
        'POST' => ['total' => 2, 'completed' => 0, 'weight' => 0.20],    // 0% * 0.20 = 0%
    ];
    
    // Act
    $progress = $this->calculator->calculateWeightedProgress($phases);
    
    // Assert - 20 + 30 + 0 = 50%
    $this->assertEquals(50, $progress);
}
```

### Test Case 13: Empty phase doesn't break calculation

```php
/**
 * @test
 * @covers ProgressCalculator::calculateWeightedProgress
 */
public function calculateWeightedProgress_handlesEmptyPhase(): void
{
    // Arrange - IN_EXAM has no items
    $phases = [
        'PRE' => ['total' => 2, 'completed' => 2, 'weight' => 0.20],
        'IN_EXAM' => ['total' => 0, 'completed' => 0, 'weight' => 0.60], // Empty!
        'POST' => ['total' => 2, 'completed' => 2, 'weight' => 0.20],
    ];
    
    // Act - should not throw
    $progress = $this->calculator->calculateWeightedProgress($phases);
    
    // Assert - empty phase treated as 100% complete
    $this->assertEquals(100, $progress);
}
```

### Test Case 14: Progress never exceeds 100%

```php
/**
 * @test
 * @covers ProgressCalculator::calculateWeightedProgress
 * @see COMMON-IMPLEMENTATION-PITFALLS.md Section 2.3
 */
public function calculateWeightedProgress_neverExceeds100(): void
{
    // Arrange - weights intentionally over 1.0
    $phases = [
        'PRE' => ['total' => 2, 'completed' => 2, 'weight' => 0.50],
        'IN_EXAM' => ['total' => 5, 'completed' => 5, 'weight' => 0.50],
        'POST' => ['total' => 2, 'completed' => 2, 'weight' => 0.50], // Sum = 1.5!
    ];
    
    // Act
    $progress = $this->calculator->calculateWeightedProgress($phases);
    
    // Assert - capped at 100
    $this->assertLessThanOrEqual(100, $progress);
}
```

### Test Case 15: Progress never below 0%

```php
/**
 * @test
 * @covers ProgressCalculator::calculateWeightedProgress
 */
public function calculateWeightedProgress_neverBelow0(): void
{
    // Arrange - negative values shouldn't happen but test anyway
    $phases = [
        'PRE' => ['total' => 2, 'completed' => 0, 'weight' => 0.20],
        'IN_EXAM' => ['total' => 5, 'completed' => 0, 'weight' => 0.60],
        'POST' => ['total' => 2, 'completed' => 0, 'weight' => 0.20],
    ];
    
    // Act
    $progress = $this->calculator->calculateWeightedProgress($phases);
    
    // Assert
    $this->assertGreaterThanOrEqual(0, $progress);
    $this->assertEquals(0, $progress);
}
```

---

## 🧪 Edge Cases

### Test Case 16: Single item checklist

```php
/**
 * @test
 * @covers ProgressCalculator::calculateProgress
 */
public function calculateProgress_singleItem(): void
{
    // Arrange
    $items = [$this->createItem(ChecklistStatus::PENDING)];
    
    // Act
    $progress = $this->calculator->calculateProgress($items);
    
    // Assert
    $this->assertEquals(0, $progress);
    
    // Arrange - now complete
    $items = [$this->createItem(ChecklistStatus::COMPLETED)];
    
    // Act
    $progress = $this->calculator->calculateProgress($items);
    
    // Assert
    $this->assertEquals(100, $progress);
}
```

### Test Case 17: Large checklist accurate

```php
/**
 * @test
 * @covers ProgressCalculator::calculateProgress
 */
public function calculateProgress_largeChecklist(): void
{
    // Arrange - 1000 items, 333 complete
    $items = $this->createChecklistItems(total: 1000, completed: 333);
    
    // Act
    $progress = $this->calculator->calculateProgress($items);
    
    // Assert - floor(33.3) = 33
    $this->assertEquals(33, $progress);
}
```

### Test Case 18: Mixed status items

```php
/**
 * @test
 * @covers ProgressCalculator::calculateProgress
 */
public function calculateProgress_mixedStatuses(): void
{
    // Arrange
    $items = [
        $this->createItem(ChecklistStatus::COMPLETED),
        $this->createItem(ChecklistStatus::COMPLETED),
        $this->createItem(ChecklistStatus::PENDING),
        $this->createItem(ChecklistStatus::IN_PROGRESS),
        $this->createItem(ChecklistStatus::SKIPPED),
    ];
    
    // Act
    $progress = $this->calculator->calculateProgress($items);
    
    // Assert - 2 of 4 (excluding SKIPPED) = 50%
    // IN_PROGRESS counts as not complete
    $this->assertEquals(50, $progress);
}
```

---

## 🔧 Test Helper Methods

```php
private function createChecklistItems(int $total, int $completed): array
{
    $items = [];
    
    for ($i = 0; $i < $completed; $i++) {
        $items[] = $this->createItem(ChecklistStatus::COMPLETED);
    }
    
    for ($i = $completed; $i < $total; $i++) {
        $items[] = $this->createItem(ChecklistStatus::PENDING);
    }
    
    return $items;
}

private function createItem(ChecklistStatus $status): ChecklistItem
{
    return new ChecklistItem(
        id: rand(1, 10000),
        status: $status,
        isRequired: true
    );
}
```

---

## ✅ Acceptance Criteria

- [ ] `floor()` used instead of `round()` - never shows 100% until complete
- [ ] SKIPPED items excluded from both total and completed counts
- [ ] Division by zero handled gracefully (empty arrays, all skipped)
- [ ] Phase weights normalized to sum to exactly 1.0
- [ ] Progress always between 0% and 100% inclusive
- [ ] IN_PROGRESS status not counted as complete
- [ ] 100% coverage for ProgressCalculator

---

## 📌 Related Pitfalls

| Pitfall | Test Case | Description |
|---------|-----------|-------------|
| 2.1 | TC1, TC2 | Using round() instead of floor() |
| 2.2 | TC5, TC6, TC7 | Not excluding SKIPPED items |
| 2.3 | TC10, TC11, TC14 | Phase weights not summing to 1.0 |
| 2.4 | TC8, TC9 | Division by zero |

---

*End of Test Specifications*
