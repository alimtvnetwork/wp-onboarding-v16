# 41d - Test Specification: Deadline Engine

> **Component:** `src/Services/DeadlineEngine.php`  
> **Test File:** `tests/php/Unit/Services/DeadlineEngineTest.php`  
> **Priority:** Critical (Core business logic)  
> **Source:** `COMMON-IMPLEMENTATION-PITFALLS.md` Section 1

---

## 📋 Test Coverage Summary

| Method | Test Cases | Edge Cases |
|--------|------------|------------|
| `calculateDeadlines()` | 6 | 3 |
| `applyExtension()` | 8 | 4 |
| `getEffectiveDeadline()` | 5 | 2 |
| `isLocked()` | 4 | 2 |
| `getDeadlineStatus()` | 6 | 3 |

---

## 🧪 Deadline Calculation Tests

### Test Case 1: Soft and hard deadlines calculated from signup

```php
/**
 * @test
 * @covers DeadlineEngine::calculateDeadlines
 */
public function calculateDeadlines_fromSignupDate(): void
{
    // Arrange
    $signupDate = new \\DateTime('2026-01-15 10:00:00', new \\DateTimeZone('UTC'));
    $exam = $this->createExam(softDays: 7, hardDays: 14);
    
    // Act
    $deadlines = $this->engine->calculateDeadlines($signupDate, $exam);
    
    // Assert
    $this->assertEquals(
        '2026-01-22 10:00:00',
        $deadlines['soft']->format('Y-m-d H:i:s')
    );
    $this->assertEquals(
        '2026-01-29 10:00:00',
        $deadlines['hard']->format('Y-m-d H:i:s')
    );
}
```

### Test Case 2: Soft deadline must be before hard deadline

```php
/**
 * @test
 * @covers DeadlineEngine::calculateDeadlines
 */
public function calculateDeadlines_throwsException_whenSoftAfterHard(): void
{
    // Arrange
    $signupDate = new \\DateTime('2026-01-15 10:00:00', new \\DateTimeZone('UTC'));
    $exam = $this->createExam(softDays: 14, hardDays: 7); // Invalid!
    
    // Assert
    $this->expectException(ValidationException::class);
    $this->expectExceptionMessage('Soft deadline must be before hard deadline');
    
    // Act
    $this->engine->calculateDeadlines($signupDate, $exam);
}
```

### Test Case 3: All dates stored in UTC

```php
/**
 * @test
 * @covers DeadlineEngine::calculateDeadlines
 */
public function calculateDeadlines_storesInUtc(): void
{
    // Arrange - signup in different timezone
    $signupDate = new \\DateTime('2026-01-15 10:00:00', new \\DateTimeZone('America/New_York'));
    $exam = $this->createExam(softDays: 7, hardDays: 14);
    
    // Act
    $deadlines = $this->engine->calculateDeadlines($signupDate, $exam);
    
    // Assert - timezone is UTC
    $this->assertEquals('UTC', $deadlines['soft']->getTimezone()->getName());
    $this->assertEquals('UTC', $deadlines['hard']->getTimezone()->getName());
}
```

### Test Case 4: Original deadlines preserved

```php
/**
 * @test
 * @covers DeadlineEngine::calculateDeadlines
 */
public function calculateDeadlines_setsOriginalDeadlines(): void
{
    // Arrange
    $signupDate = new \\DateTime('2026-01-15 10:00:00', new \\DateTimeZone('UTC'));
    $exam = $this->createExam(softDays: 7, hardDays: 14);
    
    // Act
    $deadlines = $this->engine->calculateDeadlines($signupDate, $exam);
    
    // Assert - originals match calculated
    $this->assertEquals($deadlines['soft'], $deadlines['originalSoft']);
    $this->assertEquals($deadlines['hard'], $deadlines['originalHard']);
}
```

---

## 🧪 Extension Application Tests (Critical Pitfalls)

### Test Case 5: First extension uses ORIGINAL hard deadline

```php
/**
 * @test
 * @covers DeadlineEngine::applyExtension
 * @see COMMON-IMPLEMENTATION-PITFALLS.md Section 1.1
 */
public function applyExtension_firstExtensionUsesOriginalHardDeadline(): void
{
    // Arrange
    $participant = $this->createParticipant([
        'hardDeadlineDate' => '2026-01-29 10:00:00',
        'originalHardDeadline' => '2026-01-29 10:00:00',
        'extensionDeadlineDate' => null,
    ]);
    
    // Act
    $result = $this->engine->applyExtension($participant, approvedDays: 7);
    
    // Assert - 7 days from ORIGINAL hard deadline
    $this->assertEquals(
        '2026-02-05 10:00:00',
        $result['extensionDeadline']->format('Y-m-d H:i:s')
    );
}
```

### Test Case 6: Subsequent extensions use CURRENT extension deadline

```php
/**
 * @test
 * @covers DeadlineEngine::applyExtension
 * @see COMMON-IMPLEMENTATION-PITFALLS.md Section 1.1
 */
public function applyExtension_subsequentExtensionUsesCurrentExtensionDeadline(): void
{
    // Arrange - already has one extension
    $participant = $this->createParticipant([
        'hardDeadlineDate' => '2026-01-29 10:00:00',
        'originalHardDeadline' => '2026-01-29 10:00:00',
        'extensionDeadlineDate' => '2026-02-05 10:00:00', // First extension
    ]);
    
    // Act - second extension
    $result = $this->engine->applyExtension($participant, approvedDays: 3);
    
    // Assert - 3 days from CURRENT extension deadline (not original!)
    $this->assertEquals(
        '2026-02-08 10:00:00',
        $result['extensionDeadline']->format('Y-m-d H:i:s')
    );
}
```

### Test Case 7: NEVER extend from soft deadline

```php
/**
 * @test
 * @covers DeadlineEngine::applyExtension
 * @see COMMON-IMPLEMENTATION-PITFALLS.md Section 1.2
 */
public function applyExtension_neverExtendsFromSoftDeadline(): void
{
    // Arrange
    $participant = $this->createParticipant([
        'softDeadlineDate' => '2026-01-22 10:00:00',
        'hardDeadlineDate' => '2026-01-29 10:00:00',
        'originalHardDeadline' => '2026-01-29 10:00:00',
    ]);
    
    // Act
    $result = $this->engine->applyExtension($participant, approvedDays: 7);
    
    // Assert - based on HARD deadline, not soft
    $expectedDate = (new \\DateTime('2026-01-29 10:00:00'))
        ->modify('+7 days')
        ->format('Y-m-d H:i:s');
    
    $this->assertEquals($expectedDate, $result['extensionDeadline']->format('Y-m-d H:i:s'));
    $this->assertNotEquals(
        '2026-01-29 10:00:00', // Would be wrong if extending from soft
        $result['extensionDeadline']->format('Y-m-d H:i:s')
    );
}
```

### Test Case 8: Original deadline preserved after extension

```php
/**
 * @test
 * @covers DeadlineEngine::applyExtension
 * @see COMMON-IMPLEMENTATION-PITFALLS.md Section 1.3
 */
public function applyExtension_preservesOriginalDeadline(): void
{
    // Arrange
    $original = '2026-01-29 10:00:00';
    $participant = $this->createParticipant([
        'hardDeadlineDate' => $original,
        'originalHardDeadline' => null, // Not yet set
    ]);
    
    // Act
    $result = $this->engine->applyExtension($participant, approvedDays: 7);
    
    // Assert - original is preserved
    $this->assertEquals($original, $result['originalHardDeadline']->format('Y-m-d H:i:s'));
}
```

### Test Case 9: Already-set original deadline not overwritten

```php
/**
 * @test
 * @covers DeadlineEngine::applyExtension
 */
public function applyExtension_doesNotOverwriteExistingOriginal(): void
{
    // Arrange - original already set (from previous extension)
    $participant = $this->createParticipant([
        'hardDeadlineDate' => '2026-02-05 10:00:00',
        'originalHardDeadline' => '2026-01-29 10:00:00', // Already preserved
    ]);
    
    // Act
    $result = $this->engine->applyExtension($participant, approvedDays: 7);
    
    // Assert - original unchanged
    $this->assertEquals(
        '2026-01-29 10:00:00',
        $result['originalHardDeadline']->format('Y-m-d H:i:s')
    );
}
```

### Test Case 10: Extension limits enforced

```php
/**
 * @test
 * @covers DeadlineEngine::applyExtension
 */
public function applyExtension_enforcesMaximumDays(): void
{
    // Arrange
    $participant = $this->createParticipant([
        'hardDeadlineDate' => '2026-01-29 10:00:00',
        'originalHardDeadline' => '2026-01-29 10:00:00',
        'extensionDays' => 25, // Already extended 25 days
    ]);
    
    // Act & Assert - trying to add 10 more when max is 30
    $this->expectException(ValidationException::class);
    $this->expectExceptionMessage('Maximum extension limit exceeded');
    
    $this->engine->applyExtension($participant, approvedDays: 10);
}
```

---

## 🧪 Effective Deadline Tests

### Test Case 11: Admin override takes precedence

```php
/**
 * @test
 * @covers DeadlineEngine::getEffectiveDeadline
 */
public function getEffectiveDeadline_adminOverrideTakesPrecedence(): void
{
    // Arrange
    $participant = $this->createParticipant([
        'hardDeadlineDate' => '2026-01-29 10:00:00',
        'extensionDeadlineDate' => '2026-02-05 10:00:00',
        'deadlineOverride' => '2026-02-15 10:00:00', // Admin override
    ]);
    
    // Act
    $effective = $this->engine->getEffectiveDeadline($participant);
    
    // Assert
    $this->assertEquals('2026-02-15 10:00:00', $effective->format('Y-m-d H:i:s'));
}
```

### Test Case 12: Extension deadline when no override

```php
/**
 * @test
 * @covers DeadlineEngine::getEffectiveDeadline
 */
public function getEffectiveDeadline_usesExtension_whenNoOverride(): void
{
    // Arrange
    $participant = $this->createParticipant([
        'hardDeadlineDate' => '2026-01-29 10:00:00',
        'extensionDeadlineDate' => '2026-02-05 10:00:00',
        'deadlineOverride' => null,
    ]);
    
    // Act
    $effective = $this->engine->getEffectiveDeadline($participant);
    
    // Assert
    $this->assertEquals('2026-02-05 10:00:00', $effective->format('Y-m-d H:i:s'));
}
```

### Test Case 13: Hard deadline when no extension or override

```php
/**
 * @test
 * @covers DeadlineEngine::getEffectiveDeadline
 */
public function getEffectiveDeadline_usesHardDeadline_whenNoExtensionOrOverride(): void
{
    // Arrange
    $participant = $this->createParticipant([
        'hardDeadlineDate' => '2026-01-29 10:00:00',
        'extensionDeadlineDate' => null,
        'deadlineOverride' => null,
    ]);
    
    // Act
    $effective = $this->engine->getEffectiveDeadline($participant);
    
    // Assert
    $this->assertEquals('2026-01-29 10:00:00', $effective->format('Y-m-d H:i:s'));
}
```

---

## 🧪 Lock Status Tests

### Test Case 14: Participant locked after hard deadline

```php
/**
 * @test
 * @covers DeadlineEngine::isLocked
 */
public function isLocked_true_afterHardDeadline(): void
{
    // Arrange
    $participant = $this->createParticipant([
        'hardDeadlineDate' => '2026-01-20 10:00:00', // Past
        'extensionDeadlineDate' => null,
        'deadlineOverride' => null,
    ]);
    
    // Act
    $isLocked = $this->engine->isLocked($participant, new \\DateTime('2026-01-25'));
    
    // Assert
    $this->assertTrue($isLocked);
}
```

### Test Case 15: Participant NOT locked before hard deadline

```php
/**
 * @test
 * @covers DeadlineEngine::isLocked
 */
public function isLocked_false_beforeHardDeadline(): void
{
    // Arrange
    $participant = $this->createParticipant([
        'hardDeadlineDate' => '2026-01-29 10:00:00', // Future
    ]);
    
    // Act
    $isLocked = $this->engine->isLocked($participant, new \\DateTime('2026-01-25'));
    
    // Assert
    $this->assertFalse($isLocked);
}
```

### Test Case 16: Extension prevents lock

```php
/**
 * @test
 * @covers DeadlineEngine::isLocked
 */
public function isLocked_false_whenExtensionActive(): void
{
    // Arrange - hard deadline passed, but extension active
    $participant = $this->createParticipant([
        'hardDeadlineDate' => '2026-01-20 10:00:00', // Past
        'extensionDeadlineDate' => '2026-02-05 10:00:00', // Future
    ]);
    
    // Act
    $isLocked = $this->engine->isLocked($participant, new \\DateTime('2026-01-25'));
    
    // Assert
    $this->assertFalse($isLocked);
}
```

---

## 🧪 Deadline Status Tests

### Test Case 17: Status GREEN when plenty of time

```php
/**
 * @test
 * @covers DeadlineEngine::getDeadlineStatus
 */
public function getDeadlineStatus_green_whenPlentyOfTime(): void
{
    // Arrange - soft deadline is 10 days away
    $participant = $this->createParticipant([
        'softDeadlineDate' => '2026-02-04 10:00:00',
        'hardDeadlineDate' => '2026-02-11 10:00:00',
    ]);
    
    // Act
    $status = $this->engine->getDeadlineStatus($participant, new \\DateTime('2026-01-25'));
    
    // Assert
    $this->assertEquals(DeadlineStatus::GREEN, $status);
}
```

### Test Case 18: Status YELLOW approaching soft deadline

```php
/**
 * @test
 * @covers DeadlineEngine::getDeadlineStatus
 */
public function getDeadlineStatus_yellow_approachingSoftDeadline(): void
{
    // Arrange - soft deadline is 2 days away
    $participant = $this->createParticipant([
        'softDeadlineDate' => '2026-01-27 10:00:00',
        'hardDeadlineDate' => '2026-02-03 10:00:00',
    ]);
    
    // Act
    $status = $this->engine->getDeadlineStatus($participant, new \\DateTime('2026-01-25'));
    
    // Assert
    $this->assertEquals(DeadlineStatus::YELLOW, $status);
}
```

### Test Case 19: Status ORANGE past soft, before hard

```php
/**
 * @test
 * @covers DeadlineEngine::getDeadlineStatus
 */
public function getDeadlineStatus_orange_pastSoftBeforeHard(): void
{
    // Arrange
    $participant = $this->createParticipant([
        'softDeadlineDate' => '2026-01-20 10:00:00', // Past
        'hardDeadlineDate' => '2026-01-27 10:00:00', // Future
    ]);
    
    // Act
    $status = $this->engine->getDeadlineStatus($participant, new \\DateTime('2026-01-25'));
    
    // Assert
    $this->assertEquals(DeadlineStatus::ORANGE, $status);
}
```

### Test Case 20: Status RED approaching hard deadline

```php
/**
 * @test
 * @covers DeadlineEngine::getDeadlineStatus
 */
public function getDeadlineStatus_red_approachingHardDeadline(): void
{
    // Arrange - hard deadline is 1 day away
    $participant = $this->createParticipant([
        'softDeadlineDate' => '2026-01-20 10:00:00', // Past
        'hardDeadlineDate' => '2026-01-26 10:00:00', // Tomorrow
    ]);
    
    // Act
    $status = $this->engine->getDeadlineStatus($participant, new \\DateTime('2026-01-25'));
    
    // Assert
    $this->assertEquals(DeadlineStatus::RED, $status);
}
```

### Test Case 21: Status BLACK when locked

```php
/**
 * @test
 * @covers DeadlineEngine::getDeadlineStatus
 */
public function getDeadlineStatus_black_whenLocked(): void
{
    // Arrange - hard deadline passed
    $participant = $this->createParticipant([
        'softDeadlineDate' => '2026-01-15 10:00:00',
        'hardDeadlineDate' => '2026-01-22 10:00:00',
    ]);
    
    // Act
    $status = $this->engine->getDeadlineStatus($participant, new \\DateTime('2026-01-25'));
    
    // Assert
    $this->assertEquals(DeadlineStatus::BLACK, $status);
}
```

---

## 🔧 Test Helper Methods

```php
private function createExam(int $softDays, int $hardDays): Exam
{
    return new Exam(
        id: 1,
        title: 'Test Exam',
        slug: 'test-exam',
        softDeadlineDays: $softDays,
        hardDeadlineDays: $hardDays
    );
}

private function createParticipant(array $data): Participant
{
    return new Participant(
        id: $data['id'] ?? 1,
        examId: $data['examId'] ?? 1,
        softDeadlineDate: isset($data['softDeadlineDate']) 
            ? new \\DateTime($data['softDeadlineDate'], new \\DateTimeZone('UTC')) 
            : null,
        hardDeadlineDate: isset($data['hardDeadlineDate']) 
            ? new \\DateTime($data['hardDeadlineDate'], new \\DateTimeZone('UTC')) 
            : null,
        originalHardDeadline: isset($data['originalHardDeadline']) 
            ? new \\DateTime($data['originalHardDeadline'], new \\DateTimeZone('UTC')) 
            : null,
        extensionDeadlineDate: isset($data['extensionDeadlineDate']) 
            ? new \\DateTime($data['extensionDeadlineDate'], new \\DateTimeZone('UTC')) 
            : null,
        deadlineOverride: isset($data['deadlineOverride']) 
            ? new \\DateTime($data['deadlineOverride'], new \\DateTimeZone('UTC')) 
            : null,
        extensionDays: $data['extensionDays'] ?? 0
    );
}
```

---

## ✅ Acceptance Criteria

- [ ] First extension calculated from ORIGINAL hard deadline
- [ ] Subsequent extensions calculated from CURRENT extension deadline
- [ ] Extensions NEVER calculated from soft deadline
- [ ] Original deadline preserved on first modification
- [ ] All dates stored and compared in UTC
- [ ] Soft deadline always before hard deadline validated
- [ ] Admin override > Extension > Hard deadline priority
- [ ] Correct status colors at each deadline phase
- [ ] 100% coverage for DeadlineEngine

---

*Next: `41e-test-spec-progress-calculation.md`*
