<?php
declare(strict_types=1);

namespace CoreEnroller\Test\TestCase\View\Cell;

use Cake\TestSuite\TestCase;
use CoreEnroller\View\Cell\IdentifierCollectorsCell;

/**
 * CoreEnroller\View\Cell\IdentifierCollectorsCell Test Case
 */
class IdentifierCollectorsCellTest extends TestCase
{
    /**
     * Request mock
     *
     * @var \Cake\Http\ServerRequest|\PHPUnit\Framework\MockObject\MockObject
     */
    protected $request;

    /**
     * Response mock
     *
     * @var \Cake\Http\Response|\PHPUnit\Framework\MockObject\MockObject
     */
    protected $response;

    /**
     * Test subject
     *
     * @var \CoreEnroller\View\Cell\IdentifierCollectorsCell
     */
    protected $IdentifierCollectors;

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->request = $this->getMockBuilder('Cake\Http\ServerRequest')->getMock();
        $this->response = $this->getMockBuilder('Cake\Http\Response')->getMock();
        $this->IdentifierCollectors = new IdentifierCollectorsCell($this->request, $this->response);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->IdentifierCollectors);

        parent::tearDown();
    }

    /**
     * Test display method
     *
     * @return void
     * @uses \CoreEnroller\View\Cell\IdentifierCollectorsCell::display()
     */
    public function testDisplay(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
