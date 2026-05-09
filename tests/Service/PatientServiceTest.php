// tests/Service/PatientServiceTest.php
namespace App\Tests\Service;

use App\Entity\Patient;
use App\Service\PatientService;
use PHPUnit\Framework\TestCase;

class PatientServiceTest extends TestCase
{
    private PatientService $service;

    protected function setUp(): void
    {
        $this->service = new PatientService();
    }

    public function testIsAdult(): void
    {
        $patient = new Patient();
        $patient->setAge(20);
        $this->assertTrue($this->service->isAdult($patient));

        $patient->setAge(16);
        $this->assertFalse($this->service->isAdult($patient));
    }

    public function testHasValidName(): void
    {
        $patient = new Patient();
        $patient->setNom('Doe');
        $patient->setPrenom('John');
        $this->assertTrue($this->service->hasValidName($patient));

        $patient->setNom('');
        $this->assertFalse($this->service->hasValidName($patient));
    }
}