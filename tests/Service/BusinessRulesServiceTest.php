// tests/Service/BusinessRulesServiceTest.php
namespace App\Tests\Service;

use App\Service\BusinessRulesService;
use PHPUnit\Framework\TestCase;

class BusinessRulesServiceTest extends TestCase
{
    private BusinessRulesService $service;

    protected function setUp(): void
    {
        $this->service = new BusinessRulesService();
    }

    public function testValidateQuantity(): void
    {
        $this->assertTrue($this->service->validateQuantity(5));
        $this->expectException(\InvalidArgumentException::class);
        $this->service->validateQuantity(-1);
    }

    public function testValidatePrice(): void
    {
        $this->assertTrue($this->service->validatePrice(10.5));
        $this->expectException(\InvalidArgumentException::class);
        $this->service->validatePrice(0);
    }

    public function testValidateEventDates(): void
    {
        $start = new \DateTime('2026-01-01');
        $end = new \DateTime('2026-01-02');
        $this->assertTrue($this->service->validateEventDates($start, $end));

        $this->expectException(\InvalidArgumentException::class);
        $this->service->validateEventDates($end, $start);
    }

    public function testValidateRequiredField(): void
    {
        $this->assertTrue($this->service->validateRequiredField("test"));
        $this->expectException(\InvalidArgumentException::class);
        $this->service->validateRequiredField("   ");
    }

    public function testCanApplyDiscount(): void
    {
        $this->assertTrue($this->service->canApplyDiscount(150));
        $this->assertFalse($this->service->canApplyDiscount(50));
    }

    public function testValidateUniqueCode(): void
    {
        $this->assertTrue($this->service->validateUniqueCode("ABC123", ["XYZ"]));
        $this->expectException(\InvalidArgumentException::class);
        $this->service->validateUniqueCode("ABC", ["ABC", "XYZ"]);
    }

    public function testValidateWorkflow(): void
    {
        $steps = [['completed' => true], ['completed' => true]];
        $this->assertTrue($this->service->validateWorkflow($steps));

        $this->expectException(\RuntimeException::class);
        $steps[1]['completed'] = false;
        $this->service->validateWorkflow($steps);
    }

    public function testValidateSubscriptionDuration(): void
    {
        $this->assertTrue($this->service->validateSubscriptionDuration(3));
        $this->expectException(\InvalidArgumentException::class);
        $this->service->validateSubscriptionDuration(0);
    }

    public function testValidatePassword(): void
    {
        $this->assertTrue($this->service->validatePassword("abcdefgh"));
        $this->expectException(\InvalidArgumentException::class);
        $this->service->validatePassword("abc");
    }

    public function testValidateCriticalAction(): void
    {
        $this->assertTrue($this->service->validateCriticalAction(true));
        $this->expectException(\RuntimeException::class);
        $this->service->validateCriticalAction(false);
    }
}