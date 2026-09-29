<?php

declare(strict_types=1);

namespace WaysNX\BusinessFramework\Tests\Unit\BusinessFunctions;

use PHPUnit\Framework\TestCase;
use WaysNX\BusinessFramework\BusinessFunctions\ApplyLeave;
use WaysNX\BusinessFramework\Runtime\BusinessFunctionRuntime;

/**
 * ApplyLeaveTest
 *
 * Comprehensive tests for the real HR.LEAVE.APPLY.APPLY_LEAVE business function.
 *
 * Tests validate:
 * - Request validation
 * - Authorization checks
 * - Business rules enforcement
 * - Successful leave request creation
 * - Error handling
 * - Event publishing
 * - Response contract compliance
 * - Runtime integration
 *
 * @package WaysNX\BusinessFramework\Tests\Unit\BusinessFunctions
 */
class ApplyLeaveTest extends TestCase
{
    private ApplyLeave $applyLeave;
    private BusinessFunctionRuntime $runtime;

    protected function setUp(): void
    {
        $this->applyLeave = new ApplyLeave();
        $this->runtime = new BusinessFunctionRuntime();
        ApplyLeave::clearCreatedRequests();
    }

    /**
     * Helper: Get a future date relative to today
     *
     * @param int $daysInFuture Number of days from today
     * @return string Date in Y-m-d format
     */
    private function futureDate(int $daysInFuture): string
    {
        return (new \DateTime())
            ->add(new \DateInterval('P' . $daysInFuture . 'D'))
            ->format('Y-m-d');
    }

    /**
     * Helper: Get a past date relative to today
     *
     * @param int $daysInPast Number of days ago from today
     * @return string Date in Y-m-d format
     */
    private function pastDate(int $daysInPast): string
    {
        return (new \DateTime())
            ->sub(new \DateInterval('P' . $daysInPast . 'D'))
            ->format('Y-m-d');
    }

    // ================================
    // GROUP 1: SUCCESSFUL SCENARIOS
    // ================================

    /**
     * Test 1: Valid annual leave request succeeds
     *
     * Validates the happy path: valid request from active employee with sufficient balance
     */
    public function testValidAnnualLeaveRequestSucceeds(): void
    {
        $request = [
            'employeeId' => 'EMP-2026-001',
            'leaveType' => 'Annual',
            'startDate' => $this->futureDate(14),
            'endDate' => $this->futureDate(19),
        ];

        $caller = [
            'id' => 'EMP-2026-001',
            'role' => 'Employee',
        ];

        $response = $this->runtime->execute($this->applyLeave, $request, $caller);

        // Verify success
        $this->assertTrue($this->runtime->wasSuccessful());
        $this->assertArrayHasKey('leaveRequestId', $response);
        $this->assertArrayHasKey('status', $response);
        $this->assertArrayHasKey('remainingBalance', $response);
        $this->assertArrayHasKey('daysRequested', $response);

        // Verify response - 6 days needs approval so status is Pending unless auto-approved
        // Annual leave auto-approves if <= 3 days, so 6 days = Pending
        $this->assertSame(6, $response['daysRequested']); // 6 days
        $this->assertSame(9, $response['remainingBalance']); // 15 - 6 = 9
    }

    /**
     * Test 2: Manager can apply leave for their direct report
     *
     * Tests authorization: Manager applying on behalf of subordinate
     */
    public function testManagerCanApplyLeaveForDirectReport(): void
    {
        // Jane Manager (EMP-2026-005) applying for Alice Johnson (EMP-2026-001)
        $request = [
            'employeeId' => 'EMP-2026-001',
            'leaveType' => 'Sick',
            'startDate' => $this->futureDate(10),
            'endDate' => $this->futureDate(11),
        ];

        $caller = [
            'id' => 'EMP-2026-005', // Jane Manager
            'role' => 'Manager',
        ];

        $response = $this->runtime->execute($this->applyLeave, $request, $caller);

        $this->assertTrue($this->runtime->wasSuccessful());
        $this->assertSame('Pending', $response['status']);
    }

    /**
     * Test 3: HR Admin can apply leave for any employee
     *
     * Tests authorization: HR Admin has full permissions
     */
    public function testHrAdminCanApplyLeaveForAnyEmployee(): void
    {
        $request = [
            'employeeId' => 'EMP-2026-002',
            'leaveType' => 'Unpaid',
            'startDate' => $this->futureDate(20),
            'endDate' => $this->futureDate(23),
        ];

        $caller = [
            'id' => 'EMP-HR-ADMIN-001',
            'role' => 'HR_Admin',
        ];

        $response = $this->runtime->execute($this->applyLeave, $request, $caller);

        $this->assertTrue($this->runtime->wasSuccessful());
        $this->assertArrayHasKey('leaveRequestId', $response);
    }

    /**
     * Test 4: Short annual leave auto-approves
     *
     * Tests business logic: Annual leave ≤3 days auto-approves
     */
    public function testShortAnnualLeaveAutoApproves(): void
    {
        $request = [
            'employeeId' => 'EMP-2026-004',
            'leaveType' => 'Annual',
            'startDate' => $this->futureDate(26),
            'endDate' => $this->futureDate(28), // 3 days
        ];

        $caller = [
            'id' => 'EMP-2026-004',
            'role' => 'Employee',
        ];

        $response = $this->runtime->execute($this->applyLeave, $request, $caller);

        $this->assertTrue($this->runtime->wasSuccessful());
        $this->assertSame('Approved', $response['status']);
        $this->assertSame(3, $response['daysRequested']);
    }

    /**
     * Test 5: Remaining balance is calculated correctly
     *
     * Verifies balance calculation: current - requested = remaining
     */
    public function testRemainingBalanceCalculatedCorrectly(): void
    {
        $request = [
            'employeeId' => 'EMP-2026-002', // Has 18 Annual days
            'leaveType' => 'Annual',
            'startDate' => $this->futureDate(29),
            'endDate' => $this->futureDate(33), // 5 days
        ];

        $caller = [
            'id' => 'EMP-2026-002',
            'role' => 'Employee',
        ];

        $response = $this->runtime->execute($this->applyLeave, $request, $caller);

        $this->assertTrue($this->runtime->wasSuccessful());
        $this->assertSame(5, $response['daysRequested']);
        $this->assertSame(13, $response['remainingBalance']); // 18 - 5 = 13
    }

    /**
     * Test 6: Runtime can execute real ApplyLeave
     *
     * Verifies that the Runtime successfully orchestrates ApplyLeave
     */
    public function testRuntimeExecutesRealApplyLeave(): void
    {
        $request = [
            'employeeId' => 'EMP-2026-004',
            'leaveType' => 'Compensatory',
            'startDate' => $this->futureDate(38),
            'endDate' => $this->futureDate(39),
        ];

        $caller = [
            'id' => 'EMP-2026-004',
            'role' => 'Employee',
        ];

        $response = $this->runtime->execute($this->applyLeave, $request, $caller);

        // Verify all phases succeeded
        $context = $this->runtime->getContext();
        $this->assertSame('success', $context['status']);
        $this->assertArrayHasKey('executionTime', $context);
        $this->assertGreaterThan(0, $context['executionTime']);

        // Verify response
        $this->assertSame('Approved', $response['status']);
    }

    // ================================
    // GROUP 2: VALIDATION FAILURES
    // ================================

    /**
     * Test 7: Invalid date format fails validation
     *
     * Validates date format checking in validateRequest()
     */
    public function testInvalidDateFormatFailsValidation(): void
    {
        $request = [
            'employeeId' => 'EMP-2026-001',
            'leaveType' => 'Annual',
            'startDate' => '15-09-2026', // Wrong format
            'endDate' => '2026-09-20',
        ];

        $caller = [
            'id' => 'EMP-2026-001',
            'role' => 'Employee',
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Date format must be YYYY-MM-DD');

        $this->runtime->execute($this->applyLeave, $request, $caller);
    }

    /**
     * Test 8: Start date after end date fails validation
     *
     * Validates logical date constraints
     */
    public function testStartDateAfterEndDateFailsValidation(): void
    {
        $request = [
            'employeeId' => 'EMP-2026-001',
            'leaveType' => 'Annual',
            'startDate' => $this->futureDate(20),
            'endDate' => $this->futureDate(15), // Before start
        ];

        $caller = [
            'id' => 'EMP-2026-001',
            'role' => 'Employee',
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('startDate cannot be after endDate');

        $this->runtime->execute($this->applyLeave, $request, $caller);
    }

    /**
     * Test 9: Past date fails validation
     *
     * Validates that leave cannot be requested for past dates
     */
    public function testPastDateFailsValidation(): void
    {
        $request = [
            'employeeId' => 'EMP-2026-001',
            'leaveType' => 'Annual',
            'startDate' => $this->pastDate(5), // Past
            'endDate' => $this->pastDate(1),
        ];

        $caller = [
            'id' => 'EMP-2026-001',
            'role' => 'Employee',
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('startDate cannot be in the past');

        $this->runtime->execute($this->applyLeave, $request, $caller);
    }

    /**
     * Test 10: Invalid leave type fails validation
     *
     * Validates leave type enum constraint
     */
    public function testInvalidLeaveTypeFailsValidation(): void
    {
        $request = [
            'employeeId' => 'EMP-2026-001',
            'leaveType' => 'InvalidType',
            'startDate' => $this->futureDate(14),
            'endDate' => $this->futureDate(19),
        ];

        $caller = [
            'id' => 'EMP-2026-001',
            'role' => 'Employee',
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('leaveType must be one of');

        $this->runtime->execute($this->applyLeave, $request, $caller);
    }

    // ================================
    // GROUP 3: AUTHORIZATION FAILURES
    // ================================

    /**
     * Test 11: Employee cannot apply leave for other employees
     *
     * Validates authorization: Employee role scope
     */
    public function testEmployeeCannotApplyLeaveForOthers(): void
    {
        $request = [
            'employeeId' => 'EMP-2026-002', // Different employee
            'leaveType' => 'Annual',
            'startDate' => $this->futureDate(14),
            'endDate' => $this->futureDate(19),
        ];

        $caller = [
            'id' => 'EMP-2026-001', // Different caller
            'role' => 'Employee',
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Employees can only apply leave for themselves');

        $this->runtime->execute($this->applyLeave, $request, $caller);
    }

    /**
     * Test 12: Manager cannot apply leave for non-reports
     *
     * Validates authorization: Manager scope limited to direct reports
     */
    public function testManagerCannotApplyLeaveForNonReports(): void
    {
        // Sales Manager (EMP-2026-006) trying to apply for Engineering employee (EMP-2026-001)
        $request = [
            'employeeId' => 'EMP-2026-001', // Not their report
            'leaveType' => 'Annual',
            'startDate' => $this->futureDate(14),
            'endDate' => $this->futureDate(19),
        ];

        $caller = [
            'id' => 'EMP-2026-006', // Sales Manager
            'role' => 'Manager',
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Managers can only apply leave for their direct reports');

        $this->runtime->execute($this->applyLeave, $request, $caller);
    }

    /**
     * Test 13: Missing caller context fails authorization
     *
     * Validates that authorization always requires caller context
     */
    public function testMissingCallerContextFailsAuthorization(): void
    {
        $request = [
            'employeeId' => 'EMP-2026-001',
            'leaveType' => 'Annual',
            'startDate' => $this->futureDate(14),
            'endDate' => $this->futureDate(19),
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Caller context required');

        $this->runtime->execute($this->applyLeave, $request, null);
    }

    // ================================
    // GROUP 4: BUSINESS RULE FAILURES
    // ================================

    /**
     * Test 14: Inactive employee cannot apply leave
     *
     * Validates business rule: Employee must be active
     */
    public function testInactiveEmployeeCannotApplyLeave(): void
    {
        // Carol Davis (EMP-2026-003) has status = 'Inactive'
        $request = [
            'employeeId' => 'EMP-2026-003',
            'leaveType' => 'Annual',
            'startDate' => $this->futureDate(14),
            'endDate' => $this->futureDate(19),
        ];

        $caller = [
            'id' => 'EMP-2026-003',
            'role' => 'Employee',
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('is not active');

        $this->runtime->execute($this->applyLeave, $request, $caller);
    }

    /**
     * Test 15: Insufficient leave balance fails
     *
     * Validates business rule: Must have sufficient balance
     */
    public function testInsufficientLeaveBalanceFails(): void
    {
        // Alice (EMP-2026-001) has only 15 Annual days
        $request = [
            'employeeId' => 'EMP-2026-001',
            'leaveType' => 'Annual',
            'startDate' => $this->futureDate(14),
            'endDate' => $this->futureDate(34), // 21 days (more than available)
        ];

        $caller = [
            'id' => 'EMP-2026-001',
            'role' => 'Employee',
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Insufficient leave balance');

        $this->runtime->execute($this->applyLeave, $request, $caller);
    }

    /**
     * Test 16: Overlapping approved leave fails
     *
     * Validates business rule: No overlapping approved leave
     */
    public function testOverlappingApprovedLeaveFails(): void
    {
        // Alice (EMP-2026-001) already has approved leave 90-94 days in future
        // Trying to request 92-97 days in future (overlaps)
        $request = [
            'employeeId' => 'EMP-2026-001',
            'leaveType' => 'Annual',
            'startDate' => $this->futureDate(92),
            'endDate' => $this->futureDate(97),
        ];

        $caller = [
            'id' => 'EMP-2026-001',
            'role' => 'Employee',
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('overlaps with existing approved leave');

        $this->runtime->execute($this->applyLeave, $request, $caller);
    }

    /**
     * Test 17: Exceeds maximum consecutive days fails
     *
     * Validates business rule: Maximum consecutive days constraint
     */
    public function testExceedsMaximumConsecutiveDaysFails(): void
    {
        // Sick leave has max 5 consecutive days
        $request = [
            'employeeId' => 'EMP-2026-001',
            'leaveType' => 'Sick',
            'startDate' => $this->futureDate(40),
            'endDate' => $this->futureDate(46), // 7 days (max is 5)
        ];

        $caller = [
            'id' => 'EMP-2026-001',
            'role' => 'Employee',
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Maximum consecutive leave');

        $this->runtime->execute($this->applyLeave, $request, $caller);
    }

    /**
     * Test 18: Single day leave succeeds
     *
     * Edge case: Single day (start = end)
     */
    public function testSingleDayLeaveSucceeds(): void
    {
        $request = [
            'employeeId' => 'EMP-2026-002',
            'leaveType' => 'Sick',
            'startDate' => $this->futureDate(18),
            'endDate' => $this->futureDate(18),
        ];

        $caller = [
            'id' => 'EMP-2026-002',
            'role' => 'Employee',
        ];

        $response = $this->runtime->execute($this->applyLeave, $request, $caller);

        $this->assertTrue($this->runtime->wasSuccessful());
        $this->assertSame(1, $response['daysRequested']);
    }

    // ================================
    // GROUP 5: EVENT AND RESPONSE BEHAVIOR
    // ================================

    /**
     * Test 19: LeaveRequested event published on success
     *
     * Validates that events are published after successful execution
     */
    public function testLeaveRequestedEventPublishedOnSuccess(): void
    {
        $request = [
            'employeeId' => 'EMP-2026-004',
            'leaveType' => 'Annual',
            'startDate' => $this->futureDate(48),
            'endDate' => $this->futureDate(50),
        ];

        $caller = [
            'id' => 'EMP-2026-004',
            'role' => 'Employee',
        ];

        $this->runtime->execute($this->applyLeave, $request, $caller);

        $events = $this->runtime->getEvents();
        $this->assertNotEmpty($events);
        $this->assertSame('LeaveRequested', $events[0]['type']);
    }

    /**
     * Test 20: Response follows contract
     *
     * Validates response contract compliance
     */
    public function testResponseFollowsContract(): void
    {
        $request = [
            'employeeId' => 'EMP-2026-002',
            'leaveType' => 'Annual',
            'startDate' => $this->futureDate(62),
            'endDate' => $this->futureDate(65),
        ];

        $caller = [
            'id' => 'EMP-2026-002',
            'role' => 'Employee',
        ];

        $response = $this->runtime->execute($this->applyLeave, $request, $caller);

        // Contract fields (success_fields from BFD)
        $this->assertArrayHasKey('leaveRequestId', $response);
        $this->assertArrayHasKey('status', $response);
        $this->assertArrayHasKey('remainingBalance', $response);
        $this->assertArrayHasKey('daysRequested', $response);
        $this->assertArrayHasKey('approverName', $response);
        $this->assertArrayHasKey('createdAt', $response);

        // Contract compliance: values are correct type
        $this->assertIsString($response['leaveRequestId']);
        $this->assertIsString($response['status']);
        $this->assertIsInt($response['remainingBalance']);
        $this->assertIsInt($response['daysRequested']);
        $this->assertIsString($response['createdAt']);
    }

    /**
     * Test 21: BFD remains accurate after real execution
     *
     * Validates that the BFD is preserved and matches implementation
     */
    public function testBfdRemainsAccurate(): void
    {
        $bfd = $this->applyLeave->getCompleteContractDefinition();

        // Identity
        $this->assertSame('HR.LEAVE.APPLY.APPLY_LEAVE', $bfd['identity']['functionId']);
        $this->assertSame('Apply Leave', $bfd['identity']['functionName']);

        // Response Contract
        $successFields = $bfd['contract']['response']['success_fields'] ?? [];
        $this->assertContains('leaveRequestId', $successFields);
        $this->assertContains('status', $successFields);
        $this->assertContains('remainingBalance', $successFields);
        $this->assertContains('daysRequested', $successFields);

        // Events
        $this->assertContains('LeaveRequested', $bfd['metadata']['eventsPublished']);
    }

    // ================================
    // GROUP 6: STORED LEAVE REQUESTS
    // ================================

    /**
     * Test 22: Created leave request is stored
     *
     * Verifies that leave requests are persisted (in-memory for this test)
     */
    public function testCreatedLeaveRequestIsStored(): void
    {
        $request = [
            'employeeId' => 'EMP-2026-004',
            'leaveType' => 'Annual',
            'startDate' => $this->futureDate(72),
            'endDate' => $this->futureDate(74),
        ];

        $caller = [
            'id' => 'EMP-2026-004',
            'role' => 'Employee',
        ];

        $response = $this->runtime->execute($this->applyLeave, $request, $caller);
        $leaveRequestId = $response['leaveRequestId'];

        // Verify it's stored
        $stored = ApplyLeave::getCreatedRequest($leaveRequestId);
        $this->assertNotNull($stored);
        $this->assertSame('EMP-2026-004', $stored['employeeId']);
        $this->assertSame('Annual', $stored['leaveType']);
    }

    /**
     * Test 23: Multiple requests can be created
     *
     * Tests that multiple requests can be submitted independently
     */
    public function testMultipleLeaveRequestsCanBeCreated(): void
    {
        $caller1 = ['id' => 'EMP-2026-001', 'role' => 'Employee'];
        $caller2 = ['id' => 'EMP-2026-002', 'role' => 'Employee'];

        // Request 1
        $request1 = [
            'employeeId' => 'EMP-2026-001',
            'leaveType' => 'Annual',
            'startDate' => $this->futureDate(78),
            'endDate' => $this->futureDate(80),
        ];
        $response1 = $this->runtime->execute($this->applyLeave, $request1, $caller1);

        // Request 2
        $request2 = [
            'employeeId' => 'EMP-2026-002',
            'leaveType' => 'Sick',
            'startDate' => $this->futureDate(82),
            'endDate' => $this->futureDate(83),
        ];
        $response2 = $this->runtime->execute($this->applyLeave, $request2, $caller2);

        // Verify both stored
        $allRequests = ApplyLeave::getCreatedRequests();
        $this->assertGreaterThanOrEqual(2, count($allRequests));
        $this->assertArrayHasKey($response1['leaveRequestId'], $allRequests);
        $this->assertArrayHasKey($response2['leaveRequestId'], $allRequests);
    }
}
