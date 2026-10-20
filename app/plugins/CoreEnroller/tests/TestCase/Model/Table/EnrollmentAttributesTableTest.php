<?php
declare(strict_types=1);

namespace CoreEnroller\Test\TestCase\Model\Table;

use Cake\I18n\FrozenTime;
use Cake\ORM\Entity;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use CoreEnroller\Model\Table\EnrollmentAttributesTable;

/**
 * CoreEnroller\Model\Table\EnrollmentAttributesTable Test Case
 */
class EnrollmentAttributesTableTest extends TestCase
{
    protected EnrollmentAttributesTable $EnrollmentAttributes;

    protected FrozenTime $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->EnrollmentAttributes = TableRegistry::getTableLocator()->get('CoreEnroller.EnrollmentAttributes');
        $this->now = new FrozenTime('2026-10-19 13:30:00');
    }

    protected function tearDown(): void
    {
        unset($this->EnrollmentAttributes);
        parent::tearDown();
    }

    private function enrollmentAttribute(array $data): Entity
    {
        return new Entity($data + ['modifiable' => true, 'hidden' => false]);
    }

    public function testValidThroughOffsetAppliedWhenBlank(): void
    {
        $ea = $this->enrollmentAttribute(['attribute' => 'valid_through', 'default_value' => '365']);

        $this->assertSame('2027-10-19 23:59:59', $this->EnrollmentAttributes->resolveValidityDate('valid_through', '', $ea, $this->now));
        $this->assertSame('2027-10-19 23:59:59', $this->EnrollmentAttributes->resolveValidityDate('valid_through', null, $ea, $this->now));
    }

    public function testValidThroughExplicitDateKeptWhenModifiable(): void
    {
        $ea = $this->enrollmentAttribute(['attribute' => 'valid_through', 'default_value' => '365']);

        $this->assertSame(
            '2030-01-01 00:00:00',
            $this->EnrollmentAttributes->resolveValidityDate('valid_through', '2030-01-01 00:00:00', $ea, $this->now)
        );
    }

    public function testValidThroughOffsetForcedWhenReadOnly(): void
    {
        $ea = $this->enrollmentAttribute(['attribute' => 'valid_through', 'default_value' => '365', 'modifiable' => false]);

        $this->assertSame('2027-10-19 23:59:59', $this->EnrollmentAttributes->resolveValidityDate('valid_through', '2030-01-01', $ea, $this->now));
    }

    public function testValidThroughOffsetForcedWhenHidden(): void
    {
        $ea = $this->enrollmentAttribute(['attribute' => 'valid_through', 'default_value' => '30', 'hidden' => true]);

        $this->assertSame('2026-11-18 23:59:59', $this->EnrollmentAttributes->resolveValidityDate('valid_through', '365', $ea, $this->now));
    }

    public function testEmptyValueWithoutDefaultIsNull(): void
    {
        $from = $this->enrollmentAttribute(['attribute' => 'valid_from']);
        $through = $this->enrollmentAttribute(['attribute' => 'valid_through']);

        $this->assertNull($this->EnrollmentAttributes->resolveValidityDate('valid_from', '', $from, $this->now));
        $this->assertNull($this->EnrollmentAttributes->resolveValidityDate('valid_from', null, $from, $this->now));
        $this->assertNull($this->EnrollmentAttributes->resolveValidityDate('valid_through', '  ', $through, $this->now));
    }

    public function testValidFromIgnoresDefaultValueOffset(): void
    {
        $ea = $this->enrollmentAttribute(['attribute' => 'valid_from', 'default_value' => '365', 'modifiable' => false]);

        $this->assertNull($this->EnrollmentAttributes->resolveValidityDate('valid_from', '', $ea, $this->now));
        $this->assertSame(
            '2026-12-01 08:00:00',
            $this->EnrollmentAttributes->resolveValidityDate('valid_from', '2026-12-01 08:00:00', $ea, $this->now)
        );
    }

    public function testZeroOffsetReturnsEndOfToday(): void
    {
        $ea = $this->enrollmentAttribute(['attribute' => 'valid_through', 'default_value' => '0']);

        $this->assertSame('2026-10-19 23:59:59', $this->EnrollmentAttributes->resolveValidityDate('valid_through', '', $ea, $this->now));
    }

    public function testNonNumericDefaultIsIgnored(): void
    {
        $ea = $this->enrollmentAttribute(['attribute' => 'valid_through', 'default_value' => 'next year', 'modifiable' => false]);

        $this->assertNull($this->EnrollmentAttributes->resolveValidityDate('valid_through', '', $ea, $this->now));
        $this->assertSame(
            '2030-01-01 00:00:00',
            $this->EnrollmentAttributes->resolveValidityDate('valid_through', '2030-01-01', $ea, $this->now)
        );
    }
}
