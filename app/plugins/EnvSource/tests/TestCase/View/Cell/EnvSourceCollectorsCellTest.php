<?php
declare(strict_types=1);

namespace EnvSource\Test\TestCase\View\Cell;

use Cake\TestSuite\TestCase;
use EnvSource\View\Cell\EnvSourceCollectorsCell;

/**
 * EnvSource\View\Cell\EnvSourceCollectorsCell Test Case
 */
class EnvSourceCollectorsCellTest extends TestCase
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
     * @var \EnvSource\View\Cell\EnvSourceCollectorsCell
     */
    protected $EnvSourceCollectors;

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
        $this->EnvSourceCollectors = new EnvSourceCollectorsCell($this->request, $this->response);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->EnvSourceCollectors);

        parent::tearDown();
    }
}
