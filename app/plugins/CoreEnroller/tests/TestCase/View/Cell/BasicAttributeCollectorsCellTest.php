<?php
declare(strict_types=1);

namespace CoreEnroller\Test\TestCase\View\Cell;

use Cake\TestSuite\TestCase;
use CoreEnroller\View\Cell\BasicAttributeCollectorsCell;

/**
 * CoreEnroller\View\Cell\BasicAttributeCollectorsCell Test Case
 */
class BasicAttributeCollectorsCellTest extends TestCase
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
     * @var \CoreEnroller\View\Cell\BasicAttributeCollectorsCell
     */
    protected $BasicAttributeCollectors;

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
        $this->BasicAttributeCollectors = new BasicAttributeCollectorsCell($this->request, $this->response);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->BasicAttributeCollectors);

        parent::tearDown();
    }

    /**
     * Test display method
     *
     * @return void
     * @uses \CoreEnroller\View\Cell\BasicAttributeCollectorsCell::display()
     */
    public function testDisplay(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
